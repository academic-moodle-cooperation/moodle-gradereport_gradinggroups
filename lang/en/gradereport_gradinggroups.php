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

$string['pluginname'] = 'Groupgrade';
$string['copy_grades_overwrite_confirm'] = 'Continue copying these grades? Existing previous grades get overwritten!';
$string['copy_grades_confirm'] = 'Continue copying these grades?';
$string['no_target_selected'] = 'There\'s no destination for the copy operation selected. You must select at least 1 destination!';
$string['sources_missing'] = 'There\'s at least 1 group without a chosen source to copy from!';
$string['copy_grades_errors'] = 'At least 1 error occurred during copying of grades:';
$string['copy_grades_success'] = 'The following grades where successfully updated:';
$string['skipped'] = 'Skipped';
$string['copied_grade_feedback'] = 'Group grading<br />
+Submission from: <strong>{$a->student}</strong><br />
+Graded by: <strong>{$a->teacher}</strong><br />
+Original Date/Time: <strong>{$a->date}</strong><br />
+Feedback: <strong>{$a->feedback}</strong>';
$string['chooseactivity'] = 'You have to choose an activity before data can be displayed!';
$string['copy'] = 'Copy';
$string['copy_refgrades_feedback'] = 'Copy reference grades and feedback for selected groups to other group members';
$string['reference_grade_feedback'] = 'Reference-grade / Feedback';
$string['missing_source_selection'] = 'No source selected!';
$string['no_grades_present'] = 'No grades to show';
$string['target'] = 'Target';
$string['source'] = 'Source';
$string['not_graded_by_me'] = 'Graded by another user';
$string['copygrade'] = 'Copy grade';
$string['no_grades_present'] = 'No grades to show';
$string['no_data_to_display'] = 'No group(s) data to display!';
$string['no_conflictfree_to_display'] = 'No conflict-free groups to display. So we try to display all instead!';
$string['no_groupmembers_to_display'] = 'No group members to display. So we try to display all groups instead!';
$string['privacy:metadata'] = 'The Gradinggroups reports plugin does not store any personal data.';
$string['filters_legend'] = 'Filter data';
$string['grading_activity_title'] = 'Activity';
$string['mygroups_only_label'] = 'Show only sources entries I graded';
$string['incomplete_only_label'] = 'Show only groups with missing grades';
$string['overwrite_label'] = 'Overwrite existing grades';
$string['grading_grouping_select_title'] = 'Filter grouping';
$string['nonconflicting'] = 'Without conflicts';
$string['grading_filter_select_title'] = 'Group or groups';
$string['refresh_table_button'] = 'Refresh preview';
$string['groupselection'] = 'Group selection';
$string['gradereport_gradinggroups'] = 'Groupgrade';
$string['grading_filter_select_title_help'] = 'Choose which group or groups to use:<ul><li>Without conflicts - all groups, in which only 1 group member got graded for the chosen activity</li><li>All - all groups</li><li>"Group-name" - only the specifically selected group</li></ul>';
$string['groupselection_help'] = 'Choose the groups/persons for which you wish to copy the chosen reference-grade and -feedback by activating the corresponding checkboxes. If only 1 group is displayed you select the source for copying chosen grade by using the corresponding button right of the entry.';
$string['coursesum'] = 'Coursesum';
$string['gradinggroups:grade'] = 'Copy grades from a group-member to others';
$string['gradinggroups:grade_own_submission'] = 'Copy grades from a group-member to others, if it is you own submission';
$string['gradinggroups:view'] = 'View Grade report Gradinggroups';
