<?php

// The following flags are set in the configuration
// $CFG->users_filelocation:       where is the file we are looking for?
// author - Funck Thibaut
require_once $CFG->dirroot.'/enrol/sync/lib.php';

class users_plugin_manager {

    var $log;  

	/// Override the base config_form() function
	function config_form($frm) {
    	global $CFG;

	    $vars = array('users_filelocation');
	    foreach ($vars as $var) {
	        if (!isset($frm->$var)) {
	            $frm->$var = '';
	        } 
	    }

	    $roles = get_records('role', '', '', '', 'id, name, shortname');
	    $ffconfig = get_config('course');

	    $frm->enrol_flatfilemapping = array();
	    foreach($roles as $id => $record) {
			$keymap = "map_{$record->shortname}";
	        $frm->enrol_flatfilemapping[$id] = array(
	            $record->name,
	            isset($ffconfig->$keymap) ? $ffconfig->$keymap : $record->shortname
	        );
	    }
	    
	    include ($CFG->dirroot.'/enrol/sync/users/config.html');    
	}


	/// Override the base process_config() function
	function process_config($config) {
	
	     if (!isset($config->users_filelocation)) {
	        $config->users_filelocation = '';
	    }
	    set_config('users_filelocation', $config->users_filelocation);
	
		if (!isset($config->users_uutype)) {
	        $config->users_uutype = '';
	    }
	    set_config('users_uutype', $config->users_uutype);
		
	    return true;	
	}

	/// Override the get_access_icons() function
	function get_access_icons($course) {
	}

	/**
	*/
    function cron() {
        global $CFG, $USER;

		$createpassword = 'no';
		$updateaccounts = 'yes';
		$allowrenames   = 'no';			
		
		if (!$adminuser = get_admin()) {
			error("Could not find site admin");
		}

		if (empty($CFG->users_filelocation)) {
            $filename = $CFG->dataroot.'/sync/userimport.csv';  // Default location
        } else {
            $filename = $CFG->dataroot.'/'.$CFG->users_filelocation;
        }

        if (!file_exists($filename)) {
			enrol_sync_report($CFG->userlog, get_string('filenotfound', 'enrol_sync', $filename));
			return;        	
        }

		$strfile = get_string('file');
		$struser = get_string('user');
		$strusersnew = get_string('usersnew');
		$strusersupdated = get_string('usersupdated');
		$struploadusers = get_string('uploadusers');
		$straddnewuser = get_string('importuser');

		$csv_encode = '/\&\#44/';
		if (isset($CFG->sync_csvseparator)) {
			$csv_delimiter = '\\' . $CFG->sync_csvseparator;
			$csv_delimiter2 = $CFG->sync_csvseparator;

			if (isset($CFG->CSV_ENCODE)) {
				$csv_encode = '/\&\#' . $CFG->CSV_ENCODE . '/';
			}
		} else {
			$csv_delimiter = "\;";
			$csv_delimiter2 = ";";
		}

		//*NT* File that is used is currently hardcoded here!
		// Large files are likely to take their time and memory. Let PHP know
		// that we'll take longer, and that the process should be recycled soon
		// to free up memory.
		@set_time_limit(0);
		@raise_memory_limit("256M");
		if (function_exists('apache_child_terminate')) {
			@apache_child_terminate();
		}

		// make arrays of valid fields for error checking
		$required = array('username' => 1,
				//'password' => !$createpassword,  //*NT* as we use LDAP and Moodle does not maintain passwords...OUT!
				'firstname' => 1,
				'lastname' => 1);
		$optionalDefaults = array(
				'mnethostid' => 1,  
				'institution' => '',
				'department' => '',
				'city' => 1,
				'country' => 1,
				'lang' => 1,
				'timezone' => 1);
		$optional = array('idnumber' => 1,
				'email' => 1,               //*NT* email is optional on upload to clear open ones  and reset at the beginning of the year!
				'wwwroot' => 1, // allows MNET propagation to remote node
				'auth' => 1,
				'icq' => 1,
				'phone1' => 1,
				'phone2' => 1,
				'address' => 1,
				'url' => 1,
				'description' => 1,
				'mailformat' => 1,
				'maildisplay' => 1,
				'htmleditor' => 1,
				'autosubscribe' => 1,
				'course1' => 1,
				'group1' => 1,
				'type1' => 1,
				'role1' => 1,
				'start1' => 1,
				'end1' => 1,
				'password' => $createpassword,
				'oldusername' => $allowrenames);

		// --- get header (field names) ---

		$textlib = new textlib();

		$fp = fopen($filename, 'rb');
		
		// jump any empty or comment line
		$text = fgets($fp, 1024);
		
		$i = 0;
		
		while(sync_is_empty_line_or_format($text, $i == 0)){				
			$text = fgets($fp, 1024);
			$i++;
		}

		$headers = split($csv_delimiter, $text);

		// check for valid field names
		foreach ($headers as $h) {
			$header[] = trim($h); 
		
			if (!(isset($required[$h]) or isset($optionalDefaults[$h]) or isset($optional[$h]))) {
				enrol_sync_report($CFG->userlog, get_string('invalidfieldname', 'error', $h));
				return;
			}

			if (isset($required[$h])) {
				$required[$h] = 0;
			}
		}
		// check for required fields
		foreach ($required as $key => $value) {
			if ($value) { //required field missing
				enrol_sync_report($CFG->userlog, get_string('fieldrequired', 'error', $key));
				return;
			}
		}
		$linenum = 2; // since header is line 1

		$usersnew     = 0;
		$usersupdated = 0;
		$userserrors  = 0;
		$renames      = 0;
		$renameerrors = 0;

		// Will use this course array a lot
		// so fetch it early and keep it in memory
		$courses = get_courses('all', 'c.sortorder','c.id,c.shortname,c.idnumber,c.fullname,c.sortorder,c.teacher,c.visible');

		// take some from admin profile, other fixed by hardcoded defaults
		foreach ($optionalDefaults as $key => $value) {
			if ($value == 'adminvalue'){
				$user->$key = addslashes($adminuser->$key);
			} else {
				$user->$key = addslashes($value);
			}
		}
		
		while (!feof ($fp)) {

			//Note: commas within a field should be encoded as &#44 (for comma separated csv files)
			//Note: semicolon within a field should be encoded as &#59 (for semicolon separated csv files)
			
			$text = fgets($fp, 1024);
			if (sync_is_empty_line_or_format($text, false)) {
				$i++;
				continue;
			}
			
			$valueset = explode($CFG->sync_csvseparator, $text);				
			$record = array();
			foreach ($valueset as $key => $value) {
				//decode encoded commas
				$record[$header[$key]] = preg_replace($csv_encode, $csv_delimiter2, trim($value));
			}
			
			if ($record[$header[0]]) {

				/// add a new user to the database
				
				// add fields to object $user
				foreach ($record as $name => $value) {
					if ($name == 'wwwroot') continue; // process later
					// check for required values
					if (isset($required[$name]) and !$value) {
						$errormessage = get_string('missingfield', 'error', $name)." ".get_string('erroronline', 'error', $linenum).". ".get_string('missingfield', 'error', $name);
						enrol_sync_report($CFG->userlog, $errormessage);
						return;
					} elseif ($name == 'password' && !empty($value)) {
					// password needs to be encrypted
						//$user->password = hash_internal_user_password($value);  *NT*  Password is LDAP!
					} elseif ($name == 'username') {
						$user->username = addslashes(moodle_strtolower($value));
					} else {
					// normal entry
						$user->{$name} = addslashes($value);
					}
				}
				$user->confirmed = 1;
				$user->timemodified = time();
				$linenum++;
				$username = $user->username;

				$ci = 1;
				$courseix = 'course'.$ci;
				$groupix = 'group'.$ci;
				$typeix = 'type'.$ci;
				$roleix = 'role'.$ci;
				$startix = 'start'.$ci;
				$endix = 'end'.$ci;
				$wwwrootix = 'wwwroot'.$ci;
				$addcourses = array();
				while(isset($user->$courseix)){
					$coursetoadd = new StdClass;
					$coursetoadd->idnumber = $user->$courseix;
					$coursetoadd->group = isset($user->$groupix) ? $user->$groupix : NULL;
					$coursetoadd->type = isset($user->$typeix) ? $user->$typeix : NULL;
					$coursetoadd->role = isset($user->$roleix) ? $user->$roleix : NULL;
					$coursetoadd->start = isset($user->$startix) ? $user->$startix : 0;
					$coursetoadd->end = isset($user->$endix) ? $user->$endix : 0;
					$coursetoadd->wwwroot = isset($user->$wwwrootix) ? $user->$wwwrootix : 0;
					$addcourses[] = $coursetoadd;
					$ci++;
					$courseix = 'course'.$ci;
					$groupix = 'group'.$ci;
					$typeix = 'type'.$ci;
					$roleix = 'role'.$ci;
					$startix = 'start'.$ci;
					$endix = 'end'.$ci;
					$wwwrootix = 'wwwroot'.$ci;
				}

				// before insert/update, check whether we should be updating
				// an old record instead
				if ($allowrenames && !empty($user->oldusername) ) {
					$user->oldusername = moodle_strtolower($user->oldusername);
					if ($olduser = get_record('user', 'username', $user->oldusername, 'mnethostid', $user->mnethostid)) {
						if (set_field('user', 'username', $user->username, 'username', $user->oldusername)) {
							enrol_sync_report($CFG->userlog, get_string('userrenamed', 'admin')." : $user->oldusername $user->username");
							$renames++;
						} else {
							enrol_sync_report($CFG->userlog, get_string('usernotrenamedexists', 'error')." : $user->oldusername $user->username");
							$renameerrors++;
							continue;
						}
					} else {
						enrol_sync_report($CFG->userlog, get_string('usernotrenamedmissing', 'error')." : $user->oldusername $user->username");
						$renameerrors++;
						continue;
					}
				}

				if (empty($user->mnethostid)) $user->mnethostid = $CFG->mnet_localhost_id;
				if ($olduser = get_record('user', 'username', $username, 'mnethostid', $user->mnethostid)) {
					if ($updateaccounts) {
						// Record is being updated
						$user->id = $olduser->id;
						if (update_record('user', $user)) {
							enrol_sync_report($CFG->userlog, get_string('useraccountupdated', 'enrol_sync', "$user->username ($user->idnumber)"));
							$usersupdated++;
						} else {
							enrol_sync_report($CFG->userlog, get_string('usernotupdatederror', 'error', "$username ($user->idnumber)"));
							$userserrors++;
							continue;
						}
					} else {
						//Record not added - user is already registered
						//In this case, output userid from previous registration
						//This can be used to obtain a list of userids for existing users
						enrol_sync_report($CFG->userlog, "$olduser->id ".get_string('usernotaddedregistered', 'error', "$username ($user->idnumber)"));
						$userserrors++;
					}
				} else { // new user
					if ($user->id = insert_record('user', $user)) {
						enrol_sync_report($CFG->userlog, get_string('useraccountadded', 'enrol_sync', "$user->id , $user->username "));
						$CFG->userlog .= "$user->id , $user->username $struser added\n";
						$usersnew++;
						if (empty($user->password) && $createpassword) {
							// passwords will be created and sent out on cron  *NT*  NO THEY WON"T
							//insert_record('user_preferences', array( userid => $user->id,
							//            name   => 'create_password',
							//            value  => 1));
							//insert_record('user_preferences', array( userid => $user->id,
							//            name   => 'auth_forcepasswordchange',
							//            value  => 1));
						}
					} else {
						// Record not added -- possibly some other error
						enrol_sync_report($CFG->userlog, get_string('usernotaddederror', 'error', "$username ($user->idnumber)"));
						$userserrors++;
						continue;
					}
				}

			// course binding management //
				if (!empty($addcourses)){
					foreach($addcourses as $c){

						if (empty($c->wwwroot)){
							// course binding is local

							if (!$crec = get_record('course', 'idnumber', $c->idnumber)){		
								enrol_sync_report($CFG->userlog, get_string('unknowncourse', 'error', $c->idnumber));
								continue;
							}

							$coursecontext = get_context_instance(CONTEXT_COURSE, $crec->id);
		
							if (!empty($c->role)) {
								if (!user_can_assign($coursecontext, $c->role)) {
									//notify('--> Can not assign role in course'); //TODO: localize
								}
								$role = get_record('role', 'shortname', $c->role);
								$ret = role_assign($role->id, $user->id, 0, $coursecontext->id);
								$e->contextid = $coursecontext->id;
								$e->rolename = $c->role;
								enrol_sync_report($CFG->userlog, get_string('roleadded', 'enrol_sync', $e));
							} else {
								$ret = enrol_student($user->id, $crec->id);
							}
							if (@$ret) {   // OK
	                			enrol_sync_report($CFG->userlog, get_string('enrolledincourse', '', $c->idnumber));
							} else {
	                			enrol_sync_report($CFG->userlog, get_string('enrolledincoursenot', '', $c->idnumber));
							}
							
							// we only can manage groups for successful enrollments

							if (@$ret) {   // OK
								// check group existance and try to create
								if (!empty($c->group)) {
									if (!$gid = groups_get_group_by_name($crec->id, $c->group)) {
										$groupsettings->name = $c->group;
										$groupsettings->courseid = $crec->id;
										if (!$gid = groups_create_group($groupsettings)) {
											enrol_sync_report($CFG->userlog, get_string('groupnotaddederror', 'error', $c->group));
										}
									}

									if ($gid){
										if (count(get_user_roles($coursecontext, $user->id))) {
											if (add_user_to_group($gid, $user->id)) {
					                			enrol_sync_report($CFG->userlog, get_string('addedtogroup', '',$c->group));
											} else {
					                			enrol_sync_report($CFG->userlog, get_string('addedtogroupnot', '',$c->group));
											}
										} else {
				                			enrol_sync_report($CFG->userlog, get_string('addedtogroupnotenrolled', '', $c->group));
										}
									}
								}
							}
						}

						// if we can propagate user to designates wwwroot let's do it					
						// only if the VMoodle block is installed.
						if (!empty($c->wwwroot) && get_record('block', 'name', 'vmoodle')){
							
							if (!file_exists($CFG->dirroot.'/blocks/vmoodle/rpclib.php')){
								notice('This feature works with VMoodle Virtual Moodle Implementation');
								continue;
							}
							include_once($CFG->dirroot.'/blocks/vmoodle/rpclib.php');
							include_once($CFG->libdir.'/pear/HTML/AJAX/JSON.php');
							include_once($CFG->dirroot.'/mnet/xmlrpc/client.php');
		
							// imagine we never did it before
							global $MNET;
							$MNET = new mnet_environment();
							$MNET->init();						
		
			                enrol_sync_report($CFG->userlog, get_string('propagating', 'local', fullname($user)));
			                $caller->username = 'admin';
			                $caller->remoteuserhostroot = $CFG->wwwroot;
			                $caller->remotehostroot = $CFG->wwwroot;
			
							// check if exists
		            		$exists = false;
			                if ($return = mnetadmin_rpc_user_exists($caller, $user->username, $c->wwwroot, true)){
			                	$response = json_decode($return);
			                	if (empty($response)){
			                		if (debugging())
				                		print_object($return);
			                		continue;
			                	}
			                	if ($response->status == RPC_FAILURE_DATA){
			                		enrol_sync_report($CFG->userlog, get_string('errorrpcparams', 'enrol_sync', implode("\n", $response->errors)));
			                		continue;
			                	} elseif ($response->status == RPC_FAILURE){
			                		enrol_sync_report($CFG->userlog, get_string('rpcmajorerror', 'enrol_sync'));
			                		continue;
			                	} elseif ($response->status == RPC_SUCCESS){
			                		if (!$response->user){
				                		enrol_sync_report($CFG->userlog, get_string('userunknownremotely', 'enrol_sync', fullname($user)));
				                		$exists = false;
				                	} else {
				                		enrol_sync_report($CFG->userlog, get_string('userexistsremotely', 'enrol_sync', fullname($user)));
				                		$exists = true;
				                	}
			                	}
			                }
			                	
		            		$created = false;
							if (!$exists){
				                if ($return = mnetadmin_rpc_create_user($caller, $user->username, $user, '', $c->wwwroot, false)){
				                	$response = json_decode($return);
				                	if (empty($response)){
				                		if (debugging()) print_object($return);
			                			enrol_sync_report($CFG->userlog, get_string('remoteserviceerror', 'enrol_sync'));
			                			continue;
				                	}
				                	if ($response->status != RPC_SUCCESS){
				                		// print_object($response);
			                			enrol_sync_report($CFG->userlog, get_string('communicationerror', 'enrol_sync'));
				                	} else {
				                		$u->username = $user->username;
				                		$u->wwwroot = $record['wwwroot'];
			                			enrol_sync_report($CFG->userlog, get_string('usercreatedremotely', 'enrol_sync', $u));
				                		$created = true;
				                	}
				                }
				            }
				            
				            // process remote course enrolment
				            if (!empty($c->role)){
								$response = mnetadmin_rpc_remote_enrol($caller, $user->username, $c->role, $c->wwwroot, 'shortname', $c->idnumber, $c->start, $c->end, false);
			                	if (empty($response)){
			                		if (debugging()) print_object($response);
		                			enrol_sync_report($CFG->userlog, get_string('remoteserviceerror', 'enrol_sync'));
		                			continue;
			                	}
			                	if ($response->status != RPC_SUCCESS){
			                		// print_object($response);
		                			enrol_sync_report($CFG->userlog, get_string('communicationerror', 'enrol_sync', implode("\n", $response->errors)));
			                	} else {			                		
			                		// in case this block is installed, mark access authorisations in the user's profile
			                		if (file_exists($CFG->dirroot.'/blocks/user_mnet_hosts/xlib.php')){
			                			include_once($CFG->dirroot.'/blocks/user_mnet_hosts/xlib.php');
			                			user_mnet_host_add_access($user, $c->wwwroot);
			                		}
			                		
			                		$e->username = $user->username;
			                		$e->rolename = $c->role;
			                		$e->coursename = $c->idnumber;
			                		$e->wwwroot = $c->wwwroot;
		                			enrol_sync_report($CFG->userlog, get_string('remoteenrolled', 'enrol_sync', $e));
			                	}
				            }
						}
					}
				}
				unset ($user);
			}
		}
		fclose($fp);
    }
}

?>
