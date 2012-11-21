<?php
	   // author - Funck Thibaut

    require_once("../../../config.php");
    require_once($CFG->libdir.'/adminlib.php');
	require_once($CFG->libdir.'/moodlelib.php');
	require_once($CFG->dirroot.'/course/lib.php');

	set_time_limit(1800);
	raise_memory_limit('512M');	
	
	require_login();
	
	admin_externalpage_setup('sync');
	
	if (!isadmin()) {
        error('You must be an administrator to edit courses in this way.');
    }
	if (! $site = get_site()) {
        print_error('errornosite', 'enrol_sync');
    }
	if (!$adminuser = get_admin()) {
        print_error('errornoadmin', 'enrol_sync');
    }
	
	require_once("$CFG->dirroot/enrol/sync/users/users.class.php");
	$usersmanager = new users_plugin_manager;

	$navlinks[] = array('name' => get_string('synchronization', 'enrol_sync'),
			  'link' => $CFG->wwwroot.'/enrol/sync/sync.php',
			  'type' => 'url');
	$navlinks[] = array('name' => get_string('usermgtmanual', 'enrol_sync'),
			  'link' => null,
			  'type' => 'title');
	
	print_header("$site->shortname", $site->fullname, build_navigation($navlinks));
	
	print_heading_with_help(get_string('usermgtmanual', 'enrol_sync'), 'uploadcourse', 'enrol_sync');

	$usersmanager->process_config($CFG);
	echo '<pre>';
	$usersmanager->cron();
	echo '</pre>';
	$address = $CFG->users_filelocation;

	$usermgtmanual = get_string('usermgtmanual', 'enrol_sync');
	$cronrunmsg = get_string('cronrunmsg', 'enrol_sync', $address);

	echo "<br/><fieldset><legend><strong>$usermgtmanual</strong></legend>";
	echo "<center>$cronrunmsg</center>";
	echo '</fieldset>';

	// always return to main tool view.
	sync_print_return_button();

	print_footer();
///    exit;
?>
