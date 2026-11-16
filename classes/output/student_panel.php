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

/**
 * Área personal del estudiante: «Lo siguiente», «Esta semana», calificaciones recientes y avisos de sus docentes.
 *
 * «Lo siguiente» es la primera actividad sin terminar (en el orden del curso) del curso que el estudiante usó por
 * última vez. Todo sale del núcleo: finalización (completion_info), último acceso (user_lastaccess), calendario,
 * entregas de tareas, libro de calificaciones y foros de avisos. Respeta la visibilidad de cada actividad.
 *
 * @package    theme_willedtech
 * @copyright  2026 WillEdTech
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_panel implements \renderable, \templatable {

    /** @var int Días que cubre «Esta semana». */
    private const WEEK_DAYS = 7;

    /** @var int Elementos por lista. */
    private const MAX_EVENTS = 5;
    private const MAX_GRADES = 3;
    private const MAX_NEWS = 3;

    /** @var \stdClass[] Cursos matriculados, indexados por id. */
    private array $courses;

    /** @var array|null Datos calculados una vez. */
    private ?array $data = null;

    /**
     * @param \stdClass[] $courses Cursos matriculados del usuario (enrol_get_my_courses).
     */
    public function __construct(array $courses) {
        $this->courses = $courses;
    }

    /**
     * Entregas pendientes esta semana, para el encabezado.
     *
     * @return int
     */
    public function due_this_week(): int {
        return $this->compute()['duecount'];
    }

    /**
     * Datos para theme_willedtech/student_panel.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $data = $this->compute();
        return [
            'next' => $data['next'],
            'hasnext' => $data['next'] !== null,
            'events' => $data['events'],
            'hasevents' => !empty($data['events']),
            'grades' => $data['grades'],
            'hasgrades' => !empty($data['grades']),
            'news' => $data['news'],
            'hasnews' => !empty($data['news']),
            'calendarurl' => (new \moodle_url('/calendar/view.php', ['view' => 'upcoming']))->out(false),
            'gradesurl' => (new \moodle_url('/grade/report/overview/index.php'))->out(false),
        ];
    }

    /**
     * Calcula todo una sola vez.
     *
     * @return array
     */
    private function compute(): array {
        if ($this->data === null) {
            [$events, $duecount] = $this->events();
            $this->data = [
                'next' => $this->courses ? $this->next_step() : null,
                'events' => $events,
                'duecount' => $duecount,
                'grades' => $this->courses ? $this->grades() : [],
                'news' => $this->courses ? $this->news() : [],
            ];
        }
        return $this->data;
    }

    /**
     * Curso usado más recientemente y su primera actividad sin terminar.
     *
     * @return array|null
     */
    private function next_step(): ?array {
        global $DB, $USER;
        [$insql, $params] = $DB->get_in_or_equal(array_keys($this->courses), SQL_PARAMS_NAMED);
        $access = $DB->get_records_select_menu('user_lastaccess', "userid = :userid AND courseid $insql",
            ['userid' => $USER->id] + $params, '', 'courseid, timeaccess');
        $ordered = $this->courses;
        uasort($ordered, fn($a, $b) => ($access[$b->id] ?? 0) <=> ($access[$a->id] ?? 0));

        foreach ($ordered as $course) {
            $step = $this->next_in_course($course);
            if ($step !== null) {
                return $step;
            }
        }
        // Nada pendiente con finalización: al menos llevar de vuelta al último curso.
        $course = reset($ordered);
        $context = \context_course::instance($course->id);
        return [
            'coursename' => format_string($course->fullname, true, ['context' => $context]),
            'courseurl' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'hasactivity' => false,
            'hasroute' => false,
        ];
    }

    /**
     * Primera actividad visible con finalización que el estudiante no ha terminado, y su ruta por unidades.
     *
     * @param \stdClass $course
     * @return array|null null si el curso no usa finalización o ya está todo hecho.
     */
    private function next_in_course(\stdClass $course): ?array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return null;
        }
        $modinfo = get_fast_modinfo($course);
        $units = [];
        $next = null;
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || $cm->deletioninprogress || !$completion->is_enabled($cm)) {
                continue;
            }
            $done = $completion->get_data($cm, true)->completionstate != COMPLETION_INCOMPLETE;
            $units[$cm->sectionnum][] = $done;
            if (!$done && $next === null) {
                $next = $cm;
            }
        }
        if ($next === null) {
            return null;
        }
        ksort($units);
        $sectionnums = array_keys($units);
        // sectionnum puede llegar como cadena desde la caché de modinfo: se compara como entero.
        $current = (int) array_search((int) $next->sectionnum, array_map('intval', $sectionnums), true);
        $route = [];
        foreach (array_values($units) as $index => $states) {
            $state = $index === $current ? 'now' : (!in_array(false, $states, true) ? 'done' : 'todo');
            $route[] = [
                'state' => $state,
                'first' => $index === 0,
                'label' => get_string('student_unitstate', 'theme_willedtech', [
                    'unit' => $index + 1,
                    'state' => get_string('student_state_' . $state, 'theme_willedtech'),
                ]),
            ];
        }
        $all = array_merge(...array_values($units));
        $unitstates = $units[$next->sectionnum];
        $position = count(array_filter($unitstates)) + 1;
        $context = \context_course::instance($course->id);
        $section = $modinfo->get_section_info($next->sectionnum);

        return [
            'coursename' => format_string($course->fullname, true, ['context' => $context]),
            'courseurl' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'hasactivity' => true,
            'unitname' => get_string('student_unitname', 'theme_willedtech', [
                'unit' => $current + 1,
                'name' => get_section_name($course, $section),
            ]),
            'activityname' => $next->get_formatted_name(),
            'activitytype' => (string) $next->get_module_type_name(),
            'activityurl' => $next->url ? $next->url->out(false) : (new \moodle_url('/course/view.php',
                ['id' => $course->id], 'module-' . $next->id))->out(false),
            'iconurl' => $next->get_icon_url()->out(false),
            // Propósito del módulo (contenido, evaluación…): Boost colorea el contenedor del ícono en claro y oscuro.
            'purpose' => plugin_supports('mod', $next->modname, FEATURE_MOD_PURPOSE, MOD_PURPOSE_OTHER),
            'position' => get_string('student_position', 'theme_willedtech',
                ['pos' => min($position, count($unitstates)), 'total' => count($unitstates)]),
            'hasroute' => count($route) > 1,
            'route' => $route,
            'progress' => get_string('student_progress', 'theme_willedtech', [
                'unit' => $current + 1,
                'units' => count($route),
                'percent' => (int) round(count(array_filter($all)) / count($all) * 100),
            ]),
        ];
    }

    /**
     * Eventos de sus cursos en los próximos días y cuántas entregas siguen pendientes.
     *
     * @return array [lista para la plantilla, número de entregas sin enviar]
     */
    private function events(): array {
        global $DB, $USER;
        if (!$this->courses) {
            return [[], 0];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($this->courses), SQL_PARAMS_NAMED);
        $now = time();
        $params += ['from' => $now, 'to' => $now + self::WEEK_DAYS * DAYSECS];
        $rows = $DB->get_records_select('event',
            "courseid $insql AND visible = 1 AND timestart BETWEEN :from AND :to",
            $params, 'timestart ASC', 'id, name, courseid, modulename, instance, eventtype, timestart', 0, self::MAX_EVENTS);

        $out = [];
        $duecount = 0;
        $today = usergetmidnight($now);
        foreach ($rows as $row) {
            $course = $this->courses[$row->courseid];
            $context = \context_course::instance($row->courseid);
            if ($row->modulename) {
                $cm = get_fast_modinfo($course)->get_instances_of($row->modulename)[$row->instance] ?? null;
                if (!$cm || !$cm->uservisible) {
                    continue;
                }
            }
            $submitted = false;
            if ($row->modulename === 'assign' && $row->eventtype === 'due') {
                $submitted = $DB->record_exists('assign_submission', ['assignment' => $row->instance,
                    'userid' => $USER->id, 'latest' => 1, 'status' => 'submitted']);
                if (!$submitted) {
                    $duecount++;
                }
            }
            $days = (int) floor((usergetmidnight($row->timestart) - $today) / DAYSECS);
            if ($submitted) {
                [$state, $tone] = [get_string('student_submitted', 'theme_willedtech'), 'ok'];
            } else if ($days <= 0) {
                [$state, $tone] = [get_string('student_today', 'theme_willedtech'), 'due'];
            } else if ($days === 1) {
                [$state, $tone] = [get_string('student_tomorrow', 'theme_willedtech'), 'due'];
            } else {
                [$state, $tone] = [get_string('student_indays', 'theme_willedtech', $days), 'soft'];
            }
            $out[] = [
                'weekday' => userdate($row->timestart, '%a'),
                'day' => userdate($row->timestart, '%d'),
                'name' => format_string($row->name, true, ['context' => $context]),
                'course' => format_string($course->fullname, true, ['context' => $context]),
                'time' => userdate($row->timestart, get_string('strftimetime', 'core_langconfig')),
                'state' => $state,
                'tone' => $tone,
            ];
        }
        return [$out, $duecount];
    }

    /**
     * Últimas notas visibles de actividades en sus cursos.
     *
     * @return array
     */
    private function grades(): array {
        global $DB, $USER, $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        [$insql, $params] = $DB->get_in_or_equal(array_keys($this->courses), SQL_PARAMS_NAMED);
        $now = time();
        $rows = $DB->get_records_sql(
            "SELECT gg.id, gg.itemid, gg.finalgrade, gg.timemodified, gi.courseid
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid
              WHERE gg.userid = :userid AND gg.finalgrade IS NOT NULL AND gi.itemtype = 'mod'
                AND gi.courseid $insql
                AND (gi.hidden = 0 OR (gi.hidden > 1 AND gi.hidden < :now1))
                AND (gg.hidden = 0 OR (gg.hidden > 1 AND gg.hidden < :now2))
           ORDER BY gg.timemodified DESC",
            ['userid' => $USER->id, 'now1' => $now, 'now2' => $now] + $params, 0, self::MAX_GRADES
        );
        $out = [];
        foreach ($rows as $row) {
            $item = \grade_item::fetch(['id' => $row->itemid]);
            if (!$item) {
                continue;
            }
            $context = \context_course::instance($row->courseid);
            $out[] = [
                'name' => format_string($item->get_name(), true, ['context' => $context]),
                'course' => format_string($this->courses[$row->courseid]->fullname, true, ['context' => $context]),
                'when' => userdate($row->timemodified, get_string('strftimedateshort', 'core_langconfig')),
                'grade' => grade_format_gradevalue($row->finalgrade, $item, true),
                'url' => (new \moodle_url('/grade/report/user/index.php', ['id' => $row->courseid]))->out(false),
            ];
        }
        return $out;
    }

    /**
     * Últimos avisos (foro de avisos de cada curso) que el estudiante puede ver.
     *
     * @return array
     */
    private function news(): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(array_keys($this->courses), SQL_PARAMS_NAMED);
        $now = time();
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $rows = $DB->get_records_sql(
            "SELECT d.id, d.name, d.timemodified, d.forum, d.course, p.message, p.messageformat, u.id AS authorid, $fields
               FROM {forum_discussions} d
               JOIN {forum} f ON f.id = d.forum AND f.type = 'news'
               JOIN {forum_posts} p ON p.id = d.firstpost
               JOIN {user} u ON u.id = d.userid
              WHERE d.course $insql AND (d.timestart = 0 OR d.timestart <= :now1)
                AND (d.timeend = 0 OR d.timeend > :now2)
           ORDER BY d.timemodified DESC",
            ['now1' => $now, 'now2' => $now] + $params, 0, self::MAX_NEWS * 3
        );
        $out = [];
        foreach ($rows as $row) {
            $cm = get_fast_modinfo($row->course)->get_instances_of('forum')[$row->forum] ?? null;
            if (!$cm || !$cm->uservisible) {
                continue;
            }
            $context = \context_module::instance($cm->id);
            $text = html_to_text(format_text($row->message, $row->messageformat, ['context' => $context]), 0, false);
            $out[] = [
                'title' => format_string($row->name, true, ['context' => $context]),
                'excerpt' => shorten_text(trim($text), 140),
                'initials' => \core_text::strtoupper(\core_text::substr($row->firstname, 0, 1)
                    . \core_text::substr($row->lastname, 0, 1)),
                'meta' => fullname($row) . ' · ' . format_string($this->courses[$row->course]->fullname, true,
                    ['context' => \context_course::instance($row->course)]) . ' · '
                    . userdate($row->timemodified, get_string('strftimedateshort', 'core_langconfig')),
                'url' => (new \moodle_url('/mod/forum/discuss.php', ['d' => $row->id]))->out(false),
            ];
            if (count($out) >= self::MAX_NEWS) {
                break;
            }
        }
        return $out;
    }
}
