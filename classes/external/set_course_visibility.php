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
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Gestión rápida de «Mis cursos»: mostrar u ocultar un curso a los estudiantes desde su tarjeta.
 *
 * Hace lo mismo que el ojo de /course/management.php: exige moodle/course:visibility en el curso y usa
 * course_change_visibility() del núcleo, que dispara course_updated y limpia las cachés.
 *
 * @package    theme_willedtech
 * @copyright  2026 WillEdTech
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_course_visibility extends external_api {

    /**
     * Parámetros.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Id del curso'),
            'visible' => new external_value(PARAM_BOOL, 'true para mostrar, false para ocultar'),
        ]);
    }

    /**
     * Cambia la visibilidad.
     *
     * @param int $courseid
     * @param bool $visible
     * @return array
     */
    public static function execute(int $courseid, bool $visible): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        ['courseid' => $courseid, 'visible' => $visible] = self::validate_parameters(self::execute_parameters(),
            ['courseid' => $courseid, 'visible' => $visible]);
        if ($courseid == SITEID) {
            throw new \invalid_parameter_exception('No se puede ocultar la portada del sitio.');
        }
        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('moodle/course:visibility', $context);

        course_change_visibility($courseid, $visible);
        $course = get_course($courseid);
        return ['id' => (int) $courseid, 'visible' => (bool) $course->visible];
    }

    /**
     * Respuesta.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Id del curso'),
            'visible' => new external_value(PARAM_BOOL, 'Visibilidad tras el cambio'),
        ]);
    }
}
