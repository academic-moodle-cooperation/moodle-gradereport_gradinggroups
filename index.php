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
 * Gradegroup
 *
 * @package    gradereport_gradinggroups
 * @author     Anne Kreppenhofer
 * @copyright  2023 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once '../../../config.php';
require_once $CFG->libdir.'/gradelib.php';
require_once $CFG->dirroot.'/grade/lib.php';
require_once $CFG->dirroot.'/grade/report/overview/lib.php';
require_once($CFG->dirroot.'/grade/report/gradinggroups/locallib.php');


global $DB,$OUTPUT,$PAGE;

$id = required_param('id', PARAM_INT);   // Course.
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);
$context = context_course::instance($course->id);

// require_capability('gradereport/overview:view', $context);
$url = '/grade/report/gradinggroups/index.php';
$PAGE->set_url($url, ['id' => $id]);
$PAGE->set_pagelayout('report');

$access = true;
global $PAGE, $OUTPUT, $USER;

echo $OUTPUT->header();
view_grading($context,$id,$course,get_coursemodule_from_id('grouptool', $id));
echo $OUTPUT->footer();
