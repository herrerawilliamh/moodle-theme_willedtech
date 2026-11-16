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

namespace theme_willedtech\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Gestión rápida de «Mis cursos»: qué puede hacer el usuario en cada curso que ve en sus tarjetas.
 *
 * Solo lectura. Devuelve permisos y visibilidad; nunca datos de otros usuarios. Los cursos que no existen o que el
 * usuario no puede ver se omiten en silencio, así una lista manipulada no revela nada.
 *
 * @package    theme_willedtech
 * @copyright  2026 WillEdTech
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_quickstate extends external_api {

    /** Tope de cursos por llamada: una página de «Mostrar todos» cabe de sobra. */
    const MAX_COURSES = 200;

    /**
     * Parámetros.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Id del curso'),
                'Cursos que muestran las tarjetas'
            ),
        ]);
    }

    /**
     * Permisos de gestión por curso.
     *
     * @param int[] $courseids
     * @return array
     */
    public static function execute(array $courseids): array {
        ['courseids' => $courseids] = self::validate_parameters(self::execute_parameters(), ['courseids' => $courseids]);
        self::validate_context(\context_system::instance());

        $courseids = array_slice(array_unique(array_filter($courseids, fn($id) => $id > SITEID)), 0, self::MAX_COURSES);
        $result = [];
        foreach ($courseids as $courseid) {
            $context = \context_course::instance($courseid, IGNORE_MISSING);
            if (!$context) {
                continue;
            }
            $course = get_course($courseid);
            if (!can_access_course($course) && !has_capability('moodle/course:view', $context)) {
                continue;
            }
            $result[] = [
                'id' => (int) $courseid,
                'visible' => (bool) $course->visible,
                'canvisibility' => has_capability('moodle/course:visibility', $context),
                'canupdate' => has_capability('moodle/course:update', $context),
                'canparticipants' => has_capability('moodle/course:viewparticipants', $context),
            ];
        }
        return $result;
    }

    /**
     * Respuesta.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Id del curso'),
            'visible' => new external_value(PARAM_BOOL, 'Visible para estudiantes'),
            'canvisibility' => new external_value(PARAM_BOOL, 'Puede mostrar u ocultar el curso'),
            'canupdate' => new external_value(PARAM_BOOL, 'Puede editar los ajustes'),
            'canparticipants' => new external_value(PARAM_BOOL, 'Puede ver participantes'),
        ]));
    }
}
