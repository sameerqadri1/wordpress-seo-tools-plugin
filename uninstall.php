<?php

/**
 * Uninstall Script
 *
 * Fired when the plugin is uninstalled.
 * Cleans up all plugin data from the database.
 *
 * @package SEO_Marketing_Tools
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Load the database class
require_once plugin_dir_path(__FILE__) . 'includes/database/class-database.php';

/**
 * Clean up database tables
 */
function seo_tools_uninstall_cleanup()
{
	global $wpdb;

	// Drop custom tables
	$database = new SEO_Marketing_Tools\Database\Database();
	$database->drop_tables();

	// Delete all plugin options
	$options = [
		'seo_tools_version',
		'seo_tools_db_version',
		'seo_tools_gemini_api_key',
		'seo_tools_gemini_model',
		'seo_tools_recaptcha_site_key',
		'seo_tools_recaptcha_secret_key',
		'seo_tools_recaptcha_version',
		'seo_tools_rate_limit_meta',
		'seo_tools_rate_limit_links',
		'seo_tools_rate_limit_kw_url',
		'seo_tools_cache_duration',
		'seo_tools_enable_logging',
		'seo_tools_log_retention_days',
		'seo_tools_max_links_check',
		'seo_tools_link_timeout'
	];

	foreach ($options as $option) {
		delete_option($option);
	}

	// Delete all transients (cache)
	$wpdb->query(
		"DELETE FROM $wpdb->options 
        WHERE option_name LIKE '_transient_seo_%' 
        OR option_name LIKE '_transient_timeout_seo_%'"
	);

	// Clear scheduled cron events
	$timestamp = wp_next_scheduled('seo_tools_daily_cleanup');
	if ($timestamp) {
		wp_unschedule_event($timestamp, 'seo_tools_daily_cleanup');
	}
}

// Run cleanup
seo_tools_uninstall_cleanup();
