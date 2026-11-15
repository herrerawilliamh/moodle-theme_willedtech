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
 * Renderer del tema: entrega siempre los lockups oficiales de WillEdTech Campus (BRAND.md §7 y §11).
 *
 * No sobrescribe plantillas de Boost: así el tema sigue funcionando cuando Moodle cambia su barra superior.
 * Los logos que se suban en Administración > Apariencia > Logos se ignoran a propósito, para que la marca
 * no se sustituya por una imagen distinta de la oficial.
 *
 * @package    theme_willedtech
 * @copyright  2026 WillEdTech
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {

    /**
     * Lockup horizontal para la barra superior (la plantilla de Boost lo muestra como logo compacto).
     *
     * @param int|null $maxwidth No se usa: el SVG oficial se dimensiona por CSS.
     * @param int $maxheight No se usa.
     * @return \moodle_url
     */
    public function get_compact_logo_url($maxwidth = 300, $maxheight = 300) {
        return $this->image_url('campus-horizontal', 'theme');
    }

    /**
     * Lockup vertical para la pantalla de acceso.
     *
     * @param int|null $maxwidth No se usa.
     * @param int $maxheight No se usa.
     * @return \moodle_url
     */
    public function get_logo_url($maxwidth = null, $maxheight = 200) {
        return $this->image_url('campus-vertical', 'theme');
    }

    /**
     * Favicon: solo la "C" de Campus, sin lockup ni texto (pix/campus-favicon.svg, propio del tema).
     * No se usa pix/favicon.svg porque es la W de Brand y está bloqueada por brand.lock.json.
     *
     * @return \moodle_url
     */
    public function favicon(): \moodle_url {
        return $this->image_url('campus-favicon', 'theme');
    }
}
