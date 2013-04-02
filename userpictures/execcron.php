<?php  	   // author - Funck Thibaut

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

	$url = $CFG->wwwroot.'/enrol/sync/userpictures/execcron.php';
	$PAGE->set_url($url);
	$PAGE->set_context(null);
	$PAGE->navigation->add(get_string('synchronization', 'enrol_sync'), $CFG->wwwroot.'/enrol/sync/sync.php');
	$PAGE->navigation->add(get_string('userpicturesmgtmanual', 'enrol_sync'));
	$PAGE->set_title("$site->shortname");
	$PAGE->set_heading($site->fullname);
	echo $OUTPUT->header();
	echo $OUTPUT->heading_with_help(get_string('userpicturesync', 'enrol_sync'), 'userpicturesync', 'enrol_sync');

	require_once("$CFG->dirroot/enrol/sync/userpictures/userpictures.class.php");
    $picturemanager = new userpictures_plugin_manager;

	$picturemanager->process_config($CFG);

	echo $OUTPUT->heading(get_string('userpicturesmanualsync', 'enrol_sync'), 3);

	$filestoprocess = glob($CFG->dataroot.'/sync/'.$CFG->userpictures_fileprefix.'*.zip');		
    if (!empty($filestoprocess)) {
    	print_string('foundfilestoprocess', 'enrol_sync', count($filestoprocess));
    	echo '<br/>';
    	foreach($filestoprocess as $upf){
    		echo $upf.'</br>';
    	}
    }

	echo $OUTPUT->heading(get_string('processresult', 'enrol_sync'), 3);

	echo "<pre>";
	$picturemanager->cron();
	echo "</pre>";

	sync_print_return_button();

	echo $OUTPUT->footer();

///    exit;
?>
