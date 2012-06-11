<?php
// The following flags are set in the configuration
// $CFG->course_filedeletelocation:       where is the file which delete courses we are looking for?
// $CFG->course_fileuploadlocation:       where is the file which upload courses we are looking for?
// author - Funck Thibaut !

include_once $CFG->dirroot.'/backup/backuplib.php';
include_once $CFG->dirroot.'/backup/lib.php';
include_once $CFG->dirroot.'/backup/restorelib.php';
include_once $CFG->dirroot.'/enrol/sync/lib.php';

class courses_plugin_manager {

    var $log;
    var $controlfiles;
    var $execute;
    
    function courses_plugin_manager($controlfiles = null, $execute = SYNC_COURSE_CREATE_DELETE){
    	$this->controlfiles = $controlfiles;
    	$this->execute = $execute;
    }

	/// Override the base config_form() function
	function config_form($frm) {
	    global $CFG;
	
	    $vars = array('course_filedeletelocation', 'course_fileuploadlocation', 'reset_course_file', 'file_course_exist');
	    foreach ($vars as $var) {
	        if (!isset($frm->$var)) {
	            $frm->$var = '';
	        } 
	    }
	
	    $roles = get_records('role', '', '', '', 'id, name, shortname');
	    $ffconfig = get_config('course');
	
	    $frm->enrol_flatfilemapping = array();
	    foreach($roles as $id => $record) {
			$mapkey = "map_{$record->shortname}";
	        $frm->enrol_flatfilemapping[$id] = array(
	            $record->name,
	            isset($ffconfig->$mapkey) ? $ffconfig->$mapkey : $record->shortname
	        );
	    }	    
	    include ($CFG->dirroot.'/enrol/sync/courses/config.html');    
	}

	/**
	* Override the base process_config() function
	*/
	function process_config($config) {
		global $CFG;

	    if (!isset($config->course_filedeletelocation)) {
	        $config->course_filedeletelocation = '';
	    }
	    set_config('course_filedeletelocation', $config->course_filedeletelocation);

	    if (!isset($config->course_filedeleteidentifier)) {
	        $config->course_filedeleteidentifier = 0;
	    }
	    set_config('course_filedeleteidentifier', $config->course_filedeleteidentifier);
		
	
	    if (!isset($config->course_fileuploadlocation)) {
	        $config->course_fileuploadlocation = '';
	    }
	    set_config('course_fileuploadlocation', $config->course_fileuploadlocation);
	
	    if (!isset($config->file_course_exist)) {
	        $config->file_course_exist = '';
	    }
	    set_config('file_course_exist', $config->file_course_exist);		
		
	    if (!isset($config->reset_course_file)) {
	        $config->reset_course_file = '';
	    }
	    set_config('reset_course_file', $config->reset_course_file);	

	    if (!isset($config->sync_forcecourseupdate)) {
	        $config->sync_forcecourseupdate = 0;
	    }
	    set_config('sync_forcecourseupdate', $config->sync_forcecourseupdate);	
				
	    return true;
	}
    
    function cron() {
        global $CFG, $USER;
	
	    define('TOPIC_FIELD','/^(topic)([0-9]|[1-4][0-9]|5[0-2])$/');
		define('TEACHER_FIELD','/^(teacher)([1-9]+\d*)(_account|_role)$/');
		
		if (empty($this->controlfiles->deletion)){
			if (empty($CFG->course_filedeletelocation)) {
	            $this->controlfiles->deletion = $CFG->dataroot.'/sync/deletecourses.csv';  // Default location
	        } else {
	            $this->controlfiles->deletion = $CFG->dataroot.'/'.$CFG->course_filedeletelocation;
	        }
	    }
		
		if (empty($this->controlfiles->creation)){
			if (empty($CFG->course_fileuploadlocation)) {
	            $this->controlfiles->creation = $CFG->dataroot.'/sync/makecourses.csv';  // Default location
	        } else {
	            $this->controlfiles->creation = $CFG->dataroot.'/'.$CFG->course_fileuploadlocation;
	        }
	    }
		
		if (empty($this->controlfiles->check)){
			if (empty($CFG->file_course_exist)) {
	            $this->controlfiles->check = $CFG->dataroot.'/sync/courses.csv';  // Default location
	        } else {
	            $this->controlfiles->check = $CFG->dataroot.'/'.$CFG->file_course_exist;
	        }
	    }

	/// process files

		if(file_exists($this->controlfiles->check) && ($this->execute & SYNC_COURSE_CHECK)){

			enrol_sync_report($CFG->courselog, get_string('startingcheck', 'enrol_sync'));

			$text = '';

			$fp = @fopen($this->controlfiles->check, 'rb', 0);

			if ($fp) {

				$i = 0;

				while (!feof($fp)) {					
					
					$text = fgets($fp, 1024);
					
					// skip comments and empty lines
					if (sync_is_empty_line_or_format($text, $i == 0)){
						continue;
					}
										
					$valueset = explode($CFG->sync_csvseparator, $text);

					$size = count($valueset);

					if($size == 2){
						$c = new StdClass;
						$c->idnumber = $valueset[0];
						$c->description = $valueset[1];
						$course = get_record('course', 'idnumber', $c->idnumber);

						// give report on missing courses
						if(!$course){
							enrol_sync_report($CFG->courselog, get_string('coursenotfound2', 'enrol_sync', $course));
						} else {
							enrol_sync_report($CFG->courselog, get_string('coursefoundas', 'enrol_sync', $course));
						}
					}
					
					$i++;
				}
			} else {
				enrol_sync_report($CFG->courselog, get_string('filenotfound', 'enrol_sync', $this->controlfiles->check));
			}
			fclose($fp);					
		}

	/// delete (clean) courses
        if (file_exists($this->controlfiles->deletion) && ($this->execute & SYNC_COURSE_DELETE)) {

			$fh = fopen($this->controlfiles->deletion, 'rb');

            if ($fh) {
				
				$i = 0;
				$shortnames = array();

				while (!feof($fh)) {					
					
					$text = fgets($fh);
					
					// skip comments and empty lines
					if (sync_is_empty_line_or_format($text, $i)){
						continue;
					}			
					$identifiers[] = $text;											
				}
				
				// Fill this with a list of comma seperated id numbers to delete courses.
				$deleted = 0;
				
				$identifieroptions = array('idnumber', 'shortname', 'id');
				$identifiername = $identifieroptions[0 + @$CFG->course_filedeleteidentifier];
				
				foreach($identifiers as $cid) {
					if(!($c = get_record('course', $identifiername, addslashes($cid))) ) {
						enrol_sync_report($CFG->courselog, get_string('coursenotfound', 'enrol_sync', $cid));
						if (!empty($CFG->sync_filefailed)) sync_feed_tryback_file($this->controlfiles->deletion, $text, null);
						$i++;
						continue;
					}

					if(delete_course($c->id, false)) {
						$deleted++;
						enrol_sync_report($CFG->courselog, get_string('coursedeleted', 'enrol_sync', $cid));
					}
				}
				
				if ($deleted){				
					fix_course_sortorder();
				}									
			}
			fclose($fh);

			if (!empty($CFG->sync_filearchive)){
				$archivename = basename($this->controlfiles->deletion);
				$now = date('Ymd-hi', time());
				$archivename = $CFG->dataroot."/sync/archives/{$now}_deletion_$archivename";
				copy($this->controlfiles->deletion, $archivename);
			}
			
			if (!empty($CFG->sync_filecleanup)){
				@unlink($this->controlfiles->deletion);
			}
        }
		
	/// update/create courses
	
		if (file_exists($this->controlfiles->creation) && ($this->execute & SYNC_COURSE_CREATE)) {

	        // make arrays of fields for error checking
	        $defaultcategory = $this->get_default_category();
	        $defaultmtime = time();

	        $required = array(  'fullname' => false, // Mandatory fields
	                            'shortname' => false);
	
	        $optional = array(  'category' => $defaultcategory, // Default values for optional fields
	                            'sortorder' => 0,
	                            'summary' => get_string('couresedefaultsummary', 'enrol_sync'),
	                            'format' => 'weeks',
	                            'showgrades' => 1,
	                            'newsitems' => 5,
	                            'teacher' => 'Teacher',
	                            'teachers' => 'Teachers',
	                            'student' => 'Student',
	                            'students' => 'Students',
	                            'startdate' => $defaultmtime,
	                            'numsections' => 10,
	                            'maxbytes' => 2097152,
	                            'visible' => 1,
	                            'groupmode' => 0,
	                            'timecreated' => $defaultmtime,
	                            'timemodified' => $defaultmtime,
	                            'idnumber' => '',
	                            'password' => '',
	                            'enrolperiod' => 0,
	                            'groupmodeforce' => 0,
	                            'metacourse' => 0,
	                            'lang' => '',
	                            'theme' => '',
	                            'cost' => '',
	                            'showreports' => 0,
	                            'guest' => 0,
								'enrollable' => 1,
								'enrolstartdate' => $defaultmtime,
								'enrolenddate' => $defaultmtime,
								'notifystudents' => 0,
								'template' => '',
								'expirynotify' => 0,
								'expirythreshold' => 10);

	        $validate = array(  'fullname' => array(1,254,1), // Validation information - see validate_as function
	                            'shortname' => array(1,15,1),
	                            'category' => array(5),
	                            'sortorder' => array(2,4294967295,0),
	                            'summary' => array(1,0,0),
	                            'format' => array(4,'social,topics,weeks'),
	                            'showgrades' => array(4,'0,1'),
	                            'newsitems' => array(2,10,0),
	                            'teacher' => array(1,100,1),
	                            'teachers' => array(1,100,1),
	                            'student' => array(1,100,1),
	                            'students' => array(1,100,1),
	                            'startdate' => array(3),
	                            'numsections' => array(2,52,0),
	                            'maxbytes' => array(2,$CFG->maxbytes,0),
	                            'visible' => array(4,'0,1'),
	                            'groupmode' => array(4,NOGROUPS.','.SEPARATEGROUPS.','.VISIBLEGROUPS),
	                            'timecreated' => array(3),
	                            'timemodified' => array(3),
	                            'idnumber' => array(1,100,0),
	                            'password' => array(1,50,0),
	                            'enrolperiod' => array(2,4294967295,0),
	                            'groupmodeforce' => array(4,'0,1'),
	                            'metacourse' => array(4,'0,1'),
	                            'lang' => array(1,50,0),
	                            'theme' => array(1,50,0),
	                            'cost' => array(1,10,0),
	                            'showreports' => array(4,'0,1'),
	                            'guest' => array(4,'0,1,2'),
								'enrollable' => array(4,'0,1'),
								'enrolstartdate' => array(3),
								'enrolenddate' => array(3),
								'notifystudents' => array(4,'0,1'),
								'template' => array(1,0,0),
								'expirynotify' => array(4,'0,1'),
								'expirythreshold' => array(2,30,1), // Following ones cater for [something]N
	                            'topic' => array(1,0,0),
	                            'teacher_account' => array(6,0),
	                            'teacher_role' => array(1,40,0));
			
			$fu = @fopen($this->controlfiles->creation, 'rb');
			
			$i = 0;
			
			if ($fu) {

				while(!feof($fu)){
					$text = fgets($fu, 1024);
					if (!sync_is_empty_line_or_format($text, $i == 0)){
						break;
					}
					$i++;
				}

				$header = split($CFG->sync_csvseparator, $text);
		
        		// check for valid field names

        		foreach ($header as $h) {			
		            if (empty($h)){
		                enrol_sync_report($CFG->courselog, get_string('errornullcsvheader', 'enrol_sync'));
		                return;
		            }
                    if (preg_match(TOPIC_FIELD, $h)) { // Regex defined header names
                    } elseif (preg_match(TEACHER_FIELD, $h)) {                         
                    } else {
                		if (!(isset($required[$h]) || isset($optional[$h]))){ 
			                enrol_sync_report($CFG->courselog, get_string('invalidfieldname', 'error', $h));
			                return;
						}
					
                		if (isset($required[$h])){
							$required[$h] = true; 
						}
            		}
        		}

		        // check for required fields
		        foreach ($required as $key => $value) {
		            if ($value != true){
		                enrol_sync_report($CFG->courselog, get_string('fieldrequired', 'error', $key));
		                return;
		            }
		        }

        		$fieldcount = count($header);

		        unset($bulkcourses);
				$courseteachers = array();

				// start processing lines
	
        		while (!feof($fu)) {
        			$text = fgets($fu, 1024);

					if (sync_is_empty_line_or_format($text)) {
						$i++;
						continue;
					}
					
					$valueset = explode($CFG->sync_csvseparator, $text);
        				
            		if (count($valueset) != $fieldcount){					
               			$e->i = $i;
               			$e->count = count($valueset);
               			$e->expected = $fieldcount;
		                enrol_sync_report($CFG->courselog, get_string('errorbadcount', 'enrol_sync', $e));
						if (!empty($CFG->sync_filefailed)) sync_feed_tryback_file($this->controlfiles->creation, $text, null);
						$i++;
		                continue;
            		}

	                unset($coursetocreate);
	                unset($coursetopics);
	                unset($courseteachers);

					// Set course array to defaults
	                foreach ($optional as $key => $value) { 
	                    $coursetocreate[$key] = $value;
	                }

					$coursetopics = array();

					// Validate incoming values
            		foreach ($valueset as $key => $value) { 
                		$cf = $header[$key];

                		if (preg_match(TOPIC_FIELD, $cf, $matches)) {
                  			$coursetopics[$matches[2]] = $this->validate_as($value, $matches[1], $i, $cf);
                		} elseif (preg_match(TEACHER_FIELD, $cf, $matches)) {
                  			$tmp = $this->validate_as($value, $matches[1].$matches[3], $i, $cf);
                  			(isset($tmp) && ($tmp != '')) and ($courseteachers[$matches[2]][$matches[3]] = $tmp);
                		} else {						
                    		$coursetocreate[$cf] = $this->validate_as($value, $cf, $i); // Accept value if it passed validation
                		}
            		}
            		$coursetocreate['topics'] = $coursetopics;
				
            		if (isset($courseteachers)){
                		foreach ($courseteachers as $key => $value){ // Deep validate course teacher info on second pass
	                  		if (isset($value) && (count($value) > 0)){
	                    		if (!(isset($value['_account']) && $this->check_is_in($value['_account']))){
	                    			$e->i = $i;
	                    			$e->key = $key;
		                			enrol_sync_report($CFG->courselog, get_string('errornoteacheraccountkey', 'enrol_sync', $e));
	                          		continue;
	                          	}
                    			// Hardcoded default values (that are as close to moodle's UI as possible)
                    			// and we can't assume PHP5 so no pointers!
	                    		if (!isset($value['_role'])){
	                        		$courseteachers[$key]['_role'] = '';
	                        	}
	                  		}
	                  	}
                	} else {
						$courseteachers = array();
            		}
                	$coursetocreate['teachers_enrol'] = $courseteachers;
                	$bulkcourses["$i"] = $coursetocreate; // Merge into array
                	$sourcetext["$i"] = $text; // Save text line for futher reference
            		$i++;
            	}
        	}
        	fclose($fu);
        
	        if (empty($bulkcourses)){
    			enrol_sync_report($CFG->courselog, get_string('errornocourses', 'enrol_sync'));
    			return;
	        }
            
        	// Running Status Totals
        
	        $t = 0; // Read courses
	        $s = 0; // Skipped courses
	        $n = 0; // Created courses
	        $p = 0; // Broken courses (failed halfway through
        
	        $cat_e = 0; // Errored categories
	        $cat_c = 0; // Created categories

	        foreach ($bulkcourses as $i => $bulkcourse) {
	        	
            	$a->shortname = $bulkcourse['shortname'];
            	$a->fullname = $bulkcourse['fullname'];

	            // Try to create the course
	            if (!$oldcourse = get_record('course', 'shortname', addslashes($bulkcourse['shortname']))) {
	              
	                $coursetocategory = 0; // Category ID
	                
	                if (is_array($bulkcourse['category'])) {
	                    // Course Category creation routine as a category path was given
	                    
	                    $curparent = 0;
	                    $curstatus = 0;
	    
	                    foreach ($bulkcourse['category'] as $catindex => $catname) {
	                      	$curparent = $this->fast_get_category_ex($catname, $curstatus, $curparent);
	                        switch ($curstatus) {
	                          	case 1: // Skipped the category, already exists
	                          		break;
	                          	case 2: // Created a category
	                            	$cat_c++;
	                          		break;
	                          	default:
	                            	$cat_e += count($bulkcourse['category']) - $catindex;
	                            	$coursetocategory = -1;
	                            	$e->catname = $catname;
	                            	$e->failed = $cat_e;
	                            	$e->i = $i;
    								enrol_sync_report($CFG->courselog, get_string('errorcategorycreate', 'enrol_sync', $e));
									if (!empty($CFG->sync_filefailed)) sync_feed_tryback_file($this->controlfiles->creation, $sourcetext[$i], $header);
	                          		continue;
	                        }      
	                    }
	                    ($coursetocategory == -1) or $coursetocategory = $curparent;
	                    // Last category created will contain the actual course
	                } else {
	                    // It's just a straight category ID
	                    $coursetocategory = (!empty($bulkcourse['category'])) ? $bulkcourse['category'] : -1 ;
	                }
	                
	                if ($coursetocategory == -1) {
	                	$e->i = $i;
	                	$e->coursename = $bulkcourse['shortname'];
						if (!empty($CFG->sync_filefailed)) sync_feed_tryback_file($this->controlfiles->creation, $sourcetext[$i], $header);
    					enrol_sync_report($CFG->courselog, get_string('errorcategoryparenterror', 'enrol_sync', $e));
    					continue;
	                } else {
	                    $result = $this->fast_create_course_ex($coursetocategory, $bulkcourse, $header, $validate);
	                    $e->coursename = $bulkcourse['shortname'];
	                    $e->i = $i;
						switch ($result) {
		                    case 1:
    							enrol_sync_report($CFG->courselog, get_string('coursecreated', 'enrol_sync', $a));
		                        $n++; // Succeeded
		                    break;
		                    case -3:
    							enrol_sync_report($CFG->courselog, get_string('errorsectioncreate', 'enrol_sync', $e));
								if (!empty($CFG->sync_filefailed)) sync_feed_tryback_file($this->controlfiles->creation, $sourcetext[$i], $header);
		                        $p++;
		                    break;
		                    case -4:
    							enrol_sync_report($CFG->courselog, get_string('errorteacherenrolincourse', 'enrol_sync', $e));
								if (!empty($CFG->sync_filefailed)) sync_feed_tryback_file($this->controlfiles->creation, $sourcetext[$i], $header);
		                        $p++;
		                    break;
							case -5:
    							enrol_sync_report($CFG->courselog, get_string('errorteacherrolemissing', 'enrol_sync', $e));
								if (!empty($CFG->sync_filefailed)) sync_feed_tryback_file($this->controlfiles->creation, $sourcetext[$i], $header);
								$p++;
							break;
							case -6:
    							enrol_sync_report($CFG->courselog, get_string('errorcoursemisconfiguration', 'enrol_sync', $e));
								if (!empty($CFG->sync_filefailed)) sync_feed_tryback_file($this->controlfiles->creation, $sourcetext[$i], $header);
								$p++;
							break;
							case -7:
								$e->template = $bulkcourse['template'];
    							enrol_sync_report($CFG->courselog, get_string('errortemplatenotfound', 'enrol_sync', $e));
								if (!empty($CFG->sync_filefailed)) sync_feed_tryback_file($this->controlfiles->creation, $sourcetext[$i], $header);
								$p++;
							break;	
							case -8:
    							enrol_sync_report($CFG->courselog, get_string('errorrestoringtemplatesql', 'enrol_sync', $e));
								if (!empty($CFG->sync_filefailed)) sync_feed_tryback_file($this->controlfiles->creation, $sourcetext[$i], $header);
								$p++;
							break;	
		                    default:
    							enrol_sync_report($CFG->courselog, get_string('errorrestoringtemplate', 'enrol_sync', $e));
								if (!empty($CFG->sync_filefailed)) sync_feed_tryback_file($this->controlfiles->creation, $sourcetext[$i], $header);
		                    break;
	                	}
	              	}
	            } else {
	            	if (!empty($CFG->sync_forcecourseupdate)){

						$coursetocategory = 0;
						 
		                if (is_array($bulkcourse['category'])) {
		                    // Course Category creation routine as a category path was given
		                    
		                    $curparent = 0;
		                    $curstatus = 0;
		    
		                    foreach ($bulkcourse['category'] as $catindex => $catname) {
		                      	$curparent = $this->fast_get_category_ex($catname, $curstatus, $curparent);
		                        switch ($curstatus) {
		                          	case 1: // Skipped the category, already exists
		                          		break;
		                          	case 2: // Created a category
		                            	$cat_c++;
		                          		break;
		                          	default:
		                            	$cat_e += count($bulkcourse['category']) - $catindex;
		                            	$coursetocategory = -1;
		                            	$e->catname = $catname;
		                            	$e->failed = $cat_e;
		                            	$e->i = $i;
	    								enrol_sync_report($CFG->courselog, get_string('errorcategorycreate', 'enrol_sync', $e));
										if (!empty($CFG->sync_filefailed)) sync_feed_tryback_file($this->controlfiles->creation, $sourcetext[$i], $header);
		                          		continue;
		                        }      
		                    }
		                    ($coursetocategory == -1) or $coursetocategory = $curparent;
		                    // Last category created will contain the actual course
		                } else {
		                    // It's just a straight category ID
		                    $coursetocategory = (!empty($bulkcourse['category'])) ? $bulkcourse['category'] : -1 ;
		                }
		                
		                if ($coursetocategory == -1){
		                	$e->i = $i;
		                	$e->coursename = $oldcourse->shortname;
							if (!empty($CFG->sync_filefailed)) sync_feed_tryback_file($this->controlfiles->creation, $sourcetext[$i], $header);
	    					enrol_sync_report($CFG->courselog, get_string('errorcategoryparenterror', 'enrol_sync', $e));
	    					continue;
		                } else {
		                	$oldcourse->category = $coursetocategory;
		                }

	            		foreach($bulkcourse as $key => $value){
	            			if (isset($oldcourse->$key) && $key != 'id' && $key != 'category'){
	            				$oldcourse->$key = addslashes($value);
	            			}
	            		}
	            		if (update_record('course', $oldcourse)){
	                    	$e->i = $i;
	            			$e->shortname = $oldcourse->shortname;
							enrol_sync_report($CFG->courselog, get_string('courseupdated', 'enrol_sync', $e));
	            		} else {
	                    	$e->i = $i;
	            			$e->shortname = $oldcourse->shortname;
							enrol_sync_report($CFG->courselog, get_string('errorcourseupdated', 'enrol_sync', $e));
	            		}
	            	} else {
						enrol_sync_report($CFG->courselog, get_string('courseexists', 'enrol_sync', $a));
		              	// Skip course, already exists
		            }
	              	$s++;
	            }
	            $t++;
	        }    
            
	        fix_course_sortorder(); // Re-sort courses
	        
	        // We potentially did a lot to the Database: avoid tempting fate
	        if (!mysql_query('ANALYZE TABLE `'.$CFG->prefix.'course`') && mysql_query('ANALYZE TABLE `'.$CFG->prefix.'course_categories`') && mysql_query('ANALYZE TABLE `'.$CFG->prefix.'course_sections`')){
	            $errormessage = 'Could not ANALYZE Database Tables (`'.$CFG->prefix.'course`, `'.$CFG->prefix.'course_categories`, `'.$CFG->prefix.'course_sections` and `'.$CFG->prefix.'user_teachers`) - You should do this manually';
				enrol_sync_report($CFG->courselog, $errormessage);
	        }
	        if (!mysql_query('OPTIMIZE TABLE `'.$CFG->prefix.'course`') && mysql_query('OPTIMIZE TABLE `'.$CFG->prefix.'course_categories`') && mysql_query('OPTIMIZE TABLE `'.$CFG->prefix.'course_sections`')){
	            $errormessage = 'Could not OPTIMIZE Database Tables (`'.$CFG->prefix.'course`, `'.$CFG->prefix.'course_categories`, `'.$CFG->prefix.'course_sections` and `'.$CFG->prefix.'user_teachers`) - You should do this manually';
				enrol_sync_report($CFG->courselog, $errormessage);
	        }

			if (!empty($CFG->sync_filearchive)){
				$archivename = basename($this->controlfiles->creation);
				$now = date('Ymd-hi', time());
				$archivename = $CFG->dataroot."/sync/archives/{$now}_creation_$archivename";
				copy($this->controlfiles->creation, $archivename);
			}
			
			if (!empty($CFG->sync_filecleanup)){
				@unlink($this->controlfiles->creation);
			}
        }
        
        return true;
    }
		
	/**
	*
	*/
    function get_default_category() {
        global $CFG;
        global $USER;

		if (!$mincat = get_field('course_categories', 'MIN(id)', '', '')){
	        return 1; // *SHOULD* be the Misc category?
	    }
	    return $mincat;
    }

	/**
	*
	*
	*
	*/
    function check_is_in($supposedint) {
        return ((string)intval($supposedint) == $supposedint) ? true : false;
    }

	/**
	*
	*
	*/
    function check_is_string($supposedstring) {
        $supposedstring = trim($supposedstring); // Is it just spaces?
        return (strlen($supposedstring) == 0) ? false : true;
    }

    /**
    * Validates each field based on information in the $validate array
    *
    */
    function validate_as($value, $validatename, $lineno, $fieldname = '') {
        global $USER;
		global $CFG;
        global $validate;
		
        $validate = array(  'fullname' => array(1,254,1), // Validation information - see validate_as function
                            'shortname' => array(1,100,1),
                            'category' => array(5),
                            'sortorder' => array(2,4294967295,0),
                            'summary' => array(1,0,0),
                            'format' => array(4,'social,topics,weeks'),
                            'showgrades' => array(4,'0,1'),
                            'newsitems' => array(2,10,0),
                            'teacher' => array(1,100,1),
                            'teachers' => array(1,100,1),
                            'student' => array(1,100,1),
                            'students' => array(1,100,1),
                            'startdate' => array(3),
                            'numsections' => array(2,52,0),
                            'maxbytes' => array(2,$CFG->maxbytes,0),
                            'visible' => array(4,'0,1'),
                            'groupmode' => array(4,NOGROUPS.','.SEPARATEGROUPS.','.VISIBLEGROUPS),
                            'timecreated' => array(3),
                            'timemodified' => array(3),
                            'idnumber' => array(1,100,0),
                            'password' => array(1,50,0),
                            'enrolperiod' => array(2,4294967295,0),
                            'groupmodeforce' => array(4,'0,1'),
                            'metacourse' => array(4,'0,1'),
                            'lang' => array(1,50,0),
                            'theme' => array(1,50,0),
                            'cost' => array(1,10,0),
                            'showreports' => array(4,'0,1'),
                            'guest' => array(4,'0,1,2'),
							'enrollable' => array(4,'0,1'),
							'enrolstartdate' => array(3),
							'enrolenddate' => array(3),
							'notifystudents' => array(4,'0,1'),
							'template' => array(1,0,0),
							'expirynotify' => array(4,'0,1'),
							'expirythreshold' => array(2,30,1), // Following ones cater for [something]N
                            'topic' => array(1,0,0),
                            'teacher_account' => array(6,0),
                            'teacher_role' => array(1,40,0));		
        
        if ($fieldname == ''){
        	$fieldname = $validatename;
        }
		 
        if (!isset($validate[$validatename])){
        	// we dont translate this > developper issue
        	$errormessage = 'Coding Error: Unvalidated field type: "'.$validatename.'"';
			enrol_sync_report($CFG->courselog, $errormessage);
			return;
        }

        $format = $validate[$validatename];

        switch($format[0]) {
	        case 1: // String
	            if (($maxlen = $format[1]) != 0){  // Max length?
	                if (strlen($value) > $format[1]){
	                	$e->i = $lineno;
	                	$e->fieldname = $fieldname;
	                	$e->length = $format[1];
						enrol_sync_report($CFG->courselog, get_string('errorvalidationstringlength', 'enrol_sync', $e));
						return;
                    }
                }

	            if ($format[2] == 1){ // Not null?
	                if (!$this->check_is_string($value)){
	                	$e->i = $lineno;
	                	$e->fieldname = $fieldname;
						enrol_sync_report($CFG->courselog, get_string('errorvalidationempty', 'enrol_sync', $e));
						return;
	                }
	            }
	        break;

	        case 2: // Integer
                if (!$this->check_is_in($value)){ 
                	$e->i = $lineno;
                	$e->fieldname = $fieldname;
					enrol_sync_report($CFG->courselog, get_string('errorvalidationintegercheck', 'enrol_sync', $e));
					return;
				}
                
                if (($max = $format[1]) != 0){  // Max value?
                    if ($value > $max){
	                	$e->i = $lineno;
	                	$e->fieldname = $fieldname;
	                	$e->max = $max;
						enrol_sync_report($CFG->courselog, get_string('errorvalidationintegerabove', 'enrol_sync', $e));
						return;
                    }
                }

                if (isset($format[2]) && !is_null($format[2])){  // Min value
	                $min = $format[2];
                    if ($value < $min){ 
	                	$e->i = $lineno;
	                	$e->fieldname = $fieldname;
	                	$e->min = $min;
						enrol_sync_report($CFG->courselog, get_string('errorvalidationintegerbeneath', 'enrol_sync', $e));
						return;
                    }
                }
	        break;
    
	        case 3: // Timestamp - validates and converts to Unix Time
	        	$value = strtotime($value);
	            if (!$checktime){
                	$e->i = $lineno;
                	$e->fieldname = $fieldname;
					enrol_sync_report($CFG->courselog, get_string('errorvalidationtimecheck', 'enrol_sync', $e));
					return;
	            }
	        break;

	        case 4: // Domain
	            $validvalues = explode(',', $format[1]);
	            if (array_search($value, $validvalues) === false){
                	$e->i = $lineno;
                	$e->fieldname = $fieldname;
                	$e->set = $format[1];
					enrol_sync_report($CFG->courselog, get_string('errorvalidationvalueset', 'enrol_sync', $e));
					return;
				}
	        break; 

	        case 5: // Category
	            if ($this->check_is_in($value)) {
	              // It's a Category ID Number
					if (!record_exists('course_categories', 'id', $value)){
	                	$e->i = $lineno;
	                	$e->fieldname = $fieldname;
	                	$e->category = $value;
						enrol_sync_report($CFG->courselog, get_string('errorvalidationcategoryid', 'enrol_sync', $e));
						return;
					}
	            } elseif ($this->check_is_string($value)) {
	               	// It's a Category Path string
	               
	               	$value = trim(str_replace('\\','/',$value)," \t\n\r\0\x0B/");
	               	// Clean path, ensuring all slashes are forward ones
	               
	               	if (strlen($value) <= 0){
	                	$e->i = $lineno;
	                	$e->fieldname = $fieldname;
						enrol_sync_report($CFG->courselog, get_string('errorvalidationcategoryunpathed', 'enrol_sync', $e));
						return;
	                }
	                
	                unset ($cats);
	                $cats = explode('/', $value); // Break up path into array
	                	                
	                if (count($cats) <= 0){
	                	$e->i = $lineno;
	                	$e->fieldname = $fieldname;
	                	$e->path = $value;
						enrol_sync_report($CFG->courselog, get_string('errorvalidationcategorybadpath', 'enrol_sync', $e));
						return;
	                }
	                          
	                foreach ($cats as $n => $item) { // Validate the path
	                  
	                  	$item = trim($item); // Remove outside whitespace
	                  
	                  	if (strlen($item) > 100){ 
		                	$e->i = $lineno;
		                	$e->fieldname = $fieldname;
		                	$e->item = $item;
							enrol_sync_report($CFG->courselog, get_string('errorvalidationcategorylength', 'enrol_sync', $e));
	                      	return;
	                    }
	                  	if (!$this->check_is_string($item)){
		                	$e->i = $lineno;
		                	$e->fieldname = $fieldname;
		                	$e->value = $value;
		                	$e->pos = $n + 1;
							enrol_sync_report($CFG->courselog, get_string('errorvalidationcategorytype', 'enrol_sync', $e));
	                      	return;
	                    }
	                }
	                
	                $value = $cats; // Return the array
	                unset ($cats);
	               
	            } else {
                	$e->i = $lineno;
                	$e->fieldname = $fieldname;
					enrol_sync_report($CFG->courselog, get_string('errorvalidationbadtype', 'enrol_sync', $e));
					return;
	            }
	        break;

	        case 6: // User ID or Name (Search String)
	            $value = trim($value);
	            if ($this->check_is_in($value)) { // User ID
	                if (!record_exists('user', 'id', $value)){
	                	$e->i = $lineno;
	                	$e->fieldname = $fieldname;
	                	$e->value = $value;
						enrol_sync_report($CFG->courselog, get_string('errorvalidationbaduserid', 'enrol_sync', $e));
	                    return;
	                }
	            } elseif ($this->check_is_string($value)) { // User Search String
	                // Only PHP5 supports named arguments
	                $usersearch = get_users_listing('lastaccess', 'ASC', 0, 99999, mysql_real_escape_string($value), '', '');
	                if (isset($usersearch) and ($usersearch !== false) and is_array($usersearch) and (($ucountc = count($usersearch)) > 0)) {
	                    if ($ucount > 1){
		                	$e->i = $lineno;
		                	$e->fieldname = $fieldname;
	                    	$e->ucount = $ucount;
							enrol_sync_report($CFG->courselog, get_string('errorvalidationmultipleresults', 'enrol_sync', $e));
		                    return;
	                    }

	                    reset($usersearch);

	                    $uid = key($usersearch);

	                    if (!$this->check_is_in($uid) || !record_exists('user', 'id', $uid)){
		                	$e->i = $lineno;
		                	$e->fieldname = $fieldname;
							enrol_sync_report($CFG->courselog, get_string('errorvalidationsearchmisses', 'enrol_sync', $e));
		                    return;
	                    }
	
	                    $value = $uid; // Return found user id
	
	                } else {
	                	$e->i = $lineno;
	                	$e->fieldname = $fieldname;
						enrol_sync_report($CFG->courselog, get_string('errorvalidationsearchfails', 'enrol_sync', $e));
	                    return;
	                }
	            } else {
	              	if ($format[2] == 1){ // Not null?
	                	$e->i = $lineno;
	                	$e->fieldname = $fieldname;
						enrol_sync_report($CFG->courselog, get_string('errorvalidationempty', 'enrol_sync', $e));
	                    return;
                    }
	            }
	        break; 

	        default:
	        	// not translated
	            $errormessage = 'Coding Error: Bad field validation type: "'.$fieldname.'"';
				enrol_sync_report($CFG->courselog, $errormessage);
                return;
	        break;
        }

        return $value;
    }

    function microtime_float(){
        // In case we don't have php5
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
    
    function fast_get_category_ex($hname, &$hstatus, $hparent = 0){
        // Find category with the given name and parentID, or create it, in both cases returning a category ID
        /* $hstatus:
            -1  :   Failed to create category
            1   :   Existing category found
            2   :   Created new category successfully
        */
        
        global $CFG;
        global $USER;
        
        $cname = mysql_real_escape_string($hname);
        
        // Check if a category with the same name and parent ID already exists
        if ($cat = get_field_select('course_categories', 'id', " name = '$cname' AND parent = $hparent ")){
			$hstatus = 1;
        	return $cat;
        } else {
        	
        	if (!$parent = get_record('course_categories', 'id', $hparent)){
        		$parent->path = '';
        		$parent->depth = 0;
        		$hparent = 0;
        	}
        	
			$cat = new StdClass;
			$cat->name = $cname;
			$cat->description = '';
			$cat->parent = $hparent;
			$cat->sortorder = 999;
			$cat->coursecount = 0;
			$cat->visible = 1;
			$cat->depth = $parent->depth + 1;
			$cat->timemodified = time();
			if ($cat->id = insert_record('course_categories', $cat)){
				$hstatus = 2;
				
				// must post update 
				$cat->path = $parent->path.'/'.$cat->id;
				update_record('course_categories', $cat);
				
				// we must make category context
				create_context(CONTEXT_COURSECAT, $cat->id);
				
			} else {
				$hstatus = -1;
			}
			return $cat->id;
        }
    }
    
	// Edited by Ashley Gooding & Cole Spicer to fix problems with 1.7.1 and make easier to dynamically add new columns
	// we keep that old code till next work
    function fast_create_course_ex($hcategory, $course, $header, $validate) { 
        global $CFG;

		if(!is_array($course) || !is_array($header) || !is_array($validate)) {
			return -2;
		}  

		// trap when template not found
		if(isset($course['template']) && $course['template'] != '') {			
			if(!($tempcourse = get_record('course','shortname', $course['template']))){
				return -7;
			}

			if (!$pathname = $this->delivery_check_available_backup($tempcourse->id)){
				return -7;
			}

		}
		 
		// Dynamically Create Query Based on number of headings excluding Teacher[1,2,...] and Topic[1,2,...]
        // Added for increased functionality with newer versions of moodle
		// Author: Ashley Gooding & Cole Spicer
		
		$courserec->category = $hcategory;
		foreach ($header as $i => $col) {
			$col = strtolower($col);
			if(preg_match(TOPIC_FIELD, $col) || preg_match(TEACHER_FIELD, $col) || $col == 'category' || $col == 'template') {
				continue;
			}
			if($col == 'expirythreshold') {
				$courserec->$col = $course[$col]*86400;
			} else {
				$courserec->$col = $course[$col];
			}
		}
		if (!$courserec->id = insert_record('course', addslashes_object($courserec))){
			return -2;
		}

		create_context(CONTEXT_COURSE, $courserec->id);
		
		if(isset($course['template']) && $course['template'] != '') {
						
			import_backup_file_silently($pathname->path, $courserec->id, true, false, array('restore_course_files' => 1));
			
			$c = new StdClass;
			foreach ($header as $col) {

				$col = strtolower($col);
				if(preg_match(TOPIC_FIELD, $col) || preg_match(TEACHER_FIELD, $col) || $col == 'category' || $col == 'template') {
					continue;
				}
				if($col == 'expirythreshold') {
					$c->$col = $course[$col]*86400;
				} else {
					$c->$col = $course[$col];
				}
			}
			
			$thiscourse = get_record('course','id',$courserec->id);
			$thiscourse->format 			= (empty($c->format)) ? $tempcourse->format : $c->format;
			$thiscourse->sortorder 			= (empty($c->sortorder)) ? $tempcourse->sortorder : $c->sortorder;
			$thiscourse->showgrades  		= (empty($c->showgrades )) ? $tempcourse->showgrades  : $c->showgrades;
			$thiscourse->newsitems   		= (empty($c->newsitems)) ? $tempcourse->newsitems : $c->newsitems;
			$thiscourse->enrolperiod    	= (empty($c->enrolperiod)) ? $tempcourse->enrolperiod : $c->enrolperiod;
			$thiscourse->startdate  		= (empty($c->startdate)) ? $tempcourse->startdate : $c->startdate;
			$thiscourse->marker   			= (empty($c->marker)) ? $tempcourse->marker : $c->marker;
			$thiscourse->maxbytes  			= (empty($c->maxbytes)) ? $tempcourse->maxbytes : $c->maxbytes;
			$thiscourse->showreports  		= (empty($c->showreports)) ? $tempcourse->showreports : $c->showreports;
			$thiscourse->visible  			= (empty($c->visible)) ? $tempcourse->visible : $c->visible;
			$thiscourse->groupmode   		= (empty($c->groupmode)) ? $tempcourse->groupmode : $c->groupmode;
			$thiscourse->lang   			= (empty($c->lang)) ? $tempcourse->lang : $c->lang;
			$thiscourse->expirythreshold 	= (empty($c->expirythreshold)) ? $tempcourse->expirythreshold : $c->expirythreshold;
			$thiscourse->enrollable   		= (empty($c->enrollable)) ? $tempcourse->enrollable : $c->enrollable;
			$thiscourse->enrolstartdate   	= (empty($c->enrolstartdate)) ? $tempcourse->enrolstartdate : $c->enrolstartdate;
			$thiscourse->enrolenddate 		= (empty($c->enrolenddate)) ? $tempcourse->enrolenddate : $c->enrolenddate;
			$thiscourse->defaultrole 		= (empty($c->defaultrole)) ? $tempcourse->defaultrole : $c->defaultrole;
			$thiscourse->expirynotify 		= (empty($c->expirynotify)) ? $tempcourse->expirynotify : $c->expirynotify;
			$thiscourse->password 			= (empty($c->password)) ? $tempcourse->password : $c->password;
			update_record('course', addslashes_object($thiscourse));		
		} else {
        	$page = page_create_object(PAGE_COURSE_VIEW, $courserec->id);
        	blocks_repopulate_page($page); // Setup blocks
		}
        
        if (isset($course['topics'])) { // Any topic headings specified ?
            foreach($course['topics'] as $dtopicno => $dtopicname) 
            if ($dtopicno <= $course['numsections']) { // Avoid overflowing topic headings
		        $csection = new StdClass;
		        $csection->course = $courserec->id;
		        $csection->section = $dtopicno;
		        $csection->summary = $dtopicname;
		        $csection->sequence = '';
		        $csection->visible = 1;
		        if (!insert_record('course_sections', addslashes_object($csection))){
		        }
            }
        }
        
        if (!isset($course['topics'][0])) {
	        $csection = new StdClass;
	        $csection->course = $courserec->id;
	        $csection->section = 0;
	        $csection->summary = '';
	        $csection->sequence = '';
	        $csection->visible = 1;
	        if (!insert_record('course_sections', $csection)){
	            return -3;
	        }
        }
        
		if (!$context = get_context_instance(CONTEXT_COURSE, $courserec->id)) {
        	return -6;
    	}
		
        if (isset($course['teachers_enrol']) && (count($course['teachers_enrol']) > 0)) { // Any teachers specified?
            foreach($course['teachers_enrol'] as $dteacherno => $dteacherdata) {
                if (isset($dteacherdata['_account'])) {
					$roleid = get_field('role', 'shortname', $dteacherdata['_role']);
					$roleassignrec = new StdClass;
					$roleassignrec->roleid = $roleid;
					$roleassignrec->contextid = $context->id;
					$roleassignrec->userid = $dteacherdata['_account'];
					$roleassignrec->timemodified = $course['timecreated'];
					$roleassignrec->modifierid = 0;
					$roleassignrec->enrol = 'manual';
					if (!insert_record('role_assignments', $roleassignrec)){
	                    return -4;
					}
                }
            }
        }       
        
        return 1;
    }	

	function delivery_check_available_backup($courseid, &$loopback = null){
		global $CFG;

		$realpath = false;

		// calculate the archive pattern

		$course = get_record('course', 'id', $courseid);

		//Calculate the backup word
		$backup_word = backup_get_backup_string($course);

		//Calculate the date recognition/capture patterns
		$backup_date_pattern = '([0-9]{8}-[0-9]{4})';

		//Calculate the shortname
		$backup_shortname = clean_filename($course->shortname);
		if (empty($backup_shortname) or $backup_shortname == '_' ) {
			$backup_shortname = $course->id;
		} else {
			// get rid of all version information for searching archive
			$backup_shortname = preg_replace('/(_(\d+))+$/' , '', $backup_shortname);
		}
		
		//Calculate the final backup filename
		//The backup word
		$backup_pattern = $backup_word."-";
		//The shortname
		$backup_pattern .= preg_quote(moodle_strtolower($backup_shortname)).".*-";
		//The date format
		$backup_pattern .= $backup_date_pattern;
		//The extension
		$backup_pattern .= "\\.zip";
		
		// Get the last backup in the proper location
		// backup must have moodle backup filename format
		$realdir = $CFG->dataroot.'/'.$courseid.'/backupdata';

		if (!file_exists($realdir)) return false;

		if ($DIR = opendir($realdir)){
			$archives = array();
			while($entry = readdir($DIR)){
				if (preg_match("/^$backup_pattern\$/", $entry, $matches)){
					$archives[$matches[1]] = "{$realdir}/{$entry}";
				}
			}

			if (!empty($archives)){
				// sorts reverse the archives so we can get the latest.
				krsort($archives);
				$archnames = array_values($archives);
				$realpath->path = $archnames[0];
				$realpath->dir = $realdir;
			}
		}

		return $realpath;
	}
	
}
