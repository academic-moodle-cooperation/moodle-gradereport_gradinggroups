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
 * Gradegroup class
 *
 * @package    gradereport_gradinggroups
 * @author     Anne Kreppenhofer
 * @copyright  2024 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_report_gradinggroups extends grade_report_grader {

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
     * We get gradeitems for select here.
     */
    public function get_gradeitems() {
        global $CFG, $DB;

        $gradeitems = [];
        $gradetypes = (!empty($CFG->gradedist_showgradeitem)) ? explode(',', $CFG->gradedist_showgradeitem) : [];
        $showgradeitemtypes = (isset($CFG->gradedist_showgradeitemtype)) ? $CFG->gradedist_showgradeitemtype : 0;

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
                $gradeitem->name = get_string('coursesum', 'gradereport_gradedist');
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
