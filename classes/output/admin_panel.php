<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace theme_willedtech\output;

use core\check\result;

/**
 * Panel de la portada de Administración del sitio: estado del Campus, uso, tareas frecuentes y cambios recientes.
 *
 * Todo sale de APIs y tablas del núcleo (comprobaciones de estado, usuarios, cursos, matrículas y config_log);
 * no se inventa ningún dato. Solo lo ven quienes tienen moodle/site:config (lo comprueba el renderer).
 *
 * @package    theme_willedtech
 * @copyright  2026 WillEdTech
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_panel implements \renderable, \templatable {

    /** @var string[] Orden de gravedad: lo urgente primero. */
    private const SEVERITY = [
        result::CRITICAL => 0,
        result::ERROR => 1,
        result::WARNING => 2,
        result::UNKNOWN => 3,
        result::INFO => 4,
        result::OK => 5,
    ];

    /** @var int Cuántos cambios de ajustes se muestran. */
    private const RECENT_LIMIT = 5;

    /**
     * Datos para theme_willedtech/admin_panel.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $checks = $this->checks();
        $recent = $this->recent();
        $problems = count(array_filter($checks, fn($c) => !$c['isok']));
        $okcount = count($checks) - $problems;

        return [
            // Lo que falla se ve siempre; lo que está en orden queda plegado para no competir con ello.
            'problemchecks' => array_values(array_filter($checks, fn($c) => !$c['isok'])),
            'okchecks' => array_values(array_filter($checks, fn($c) => $c['isok'])),
            'hasproblems' => $problems > 0,
            'problems' => $problems,
            'okcount' => $okcount,
            'checkcount' => count($checks),
            'statusurl' => (new \moodle_url('/report/status/index.php'))->out(false),
            'usage' => $this->usage(),
            'tasks' => $this->tasks(),
            'recent' => $recent,
            'hasrecent' => !empty($recent),
            'configlogurl' => (new \moodle_url('/report/configlog/index.php'))->out(false),
        ];
    }

    /**
     * Comprobaciones de estado del núcleo y de los plugins, más correo saliente y actualizaciones pendientes.
     *
     * @return array
     */
    private function checks(): array {
        global $CFG;
        $items = [];
        foreach (\core\check\manager::get_status_checks() as $check) {
            try {
                $result = $check->get_result();
            } catch (\Throwable $e) {
                // Una comprobación rota no puede tumbar la página de administración.
                continue;
            }
            if ($result->get_status() === result::NA) {
                continue;
            }
            $link = $check->get_action_link();
            $items[] = $this->item(
                $result->get_status(),
                $check->get_name(),
                $result->get_summary(),
                $link ? $link->url : null,
                $link ? (string) $link->text : null
            );
        }

        // Sin servidor SMTP Moodle intenta enviar con la función mail() de PHP, que en contenedores casi nunca entrega.
        if (empty($CFG->noemailever)) {
            $hassmtp = !empty($CFG->smtphosts);
            $items[] = $this->item(
                $hassmtp ? result::OK : result::WARNING,
                get_string('adminpanel_mail', 'theme_willedtech'),
                get_string($hassmtp ? 'adminpanel_mail_ok' : 'adminpanel_mail_missing', 'theme_willedtech'),
                $hassmtp ? null : new \moodle_url('/admin/settings.php', ['section' => 'outgoingmailconfig']),
                $hassmtp ? null : get_string('adminpanel_mail_action', 'theme_willedtech')
            );
        }

        // Actualizaciones ya conocidas por el comprobador (no hace llamadas de red en esta página).
        if (\core\update\checker::instance()->enabled()) {
            $updates = count(\core_plugin_manager::instance()->available_updates());
            $items[] = $this->item(
                $updates ? result::WARNING : result::OK,
                get_string('adminpanel_updates', 'theme_willedtech'),
                $updates ? get_string('adminpanel_updates_some', 'theme_willedtech', $updates)
                    : get_string('adminpanel_updates_none', 'theme_willedtech'),
                $updates ? new \moodle_url('/admin/index.php') : null,
                $updates ? get_string('adminpanel_updates_action', 'theme_willedtech') : null
            );
        }

        usort($items, fn($a, $b) => $a['severity'] <=> $b['severity']);
        return $items;
    }

    /**
     * Una fila de comprobación para la plantilla.
     *
     * @param string $status Constante de \core\check\result.
     * @param string $name
     * @param string $summary Puede traer HTML del núcleo; se muestra como texto.
     * @param \moodle_url|null $url
     * @param string|null $action
     * @return array
     */
    private function item(string $status, string $name, string $summary, ?\moodle_url $url, ?string $action): array {
        $isok = in_array($status, [result::OK, result::INFO], true);
        $tone = $isok ? 'ok' : (in_array($status, [result::ERROR, result::CRITICAL], true) ? 'error' : 'warning');
        return [
            'severity' => self::SEVERITY[$status] ?? 3,
            'isok' => $isok,
            'tone' => $tone,
            'name' => $name,
            'summary' => trim(html_to_text($summary, 0, false)),
            'state' => get_string('adminpanel_state_' . $tone, 'theme_willedtech'),
            'hasaction' => !$isok && $url !== null,
            'actionurl' => $url ? $url->out(false) : null,
            'actiontext' => $action,
        ];
    }

    /**
     * Uso del sitio en los últimos 30 días.
     *
     * @return array
     */
    private function usage(): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        $now = time();
        $base = 'deleted = 0 AND id <> :guest';
        $params = ['guest' => $CFG->siteguest];

        $total = $DB->count_records_select('user', $base, $params);
        $active = $DB->count_records_select('user', "$base AND suspended = 0 AND lastaccess > :since",
            $params + ['since' => $now - 30 * DAYSECS]);
        $online = $DB->count_records_select('user', "$base AND lastaccess > :since",
            $params + ['since' => $now - 5 * MINSECS]);
        $courses = $DB->count_records_select('course', 'id <> :site AND visible = 1', ['site' => SITEID]);
        $enrolments = $DB->count_records('user_enrolments', ['status' => ENROL_USER_ACTIVE]);
        $percent = $total ? (int) round($active / $total * 100) : 0;

        return [
            'active' => $active,
            'total' => $total,
            'percent' => $percent,
            'online' => $online,
            'courses' => $courses,
            'enrolments' => $enrolments,
        ];
    }

    /**
     * Accesos a las tareas que más repite un administrador.
     *
     * @return array
     */
    private function tasks(): array {
        $category = \core_course_category::get_default();
        $tasks = [
            ['createuser', new \moodle_url('/user/editadvanced.php', ['id' => -1]), 'user-plus'],
            ['uploadusers', new \moodle_url('/admin/tool/uploaduser/index.php'), 'upload'],
            ['createcourse', new \moodle_url('/course/edit.php', ['category' => $category->id]), 'plus'],
            ['managecourses', new \moodle_url('/course/management.php'), 'folder'],
            ['cohorts', new \moodle_url('/cohort/index.php'), 'users'],
            ['appearance', new \moodle_url('/admin/category.php', ['category' => 'appearance']), 'palette'],
        ];
        $out = [];
        // Kopere Dashboard (local_kopere_dashboard) va primero y destacado, pero solo donde está instalado:
        // así el mismo tema no deja un enlace roto en un sitio que no lo tenga.
        if (\core_plugin_manager::instance()->get_plugin_info('local_kopere_dashboard')) {
            $out[] = [
                'title' => get_string('adminpanel_task_kopere', 'theme_willedtech'),
                'hint' => get_string('adminpanel_task_kopere_hint', 'theme_willedtech'),
                'url' => (new \moodle_url('/local/kopere_dashboard/'))->out(false),
                'icon_chart' => true,
                'featured' => true,
            ];
        }
        foreach ($tasks as [$key, $url, $icon]) {
            $out[] = [
                'title' => get_string('adminpanel_task_' . $key, 'theme_willedtech'),
                'hint' => get_string('adminpanel_task_' . $key . '_hint', 'theme_willedtech'),
                'url' => $url->out(false),
                'icon_' . str_replace('-', '', $icon) => true,
            ];
        }
        return $out;
    }

    /**
     * Últimos ajustes cambiados (tabla config_log del núcleo).
     *
     * @return array
     */
    private function recent(): array {
        global $DB;
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $rows = $DB->get_records_sql(
            "SELECT cl.id, cl.timemodified, cl.plugin, cl.name, u.id AS userid, $fields
               FROM {config_log} cl
          LEFT JOIN {user} u ON u.id = cl.userid
           ORDER BY cl.timemodified DESC, cl.id DESC",
            [], 0, self::RECENT_LIMIT
        );
        $out = [];
        foreach ($rows as $row) {
            $setting = $row->plugin ? $row->plugin . ' | ' . $row->name : $row->name;
            $who = $row->userid ? fullname($row) : get_string('adminpanel_system', 'theme_willedtech');
            $out[] = [
                'setting' => $setting,
                'meta' => $who . ' · ' . userdate($row->timemodified, get_string('strftimedatetimeshort', 'core_langconfig')),
            ];
        }
        return $out;
    }
}
