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
	private $text_to_replace = 'http://wptest.local';

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
				jQuery('#show_debug').click(function(){
					jQuery('.debug').show();
				});
			});
		</script>
		<div id="poststuff" class="wrap metabox-holder">
			<h2>Deploy Helper</h2>

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
							<td><input type="text" style="width: 100%;" value="" /></td>
							<td><input type="text" style="width: 100%;" value="<?php echo get_option('siteurl') ?>" />
							</td>
						</tr>
						<tr>
							<td><label>Server Path: </label></td>
							<td><input type="text" style="width: 100%;" value="/var/..." />
							<p class="description">Paste in the server path where the site previously was. You may have to check the options table.</p></td>
							<td><input type="text" style="width: 100%;" value="/var/..." />
							<p class="description">Paste in the server path the site is currently in.</p></td>
						</tr>
					</table>
					<p class="description">The test will also check for write permissions on most common paths.</p>

					<p><label><input type="checkbox" name="ignore" checked="checked"> Ignore the server path.</label></p>
					<input type="submit" name="submit" class="button button-primary" value="Run check" />
					<input type="submit" name="submit" class="button" value="Fix" />
				</div>
				</form>
			</div>

		<?php
		// Runing the test?
		//print_r($_POST);
		if (isset($_POST['submit']) && $_POST['submit'] === 'Run check') {
			echo $this->run_check();
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
	function run_check() {
		$out = '';
		$results = $this->_get_options_with_url();
		$out .= '<table class="wp-list-table widefat"><tr><th>Table name
			<a href="#" id="show_debug">Show debug</a>
			</th><th>Table name</th></tr>';

		//print_r($this->_get_tables());
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
				$array = $this->_check_fields_in_table($table, $field[0]);
				foreach ($array as $found) {
					$output .= $field[0];
					$value = @unserialize($found[0]);
					if ($value) { // means the data is serialized.
						$serialized++;
						$found[0] = '<div style="border: 1px solid #BBB; padding: 3px; margin: 3px; overflow: auto;
						max-height: 80px; width: 420px;">' . $this->_paint_url($this->text_to_replace,print_r($value, true)) .  '</div>';
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

	function _get_options() {
		
	}

	function _check_fields_in_table($table, $field) {
		global $wpdb;
		$query = "SELECT `$field` FROM $table WHERE `$field` LIKE \"%$this->text_to_replace%\"";
		//echo $query;
		$results = $wpdb->get_results($query, ARRAY_N);
		return $results;
	}

	function _get_options_with_url() {
		global $wpdb;
		$query = "SELECT * FROM $wpdb->options WHERE option_value LIKE \"%$this->text_to_replace%\"";
		$results = $wpdb->get_results($query, OBJECT);
		return $results;
	}

	function _get_tables() {
		global $wpdb;
		$query = "show tables";
		$results = $wpdb->get_results($query, ARRAY_N);
		return $results;
	}

	function _get_table_fields($table) {
		global $wpdb;
		$query = "SHOW COLUMNS FROM `$table`";
		//echo $query;
		$results = $wpdb->get_results($query, ARRAY_N);
		return $results;
		echo '<pre>';
		echo print_r($results, false);
		echo '</pre>';
	}


	function _paint_url($url, $data){
		$data = str_replace($url, '<span style="color:red">'.$url.'</span>', $data);
		return $data;
	}

	function my_action_javascript()
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
	}



}

$dh = new DeployHelper();