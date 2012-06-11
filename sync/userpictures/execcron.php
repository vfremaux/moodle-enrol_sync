<?PHP  	   // author - Funck Thibaut
    require_once("../../../config.php");
    require_once($CFG->libdir.'/adminlib.php');
	require_once($CFG->libdir.'/moodlelib.php');
	require_once($CFG->dirroot.'/course/lib.php');
	
	set_time_limit(1800);
	raise_memory_limit('512M');		
	
	require_capability('moodle/site:doanything', get_context_instance(CONTEXT_SYSTEM));
	
	admin_externalpage_setup('sync');
	
	if (! $site = get_site()) {
        error('Could not find site-level course');
    }
	
	require_once("$CFG->dirroot/enrol/sync/userpictures/userpictures.php");
    $picturemanager = new userpictures_plugin_manager;
	
	$navlinks[] = array('name' => get_string('synchronization', 'enrol_sync'),
			  'link' => $CFG->wwwroot.'/enrol/sync/sync.php',
			  'type' => 'url');
	$navlinks[] = array('name' => get_string('userpicturesmgtmanual', 'enrol_sync'),
			  'url' => null,
			  'type' => 'title');

	print_header("$site->shortname", $site->fullname, build_navigation($navlinks));
	
	print_heading_with_help(get_string('userpicturesmgtmanual', 'enrol_sync'), 'uploaduserpictures');

	$picturemanager->process_config($CFG);
	echo "<pre>";
	$picturemanager->cron();
	echo "</pre>";

	$userpicturesmgtmanualstr = get_string('userpicturesmgtmanual', 'enrol_sync');
	$backtoprevious = get_string('backtoprevious', 'enrol_sync');
	$cronrunmsg = get_string('cronrunmsg', 'enrol_sync', '');

	echo "<br/><fieldset><legend><strong>$userpicturesmgtmanualstr</strong></legend>";
	echo "<center>$cronrunmsg</center>";
	echo '</fieldset>';
	echo '<center>';
	echo '<hr/>';
	echo '<br/>';
	echo '<input type="button" value="'.get_string('returntotools', 'enrol_sync')."\" onclick=\"document.location.href='{$CFG->wwwroot}/enrol/sync/sync.php?sesskey={$USER->sesskey}';\">";
	echo '<br/>';			 
	echo '</center>';

	print_footer();

///    exit;
?>
