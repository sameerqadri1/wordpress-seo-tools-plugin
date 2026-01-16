<?php

/**
 * Admin AJAX Handler
 *
 * Handles AJAX requests from admin panel.
 *
 * @package    SEO_Marketing_Tools
 * @subpackage Ajax
 * @since      1.0.0
 */

namespace SEO_Marketing_Tools\Ajax;

use SEO_Marketing_Tools\Services\Gemini_API;
use SEO_Marketing_Tools\Utils\Cache_Manager;
use SEO_Marketing_Tools\Utils\Logger;

if (!defined('ABSPATH')) {
	exit;
}

class Admin_Ajax
{

	/**
	 * Constructor - Register admin AJAX handlers
	 */
	public function __construct()
	{
		add_action('wp_ajax_seo_test_api_key', [$this, 'handle_test_api_key']);
		add_action('wp_ajax_seo_clear_cache', [$this, 'handle_clear_cache']);
		add_action('wp_ajax_seo_export_logs', [$this, 'handle_export_logs']);
	}

	/**
	 * Test Gemini API key
	 *
	 * @since 1.0.0
	 */
	public function handle_test_api_key(): void
	{
		// Verify nonce
		if (!check_ajax_referer('seo_tools_admin_nonce', 'nonce', false)) {
			wp_send_json_error(['message' => 'Invalid nonce']);
			return;
		}

		// Check permissions
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized']);
			return;
		}

		$api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

		if (empty($api_key)) {
			wp_send_json_error(['message' => 'API key is required']);
			return;
		}

		// Test the API
		$gemini_api = new Gemini_API($api_key);
		$result = $gemini_api->test_connection();

		if ($result['success']) {
			wp_send_json_success(['message' => $result['message']]);
		} else {
			wp_send_json_error(['message' => $result['message']]);
		}
	}

	/**
	 * Clear all caches
	 *
	 * @since 1.0.0
	 */
	public function handle_clear_cache(): void
	{
		// Verify nonce
		if (!check_ajax_referer('seo_tools_admin_nonce', 'nonce', false)) {
			wp_send_json_error(['message' => 'Invalid nonce']);
			return;
		}

		// Check permissions
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized']);
			return;
		}

		$cache_manager = new Cache_Manager();
		$count = $cache_manager->clear_all();

		wp_send_json_success([
			'message' => sprintf('Cleared %d cache items successfully', $count)
		]);
	}

	/**
	 * Export logs as CSV
	 *
	 * @since 1.0.0
	 */
	public function handle_export_logs(): void
	{
		// Verify nonce
		if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'seo_tools_admin_nonce')) {
			wp_die('Invalid nonce');
		}

		// Check permissions
		if (!current_user_can('manage_options')) {
			wp_die('Unauthorized');
		}

		$logger = new Logger();
		$csv = $logger->export_logs_csv(30);

		// Set headers for CSV download
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="seo-tools-logs-' . date('Y-m-d') . '.csv"');
		header('Pragma: no-cache');
		header('Expires: 0');

		echo $csv;
		exit;
	}
}

// Initialize admin AJAX handlers
new Admin_Ajax();
