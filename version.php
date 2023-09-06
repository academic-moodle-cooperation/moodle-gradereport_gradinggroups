<?php
/**
 * Version page
 *
 * @package       gradereport_gradinggroups
 * @author        Anne Kreppenhofer (annek03@univie.ac.at)
 * @copyright     2023 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();


$plugin->version  = 2023083000;
$plugin->release = "v4.2.0";       // User-friendly version number.
$plugin->maturity = MATURITY_ALPHA;
$plugin->requires = 2022112800;      // Requires this Moodle version!
$plugin->component = 'gradereport_gradinggroups';    // To check on upgrade, that module sits in correct place.
// $plugin->dependencies = ['mod_grouptool' => 2022113000]; // requires this moodle version


