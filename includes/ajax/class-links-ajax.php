<?php

/**
 * Broken Link Checker AJAX Handler
 *
 * Handles AJAX requests for broken link checking.
 *
 * @package    SEO_Marketing_Tools
 * @subpackage Ajax
 * @since      1.0.0
 */

namespace SEO_Marketing_Tools\Ajax;

use SEO_Marketing_Tools\Database\Rate_Limiter;
use SEO_Marketing_Tools\Utils\Validator;
use SEO_Marketing_Tools\Utils\Cache_Manager;
use SEO_Marketing_Tools\Utils\Logger;
use SEO_Marketing_Tools\Services\Link_Checker;

if (!defined('ABSPATH')) {
	exit;
}

class Links_Ajax
{

	/**
	 * Handle link checking request (full website crawl)
	 *
	 * @since 1.0.0
	 */
	public function handle_check_links(): void
	{
		// Set longer execution time for full website crawling
		@set_time_limit(1800); // 30 minutes

		// 1. Verify nonce
		if (!check_ajax_referer('seo_links_nonce', 'nonce', false)) {
			wp_send_json_error([
				'message' => 'Security verification failed',
				'code' => 'INVALID_NONCE'
			]);
			return;
		}

		$validator = new Validator();
		$rate_limiter = new Rate_Limiter();
		$cache_manager = new Cache_Manager();
		$logger = new Logger();

		// 2. Verify reCAPTCHA
		$recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
		$recaptcha_secret = get_option('seo_tools_recaptcha_secret_key', '');

		if (!empty($recaptcha_secret)) {
			$captcha_result = $validator->verify_recaptcha(
				$recaptcha_response,
				$recaptcha_secret,
				'link_checker'
			);

			if (!$captcha_result['valid']) {
				wp_send_json_error([
					'message' => 'reCAPTCHA verification failed',
					'code' => 'CAPTCHA_FAILED'
				]);
				return;
			}
		}

		// 3. Check rate limiting
		if (!$rate_limiter->should_bypass_rate_limit()) {
			$rate_check = $rate_limiter->check_rate_limit('link_checker');

			if (!$rate_check['allowed']) {
				$logger->log_usage('link_checker', $_POST, 'rate_limited', []);

				wp_send_json_error([
					'message' => sprintf(
						'Daily limit reached. Try again in %s.',
						$rate_check['reset_time']
					),
					'code' => 'RATE_LIMIT_EXCEEDED',
					'remaining' => $rate_check['remaining'],
					'reset_time' => $rate_check['reset_time']
				]);
				return;
			}
		}

		// 4. Validate URL
		$url = $_POST['url'] ?? '';
		$url_validation = $validator->validate_url($url);

		if (!$url_validation['valid']) {
			wp_send_json_error([
				'message' => 'Invalid URL: ' . implode(', ', $url_validation['errors']),
				'code' => 'INVALID_URL',
				'errors' => $url_validation['errors']
			]);
			return;
		}

		$url = $url_validation['url'];

		// 5. Generate unique job ID
		$job_id = uniqid('scan_', true);

		// 6. Return job_id immediately (don't wait for crawl to complete)
		wp_send_json_success([
			'job_id' => $job_id,
			'message' => 'Scan started'
		]);

		// 7. Start crawl in background (after response sent)
		// Use fastcgi_finish_request if available to continue processing after response
		if (function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
		}

		// Start the crawl
		$link_checker = new Link_Checker();
		$result = $link_checker->crawl_website($url, $job_id);

		// Store final result in transient for retrieval
		$transient_key = 'seo_scan_result_' . $job_id;

		if ($result['success']) {
			// Cache the result
			$cache_key = $cache_manager->generate_cache_key('links', ['url' => $url]);
			$cache_data = [
				'total_links_checked' => $result['total_links_checked'],
				'broken_links_count' => $result['broken_links_count'],
				'working_links' => $result['working_links'],
				'broken_links' => $result['broken_links'],
				'pages_crawled' => $result['pages_crawled'],
				'scan_time' => $result['scan_time']
			];

			$cache_manager->set($cache_key, $cache_data);

			// Store final result
			set_transient($transient_key, $result, 3600);

			// Log success
			$logger->log_usage('link_checker', ['url' => $url], 'success', [
				'pages_crawled' => $result['pages_crawled'],
				'links_checked' => $result['total_links_checked'],
				'broken_found' => $result['broken_links_count']
			]);
		} else {
			// Store error result
			set_transient($transient_key, $result, 3600);

			$logger->log_usage('link_checker', ['url' => $url], 'error', [
				'error_message' => $result['error'] ?? 'Unknown error'
			]);
		}
	}

	/**
	 * Get scan progress
	 *
	 * @since 1.0.0
	 */
	public function handle_get_scan_progress(): void
	{
		// Verify nonce
		if (!check_ajax_referer('seo_links_nonce', 'nonce', false)) {
			wp_send_json_error([
				'message' => 'Security verification failed',
				'code' => 'INVALID_NONCE'
			]);
			return;
		}

		$job_id = $_POST['job_id'] ?? '';

		if (empty($job_id)) {
			wp_send_json_error([
				'message' => 'Job ID is required',
				'code' => 'MISSING_JOB_ID'
			]);
			return;
		}

		$link_checker = new Link_Checker();
		$progress = $link_checker->get_scan_progress($job_id);

		if ($progress === false) {
			wp_send_json_error([
				'message' => 'Scan not found',
				'code' => 'SCAN_NOT_FOUND'
			]);
			return;
		}

		wp_send_json_success([
			'status' => $progress['status'] ?? 'scanning',
			'pages_crawled' => $progress['pages_crawled'] ?? 0,
			'links_checked' => $progress['links_checked'] ?? 0,
			'broken_links_found' => $progress['broken_links_found'] ?? 0,
			'elapsed_time' => $progress['elapsed_time'] ?? 0
		]);
	}

	/**
	 * Cancel scan
	 *
	 * @since 1.0.0
	 */
	public function handle_cancel_scan(): void
	{
		// Verify nonce
		if (!check_ajax_referer('seo_links_nonce', 'nonce', false)) {
			wp_send_json_error([
				'message' => 'Security verification failed',
				'code' => 'INVALID_NONCE'
			]);
			return;
		}

		$job_id = $_POST['job_id'] ?? '';

		if (empty($job_id)) {
			wp_send_json_error([
				'message' => 'Job ID is required',
				'code' => 'MISSING_JOB_ID'
			]);
			return;
		}

		$link_checker = new Link_Checker();
		$cancelled = $link_checker->cancel_scan($job_id);

		if ($cancelled) {
			wp_send_json_success([
				'message' => 'Scan cancelled successfully'
			]);
		} else {
			wp_send_json_error([
				'message' => 'Failed to cancel scan',
				'code' => 'CANCEL_FAILED'
			]);
		}
	}

	/**
	 * Get final scan results
	 *
	 * @since 1.0.0
	 */
	public function handle_get_scan_results(): void
	{
		// Verify nonce
		if (!check_ajax_referer('seo_links_nonce', 'nonce', false)) {
			wp_send_json_error([
				'message' => 'Security verification failed',
				'code' => 'INVALID_NONCE'
			]);
			return;
		}

		$job_id = $_POST['job_id'] ?? '';

		if (empty($job_id)) {
			wp_send_json_error([
				'message' => 'Job ID is required',
				'code' => 'MISSING_JOB_ID'
			]);
			return;
		}

		$transient_key = 'seo_scan_result_' . $job_id;
		$result = get_transient($transient_key);

		if ($result === false) {
			wp_send_json_error([
				'message' => 'Results not found. Scan may still be in progress.',
				'code' => 'RESULTS_NOT_FOUND'
			]);
			return;
		}

		wp_send_json_success($result);
	}
}
