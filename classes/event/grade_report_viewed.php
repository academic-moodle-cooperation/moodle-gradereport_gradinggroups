<?php
// This file is part of gradinggroups for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Event triggered when the grading groups report is viewed.
 *
 * @package    gradereport_gradinggroups
 * @copyright  2024 Academic Moodle Cooperation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradereport_gradinggroups\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when the grading groups report is viewed.
 *
 * @package    gradereport_gradinggroups
 */
class grade_report_viewed extends \core\event\base {
    /**
     * Initialise the event.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Return the event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventgradinggroupsviewed', 'gradereport_gradinggroups');
    }

    /**
     * Return the event description.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' viewed the grading groups report " .
            "for the course with id '{$this->courseid}'.";
    }

    /**
     * Return the URL related to this event.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url(
            '/grade/report/gradinggroups/index.php',
            ['id' => $this->courseid]
        );
    }
}