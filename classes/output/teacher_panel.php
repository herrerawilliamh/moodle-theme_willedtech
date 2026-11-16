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
 * Espacio docente del Área personal: qué calificar, dónde va cada grupo, quién se está quedando atrás y la semana.
 *
 * Solo usa datos del núcleo: entregas de tareas (assign_submission/assign_grades), finalización de actividades
 * (course_modules_completion), último acceso al curso (user_lastaccess) y eventos del calendario. Solo entran los
 * cursos donde el usuario puede editar o calificar.
 *
 * @package    theme_willedtech
 * @copyright  2026 WillEdTech
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_panel implements \renderable, \templatable {

    /** @var int Cursos que se muestran como tarjeta (los demás siguen en «Vista general del curso»). */
    private const MAX_COURSES = 6;

    /** @var int Días sin entrar a partir de los cuales un estudiante necesita atención. */
    private const IDLE_DAYS = 7;

    /** @var int Estudiantes en «Necesitan tu atención». */
    private const MAX_ATTENTION = 5;

    /** @var int Eventos en «Tu semana». */
    private const MAX_EVENTS = 6;

    /** @var \stdClass[] Cursos donde enseña, indexados por id. */
    private array $courses;

    /** @var array|null Datos calculados (se calculan una vez y los usan el encabezado y el panel). */
    private ?array $data = null;

    /**
     * @param \stdClass[] $courses Cursos matriculados del usuario (enrol_get_my_courses).
     */
    public function __construct(array $courses) {
        $this->courses = array_filter($courses, function($course) {
            $context = \context_course::instance($course->id);
            return has_capability('moodle/course:update', $context) || has_capability('mod/assign:grade', $context);
        });
    }

    /**
     * ¿Enseña en algún curso?
     *
     * @return bool
     */
    public function has_courses(): bool {
        return !empty($this->courses);
    }

    /**
     * Ids de los cursos donde enseña (los demás cursos matriculados son de estudiante).
     *
     * @return int[]
     */
    public function course_ids(): array {
        return array_keys($this->courses);
    }

    /**
     * Resumen de entregas por calificar, para el encabezado.
     *
     * @return array
     */
    public function grading(): array {
        return $this->compute()['grading'];
    }

    /**
     * Número de estudiantes distintos en sus cursos, para el encabezado.
     *
     * @return int
     */
    public function student_count(): int {
        return $this->compute()['studentcount'];
    }

    /**
     * Número de cursos donde enseña.
     *
     * @return int
     */
    public function course_count(): int {
        return count($this->courses);
    }

    /**
     * Datos para theme_willedtech/teacher_panel.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $data = $this->compute();
        return [
            'courses' => $data['cards'],
            'hascourses' => !empty($data['cards']),
            'morecourses' => count($this->courses) > self::MAX_COURSES,
            'attention' => $data['attention'],
            'hasattention' => !empty($data['attention']),
            'idledays' => self::IDLE_DAYS,
            'events' => $data['events'],
            'hasevents' => !empty($data['events']),
            'calendarurl' => (new \moodle_url('/calendar/view.php', ['view' => 'upcoming']))->out(false),
        ];
    }

    /**
     * Calcula todo una sola vez.
     *
     * @return array
     */
    private function compute(): array {
        if ($this->data !== null) {
            return $this->data;
        }
        $pending = $this->pending_by_assignment();
        $cards = [];
        $idle = [];
        $students = [];
        foreach (array_slice($this->courses, 0, self::MAX_COURSES, true) as $course) {
            $card = $this->course_card($course, $pending, $idle, $students);
            $cards[] = $card;
        }
        // Los cursos que no tienen tarjeta también cuentan para el total de estudiantes.
        foreach (array_slice($this->courses, self::MAX_COURSES, null, true) as $course) {
            foreach ($this->student_ids($course) as $userid) {
                $students[$userid] = true;
            }
        }

        usort($idle, fn($a, $b) => $b['days'] <=> $a['days']);
        $this->data = [
            'grading' => $this->grading_summary($pending),
            'cards' => $cards,
            'attention' => array_slice($idle, 0, self::MAX_ATTENTION),
            'events' => $this->events(),
            'studentcount' => count($students),
        ];
        return $this->data;
    }

    /**
     * Entregas enviadas y sin calificar, agrupadas por tarea, en los cursos donde puede calificar.
     *
     * Cuenta como pendiente una entrega «enviada» sin nota, o modificada después de calificarla (reenvío).
     *
     * @return \stdClass[] id de tarea => {course, cmid, name, pending, oldest}
     */
    private function pending_by_assignment(): array {
        global $DB;
        $gradable = array_keys(array_filter($this->courses,
            fn($c) => has_capability('mod/assign:grade', \context_course::instance($c->id))));
        if (!$gradable) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($gradable, SQL_PARAMS_NAMED);
        $params['submitted'] = 'submitted'; // ASSIGN_SUBMISSION_STATUS_SUBMITTED, sin cargar locallib de mod_assign.
        $rows = $DB->get_records_sql(
            "SELECT a.id, a.course, a.name, COUNT(s.id) AS pending, MIN(s.timemodified) AS oldest
               FROM {assign_submission} s
               JOIN {assign} a ON a.id = s.assignment
          LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid
                                       AND g.attemptnumber = s.attemptnumber
              WHERE s.latest = 1 AND s.status = :submitted AND s.userid > 0 AND a.course $insql
                AND (g.id IS NULL OR g.grade IS NULL OR g.grade < 0 OR g.timemodified < s.timemodified)
           GROUP BY a.id, a.course, a.name",
            $params
        );
        foreach ($rows as $row) {
            $cm = get_fast_modinfo($row->course)->get_instances_of('assign')[$row->id] ?? null;
            $row->cmid = $cm ? $cm->id : null;
        }
        return $rows;
    }

    /**
     * Total por calificar y a qué tarea llevar primero (la entrega más antigua).
     *
     * @param \stdClass[] $pending
     * @return array
     */
    private function grading_summary(array $pending): array {
        $total = array_sum(array_map(fn($r) => (int) $r->pending, $pending));
        $first = null;
        foreach ($pending as $row) {
            if ($row->cmid && ($first === null || $row->oldest < $first->oldest)) {
                $first = $row;
            }
        }
        $days = $first ? (int) floor((time() - $first->oldest) / DAYSECS) : 0;
        return [
            'total' => $total,
            'has' => $total > 0,
            'activities' => count($pending),
            'oldestdays' => $days,
            'hasolddays' => $days > 0,
            'url' => $first ? (new \moodle_url('/mod/assign/view.php', ['id' => $first->cmid, 'action' => 'grading']))->out(false)
                : null,
        ];
    }

    /**
     * Estudiantes activos del curso (quienes aparecen en los informes de finalización).
     *
     * @param \stdClass $course
     * @return int[]
     */
    private function student_ids(\stdClass $course): array {
        global $DB;
        [$sql, $params] = get_enrolled_sql(\context_course::instance($course->id),
            'moodle/course:isincompletionreports', 0, true);
        return $DB->get_fieldset_sql($sql, $params);
    }

    /**
     * Tarjeta de un curso: dónde va el grupo por unidad, progreso medio, pendientes e inactivos.
     *
     * @param \stdClass $course
     * @param \stdClass[] $pending Entregas pendientes por tarea.
     * @param array $idle Acumula estudiantes inactivos de todos los cursos.
     * @param array $students Acumula ids de estudiantes de todos los cursos.
     * @return array
     */
    private function course_card(\stdClass $course, array $pending, array &$idle, array &$students): array {
        global $DB;
        $context = \context_course::instance($course->id);
        $studentids = $this->student_ids($course);
        foreach ($studentids as $userid) {
            $students[$userid] = true;
        }

        // Unidades = secciones con actividades que tienen finalización, en el orden del curso.
        $modinfo = get_fast_modinfo($course);
        $units = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->completion == COMPLETION_TRACKING_NONE || !$cm->uservisible || $cm->deletioninprogress) {
                continue;
            }
            $units[$cm->sectionnum][] = $cm->id;
        }
        ksort($units);
        $units = array_values($units);

        $cohort = [];
        $avg = null;
        if ($units && $studentids && $course->enablecompletion) {
            $cmids = array_merge(...$units);
            [$cmsql, $cmparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cm');
            [$usql, $uparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'u');
            $done = [];
            $rs = $DB->get_recordset_sql(
                "SELECT id, userid, coursemoduleid
                   FROM {course_modules_completion}
                  WHERE completionstate > 0 AND coursemoduleid $cmsql AND userid $usql",
                $cmparams + $uparams
            );
            foreach ($rs as $row) {
                $done[$row->userid][$row->coursemoduleid] = true;
            }
            $rs->close();

            // Cada estudiante está en la primera unidad que no ha terminado (o en la última si terminó todo).
            $counts = array_fill(0, count($units), 0);
            $total = count($cmids);
            $sum = 0;
            foreach ($studentids as $userid) {
                $mine = $done[$userid] ?? [];
                $sum += count($mine) / $total;
                $at = count($units) - 1;
                foreach ($units as $index => $unitcms) {
                    if (count(array_intersect_key(array_flip($unitcms), $mine)) < count($unitcms)) {
                        $at = $index;
                        break;
                    }
                }
                $counts[$at]++;
            }
            $avg = (int) round($sum / count($studentids) * 100);
            $max = max($counts) ?: 1;
            $current = array_search($max, $counts, true);
            foreach ($counts as $index => $count) {
                $cohort[] = [
                    'height' => $count ? max(8, (int) round($count / $max * 56)) : 4,
                    'state' => $index < $current ? 'done' : ($index === $current ? 'now' : 'todo'),
                    'label' => get_string('teacher_unitcount', 'theme_willedtech',
                        ['unit' => $index + 1, 'count' => $count]),
                ];
            }
        }

        // Inactivos: sin entrar al curso en IDLE_DAYS días o nunca.
        $idlecount = 0;
        if ($studentids) {
            [$usql, $uparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'u');
            $access = $DB->get_records_select_menu('user_lastaccess', "courseid = :courseid AND userid $usql",
                ['courseid' => $course->id] + $uparams, '', 'userid, timeaccess');
            $limit = time() - self::IDLE_DAYS * DAYSECS;
            $idleids = array_filter($studentids, fn($id) => empty($access[$id]) || $access[$id] < $limit);
            $idlecount = count($idleids);
            if ($idleids) {
                $users = $DB->get_records_list('user', 'id', $idleids);
                foreach ($idleids as $userid) {
                    if (empty($users[$userid])) {
                        continue;
                    }
                    $days = empty($access[$userid]) ? PHP_INT_MAX : (int) floor((time() - $access[$userid]) / DAYSECS);
                    $idle[] = [
                        'days' => $days,
                        'name' => fullname($users[$userid]),
                        'initials' => \core_text::strtoupper(\core_text::substr($users[$userid]->firstname, 0, 1)
                            . \core_text::substr($users[$userid]->lastname, 0, 1)),
                        'course' => format_string($course->fullname, true, ['context' => $context]),
                        'reason' => $days === PHP_INT_MAX ? get_string('teacher_never', 'theme_willedtech')
                            : get_string('teacher_idledays', 'theme_willedtech', $days),
                        'messageurl' => (new \moodle_url('/message/index.php', ['id' => $userid]))->out(false),
                        'profileurl' => (new \moodle_url('/user/view.php', ['id' => $userid, 'course' => $course->id]))
                            ->out(false),
                    ];
                }
            }
        }

        $coursepending = array_sum(array_map(fn($r) => (int) $r->pending,
            array_filter($pending, fn($r) => (int) $r->course === (int) $course->id)));

        return [
            'name' => format_string($course->fullname, true, ['context' => $context]),
            'shortname' => format_string($course->shortname, true, ['context' => $context]),
            'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'gradesurl' => (new \moodle_url('/grade/report/grader/index.php', ['id' => $course->id]))->out(false),
            'students' => count($studentids),
            'hascohort' => !empty($cohort),
            'cohort' => $cohort,
            'units' => count($units),
            'cohortlabel' => $cohort ? implode('; ', array_column($cohort, 'label')) : '',
            'pending' => $coursepending,
            'pendingalert' => $coursepending > 0,
            'avg' => $avg === null ? '—' : $avg . ' %',
            'idle' => $idlecount,
            'idlealert' => $idlecount > 0,
        ];
    }

    /**
     * Próximos 7 días del calendario en sus cursos.
     *
     * @return array
     */
    private function events(): array {
        global $DB;
        if (!$this->courses) {
            return [];
        }
        global $USER;
        // Mismo alcance que el calendario: grupos (todos si puede verlos), anulaciones y eventos personales.
        [$scope, $params] = \theme_willedtech\local\event_scope::where(array_keys($this->courses), (int) $USER->id);
        $now = time();
        $params += ['from' => $now, 'to' => $now + 7 * DAYSECS];
        $rows = $DB->get_records_sql(
            "SELECT e.id, e.name, e.courseid, e.timestart
               FROM {event} e
              WHERE $scope AND e.timestart BETWEEN :from AND :to
           ORDER BY e.timestart ASC, e.id ASC",
            $params, 0, self::MAX_EVENTS);
        $out = [];
        foreach ($rows as $row) {
            $context = \context_course::instance($row->courseid);
            $out[] = [
                'weekday' => userdate($row->timestart, '%a'),
                'day' => userdate($row->timestart, '%d'),
                'name' => format_string($row->name, true, ['context' => $context]),
                'meta' => format_string($this->courses[$row->courseid]->shortname, true, ['context' => $context])
                    . ' · ' . userdate($row->timestart, get_string('strftimetime', 'core_langconfig')),
            ];
        }
        return $out;
    }
}
