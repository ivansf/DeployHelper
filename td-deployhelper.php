<?php
/*
Plugin Name: Deploy Helper
Plugin URI: http://www.topdraw.com
Description: A simple deploy helper utility for moving sites from enviroments. <br/>Licensed under the <a href="http://www.fsf.org/licensing/licenses/gpl.txt">GPL</a>
Version: 0.1
Author: Ivan Soto
Author URI: http://www.topdraw.com
*/

class DeployHelper
{
	private $url_from = '';
	private $url_to = '';
	private $path_from = '';
	private $path_to = '';

	function DeployHelper()
	{

		add_action('admin_menu', array(&$this, 'td_deploy_page'));
	}

	function td_deploy_page()
	{
		add_options_page('deploy_helper', 'Deploy Helper', 'manage_options', 'deploy_helper', array(&$this, 'deploy_helper_option_page'));
		add_management_page( 'Custom Permalinks', 'Custom Permalinks', 5, __FILE__, 'custom_permalinks_options_page' );
	}

	function deploy_helper_option_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				jQuery('.show_debug').click(function(){
					jQuery('.debug').show();
					return false;
				});
			});
		</script>
		<div id="poststuff" class="wrap metabox-holder">
			<h2><a href="#" style="float:right;">Top Draw Logo</a>Deploy Helper</h2>

			<div class="postbox">
				<form method="post" action="">
					<h3><span>Instructions</span></h3>

				<div class="inside">
					<p>
						Deploy Helper helps with the tedious process of moving a site from one server to another. This is a common problem when
							working on multiple environments such as developer, staging and production.
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
					<p class="description">The test will also check for write permissions on most common paths.</p>



					<p><label><input type="checkbox" name="ignore" value="1" <?php if (@$_POST['ignore'] == 1) echo ' checked' ?>> Include server paths.</label></p>
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
		</div>
		<?php
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
		if ($path) {
			$from = $this->path_from;
			$to = $this->path_to;
		}

		$out = '';
		$out .= '<table class="wp-list-table widefat"><tr><th>Table name
			<a href="#" class="show_debug">Show debug</a>
			</th><th>Table name</th></tr>';

		$tables = $this->_get_tables();


		foreach($tables as $tbl) {
			$normal = 0;
			$serialized = 0;
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
					if (is_array($value)) { // means the data is serialized.
						$serialized++;
						$found[0] = '<div style="border: 1px solid #BBB; padding: 3px; margin: 3px; overflow: auto;
						max-height: 80px; width: 420px;">' . $this->_paint_url($from,print_r($value, true)) .  '</div>';
					} else {
						$found[0] = '<div style="border: 1px solid #555; padding: 3px; margin: 3px; overflow: auto;
						max-height: 80px; width: 420px">'.$found[0].'</div>';
						$normal++;
					}
					$output .= $found[0];
				}
			}
			if ($normal > 0 OR $serialized > 0) {
				$out .= '<tr><td><strong>' . $table . '</strong><br>';
				$out .= '<div class="debug" style="display:none;">'. $output. '</div></td><td>';
				$out .= 'In normal values: ' . $normal;
				$out .= '<br>In serialized data: ' . $serialized;
				$out .= '</td></tr>';
			}
		}
		$out .= '</table>';
		return $out;
	}


	/**
	 * @return HTML with results
	 */
	function run_fix($path = false)
	{
		$from = $this->url_from;
		$to = $this->url_to;
		if ($path) {
			$from = $this->path_from;
			$to = $this->path_to;
		}
		$out = '';
//		$results = $this->_get_options_with_url();
		$out .= '<table class="wp-list-table widefat"><tr><th>Table name
			<a href="#" class="show_debug">Show debug</a>
			</th><th>Table name</th></tr>';

		// looping through the tables
		$tables = $this->_get_tables();
		foreach($tables as $tbl) {
			$normal = 0;
			$serialized = 0;
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
					if (is_array($value)) { // means the data is serialized.
//						echo '<pre>';
//						print_r($found[0]);
//						echo '</pre>';
						//$value = json_encode($value);
						//echo addcslashes($this->url_from,"/\"");
						//$value = str_replace(addcslashes($this->url_from,"/\""), addcslashes($this->url_to,"/\""), $value);
						$value = $this->recursive_replace($from, $to, $value);
						//$value = str_replace($this->url_from, $this->url_to, $value);
						//$value = json_decode($value);
						$value = maybe_serialize($value);
//						echo '<pre>';
//						print_r($value);
//						echo '</pre>';
						$this->_update_value_in_field($table, $field[0], $found[0], $value);
					} else {
						$this->_update_value_in_field($table, $field[0], $from, $to);
					}
				}
			}
		}
		return 'Running the fix';
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