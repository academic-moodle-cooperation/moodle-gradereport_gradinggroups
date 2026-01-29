<?php
// This file is part of gradinggroups for Moodle - http://moodle.org/
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
 * @copyright  2024 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/report/overview/lib.php');
require_once($CFG->dirroot . '/grade/report/gradinggroups/locallib.php');
require_once($CFG->dirroot . '/grade/report/gradinggroups/lib.php');

global $DB, $OUTPUT, $PAGE;

$id = required_param('id', PARAM_INT);   // Course.
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
if (!$course = $DB->get_record('course', ['id' => $id])) {
    throw new moodle_exception('nocourseid');
}
require_login($course);
$context = context_course::instance($course->id);
if (!has_capability('gradereport/gradinggroups:view', $context)) {
    throw new moodle_exception('nopermissiontoviewletergrade');
}
require_capability('gradereport/gradinggroups:view', $context);
$url = '/grade/report/gradinggroups/index.php';
$PAGE->set_url($url, ['id' => $id]);

require_capability('gradereport/gradinggroups:view', $context);

$gpr = new grade_plugin_return(
    [
        'type' => 'report',
        'plugin' => 'gradinggroups',
        'course' => $course,
        'page' => $PAGE,
    ]
);

if (!isset($USER->grade_last_report)) {
    $USER->grade_last_report = [];
}
$USER->grade_last_report[$course->id] = 'gradinggroups';

$access = true;
global $PAGE, $OUTPUT, $USER;
$report = new grade_report_gradinggroups($id, $gpr, $context);
$gradeitems = $report->get_gradeitems();
print_grade_page_head($id, 'report', 'gradinggroups');
gradereport_gradinggroups_view_grading($context, $id, $course, $gradeitems);
echo $OUTPUT->footer();
