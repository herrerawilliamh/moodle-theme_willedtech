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
        . theme_willedtech_brand_dark_bridge() . "\n"
        . file_get_contents(__DIR__ . '/scss/tokens.scss') . "\n"
        . file_get_contents(__DIR__ . '/scss/post.scss') . "\n"
        . file_get_contents(__DIR__ . '/scss/admin.scss') . "\n"
        . file_get_contents(__DIR__ . '/scss/teacher.scss') . "\n"
        . file_get_contents(__DIR__ . '/scss/student.scss') . "\n"
        . file_get_contents(__DIR__ . '/scss/course.scss') . "\n"
        // Banners de sección (Brand §11): muestra solo el del modo activo, claro u oscuro.
        . file_get_contents(__DIR__ . '/style/banners.css');
}

/**
 * Puente del modo oscuro: Brand activa sus colores oscuros con [data-theme="dark"] y Moodle (Boost) marca el modo
 * con [data-bs-theme="dark"] en <html>. Se toma el bloque oscuro tal cual de style/willedtech.css (copia sincronizada
 * de Brand) y se reemite con el selector de Moodle, así los valores no se copian a mano ni se desincronizan.
 * html[...] gana a :root, donde Brand declara los valores claros.
 *
 * @return string CSS, o cadena vacía si el bloque de Brand no aparece (el tema se queda en claro, no se rompe).
 */
function theme_willedtech_brand_dark_bridge(): string {
    $css = file_get_contents(__DIR__ . '/style/willedtech.css');
    if ($css === false || !preg_match('/\[data-theme="dark"\][^{]*\{([^}]*)\}/', $css, $matches)) {
        return '';
    }
    return 'html[data-bs-theme="dark"] {' . $matches[1] . "}\n";
}
