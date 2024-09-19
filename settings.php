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
 * Sync enrolment plugin settings and presets.
 *
 * @package     enrol_sync
 * @author      2017 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @copyright   2017 Valery Fremaux {@link http://www.mylearningfactory.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // General settings.
    $settings->add(new admin_setting_heading('enrol_sync_settings', '', get_string('pluginname_desc', 'enrol_sync')));

    $key = 'enrol_sync/canhideshowinstances';
    $label = get_string('configcanhideshowinstances', 'enrol_sync');
    $desc = get_string('configcanhideshowinstances_desc', 'enrol_sync');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 0));
}
