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

/**
 * Gradegroup class
 *
 * @package    gradereport_gradinggroups
 * @author     Anne Kreppenhofer
 * @copyright  2023 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_report_gradinggroups extends grade_report {

    // TODO write Doc

    /**
     * Constructor
     * @param int $courseid
     * @param grade_plugin_return $gpr
     * @param course_context $context
     * @param int|null $page
     * @throws moodle_exception
     */
    public function __construct($courseid, $gpr, $context, $page = null) {
        parent::__construct($courseid, $gpr, $context, $page);
    }

    /**
     * Handles form data sent by this report for this report. Abstract method to implement in all children.
     * @param array $data
     */
    public function process_data($data) {
        // TODO: Implement process_data() method.
    }

    /**
     * Processes a single action against a category, grade_item or grade.
     * @param string $target Sortorder
     * @param string $action Which action to take (edit, delete etc...)
     * @return null
     */
    public function process_action($target, $action) {
        // TODO: Implement process_action() method.
        return null;
    }
}
