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
        print_error('errornosite', 'enrol_sync');
    }
	if (!$adminuser = get_admin()) {
        print_error('errornoadmin', 'enrol_sync');
    }
	
	require_once("$CFG->dirroot/enrol/sync/enrol/enrols.class.php");
    $enrolmanager = new enrol_plugin_manager;
	
	$navlinks[] = array('name' => get_string('synchronization', 'enrol_sync'),
			  'link' => $CFG->wwwroot.'/enrol/sync/sync.php',
			  'type' => 'url');
	$navlinks[] = array('name' => get_string('enrolmgtmanual', 'enrol_sync'),
			  'link' => null,
			  'type' => 'title');

	print_header("$site->shortname", $site->fullname, 
                 build_navigation($navlinks));
	
	print_heading_with_help(get_string('enrolmgtmanual', 'enrol_sync'), 'uploadcourse');

	$enrolmanager->process_config($CFG);
	echo "<pre>";
	$enrolmanager->cron();
	echo "</pre>";
	$address = $CFG->enrol_filelocation;

	$enrolmgtmanualstr = get_string('enrolmgtmanual', 'enrol_sync');
	$backtoprevious = get_string('backtoprevious', 'enrol_sync');
	$cronrunmsg = get_string('cronrunmsg', 'enrol_sync', $address);

	echo "<br/><fieldset><legend><strong>$enrolmgtmanualstr</strong></legend>";
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
