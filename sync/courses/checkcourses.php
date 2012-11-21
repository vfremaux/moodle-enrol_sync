<?php	   // author - Funck Thibaut

	require_once('../../../config.php');
	require_once($CFG->dirroot.'/course/lib.php');
	require_once($CFG->libdir.'/adminlib.php');
	require_once($CFG->libdir.'/moodlelib.php');
	require_once($CFG->dirroot.'/enrol/sync/lib.php');
	require_once($CFG->dirroot.'/enrol/sync/courses/courses.class.php');
	
	require_capability('moodle/site:doanything', get_context_instance(CONTEXT_SYSTEM));
	
	if (! $site = get_site()) {
        print_error('errornosite', 'enrol_sync');
    }
	if (!$adminuser = get_admin()) {
        print_error('errornoadmin', 'enrol_sync');
    }

	$navlinks[] = array('name' => get_string('synchronization', 'enrol_sync'),
			  'link' => $CFG->wwwroot.'/enrol/sync/sync.php',
			  'type' => 'url');
	$navlinks[] = array('name' => get_string('coursecheck', 'enrol_sync'),
			  'url' => null,
			  'type' => 'title');

	print_header("$site->shortname", $site->fullname, build_navigation($navlinks));
	
	print_heading(get_string('checkingcourse', 'enrol_sync'));
	sync_print_remote_tool_portlet('importfile', $CFG->wwwroot.'/enrol/sync/courses/checkcourses.php', 'checkcourse', 'upload');	
	sync_print_local_tool_portlet($CFG->file_course_exist, 'commandfile', 'checkcourses.php');
	
	require_once($CFG->dirroot.'/lib/uploadlib.php');			 

	// If there is a file to upload... do it... else do the rest of the stuff
	$um = new upload_manager('checkcourse', false, false, null, false, 0);

    if ($um->preprocess_files() || isset($_POST['uselocal'])) {
		// All file processing stuff will go here. ID=2...
		
        if (isset($um->files['checkcourse'])) {
  			notify(get_string('parsingfile', 'enrol_sync'), 'notifysuccess');
			$filename = $um->files['checkcourse']['tmp_name'];
		}

		$uselocal = optional_param('uselocal', false, PARAM_BOOL);
		if(!empty($uselocal)){
			$filename = $CFG->course_fileexistlocation;
			$filename = $CFG->dataroot.'/'.$filename;
		}
		
		// execron do everything a cron will do
		if (isset($filename) && file_exists($filename)){
			$filestouse->check = $filename;
		    $coursesmanager = new courses_plugin_manager($filestouse, SYNC_COURSE_CHECK);

			echo '<pre>';
			$coursesmanager->cron();
			echo '</pre>';
			
			sync_save_check_report();
		}
	}

	/**
	* writes an operation report file telling about all course tested
	*
	*/		
	function sync_save_check_report(){
		global $CFG;

		$t = time();
		$today = date("Y-m-d_H-i-s",$t);
		
		$filename = $CFG->dataroot."/sync/reports/UC_$today.txt";
		
		if($FILE = @fopen($filename,'w')){		
			fputs($FILE, $CFG->courselog);
		}
		fclose($FILE);		
	}
		
	if ($del = optional_param('del', 0, PARAM_BOOL)){
		$filename = optional_param('delname', '', PARAM_TEXT);
		if($filename){
			@unlink($filename);
		}
	}	

	if ($purge = optional_param('purge', false, PARAM_TEXT)){
		$reports = glob($CFG->dataroot.'/sync/reports/UC_*');
		if (!empty($reports)){
			foreach($reports as $report){
				@unlink($report);
			}
		}
	}	

	echo '<br/><br/><fieldset><legend><strong>'.get_string('displayoldreport', 'enrol_sync').'</strong></legend>';
	$entries = glob($CFG->dataroot."/sync/reports/UC_*");
	$filecabinetstr = get_string('filecabinet', 'enrol_sync');
	$filenameformatstr = get_string('filenameformatuc', 'enrol_sync');
	echo "<br/><strong>$filecabinetstr</strong>: $CFG->dataroot/sync/reports<br/>";
	echo "$filenameformatstr<br/><br/>";
	echo '<ul>';
	foreach($entries as $entry){
		echo '<li> '.basename($entry).'</li>';
	}
	echo '</ul>';
	echo '<br/>';
	
	$loadstr = get_string('load', 'enrol_sync');
	$purgestr = get_string('purge', 'enrol_sync');
	echo '<center>';
	echo '<form method="post" action="checkcourses.php" style="display:inline">';
	echo '<input type="hidden" name="sesskey" value="'.$USER->sesskey.'">';
	print_string('enterfilename', 'enrol_sync');
	echo '<input type="text" name="filename" size="30"> <input type="submit" value="'.$loadstr.'">';
	echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo '</form>';	

	echo '<form method="post" action="checkcourses.php" style="display:inline">';
	echo '<input type="hidden" name="sesskey" value="'.$USER->sesskey.'">';
	echo '<input type="submit" name="purge" value="'.$purgestr.'">';
	echo '</form>';	
	echo '</center>';
	 
	echo '<br/>';

	$name = optional_param('filename', '', PARAM_TEXT);
	if(!empty($name)){
		$filename = "$CFG->dataroot/sync/reports/$name";
		
		if ($file = file($filename)){
			echo '<pre>';
			echo implode("\n", $file);
			echo '</pre>';
		}

		echo '<center>';
		echo '<form method="post" action="checkcourses.php">';
		echo '<input type="hidden" name="sesskey" value="'.$USER->sesskey.'">';
		echo '<input type="hidden" name="delname" value="'.$filename.'">';
		print_string('deletethisreport', 'enrol_sync');
		echo '<input type=radio name="del" value="1" /> '.get_string('yes').' <input type=radio name="del" value="0" checked/> '.get_string('no').'<br/>';
		echo '<input type="submit" value="'.get_string('delete').'">';
		echo '</form>';			
		echo '</center>';
	}
		
	echo '</fieldset>';

	// always return to main tool view.
	sync_print_return_button();

	print_footer();
?>