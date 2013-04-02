<?php	   

/**
* This test script generates a set of three synchronisation descriptor files
*
* @package enrol
* @subpackage sync
* @author Funck Thibaut
*/

	require_once('../../config.php');
	require_once("../../course/lib.php");
	require_once($CFG->libdir.'/adminlib.php');	

	require_login();
	
	if (!isadmin()) {
        error('You must be an administrator to edit courses in this way.');
    }
	if (! $site = get_site()) {
        error('Could not find site-level course');
    }
	if (!$adminuser = get_admin()) {
        error('Could not find site admin');
    }

	print_header($SITE->fullname, $SITE->fullname, build_navigation(array()));		
	print_heading(get_string('filegenerator', 'enrol_sync');

/// create a test course definition file

	$filename = $CFG->dataroot.'/sync/uploadcourses.csv';
	$file = fopen($filename, 'w');

	fputs($file, "fullname, shortname, idnumber\n");
	for($i = 0 ; $i < 500 ; $i++){
		fputs($file,"full$i, short$i, id$i\n");
	}
	fputs($file,"full500, short500, id500");
	fclose($file);
		
/// create a test user definition file

	$filename = $CFG->dataroot.'/sync/uploadusers.csv';	
	$file = fopen($filename, "w");

	fputs($file,"username, firstname, lastname, email, password, lang, country, idnumber, auth\n");
	for($i = 0 ; $i < 500 ; $i++){
		fputs($file,"full$i, short$i, last$i, mail$i@ldap.fr, pass$i, fr_utf8, FR, id$i, ldap\n");
	}
	fputs($file,"full500, short500, last500, mail500@ldap.fr, pass500, fr_utf8, FR, id500, ldap");
	fclose($file);	

/// create a test enrollement file		

	$filename = $CFG->dataroot.'/sync/enrol.txt';	
	$file = fopen($filename, 'w');

	for($i = 0 ; $i < 500 ; $i++){
		fputs($file,"student, id$i, id$i\n");
		fputs($file,"student, id7, id$i\n");
	}
		
	fputs($file,"student, id500, id500");
	fclose($file);

	print_footer();
?>