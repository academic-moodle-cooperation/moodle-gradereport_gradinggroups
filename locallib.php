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
 * locallib of Gradegroup
 *
 * @package    gradereport_gradinggroups
 * @author     Anne Kreppenhofer
 * @copyright  2023 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * view grading
 * @param course_context $context
 * @param int $id
 * @param course $course
 * @param int $cm
 * @return void
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 * @throws required_capability_exception
 */
function view_grading($context, $id, $course, $cm) {
    global $PAGE, $OUTPUT, $USER;

    if (!has_capability('mod/grouptool:grade', $context)
        && !has_capability('mod/groputool:grade_own_groups', $context)) {
        print_error('nopermissions');
        return;
    }

    $refreshtable = optional_param('refresh_table', 0, PARAM_BOOL);
    $activity = optional_param('activity', null, PARAM_INT); // This is the coursemodule-ID.

    // Show only groups with grades given by current user!
    $mygroupsonly = optional_param('mygroups_only', null, PARAM_BOOL);

    if (!has_capability('mod/grouptool:grade', $context)) {
        $mygroupsonly = 1;
    }

    if ($mygroupsonly != null) {
        set_user_preference('mod_grouptool_mygroups_only', $mygroupsonly, $USER->id);
    }

    // Show only groups with missing grades (groups with at least 1 not-graded member)!
    $incompleteonly = optional_param('incomplete_only', 0, PARAM_BOOL);

    $overwrite = optional_param('overwrite', 0, PARAM_BOOL);

    // Here -1 = nonconflicting, 0 = all     or groupid for certain group!
    $filter = optional_param('filter', GROUPTOOL_FILTER_NONCONFLICTING, PARAM_INT);
    // Steps: 0 = show, 1 = confirm, 2 = action!
    $step = optional_param('step', 0, PARAM_INT);
    if ($refreshtable) { // If it was just a refresh, reset step!
        $step = 0;
    }

    $grouping = optional_param('grouping', 0, PARAM_INT);

    if ($filter > 0) {
        if ($step == 2) {
            $source = optional_param('source', null, PARAM_INT);
            // Serialized data TODO: better PARAM_TYPE?
            $selected = optional_param('selected', null, PARAM_RAW);
            if (!empty($selected)) {
                $selected = unserialize($selected);
            }
        } else {
            if ($refreshtable) {
                // Otherwise we get problems here, if we refresh and change from multiple groups to a single group!
                $source = null;
            } else {
                $source = optional_param('source', null, PARAM_INT);
            }
            $selected = optional_param_array('selected', null, PARAM_INT);
            if (!empty($source) && !$refreshtable) {
                $step = 1;
            }
        }
    } else {
        if ($step == 2) {
            $source = optional_param('source', null, PARAM_RAW);
            if (!empty($source)) {
                $source = unserialize($source);
            }
            $selected = optional_param('selected', null, PARAM_RAW);
            if (!empty($selected)) {
                $selected = unserialize($selected);
            }
        } else {
            $source = optional_param_array('source', [], PARAM_INT);
            $selected = optional_param_array('selected', [], PARAM_INT);
            $copygroups = optional_param('copygrades', 0, PARAM_BOOL);
            if ($copygroups && !$refreshtable) {
                $step = 1;
            }
        }
    }
    $confirm = optional_param('confirm', 0, PARAM_BOOL);
    if (($step == 2) && !$confirm) {
        $step = 0;
    }

    // Reset process if some evil hacker tried to do smth!
    if (!$confirm && (!data_submitted() || !confirm_sesskey())) {
        $step = 0;
    }

    if (!empty($mygroupsonly)) {
        $mygroupsonly = get_user_preferences('mod_grouptool_mygroups_only', 1, $USER->id);
    }

    $missingsource = [];

    if ($step == 1) {    // Show confirm message!

        if ($filter > 0) {
            // Single group mode!
            if (is_array($selected) && in_array($source, $selected)) {
                foreach ($selected as $key => $cmp) {
                    if ($cmp == $source) {
                        unset($selected[$key]);
                    }
                }
            }
            if (!empty($selected)) {
                list(, $preview) = copy_grades($activity, $mygroupsonly,
                    $selected, $source, $overwrite,
                    true, $context, $course, $cm);
                $continue = new moodle_url("view.php?id=".$id, [
                    'tab'           => 'grading',
                    'confirm'       => 'true',
                    'sesskey'       => sesskey(),
                    'step'          => '2',
                    'activity'      => $activity,
                    'mygroups_only' => $mygroupsonly,
                    'overwrite'     => $overwrite,
                    'selected'      => serialize($selected),
                    'source'        => serialize($source)
                ]);
                $cancel = new moodle_url("view.php?id=".$id, [
                    'tab'           => 'grading',
                    'confirm'       => 'false',
                    'sesskey'       => sesskey(),
                    'step'          => '2',
                    'activity'      => $activity,
                    'mygroups_only' => $mygroupsonly,
                    'overwrite'     => $overwrite,
                    'selected'      => serialize($selected),
                    'source'        => serialize($source)
                ]);
                $preview = $OUTPUT->heading(get_string('preview'), 2, 'centered').$preview;
                if ($overwrite) {
                    echo $preview.confirm(get_string('copy_grades_overwrite_confirm', 'gradereport_gradinggroups'),
                            $continue, $cancel);
                } else {
                    echo $preview.confirm(get_string('copy_grades_confirm', 'gradereport_gradinggroups'), $continue,
                            $cancel);
                }
            } else {
                $boxcontent = $OUTPUT->notification(get_string('no_target_selected', 'gradereport_gradinggroups'),
                    \core\output\notification::NOTIFY_ERROR);
                echo $OUTPUT->box($boxcontent, 'generalbox');
                unset($boxcontent);
                $step = 0;
            }

        } else if ($filter == GROUPTOOL_FILTER_ALL
            || $filter == GROUPTOOL_FILTER_NONCONFLICTING) {
            // All or nonconflicting mode?
            foreach ($selected as $key => $grp) {
                // If no grade is choosen add this group to missing-source-list!
                if (empty($source[$grp])) {
                    $missingsource[] = $grp;
                }
            }

            if (!empty($selected) && (count($missingsource) == 0)) {
                list(, $preview) = copy_grades($activity, $mygroupsonly,
                    $selected, $source, $context, $course, $cm, $overwrite);
                $continue = new moodle_url("view.php?id=".$id, [
                    'tab'           => 'grading',
                    'confirm'       => 'true',
                    'sesskey'       => sesskey(),
                    'activity'      => $activity,
                    'mygroups_only' => $mygroupsonly,
                    'overwrite'     => $overwrite,
                    'step'          => '2',
                    'selected'      => serialize($selected),
                    'source'        => serialize($source)
                ]);
                $cancel = new moodle_url("view.php?id=".$id, [
                    'tab' => 'grading',
                    'confirm'       => 'false',
                    'sesskey'       => sesskey(),
                    'activity'      => $activity,
                    'mygroups_only' => $mygroupsonly,
                    'overwrite'     => $overwrite,
                    'step'          => '2',
                    'selected'      => serialize($selected),
                    'source'        => serialize($source)
                ]);
                $preview = $OUTPUT->heading(get_string('preview'), 2, 'centered').$preview;
                if ($overwrite) {
                    echo $preview.confirm(get_string('copy_grades_overwrite_confirm', 'gradereport_gradinggroups'),
                            $continue, $cancel);
                } else {
                    echo $preview.confirm(get_string('copy_grades_confirm', 'gradereport_gradinggroups'), $continue,
                            $cancel);
                }
            } else {
                if (empty($selected)) {
                    $boxcontent = $OUTPUT->notification(get_string('no_target_selected', 'gradereport_gradinggroups'),
                        \core\output\notification::NOTIFY_ERROR);
                    echo $OUTPUT->box($boxcontent, 'generalbox');
                    unset($boxcontent);
                    $step = 0;
                }
                if (count($missingsource) != 0) {
                    $boxcontent = $OUTPUT->notification(get_string('sources_missing', 'gradereport_gradinggroups'),
                        \core\output\notification::NOTIFY_ERROR);
                    echo $OUTPUT->box($boxcontent, 'generalbox');
                    unset($boxcontent);
                    $step = 0;
                }
            }
        } else {
            print_error('wrong parameter');
        }
    }

    if ($step == 2) {    // Do action and continue with showing the form!
        // if there was an error?
        list($error, $info) = copy_grades($activity, $mygroupsonly, $selected, $source, $context, $course, $cm,
            $overwrite);
        if ($error) {
            $boxcontent = $OUTPUT->notification(get_string('copy_grades_errors', 'gradereport_gradinggroups'),
                    \core\output\notification::NOTIFY_ERROR).$info;
            echo $OUTPUT->box($boxcontent, 'generalbox tumargin');
            unset($boxcontent);
        } else {
            $boxcontent = $OUTPUT->notification(get_string('copy_grades_success', 'gradereport_gradinggroups'),
                    \core\output\notification::NOTIFY_SUCCESS).$info;
            echo $OUTPUT->box($boxcontent, 'generalbox tumargin');
            unset($boxcontent);
        }
    }

    if ($step != 1 || count($missingsource)) {    // Show form if step is either 0 or 2!

        // Prepare form content!
        if ($filter > 0) {
            $table = get_grading_table($activity, $mygroupsonly, $incompleteonly,
                $filter, $selected, $context, $course);
        } else {
            $table = get_grading_table($activity, $mygroupsonly, $incompleteonly,
                $filter, $selected, $context, $course, $missingsource);
        }

        $formdata = ['id'             => $id,
            'course'         => $course,
            'mygroupsonly'   => $mygroupsonly,
            'incompleteonly' => $incompleteonly,
            'overwrite'      => $overwrite,
            'grouping'       => $grouping,
            'filter'         => $filter,
            'table'          => $table];
        $mform = new \mod_grouptool\grading_form($PAGE->url, $formdata, 'post', '', ['class' => 'mform',
            'id'    => 'grading_form',
            'name'  => 'grading_form']);

        $params = new stdClass();
        $params->lang = current_language();
        $params->contextid  = $context->id;
        $PAGE->requires->js_call_amd('mod_grouptool/grading', 'initializer', [$params]);

        $mform->display();
    }
}

/**
 * copies the grades from the source(s) to the target(s) for the selected activity
 *
 * @param context_course $context
 * @param course $course
 * @param int $cm
 * @param int $activity ID of activity to get/set grades from/for
 * @param bool $mygroupsonly limit source-grades to those given by current user
 * @param int[] $selected array with ids of groups/users to copy grades to as keys (depends on filter)
 * @param int[] $source optional array with ids of entries for whom no source has been selected
 *                       (just to display a clue to select a source)
 * @param bool $overwrite optional overwrite existing grades (std: false)
 * @param bool $previewonly optional just return preview data
 * @return array ($error, $message)
 * @throws coding_exception
 * @throws dml_exception
 * @throws required_capability_exception
 */
function copy_grades($activity, $mygroupsonly, $selected, $source, $context, $course, $cm, $overwrite = false,
                             $previewonly = false) {
    global $DB, $USER;
    $error = false;
    // If he want's to grade all he needs the corresponding capability!
    if (!$mygroupsonly) {
        require_capability('mod/grouptool:grade', $context);
    } else if (!has_capability('mod/grouptool:grade', $context)) {
        /*
         * if he wants to grade his own (=submissions where he graded at least 1 group member)
         * he needs either capability to grade all or to grade his own at least
         */
        require_capability('mod/grouptool:grade_own_submission', $context);
    }

    $cmtouse = get_coursemodule_from_id('', $activity, $course->id);
    if (!$cmtouse) {
        return [true, get_string('couremodule_misconfigured')];
    }
    if ($previewonly) {
        $previewtable = new html_table();
        $previewtable->attributes['class'] = 'table table-hover grading_previewtable';
    } else {
        $previewtable = new stdClass();
    }
    $info = "";

    $gradeitems = grade_item::fetch_all([
        'itemtype'     => 'mod',
        'itemmodule'   => $cmtouse->modname,
        'iteminstance' => $cmtouse->instance
    ]);
    // TODO #3310 should we support multiple grade items per activity module soon?

    do {
        // Right now, we just work with the first grade item!
        $gradeitem = current($gradeitems);
    } while (!empty($gradeitem->itemnumber) && next($gradeitems));

    if (is_array($source)) { // Then we are in multigroup mode (filter = 0 || -1)!
        $sourceusers = $DB->get_records_list('user', 'id', $source);
        $groups = groups_get_all_groups($course->id);

        $previewtable->head = [
            get_string('groups')." (".count($selected).")",
            get_string('fullname'),
            get_string('grade', 'grades'),
            get_string('feedback')
        ];
        foreach ($selected as $group) {
            $groupinfo = "";
            $grouprows = [];

            $sourcegroup = is_array($source[$group]) ? $source[$group] : [$source[$group]];
            $sourcegrade = grade_grade::fetch_users_grades($gradeitem, $sourcegroup,
                false);
            $sourcegrade = reset($sourcegrade);
            $sourcegrade->load_optional_fields();
            $origteacher = $DB->get_record('user', ['id' => $sourcegrade->usermodified]);
            $formattedgrade = round($sourcegrade->finalgrade, 2) .' / ' .
                round($gradeitem->grademax, 2);

            $groupmembers = groups_get_members($group);
            $targetgrades = grade_grade::fetch_users_grades($gradeitem,
                array_keys($groupmembers), true);
            $propertiestocopy = ['rawgrade', 'finalgrade', 'feedback', 'feedbackformat'];

            foreach ($targetgrades as $currentgrade) {

                if ($currentgrade->id == $sourcegrade->id) {
                    continue;
                }
                if (!$overwrite && ($currentgrade->finalgrade != null)) {
                    if ($previewonly) {
                        $rowcells = [];
                        if (empty($grouprows)) {
                            $rowcells[] = new html_table_cell($groups[$group]->name."\n".
                                html_writer::empty_tag('br').
                                "(".(count($groupmembers) - 1).")");
                        }
                        $fullname = fullname($groupmembers[$currentgrade->userid]);
                        $rowcells[] = new html_table_cell($fullname);
                        $cell = new html_table_cell(get_string('skipped', 'gradereport_gradinggroups'));
                        $cell->colspan = 2;
                        $rowcells[] = $cell;
                        $row = new html_table_row();
                        $row->cells = $rowcells;
                        if (empty($grouprows)) {
                            $row->attributes['class'] .= ' firstgrouprow';
                        }
                        $grouprows[] = $row;
                    }
                    continue;
                }
                $currentgrade->load_optional_fields();
                foreach ($propertiestocopy as $property) {
                    $currentgrade->$property = $sourcegrade->$property;
                }
                $details = [
                    'student'  => fullname($sourceusers[$source[$group]]),
                    'teacher'  => fullname($origteacher),
                    'date'     => userdate($sourcegrade->get_dategraded(),
                        get_string('strftimedatetimeshort')),
                    'feedback' => $sourcegrade->feedback
                ];
                $currentgrade->feedback = format_text(get_string('copied_grade_feedback',
                    'gradereport_gradinggroups',
                    $details),
                    $currentgrade->feedbackformat);
                $currentgrade->usermodified = $USER->id;
                if ($previewonly) {
                    $rowcells = [];
                    if (empty($grouprows)) {
                        $rowcells[] = new html_table_cell($groups[$group]->name."\n".
                            html_writer::empty_tag('br').
                            "(".count($groupmembers).")");
                    }
                    $fullname = fullname($groupmembers[$currentgrade->userid]);
                    $rowcells[] = new html_table_cell($fullname);
                    $rowcells[] = new html_table_cell($formattedgrade);
                    $rowcells[] = new html_table_cell($currentgrade->feedback);
                    $row = new html_table_row();
                    $row->cells = $rowcells;
                    if (empty($grouprows)) {
                        $row->attributes['class'] .= ' firstgrouprow';
                    }
                    $grouprows[] = $row;
                } else {
                    if (function_exists ('grouptool_copy_'.$cmtouse->modname.'_grades')) {
                        $copyfunction = 'grouptool_copy_'.$cmtouse->modname.'_grades';
                        $copyfunction($cmtouse->instance, $sourcegrade->userid, $currentgrade->userid);
                    }
                    if ($currentgrade->id) {
                        $noerror = $currentgrade->update();
                    } else {
                        $noerror = $currentgrade->insert();
                    }
                    $currentgrade->set_overridden(true, false);
                    $currentgrade->grade_item->force_regrading();
                    $fullname = fullname($groupmembers[$currentgrade->userid]);
                    if ($noerror) {
                        $groupinfo .= html_writer::tag('span',
                            '✓&nbsp;'.$fullname.
                            " (".$formattedgrade.")",
                            ['class' => 'notifysuccess']);
                    } else {
                        $error = true;
                        $groupinfo .= html_writer::tag('span',
                            '✗&nbsp;'.$fullname.
                            " (".$formattedgrade.")",
                            ['class' => 'notifyproblem']);
                    }
                }
            }
            if ($previewonly) {
                $grouprows[0]->cells[0]->rowspan = count($grouprows);
                if (!is_array($previewtable->data)) {
                    $previewtable->data = [];
                }
                $previewtable->data = array_merge($previewtable->data, $grouprows);
            } else {
                $grpinfo = "";
                $grpinfo .= html_writer::tag('div', $groups[$group]->name." (".
                    count($groupmembers)."): ".$groupinfo);
                $data = [
                    'student' => fullname($sourceusers[$source[$group]]),
                    'teacher' => fullname($origteacher),
                    'date'    => userdate($sourcegrade->get_dategraded(),
                        get_string('strftimedatetimeshort')),
                    'feedback' => $sourcegrade->feedback
                ];
                $temp = get_string('copied_grade_feedback', 'gradereport_gradinggroups', $data);
                $grpinfo .= html_writer::tag('div', $formattedgrade.html_writer::empty_tag('br').
                    format_text($temp,
                        $sourcegrade->feedbackformat));
                $info .= html_writer::tag('div', $grpinfo, ['class' => 'box1embottom']);
                // Trigger the event!
                $logdata = new stdClass();
                $logdata->groupid = $group;
                $logdata->cmtouse = $cmtouse->id;
                \mod_grouptool\event\group_graded::create_direct($cm, $logdata)->trigger();
            }
        }
    } else {
        $sourceuser = $DB->get_record('user', ['id' => $source]);
        $targetusers = $DB->get_records_list('user', 'id', $selected);
        $sourcegrade = grade_grade::fetch_users_grades($gradeitem, [$source], false);
        $sourcegrade = reset($sourcegrade);
        $origteacher = $DB->get_record('user', ['id' => $sourcegrade->usermodified]);
        $formattedgrade = round($sourcegrade->finalgrade, 2).' / ' .
            round($gradeitem->grademax, 2);
        $targetgrades = grade_grade::fetch_users_grades($gradeitem, $selected, true);
        $propertiestocopy = ['rawgrade', 'finalgrade', 'feedback', 'feedbackformat'];
        $nameinfo = "";
        $grouprows = [];
        if ($previewonly) {
            $count = in_array($source, $selected) ? count($selected) - 1 : count($selected);
            $previewtable->head = [
                '', get_string('fullname')." (".$count.")",
                get_string('grade', 'grades'), get_string('feedback')
            ];
            $previewtable->attributes['class'] = 'table table-hover grading_previewtable';
        } else {
            $info .= html_writer::start_tag('div');
        }

        foreach ($targetgrades as $currentgrade) {
            if ($currentgrade->id == $sourcegrade->id) {
                continue;
            }
            if (!$overwrite && ($currentgrade->rawgrade != null)) {
                if ($previewonly) {
                    $rowcells = [];
                    if (empty($grouprows)) {
                        $rowcells[] = new html_table_cell(get_string('users'));
                    }
                    $fullname = fullname($targetusers[$currentgrade->userid]);
                    $rowcells[] = new html_table_cell($fullname);
                    $cell = new html_table_cell(get_string('skipped', 'gradereport_gradinggroups'));
                    $cell->colspan = 2;
                    $rowcells[] = $cell;
                    $row = new html_table_row();
                    $row->cells = $rowcells;
                    if (empty($grouprows)) {
                        $row->attributes['class'] .= ' firstgrouprow';
                    }
                    $grouprows[] = $row;
                }
                continue;
            }
            $currentgrade->load_optional_fields();
            foreach ($propertiestocopy as $property) {
                $currentgrade->$property = $sourcegrade->$property;
            }

            $details = [
                'student' => fullname($sourceuser),
                'teacher' => fullname($origteacher),
                'date' => userdate($sourcegrade->get_dategraded(),
                    get_string('strftimedatetimeshort')),
                'feedback' => $sourcegrade->feedback
            ];
            $currentgrade->feedback = format_text(get_string('copied_grade_feedback',
                'gradereport_gradinggroups',
                $details),
                $currentgrade->feedbackformat);
            $currentgrade->usermodified   = $USER->id;
            if ($previewonly) {
                $rowcells = [];
                if (empty($grouprows)) {
                    $rowcells[] = new html_table_cell(get_string('users'));
                }
                $fullname = fullname($targetusers[$currentgrade->userid]);
                $rowcells[] = new html_table_cell($fullname);
                $rowcells[] = new html_table_cell($formattedgrade);
                $rowcells[] = new html_table_cell(format_text($currentgrade->feedback,
                    $currentgrade->feedbackformat));
                $row = new html_table_row();
                $row->cells = $rowcells;
                if (empty($grouprows)) {
                    $row->attributes['class'] .= ' firstgrouprow';
                }
                $grouprows[] = $row;
            } else {
                if ($nameinfo != "") {
                    $nameinfo .= ", ";
                }
                if ($currentgrade->id) {
                    $noerror = $currentgrade->update();
                } else {
                    $noerror = $currentgrade->insert();
                }
                $currentgrade->set_overridden(true, false);
                $currentgrade->grade_item->force_regrading();
                $fullname = fullname($targetusers[$currentgrade->userid]);
                if (function_exists ('grouptool_copy_'.$cmtouse->modname.'_grades')) {
                    $copyfunction = 'grouptool_copy_'.$cmtouse->modname.'_grades';
                    $copyfunction($cmtouse->instance, $sourcegrade->userid, $currentgrade->userid);
                }
                if ($noerror) {
                    $nameinfo .= html_writer::tag('span',
                        '✓&nbsp;'.$fullname,
                        ['class' => 'notifysuccess']);
                } else {
                    $error = true;
                    $nameinfo .= html_writer::tag('span',
                        '✗&nbsp;'.$fullname,
                        ['class' => 'notifyproblem']);
                }
            }
        }
        if ($previewonly) {
            $grouprows[0]->cells[0]->rowspan = count($grouprows);
            $previewtable->data = $grouprows;
        } else {
            $info .= $nameinfo.html_writer::end_tag('div');
            $details = [
                'student' => fullname($sourceuser),
                'teacher' => fullname($origteacher),
                'date' => userdate($sourcegrade->get_dategraded(),
                    get_string('strftimedatetimeshort')),
                'feedback' => $sourcegrade->feedback
            ];
            $info .= html_writer::tag('div', get_string('grade', 'grades').": ".
                $formattedgrade.html_writer::empty_tag('br').
                format_text(get_string('copied_grade_feedback', 'gradereport_gradinggroups',
                    $details),
                    $sourcegrade->feedbackformat),
                ['class' => 'gradeinfo']);
        }
        if (!$previewonly) {
            // Trigger the event!
            $logdata = new stdClass();
            $logdata->source = $source;
            $logdata->selected = $selected;
            $logdata->cmtouse = $cmtouse->id;
            \mod_grouptool\event\group_graded::create_without_groupid($cm, $logdata)->trigger();
        }
    }
    if ($previewonly) {
        return [
            $error, html_writer::tag('div', html_writer::table($previewtable),
                ['class' => 'centeredblock'])
        ];
    } else {
        return [$error, html_writer::tag('div', $info, ['class' => 'centeredblock'])];
    }
}

/**
 * Print a message along with button choices for Continue/Cancel
 *
 * If a string or moodle_url is given instead of a single_button, method defaults to post.
 * If cancel=null only continue button is displayed!
 *
 * @param string $message The question to ask the user
 * @param single_button|moodle_url|string $continue The single_button component representing the
 *                                                  Continue answer. Can also be a moodle_url
 *                                                  or string URL
 * @param single_button|moodle_url|string $cancel   The single_button component representing the
 *                                                  Cancel answer. Can also be a moodle_url or
 *                                                  string URL
 * @return string HTML fragment
 * @throws coding_exception
 * @throws moodle_exception
 */
function confirm($message, $continue, $cancel = null) {
    global $OUTPUT;
    if (!($continue instanceof single_button)) {
        if (is_string($continue)) {
            $url = new moodle_url($continue);
            $continue = new single_button($url, get_string('continue'), 'post', 'primary');
        } else if ($continue instanceof moodle_url) {
            $continue = new single_button($continue, get_string('continue'), 'post', 'primary');
        } else {
            throw new coding_exception('The continue param to grouptool::confirm() must be either a'.
                ' URL (string/moodle_url) or a single_button instance.');
        }
    }

    if (!($cancel instanceof single_button)) {
        if (is_string($cancel)) {
            $cancel = new single_button(new moodle_url($cancel), get_string('cancel'), 'get');
        } else if ($cancel instanceof moodle_url) {
            $cancel = new single_button($cancel, get_string('cancel'), 'get');
        } else if ($cancel == null) {
            $cancel = null;
        } else {
            throw new coding_exception('The cancel param to grouptool::confirm() must be either a'.
                ' URL (string/moodle_url), single_button instance or null.');
        }
    }

    $output = $OUTPUT->box_start('generalbox modal modal-dialog modal-in-page show', 'notice');
    $output .= $OUTPUT->box_start('modal-content', 'modal-content');
    $output .= $OUTPUT->box_start('modal-header', 'modal-header');
    $output .= html_writer::tag('h4', get_string('confirm'));
    $output .= $OUTPUT->box_end();
    $output .= $OUTPUT->box_start('modal-body', 'modal-body');
    $output .= html_writer::tag('p', $message);
    $output .= $OUTPUT->box_end();
    $output .= $OUTPUT->box_start('modal-footer', 'modal-footer');
    $cancel = ($cancel != null) ? $OUTPUT->render($cancel) : "";
    $output .= html_writer::tag('div', $OUTPUT->render($continue) . $cancel, ['class' => 'buttons']);
    $output .= $OUTPUT->box_end();
    $output .= $OUTPUT->box_end();
    $output .= $OUTPUT->box_end();
    return $output;
}
/**
 * returns table used in group-grading form
 *
 *  TODO use templates and load via AJAX (AMD core/fragment)
 *
 * @param course_context $context
 * @param course $course
 * @param int $activity ID of activity to get/set grades from/for
 * @param bool $mygroupsonly limit source-grades to those given by current user
 * @param bool $incompleteonly show only groups which have not-graded members
 * @param int $filter GROUPTOOL_FILTER_ALL => all groups
 *                     GROUPTOOL_FILTER_NONCONFLICTING => groups with exactly 1 graded member
 *                     >0 => id of single group
 * @param int[] $selected array with ids of groups/users to copy grades to as keys (depends on filter)
 * @param int[] $missingsource optional array with ids of entries for whom no source has been selected
 *                              (just to display a clue to select a source)
 * @return string HTML Fragment containing checkbox-controller and dependencies
 * @throws coding_exception
 * @throws moodle_exception
 * @throws required_capability_exception
 */
function get_grading_table($activity, $mygroupsonly, $incompleteonly, $filter, $selected, $context, $course, $missingsource = []) {
    global $OUTPUT, $USER, $PAGE;

    // If he want's to grade all he needs the corresponding capability!
    if (!$mygroupsonly) {
        require_capability('mod/grouptool:grade', $context);
    } else if (!has_capability('mod/grouptool:grade', $context)) {
        /*
         * if he want's to grad his own he needs either capability to grade all
         * or to grade his own at least
         */
        require_capability('mod/grouptool:grade_own_submission', $context);
    }

    $grouping = optional_param('grouping', null, PARAM_INT);

    $table = new html_table();

    if ($activity == 0) {
        return $OUTPUT->box($OUTPUT->notification(get_string('chooseactivity', 'gradereport_gradinggroups'),
            \core\output\notification::NOTIFY_ERROR), 'generalbox centered');
    }

    // General table settings!
    $table->attributes['class'] .= ' table table-hover grading_gradingtable';
    $tablepostfix = "";
    $tablecolumns = [];
    $tableheaders = [];
    // Determine what mode we have to interpret the selected items the right way!
    if ($filter == GROUPTOOL_FILTER_ALL || $filter == GROUPTOOL_FILTER_NONCONFLICTING) {
        // Multiple groups?
        $tablecolumns = [
            'select',
            'name',
            'gradeinfo'
        ];
        $button = html_writer::tag('button', get_string('copy', 'gradereport_gradinggroups'), [
            'name'  => 'copygrades',
            'type'  => 'submit',
            'value' => 'true',
            'class' => 'btn btn-primary'
        ]);
        $buttontext = get_string('copy_refgrades_feedback', 'gradereport_gradinggroups');
        $tableheaders = [
            '',
            get_string('name'),
            get_string('reference_grade_feedback', 'gradereport_gradinggroups')
        ];

        $groups = groups_get_all_groups($course->id, 0, $grouping);
        $cmtouse = get_coursemodule_from_id('', $activity, $course->id);

        foreach ($groups as $group) {
            $error = "";
            $groupmembers = groups_get_members($group->id);
            // Get grading info for all group members!
            $gradinginfo = grade_get_grades($course->id, 'mod', $cmtouse->modname,
                $cmtouse->instance, array_keys($groupmembers));
            $gradeinfo = [];
            if (in_array($group->id, $missingsource)) {
                $error = ' error';
                $gradeinfo[] = html_writer::tag('div', get_string('missing_source_selection',
                    'gradereport_gradinggroups'));
            }

            $userwithgrades = [];
            foreach ($groupmembers as $key => $groupmember) {
                if (!empty($gradinginfo->items[0]->grades[$groupmember->id]->dategraded)
                    && (!$mygroupsonly
                        || $gradinginfo->items[0]->grades[$groupmember->id]->usermodified == $USER->id)) {
                    $userwithgrades[] = $key;
                }
            }
            if ((count($userwithgrades) != 1)
                && ($filter == GROUPTOOL_FILTER_NONCONFLICTING)) {
                /*
                 * skip groups with more than 1 grade and groups without grade
                 * if only nonconflicting should be reviewed
                 */
                continue;
            }
            if ((count($userwithgrades) == count($groupmembers)) && ($incompleteonly == 1)) {
                // Skip groups fully graded if it's wished!
                continue;
            }
            foreach ($userwithgrades as $key) {
                $finalgrade = $gradinginfo->items[0]->grades[$key];
                if (!empty($finalgrade->dategraded)) {
                    $grademax = $gradinginfo->items[0]->grademax;
                    $finalgrade->formatted_grade = round($finalgrade->grade, 2) .' / ' .
                        round($grademax, 2);
                    $radioattr = [
                        'name'  => 'source['.$group->id.']',
                        'value' => $groupmembers[$key]->id,
                        'type'  => 'radio',
                        'class' => 'form-check-input'
                    ];

                    if (count($userwithgrades) == 1) {
                        $radioattr['disabled'] = 'disabled';
                        $radioattr['checked'] = 'checked';
                        $gradeinfocont = html_writer::empty_tag('input', $radioattr);
                        unset($radioattr['disabled']);
                        $radioattr['type'] = 'hidden';
                        $gradeinfocont .= html_writer::empty_tag('input', $radioattr);
                    } else if (count($userwithgrades) > 1) {
                        $gradeinfocont = html_writer::empty_tag('input', $radioattr);
                    } else {
                        $gradeinfocont = '';
                    }
                    $gradeinfocont .= ' '.fullname($groupmembers[$key])." (".$finalgrade->formatted_grade;
                    if (strip_tags($finalgrade->str_feedback) != "") {
                        $gradeinfocont .= " ".shorten_text(strip_tags($finalgrade->str_feedback), 15);
                    }
                    $gradeinfocont .= ")";
                    $label = html_writer::tag('label', $gradeinfocont, [
                        'class' => 'form-check-label gradinginfo'.
                            $groupmembers[$key]->id
                    ]);
                    $gradeinfo[] = html_writer::tag('div', $label, ['class' => 'form-check']);
                }
            }
            $selectattr = [
                'type' => 'checkbox',
                'name' => 'selected[]',
                'value' => $group->id,
                'class' => 'form-check-input'
            ];
            $checkboxcontroller = optional_param('select', '', PARAM_ALPHA);
            if ((count($groupmembers) <= 1) || count($userwithgrades) == 0) {
                $selectattr['disabled'] = 'disabled';
                unset($selectattr['checked']);
            } else if ($checkboxcontroller == 'all') {
                $selectattr['checked'] = "checked";
            } else if ($checkboxcontroller == 'none') {
                unset($selectattr['checked']);
            } else if (isset($selected[$group->id]) && $selected[$group->id] == 1) {
                $selectattr['checked'] = "checked";
            }
            $checkbox = html_writer::tag('label', html_writer::empty_tag('input', $selectattr),
                ['class' => 'form-check-label']);

            $select = new html_table_cell(html_writer::tag('div', $checkbox, ['class' => 'form-check']));
            $name = new html_table_cell($group->name);
            if (empty($gradeinfo)) {
                $gradeinfo = new html_table_cell(get_string('no_grades_present', 'gradereport_gradinggroups'));
            } else {
                $gradeinfo = new html_table_cell(implode("\n", $gradeinfo));
            }

            $row = new html_table_row([$select, $name, $gradeinfo]);
            $tmpclass = $row->attributes['class'];
            $row->attributes['class'] = isset($tmpclass) ? $tmpclass.$error : $tmpclass;
            unset($tmpclass);
            $data[] = $row;
        }
        $tablepostfix = html_writer::tag('div', $buttontext, ['class' => 'd-flex justify-content-center']);
        $tablepostfix .= html_writer::tag('div', $button, ['class' => 'd-flex justify-content-center']);

    } else if ($filter > 0) {    // Single group?
        $tablecolumns = [
            'select',
            'fullname',
            'idnumber',
            'grade',
            'feedback',
            'copybutton'
        ];
        $tableheaders = [
            get_string('target', 'gradereport_gradinggroups'),
            get_string('fullname'),
            get_string('idnumber'),
            get_string('grade', 'grades'),
            get_string('feedback'),
            get_string('source', 'gradereport_gradinggroups')
        ];

        $groupmembers = groups_get_members($filter);
        // Get grading info for all groupmembers!
        $cmtouse = get_coursemodule_from_id('', $activity, $course->id);
        $gradinginfo = grade_get_grades($course->id, 'mod', $cmtouse->modname,
            $cmtouse->instance, array_keys($groupmembers));
        if (isset($gradinginfo->items[0])) {
            foreach ($groupmembers as $groupmember) {
                $row = [];
                $finalgrade = $gradinginfo->items[0]->grades[$groupmember->id];
                $grademax = $gradinginfo->items[0]->grademax;
                $finalgrade->formatted_grade = round($finalgrade->grade, 2) .' / ' .
                    round($grademax, 2);
                $checkboxcontroller = optional_param('select', '', PARAM_ALPHA);
                if ($checkboxcontroller == 'all') {
                    $checked = true;
                } else if ($checkboxcontroller == 'none') {
                    $checked = false;
                } else {
                    $checked = (isset($selected[$groupmember->id])
                        && ($selected[$groupmember->id] == 1)) ? true : false;
                }
                $checkbox = html_writer::tag('label', html_writer::checkbox('selected[]', $groupmember->id,
                    $checked, '', ['class' => 'checkbox form-check-element']), ['class' => 'form-check-label']);

                $row[] = new html_table_cell(html_writer::tag('div', $checkbox, ['class' => 'form-check']));
                $row[] = html_writer::tag('div', fullname($groupmember), ['class' => 'fullname'.$groupmember->id]);
                $row[] = html_writer::tag('div', $groupmember->idnumber, ['class' => 'idnumber'.$groupmember->id]);
                $row[] = html_writer::tag('div', $finalgrade->formatted_grade, ['class' => 'grade'.$groupmember->id]);
                $row[] = html_writer::tag('div', shorten_text(strip_tags($finalgrade->str_feedback), 15),
                    ['class' => 'feedback'.$groupmember->id]);
                if ($mygroupsonly && ($finalgrade->usermodified != $USER->id)) {
                    $row[] = html_writer::tag('div', get_string('not_graded_by_me', 'gradereport_gradinggroups'));
                } else {
                    $row[] = html_writer::tag('button',
                        get_string('copygrade', 'gradereport_gradinggroups'),
                        [
                            'type'  => 'submit',
                            'name'  => 'source',
                            'value' => $groupmember->id,
                            'class' => 'btn btn-primary'
                        ]);
                }
                $data[] = $row;
            }
        } else {
            return $OUTPUT->box($OUTPUT->notification(get_string('no_grades_present', 'gradereport_gradinggroups'),
                \core\output\notification::NOTIFY_ERROR), 'generalbox centered');
        }
    } else {
        print_error('uknown filter-value');
    }

    if (empty($data)) {
        if ($filter == GROUPTOOL_FILTER_ALL) {
            return $OUTPUT->box($OUTPUT->notification(get_string('no_data_to_display', 'gradereport_gradinggroups'),
                \core\output\notification::NOTIFY_ERROR), 'generalbox centered');
        } else if ($filter == GROUPTOOL_FILTER_NONCONFLICTING) {
            return $OUTPUT->box($OUTPUT->notification(get_string('no_conflictfree_to_display', 'gradereport_gradinggroups'),
                    \core\output\notification::NOTIFY_ERROR), 'centered').
                get_grading_table($activity, $mygroupsonly, $incompleteonly,
                    GROUPTOOL_FILTER_ALL, $selected, $context, $course, $missingsource);
        } else {
            return $OUTPUT->box($OUTPUT->notification(get_string('no_groupmembers_to_display', 'gradereport_gradinggroups'),
                    \core\output\notification::NOTIFY_ERROR), 'centered').
                get_grading_table($activity, $mygroupsonly, $incompleteonly,
                    GROUPTOOL_FILTER_ALL, $selected, $context, $course, $missingsource);
        }
    }

    $table->colclasses = $tablecolumns;
    // Instead of the strings an array of html_table_cells can be set as head!
    $table->head = $tableheaders;
    // Instead of the strings an array of html_table_cells can be used for the rows!
    $table->data = $data;
    $overwrite = optional_param('overwrite', 0, PARAM_BOOL);
    $grouping = optional_param('grouping', 0, PARAM_INT);
    $baseurl = new \moodle_url($PAGE->url, [
        'activity' => $activity,
        'mygroups_only' => $mygroupsonly,
        'incomplete_only' => $incompleteonly,
        'filter' => $filter,
        'overwrite' => $overwrite,
        'grouping' => $grouping
    ]);
    $selectallurl = new \moodle_url($baseurl, ['select' => 'all']);
    $selectnoneurl = new \moodle_url($baseurl, ['select' => 'none']);
    $links = get_string('select').' '.
        \html_writer::link($selectallurl, get_string('all'), ['class' => 'select_all']).'/'.
        \html_writer::link($selectnoneurl, get_string('none'), ['class' => 'select_none']);
    $checkboxcontroller = html_writer::tag('div', $links, ['class' => 'checkboxcontroller']);

    return $checkboxcontroller.html_writer::table($table).$tablepostfix;
}
