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
	 * Handle link checking request
	 *
	 * @since 1.0.0
	 */
	public function handle_check_links(): void
	{
		// Set longer execution time for link checking
		@set_time_limit(300);

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

		// 5. Check cache
		$cache_key = $cache_manager->generate_cache_key('links', ['url' => $url]);
		$cached_result = $cache_manager->get($cache_key);

		if ($cached_result !== null) {
			$logger->log_usage('link_checker', ['url' => $url], 'success', [
				'cached' => true
			]);

			wp_send_json_success([
				'total_links' => $cached_result['total_links'],
				'broken_links' => $cached_result['broken_links'],
				'working_links' => $cached_result['working_links'],
				'links' => $cached_result['links'],
				'scan_time' => $cached_result['scan_time'],
				'cached' => true
			]);
			return;
		}

		// 6. Check links
		$link_checker = new Link_Checker();
		$result = $link_checker->check_page_links($url);

		if (!$result['success']) {
			$logger->log_usage('link_checker', ['url' => $url], 'error', [
				'error_message' => $result['error'] ?? 'Unknown error'
			]);

			wp_send_json_error([
				'message' => $result['error'] ?? 'Failed to check links',
				'code' => $result['code'] ?? 'CHECK_FAILED'
			]);
			return;
		}

		// 7. Cache the result
		$cache_data = [
			'total_links' => $result['total_links'],
			'broken_links' => $result['broken_links'],
			'working_links' => $result['working_links'],
			'links' => $result['links'],
			'scan_time' => $result['scan_time']
		];

		$cache_manager->set($cache_key, $cache_data);

		// 8. Log success
		$logger->log_usage('link_checker', ['url' => $url], 'success', [
			'cached' => false,
			'links_checked' => $result['total_links']
		]);

		// 9. Return response
		wp_send_json_success([
			'total_links' => $result['total_links'],
			'broken_links' => $result['broken_links'],
			'working_links' => $result['working_links'],
			'links' => $result['links'],
			'scan_time' => $result['scan_time'],
			'cached' => false
		]);
	}
}
