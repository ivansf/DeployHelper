<?php
/*
Plugin Name: Deploy Helper
Plugin URI: http://www.topdraw.com
Description: A simple deploy helper utility for moving sites from enviroments. <br/>Licensed under the <a href="http://www.fsf.org/licensing/licenses/gpl.txt">GPL</a>
Version: 0.3
Author: Top Draw Inc.
Author URI: http://www.topdraw.com
*/

class DeployHelper
{
	private $url_from = '';
	private $url_to = '';
	private $path_from = '';
	private $path_to = '';
	private $path = '';

	function DeployHelper()
	{
		add_action('admin_menu', array(&$this, 'td_deploy_page'));
		$this->path = WP_PLUGIN_URL . '/' . str_replace(basename(__FILE__), "", plugin_basename(__FILE__));
	}

	function td_deploy_page()
	{
		set_time_limit(120);
		$deploy_helper_page = add_options_page('deploy_helper', 'Deploy Helper', 'manage_options', 'deploy_helper', array(&$this, 'deploy_helper_option_page'));
		add_action('admin_print_styles-'.$deploy_helper_page, array(&$this, 'init_plugin'));
		add_management_page( 'Custom Permalinks', 'Custom Permalinks', 5, __FILE__, 'custom_permalinks_options_page' );
	}

	function deploy_helper_option_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}
		//wp_enqueue_script( 'my_awesome_script', '/script.js', array( 'jquery' ));

		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				jQuery('.show_debug').toggle(function(){
					jQuery('.debug').show();
					return false;
				}, function(){
					jQuery('.debug').hide();
					return false;
				});
			});
		</script>



		<div id="poststuff" class="wrap metabox-holder">
			<h2>Deploy Helper</h2>

			<div class="postbox">
				<h3><span>Information</span></h3>

				<div class="inside">
<!--					<p class="description">The following is some useful information.</p>-->
					<table class="wp-list-table widefat" id="site-stats">
						<tr>
							<th width="30%">Test</th>
							<th width="70%">Status</th>
						</tr>
						<?php $uploaddir = wp_upload_dir();	?>
						<tr><td>Upload path:</td><td><strong><?php echo $uploaddir['basedir'] ?></strong></td></tr>
						<tr><td>Upload path writable:</td><td><?php echo is_writable($uploaddir['basedir'])? '<span class="green">Writable</span>': '<span class="red">Not writable</span>'; ?></td></tr>
						<tr><td>.htaccess exists:</td><td><?php echo file_exists(ABSPATH . '.htaccess')? '<span class="green">File Exists</span>': '<span class="red">Not found</span>'; ?></td></tr>
						<tr><td>.htaccess writable:</td><td><?php echo is_writable(ABSPATH . '.htaccess')? '<span class="green">Writable</span>': '<span class="red">Not writable</span>'; ?></td></tr>
					</table>
				</div>
			</div>


			<div class="postbox">
				<form method="post" action="">
					<h3><span>Fix paths and URLs</span></h3>
				<div class="inside">
					<p>
						<?php if (ini_get('safe_mode') && (ini_get('max_execution_time') < 45)): ?>
						<span class="red">Warning: You are running PHP in safe mode and the current execution time is
							<?php echo ini_get('max_execution_time') ?> seconds. You may get timeouts when running a database fix on a large amount of posts.</span>
						<?php endif; ?>
					</p>
					<p>
						You can fix previous environment paths and urls by using this tool. Running a check first will give you a quick
						report before you run a full database fix.
					</p>

					<p>
						<strong>Warning</strong>: It is strongly recommended to make a database backup before using this tool.
					</p>
					<table class="wp-list-table widefat ">
						<tr>
							<th width="10%">Option</th>
							<th width="40%">From</th><th width="40%">To</th>
						</tr>
						<tr>
							<td><label>siteurl: </label></td>
							<td><input type="text" name="url_from" style="width: 100%;"
									   value="<?php echo @$_POST['url_from'] ? @$_POST['url_from'] : '<replace>' ?>" /></td>
							<td><input type="text" name="url_to" style="width: 100%;"
									   value="<?php echo @$_POST['url_to'] ? @$_POST['url_to'] : get_option('siteurl') ?>" />
							</td>
						</tr>
						<tr>
							<td><label>Server Path: </label></td>
							<td><input type="text" name="path_from" style="width: 100%;"
									value="<?php echo @$_POST['path_from'] ? @$_POST['path_from'] : '/var/...' ?>"/>
							<p class="description">Paste in the server path where the site previously was. You may have to check the options table.</p></td>
							<td><input type="text" name="path_to" style="width: 100%;"
									value="<?php echo @$_POST['path_to'] ? @$_POST['path_to'] : '/var/...' ?>"/>
							<p class="description">Paste in the server path the site is currently in.</p></td>
						</tr>
					</table>

					<p><label><input type="checkbox" name="ignore" value="1" <?php if (@$_POST['ignore'] == 1) echo ' checked' ?>> Include server paths.</label></p>
					<p><label><input type="checkbox" name="details" value="1" <?php if (@$_POST['details'] == 1) echo ' checked' ?>> Show detailed info.</label></p>
					<input type="submit" name="submit" class="button button-primary" value="Run check" />
					<input type="submit" name="submit" class="button" value="Fix" />
				</div>
				</form>

			</div>
		<?php

		if (isset($_POST['submit'])) {
			$this->url_from = $_POST['url_from'];
			$this->url_to = $_POST['url_to'];
			$this->path_from = $_POST['path_from'];
			$this->path_to = $_POST['path_to'];
		}
		if (isset($_POST['submit']) && $_POST['submit'] === 'Run check') {
			echo $_POST['details'] ? '<div class="debug_top">'.
				'<a href="#" class="show_debug button" style="margin-left: 10px;">Show Detailed Info</a></div>' : ' ';
			echo $this->run_check();
			if (isset($_POST['ignore']) && ($_POST['ignore'] == 1)) {
				echo '<hr >';
				echo $this->run_check(true);
			}
		} else if (isset($_POST['submit']) && $_POST['submit'] === 'Fix') {
			echo $this->run_fix();
			if (isset($_POST['ignore']) && ($_POST['ignore'] == 1)) {
				echo '<hr >';
				echo $this->run_fix(true);
			}
		}
		?>
			<div class="credits">Developed by <a href="http://www.topdraw.com/" title="Top Draw" target="_blank">Top Draw, Inc</a></div>
		</div>
	<?php
	}

	/**
	 * Initializes the plugin.
	 * 
	 * @return void
	 */
	function init_plugin() {
		wp_enqueue_style('td-style', $this->path . 'style.css');
	}

	/**
	 * Runs a check to find the old server path and site url.
	 *
	 * @return string html output;
	 */
	function run_check($path = false)
	{
		$from = $this->url_from;
		$to = $this->url_to;
		$name = 'URL Results';
		if ($path) {
			$from = $this->path_from;
			$to = $this->path_to;
			$name = 'Path Results';
		}


		$out = '';
		$out .= '<table id="results-table" class="wp-list-table widefat"><tr><th>'. $name .	'</th></tr>';

		$tables = $this->_get_tables();


		foreach($tables as $tbl) {
			$normal = 0;
			$serialized = 0;
			$output = '';
			$table = $tbl[0];
			$fields = $this->_get_table_fields($table);

			// looping through the fields in a table
			foreach($fields as $field) {
				// $field[0] contains the field name    - $fields[0][0] contains the first column name from that table.
				$array = $this->_check_fields_in_table($table, $field[0], $from);
				foreach ($array as $found) {
					$value = maybe_unserialize($found[0]);
					if (is_serialized($found[0])) { // means the data is serialized.
						$serialized++;
						$output .= '<div class="serialized_label">' . $field[0] . '</div>'; //  .
						$found[0] = '<div class="serialized_debug">' .
									$this->_paint_url($from,print_r($value, true)) .  '</div>';
					} else {
						$output .= '<div class="normal_label">' . $field[0] . '</div>'; //  .
						$found[0] = '<div class="normal_debug">'. htmlentities($found[0]).'</div>';
						$normal++;
					}
					$output .= $found[0];
				}
			}

			if ($normal > 0 OR $serialized > 0) {
				$out .= '<tr><td><h3>' . $table . '</h3>';
				$out .= '<strong class="normal">In normal values: </strong>' . $normal;
				$out .= '<br><strong class="serialized">In serialized data: </strong>' . $serialized;
				$out .= '</td></tr>';
				$out .= '<tr><td><div class="debug" style="display:none;">' . ($_POST['details'] ? $output : ' ') . '</div></td></tr>';
			}
		}
		$out .= '</table>';
		return $out;
	}

	/**
	 * Same as run_check() but it will attempt to fix urls
	 * and paths (if checked).
	 *
	 * @param bool $path
	 * @return string
	 */
	function run_fix($path = false)
	{
		$from = $this->url_from;
		$to = $this->url_to;
		if ($path) {
			$from = $this->path_from;
			$to = $this->path_to;
		}
		$normal = 0;
		$serialized = 0;
		$out = '';
//		$results = $this->_get_options_with_url();
		$out .= '<table class="wp-list-table widefat"><tr><th>Results
			</th><th>Table name</th></tr>';

		// looping through the tables
		$tables = $this->_get_tables();
		foreach($tables as $tbl) {
			$output = '';
			$table = $tbl[0];
			$fields = $this->_get_table_fields($table);
			// looping through the fields in a table
			foreach($fields as $field) {
				// $field[0] contains the field name
				$array = $this->_check_fields_in_table($table, $field[0], $from);
				foreach ($array as $found) {
					$output .= $field[0];

					$value = maybe_unserialize($found[0]);
					if (is_serialized($found[0])) { // means the data is serialized.
						$value = $this->recursive_replace($from, $to, $value);
						$value = maybe_serialize($value);
						$this->_update_value_in_field($table, $field[0], $found[0], $value);
						$serialized++;
					} else {
						$this->_update_value_in_field($table, $field[0], $from, $to);
						$normal++;
					}
				}
			}
		}
		$out .= '<tr><td>Normal values replaced: </td><td>'.$normal.'</td></tr>';
		$out .= '<tr><td>Serialized values replaced: </td><td>'.$serialized.'</td></tr>';
		return $out;
	}

	function _get_options() {
		
	}

	private function _check_fields_in_table($table, $field, $from)
	{
		global $wpdb;
		$query = "SELECT `$field` FROM $table WHERE `$field` LIKE \"%$from%\"";
		//echo $query;
		$results = $wpdb->get_results($query, ARRAY_N);
		return $results;
	}

	private function _get_tables()
	{
		global $wpdb;
		$query = "show tables";
		$results = $wpdb->get_results($query, ARRAY_N);
		return $results;
	}

	private function _get_table_fields($table)
	{
		global $wpdb;
		$query = "SHOW COLUMNS FROM `$table`";
		//echo $query;
		$results = $wpdb->get_results($query, ARRAY_N);
		return $results;
		echo '<pre>';
		echo print_r($results, false);
		echo '</pre>';
	}

	private function _update_value_in_field($table, $field, $from, $to)
	{
		global $wpdb;
		$query = "UPDATE $table
				SET $field = REPLACE($field, '$from', '$to')
				WHERE $field LIKE '%$from%'";
		$wpdb->query($query);
		//echo $query;
	}


	function _paint_url($url, $data)
	{
		$data = str_replace($url, '<span style="color:red">'.$url.'</span>', $data);
		return $data;
	}


	/**
	 * Recursively goes through an array and replaces the string with the given values.
	 *
	 * It will detect numeric values and turn them into int so the re-serialization stays consistent.
	 *
	 * @param $from - to replace
	 * @param $to - to replace with
	 * @param $value - given value, could be an array or string.
	 * @return array|int|mixed
	 */
	function recursive_replace($from, $to, $value)
	{
		if (is_array($value)) {
			foreach ($value as $key => $elem) {
				$value[$key] = $this->recursive_replace($from, $to, $elem);
			}
		} elseif (is_numeric($value)) {
			$value = absint($value);
		} else {
			$value = str_replace($from, $to, $value);
		}
		return $value;
	}

/*	function my_action_javascript()
	{
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				var data = {
					action: 'my_action',
					whatever: 1234
				};
				// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
				jQuery.post(ajaxurl, data, function(response) {
					alert('Got this from the server: ' + response);
				});

				jQuery('#show_debug').click(function(){
					jQuery('.debug').show();
				});
			});
		</script>
	<?php
	}*/

}

$dh = new DeployHelper();