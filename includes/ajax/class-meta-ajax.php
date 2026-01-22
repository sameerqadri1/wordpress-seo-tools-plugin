<?php

/**
 * Meta Generator AJAX Handler
 *
 * Handles AJAX requests for meta title/description generation.
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
use SEO_Marketing_Tools\Services\Gemini_API;
use SEO_Marketing_Tools\Services\Meta_Generator;

if (!defined('ABSPATH')) {
	exit;
}

class Meta_Ajax
{

	/**
	 * Handle meta generation request
	 *
	 * @since 1.0.0
	 */
	public function handle_generate_meta(): void
	{
		// 1. Verify nonce
		if (!check_ajax_referer('seo_meta_nonce', 'nonce', false)) {
			wp_send_json_error([
				'message' => 'Security verification failed. Please refresh the page.',
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
				'meta_generator'
			);

			if (!$captcha_result['valid']) {
				wp_send_json_error([
					'message' => 'reCAPTCHA verification failed. Please try again.',
					'code' => 'CAPTCHA_FAILED'
				]);
				return;
			}
		}

		// 3. Check global Gemini API limits (everyone, including admins)
		// 3a. Check global RPD limit (20/day)
		$rpd_check = $rate_limiter->check_global_gemini_rpd();
		if (!$rpd_check['allowed']) {
			$logger->log_usage('meta_generator', $_POST, 'global_rpd_exceeded', [
				'global_count' => $rpd_check['current_count']
			]);

			wp_send_json_error([
				'message' => $rpd_check['message'],
				'code' => 'GEMINI_RPD_EXCEEDED',
				'retry_after' => $rpd_check['retry_after'],
				'global_limit' => $rpd_check['limit'],
				'global_count' => $rpd_check['current_count']
			]);
			return;
		}

		// 3b. Check global RPM limit (10/minute)
		$rpm_check = $rate_limiter->check_global_gemini_rpm();
		if (!$rpm_check['allowed']) {
			$logger->log_usage('meta_generator', $_POST, 'global_rpm_exceeded', [
				'global_count' => $rpm_check['current_count']
			]);

			wp_send_json_error([
				'message' => $rpm_check['message'],
				'code' => 'GEMINI_RPM_EXCEEDED',
				'retry_after' => $rpm_check['retry_after'],
				'global_limit' => $rpm_check['limit'],
				'global_count' => $rpm_check['current_count']
			]);
			return;
		}

		// 3c. Estimate tokens for TPM check
		$input_data_temp = [
			'keyword' => sanitize_text_field($_POST['keyword'] ?? ''),
			'business_name' => sanitize_text_field($_POST['business_name'] ?? ''),
			'description' => sanitize_textarea_field($_POST['description'] ?? ''),
			'page_type' => sanitize_text_field($_POST['page_type'] ?? 'service')
		];

		// Rough estimate: prompt + expected response
		$estimated_prompt = strlen(json_encode($input_data_temp));
		$estimated_response = 300; // ~75 tokens for title + description
		$estimated_tokens = (int) ceil(($estimated_prompt + $estimated_response) / 4);

		// Check global TPM limit (250,000/minute)
		$tpm_check = $rate_limiter->check_global_gemini_tpm($estimated_tokens);
		if (!$tpm_check['allowed']) {
			$logger->log_usage('meta_generator', $_POST, 'global_tpm_exceeded', [
				'global_tokens' => $tpm_check['current_tokens'],
				'estimated_tokens' => $estimated_tokens
			]);

			wp_send_json_error([
				'message' => $tpm_check['message'],
				'code' => 'GEMINI_TPM_EXCEEDED',
				'retry_after' => $tpm_check['retry_after'],
				'global_limit' => $tpm_check['limit'],
				'global_tokens' => $tpm_check['current_tokens']
			]);
			return;
		}

		// 4. Check per-user rate limiting (unless admin)
		if (!$rate_limiter->should_bypass_rate_limit()) {
			$rate_check = $rate_limiter->check_rate_limit('meta_generator');

			if (!$rate_check['allowed']) {
				$logger->log_usage('meta_generator', $_POST, 'rate_limited', []);

				wp_send_json_error([
					'message' => sprintf(
						'Daily limit reached. You can generate %d more meta tags in %s.',
						$rate_check['remaining'],
						$rate_check['reset_time']
					),
					'code' => 'RATE_LIMIT_EXCEEDED',
					'remaining' => $rate_check['remaining'],
					'reset_time' => $rate_check['reset_time']
				]);
				return;
			}
		}

		// 5. Validate input
		$validation = $validator->validate_meta_input($_POST);

		if (!$validation['valid']) {
			wp_send_json_error([
				'message' => 'Invalid input: ' . implode(', ', $validation['errors']),
				'code' => 'INVALID_INPUT',
				'errors' => $validation['errors']
			]);
			return;
		}

		$input_data = $validation['data'];

		// 6. Check cache
		$cache_key = $cache_manager->generate_cache_key('meta', $input_data);
		$cached_result = $cache_manager->get($cache_key);

		if ($cached_result !== null) {
			$logger->log_usage('meta_generator', $input_data, 'success', [
				'cached' => true,
				'api_tokens_used' => 0
			]);

			wp_send_json_success([
				'title' => $cached_result['title'],
				'description' => $cached_result['description'],
				'title_length' => strlen($cached_result['title']),
				'description_length' => strlen($cached_result['description']),
				'cached' => true,
				'remaining' => $rate_check['remaining'] ?? 0,
				'reset_time' => $rate_check['reset_time'] ?? ''
			]);
			return;
		}

		// 7. Generate meta using Gemini API
		$meta_generator = new Meta_Generator();
		$result = $meta_generator->generate(
			$input_data['keyword'],
			$input_data['business_name'],
			$input_data['description'],
			$input_data['page_type']
		);

		if (!$result['success']) {
			$logger->log_usage('meta_generator', $input_data, 'error', [
				'error_message' => $result['error'] ?? 'Unknown error',
				'cached' => false
			]);

			wp_send_json_error([
				'message' => $result['error'] ?? 'Failed to generate meta tags',
				'code' => $result['code'] ?? 'GENERATION_FAILED'
			]);
			return;
		}

		// 8. Cache the result
		$cache_manager->set($cache_key, [
			'title' => $result['title'],
			'description' => $result['description']
		]);

		// 9. Log success with token usage
		$logger->log_usage('meta_generator', $input_data, 'success', [
			'cached' => false,
			'api_tokens_used' => $result['tokens_used'] ?? 0,
			'tokens_breakdown' => $result['tokens_breakdown'] ?? null,
			'global_rpm' => $rpm_check['current_count'] ?? 0,
			'global_rpd' => $rpd_check['current_count'] ?? 0,
			'global_tpm' => $tpm_check['current_tokens'] ?? 0
		]);

		// 10. Return response
		wp_send_json_success([
			'title' => $result['title'],
			'description' => $result['description'],
			'title_length' => strlen($result['title']),
			'description_length' => strlen($result['description']),
			'cached' => false,
			'tokens_used' => $result['tokens_used'] ?? 0,
			'remaining' => $rate_check['remaining'] ?? 0,
			'reset_time' => $rate_check['reset_time'] ?? '',
			'global_status' => [
				'rpd' => ['current' => $rpd_check['current_count'], 'limit' => $rpd_check['limit'], 'remaining' => $rpd_check['remaining']],
				'rpm' => ['current' => $rpm_check['current_count'], 'limit' => $rpm_check['limit'], 'remaining' => $rpm_check['remaining']],
				'tpm' => ['current' => $tpm_check['current_tokens'], 'limit' => $tpm_check['limit'], 'remaining' => $tpm_check['remaining']]
			]
		]);
	}

	/**
	 * Get rate limit status (for displaying counter)
	 *
	 * @since 1.0.0
	 */
	public function handle_get_rate_limit_status(): void
	{
		if (!check_ajax_referer('seo_meta_nonce', 'nonce', false)) {
			wp_send_json_error(['message' => 'Invalid nonce']);
			return;
		}

		$tool_name = sanitize_text_field($_POST['tool_name'] ?? 'meta_generator');
		$rate_limiter = new Rate_Limiter();

		if ($rate_limiter->should_bypass_rate_limit()) {
			wp_send_json_success([
				'unlimited' => true,
				'message' => 'Unlimited (Administrator)'
			]);
			return;
		}

		$status = $rate_limiter->get_rate_limit_status($tool_name);

		wp_send_json_success([
			'unlimited' => false,
			'remaining' => $status['remaining'],
			'limit' => $status['limit'],
			'current_count' => $status['current_count'],
			'reset_time' => $status['reset_time']
		]);
	}

	/**
	 * Fetch URL content for keyword density tool
	 *
	 * @since 1.0.0
	 */
	public function handle_fetch_url_content(): void
	{
		if (!check_ajax_referer('seo_keyword_nonce', 'nonce', false)) {
			wp_send_json_error(['message' => 'Invalid nonce']);
			return;
		}

		$validator = new Validator();
		$rate_limiter = new Rate_Limiter();
		$logger = new Logger();

		// Validate URL
		$url = $_POST['url'] ?? '';
		$url_validation = $validator->validate_url($url);

		if (!$url_validation['valid']) {
			wp_send_json_error([
				'message' => 'Invalid URL: ' . implode(', ', $url_validation['errors']),
				'errors' => $url_validation['errors']
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
				'keyword_density_url'
			);

			if (!$captcha_result['valid']) {
				wp_send_json_error([
					'message' => 'reCAPTCHA verification failed. Please try again.',
					'code' => 'CAPTCHA_FAILED'
				]);
				return;
			}
		}

		// Check if user wants to force refresh
		$force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';

		// Check if URL content is cached (only if not forcing refresh)
		if (!$force_refresh) {
			$cached_content = $rate_limiter->get_cached_url_content($url);
			if ($cached_content !== null) {
				// Calculate cache age and expiry
				$cached_timestamp = isset($cached_content['cached_timestamp']) ? $cached_content['cached_timestamp'] : null;
				$expires_at = isset($cached_content['expires_at']) ? $cached_content['expires_at'] : null;

				$age_minutes = $cached_timestamp ? floor((time() - $cached_timestamp) / 60) : null;
				$expires_minutes = $expires_at ? max(0, floor(($expires_at - time()) / 60)) : null;

				wp_send_json_success([
					'text' => $cached_content['text'],
					'word_count' => $cached_content['word_count'],
					'cached' => true,
					'cache_age_minutes' => $age_minutes,
					'cache_expires_minutes' => $expires_minutes,
					'cached_at' => $cached_content['cached_at']
				]);
				return;
			}
		}

		// Check all rate limits
		if (!$rate_limiter->should_bypass_rate_limit()) {
			// Check per-minute limit
			$minute_check = $rate_limiter->check_per_minute_limit('keyword_density_url');
			if (!$minute_check['allowed']) {
				wp_send_json_error([
					'message' => 'Too many requests. Please wait a minute and try again.',
					'code' => 'RATE_LIMIT_PER_MINUTE',
					'retry_after' => $minute_check['retry_after']
				]);
				return;
			}

			// Check concurrent limit
			$concurrent_check = $rate_limiter->check_concurrent_limit('keyword_density_url');
			if (!$concurrent_check['allowed']) {
				wp_send_json_error([
					'message' => 'Server is busy. Please try again in a moment.',
					'code' => 'CONCURRENT_LIMIT',
					'retry_after' => $concurrent_check['retry_after']
				]);
				return;
			}

			// Check daily limit
			$rate_check = $rate_limiter->check_rate_limit('keyword_density_url');
			if (!$rate_check['allowed']) {
				$rate_limiter->release_concurrent_slot('keyword_density_url');

				$logger->log_usage('keyword_density_url', $_POST, 'rate_limited', []);

				wp_send_json_error([
					'message' => 'Daily limit reached for URL fetching. Resets in ' . $rate_check['reset_time'] . '.',
					'code' => 'RATE_LIMIT_EXCEEDED',
					'reset_time' => $rate_check['reset_time']
				]);
				return;
			}
		}

		// Fetch URL content
		$response = wp_remote_get($url, [
			'timeout' => 10,
			'user-agent' => 'SEO Tools Bot/1.0',
			'sslverify' => true
		]);

		// Release concurrent slot
		$rate_limiter->release_concurrent_slot('keyword_density_url');

		if (is_wp_error($response)) {
			wp_send_json_error([
				'message' => 'Failed to fetch URL: ' . $response->get_error_message()
			]);
			return;
		}

		$html = wp_remote_retrieve_body($response);

		// Extract text content (remove HTML tags)
		$text = wp_strip_all_tags($html);

		// Clean up whitespace
		$text = preg_replace('/\s+/', ' ', $text);
		$text = trim($text);

		$word_count = str_word_count($text);

		// Cache the result for 1 hour
		$rate_limiter->cache_url_content($url, $text, $word_count);

		// Log usage
		$logger->log_usage('keyword_density_url', $_POST, 'success', [
			'url' => $url,
			'word_count' => $word_count
		]);

		wp_send_json_success([
			'text' => $text,
			'word_count' => $word_count,
			'cached' => false
		]);
	}
}
