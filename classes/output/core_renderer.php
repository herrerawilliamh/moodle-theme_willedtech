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

    /**
     * Encabezado de página. En el Área personal antepone el encabezado académico de Campus con datos reales
     * (nombre, cursos matriculados mediante la API de Moodle y accesos según permisos). No toca ningún bloque.
     *
     * @return string
     */
    public function full_header() {
        $html = parent::full_header();
        if ($this->is_admin_home()) {
            // Va detrás del encabezado (título y buscador de ajustes) y antes de las pestañas con todos los ajustes.
            return $html . $this->render_from_template('theme_willedtech/admin_panel',
                (new admin_panel())->export_for_template($this));
        }
        if ($this->page->pagelayout !== 'mydashboard' || !isloggedin() || isguestuser()) {
            return $html;
        }
        global $USER, $CFG;
        require_once($CFG->libdir . '/enrollib.php');

        $courses = enrol_get_my_courses('id');
        $count = count($courses);
        // Docente de curso: puede editar al menos uno de sus cursos (etiqueta de rol sin exigir permisos globales).
        $teachesany = false;
        foreach ($courses as $course) {
            if (has_capability('moodle/course:update', \context_course::instance($course->id))) {
                $teachesany = true;
                break;
            }
        }
        $canmanage = \core_course_category::has_manage_capability_on_any();
        $cancreate = \core_course_category::has_capability_on_any('moodle/course:create');
        // Categoría destino: la primera donde el usuario puede realmente crear cursos (el núcleo lo valida en edit.php).
        $createurl = null;
        if ($cancreate) {
            $cats = \core_course_category::make_categories_list('moodle/course:create');
            if ($cats) {
                $createurl = (new \moodle_url('/course/edit.php', ['category' => array_key_first($cats)]))->out(false);
            }
        }
        $cancreate = $createurl !== null;
        $data = [
            'firstname' => s($USER->firstname),
            'hascourses' => $count > 0,
            'coursecount' => $count,
            'catalogurl' => (new \moodle_url('/course/index.php'))->out(false),
            'calendarurl' => (new \moodle_url('/calendar/view.php', ['view' => 'upcoming']))->out(false),
            'isteacher' => $canmanage || $cancreate || $teachesany,
            'createurl' => $createurl,
            'cancreate' => $cancreate,
            'manageurl' => (new \moodle_url('/course/management.php'))->out(false),
            'canmanage' => $canmanage,
        ];
        return $this->render_from_template('theme_willedtech/dashboard_hero', $data) . $html;
    }

    /**
     * Portada de Administración del sitio (admin/search.php sin búsqueda) vista por quien administra el sitio.
     *
     * @return bool
     */
    protected function is_admin_home(): bool {
        if ($this->page->pagetype !== 'admin-search' || optional_param('query', '', PARAM_NOTAGS) !== '') {
            return false;
        }
        return has_capability('moodle/site:config', \context_system::instance());
    }
}
