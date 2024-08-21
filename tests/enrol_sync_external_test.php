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
 * Sync enrol external PHPunit tests
 *
 * @package   enrol_sync
 * @author  Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright  2023 Valery Fremaux (https://www.activeprolearn.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_sync;

defined('MOODLE_INTERNAL') || die();

use externallib_advanced_testcase;
use StdClass;
use moodle_exception;
use enrol_sync_external;
use external_api;

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Sync enrolment external functions tests
 *
 * @package    enrol_sync
 * @category   external
 * @author  Valery Fremaux <valery.fremaux@gmail.com>
 * @copyright  2023 Valery Fremaux (https://www.activeprolearn.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_sync_external_test extends externallib_advanced_testcase {

    /**
     * Test get_instance_info
     * @covers \enrol_sync_plugin
     */
    public function test_get_instance_info() {
        global $DB;

        $this->resetAfterTest(true);

        // Check if sync enrolment plugin is enabled.
        $syncplugin = enrol_get_plugin('sync');
        $this->assertNotEmpty($syncplugin);

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        $coursedata = new stdClass();
        $coursedata->visible = 0;
        $course = self::getDataGenerator()->create_course($coursedata);

        $student = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'sync');

        // Add enrolment methods for course.
        $instance = $syncplugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'name' => 'Test instance',
            'roleid' => $studentrole->id]);

        $this->setAdminUser();
        $result = enrol_sync_external::get_instance_info($instance);
        $result = external_api::clean_returnvalue(enrol_sync_external::get_instance_info_returns(), $result);

        $this->assertEquals($instance, $result['instanceinfo']['id']);
        $this->assertEquals($course->id, $result['instanceinfo']['courseid']);
        $this->assertEquals('sync', $result['instanceinfo']['type']);
        $this->assertEquals('Test instance', $result['instanceinfo']['name']);
        $this->assertTrue($result['instanceinfo']['status']);

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $instance]);

        $result = enrol_sync_external::get_instance_info($instance);
        $result = external_api::clean_returnvalue(enrol_sync_external::get_instance_info_returns(), $result);
        $this->assertEquals($instance, $result['instanceinfo']['id']);
        $this->assertEquals($course->id, $result['instanceinfo']['courseid']);
        $this->assertEquals('sync', $result['instanceinfo']['type']);
        $this->assertEquals('Test instance', $result['instanceinfo']['name']);
        $this->assertFalse($result['instanceinfo']['status']);

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['id' => $instance]);

        // Try to retrieve information using a normal user for a hidden course.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        try {
            enrol_sync_external::get_instance_info($instance);
        } catch (moodle_exception $e) {
            $this->assertEquals('coursehidden', $e->errorcode);
        }

        // Student user.
        $DB->set_field('course', 'visible', 1, ['id' => $course->id]);
        $this->setUser($student);
        $result = enrol_sync_external::get_instance_info($instance);
        $result = external_api::clean_returnvalue(enrol_sync_external::get_instance_info_returns(), $result);

        $this->assertEquals($instance, $result['instanceinfo']['id']);
        $this->assertEquals($course->id, $result['instanceinfo']['courseid']);
        $this->assertEquals('sync', $result['instanceinfo']['type']);
        $this->assertEquals('Test instance', $result['instanceinfo']['name']);
        $this->assertTrue($result['instanceinfo']['status']);
    }
}
