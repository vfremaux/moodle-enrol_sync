<?php	   

/**
* @author Funck Thibaut
* @package enrol
* @subpackage sync
*/

	require_once '../../../config.php';
	require_once $CFG->dirroot.'/course/lib.php';
	require_once $CFG->libdir.'/adminlib.php';	
	require_once $CFG->dirroot.'/enrol/sync/courses/lib.php';
	require_once $CFG->dirroot.'/enrol/sync/lib.php';

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

	require_js($CFG->wwwroot.'/enrol/sync/courses/js.js');

	$selection = optional_param('selection', '', PARAM_TEXT);
	if($selection) {
		create_course_deletion_file($selection);
	}
	
	$navlinks[] = array(
			'name' => get_string('synchronization', 'enrol_sync'),
			'link' => $CFG->wwwroot.'/enrol/sync/sync.php',
			'type' => 'url'
		);
	$navlinks[] = array(
			'name' => get_string('builddeletefile', 'enrol_sync'),
			'url' => null,
			'type' => 'title'
		);

	print_header("$site->shortname", $site->fullname, build_navigation($navlinks));	

	print_heading(get_string('deletefilebuilder', 'enrol_sync'));
	
?>

<form name="form_exemple" method="post" action="#" onSubmit="return select_all(this)">
<center>
<table class="generaltable" width="80%">
	<tr class="r0" valign="top">
		<th class="header c0" align="left">	
			<?php echo get_string('shortname'); ?>
		</th>
		<th class="header c1" align="left">
			<?php echo get_string('fullname'); ?>
		</th>
		<th class="header c2" align="left" colspan="5">
			<?php echo get_string('roles'); ?>
		</th>
	</tr>
	<?php
	$courses = sync_get_all_courses();	
	$class = 'r0' ;
	foreach ($courses as $c){
		$class = ($class == 'r0') ? 'r1' : 'r0' ;
		if (@$prevc->shortname != $c->shortname){
			echo '</tr>';
			echo '<tr valign="top" class="'.$class.'">';
			echo '<td align="left" class="c0">'.$c->shortname .'</td><td align="left" class="c1"> '.$c->fullname .'</td>';
		} else {
			echo "<td>$c->rolename : $c->people</td>";
		}
		$prevc = $c;
	}
	?>
</table>
<hr width="90%"/>
<table width="90%">
	<tr valign="top">
		<td>
		</td>
		<td align="center">
			<?php print_string('choosecoursetodelete', 'enrol_sync') ?>
		</td>
		<td align="center">
			<?php print_string('selecteditems', 'enrol_sync') ?>
		</td>
	</tr>
	<tr valign="top">
		<td align="center">
			<select style="height:200px" name="courselist" multiple OnDblClick="javascript:selectcourses(this.form.courselist,this.form.selection)" >
				<?php 
				foreach ($courses as $course){
					echo '<option value="'.$course->shortname.'">'.$course->fullname.'</option>';
				}
				?>
			</select>
		</td>
		<td align="center">
			<table>
				<tr valign="top">
					<td>
						<input class="button" type="button" name="select" value=" >> " OnClick="javascript:selectcourses(this.form.courselist,this.form.selection)">
					</td>
				</tr>
				<tr>
					<td>
						<input class="button" type="button" name="deselect" value=" << " OnClick="javascript:selectcourses(this.form.selection,this.form.courselist)">
					</td>
				</tr>
			</table>
		</td>
		<td align="center">
			<select name="selection" multiple  style="height:200px" OnDblClick="javascript:selectcourses(this.form.selection,this.form.courselist)"></select>
		</td>
	</tr>
</table>
<p><input type="submit" value="<?php print_string('generate', 'enrol_sync') ?>"/></p>
<hr/>
<input type="button" value="<?php print_string('returntotools', 'enrol_sync') ?>" onclick="document.location.href='<?php echo $CFG->wwwroot; ?>/enrol/sync/sync.php?sesskey=<?php echo $USER->sesskey ?>'" />
</center>
</form>

<?php
print_footer();
?>