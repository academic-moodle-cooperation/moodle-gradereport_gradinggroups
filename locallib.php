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
 * locallib of Gradinggroups
 *
 * @package    gradereport_gradinggroups
 * @author     Anne Kreppenhofer
 * @copyright  2023 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * GRADINGGROUPS_FILTER_NONCONFLICTING - Show just those groups, which have just 1 graded member
 * for this activity
 */
define('GRADINGGROUPS_FILTER_NONCONFLICTING', -1);

/**
 * GRADINGGROUPS_FILTER_ALL - no filter at all...
 */
define('GRADINGGROUPS_FILTER_ALL', 0);

/**
 * View Grading.
 *
 * @param context_course $context Course context
 * @param int $id Course ID
 * @param course|stdClass $course Course
 * @param grade_item|array|null $gradeitems Grade items
 * @return void
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 * @throws required_capability_exception
 */
function gradereport_gradinggroups_view_grading(
    context_course $context,
    int $id,
    course|stdClass $course,
    grade_item|array|null $gradeitems = null
): void {
    global $PAGE, $OUTPUT, $USER;

    require_capability('gradereport/gradinggroups:view', $context);

    $cangradeall = has_capability(
        'gradereport/gradinggroups:grade',
        $context
    );
    $cangradeown = has_capability(
        'gradereport/gradinggroups:grade_own_submission',
        $context
    );
    $cancopygrades = $cangradeall || $cangradeown;

    $refreshtable = optional_param('refresh_table', 0, PARAM_BOOL);
    $activity = optional_param('activity', null, PARAM_INT);

    if ($activity === null) {
        foreach ($gradeitems as $gradeitem) {
            $activity = $gradeitem->gid;
            break;
        }
    }

    // Show only groups with grades given by the current user.
    $mygroupsonly = optional_param('mygroups_only', null, PARAM_BOOL);

    if ($cangradeall) {
        // Users with the general grade capability may change the filter.
        if ($mygroupsonly !== null) {
            set_user_preference(
                'gradereport_gradinggroups_mygroups_only',
                $mygroupsonly,
                $USER->id
            );
        }

        $mygroupsonly = get_user_preferences(
            'gradereport_gradinggroups_mygroups_only',
            0,
            $USER->id
        );
    } else if ($cangradeown) {
        // The filter is mandatory for users who may only copy their own grades.
        $mygroupsonly = 1;
    } else {
        // Users without grading capabilities may still view the report.
        $mygroupsonly = 0;
    }

    // Show only groups containing at least one member without a grade.
    $incompleteonly = optional_param(
        'incomplete_only',
        0,
        PARAM_BOOL
    );

    $overwrite = optional_param('overwrite', 0, PARAM_BOOL);

    // -1 = nonconflicting, 0 = all, positive value = a particular group.
    $filter = optional_param(
        'filter',
        GRADINGGROUPS_FILTER_NONCONFLICTING,
        PARAM_INT
    );

    // Each option only applies to the filter modes in which it is available.
    if ($filter > GRADINGGROUPS_FILTER_ALL) {
        $incompleteonly = 0;
    } else if ($cangradeall) {
        $mygroupsonly = 0;
    }

    // Steps: 0 = show, 1 = confirm, 2 = perform action.
    $step = optional_param('step', 0, PARAM_INT);

    if ($refreshtable) {
        $step = 0;
    }

    // Prevent manipulated requests from starting the copy workflow.
    if (!$cancopygrades) {
        $step = 0;
    }

    $grouping = optional_param('grouping', 0, PARAM_INT);

    if ($filter > 0) {
        if ($step == 2) {
            $source = optional_param('source', null, PARAM_INT);

            $selected = optional_param(
                'selected',
                null,
                PARAM_RAW
            );

            if (!empty($selected)) {
                $selected = json_decode($selected, true);
            }
        } else {
            if ($refreshtable) {
                // Avoid retaining a source when switching to a single group.
                $source = null;
            } else {
                $source = optional_param(
                    'source',
                    null,
                    PARAM_INT
                );
            }

            $selected = optional_param_array(
                'selected',
                null,
                PARAM_INT
            );

            if (!empty($source) && !$refreshtable) {
                $step = 1;
            }
        }
    } else {
        if ($step == 2) {
            $source = optional_param(
                'source',
                null,
                PARAM_RAW
            );

            if (!empty($source)) {
                $source = json_decode($source, true);
            }

            $selected = optional_param(
                'selected',
                null,
                PARAM_RAW
            );

            if (!empty($selected)) {
                $selected = json_decode($selected, true);
            }
        } else {
            $source = optional_param_array(
                'source',
                [],
                PARAM_INT
            );
            $selected = optional_param_array(
                'selected',
                [],
                PARAM_INT
            );
            $copygroups = optional_param(
                'copygrades',
                0,
                PARAM_BOOL
            );

            if ($copygroups && !$refreshtable) {
                $step = 1;
            }
        }
    }

    $confirm = optional_param('confirm', 0, PARAM_BOOL);

    if ($step == 2 && !$confirm) {
        $step = 0;
    }

    // Reset the workflow for invalid or manipulated requests.
    if (!$confirm && (!data_submitted() || !confirm_sesskey())) {
        $step = 0;
    }

    $missingsource = [];

    if ($step == 1) {
        if ($filter > 0) {
            // Single-group mode.
            if (is_array($selected) && in_array($source, $selected)) {
                foreach ($selected as $key => $cmp) {
                    if ($cmp == $source) {
                        unset($selected[$key]);
                    }
                }
            }

            if (!empty($selected)) {
                [, $preview] = gradereport_gradinggroups_copy_grades(
                    $activity,
                    $mygroupsonly,
                    $selected,
                    $source,
                    $context,
                    $course,
                    $overwrite,
                    true
                );

                $continue = new moodle_url(
                    'index.php',
                    [
                        'id' => $id,
                        'tab' => 'grading',
                        'confirm' => 1,
                        'sesskey' => sesskey(),
                        'step' => 2,
                        'activity' => $activity,
                        'mygroups_only' => $mygroupsonly,
                        'overwrite' => $overwrite,
                        'selected' => json_encode($selected),
                        'source' => json_encode($source),
                    ]
                );

                $cancel = new moodle_url(
                    'index.php',
                    [
                        'id' => $id,
                        'tab' => 'grading',
                        'confirm' => 0,
                        'sesskey' => sesskey(),
                        'step' => 2,
                        'activity' => $activity,
                        'mygroups_only' => $mygroupsonly,
                        'overwrite' => $overwrite,
                        'selected' => json_encode($selected),
                        'source' => json_encode($source),
                    ]
                );

                $preview = $OUTPUT->heading(
                    get_string('preview'),
                    2,
                    'centered'
                ) . $preview;

                if ($overwrite) {
                    echo $preview . gradereport_gradinggroups_confirm(
                        get_string(
                            'copy_grades_overwrite_confirm',
                            'gradereport_gradinggroups'
                        ),
                        $continue,
                        $cancel
                    );
                } else {
                    echo $preview . gradereport_gradinggroups_confirm(
                        get_string(
                            'copy_grades_confirm',
                            'gradereport_gradinggroups'
                        ),
                        $continue,
                        $cancel
                    );
                }
            } else {
                $boxcontent = $OUTPUT->notification(
                    get_string(
                        'no_target_selected',
                        'gradereport_gradinggroups'
                    ),
                    \core\output\notification::NOTIFY_ERROR
                );

                echo $OUTPUT->box($boxcontent, 'generalbox');
                unset($boxcontent);

                $step = 0;
            }
        } else if (
            $filter == GRADINGGROUPS_FILTER_ALL
            || $filter == GRADINGGROUPS_FILTER_NONCONFLICTING
        ) {
            // All-groups or nonconflicting-groups mode.
            foreach ($selected as $grp) {
                if (empty($source[$grp])) {
                    $missingsource[] = $grp;
                }
            }

            if (!empty($selected) && count($missingsource) == 0) {
                [, $preview] = gradereport_gradinggroups_copy_grades(
                    $activity,
                    $mygroupsonly,
                    $selected,
                    $source,
                    $context,
                    $course,
                    $overwrite,
                    true
                );

                $continue = new moodle_url(
                    'index.php',
                    [
                        'id' => $id,
                        'tab' => 'grading',
                        'confirm' => 1,
                        'sesskey' => sesskey(),
                        'activity' => $activity,
                        'mygroups_only' => $mygroupsonly,
                        'overwrite' => $overwrite,
                        'step' => 2,
                        'selected' => json_encode($selected),
                        'source' => json_encode($source),
                    ]
                );

                $cancel = new moodle_url(
                    'index.php',
                    [
                        'id' => $id,
                        'tab' => 'grading',
                        'confirm' => 0,
                        'sesskey' => sesskey(),
                        'activity' => $activity,
                        'mygroups_only' => $mygroupsonly,
                        'overwrite' => $overwrite,
                        'step' => 2,
                        'selected' => json_encode($selected),
                        'source' => json_encode($source),
                    ]
                );

                $preview = $OUTPUT->heading(
                    get_string('preview'),
                    2,
                    'centered'
                ) . $preview;

                if ($overwrite) {
                    echo $preview . gradereport_gradinggroups_confirm(
                        get_string(
                            'copy_grades_overwrite_confirm',
                            'gradereport_gradinggroups'
                        ),
                        $continue,
                        $cancel
                    );
                } else {
                    echo $preview . gradereport_gradinggroups_confirm(
                        get_string(
                            'copy_grades_confirm',
                            'gradereport_gradinggroups'
                        ),
                        $continue,
                        $cancel
                    );
                }
            } else {
                if (empty($selected)) {
                    $boxcontent = $OUTPUT->notification(
                        get_string(
                            'no_target_selected',
                            'gradereport_gradinggroups'
                        ),
                        \core\output\notification::NOTIFY_ERROR
                    );

                    echo $OUTPUT->box($boxcontent, 'generalbox');
                    unset($boxcontent);

                    $step = 0;
                }

                if (count($missingsource) != 0) {
                    $boxcontent = $OUTPUT->notification(
                        get_string(
                            'sources_missing',
                            'gradereport_gradinggroups'
                        ),
                        \core\output\notification::NOTIFY_ERROR
                    );

                    echo $OUTPUT->box($boxcontent, 'generalbox');
                    unset($boxcontent);

                    $step = 0;
                }
            }
        } else {
            throw new moodle_exception('wrong parameter');
        }
    }

    if ($step == 2) {
        [$error, $info] = gradereport_gradinggroups_copy_grades(
            $activity,
            $mygroupsonly,
            $selected,
            $source,
            $context,
            $course,
            $overwrite,
            false
        );

        if ($error) {
            $boxcontent = $OUTPUT->notification(
                get_string(
                    'copy_grades_errors',
                    'gradereport_gradinggroups'
                ),
                \core\output\notification::NOTIFY_ERROR
            ) . $info;

            echo $OUTPUT->box(
                $boxcontent,
                'generalbox tumargin'
            );
            unset($boxcontent);
        } else {
            $boxcontent = $OUTPUT->notification(
                get_string(
                    'copy_grades_success',
                    'gradereport_gradinggroups'
                ),
                \core\output\notification::NOTIFY_SUCCESS
            ) . $info;

            echo $OUTPUT->box(
                $boxcontent,
                'generalbox tumargin'
            );
            unset($boxcontent);
        }
    }

    // Show the form unless the confirmation page is currently displayed.
    if ($step != 1 || count($missingsource)) {
        if ($filter > 0) {
            $table = gradereport_gradinggroups_get_grading_table(
                $activity,
                $mygroupsonly,
                $incompleteonly,
                $filter,
                $selected,
                $context,
                $course
            );
        } else {
            $table = gradereport_gradinggroups_get_grading_table(
                $activity,
                $mygroupsonly,
                $incompleteonly,
                $filter,
                $selected,
                $context,
                $course,
                $missingsource
            );
        }

        $formdata = [
            'id' => $id,
            'course' => $course,
            'mygroupsonly' => $mygroupsonly,
            'mygroupsonlylocked' => !$cangradeall,
            'cancopygrades' => $cancopygrades,
            'incompleteonly' => $incompleteonly,
            'overwrite' => $overwrite,
            'grouping' => $grouping,
            'filter' => $filter,
            'table' => $table,
            'gradeitems' => $gradeitems,
            'context' => $context,
        ];

        $mform = new \gradereport_gradinggroups\grading_form(
            $PAGE->url,
            $formdata,
            'post',
            '',
            [
                'class' => 'mform',
                'id' => 'grading_form',
                'name' => 'grading_form',
            ]
        );

        $params = new stdClass();
        $params->lang = current_language();
        $params->contextid = $context->id;

        $PAGE->requires->js_call_amd(
            'gradereport_gradinggroups/grading',
            'initializer',
            [$params]
        );

        $mform->display();
    }
}

/**
 * Copies the grades from the source(s) to the target(s) for the selected activity.
 *
 * @param int $activity ID of activity to get/set grades from/for
 * @param bool $mygroupsonly Limit source grades to those given by current user
 * @param int[]|null $selected Array with IDs of groups/users to copy grades to
 * @param int[]|int $source Source user IDs
 * @param context_course $context Course context
 * @param course $course Course
 * @param bool $overwrite Overwrite existing grades
 * @param bool $previewonly Only return preview data
 * @return array ($error, $message)
 * @throws coding_exception
 * @throws dml_exception
 * @throws required_capability_exception
 */
function gradereport_gradinggroups_copy_grades(
    $activity,
    $mygroupsonly,
    $selected,
    $source,
    $context,
    $course,
    $overwrite = false,
    $previewonly = true
) {
    global $DB, $USER, $OUTPUT;

    $error = false;

    $cangradeall = has_capability(
        'gradereport/gradinggroups:grade',
        $context
    );

    $cangradeown = has_capability(
        'gradereport/gradinggroups:grade_own_submission',
        $context
    );

    // The user needs at least one of the two grading capabilities.
    if (!$cangradeall && !$cangradeown) {
        require_capability(
            'gradereport/gradinggroups:grade_own_submission',
            $context
        );
    }

    // Only users with the general grade capability may disable this restriction.
    if (!$mygroupsonly && !$cangradeall) {
        require_capability(
            'gradereport/gradinggroups:grade',
            $context
        );
    }

    if ($previewonly) {
        $previewtable = new html_table();
        $previewtable->attributes['class'] =
            'table table-hover grading_previewtable';
    } else {
        $previewtable = new stdClass();
    }

    $info = '';

    $gradeitems = grade_item::fetch_all(['id' => $activity]);
    $gradeitem = $gradeitems[$activity];

    if (is_array($source)) {
        // Multigroup mode: filter is 0 or -1.
        $sourceuserids = [];

        foreach ($source as $groupsource) {
            if (is_array($groupsource)) {
                $sourceuserids = array_merge(
                    $sourceuserids,
                    $groupsource
                );
            } else {
                $sourceuserids[] = $groupsource;
            }
        }

        $sourceuserids = array_unique($sourceuserids);

        $sourceusers = $DB->get_records_list(
            'user',
            'id',
            $sourceuserids
        );

        $groups = groups_get_all_groups($course->id);

        $previewtable->head = [
            get_string('groups') . ' (' . count($selected) . ')',
            get_string('fullname'),
            get_string('gradenoun'),
            get_string('feedback'),
        ];

        foreach ($selected as $group) {
            $groupinfo = '';
            $grouprows = [];

            $sourcegroup = is_array($source[$group])
                ? $source[$group]
                : [$source[$group]];

            $sourcegrades = grade_grade::fetch_users_grades(
                $gradeitem,
                $sourcegroup,
                false
            );

            $sourcegrade = reset($sourcegrades);

            if (!$sourcegrade) {
                $error = true;
                continue;
            }

            /*
             * Users possessing only grade_own_submission must not be able
             * to submit another teacher's grade as the source through a
             * manipulated request.
             */
            if (!$cangradeall && $sourcegrade->usermodified != $USER->id) {
                require_capability(
                    'gradereport/gradinggroups:grade',
                    $context
                );
            }

            $sourcegrade->load_optional_fields();

            $origteacher = $DB->get_record(
                'user',
                ['id' => $sourcegrade->usermodified],
                '*',
                MUST_EXIST
            );

            $formattedgrade = (
                $sourcegrade->finalgrade !== null
                    ? round($sourcegrade->finalgrade, 2)
                    : 0
                ) . ' / ' . round($gradeitem->grademax, 2);

            $groupmembers = groups_get_members($group);

            $targetgrades = grade_grade::fetch_users_grades(
                $gradeitem,
                array_keys($groupmembers),
                true
            );

            $propertiestocopy = [
                'rawgrade',
                'finalgrade',
            ];

            foreach ($targetgrades as $currentgrade) {
                if ($currentgrade->id == $sourcegrade->id) {
                    continue;
                }

                if (
                    !$overwrite
                    && $currentgrade->finalgrade !== null
                ) {
                    if ($previewonly) {
                        $rowcells = [];

                        if (empty($grouprows)) {
                            $rowcells[] = new html_table_cell(
                                $groups[$group]->name . "\n" .
                                html_writer::empty_tag('br') .
                                '(' . (count($groupmembers) - 1) . ')'
                            );
                        }

                        $fullname = fullname(
                            $groupmembers[$currentgrade->userid]
                        );

                        $rowcells[] = new html_table_cell($fullname);

                        $cell = new html_table_cell(
                            get_string(
                                'skipped',
                                'gradereport_gradinggroups'
                            )
                        );
                        $cell->colspan = 2;
                        $rowcells[] = $cell;

                        $row = new html_table_row();
                        $row->cells = $rowcells;

                        if (empty($grouprows)) {
                            $row->attributes['class'] = 'firstgrouprow';
                        }

                        $grouprows[] = $row;
                    }

                    continue;
                }

                $currentgrade->load_optional_fields();

                foreach ($propertiestocopy as $property) {
                    $currentgrade->$property = $sourcegrade->$property;
                }

                $sourceuserid = $sourcegrade->userid;

                $details = [
                    'student' => fullname($sourceusers[$sourceuserid]),
                    'teacher' => fullname($origteacher),
                    'date' => userdate(
                        $sourcegrade->get_dategraded(),
                        get_string('strftimedatetimeshort')
                    ),
                    'feedback' => $sourcegrade->feedback
                        ? strip_tags($sourcegrade->feedback)
                        : '',
                ];

                $currentgrade->feedback = $OUTPUT->render_from_template(
                    'gradereport_gradinggroups/feedback',
                    $details
                );
                $currentgrade->feedbackformat = FORMAT_HTML;
                $currentgrade->usermodified = $USER->id;

                if ($previewonly) {
                    $rowcells = [];

                    if (empty($grouprows)) {
                        $rowcells[] = new html_table_cell(
                            $groups[$group]->name . "\n" .
                            html_writer::empty_tag('br') .
                            '(' . count($groupmembers) . ')'
                        );
                    }

                    $fullname = fullname(
                        $groupmembers[$currentgrade->userid]
                    );

                    $rowcells[] = new html_table_cell($fullname);
                    $rowcells[] = new html_table_cell($formattedgrade);
                    $rowcells[] = new html_table_cell(
                        $currentgrade->feedback
                    );

                    $row = new html_table_row();
                    $row->cells = $rowcells;

                    if (empty($grouprows)) {
                        $row->attributes['class'] = 'firstgrouprow';
                    }

                    $grouprows[] = $row;
                } else {
                    if (
                        isset($gradeitem->itemmodule)
                        && function_exists(
                            'gradinggroups_copy_' .
                            $gradeitem->itemmodule .
                            '_grades'
                        )
                    ) {
                        $copyfunction =
                            'gradinggroups_copy_' .
                            $gradeitem->itemmodule .
                            '_grades';

                        $copyfunction(
                            $gradeitem->iteminstance,
                            $sourcegrade->userid,
                            $currentgrade->userid
                        );
                    }

                    if ($currentgrade->id) {
                        $noerror = $currentgrade->update();
                    } else {
                        $noerror = $currentgrade->insert();
                    }

                    $currentgrade->set_overridden(true, false);
                    $currentgrade->grade_item->force_regrading();

                    $fullname = fullname(
                        $groupmembers[$currentgrade->userid]
                    );

                    if ($noerror) {
                        $groupinfo .= html_writer::tag(
                            'span',
                            '✓&nbsp;' . $fullname .
                            ' (' . $formattedgrade . ')',
                            ['class' => 'notifysuccess']
                        );
                    } else {
                        $error = true;

                        $groupinfo .= html_writer::tag(
                            'span',
                            '✗&nbsp;' . $fullname .
                            ' (' . $formattedgrade . ')',
                            ['class' => 'notifyproblem']
                        );
                    }
                }
            }

            if ($previewonly) {
                if (!empty($grouprows)) {
                    $grouprows[0]->cells[0]->rowspan =
                        count($grouprows);

                    if (!isset($previewtable->data)) {
                        $previewtable->data = [];
                    }

                    $previewtable->data = array_merge(
                        $previewtable->data,
                        $grouprows
                    );
                }
            } else {
                $grpinfo = '';

                $grpinfo .= html_writer::tag(
                    'div',
                    $groups[$group]->name .
                    ' (' . count($groupmembers) . '): ' .
                    $groupinfo
                );

                $data = [
                    'student' => fullname(
                        $sourceusers[$sourcegrade->userid]
                    ),
                    'teacher' => fullname($origteacher),
                    'date' => userdate(
                        $sourcegrade->get_dategraded(),
                        get_string('strftimedatetimeshort')
                    ),
                    'feedback' => $sourcegrade->feedback
                        ? strip_tags($sourcegrade->feedback)
                        : '',
                ];

                $grpinfo .= html_writer::tag(
                    'div',
                    $formattedgrade .
                    html_writer::empty_tag('br') .
                    $OUTPUT->render_from_template(
                        'gradereport_gradinggroups/feedback',
                        $data
                    )
                );

                $info .= html_writer::tag(
                    'div',
                    $grpinfo,
                    ['class' => 'box1embottom']
                );
            }
        }
    } else {
        // Single-group mode.
        $sourceuser = $DB->get_record(
            'user',
            ['id' => $source],
            '*',
            MUST_EXIST
        );

        $targetusers = $DB->get_records_list(
            'user',
            'id',
            $selected
        );

        $sourcegrades = grade_grade::fetch_users_grades(
            $gradeitem,
            [$source],
            false
        );

        $sourcegrade = reset($sourcegrades);

        if (!$sourcegrade) {
            return [true, 'Not graded yet'];
        }

        /*
         * This check is required even if mygroupsonly was forced by the UI.
         * Request parameters can be manipulated independently of the form.
         */
        if (!$cangradeall && $sourcegrade->usermodified != $USER->id) {
            require_capability(
                'gradereport/gradinggroups:grade',
                $context
            );
        }

        $sourcegrade->load_optional_fields();

        $origteacher = $DB->get_record(
            'user',
            ['id' => $sourcegrade->usermodified],
            '*',
            MUST_EXIST
        );

        $formattedgrade = (
            $sourcegrade->finalgrade !== null
                ? round($sourcegrade->finalgrade, 2)
                : 0
            ) . ' / ' . round($gradeitem->grademax, 2);

        $targetgrades = grade_grade::fetch_users_grades(
            $gradeitem,
            $selected,
            true
        );

        $propertiestocopy = [
            'rawgrade',
            'finalgrade',
        ];

        $nameinfo = '';
        $grouprows = [];

        if ($previewonly) {
            $count = in_array($source, $selected)
                ? count($selected) - 1
                : count($selected);

            $previewtable->head = [
                '',
                get_string('fullname') . ' (' . $count . ')',
                get_string(
                    'grade',
                    'gradereport_gradinggroups'
                ),
                get_string('feedback'),
            ];

            $previewtable->attributes['class'] =
                'table table-hover grading_previewtable';
        } else {
            $info .= html_writer::start_tag('div');
        }

        foreach ($targetgrades as $currentgrade) {
            if ($currentgrade->id == $sourcegrade->id) {
                continue;
            }

            if (
                !$overwrite
                && $currentgrade->finalgrade !== null
            ) {
                if ($previewonly) {
                    $rowcells = [];

                    if (empty($grouprows)) {
                        $rowcells[] = new html_table_cell(
                            get_string('users')
                        );
                    }

                    $fullname = fullname(
                        $targetusers[$currentgrade->userid]
                    );

                    $rowcells[] = new html_table_cell($fullname);

                    $cell = new html_table_cell(
                        get_string(
                            'skipped',
                            'gradereport_gradinggroups'
                        )
                    );
                    $cell->colspan = 2;
                    $rowcells[] = $cell;

                    $row = new html_table_row();
                    $row->cells = $rowcells;

                    if (empty($grouprows)) {
                        $row->attributes['class'] = 'firstgrouprow';
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
                'date' => userdate(
                    $sourcegrade->get_dategraded(),
                    get_string('strftimedatetimeshort')
                ),
                'feedback' => $sourcegrade->feedback
                    ? strip_tags($sourcegrade->feedback)
                    : '',
            ];
            $currentgrade->feedback = $OUTPUT->render_from_template('gradereport_gradinggroups/feedback', $details);
            $currentgrade->feedbackformat = FORMAT_HTML;
            $currentgrade->usermodified = $USER->id;

            if ($previewonly) {
                $rowcells = [];

                if (empty($grouprows)) {
                    $rowcells[] = new html_table_cell(
                        get_string('users')
                    );
                }

                $fullname = fullname(
                    $targetusers[$currentgrade->userid]
                );

                $rowcells[] = new html_table_cell($fullname);
                $rowcells[] = new html_table_cell($formattedgrade);
                $rowcells[] = new html_table_cell(
                    format_text(
                        $currentgrade->feedback,
                        $currentgrade->feedbackformat
                    )
                );

                $row = new html_table_row();
                $row->cells = $rowcells;

                if (empty($grouprows)) {
                    $row->attributes['class'] = 'firstgrouprow';
                }

                $grouprows[] = $row;
            } else {
                if ($nameinfo !== '') {
                    $nameinfo .= ', ';
                }

                if ($currentgrade->id) {
                    $noerror = $currentgrade->update();
                } else {
                    $noerror = $currentgrade->insert();
                }

                $currentgrade->set_overridden(true, false);
                $currentgrade->grade_item->force_regrading();

                $fullname = fullname(
                    $targetusers[$currentgrade->userid]
                );

                if (
                    isset($gradeitem->itemmodule)
                    && function_exists(
                        'gradinggroups_copy_' .
                        $gradeitem->itemmodule .
                        '_grades'
                    )
                ) {
                    $copyfunction =
                        'gradinggroups_copy_' .
                        $gradeitem->itemmodule .
                        '_grades';

                    $copyfunction(
                        $gradeitem->iteminstance,
                        $sourcegrade->userid,
                        $currentgrade->userid
                    );
                }

                if ($noerror) {
                    $nameinfo .= html_writer::tag(
                        'span',
                        '✓&nbsp;' . $fullname,
                        ['class' => 'notifysuccess']
                    );
                } else {
                    $error = true;

                    $nameinfo .= html_writer::tag(
                        'span',
                        '✗&nbsp;' . $fullname,
                        ['class' => 'notifyproblem']
                    );
                }
            }
        }

        if ($previewonly) {
            if (!empty($grouprows)) {
                $grouprows[0]->cells[0]->rowspan =
                    count($grouprows);
            }

            $previewtable->data = $grouprows;
        } else {
            $info .= $nameinfo . html_writer::end_tag('div');

            $details = [
                'student' => fullname($sourceuser),
                'teacher' => fullname($origteacher),
                'date' => userdate(
                    $sourcegrade->get_dategraded(),
                    get_string('strftimedatetimeshort')
                ),
                'feedback' => $sourcegrade->feedback
                    ? strip_tags($sourcegrade->feedback)
                    : '',
            ];

            $info .= html_writer::tag(
                'div',
                get_string(
                    'grade',
                    'gradereport_gradinggroups'
                ) . ': ' .
                $formattedgrade .
                html_writer::empty_tag('br') .
                $OUTPUT->render_from_template(
                    'gradereport_gradinggroups/feedback',
                    $details
                ),
                ['class' => 'gradeinfo']
            );
        }
    }

    if ($previewonly) {
        return [
            $error,
            html_writer::tag(
                'div',
                html_writer::table($previewtable),
                ['class' => 'centeredblock']
            ),
        ];
    }

    return [
        $error,
        html_writer::tag(
            'div',
            $info,
            ['class' => 'centeredblock']
        ),
    ];
}

/**
 * Print a message along with button choices for Continue/Cancel
 * If a string or moodle_url is given instead of a single_button, method defaults to post.
 * If cancel=null only continue button is displayed!
 *
 * @param string $message The question to ask the user
 * @param single_button|moodle_url|string $continue The single_button component representing the
 *                                                  Continue answer. Can also be a moodle_url
 *                                                  or string URL
 * @param single_button|moodle_url|string $cancel The single_button component representing the
 *                                                  Cancel answer. Can also be a moodle_url or
 *                                                  string URL
 * @return string HTML fragment
 * @throws coding_exception
 * @throws moodle_exception
 */
function gradereport_gradinggroups_confirm($message, $continue, $cancel = null) {
    global $OUTPUT;
    if (!($continue instanceof single_button)) {
        if (is_string($continue)) {
            $url = new moodle_url($continue);
            $continue = new single_button($url, get_string('continue'), 'post', 'primary');
        } else if ($continue instanceof moodle_url) {
            $continue = new single_button($continue, get_string('continue'), 'post', 'primary');
        } else {
            throw new coding_exception('The continue param to gradinggroups::confirm() must be either a' .
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
            throw new coding_exception('The cancel param to gradinggroups::confirm() must be either a' .
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
 * Returns the table used in the group-grading form.
 *
 * @param int $activity ID of activity to get/set grades from/for
 * @param bool|null $mygroupsonly Limit source grades to those given by the current user
 * @param bool|null $incompleteonly Show only groups containing ungraded members
 * @param int $filter GRADINGGROUPS_FILTER_ALL => all groups
 *                    GRADINGGROUPS_FILTER_NONCONFLICTING => groups with exactly one usable grade
 *                    > 0 => ID of a single group
 * @param int[]|null $selected IDs of selected groups/users
 * @param course_context|context_course|context|stdClass $context Course context
 * @param course|stdClass $course Course
 * @param int[] $missingsource IDs of groups for which no source was selected
 * @return string HTML fragment containing checkbox controller and table
 * @throws coding_exception
 * @throws moodle_exception
 */
function gradereport_gradinggroups_get_grading_table(
    int $activity,
    bool|null $mygroupsonly,
    bool|null $incompleteonly,
    int $filter,
    array|null $selected,
    course_context|context_course|context|stdClass $context,
    course|stdClass $course,
    array $missingsource = []
): string {
    global $OUTPUT, $USER, $PAGE;

    $cangradeall = has_capability(
        'gradereport/gradinggroups:grade',
        $context
    );
    $cangradeown = has_capability(
        'gradereport/gradinggroups:grade_own_submission',
        $context
    );
    $cancopygrades = $cangradeall || $cangradeown;

    /*
     * A user possessing only grade_own_submission must never be able to
     * select grades assigned by another user.
     */
    if (!$cangradeall && $cangradeown) {
        $mygroupsonly = true;
    }

    $selected = $selected ?? [];
    $grouping = optional_param('grouping', null, PARAM_INT);

    if ($activity === 0) {
        return $OUTPUT->box(
            $OUTPUT->notification(
                get_string(
                    'chooseactivity',
                    'gradereport_gradinggroups'
                ),
                \core\output\notification::NOTIFY_ERROR
            ),
            'generalbox centered'
        );
    }

    $gradeitems = grade_item::fetch_all(['id' => $activity]);

    if (empty($gradeitems[$activity])) {
        return $OUTPUT->box(
            $OUTPUT->notification(
                get_string(
                    'chooseactivity',
                    'gradereport_gradinggroups'
                ),
                \core\output\notification::NOTIFY_ERROR
            ),
            'generalbox centered'
        );
    }

    $gradeitem = $gradeitems[$activity];

    $table = new html_table();
    $table->attributes['class'] =
        'table table-hover grading_gradingtable';

    $tablepostfix = '';
    $tablecolumns = [];
    $tableheaders = [];
    $data = [];

    $checkboxcontroller = optional_param(
        'select',
        '',
        PARAM_ALPHA
    );

    if (
        $filter === GRADINGGROUPS_FILTER_ALL
        || $filter === GRADINGGROUPS_FILTER_NONCONFLICTING
    ) {
        $countgroups = 0;
        $countgroupswithoutgrades = 0;

        $tablecolumns = [
            'select',
            'name',
            'gradeinfo',
        ];

        $tableheaders = [
            '',
            get_string('name'),
            get_string(
                'reference_grade_feedback',
                'gradereport_gradinggroups'
            ),
        ];

        $groups = groups_get_all_groups(
            $course->id,
            0,
            $grouping
        );

        foreach ($groups as $group) {
            $groupmembers = groups_get_members($group->id);

            if (empty($groupmembers)) {
                continue;
            }

            $countgroups++;

            $errorclass = '';
            $gradeinfo = [];

            if (in_array($group->id, $missingsource)) {
                $errorclass = ' error';

                $gradeinfo[] = html_writer::tag(
                    'div',
                    get_string(
                        'missing_source_selection',
                        'gradereport_gradinggroups'
                    )
                );
            }

            $gradegrades = grade_grade::fetch_users_grades(
                $gradeitem,
                array_keys($groupmembers),
                true
            );

            /*
             * Contains only users whose grade may actually be used as a
             * source by the current user.
             */
            $userswithgrades = [];

            foreach ($groupmembers as $userid => $groupmember) {
                if (empty($gradegrades[$userid])) {
                    continue;
                }

                $gradegrade = $gradegrades[$userid];

                // A grade of 0 is a valid grade.
                if ($gradegrade->finalgrade === null) {
                    continue;
                }

                if (
                    $mygroupsonly
                    && (int) $gradegrade->usermodified !== (int) $USER->id
                ) {
                    continue;
                }

                $userswithgrades[] = $userid;
            }

            if (
                $filter === GRADINGGROUPS_FILTER_NONCONFLICTING
                && count($userswithgrades) !== 1
            ) {
                /*
                 * In nonconflicting mode, only groups with exactly one usable
                 * reference grade are displayed.
                 */
                continue;
            }

            $gradedmembercount = 0;

            foreach ($gradegrades as $gradegrade) {
                if ($gradegrade->finalgrade !== null) {
                    $gradedmembercount++;
                }
            }

            if (
                $incompleteonly
                && $gradedmembercount === count($groupmembers)
            ) {
                continue;
            }

            $firstsource = true;

            foreach ($userswithgrades as $userid) {
                $finalgrade = $gradegrades[$userid];

                if (empty($finalgrade->get_dategraded())) {
                    continue;
                }

                $finalgradeformatted =
                    round($finalgrade->finalgrade, 2)
                    . ' / '
                    . round($gradeitem->grademax, 2);

                $radioattributes = [
                    'name' => 'source[' . $group->id . ']',
                    'value' => $userid,
                    'type' => 'radio',
                    'class' => 'form-check-input',
                ];

                if (!$cancopygrades) {
                    $radioattributes['disabled'] = 'disabled';
                }

                if (count($userswithgrades) === 1) {
                    $radioattributes['checked'] = 'checked';
                    $radioattributes['disabled'] = 'disabled';

                    $gradeinfocontent = html_writer::empty_tag(
                        'input',
                        $radioattributes
                    );

                    /*
                     * Disabled inputs are not submitted. Therefore, an
                     * additional hidden input is required for permitted users.
                     */
                    if ($cancopygrades) {
                        unset($radioattributes['disabled']);
                        unset($radioattributes['checked']);

                        $radioattributes['type'] = 'hidden';

                        $gradeinfocontent .= html_writer::empty_tag(
                            'input',
                            $radioattributes
                        );
                    }
                } else {
                    if ($firstsource) {
                        $radioattributes['checked'] = 'checked';
                        $firstsource = false;
                    }

                    $gradeinfocontent = html_writer::empty_tag(
                        'input',
                        $radioattributes
                    );
                }

                $gradeinfocontent .=
                    ' '
                    . fullname($groupmembers[$userid])
                    . ' ('
                    . $finalgradeformatted;

                if ($finalgrade->feedback !== null) {
                    $gradeinfocontent .=
                        ' '
                        . shorten_text(
                            strip_tags($finalgrade->feedback),
                            15
                        );
                }

                $gradeinfocontent .= ')';

                $label = html_writer::tag(
                    'label',
                    $gradeinfocontent,
                    [
                        'class' =>
                            'form-check-label gradinginfo' . $userid,
                    ]
                );

                $gradeinfo[] = html_writer::tag(
                    'div',
                    $label,
                    ['class' => 'form-check']
                );
            }

            $selectattributes = [
                'type' => 'checkbox',
                'name' => 'selected[]',
                'value' => $group->id,
                'class' => 'form-check-input',
            ];

            if (
                !$cancopygrades
                || count($groupmembers) <= 1
                || count($userswithgrades) === 0
            ) {
                $selectattributes['disabled'] = 'disabled';
            } else if ($checkboxcontroller === 'all') {
                $selectattributes['checked'] = 'checked';
            } else if ($checkboxcontroller !== 'none') {
                if (in_array($group->id, $selected)) {
                    $selectattributes['checked'] = 'checked';
                }
            }

            $checkbox = html_writer::tag(
                'label',
                html_writer::empty_tag(
                    'input',
                    $selectattributes
                ),
                ['class' => 'form-check-label']
            );

            $selectcell = new html_table_cell(
                html_writer::tag(
                    'div',
                    $checkbox,
                    ['class' => 'form-check']
                )
            );

            $namecell = new html_table_cell($group->name);

            if (empty($gradeinfo)) {
                $gradeinfocell = new html_table_cell(
                    get_string(
                        'no_grades_present',
                        'gradereport_gradinggroups'
                    )
                );

                $countgroupswithoutgrades++;
            } else {
                $gradeinfocell = new html_table_cell(
                    implode("\n", $gradeinfo)
                );
            }

            $row = new html_table_row([
                $selectcell,
                $namecell,
                $gradeinfocell,
            ]);

            $existingclass = $row->attributes['class'] ?? '';
            $row->attributes['class'] =
                trim($existingclass . $errorclass);

            $data[] = $row;
        }

        if ($cancopygrades) {
            $buttonattributes = [
                'name' => 'copygrades',
                'type' => 'submit',
                'value' => 'true',
                'class' => 'btn btn-primary',
            ];

            if (
                $countgroups === 0
                || $countgroupswithoutgrades === $countgroups
            ) {
                $buttonattributes['disabled'] = 'disabled';
            }

            $button = html_writer::tag(
                'button',
                get_string(
                    'copy',
                    'gradereport_gradinggroups'
                ),
                $buttonattributes
            );

            $buttontext = get_string(
                'copy_refgrades_feedback',
                'gradereport_gradinggroups'
            );

            $tablepostfix = html_writer::tag(
                'div',
                $buttontext,
                ['class' => 'd-flex justify-content-center']
            );

            $tablepostfix .= html_writer::tag(
                'div',
                $button,
                ['class' => 'd-flex justify-content-center']
            );
        }
    } else if ($filter > 0) {
        // Single-group mode.
        $tablecolumns = [
            'select',
            'fullname',
            'idnumber',
            'grade',
            'feedback',
            'copybutton',
        ];

        $tableheaders = [
            get_string(
                'target',
                'gradereport_gradinggroups'
            ),
            get_string('fullname'),
            get_string('idnumber'),
            get_string(
                'grade',
                'gradereport_gradinggroups'
            ),
            get_string('feedback'),
            get_string(
                'source',
                'gradereport_gradinggroups'
            ),
        ];

        $groupmembers = groups_get_members($filter);

        if (empty($groupmembers)) {
            return $OUTPUT->box(
                $OUTPUT->notification(
                    get_string(
                        'no_groupmembers_to_display',
                        'gradereport_gradinggroups'
                    ),
                    \core\output\notification::NOTIFY_ERROR
                ),
                'centered'
            );
        }

        $gradegrades = grade_grade::fetch_users_grades(
            $gradeitem,
            array_keys($groupmembers),
            true
        );

        foreach ($groupmembers as $groupmember) {
            if (empty($gradegrades[$groupmember->id])) {
                continue;
            }

            $row = [];
            $finalgrade = $gradegrades[$groupmember->id];

            $finalgradeformatted = get_string(
                'no_grade_yet',
                'gradereport_gradinggroups'
            );

            // A grade of 0 must be displayed as a valid grade.
            if ($finalgrade->finalgrade !== null) {
                $finalgradeformatted =
                    round($finalgrade->finalgrade, 2)
                    . ' / '
                    . round($gradeitem->grademax, 2);
            }

            if ($checkboxcontroller === 'all') {
                $checked = true;
            } else if ($checkboxcontroller === 'none') {
                $checked = false;
            } else {
                $checked = in_array(
                    $groupmember->id,
                    $selected
                );
            }

            $checkboxattributes = [
                'class' => 'checkbox form-check-element',
            ];

            if (!$cancopygrades) {
                $checkboxattributes['disabled'] = 'disabled';
                $checked = false;
            }

            $checkbox = html_writer::tag(
                'label',
                html_writer::checkbox(
                    'selected[]',
                    $groupmember->id,
                    $checked,
                    '',
                    $checkboxattributes
                ),
                ['class' => 'form-check-label']
            );

            $row[] = new html_table_cell(
                html_writer::tag(
                    'div',
                    $checkbox,
                    ['class' => 'form-check']
                )
            );

            $row[] = html_writer::tag(
                'div',
                fullname($groupmember),
                ['class' => 'fullname' . $groupmember->id]
            );

            $row[] = html_writer::tag(
                'div',
                $groupmember->idnumber,
                ['class' => 'idnumber' . $groupmember->id]
            );

            $row[] = html_writer::tag(
                'div',
                $finalgradeformatted,
                ['class' => 'grade' . $groupmember->id]
            );

            $row[] = html_writer::tag(
                'div',
                shorten_text(
                    (string) $finalgrade->feedback,
                    15
                ),
                ['class' => 'feedback' . $groupmember->id]
            );

            if (!$cancopygrades) {
                $row[] = '';
            } else if (
                $mygroupsonly
                && (int) $finalgrade->usermodified !== (int) $USER->id
            ) {
                $row[] = html_writer::tag(
                    'div',
                    get_string(
                        'not_graded_by_me',
                        'gradereport_gradinggroups'
                    )
                );
            } else {
                $buttonattributes = [
                    'type' => 'submit',
                    'name' => 'source',
                    'value' => $groupmember->id,
                    'class' => 'btn btn-primary',
                ];

                if ($finalgrade->finalgrade === null) {
                    $buttonattributes['disabled'] = 'disabled';
                }

                $row[] = html_writer::tag(
                    'button',
                    get_string(
                        'copygrade',
                        'gradereport_gradinggroups'
                    ),
                    $buttonattributes
                );
            }

            $data[] = $row;
        }
    } else {
        throw new \core\exception\moodle_exception(
            'unknown filter-value'
        );
    }

    if (empty($data)) {
        if ($filter === GRADINGGROUPS_FILTER_ALL) {
            return $OUTPUT->box(
                $OUTPUT->notification(
                    get_string(
                        'no_data_to_display',
                        'gradereport_gradinggroups'
                    ),
                    \core\output\notification::NOTIFY_ERROR
                ),
                'generalbox centered'
            );
        }

        if ($filter === GRADINGGROUPS_FILTER_NONCONFLICTING) {
            return $OUTPUT->box(
                $OUTPUT->notification(
                    get_string(
                        'no_conflictfree_to_display',
                        'gradereport_gradinggroups'
                    ),
                    \core\output\notification::NOTIFY_ERROR
                ),
                'centered'
            ) . gradereport_gradinggroups_get_grading_table(
                $activity,
                $mygroupsonly,
                $incompleteonly,
                GRADINGGROUPS_FILTER_ALL,
                $selected,
                $context,
                $course,
                $missingsource
            );
        }

        return $OUTPUT->box(
            $OUTPUT->notification(
                get_string(
                    'no_groupmembers_to_display',
                    'gradereport_gradinggroups'
                ),
                \core\output\notification::NOTIFY_ERROR
            ),
            'centered'
        ) . gradereport_gradinggroups_get_grading_table(
            $activity,
            $mygroupsonly,
            $incompleteonly,
            GRADINGGROUPS_FILTER_ALL,
            $selected,
            $context,
            $course,
            $missingsource
        );
    }

    $table->colclasses = $tablecolumns;
    $table->head = $tableheaders;
    $table->data = $data;

    $overwrite = optional_param(
        'overwrite',
        0,
        PARAM_BOOL
    );
    $grouping = optional_param(
        'grouping',
        0,
        PARAM_INT
    );

    $baseurl = new moodle_url(
        $PAGE->url,
        [
            'activity' => $activity,
            'mygroups_only' => $mygroupsonly,
            'incomplete_only' => $incompleteonly,
            'filter' => $filter,
            'overwrite' => $overwrite,
            'grouping' => $grouping,
            'refresh_table' => 1,
        ]
    );

    $selectallurl = new moodle_url(
        $baseurl,
        ['select' => 'all']
    );
    $selectnoneurl = new moodle_url(
        $baseurl,
        ['select' => 'none']
    );

    $controller = '';

    if ($cancopygrades) {
        $links = get_string('select')
            . ' '
            . html_writer::link(
                $selectallurl,
                get_string('all'),
                ['class' => 'select_all']
            )
            . '/'
            . html_writer::link(
                $selectnoneurl,
                get_string('none'),
                ['class' => 'select_none']
            );

        $controller = html_writer::tag(
            'div',
            $links,
            ['class' => 'checkboxcontroller']
        );
    }

    return $controller
        . html_writer::table($table)
        . $tablepostfix;
}
