<?php

	function create_course_deletion_file($selection){	
		global $CFG;
		
		$filename = $CFG->dataroot.'/sync/deletecourses.txt';
		$file = fopen($filename, 'wb');		
		$size = count($selection);
		
		for($i = 0 ; $i < $size - 1 ; $i++){
			fputs($file, "$selection[$i]");
			fputs($file, "\n");
		}		
		$size = $size - 1;
		fputs($file, "$selection[$size]");
		fclose($file);
	}
	
?>