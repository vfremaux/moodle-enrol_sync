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
 * Guest enrolment external functions and service definitions.
 *
 * @package    enrol_sync
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.1
 */
defined('MOODLE_INTERNAL') || die();

$functions = [

    'enrol_sync_get_instance_info' => [
        'classname'   => 'enrol_sync_external',
        'methodname'  => 'get_instance_info',
        'classpath'   => 'enrol/sync/externallib.php',
        'description' => 'Get info about enrol instances',
        'capabilities'=> 'enrol/sync:config',
        'type'        => 'read',
    ],

    // === enrol related functions ===
    'enrol_sync_enrol_users' => [
        'classname'   => 'enrol_sync_external',
        'methodname'  => 'enrol_users',
        'classpath'   => 'enrol/sync/externallib.php',
        'description' => 'Synced enrol users',
        'capabilities'=> 'enrol/sync:enrol',
        'type'        => 'write',
    ],

    'enrol_sync_unenrol_users' => [
        'classname'   => 'enrol_sync_external',
        'methodname'  => 'unenrol_users',
        'classpath'   => 'enrol/sync/externallib.php',
        'description' => 'Sync unenrol users',
        'capabilities'=> 'enrol/sync:unenrol',
        'type'        => 'write',
    ],

    'enrol_sync_get_enrolled_users' => [
        'classname'   => 'enrol_sync_external',
        'methodname'  => 'get_enrolled_users',
        'classpath'   => 'enrol/sync/externallib.php',
        'description' => 'Get users enrolled with enrol sync plugin',
        'capabilities'=> 'enrol/sync:enrol',
        'type'        => 'write',
    ],
];
