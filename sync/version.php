<?php // $Id: version.php,v 1.2 2012-11-14 12:06:44 vf Exp $
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
 * Flat file enrolment plugin version specification.
 *
 * @package    enrol
 * @subpackage sync
 * @copyright  2010 Valery Fremaux 
 * @author     Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$plugin->version  = 2011072600;
$plugin->requires = 2007101000;
$plugin->component = 'enrol_sync';  // Full name of the plugin (used for diagnostics)
$plugin->cron      = 60;

?>
