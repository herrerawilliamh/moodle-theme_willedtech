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

/**
 * Servicios AJAX del tema: gestión rápida de las tarjetas de «Mis cursos».
 *
 * @package    theme_willedtech
 * @copyright  2026 WillEdTech
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'theme_willedtech_course_quickstate' => [
        'classname' => \theme_willedtech\external\course_quickstate::class,
        'description' => 'Permisos de gestión rápida de los cursos mostrados en «Mis cursos».',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'theme_willedtech_set_course_visibility' => [
        'classname' => \theme_willedtech\external\set_course_visibility::class,
        'description' => 'Muestra u oculta un curso a los estudiantes desde su tarjeta.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'moodle/course:visibility',
        'loginrequired' => true,
    ],
];
