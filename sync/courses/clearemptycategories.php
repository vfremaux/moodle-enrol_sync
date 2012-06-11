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

// security
		
	require_capability('moodle/site:doanything', get_context_instance(CONTEXT_SYSTEM));
	
	if (! $site = get_site()) {
        error('Could not find site-level course');
    }

	$strcleancatname = get_string('cleancategories', 'enrol_sync');
	
	set_time_limit(300);

	list($usec, $sec) = explode(' ', microtime());
    $time_start = ((float)$usec + (float)$sec);
    
    $navlinks[] = array(
    	'name' => $strcleancatname,
    	'url' => null,
    	'type' => 'title'
    );
	
	print_header("$site->shortname: $strcleancatname", $site->fullname, build_navigation($navlinks));
	print_heading_with_help(get_string('cleancategories', 'enrol_sync'), 'coursedeletion', 'enrol_sync');

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
			notify(get_string('nothingtodelete', 'enrol_sync'), 'notifyproblem');
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
				$deletedcat = get_record('course_categories', 'id', $id);
				if (delete_records('course_categories', 'id', $id)){
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

	$returntotoolsstr = get_string('returntotools', 'enrol_sync');
	// always return to main tool view.
	echo '<center>';
	echo "<br/>";
	echo '<input type="button" value="'.$returntotoolsstr."\" onclick=\"document.location.href='{$CFG->wwwroot}/enrol/sync/sync.php?sesskey={$USER->sesskey}';\">";
	echo '<br/>';			 
	echo '</center>';
	print_footer();
?>