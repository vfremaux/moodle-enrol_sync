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
 * External course participation api.
 *
 * This api is mostly read only, the actual enrol and unenrol
 * support is in each enrol plugin.
 *
 * @package    enrol_sync
 * @category   external
 * @copyright  2023 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/enrol/sync/lib.php");

/**
 * Manual enrolment external functions.
 *
 * @package    enrol_sync
 * @category   external
 * @copyright  2011 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_sync_external extends external_api {

    /**
     * Returns description of get_instance_info() parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function get_instance_info_parameters() {
        return new external_function_parameters(
                array('instanceid' => new external_value(PARAM_INT, 'Instance id of sync enrolment plugin.'))
            );
    }

    /**
     * Return guest enrolment instance information.
     *
     * @param int $instanceid instance id of guest enrolment plugin.
     * @return array warnings and instance information.
     * @since Moodle 3.1
     */
    public static function get_instance_info($instanceid) {
        global $DB;

        $params = self::validate_parameters(self::get_instance_info_parameters(), array('instanceid' => $instanceid));
        $warnings = array();

        // Retrieve guest enrolment plugin.
        $enrolplugin = enrol_get_plugin('sync');
        if (empty($enrolplugin)) {
            throw new moodle_exception('invaliddata', 'error');
        }

        self::validate_context(context_system::instance());
        $enrolinstance = $DB->get_record('enrol', array('id' => $params['instanceid']), '*', MUST_EXIST);

        $course = $DB->get_record('course', array('id' => $enrolinstance->courseid), '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        if (!$course->visible and !has_capability('moodle/course:viewhiddencourses', $context)) {
            throw new moodle_exception('coursehidden');
        }

        $instanceinfo = $enrolplugin->get_enrol_info($enrolinstance);

        unset($instanceinfo->requiredparam);

        $result = array();
        $result['instanceinfo'] = $instanceinfo;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of get_instance_info() result value.
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function get_instance_info_returns() {
        return new external_single_structure(
            array(
                'instanceinfo' => new external_single_structure(
                    array(
                        'id' => new external_value(PARAM_INT, 'Id of course enrolment instance'),
                        'courseid' => new external_value(PARAM_INT, 'Id of course'),
                        'type' => new external_value(PARAM_PLUGIN, 'Type of enrolment plugin'),
                        'name' => new external_value(PARAM_RAW, 'Name of enrolment plugin'),
                        'status' => new external_value(PARAM_BOOL, 'Is the enrolment enabled?'),
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function enrol_users_parameters() {
        return new external_function_parameters(
                array(
                    'enrolments' => new external_multiple_structure(
                            new external_single_structure(
                                    array(
                                        'roleid' => new external_value(PARAM_INT, 'Role to assign to the user'),
                                        'userid' => new external_value(PARAM_INT, 'The user that is going to be enrolled'),
                                        'courseid' => new external_value(PARAM_INT, 'The course to enrol the user role in'),
                                        'timestart' => new external_value(PARAM_INT, 'Timestamp when the enrolment start', VALUE_DEFAULT, 0),
                                        'timeend' => new external_value(PARAM_INT, 'Timestamp when the enrolment end', VALUE_DEFAULT, 0),
                                        'suspend' => new external_value(PARAM_INT, 'set to 1 to suspend the enrolment', VALUE_DEFAULT, 0)
                                    )
                            )
                    ),
                    'shift' => new external_value(PARAM_BOOL, 'If set, will delete previous manual enrolment of the users', VALUE_DEFAULT, 0)
                )
        );
    }

    /**
     * Enrolment of users.
     *
     * Function throw an exception at the first error encountered.
     * @param array $enrolments  An array of user enrolment
     * @since Moodle 2.2
     */
    public static function enrol_users($enrolments, $shift = false) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/enrollib.php');

        $params = self::validate_parameters(self::enrol_users_parameters(),
                array('enrolments' => $enrolments));

        $transaction = $DB->start_delegated_transaction(); // Rollback all enrolment if an error occurs
                                                           // (except if the DB doesn't support it).

        // Retrieve the sync enrolment plugin.
        $enrol = enrol_get_plugin('sync');
        if (empty($enrol)) {
            throw new moodle_exception('syncpluginnotinstalled', 'enrol_sync');
        }

        foreach ($params['enrolments'] as $enrolment) {
            // Ensure the current user is allowed to run this function in the enrolment context.
            $context = context_course::instance($enrolment['courseid'], IGNORE_MISSING);
            self::validate_context($context);

            // Check that the user has the permission to manual enrol.
            require_capability('enrol/sync:enrol', $context);

            // Throw an exception if user is not able to assign the role.
            $roles = get_assignable_roles($context);
            if (!array_key_exists($enrolment['roleid'], $roles)) {
                $errorparams = new stdClass();
                $errorparams->roleid = $enrolment['roleid'];
                $errorparams->courseid = $enrolment['courseid'];
                $errorparams->userid = $enrolment['userid'];
                throw new moodle_exception('wsusercannotassign', 'enrol_sync', '', $errorparams);
            }

            $course = $DB->get_record('course', [id => $enrolment['courseid']]);
            if (!$course) {
                throw new invalid_parameter_exception('Course id not exist: '.$enrolment['courseid']);
            }

            // Finally proceed the enrolment.
            $enrolment['timestart'] = isset($enrolment['timestart']) ? $enrolment['timestart'] : 0;
            $enrolment['timeend'] = isset($enrolment['timeend']) ? $enrolment['timeend'] : 0;
            $enrolment['status'] = (isset($enrolment['suspend']) && !empty($enrolment['suspend'])) ?
                    ENROL_USER_SUSPENDED : ENROL_USER_ACTIVE;
            enrol_sync_plugin::static_enrol_user($course, $enrolment['userid'], $enrolment['roleid'],
                    $enrolment['timestart'], $enrolment['timeend'], $enrolment['status'], $shift);
        }

        $transaction->allow_commit();
    }

    /**
     * Returns description of method result value.
     *
     * @return null
     * @since Moodle 2.2
     */
    public static function enrol_users_returns() {
        return null;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function unenrol_users_parameters() {
        return new external_function_parameters(array(
            'enrolments' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'userid' => new external_value(PARAM_INT, 'The user that is going to be unenrolled'),
                        'courseid' => new external_value(PARAM_INT, 'The course to unenrol the user from'),
                        'roleid' => new external_value(PARAM_INT, 'The user role', VALUE_OPTIONAL),
                    )
                )
            )
        ));
    }

    /**
     * Unenrolment of users.
     *
     * @param array $enrolments an array of course user and role ids
     * @throws coding_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function unenrol_users($enrolments) {
        global $CFG, $DB;

        $params = self::validate_parameters(self::unenrol_users_parameters(), array('enrolments' => $enrolments));
        require_once($CFG->libdir . '/enrollib.php');
        $transaction = $DB->start_delegated_transaction(); // Rollback all enrolment if an error occurs.
        $enrol = enrol_get_plugin('manual');
        if (empty($enrol)) {
            throw new moodle_exception('manualpluginnotinstalled', 'enrol_manual');
        }

        foreach ($params['enrolments'] as $enrolment) {
            $context = context_course::instance($enrolment['courseid']);
            self::validate_context($context);
            require_capability('enrol/sync:unenrol', $context);

            $user = $DB->get_record('user', array('id' => $enrolment['userid']));
            if (!$user) {
                throw new invalid_parameter_exception('User id not exist: '.$enrolment['userid']);
            }

            $course = $DB->get_record('course', [id => $enrolment['courseid']]);
            if (!$course) {
                throw new invalid_parameter_exception('Course id not exist: '.$enrolment['courseid']);
            }

            enrol_sync_plugin::static_unenrol_user($course, $enrolment['userid']);
        }
        $transaction->allow_commit();
    }

    /**
     * Returns description of method result value.
     *
     * @return null
     */
    public static function unenrol_users_returns() {
        return null;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_enrolled_users_parameters() {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'course id'),
                'options'  => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'name'  => new external_value(PARAM_ALPHANUMEXT, 'option name'),
                            'value' => new external_value(PARAM_RAW, 'option value')
                        ]
                    ), 'Option names:
                            * withcapability (string) return only users with this capability. This option requires \'moodle/role:review\' on the course context.
                            * groupid (integer) return only users in this group id. If the course has groups enabled and this param
                                                isn\'t defined, returns all the viewable users.
                                                This option requires \'moodle/site:accessallgroups\' on the course context if the
                                                user doesn\'t belong to the group.
                            * onlyactive (integer) return only users with active enrolments and matching time restrictions.
                                                This option requires \'moodle/course:enrolreview\' on the course context.
                                                Please note that this option can\'t
                                                be used together with onlysuspended (only one can be active).
                            * onlysuspended (integer) return only suspended users. This option requires
                                            \'moodle/course:enrolreview\' on the course context. Please note that this option can\'t
                                                be used together with onlyactive (only one can be active).
                            * userfields (\'string, string, ...\') return only the values of these user fields.
                            * limitfrom (integer) sql limit from.
                            * limitnumber (integer) maximum number of returned users.
                            * sortby (string) sort by id, firstname or lastname. For ordering like the site does, use siteorder.
                            * sortdirection (string) ASC or DESC',
                            VALUE_DEFAULT, []),
            ]
        );
    }

    /**
     * Unenrolment of users.
     *
     * @param array $enrolments an array of course user and role ids
     * @throws coding_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function get_enrolled_users($enrolments) {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . "/user/lib.php");

        $params = self::validate_parameters(
            self::get_enrolled_users_parameters(),
            [
                'courseid'=>$courseid,
                'options'=>$options
            ]
        );
        $withcapability = '';
        $groupid        = 0;
        $onlyactive     = false;
        $onlysuspended  = false;
        $userfields     = [];
        $limitfrom = 0;
        $limitnumber = 0;
        $sortby = 'us.id';
        $sortparams = [];
        $sortdirection = 'ASC';
        foreach ($options as $option) {
            switch ($option['name']) {
                case 'withcapability':
                    $withcapability = $option['value'];
                    break;
                case 'groupid':
                    $groupid = (int)$option['value'];
                    break;
                case 'onlyactive':
                    $onlyactive = !empty($option['value']);
                    break;
                case 'onlysuspended':
                    $onlysuspended = !empty($option['value']);
                    break;
                case 'userfields':
                    $thefields = explode(',', $option['value']);
                    foreach ($thefields as $f) {
                        $userfields[] = clean_param($f, PARAM_ALPHANUMEXT);
                    }
                    break;
                case 'limitfrom' :
                    $limitfrom = clean_param($option['value'], PARAM_INT);
                    break;
                case 'limitnumber' :
                    $limitnumber = clean_param($option['value'], PARAM_INT);
                    break;
                case 'sortby':
                    $sortallowedvalues = ['id', 'firstname', 'lastname', 'siteorder'];
                    if (!in_array($option['value'], $sortallowedvalues)) {
                        throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' .
                            $option['value'] . '), allowed values are: ' . implode(',', $sortallowedvalues));
                    }
                    if ($option['value'] == 'siteorder') {
                        list($sortby, $sortparams) = users_order_by_sql('us');
                    } else {
                        $sortby = 'us.' . $option['value'];
                    }
                    break;
                case 'sortdirection':
                    $sortdirection = strtoupper($option['value']);
                    $directionallowedvalues = ['ASC', 'DESC'];
                    if (!in_array($sortdirection, $directionallowedvalues)) {
                        throw new invalid_parameter_exception('Invalid value for sortdirection parameter
                        (value: ' . $sortdirection . '),' . 'allowed values are: ' . implode(',', $directionallowedvalues));
                    }
                    break;
            }
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
        if ($courseid == SITEID) {
            $context = context_system::instance();
        } else {
            $context = $coursecontext;
        }
        try {
            self::validate_context($context);
        } catch (Exception $e) {
            $exceptionparam = new stdClass();
            $exceptionparam->message = $e->getMessage();
            $exceptionparam->courseid = $params['courseid'];
            throw new moodle_exception('errorcoursecontextnotvalid' , 'webservice', '', $exceptionparam);
        }

        course_require_view_participants($context);

        // to overwrite this parameter, you need role:review capability
        if ($withcapability) {
            require_capability('moodle/role:review', $coursecontext);
        }
        // need accessallgroups capability if you want to overwrite this option
        if (!empty($groupid) && !groups_is_member($groupid)) {
            require_capability('moodle/site:accessallgroups', $coursecontext);
        }
        // to overwrite this option, you need course:enrolereview permission
        if ($onlyactive || $onlysuspended) {
            require_capability('moodle/course:enrolreview', $coursecontext);
        }

        $plugin = enrol_get_plugin('sync');
        $syncenrolid = $plugin->add_instance($course);

        list($enrolledsql, $enrolledparams) = get_enrolled_sql($coursecontext, $withcapability, $groupid, $onlyactive,
        $onlysuspended, $syncenrolid);
        $ctxselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = u.id AND ctx.contextlevel = :contextlevel)";
        $enrolledparams['contextlevel'] = CONTEXT_USER;

        $groupjoin = '';
        if (empty($groupid) && groups_get_course_groupmode($course) == SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups', $coursecontext)) {
            // Filter by groups the user can view.
            $usergroups = groups_get_user_groups($course->id);
            if (!empty($usergroups['0'])) {
                list($groupsql, $groupparams) = $DB->get_in_or_equal($usergroups['0'], SQL_PARAMS_NAMED);
                $groupjoin = "JOIN {groups_members} gm ON (u.id = gm.userid AND gm.groupid $groupsql)";
                $enrolledparams = array_merge($enrolledparams, $groupparams);
            } else {
                // User doesn't belong to any group, so he can't see any user. Return an empty array.
                return [];
            }
        }
        $sql = "SELECT us.*, COALESCE(ul.timeaccess, 0) AS lastcourseaccess
                  FROM {user} us
                  JOIN (
                      SELECT DISTINCT u.id $ctxselect
                        FROM {user} u $ctxjoin $groupjoin
                       WHERE u.id IN ($enrolledsql)
                  ) q ON q.id = us.id
             LEFT JOIN {user_lastaccess} ul ON (ul.userid = us.id AND ul.courseid = :courseid)
                ORDER BY $sortby $sortdirection";
        $enrolledparams = array_merge($enrolledparams, $sortparams);
        $enrolledparams['courseid'] = $courseid;

        $enrolledusers = $DB->get_recordset_sql($sql, $enrolledparams, $limitfrom, $limitnumber);
        $users = [];
        foreach ($enrolledusers as $user) {
            context_helper::preload_from_record($user);
            if ($userdetails = user_get_user_details($user, $course, $userfields)) {
                $users[] = $userdetails;
            }
        }
        $enrolledusers->close();

        return $users;
    }

    /**
     * Returns description of method result value.
     *
     * @return null
     */
    public static function get_enrolled_users_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'id'    => new external_value(PARAM_INT, 'ID of the user'),
                    'username'    => new external_value(PARAM_RAW, 'Username policy is defined in Moodle security config', VALUE_OPTIONAL),
                    'firstname'   => new external_value(PARAM_NOTAGS, 'The first name(s) of the user', VALUE_OPTIONAL),
                    'lastname'    => new external_value(PARAM_NOTAGS, 'The family name of the user', VALUE_OPTIONAL),
                    'fullname'    => new external_value(PARAM_NOTAGS, 'The fullname of the user'),
                    'email'       => new external_value(PARAM_TEXT, 'An email address - allow email as root@localhost', VALUE_OPTIONAL),
                    'address'     => new external_value(PARAM_TEXT, 'Postal address', VALUE_OPTIONAL),
                    'phone1'      => new external_value(PARAM_NOTAGS, 'Phone 1', VALUE_OPTIONAL),
                    'phone2'      => new external_value(PARAM_NOTAGS, 'Phone 2', VALUE_OPTIONAL),
                    'department'  => new external_value(PARAM_TEXT, 'department', VALUE_OPTIONAL),
                    'institution' => new external_value(PARAM_TEXT, 'institution', VALUE_OPTIONAL),
                    'idnumber'    => new external_value(PARAM_RAW, 'An arbitrary ID code number perhaps from the institution', VALUE_OPTIONAL),
                    'interests'   => new external_value(PARAM_TEXT, 'user interests (separated by commas)', VALUE_OPTIONAL),
                    'firstaccess' => new external_value(PARAM_INT, 'first access to the site (0 if never)', VALUE_OPTIONAL),
                    'lastaccess'  => new external_value(PARAM_INT, 'last access to the site (0 if never)', VALUE_OPTIONAL),
                    'lastcourseaccess'  => new external_value(PARAM_INT, 'last access to the course (0 if never)', VALUE_OPTIONAL),
                    'description' => new external_value(PARAM_RAW, 'User profile description', VALUE_OPTIONAL),
                    'descriptionformat' => new external_format_value('description', VALUE_OPTIONAL),
                    'city'        => new external_value(PARAM_NOTAGS, 'Home city of the user', VALUE_OPTIONAL),
                    'country'     => new external_value(PARAM_ALPHA, 'Home country code of the user, such as AU or CZ', VALUE_OPTIONAL),
                    'profileimageurlsmall' => new external_value(PARAM_URL, 'User image profile URL - small version', VALUE_OPTIONAL),
                    'profileimageurl' => new external_value(PARAM_URL, 'User image profile URL - big version', VALUE_OPTIONAL),
                    'customfields' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'type'  => new external_value(PARAM_ALPHANUMEXT, 'The type of the custom field - text field, checkbox...'),
                                'value' => new external_value(PARAM_RAW, 'The value of the custom field'),
                                'name' => new external_value(PARAM_RAW, 'The name of the custom field'),
                                'shortname' => new external_value(PARAM_RAW, 'The shortname of the custom field - to be able to build the field class in the code'),
                            ]
                        ), 'User custom fields (also known as user profil fields)', VALUE_OPTIONAL),
                    'groups' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id'  => new external_value(PARAM_INT, 'group id'),
                                'name' => new external_value(PARAM_RAW, 'group name'),
                                'description' => new external_value(PARAM_RAW, 'group description'),
                                'descriptionformat' => new external_format_value('description'),
                            ]
                        ), 'user groups', VALUE_OPTIONAL),
                    'roles' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'roleid'       => new external_value(PARAM_INT, 'role id'),
                                'name'         => new external_value(PARAM_RAW, 'role name'),
                                'shortname'    => new external_value(PARAM_ALPHANUMEXT, 'role shortname'),
                                'sortorder'    => new external_value(PARAM_INT, 'role sortorder')
                            ]
                        ), 'user roles', VALUE_OPTIONAL),
                    'preferences' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'name'  => new external_value(PARAM_RAW, 'The name of the preferences'),
                                'value' => new external_value(PARAM_RAW, 'The value of the custom field'),
                            ]
                    ), 'User preferences', VALUE_OPTIONAL),
                    'enrolledcourses' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id'  => new external_value(PARAM_INT, 'Id of the course'),
                                'fullname' => new external_value(PARAM_RAW, 'Fullname of the course'),
                                'shortname' => new external_value(PARAM_RAW, 'Shortname of the course')
                            ]
                    ), 'Courses where the user is enrolled - limited by which courses the user is able to see', VALUE_OPTIONAL)
                ]
            )
        );
    }

}
