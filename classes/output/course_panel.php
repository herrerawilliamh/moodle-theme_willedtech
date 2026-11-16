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
 * Panel del aula (igual para estudiantes, docentes y administración), encima de las secciones del curso: dónde sigue, cuánto lleva,
 * qué vence pronto y a quién escribir. No sustituye el formato del curso: solo lo encabeza.
 *
 * Reutiliza student_panel («Lo siguiente» y eventos) para que el Área personal y el aula digan lo mismo.
 *
 * @package    theme_willedtech
 * @copyright  2026 WillEdTech
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_panel implements \renderable, \templatable {

    /** @var \stdClass Curso. */
    private \stdClass $course;

    /**
     * @param \stdClass $course
     */
    public function __construct(\stdClass $course) {
        $this->course = $course;
    }

    /**
     * Datos para theme_willedtech/course_panel.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $USER;
        $student = new student_panel([$this->course->id => $this->course]);
        $next = $student->next_in_course($this->course);
        $percent = \core_completion\progress::get_course_progress_percentage($this->course, $USER->id);
        $events = $student->upcoming();
        $teacher = $this->teacher();

        return [
            'hasnext' => $next !== null,
            'next' => $next,
            'hasprogress' => $percent !== null,
            'percent' => $percent === null ? 0 : (int) round($percent),
            'events' => $events,
            'hasevents' => !empty($events),
            'hasteacher' => $teacher !== null,
            'teacher' => $teacher,
        ];
    }

    /**
     * Primer contacto del curso (roles de «Contactos del curso» en la administración) y cómo escribirle.
     *
     * @return array|null
     */
    private function teacher(): ?array {
        global $USER;
        $contacts = (new \core_course_list_element($this->course))->get_course_contacts();
        foreach ($contacts as $contact) {
            $user = $contact['user'];
            if ((int) $user->id === (int) $USER->id) {
                continue;
            }
            return [
                'name' => $contact['username'],
                'role' => $contact['rolename'],
                'initials' => \core_text::strtoupper(\core_text::substr($user->firstname ?? '', 0, 1)
                    . \core_text::substr($user->lastname ?? '', 0, 1)),
                'messageurl' => (new \moodle_url('/message/index.php', ['id' => $user->id]))->out(false),
            ];
        }
        return null;
    }
}
