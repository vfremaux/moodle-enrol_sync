<?php  	   // author - Funck Thibaut

    require_once('../../../config.php');
    require_once($CFG->libdir.'/adminlib.php');
	require_once($CFG->dirroot."/course/lib.php");

	set_time_limit(1800);
	raise_memory_limit('512M');	
	
	require_login();

	if (!is_siteadmin()) {
        print_error('erroradminrequired', 'enrol_sync');
    }
	if (! $site = get_site()) {
        print_error('errornosite', 'enrol_sync');
    }
	if (!$adminuser = get_admin()) {
        print_error('errornoadmin', 'enrol_sync');
    }

	require_once("$CFG->dirroot/enrol/sync/courses/courses.class.php");

	$url = $CFG->wwwroot.'/enrol/sync/courses/execcron.php';
	$PAGE->navigation->add(get_string('synchronization', 'enrol_sync'), $CFG->wwwroot.'/enrol/sync/sync.php');
	$PAGE->navigation->add(get_string('coursesync', 'enrol_sync'));
	$PAGE->set_url($url);
	$PAGE->set_context(null);
	$PAGE->set_title("$site->shortname");
	$PAGE->set_heading($site->fullname);
	echo $OUTPUT->header();
	echo $OUTPUT->heading_with_help(get_string('coursesync', 'enrol_sync'), 'coursesync', 'enrol_sync');

	// execron do everything a cron will do
    $coursesmanager = new courses_plugin_manager(null, SYNC_COURSE_CHECK | SYNC_COURSE_DELETE | SYNC_COURSE_CREATE);

	$coursesmanager->process_config($CFG);

	echo $OUTPUT->heading(get_string('coursemanualsync', 'enrol_sync'), 3);

	$cronrunmsg = get_string('cronrunmsg', 'enrol_sync', $CFG->course_fileexistlocation);
	echo "<center>$cronrunmsg</center>";

	$cronrunmsg = get_string('cronrunmsg', 'enrol_sync', $CFG->course_filedeletelocation);
	echo "<center>$cronrunmsg</center>";

	$cronrunmsg = get_string('cronrunmsg', 'enrol_sync', $CFG->course_fileuploadlocation);
	echo "<center>$cronrunmsg</center>";

	echo $OUTPUT->heading(get_string('processresult', 'enrol_sync'), 3);

	echo '<pre>';
	$coursesmanager->cron();
	echo '</pre>';

	sync_print_return_button();

	echo $OUTPUT->footer();

///    exit;
?>
