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
    * Given a section, return the data for progress.
    *
    * @param section|stdClass $section The course_section entry from DB
    */
    public function format_ucl_section_progress($section): stdClass {
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
        return $data;
    }

    /**
    * Return data for course table of contents.
    *
    * @return string the TOC HTML
    */
    public function format_ucl_table_of_contents(): stdClass {
        global $COURSE, $PAGE, $USER;
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

                // Highlighted.
                if ($course->marker) {
                    if ($section->section == $course->marker) {
                        $s->hightlight = true;
                    }
                }

                // TODO - if mods?
                if(!$USER->editing) {
                    if ($course->enablecompletion) {
                        $s->progress = $this->format_ucl_section_progress($section);
                    }
                }

                // Add to template data.
                $data->coursesection[] = $s;
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
                'title' => "Add new section",
            ];
        }
        return $data;
    }

    /**
    * Return template data for next visible section - only called by section 0.
    * // SHAME - by default section 0 dosn't have the previous/next to output in template.
    *
    */
    public function format_ucl_next_section(): stdClass {
        global $COURSE;
        $course = $COURSE;

        $format = course_get_format($course);
        $sections = $format->get_sections();
        $numsections = count($sections);
        $context = context_course::instance($course->id);
        $canviewhidden = has_capability('moodle/course:update', $context);

        // Iterate through sections to see if any are visible.
        $i = 1;
        while ($i <= $numsections) {
            $s = $sections[$i];
            if ($s->visible || $canviewhidden) {
                $n = new stdClass;
                $n->nextname = $format->get_section_name($s);
                $n->hasnext = true;
                $n->nexturl =  new moodle_url('/course/section.php', ['id' => $s->id]);
                // Section hidden from students.
                if (!$s->visible) {
                    $n->nexthidden = true;
                }
                return $n;
            }
            $i++;
        }
        return new stdClass;
    }

    /**
    * Return html for sectionactions menu.
    * // SHAME - by default section 0 dosn't have the previous/next to output in template.
    *
    */
    public function format_ucl_sectionactions($data): string {
        global $COURSE;

        // Edit.
        $params = [
            'id' => $data->singlesection->id,
            'section' => $data->singlesection->num,
            'sectionid' => $data->singlesection->id,
            'sesskey' => sesskey(),
        ];
        $data->editurl = new moodle_url('/course/editsection.php', $params);

        // Move.
        $params = [
            'movesection' => 1,
            'id' => $COURSE->id,
            'section' => $data->singlesection->num,
            'sesskey' => sesskey(),
        ];
        $data->moveurl = new moodle_url('/course/view.php', $params);
        $data->sectionid = $data->singlesection->id;

        // Show / Hide.
        $params = [
            'id' => $COURSE->id,
            'sectionid' => $data->singlesection->id,
            'sesskey' => sesskey(),
        ];
        if (isset($data->singlesection->ishidden)) {
            $params['show'] = $data->singlesection->num;
            $data->showurl = new moodle_url('/course/view.php', $params);
        } else {
            $params['hide'] = $data->singlesection->num;
            $data->hideurl = new moodle_url('/course/view.php', $params);
        }

        // Highlight.
        $params = [
            'id' => $COURSE->id,
            'sectionid' => $data->singlesection->id,
            'sesskey' => sesskey(),
        ];
        if (isset($data->singlesection->iscurrent)) {
            $params['marker'] = 0;
            $data->unhighlighturl = new moodle_url('/course/view.php', $params);
        } else {
            $params['marker'] = $data->singlesection->num;
            $data->highlighturl = new moodle_url('/course/view.php', $params);
        }

        // Duplicate.
        $params = [
            'id' => $COURSE->id,
            'duplicatesection' => $data->singlesection->num,
            'section' => $data->singlesection->num,
            'sesskey' => sesskey(),
        ];
        $data->duplicateurl = new moodle_url('/course/view.php', $params);

        // Delete.
        $params = [
            'delete' => 1,
            'id' => $data->singlesection->id,
            'sr' => $data->singlesection->num -1,
            'confirm' => true,
            'sesskey' => sesskey(),
        ];
        $data->deleteurl = new moodle_url('/course/editsection.php', $params);

        return $this->render_from_template('format_ucl/sectionactions', $data);
    }

    /**
     * Return data for first section.
     *
     * @param $data
     *
     */
    public function format_ucl_firstsection($data): stdClass {
        $section = $data->sections['0'];
        // TODO - does this actually improve speed?
        $data->sections = ''; // Remove the rest of the data, not needed.
        if ($section->num == '0') {
            $section->displayonesection = true; // Magic to stop accordians.
            $data->firstsection = $section;

            // Section title.
            $data->sectionname = $section->header->name;

            // Add next section for next/previous section template.
            if ($next = $this->format_ucl_next_section()) {
                $data->sectionselector = $next;
            }

            // Single section editing.
            // TODO - make better.
            if ($data->isediting) {
                // Edit.
                $params = [
                    'id' => $section->id,
                    'section' => $section->num,
                    'sectionid' => $section->id,
                    'sesskey' => sesskey(),
                ];
                $data->editurl = new moodle_url('/course/editsection.php', $params);
                $data->singleedit = true;
            }
            // Set first section to enable adding ucl metadata.
            $data->firstsection = $section;
        }
        return $data;
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
        global $PAGE, $COURSE, $USER;
        $data = $widget->export_for_template($this);

        // Am i editing?
        $data->isediting = $USER->editing;

        // Redirect to edit page when creating a new section.
        // TODO - make better.
        // SHAME - This is pretty hardcore, outputs js redirect in a template.
        // NOTE - I'm not sure this is worse than moodle core redirect function.
        if ($data->newsectionredirect = optional_param('newsectionredirect', null, PARAM_BOOL)) {
            $data->newurl = new moodle_url('/course/editsection.php',
                ['id' => $data->singlesection->id,
                 'sr' => $data->singlesection->num,
                ]
            );
            return $this->render_from_template('format_ucl/main', $data);
        }

        // Section name.
        if (isset($data->singlesection->singleheader->name)) {
            $data->sectionname = $data->singlesection->singleheader->name;
        }

        // Section actions - the edit section menu.
        if ($data->isediting) {
            if (isset($data->singlesection)) {
                $data->sectionactions = $this->format_ucl_sectionactions($data);
            }
        }

        // TOC layout.
        $layout = 'toc';
        if ($layout == 'toc') {

            // Table of contents for ucl format.
            $data->toc[] = $this->format_ucl_table_of_contents();

            // Get section 0 only for first page.
            // SHAME - Is there a better way to do this?
            if ($data->sections) {
                $data = $this->format_ucl_firstsection($data);
            }

            return $this->render_from_template('format_ucl/main', $data);
        }

        // TODO - best practice - build into format.

        // More than 16 sections - not display well on laptops.
        // This course contains unnamed sections - you can improve your course by giving each section a meanigful title.
        // This course contains sections with one or less visbible actitivites - you can imporve your course by re-organising these.
        // This section contains lots of activites without any structure - you can improve this by using lables to structure the content.
        // etc
    }
}