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

namespace theme_willedtech\local;

/**
 * Qué eventos del calendario de unos cursos puede ver un usuario, para consultar la tabla {event} directamente.
 *
 * Reproduce las reglas de alcance del calendario que importan en los paneles:
 *  - eventos de grupo: solo de los grupos del usuario, o de todos si tiene moodle/site:accessallgroups en el curso;
 *  - anulaciones (overrides) de actividades: las de grupo siguen la regla anterior y las de usuario solo las suyas;
 *  - eventos personales (eventtype 'user'): solo los propios.
 * La visibilidad de la actividad (uservisible) la sigue comprobando quien llama.
 *
 * @package    theme_willedtech
 * @copyright  2026 WillEdTech
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_scope {

    /**
     * Condición SQL (alias de tabla «e») y parámetros.
     *
     * @param int[] $courseids
     * @param int $userid
     * @return array [string $where, array $params]
     */
    public static function where(array $courseids, int $userid): array {
        global $DB;
        [$coursesql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'esc');

        $groupids = [];
        foreach ($courseids as $courseid) {
            $context = \context_course::instance($courseid);
            $all = has_capability('moodle/site:accessallgroups', $context, $userid);
            $groups = groups_get_all_groups($courseid, $all ? 0 : $userid, 0, 'g.id');
            $groupids = array_merge($groupids, array_keys($groups));
        }
        if ($groupids) {
            [$groupsql, $groupparams] = $DB->get_in_or_equal(array_unique($groupids), SQL_PARAMS_NAMED, 'esg');
            $groupcondition = "(e.groupid = 0 OR e.groupid $groupsql)";
            $params += $groupparams;
        } else {
            $groupcondition = 'e.groupid = 0';
        }

        $where = "e.courseid $coursesql AND e.visible = 1 AND $groupcondition
                  AND (e.eventtype <> 'user' OR e.userid = :escuser1)
                  AND (e.priority IS NULL OR e.groupid > 0 OR e.userid = :escuser2)";
        $params += ['escuser1' => $userid, 'escuser2' => $userid];
        return [$where, $params];
    }
}
