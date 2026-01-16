<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    SEO_Marketing_Tools
 * @subpackage Admin
 * @since      1.0.0
 */

namespace SEO_Marketing_Tools\Admin;

use SEO_Marketing_Tools\Utils\Logger;
use SEO_Marketing_Tools\Services\Gemini_API;
use SEO_Marketing_Tools\Utils\Cache_Manager;

if (!defined('ABSPATH')) {
	exit;
}

class Admin
{

	/**
	 * The ID of this plugin.
	 *
	 * @var string
	 */
	private string $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 */
	public function __construct(string $plugin_name, string $version)
	{
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 */
	public function enqueue_styles(): void
	{
		wp_enqueue_style(
			$this->plugin_name,
			SEO_MARKETING_TOOLS_PLUGIN_URL . 'assets/css/admin.css',
			[],
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 */
	public function enqueue_scripts(): void
	{
		wp_enqueue_script(
			$this->plugin_name,
			SEO_MARKETING_TOOLS_PLUGIN_URL . 'assets/js/admin.js',
			['jquery'],
			$this->version,
			true
		);

		wp_localize_script(
			$this->plugin_name,
			'seoToolsAdmin',
			[
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('seo_tools_admin_nonce')
			]
		);
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu(): void
	{
		add_options_page(
			'SEO Marketing Tools Settings',
			'SEO Tools',
			'manage_options',
			'seo-marketing-tools',
			[$this, 'display_settings_page']
		);
	}

	/**
	 * Display settings page.
	 */
	public function display_settings_page(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		// Get statistics
		$logger = new Logger();
		$stats = $logger->get_today_stats();
		$weekly_stats = $logger->get_usage_stats(7);

		$cache_manager = new Cache_Manager();
		$cache_stats = $cache_manager->get_stats();

		include SEO_MARKETING_TOOLS_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings(): void
	{
		// API Settings
		register_setting('seo_tools_options', 'seo_tools_gemini_api_key', [
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => ''
		]);

		register_setting('seo_tools_options', 'seo_tools_gemini_model', [
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'gemini-2.0-flash-lite'
		]);

		// reCAPTCHA Settings
		register_setting('seo_tools_options', 'seo_tools_recaptcha_site_key', [
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => ''
		]);

		register_setting('seo_tools_options', 'seo_tools_recaptcha_secret_key', [
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => ''
		]);

		register_setting('seo_tools_options', 'seo_tools_recaptcha_version', [
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'v2'
		]);

		// Rate Limiting Settings
		register_setting('seo_tools_options', 'seo_tools_rate_limit_meta', [
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 5
		]);

		register_setting('seo_tools_options', 'seo_tools_rate_limit_links', [
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 5
		]);

		register_setting('seo_tools_options', 'seo_tools_rate_limit_kw_url', [
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 20
		]);

		// Cache Settings
		register_setting('seo_tools_options', 'seo_tools_cache_duration', [
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 24 * HOUR_IN_SECONDS
		]);

		// Logging Settings
		register_setting('seo_tools_options', 'seo_tools_enable_logging', [
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'yes'
		]);

		register_setting('seo_tools_options', 'seo_tools_log_retention_days', [
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 30
		]);

		// Tool Settings
		register_setting('seo_tools_options', 'seo_tools_max_links_check', [
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 50
		]);

		register_setting('seo_tools_options', 'seo_tools_link_timeout', [
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 5
		]);
	}

	/**
	 * Add settings link on plugin page.
	 *
	 * @param array $links Existing links
	 * @return array Modified links
	 */
	public function add_settings_link(array $links): array
	{
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url('options-general.php?page=seo-marketing-tools'),
			__('Settings', 'seo-marketing-tools')
		);

		array_unshift($links, $settings_link);

		return $links;
	}
}
