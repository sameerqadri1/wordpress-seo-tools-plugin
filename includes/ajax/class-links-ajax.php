<?php

/**
 * Broken Link Checker AJAX Handler - Simplified
 *
 * Handles AJAX requests for broken link checking with synchronous processing.
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
use SEO_Marketing_Tools\Utils\Scan_Lock_Manager;
use SEO_Marketing_Tools\Services\Link_Checker;

if (!defined('ABSPATH')) {
	exit;
}

class Links_Ajax
{

	/**
	 * Handle link checking request (synchronous with chunking)
	 *
	 * @since 1.0.0
	 */
	public function handle_check_links(): void
	{
		// Set longer execution time for website crawling
		@set_time_limit(300); // 5 minutes per chunk

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
		$scan_lock_manager = new Scan_Lock_Manager();

		// 2. Get and validate scan mode
		$scan_mode = $_POST['scan_mode'] ?? '';

		if (!in_array($scan_mode, ['quick', 'full'], true)) {
			wp_send_json_error([
				'message' => 'Please select a scan mode',
				'code' => 'MISSING_SCAN_MODE'
			]);
			return;
		}

		// 3. Validate URL
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

		// 4. Check if this is a continuation or new scan (full mode only)
		$state_key = 'seo_scan_state_' . md5($url);
		$resume_state = null;

		if ($scan_mode === 'full') {
			$resume_state = get_transient($state_key);

			// Convert false to null for strict type compatibility
			if ($resume_state === false) {
				$resume_state = null;
			}
		}

		// For new scans (not continuations), verify reCAPTCHA, rate limit, and scan locks
		if (!$resume_state) {
			// Check concurrent scan limits
			$scan_lock_check = $scan_lock_manager->can_start_scan();
			if (!$scan_lock_check['allowed']) {
				wp_send_json_error([
					'message' => $scan_lock_check['message'],
					'code' => $scan_lock_check['code']
				]);
				return;
			}

			// Verify reCAPTCHA
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

			// Check rate limiting
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

			// Acquire scan lock
			$scan_lock_manager->acquire_lock();
		} else {
			// For continuations, just update the lock activity timestamp
			$scan_lock_manager->update_lock_activity();
		}

		// 5. Run the scan based on mode
		$link_checker = new Link_Checker();

		if ($scan_mode === 'quick') {
			// Quick Scan: Single page, max 100 links
			$result = $link_checker->check_page_links($url, 100);
		} else {
			// Full Site Audit: Crawl website, 50 pages per chunk
			$result = $link_checker->crawl_website($url, 50, $resume_state);
		}

		if (!$result['success']) {
			// Delete state on error
			delete_transient($state_key);

			// Release scan lock
			$scan_lock_manager->release_lock();

			wp_send_json_error([
				'message' => $result['error'] ?? 'Scan failed',
				'code' => $result['code'] ?? 'SCAN_FAILED'
			]);
			return;
		}

		// 6. Save state if there's more to crawl (full mode only)
		if ($scan_mode === 'full' && $result['has_more']) {
			set_transient($state_key, $result['state'], 3600); // 1 hour expiry
			// Lock remains active for continuation
		} else {
			// No more pages - delete state
			delete_transient($state_key);

			// Release scan lock (scan complete)
			$scan_lock_manager->release_lock();

			// Cache the final result
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

			// Log success
			$logger->log_usage('link_checker', ['url' => $url], 'success', [
				'pages_crawled' => $result['pages_crawled'],
				'links_checked' => $result['total_links_checked'],
				'broken_found' => $result['broken_links_count']
			]);
		}

		// 7. Return results
		wp_send_json_success([
			'scan_mode' => $scan_mode,
			'total_links_checked' => $result['total_links_checked'],
			'broken_links_count' => $result['broken_links_count'],
			'working_links' => $result['working_links'],
			'broken_links' => $result['broken_links'],
			'pages_crawled' => $result['pages_crawled'],
			'scan_time' => $result['scan_time'],
			'has_more' => $result['has_more'],
			'estimated_remaining' => $result['estimated_remaining'] ?? 0,
			'estimated_total_pages' => $result['estimated_total_pages'] ?? $result['pages_crawled']
		]);
	}

	/**
	 * Cancel scan and clear all data
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

		$url = $_POST['url'] ?? '';

		if (empty($url)) {
			wp_send_json_error([
				'message' => 'URL is required',
				'code' => 'MISSING_URL'
			]);
			return;
		}

		// Delete state transient
		$state_key = 'seo_scan_state_' . md5($url);
		delete_transient($state_key);

		// Release scan lock
		$scan_lock_manager = new Scan_Lock_Manager();
		$scan_lock_manager->release_lock();

		wp_send_json_success([
			'message' => 'Scan cancelled and data cleared'
		]);
	}
}
