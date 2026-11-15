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

use core_course_list_element;
use coursecat_helper;
use html_writer;
use moodle_url;
use stdClass;

/**
 * Catálogo de cursos de Campus. Se cargan los datos reales con las consultas de Moodle (core_course_category,
 * core_course_list_element) y solo se cambia la composición de cada curso: tarjeta con portada, acceso y detalle.
 *
 * Qué genera PHP en el original: coursecat_courses() (lista y paginación), coursecat_coursebox() (contenedor),
 * course_name(), course_summary(), course_contacts(), course_custom_fields(). No hay Mustache en el catálogo del núcleo,
 * por eso el tema compone la tarjeta con theme_willedtech/catalog_coursecard.
 *
 * @package    theme_willedtech
 * @copyright  2026 WillEdTech
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_course_renderer extends \core_course_renderer {

    /**
     * Un curso del catálogo como tarjeta. Respeta los modos de visualización del núcleo (contraído/expandido).
     *
     * @param coursecat_helper $chelper
     * @param core_course_list_element|stdClass $course
     * @param string $additionalclasses
     * @return string
     */
    protected function coursecat_coursebox(coursecat_helper $chelper, $course, $additionalclasses = '') {
        if ($chelper->get_show_courses() <= self::COURSECAT_SHOW_COURSES_COUNT) {
            return '';
        }
        if ($course instanceof stdClass) {
            $course = new core_course_list_element($course);
        }

        $classes = trim('coursebox clearfix ' . $additionalclasses);
        if ($chelper->get_show_courses() < self::COURSECAT_SHOW_COURSES_EXPANDED) {
            $classes .= ' collapsed';
        }

        $imageurl = '';
        foreach ($course->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                $imageurl = moodle_url::make_file_url('/pluginfile.php',
                    '/' . $file->get_contextid() . '/' . $file->get_component() . '/' .
                    $file->get_filearea() . $file->get_filepath() . $file->get_filename())->out(false);
                // Valor seguro dentro de url("...") en CSS: sin comillas, paréntesis, barras invertidas ni saltos.
                $imageurl = str_replace(['\\', '"', "'", '(', ')', "\n", "\r"],
                    ['%5C', '%22', '%27', '%28', '%29', '%0A', '%0D'], $imageurl);
                break;
            }
        }

        $data = [
            'id' => $course->id,
            'classes' => $classes . ($course->visible ? '' : ' wt-dimmed'),
            'viewurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'fullname' => $chelper->get_course_formatted_name($course),
            'hasimage' => $imageurl !== '',
            'imageurl' => $imageurl,
            'name' => $this->course_name($chelper, $course),
            'enrolmenticons' => $this->course_enrolment_icons($course),
            'content' => $this->coursecat_coursebox_content($chelper, $course),
        ];
        return $this->render_from_template('theme_willedtech/catalog_coursecard', $data);
    }

    /**
     * Contenido del curso sin la imagen de portada (ya se muestra en la cabecera de la tarjeta).
     * Se conservan resumen, docentes, categoría, campos personalizados y archivos no imagen.
     *
     * @param coursecat_helper $chelper
     * @param core_course_list_element|stdClass $course
     * @return string
     */
    protected function coursecat_coursebox_content(coursecat_helper $chelper, $course) {
        if ($chelper->get_show_courses() < self::COURSECAT_SHOW_COURSES_EXPANDED) {
            return '';
        }
        if ($course instanceof stdClass) {
            $course = new core_course_list_element($course);
        }
        $content = $this->course_nonimage_files($course);
        $content .= $this->course_summary($chelper, $course);
        $content .= $this->course_contacts($course);
        $content .= $this->course_category_name($chelper, $course);
        $content .= $this->course_custom_fields($course);
        return $content;
    }

    /**
     * Archivos de presentación que no son imagen (la imagen ya es la portada), con el mismo marcado que el núcleo.
     *
     * @param core_course_list_element $course
     * @return string
     */
    protected function course_nonimage_files(core_course_list_element $course): string {
        global $CFG;
        $contentfiles = '';
        foreach ($course->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                continue;
            }
            $url = moodle_url::make_file_url("$CFG->wwwroot/pluginfile.php",
                '/' . $file->get_contextid() . '/' . $file->get_component() . '/' .
                $file->get_filearea() . $file->get_filepath() . $file->get_filename(), true);
            $image = $this->output->pix_icon(file_file_icon($file), $file->get_filename(), 'moodle');
            $filename = html_writer::tag('span', $image, ['class' => 'fp-icon']) .
                html_writer::tag('span', $file->get_filename(), ['class' => 'fp-filename']);
            $contentfiles .= html_writer::tag('span', html_writer::link($url, $filename),
                ['class' => 'coursefile fp-filename-icon text-break']);
        }
        return $contentfiles;
    }
}
