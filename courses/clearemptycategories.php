<?php
/* 
 * A moodle addon to quickly remove all empty categories and cleanup category tree
 *
 * Date: 23/12/2012
 * Date review: 23/12/2012
 *
 * $productname = "";
 * $version = "v1.1";
 * $author = "Valery Fremaux";
 *
 */

	require_once('../../../config.php');
	require_once($CFG->dirroot.'/enrol/sync/lib.php');
	require_once($CFG->dirroot.'/enrol/sync/courses/lib.php');

	require_login();

// security
	if (!is_siteadmin()) {
        print_error('erroradminrequired', 'enrol_sync');
    }
	if (! $site = get_site()) {
        print_error('errornosite', 'enrol_sync');
    }
	if (!$adminuser = get_admin()) {
        print_error('errornoadmin', 'enrol_sync');
    }

	$cleancatnamestr = get_string('cleancategories', 'enrol_sync');
	
	set_time_limit(300);

	list($usec, $sec) = explode(' ', microtime());
    $time_start = ((float)$usec + (float)$sec);
    $url = $CFG->wwwroot.'/enrol/sync/courses/clearemptycategories.php';
	$PAGE->set_context(null);
	$PAGE->set_url($url);
	$PAGE->navigation->add($cleancatnamestr);
	$PAGE->set_title("$site->shortname: $cleancatnamestr");
	$PAGE->set_heading($site->fullname);
	echo $OUTPUT->header();
	echo $OUTPUT->heading_with_help(get_string('cleancategories', 'enrol_sync'), 'cleancategories', 'enrol_sync');

// Page controller

	if(!isset($_POST['ids'])) {

		echo '<center>';
		echo '<table width="70%">';
		$path = '';
		sync_scan_empty_categories(0, $catids, $path);
		echo '</table>';

		if (!empty($catids)){
			$deleteids = implode(',', $catids);

			echo '<form method="post" action="clearemptycategories.php">';
			echo '<input type="hidden" name="ids" value="'.$deleteids.'">';
			echo '<input type="submit" value="'.get_string('confirm', 'enrol_sync').'">';
			echo '</form>';
		} else if (!isset($_POST['cancel'])) {
			echo $OUTPUT->notification(get_string('nothingtodelete', 'enrol_sync'), 'notifyproblem');
		}
		echo '</center>';
	} else {
		// We got passed a list of id's to delete... they pressed the confirm button. Go ahead and delete the courses
		
		$ids = optional_param('ids', '', PARAM_TEXT);
		if (!empty($ids)){
		
			$count = 0;
			
			$idarr = explode(',', $ids);
			echo '<pre>';
			foreach($idarr as $id) {
				$deletedcat = $DB->get_record('course_categories', array('id' => $id));
				if ($DB->delete_records('course_categories', array('id' => $id))){
					if(delete_context(CONTEXT_COURSECAT, $id)) {
						enrol_sync_report($CFG->deletereport, get_string('categoryremoved', 'enrol_sync', $deletedcat->name));
						$count++;
					} else {
						enrol_sync_report($CFG->deletereport, get_string('errorcategorycontextdeletion', 'enrol_sync', $id));
					}
				} else {
					enrol_sync_report($CFG->deletereport, get_string('errorcategorydeletion', 'enrol_sync', $id));
				}
			}
			
			enrol_sync_report($CFG->deletereport, get_string('ncategoriesdeleted', 'enrol_sync', $count));
			echo '</pre>';
		}
		
		// Show execute time
		list($usec, $sec) = explode(' ', microtime());
    	$time_end = ((float)$usec + (float)$sec);
        enrol_sync_report($CFG->deletereport, get_string('totaltime', 'enrol_sync').' '.round(($time_end - $time_start),2).' s');				
 	}

	// always return to main tool view.
	sync_print_return_button();

	echo $OUTPUT->footer();
?>