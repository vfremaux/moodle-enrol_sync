<?PHP  	   // author - Funck Thibaut

    require_once("../../../config.php");
    require_once($CFG->libdir.'/adminlib.php');
	require_once($CFG->dirroot.'/course/lib.php');

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

	set_time_limit(1800);
	raise_memory_limit('512M');		

	$url = $CFG->wwwroot.'/enrol/sync/enrol/execcron.php';
	$PAGE->set_url($url);
	$PAGE->set_context(null);
	$PAGE->navigation->add(get_string('synchronization', 'enrol_sync'), $CFG->wwwroot.'/enrol/sync/sync.php');
	$PAGE->navigation->add(get_string('enrolmgtmanual', 'enrol_sync'));
	$PAGE->set_title("$site->shortname");
	$PAGE->set_heading($site->fullname);
	echo $OUTPUT->header();
	echo $OUTPUT->heading_with_help(get_string('enrolsync', 'enrol_sync'), 'enrolsync', 'enrol_sync');

	require_once($CFG->dirroot.'/enrol/sync/enrol/enrols.class.php');
    $enrolmanager = new enrol_plugin_manager;

	$enrolmanager->process_config($CFG);

	echo $OUTPUT->heading(get_string('enrolmanualsync', 'enrol_sync'), 3);

	$cronrunmsg = get_string('cronrunmsg', 'enrol_sync', $CFG->enrol_filelocation);
	echo "<center>$cronrunmsg</center>";

	echo $OUTPUT->heading(get_string('processresult', 'enrol_sync'), 3);

	echo "<pre>";
	$enrolmanager->cron();
	echo "</pre>";

	sync_print_return_button();

	echo $OUTPUT->footer();

///    exit;
?>
