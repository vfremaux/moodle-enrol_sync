<?php
/**
* @author Valery Fremaux
* @package enrol
* @subpackage sync
*
**/

require_once $CFG->dirroot.'/enrol/sync/lib.php';
require_once $CFG->dirroot.'/enrol/sync/userpictures/lib.php';
require_once($CFG->libdir.'/gdlib.php');

define ('PIX_FILE_UPDATED', 0);
define ('PIX_FILE_ERROR',   1);
define ('PIX_FILE_SKIPPED', 2);    

class userpictures_plugin_manager {

    var $log;    

	/// Override the base config_form() function
	function config_form($frm) {
	    global $CFG;
	
	    $vars = array('userpictures_fileprefix', 'userpictures_userfield', 'userpictures_overwrite');

	    foreach ($vars as $var) {
	        if (!isset($frm->$var)) {
	            $frm->$var = '';
	        } 	
	    }

	    include ($CFG->dirroot.'/enrol/sync/userpictures/config.html');    
	}

	/// Override the base process_config() function
	function process_config($config) {
		
	    if (!isset($config->userpictures_fileprefix)) {
	        $config->userpictures_fileprefix = 'userpictures_';
	    }
	    set_config('userpictures_fileprefix', $config->userpictures_fileprefix);

	    if (!isset($config->userpictures_userfield)) {
	        $config->userpictures_userfield = 1;
	    }
	    set_config('userpictures_userfield', $config->userpictures_userfield);

	    if (!isset($config->userpictures_overwrite)) {
	        $config->userpictures_overwrite = 1;
	    }
	    set_config('userpictures_overwrite', $config->userpictures_overwrite);

	    if (!isset($config->userpictures_forcedeletion)) {
	        $config->userpictures_forcedeletion = 1;
	    }
	    set_config('userpictures_forcedeletion', $config->userpictures_forcedeletion);

	    return true;	
	}	

    function cron() {
        global $CFG;
        global $USER;
        
		$filestoprocess = glob($CFG->dataroot.'/sync/'.$CFG->userpictures_fileprefix.'*.zip');		
        if (empty($filestoprocess)) {
			enrol_sync_report($CFG->userpictureslog, get_string('nofiletoprocess', 'enrol_sync'));		
			return;
        }
        
        $userfields = $this->get_userfields();
		
		$userfield = $CFG->userpictures_userfield;
		$overwritepicture = $CFG->userpictures_overwrite;

	    if (!array_key_exists($userfield, $userfields)) {
	        enrol_sync_report($CFG->userpictureslog, get_string('uploadpicture_baduserfield','admin'));
	        return;
	    } 

		foreach($filestoprocess as $f){

            enrol_sync_report($CFG->userpictureslog, get_string('processingfile','enrol_sync', $f));
			// user pictures processing
			
	        // Large files are likely to take their time and memory. Let PHP know
	        // that we'll take longer, and that the process should be recycled soon
	        // to free up memory.
	        @set_time_limit(0);
	        @raise_memory_limit("256M");
	        if (function_exists('apache_child_terminate')) {
	            @apache_child_terminate();
	        }
	        
	        // Create a unique temporary directory, to process the zip file
	        // contents.
	        $zipdir = sync_my_mktempdir($CFG->dataroot.'/temp/', 'usrpic_'.time());
	        
            if(!unzip_file($f, $zipdir, false)) {
                enrol_sync_report($CFG->userpictureslog, get_string('erroruploadpicturescannotunzip','enrol_sync', $f));
                @remove_dir($zipdir);
            } else {
                // We don't need the zip file any longer, so delete it to make
                // it easier to process the rest of the files inside the directory.
                
                $results = array ('errors' => 0,'updated' => 0);

                sync_process_directory($zipdir, $userfields[$userfield], $overwritepicture, $results);
            
                // Finally remove the temporary directory with all the user images and print some stats.
                remove_dir($zipdir);
                enrol_sync_report($CFG->userpictureslog, get_string('usersupdated', 'admin') . ": " . $results['updated']);
                enrol_sync_report($CFG->userpictureslog, get_string('errors', 'admin') . ": " . $results['errors']);
            }
			
			// files cleanup
		
			if (!empty($CFG->sync_filearchive)){
				$archivename = basename($f);
				$now = date('Ymd-hi', time());
				$archivename = $CFG->dataroot."/sync/archives/{$now}_userfiles_$archivename";
				copy($f, $archivename);
			}
			
			if (!empty($CFG->sync_filecleanup) || !empty($CFG->userfiles_forcedeletion)){
				@unlink($f);
			}
		}
		
		enrol_sync_report($CFG->enrollog, "\n".get_string('endofreport', 'enrol_sync'));
		
		return true;
    }
    
    function get_userfields(){

		$ufs = array (
		    0 => 'username',
		    1 => 'idnumber',
		    2 => 'id' );
		    
		return $ufs;
    }
}

?>