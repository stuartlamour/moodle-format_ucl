<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Renderer.
 *
 * @package    format_ucl
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Stuart Lamour <s.lamour@ucl.ac.uk>
 */

namespace format_ucl\output;

// require_once($CFG->dirroot.'/course/format/ucl/classes/external/toc.php');

use core_courseformat\base as format_base;
use core_courseformat\output\section_renderer;
use completion_info;
use context_course;
use moodle_page;
use moodle_url;
use stdClass;

/**
 * Renderer.
 *
 * @package    format_ucl
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Stuart Lamour <s.lamour@ucl.ac.uk>
 */
class renderer extends section_renderer {
// Override any necessary renderer method here.

    /**
    * Generate the section title, wraps it in a link to the section page if page is to be displayed on a separate page.
    *
    * This method is required to enable the inplace section title editor.
    *
    * @param section_info|stdClass $section The course_section entry from DB
    * @param stdClass $course The course entry from DB
    * @return string HTML to output.
    */
    public function section_title($section, $course) {
        return $this->render(format_base::instance($course)->inplace_editable_render_section_name($section));
    }

    /**
    * Generate the section title to be displayed on the section page, without a link.
    *
    * This method is required to enable the inplace section title editor.
    *
    * @param section_info|stdClass $section The course_section entry from DB
    * @param int|stdClass $course The course entry from DB
    * @return string HTML to output.
    */
    public function section_title_without_link($section, $course) {
        return $this->render(format_base::instance($course)->inplace_editable_render_section_name($section, false));
    }


    /**
    * Given a section, return the html for progress.
    *
    * @param section|stdClass $section The course_section entry from DB
    */
    public function format_ucl_section_progress($section): string {
        global $COURSE;
        $course = $COURSE;

        // Get all the Moodle things.
        $format = course_get_format($course);
        $modinfo = $format->get_modinfo();
        $completioninfo = new completion_info($course);
        $cmids = $modinfo->sections[$section->section] ?? [];

        // Count vars.
        $total = 0;
        $complete = 0;

        // Loop through cm in this section.
        foreach ($cmids as $cmid) {
            $thismod = $modinfo->cms[$cmid];
            if ($thismod->uservisible) {
                if ($completioninfo->is_enabled($thismod) != COMPLETION_TRACKING_NONE) {
                    $total++;
                    $completiondata = $completioninfo->get_data($thismod, true);
                    if ($completiondata->completionstate == COMPLETION_COMPLETE ||
                            $completiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                        $complete++;
                    }
                }
            }
        }

        // Return data.
        $data = new stdClass;
        if ($total) {
            $data->id = $section->id;
            $data->total = $total;
            $data->complete = $complete;
            $data->percentage = round(($complete / $total) * 100);
            if ($data->percentage == 100) {
                $data->done = true;
            }
        }
        return $this->render_from_template('format_ucl/toc_link_progress', $data);
    }

    /**
    * Output html for course table of contents.
    *
    * @return string the TOC HTML
    */
    public function format_ucl_table_of_contents(): string {
        global $COURSE, $PAGE;
        $course = $COURSE;

        $activesection = optional_param('id', 0, PARAM_INT);
        $format = course_get_format($course);
        $context = context_course::instance($course->id);
        $numsections = $format->get_last_section_number();
        $canviewhidden = has_capability('moodle/course:update', $context);
        $coursesections = $format->get_sections();

        $data = new stdClass();
        foreach ($coursesections as $section) {
            if ($section->visible || $canviewhidden) {
                $s = new stdClass;
                $s->name = $format->get_section_name($section);
                $s->url = new moodle_url('/course/section.php', ['id' => $section->id]);
                $s->visible = $section->visible;

                // Current url.
                if ($activesection == $section->id) {
                    $s->active = true;
                }

                // SHAME - Course home page link for section 0.
                if ($section->section === 0) {
                    $s->url = new moodle_url('/course/view.php', ['id' => $course->id]);
                    $s->class = "course-home";
                }

                // TODO - if mods?
                if ($course->enablecompletion) {
                    $s->progress = $this->format_ucl_section_progress($section);
                }

                $data->sections .= $this->render_from_template('format_ucl/toc_link', $s);
            }
        }

        // SHAME - Adding a new section requires some oddness.
        // With js, adding a section with js dosn't return us to the new section.
        // changenumsections.php however does have a redirect.
        // How do we know where to redirect though?
        // TODO - do we have the new section id?
        // We do have the section number in the course as its incrimented.
        // So we can go to that url
        // E.g. view.php?id=courseid&section=section->section
        if (has_any_capability(['moodle/course:manageactivities'], $PAGE->context)) {
            $returnurl = new moodle_url('/course/view.php',
                ['id' => $course->id,
                'section' => count($coursesections),
                'newsectionredirect' => true,
                ]
            );

            $params = ['courseid' => $course->id,
                'insertsection' => 0,
                'sesskey' => sesskey(),
                'returnurl' => $returnurl,
            ];

            $data->addsections = (object) [
                'url' => new moodle_url('/course/changenumsections.php', $params),
                'title' => $addstring,
            ];
        }

        return $this->render_from_template('format_ucl/toc', $data);
    }

    /**
    * // SHAME - by default section 0 dosn't have the previous/next to output in template.
    * Return template data for next visible section - only called by section 0.
    *
    */
    public function next_section() {
        global $COURSE;
        $course = $COURSE;

        $format = course_get_format($course);
        $sections = $format->get_sections();
        $numsections = count($sections);

        // Iterate through sections to see if any are visible.
        $i = 1;
        while ($i <= $numsections) {
            $s = $sections[$i];
            if ($s->visible) {
                $n = new stdClass;
                $n->nextname = $format->get_section_name($s);
                $n->hasnext = true;
                $n->nexturl =  new moodle_url('/course/section.php', ['id' => $s->id]);
                return $n;
            }
            $i++;
        }
        return false;
    }

    /**
     * Magic so format can use its own templates.
     *
     * Renders the content widget.
     *
     * @param renderable $widget instance with renderable interface
     * @return string the widget HTML
     *
     */
    public function render_content($widget): string {
        global $PAGE, $COURSE;

        $data = $widget->export_for_template($this);
        // Table of contents for ucl format.
        $data->toc = $this->format_ucl_table_of_contents();

        // TODO - this should be if editing.
        $data->canedit = has_any_capability(['moodle/course:manageactivities'], $PAGE->context);


        // SHAME - get section 0 only for first page.
        // Is there a better way to do this?
        // TODO -  probably seperate function.
        foreach ($data->sections as $section) {
            if ($section->num == '0') {
                $section->displayonesection = true; // Magic to stop accordians.
                $data->firstsection = $section;

                // Section title.
                $data->sectionname = $section->header->name;

                // Add next visible section for next/previous section template.
                if ($n = $this->next_section()) {
                    $data->sectionselector = $n;
                }
            }
        }

        // Section name - we never want to output this as a link.
        // Section 0 has a singlesection header.
        // TODO - make better.
        if ($data->singlesection->header) {
            // Swap section 0 into special first section, with UCL meatdata.
            $data->firstsection = $data->singlesection;
            $data->singlesection = '';
        }

        // SHAME - Edit section menu.
        // I don't know why, but the core edit a section menu dosn't work.
        // hide/show dosn't reload.
        // hightlight dosn't show.
        // If we can get these to work, that would be great, but in the mean time...
        // Edit section menu.
        if ($data->singlesection) {
            // Edit.
            // http://localhost:8888/moodle44/course/editsection.php?id=36&sr=1
            // id = section->id
            // sr = section->section ?
            $params = ['id' => $data->singlesection->id,
                'section' => $data->singlesection->section,
                'sesskey' => sesskey(),
            ];
            $data->editurl = new moodle_url('/course/editsection.php', $params);

            // Show / Hide.
            // http://localhost:8888/moodle44/course/view.php?id=6&sesskey=ZPBaSdBBnI&show=1&sectionid=36
            // id = course->id
            // ssectionid = ection->id
            // show ? 1
            // hide ? 1
            // sesskey
            // var_dump($data->singlesection);
            $params = ['id' => $COURSE->id,
                'sectionid' => $data->singlesection->id,
                'sesskey' => sesskey(),
            ];
            if ($data->singlesection->ishidden) {
                $params['show'] = $data->singlesection->num;
                $data->showurl = new moodle_url('/course/view.php', $params);
            } else {
                $params['hide'] = $data->singlesection->num;
                $data->hideurl = new moodle_url('/course/view.php', $params);
            }


            // Highlight.
            // http://localhost:8888/moodle44/course/view.php?id=3&sesskey=ZPBaSdBBnI&sectionid=4&marker=0
            // id = course->id
            // sectionid = section->id
            // marker = bool!

            // Delete.
            // http://localhost:8888/moodle44/course/editsection.php?id=36&delete=1&sesskey=ZPBaSdBBnI&sr=1
            // id = $section->id
            // delete = bool
            // sr = ??


        }


        // SHAME - Move - only for single sections.
        // TODO - improve this.
        if ($data->singlesection) {

            // http://localhost:8888/moodle43/course/view.php?id=3&amp;sesskey=ukEIuVkgjE&amp;movesection=1&amp;section=1#section-0"
            $m = new stdClass;
            $params = ['id' => $COURSE->id,
                'section' => $data->singlesection->section,
                'sesskey' => sesskey(),
                'movesection' => 1,
            ];
            $m->url = new moodle_url('/course/view.php', $params);
            $m->id = $data->singlesection->id;
            $data->movesection = $this->render_from_template('format_ucl/movesection', $m);
        }

        $data->sectionname .= $data->singlesection->singleheader->name;

        // SHAME - Redirect to edit page when creating a new section.
        // This is pretty hardcore, outputs js redirect in template.
        // TODO - make better.
        // TODO - the redirect on save from the edit page seems to not work.
        // Outputs course/view.php?id=6&expandsection=17#section-17
        // We want course/section.php?id=115
        if ($data->newsectionredirect = optional_param('newsectionredirect', null, PARAM_BOOL)) {
            $data->newurl = new moodle_url('/course/editsection.php',
                ['id' => $data->singlesection->id,
                 'sr' => $data->singlesection->num,
                ]
            );
        }

        return $this->render_from_template('format_ucl/main', $data);
    }
}