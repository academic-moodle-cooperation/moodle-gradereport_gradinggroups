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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/report/lib.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot.'/grade/report/gradinggroups/locallib.php');
require_once($CFG->dirroot.'/grade/report/grader/lib.php');


/**
 * This file contains the moodle hooks for the gradegroup module.
 *
 * @package    gradereport_gradinggroups
 * @author     Anne Kreppenhofer
 * @copyright  2024 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_report_gradinggroups extends grade_report_grader {

    /**
     * Constructor
     * @param int $courseid Course id
     * @param grade_plugin_return $gpr grade pluging return
     * @param course_context $context Context id
     * @param int|null $page
     * @throws moodle_exception
     */
    public function __construct($courseid, $gpr, $context, $page = null) {
        parent::__construct($courseid, $gpr, $context, $page);
    }
    /**
     * We get gradeitems for select here.
     */
    public function get_gradeitems() {
        global $CFG, $DB;

        $gradeitems = [];
        $gradetypes = [];
        $showgradeitemtypes = 0;

        foreach ($this->gtree->get_items() as $g) {
            if ($g->gradetype != GRADE_TYPE_VALUE) {
                continue;
            }

            $gradeitem = new stdClass();
            if ($g->display == 0) { // If display type is "default" check what default is.
                if ($coursedefault = $DB->get_field('grade_settings', 'value', ['courseid' => $g->courseid,
                    'name' => 'displaytype', ])) { // If course default exists take it.
                    $g->display = $coursedefault;
                } else { // Else take system default.
                    $g->display = $CFG->grade_displaytype;
                }
            }
            $gradeitem->disable = !in_array($g->display, $gradetypes);

            if (strcmp($g->itemtype, 'course') == 0) { // Item for the whole course.
                $gradeitem->name = get_string('coursesum', 'gradereport_gradinggroups');
                $gradeitem->sortorder = 0;
                $gradeitem->type = $g->itemtype;
                $gradeitem->module = '';
                $gradeitem->gid = $g->id;
            } else if (strcmp($g->itemtype, 'category') == 0) {  // Category item.
                $gc = $DB->get_record('grade_categories', ['id' => $g->iteminstance ]);
                $gradeitem->name = $gc->fullname;
                $gradeitem->sortorder = $g->sortorder;
                $gradeitem->type = get_string('gradecategory', 'grades');
                $gradeitem->module = '';
                $gradeitem->gid = $g->id;
            } else {
                $gradeitem->name = $g->itemname;
                $gradeitem->sortorder = $g->sortorder;
                $gradeitem->type = $g->itemtype;
                $gradeitem->module = $showgradeitemtypes ? $g->itemmodule : '';
                $gradeitem->gid = $g->id;
            }
            $gradeitems[] = $gradeitem;
        }
        return $gradeitems;
    }
}
/**
 * Copy Assign Grades from one user to another user (in assign_grade table)
 *
 * @package    gradereport_gradinggroups
 * @param int $id Assignment ID
 * @param int $fromid User ID from whom will be copied
 * @param int $toid User ID to whom will be copied
 * @throws coding_exception
 * @throws dml_exception
 */
function gradinggroups_copy_assign_grades($id, $fromid, $toid) {
    global $DB, $CFG;

    $source = $DB->get_records('assign_grades', ['assignment' => $id, 'userid' => $fromid],'id DESC', '*', 0, 1);

    if(empty($source)){
        return;
    }
    if (!is_array($toid)) {
        $toid = [$toid];
    }
    $source = reset($source);
    $user = $DB->get_record('user', ['id' => $source->userid]);
    $grader = $DB->get_record('user', ['id' => $source->grader]);
    // Get corresponding feedback!
    $feedbackcomment = $DB->get_record('assignfeedback_comments', [
        'assignment' => $id,
        'grade' => $source->id,
    ]);
    $feedbackfile = $DB->get_record('assignfeedback_file', [
        'assignment' => $id,
        'grade' => $source->id,
    ]);
    foreach ($toid as $curid) {
        $record = clone $source;
        $record->userid = $curid;
        unset($record->id);
        if ($record->id = $DB->get_field('assign_grades', 'id', [
            'assignment' => $id,
            'userid' => $curid,
            'attemptnumber' => $source->attemptnumber,
        ])) {
            $DB->update_record('assign_grades', $record);
            if ($feedbackcomment) {
                $newfeedbackcomment = clone $feedbackcomment;
                unset($newfeedbackcomment->id);
                $newfeedbackcomment->grade = $record->id;
                $newfeedbackcomment->assignment = $id;
                $details = [
                    'student' => fullname($user),
                    'teacher' => fullname($grader),
                    'date' => userdate($source->timemodified,
                        get_string('strftimedatetimeshort')),
                    'feedback' => $newfeedbackcomment->commenttext,
                ];
                $newfeedbackcomment->commenttext = format_text(get_string('copied_grade_feedback',
                    'gradinggroups',
                    $details),
                    $newfeedbackcomment->commentformat);
                if ($newfeedbackcomment->id = $DB->get_field('assignfeedback_comments', 'id', [
                    'assignment' => $id,
                    'grade' => $record->id,
                ])) {
                    $DB->update_record('assignfeedback_comments', $newfeedbackcomment);
                } else {
                    $DB->insert_record('assignfeedback_comments', $newfeedbackcomment);
                }
            }
            if ($feedbackfile) {
                $newfeedbackfile = clone $feedbackfile;
                unset($newfeedbackfile->id);
                $newfeedbackfile->grade = $record->id;
                $newfeedbackfile->assignment = $id;
                if ($newfeedbackfile->id = $DB->get_field('assignfeedback_file', 'id', [
                    'assignment' => $id,
                    'grade' => $record->id,
                ])) {
                    $DB->update_record('assignfeedback_file', $newfeedbackfile);
                } else {
                    $DB->insert_record('assignfeedback_file', $newfeedbackfile);
                }
            }
        } else {
            $gradeid = $DB->insert_record('assign_grades', $record);
            if ($feedbackcomment) {
                $newfeedbackcomment = clone $feedbackcomment;
                unset($newfeedbackcomment->id);
                $newfeedbackcomment->grade = $gradeid;
                $newfeedbackcomment->assignment = $id;
                $details = [
                    'student' => fullname($user),
                    'teacher' => fullname($grader),
                    'date' => userdate($source->timemodified,
                        get_string('strftimedatetimeshort')),
                    'feedback' => $newfeedbackcomment->commenttext,
                ];
                $newfeedbackcomment->commenttext = format_text(get_string('copied_grade_feedback',
                    'grouptool',
                    $details),
                    $newfeedbackcomment->commentformat);
                if ($newfeedbackcomment->id = $DB->get_field('assignfeedback_comments', 'id', [
                    'assignment' => $id,
                    'grade' => $gradeid,
                ])) {
                    $DB->update_record('assignfeedback_comments', $newfeedbackcomment);
                } else {
                    $DB->insert_record('assignfeedback_comments', $newfeedbackcomment);
                }
            }
            if ($feedbackfile) {
                $newfeedbackfile = clone $feedbackfile;
                unset($newfeedbackfile->id);
                $newfeedbackfile->grade = $gradeid;
                $newfeedbackfile->assignment = $id;
                if ($newfeedbackfile->id = $DB->get_field('assignfeedback_file', 'id', [
                    'assignment' => $id,
                    'grade' => $gradeid,
                ])) {
                    $DB->update_record('assignfeedback_file', $newfeedbackfile);
                } else {
                    $DB->insert_record('assignfeedback_file', $newfeedbackfile);
                }
            }
        }

        // User must have an assign_submission record, or the grade wont be displayed properly!
        if (!$DB->record_exists('assign_submission', ['assignment' => $id, 'userid' => $curid])) {
            require_once($CFG->dirroot . '/mod/assign/locallib.php');
            $rec = new stdClass();
            $rec->assignment = $id;
            $rec->userid = $curid;
            $rec->timecreated = time();
            $rec->timemodified = $rec->timecreated;
            $rec->groupid = 0;
            $rec->attemptnumber = 0;
            $rec->latest = 1;
            $rec->status = ASSIGN_SUBMISSION_STATUS_NEW;
            $DB->insert_record('assign_submission', $rec);
        }
    }
}

