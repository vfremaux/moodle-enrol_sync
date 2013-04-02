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
	
	function sync_scan_empty_categories($parentcatid, &$scannedids, &$path){
		global $CFG;

		// get my subs
		$sql = "
			SELECT DISTINCT
				cc.id,
				cc.parent,
				cc.name,
				count(c.id) as courses
			FROM
				{$CFG->prefix}course_categories cc
			LEFT JOIN
				{$CFG->prefix}course c
			ON 
				cc.id = c.category
			WHERE 
				cc.parent = $parentcatid
			GROUP BY 
				cc.id
		";
		$cats = get_records_sql($sql);
		
		if ($parentcatid != 0){
			$countcourses = count_records('course', 'category', $parentcatid);
		} else {
			$countcourses = 0;
		}

		if (!empty($cats)){			
			foreach($cats as $ec){

				$mempath = $path;
				$path .= ' / '.$ec->name;
				$subcountcourses = sync_scan_empty_categories($ec->id, $scannedids, $path);
				$path = $mempath;

				if ($subcountcourses == 0){
					// this is a really empty cat
					
					echo "<tr><td align=\"left\"><b>{$ec->name}</b></td><td align=\"left\">$path</td></tr>";
					$scannedids[] = $ec->id;
				}
				$countcourses += $subcountcourses;
			}
		}
		return $countcourses;
	}
?>