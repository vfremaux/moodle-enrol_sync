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
 * Sync access plugin version specification.
 *
 * @package     enrol_sync
 * @copyright   2017 Valery Fremaux  {@link http://www.mylearningfactory.com}
 * @author      2017 Valery Fremaux  {@link http://www.mylearningfactory.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2025011400;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2022111800;        // Requires this Moodle version.
$plugin->component = 'enrol_sync';     // Full name of the plugin (used for diagnostics).
$plugin->release = '4.5.0 (Build 2023082353)';
$plugin->maturity   = MATURITY_STABLE;
$plugin->supported = [401, 405];

// Non moodle attributes.
$plugin->codeincrement = '4.5.0000';
