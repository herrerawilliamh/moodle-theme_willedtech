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
 * Funciones SCSS del tema.
 *
 * @package    theme_willedtech
 * @copyright  2026 WillEdTech
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * SCSS principal: el preset de Boost tal cual, para conservar sus componentes y su compatibilidad.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_willedtech_get_main_scss_content($theme) {
    global $CFG;
    require_once($CFG->dirroot . '/theme/boost/lib.php');
    return theme_boost_get_main_scss_content(theme_config::load('boost'));
}

/**
 * SCSS previo: los tokens de Brand traducidos a Bootstrap (tokens/willedtech-bootstrap.scss) y los ajustes de Moodle.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_willedtech_get_pre_scss($theme) {
    return file_get_contents(__DIR__ . '/scss/willedtech-bootstrap.scss') . "\n"
        . file_get_contents(__DIR__ . '/scss/pre.scss');
}

/**
 * SCSS final: tipografías autoalojadas y acabado de marca.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_willedtech_get_extra_scss($theme) {
    return file_get_contents(__DIR__ . '/scss/fonts.scss') . "\n"
        . file_get_contents(__DIR__ . '/scss/post.scss');
}
