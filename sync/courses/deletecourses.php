<?php
/* 
 * A moodle addon to quickly remove a number of courses by uploading an
 *       unformatted text file containing the shortnames of the courses
 *       each on its own line
 *
 *
 * Date: 4/11/07
 * Date review: 03/05/11
 * Employed By; Appalachian State University
 *
 * $productname = "Bulk Course Delete";
 * $version = "v1.1";
 * $author = "Ashley Gooding & Cole Spicer";
 * &reviewer = "Funck Thibaut";
 *
 */

	require_once('../../../config.php');
	require_once($CFG->dirroot.'/course/lib.php');
	require_once($CFG->libdir.'/adminlib.php');
	require_once($CFG->libdir.'/moodlelib.php');
	require_once($CFG->dirroot.'/enrol/sync/lib.php');
	require_once($CFG->dirroot.'/lib/uploadlib.php');			 

// security
		
	require_capability('moodle/site:doanything', get_context_instance(CONTEXT_SYSTEM));
	
	if (! $site = get_site()) {
        error('Could not find site-level course');
    }

	$strenrolname = get_string('enrolname', 'enrol_sync');
	$strdeletecourses = get_string('coursedeletion', 'enrol_sync');
	$strchoose = get_string('choose');
	
	set_time_limit(300);

	list($usec, $sec) = explode(' ', microtime());
    $time_start = ((float)$usec + (float)$sec);
    
    $navlinks[] = array(
    	'name' => $strenrolname,
    	'url' => null,
    	'type' => 'title'
    );
	
	print_header("$site->shortname: $strdeletecourses", $site->fullname, build_navigation($navlinks));

	print_heading_with_help(get_string('coursedeletion', 'enrol_sync'), 'coursedeletion', 'enrol_sync');

// Page controller

	if(!isset($_POST['ids'])) {
		// If there is a file to upload... do it... else do the rest of the stuff
		$um = new upload_manager('deletefile', false, false, null, false, 0);
		
	    if (isset($um->files['deletefile'])) {
			// All file processing stuff will go here. ID=2...
	  		notify(get_string('parsingfile', 'enrol_sync'), 'notifysuccess');
	
			$um->preprocess_files();
			
			$filename = $um->files['deletefile']['tmp_name'];
		} 

		if ($uselocal = optional_param('uselocal', false, PARAM_BOOL)){
			if(!empty($uselocal)){
				$filename = $CFG->course_filedeletelocation;
				$filename = $CFG->dataroot.'/'.$filename;
			}
		}
		

		if (isset($filename)){

			$i = 0;
			$file = @fopen($filename, 'rb', 0);

			if ($file) {
				while (!feof($file)) {
					$text = fgets($file, 1024);
					
					if (sync_is_empty_line_or_format($text, $i == 0)){
						$i++;
						continue;
					}
					$shortnames[] = trim($text);
				}
				fclose($file);
			}
			
			
			// Ok now we have the file in a proper format... lets parse it for course
			//    shortnames and show the list of courses with id #s followed by a confirm button
			
			// Fill this with a list of comma seperated id numbers to delete courses.
			$deleteids = '';
			$idnums = array();
			
			foreach($shortnames as $shortname) {
				$report = optional_param('report', false, PARAM_BOOL);
	
				if(!($course = get_record('course', 'shortname', $shortname))) {
					// Say we couldnt find that course
					notify(get_string('coursenodeleteadvice', 'enrol_sync', $shortname), 'notifyproblem');
					continue;
				}
				
				if(!empty($idnums[$course->id])) {
					continue;
				} else {
					$idnums[$course->id] = $course->fullname;
				}
				
			}
			
			// Remove last comma
			$deleteids = '';
			if (!empty($idnums)){
				$deleteids = implode(',', array_keys($idnums));
			}
			
			// Show execute time
			list($usec, $sec) = explode(' ', microtime());
	    	$time_end = ((float)$usec + (float)$sec);
	        notify(get_string('totaltime', 'enrol_sync').' '.round(($time_end - $time_start),2).' s', 'notifysuccess');
			
			echo '<center>';
			echo '<hr /><br />'.get_string('predeletewarning', 'enrol_sync').'<br />';
			echo '<p><table border="0">';
			echo '<tr><td width="50" align="left"><b> ID </b></td><td align="left"><b>'.get_string('coursefullname', 'enrol_sync').'</b></td></tr>';
			foreach($idnums as $id => $name) {
				echo '<tr><td align="left">'.$id.'</td><td align="left">'.$name.'</td></tr>';
			}
			echo '</table></p>';
			echo '<br />'.get_string('deletecoursesconfirmquestion', 'enrol_sync').'<br /><br />';
					
			echo '<table border="0"><tr><td>';
			if (!empty($idnums)){
				echo '<form method="post" action="deletecourses.php">';
				echo '<input type="hidden" name="ids" value="'.$deleteids.'">';
				echo '<input type="hidden" name="using" value="'.htmlentities($filename, ENT_QUOTES, 'UTF-8').'">';
				echo '<input type="submit" value="'.get_string('confirm', 'enrol_sync').'">';
				echo '</form>';
			}
			echo '</td><td>';
			echo '<form method="post" action="deletecourses.php">';
			echo '<input type="submit" name="cancel" value="'.get_string('cancel').'">';
			echo '</form></td></tr></table></br>';
			echo '</center>';

			print_footer();
			die;
		} else if (!isset($_POST['cancel'])) {
			notify(get_string('nofile', 'enrol_sync'), 'notifyproblem');
		}
		
	} else {
		// We got passed a list of id's to delete... they pressed the confirm button. Go ahead and delete the courses
		
		$ids = optional_param('ids', '', PARAM_TEXT);
		if (!empty($ids)){
		
			$count = 0;
			
			$idarr = explode(',', $ids);
			foreach($idarr as $id) {
				if(!delete_course($id, false)) {
					enrol_sync_report($CFG->deletereport, get_string('errorcoursedeletion', 'enrol_sync', $id));
				} else {
					$count++;
				}
			}
			
			fix_course_sortorder();
	
			enrol_sync_report($CFG->deletereport, get_string('coursedeleted', 'enrol_sync', $count));
		}
		
		// Show execute time
		list($usec, $sec) = explode(' ', microtime());
    	$time_end = ((float)$usec + (float)$sec);
        enrol_sync_report($CFG->deletereport, get_string('totaltime', 'enrol_sync').' '.round(($time_end - $time_start),2).' s');		

		if (!empty($CFG->sync_filearchive)){
			$filename = required_param('using', PARAM_TEXT);
			$archivename = basename($filename);
			$now = date('Ymd-hi', time());
			$archivename = $CFG->dataroot."/sync/archives/deletion_{$now}_$archivename";
			copy($filename, $archivename);
		}
		
		if (!empty($CFG->sync_filecleanup)){
			@unlink($filename);
		}
 	}

	// Start page... display instructions and upload buttons etc...
	print_box_start();
	print_string('deletefileinstructions', 'enrol_sync');
	print_box_end();
	
	echo '<br/><br/>';
		
	sync_print_remote_tool_portlet('deletefromremote', $CFG->wwwroot.'/enrol/sync/deletecourses.php', 'filedelete', 'confirmdelete');

	sync_print_local_tool_portlet($CFG->course_filedeletelocation, 'deletefile', 'deletecourses.php');
	
	$returntotoolsstr = get_string('returntotools', 'enrol_sync');
	// always return to main tool view.
	echo '<center>';
	echo "<br/>";
	echo '<input type="button" value="'.$returntotoolsstr."\" onclick=\"document.location.href='{$CFG->wwwroot}/enrol/sync/sync.php?sesskey={$USER->sesskey}';\">";
	echo '<br/>';			 
	echo '</center>';
	print_footer();
?>