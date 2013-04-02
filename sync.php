<?php  // $Id: sync.php,v 1.1 2011-05-04 14:22:23 
       // sync.php - allows admin to create or delete courses,users,enrol from csv files
	   // author - Funck Thibaut

    require_once('../../config.php');
    require_once($CFG->libdir.'/adminlib.php');
	require_once($CFG->libdir.'/moodlelib.php');
	require_once($CFG->dirroot.'/course/lib.php');

	require_login();

	// admin_externalpage_setup('sync');
	// create sync file repo if needed
	if (!is_dir($CFG->dataroot.'/sync')){
		mkdir ($CFG->dataroot.'/sync', 0777);
	}

	// create sync archive repo if needed
	if (!is_dir($CFG->dataroot.'/sync/archives')){
		mkdir ($CFG->dataroot.'/sync/archives', 0777);
	}

	// create sync reports repo if needed
	if (!is_dir($CFG->dataroot.'/sync/reports')){
		mkdir ($CFG->dataroot.'/sync/reports', 0777);
	}

	if (!is_siteadmin()) {
        print_error('erroradminrequired', 'enrol_sync');
    }
	if (! $site = get_site()) {
        print_error('errornosite', 'enrol_sync');
    }
	if (!$adminuser = get_admin()) {
        print_error('errornoadmin', 'enrol_sync');
    }

	require_once($CFG->dirroot.'/enrol/sync/courses/courses.class.php');
    $coursesmanager = new courses_plugin_manager;
	require_once($CFG->dirroot.'/enrol/sync/users/users.class.php');
	$usersmanager = new users_plugin_manager;
	require_once($CFG->dirroot.'/enrol/sync/userpictures/userpictures.class.php');
	$userpicturesmanager = new userpictures_plugin_manager;
	require_once($CFG->dirroot.'/enrol/sync/enrol/enrols.class.php');
    $enrolmanager = new enrol_plugin_manager;
	
	require_once("$CFG->dirroot/enrol/sync/enrol.php");
	$mainmanager = new enrolment_plugin_sync;
	
	if (!isset($CFG->sync_encoding)) set_config('sync_encoding', 'UTF-8');
	if (!isset($CFG->sync_csvseparator)) set_config('sync_csvseparator', ';');
	if (!isset($CFG->userpictures_userfield)) set_config('userpictures_userfield', 1);
	if (!isset($CFG->userpictures_fileprefix)) set_config('userpictures_fileprefix', 'userpictures_');
	if (!isset($CFG->userpictures_forcedeletion)) set_config('userpictures_forcedeletion', 1);
	if (!isset($CFG->userpictures_overwrite)) set_config('userpictures_overwrite', 1);

/// If data submitted, then process and store.

    if ($frm = data_submitted()) {
        if (!confirm_sesskey()) {
            print_error('confirmsesskeybad', 'error');
        }
        if ($coursesmanager->process_config($frm) && 
        		$usersmanager->process_config($frm) && 
        			$userpicturesmanager->process_config($frm) && 
        				$enrolmanager->process_config($frm) && 
        					$mainmanager->process_config($frm)) {
            redirect($CFG->wwwroot.'/enrol/sync/sync.php?sesskey='.$USER->sesskey, get_string('changessaved'), 1);
        }
    } else {
        $frm = $CFG;
    }
	
/// Print current courses type description

    $url = $CFG->wwwroot."/enrol/sync/sync.php";
    // $PAGE->set_title(format_string($SITE->fullname));
    $PAGE->set_url($url);
    $PAGE->set_context(null);
    $PAGE->set_title($SITE->fullname);
    $PAGE->set_heading($SITE->fullname);
	$PAGE->set_pagelayout('admin');
	echo $OUTPUT->header();	

	echo "<form method=\"post\" action=\"{$CFG->wwwroot}/enrol/sync/sync.php\">";
    echo '<div>';
    echo "<input type=\"hidden\" name=\"sesskey\" value=\"".$USER->sesskey."\" />";
	
	echo $OUTPUT->heading(get_string('title', 'enrol_sync'));

    echo $OUTPUT->box_start('center', '100%', '', 5, 'informationbox');
    print_string('boxdescription', 'enrol_sync');	
    echo $OUTPUT->box_end();

    echo '<hr />';

	echo $OUTPUT->heading(get_string('filemanager2', 'enrol_sync'));

	echo "<fieldset><legend><strong>".get_string('filemanager', 'enrol_sync')."</strong></legend>";
	echo "<center><a href=\"$CFG->wwwroot/enrol/sync/file.php\"> ". get_string('filemanager2', 'enrol_sync') ." </a><br/><br/></center></fieldset>";
	echo '<br/>';
	echo '<br/>';

	echo $OUTPUT->heading_with_help(get_string('coursesync', 'enrol_sync'), 'coursesync', 'enrol_sync');

	$coursesmanager->config_form($frm);

	$manualhandlingstr = get_string('manualhandling', 'enrol_sync');
	$utilitiesstr = get_string('utilities', 'enrol_sync');

    echo "<p class=\"centerpara\"><input type=\"submit\" value=\" ". get_string('button', 'enrol_sync')."\" /></p>\n";
	echo '<fieldset>';
	echo "<legend><strong>$utilitiesstr</strong></legend>";
	echo '<center>';
	echo "<a href=\"$CFG->wwwroot/enrol/sync/courses/deletecourses_creator.php\"> ". get_string('makedeletefile', 'enrol_sync') .' </a><br/>';
	echo "<a href=\"$CFG->wwwroot/enrol/sync/courses/checkcourses.php\">". get_string('testcourseexist', 'enrol_sync') .'</a><br/>';	
	echo '<br/>';
	echo '</center></fieldset>';
	echo '<fieldset>';
	echo "<legend><strong>$manualhandlingstr</strong></legend>";
	echo '<center>';
	echo "<a href=\"$CFG->wwwroot/enrol/sync/courses/resetcourses.php\">". get_string('reinitialisation', 'enrol_sync') .'</a><br/>';		
	echo "<a href=\"$CFG->wwwroot/enrol/sync/courses/synccourses.php\">". get_string('manualuploadrun', 'enrol_sync') .'</a><br/>';
	echo "<a href=\"$CFG->wwwroot/enrol/sync/courses/deletecourses.php\"> ". get_string('manualdeleterun', 'enrol_sync') . '</a><br/><br/>';
	echo "<a href=\"$CFG->wwwroot/enrol/sync/courses/clearemptycategories.php\"> ". get_string('manualcleancategories', 'enrol_sync') . '</a><br/><br/>';
	echo "<a href=\"$CFG->wwwroot/enrol/sync/courses/execcron.php\"> ". get_string('executecoursecronmanually', 'enrol_sync') .'</a><br/>';
	echo '<br/>';
	echo '</center></fieldset>';
	//$coursesmanager->showFileDelete();
	//$coursesmanager->showFileUpdate();

	echo '<br />';
	echo '<br />';	
	echo $OUTPUT->heading_with_help(get_string('usersync', 'enrol_sync'), 'usersync', 'enrol_sync');
	$usersmanager->config_form($frm);
	$manualusermgtstr = get_string('usermgtmanual', 'enrol_sync');
	//$usersmanager->cron();
	//$filechecker->transform_enrol_file($CFG->enrol_filelocation);
    echo "<p class=\"centerpara\"><input type=\"submit\" value=\" ". get_string('button', 'enrol_sync')."\" /></p>\n";
	echo "<fieldset><legend><strong>$manualusermgtstr</strong></legend>";	
	echo "<center><br/> <a href=\"$CFG->wwwroot/enrol/sync/users/execcron.php\">". get_string('manualuserrun', 'enrol_sync') ." </a><br/></center>";
	echo "<!-- center><br/> <a href=\"$CFG->wwwroot/admin/uploaduser.php\">". get_string('manualuserrun2', 'enrol_sync') ." </a><br/></center -->";	
	echo '<br />';
	echo '</fieldset>';

	echo '<br />';
	echo '<br />';

	echo $OUTPUT->heading_with_help(get_string('userpicturesync', 'enrol_sync'), 'userpicturesync', 'enrol_sync');
	$userpicturesmanager->config_form($frm);
	$manualuserpicturesmgtstr = get_string('userpicturesmgtmanual', 'enrol_sync');
    echo "<p class=\"centerpara\"><input type=\"submit\" value=\" ". get_string('button', 'enrol_sync')."\" /></p>\n";
	echo "<fieldset><legend><strong>$manualuserpicturesmgtstr</strong></legend>";	
	echo "<center><br/> <a href=\"$CFG->wwwroot/enrol/sync/userpictures/execcron.php\">". get_string('manualuserpicturesrun', 'enrol_sync') ." </a><br/></center>";	
	echo '<br />';
	echo '</fieldset>';

	echo '<br />';
	echo '<br />';

	echo $OUTPUT->heading_with_help(get_string('enrolsync', 'enrol_sync'), 'enrolsync', 'enrol_sync');
	$enrolmanager->config_form($frm);
	$manualenrolmgtstr = get_string('enrolmgtmanual', 'enrol_sync');
	//$enrolmanager->cron();
	echo "<fieldset><legend><strong>$manualenrolmgtstr</strong></legend>";		
	echo "<center><br /> <a href=\"$CFG->wwwroot/enrol/sync/enrol/execcron.php\">". get_string('manualenrolrun', 'enrol_sync') ." </a><br/></center>";
	echo '<br />';
	echo '</fieldset>';
	
	echo '<br />';
	echo '<br />';

	echo $OUTPUT->heading_with_help(get_string('optionheader', 'enrol_sync'), 'syncconfig', 'enrol_sync');
	$mainmanager->config_form($frm);

	echo '<br />';
	echo '<br />';

    echo "<p class=\"centerpara\"><input type=\"submit\" value=\" ". get_string('button', 'enrol_sync')."\" /></p>\n";
    echo '</div/>';
    echo '</form>';

 ///   print_footer();
	echo $OUTPUT->footer();

?>