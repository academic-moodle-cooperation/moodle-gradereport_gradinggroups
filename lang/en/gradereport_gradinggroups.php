<?php
// This file is part of gradereport_gradinggroups for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'gradereport_gradinggroups', language 'en'
 *
 * @package   gradereport_gradinggroups
 * @author    Anne Kreppenhofer
 * @copyright 2024 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['chooseactivity'] = 'You have to choose an activity before data can be displayed!';
$string['copied_grade_feedback'] = 'Group grading<br />+Submission from: <strong>{$a->student}</strong><br />+Graded by: <strong>{$a->teacher}</strong><br />+Original Date/Time: <strong>{$a->date}</strong><br />+Feedback: <strong>{$a->feedback}</strong>';
$string['copy'] = 'Copy';
$string['copy_grades_confirm'] = 'Continue copying these grades?';
$string['copy_grades_errors'] = 'At least 1 error occurred during copying of grades:';
$string['copy_grades_overwrite_confirm'] = 'Continue copying these grades? Existing previous grades get overwritten!';
$string['copy_grades_success'] = 'The following grades where successfully updated:';
$string['copy_refgrades_feedback'] = 'Copy reference grades and feedback of selected groups to other group members';
$string['copygrade'] = 'Copy grade';
$string['coursesum'] = 'Course sum';
$string['filters_legend'] = 'Filter data';
$string['grade'] = 'Grade';
$string['gradereport_gradinggroups'] = 'Groupgrade';
$string['grading_activity_title'] = 'Activity';
$string['grading_filter_select_title'] = 'Group or groups';
$string['grading_filter_select_title_help'] = 'Choose which group or groups to use:<ul><li>Without conflicts - all groups, in which only 1 group member got graded for the chosen activity</li><li>All - all groups</li><li>Groupname - only the specifically selected group</li></ul>';
$string['grading_grouping_select_title'] = 'Filter grouping';
$string['gradinggroups:grade'] = 'Copy grades from a group member to others';
$string['gradinggroups:grade_own_submission'] = 'Copy grades from a group member to others, if it is their own submission';
$string['gradinggroups:view'] = 'View grade report for grading groups';
$string['groupselection'] = 'Group selection';
$string['groupselection_help'] = 'Choose the groups/persons for which you wish to copy the chosen reference grade and feedback by activating the corresponding checkboxes. If only 1 group is displayed , select the source for copying the chosen grade by using the corresponding button to the right of the entry.';
$string['incomplete_only_label'] = 'Show only groups with missing grades';
$string['missing_source_selection'] = 'No source selected!';
$string['mygroups_only_label'] = 'Show only sources graded by me';
$string['no_conflictfree_to_display'] = 'No conflict-free groups to display. All groups displayed instead!';
$string['no_data_to_display'] = 'No group data to display!';
$string['no_grade_yet'] = 'No grades yet';
$string['no_grades_present'] = 'No grades to show';
$string['no_groupmembers_to_display'] = 'No group members to display. So we try to display all groups instead!';
$string['no_target_selected'] = 'No destination for the copy operation was selected. You must select at least 1 destination!';
$string['nonconflicting'] = 'Without conflicts';
$string['not_graded_by_me'] = 'Graded by another user';
$string['overwrite_label'] = 'Overwrite existing grades';
$string['pluginname'] = 'Groupgrade';
$string['privacy:metadata'] = 'The Gradinggroups reports plugin does not store any personal data.';
$string['reference_grade_feedback'] = 'Reference grade or reference feedback';
$string['refresh_table_button'] = 'Refresh preview';
$string['skipped'] = 'Skipped';
$string['source'] = 'Source';
$string['sources_missing'] = 'There is at least 1 group with no source chosen from which to copy!';
$string['target'] = 'Target';
