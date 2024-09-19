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
 * Strings for component 'enrol_sync', language 'en'.
 *
 * @package    enrol_sync
 * @copyright  2010 onwards Valery Fremaux  {@link http://www.mylearningfactory.com}
 * @author  2010 onwards Valery Fremaux  {@link http://www.mylearningfactory.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Privacy.
$string['privacy:metadata'] = "The Sync Enrol do not store any data belonging to users";

$string['sync:config'] = 'Configure sync enrol instances';
$string['sync:enrol'] = 'Enrol users';
$string['sync:unenrol'] = 'Unenrol users from the course';

$string['configcanhideshowinstances'] = 'Can hide/show instances';
$string['configcanhideshowinstances_desc'] = 'If set, the people allowed to manage enrol method will be able to
hide/show enrol sync instances. An hidden instance will still be synchronized with new changes.';
$string['pluginname'] = 'Synced access';
$string['pluginname_desc'] = 'This plugin is not interactive enrol and keeps all user enrolment that were obtained by synced
automation.';
$string['status'] = 'Allow synced access';
$string['status_desc'] = 'Allow temporary synced access by default.';
$string['status_help'] = 'This setting determines whether a user can access the course when owning a synced enrolment instance.';
$string['status_link'] = 'enrol/synced';
$string['sync:config'] = 'Can configure';

$string['wsusercannotassign'] = 'You don\'t have the permission to assign this role ({$a->roleid}) to this user ({$a->userid})
in this course ({$a->courseid}).';
$string['syncpluginnotinstalled'] = 'The "Sync" plugin has not yet been installed or enabled';
