<?php
/*
Plugin Name: RS Gravity Forms Limited Entries
Description: Allows you to limit any Gravity Form to a single entry per user, or a set number of entries per user.
Version: 1.0.2
Author: Radley Sustaire
Author URI: https://radleysustaire.com/
GitHub Plugin URI: https://github.com/RadGH/RS-Gravity-Forms-Limited-Entries
*/

define( 'RSLE_PATH', __DIR__ );
define( 'RSLE_URL', untrailingslashit(plugin_dir_url(__FILE__)) );
define( 'RSLE_VERSION', '1.0.2' );

class RS_GF_Limited_Entries_Plugin {
	
	/**
	 * Checks that required plugins are loaded before continuing
	 *
	 * @return void
	 */
	public static function load_plugin() {
		
		// Check for required plugins
		$missing_plugins = array();
		
		if ( ! class_exists('GFAPI') ) {
			$missing_plugins[] = 'Gravity Forms';
		}
		
		if ( $missing_plugins ) {
			self::add_admin_notice( '<strong>RS Gravity Forms Limited Entries:</strong> The following plugins are required: '. implode(', ', $missing_plugins) . '.', 'error' );
			return;
		}
		
		// Load plugin files
		require_once( RSLE_PATH . '/includes/form.php' );
	}
	
	/**
	 * Adds an admin notice to the dashboard's "admin_notices" hook.
	 *
	 * @param string $message The message to display
	 * @param string $type    The type of notice: info, error, warning, or success. Default is "info"
	 * @param bool $format    Whether to format the message with wpautop()
	 *
	 * @return void
	 */
	public static function add_admin_notice( $message, $type = 'info', $format = true ) {
		add_action( 'admin_notices', function() use ( $message, $type, $format ) {
			?>
			<div class="notice notice-<?php echo $type; ?> bbearg-crm-notice">
				<?php echo $format ? wpautop($message) : $message; ?>
			</div>
			<?php
		});
	}
	
}

// Initialize the plugin
add_action( 'plugins_loaded', array('RS_GF_Limited_Entries_Plugin', 'load_plugin') );