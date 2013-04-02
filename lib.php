<?php

define('SYNC_COURSE_CHECK', 0x001);
define('SYNC_COURSE_CREATE', 0x002);
define('SYNC_COURSE_DELETE', 0x004);
define('SYNC_COURSE_CREATE_DELETE', 0x006);

/**
* prints a report to a log stream and output ir also to screen if required
*
*/
function enrol_sync_report(&$report, $message){

	if (empty($report)) $report = '';
	mtrace($message);
	$report .= $message."\n";
}

/**
* Check a CSV input line format for empty or commented lines
* Ensures compatbility to UTF-8 BOM or unBOM formats
*/
function sync_is_empty_line_or_format(&$text, $resetfirst = false){
	global $CFG;
	
	static $textlib;
	static $first = true;
		
	// we may have a risk the BOM is present on first line
	if ($resetfirst) $first = true;	
	if (!isset($textlib)) $textlib = new textlib(); // singleton
	if ($first && $CFG->sync_encoding == 'UTF-8'){
		$text = $textlib->trim_utf8_bom($text);					
		$first = false;
	}
	
	$text = preg_replace("/\n?\r?/", '', $text);			

	if ($CFG->sync_encoding != 'UTF-8'){
		$text = utf8_encode($text);
	}
	
	return preg_match('/^$/', $text) || preg_match('/^(\(|\[|-|#|\/| )/', $text);
}

/**
* prints a remote file upload for processing form
*
*/
function sync_print_remote_tool_portlet($titlekey, $targeturl, $filefieldname, $submitlabel, $return = false){
	global $CFG, $USER;
	
	$maxuploadsize = get_max_upload_file_size();

	$str = '<fieldset>';
	$str .= '<legend><strong>'.get_string($titlekey, 'enrol_sync').'</strong></legend>';
	$str .= '<center>';
	$str .= '<form method="post" enctype="multipart/form-data" action="'.$targeturl.'">'.
		 ' <input type="hidden" name="MAX_FILE_SIZE" value="'.$maxuploadsize.'">'.
		 '<input type="hidden" name="sesskey" value="'.$USER->sesskey.'">'.
		 '<input type="file" name="'.$filefieldname.'" size="30">'.
		 ' <input type="submit" value="'.get_string($submitlabel, 'enrol_sync').'">'.
		 '</form></br>';
	$str .= '</center>';
	$str .= '</fieldset>';

	if ($return) return $str;
	echo $str;
}

/**
* prints the form for using the registered commande file (locally on server)
*
*/
function sync_print_local_tool_portlet($config, $titlekey, $targeturl, $return = false){
	global $USER, $CFG;
	
	$str = '<fieldset>';
	$str .= '<legend><strong>'.get_string($titlekey, 'enrol_sync').'</strong></legend><br/>';

	if(empty($config)){
	 	$nofilestoredstr = get_string('nofileconfigured', 'enrol_sync');
		$str .= "<center>$nofilestoredstr<br/>";
	} else {
		if(file_exists($CFG->dataroot.'/'.$config)){
			$filestoredstr = get_string('storedfile', 'enrol_sync', $config); 			
			$syncfilelocation = str_replace('sync/', '', $config);
			$str .= "<center>$filestoredstr. <a href=\"$CFG->wwwroot/enrol/sync/file.php?file=/{$syncfilelocation}&forcedownload=1\" target=\"_blank\">".get_string('getfile', 'enrol_sync')."</a><br/><br/></center>";
			$str .= '<form method="post" action="'.$targeturl.'"><center>';
			$str .= '<input type="hidden" name="sesskey" value="'.$USER->sesskey.'">';
			$str .= '<input type="hidden" name="uselocal" value="1">';
			$str .= get_string('createtextreport', 'enrol_sync');
			$str .= ' <input type="radio" name="report" value="1" checked/> '.get_string('yes').'. <input type=radio name="report" value="0"/> '.get_string('no').'<br/><br/>';
			$str .= ' <input type="submit" value="'.get_string('process', 'enrol_sync').'">';
			$str .= '</center></form>';	
		} else {
			$filenotfoundstr = get_string('filenotfound', 'enrol_sync', $config);
			$str .= "<center>$filenotfoundstr<br/><br/>";
		}
	}		 
	$str .= '</br></fieldset>';
	
	if ($return) return $str;
	echo $str;
}

function sync_print_return_button(){
	global $CFG, $OUTPUT;
	
	echo '<center>';
	echo '<hr/>';
	echo '<br/>';
	$url = $CFG->wwwroot.'/enrol/sync/sync.php';
	$options['sesskey'] = sesskey();
	$text = get_string('returntotools', 'enrol_sync');
	print_single_button($url, $options, $text);
	echo '<br/>';			 
	echo '</center>';
}

/**
* Get course and role assignations summary
* TODO : Rework for PostGre compatibility.
*/
function sync_get_all_courses(){
	global $CFG;
	
	$sql = "
		SELECT
			IF(ass.roleid IS NOT NULL , CONCAT( c.id, '_', ass.roleid ) , CONCAT( c.id, '_', '0' ) ) AS recid, 
			c.id,
			c.shortname, 
			c.fullname, 
			count( DISTINCT ass.userid ) AS people, 
			ass.rolename
		FROM
			{$CFG->prefix}course c
		LEFT JOIN
			(SELECT
			    co.instanceid,
				ra.userid, 
				r.name as rolename,
				r.id as roleid
			 FROM
				{$CFG->prefix}context co,
				{$CFG->prefix}role_assignments ra,
				{$CFG->prefix}role r
			 WHERE
				co.contextlevel = 50 AND
				co.id = ra.contextid AND
				ra.roleid = r.id) ass
		ON
			ass.instanceid = c.id
		GROUP BY
			recid
		ORDER BY
			c.shortname
	";
	
	$results = get_records_sql($sql);
	return $results;
}

/**
* Create and feeds tryback file with failed records from an origin command file
* @param string $originfilename the origin command fiale name the tryback name will be guessed from
* @param string $line the initial command line that has failed (and should be replayed after failure conditions have been fixed)
* @param mixed $header the header fields to be reproduced in the tryback file as a string, or an array of string.
*/
function sync_feed_tryback_file($originfilename, $line, $header = ''){
	global $CFG;
	
	static $TRYBACKFILE = null;
	static $ORIGINFILE = '';

	// guess the name of the tryback
	$path_parts = pathinfo($originfilename);
	$trybackfilename = $path_parts['dirname'].'/'.$path_parts['filename'].'_tryback_'.date('Ymd-Hi').'.'.$path_parts['extension'];
	
	// if changing dump, close opened
	if ($originfilename != $ORIGINFILE){
		if (!is_null($TRYBACKFILE)){
			fclose($TRYBACKFILE);
		}
		$TRYBACKFILE = fopen($trybackfilename, 'wb');
		$ORIGINFILE = $originfilename;
		if (!empty($header)){
			if (is_string($header)){
				fputs($TRYBACKFILE, $header."\n");
			} else {
				fputs($TRYBACKFILE, implode($CFG->sync_csvseparator, $header)."\n");
			}
			fputs($TRYBACKFILE, '--------------------------------------------------'."\n");
		}
	}
	
	// dumpline
	fputs($TRYBACKFILE, $line."\n");
}

?>