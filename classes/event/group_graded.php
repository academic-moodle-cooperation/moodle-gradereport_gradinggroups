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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * The gradereport_gradinggroups group_graded event.
 *
 * @package   gradereport_gradinggroups
 * @author    Anne Kreppenhofer
 * @copyright 2024 Academic Moodle Cooperation
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradereport_gradinggroups\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when grades are copied to a group or selected users.
 *
 * @package   gradereport_gradinggroups
 * @author    Anne Kreppenhofer
 * @copyright 2024 Academic Moodle Cooperation
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_graded extends \core\event\base {
    /**
     * Initialise the event.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'grade_items';
    }

    /**
     * Create the event when grades are copied to a group.
     *
     * @param \core\context\course $context Course context
     * @param \grade_item $gradeitem Grade item
     * @param \stdClass $data Additional event data
     * @return \core\event\base
     */
    public static function create_direct(
        \core\context\course $context,
        \grade_item $gradeitem,
        \stdClass $data
    ): \core\event\base {
        $data->type = 'group';

        return self::create([
            'objectid' => $gradeitem->id,
            'context' => $context,
            'other' => (array) $data,
        ]);
    }

    /**
     * Create the event when grades are copied to selected users.
     *
     * @param \core\context\course $context Course context
     * @param \grade_item $gradeitem Grade item
     * @param \stdClass $data Additional event data
     * @return \core\event\base
     */
    public static function create_without_groupid(
        \core\context\course $context,
        \grade_item $gradeitem,
        \stdClass $data
    ): \core\event\base {
        $data->type = 'users';

        return self::create([
            'objectid' => $gradeitem->id,
            'context' => $context,
            'other' => (array) $data,
        ]);
    }

    /**
     * Return a description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        if ($this->other['type'] === 'group') {
            return "The user with id '{$this->userid}' group-graded the group " .
                "with id '{$this->other['groupid']}' using the grade item " .
                "with id '{$this->objectid}'.";
        }

        if ($this->other['type'] === 'users') {
            return "The user with id '{$this->userid}' group-graded the user(s) " .
                "with id(s) '" . implode(', ', $this->other['selected']) .
                "' using the grade item with id '{$this->objectid}'.";
        }

        return '';
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string(
            'eventgroupgraded',
            'gradereport_gradinggroups'
        );
    }

    /**
     * Return the URL related to the event.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        $params = [
            'id' => $this->contextinstanceid,
            'activity' => $this->objectid,
            'refresh_table' => 1,
        ];

        if ($this->other['type'] === 'group') {
            $params['filter'] = $this->other['groupid'];
        }

        return new \moodle_url(
            '/grade/report/gradinggroups/index.php',
            $params
        );
    }

    /**
     * Return the object ID mapping for backup and restore.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return [
            'db' => 'grade_items',
            'restore' => 'grade_item',
        ];
    }

    /**
     * Validate the event data.
     *
     * @return void
     */
    protected function validate_data(): void {
        parent::validate_data();

        if (empty($this->objectid)) {
            throw new \coding_exception(
                'The group_graded event must define a grade item ID.'
            );
        }

        if ($this->contextlevel !== CONTEXT_COURSE) {
            throw new \coding_exception(
                'Context level must be CONTEXT_COURSE.'
            );
        }

        if (empty($this->other['type'])) {
            throw new \coding_exception(
                'The grading type must be specified.'
            );
        }

        if (
            $this->other['type'] === 'group'
            && empty($this->other['groupid'])
        ) {
            throw new \coding_exception(
                'ID of group to be graded has to be specified.'
            );
        }

        if (
            $this->other['type'] === 'users'
            && empty($this->other['selected'])
        ) {
            throw new \coding_exception(
                'Users to be graded have to be specified.'
            );
        }
    }
}
