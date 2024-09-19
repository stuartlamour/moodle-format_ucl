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
 * Format.
 *
 * @package    format_ucl
 * @copyright  2024 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Stuart Lamour <s.lamour@ucl.ac.uk>
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');

// Horrible backwards compatible parameter aliasing.
if ($topic = optional_param('topic', 0, PARAM_INT)) {
    $url = $PAGE->url;
    $url->param('section', $topic);
    debugging('Outdated topic param passed to course/view.php', DEBUG_DEVELOPER);
    redirect($url);
}
// End backwards-compatible aliasing.

// TODO - Make this better.
// When we get the url /course/view.php?id=6&expandsection=13#section-13
// We then redirect to /course/section.php?id=foo where foo is the section id..
// which is different from the section num passed in expand.
// Is there a setting to make moodle use section for return url?
if ($expandsection = optional_param('expandsection', 0, PARAM_INT)) {
    // SHAME - Hide the page to avoid redirect flicker.
    echo "
    <style>
    #page {
        display: none !important;
    }
    </style>
    ";
    global $COURSE;
    $format = course_get_format($COURSE);
    $coursesections = $format->get_sections();

    foreach ($coursesections as $section) {
        if ($section->section == $expandsection) {
            // TODO - if section 0, redirect to /course/section.php?id=courseid;
            $redirect = new moodle_url('/course/section.php', ['id' => $section->id]);
            redirect($redirect); // TODO - are there any params to add to make this better?
        }
    }

}

// TODO - can we use an add section param to redirect to edit page here?

// Retrieve course format option fields and add them to the $course object.
$format = core_courseformat\base::instance($course);
$course = $format->get_course();
$context = context_course::instance($course->id);

// Add any extra logic here.

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);

$renderer = $format->get_renderer($PAGE);

// Setup the format base instance.
if (!empty($displaysection)) {
    $format->set_section_number($displaysection);
}

// Output course content.
$outputclass = $format->get_output_classname('content');
$widget = new $outputclass($format);
echo $renderer->render($widget);

// Include any format js module here using $PAGE->requires->js.
