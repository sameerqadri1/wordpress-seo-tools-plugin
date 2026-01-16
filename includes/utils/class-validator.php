<?php

/**
 * Validator Class
 *
 * Handles input validation and sanitization with SSRF protection.
 *
 * @package    SEO_Marketing_Tools
 * @subpackage Utils
 * @since      1.0.0
 */

namespace SEO_Marketing_Tools\Utils;

if (!defined('ABSPATH')) {
	exit;
}

class Validator
{

	/**
	 * Validate and sanitize meta generator input
	 *
	 * @param array $data Input data
	 * @return array ['valid' => bool, 'data' => array, 'errors' => array]
	 * @since 1.0.0
	 */
	public function validate_meta_input(array $data): array
	{
		$errors = [];
		$clean_data = [];

		// Keyword (required, max 100 chars)
		if (empty($data['keyword'])) {
			$errors[] = 'Keyword is required';
		} else {
			$keyword = sanitize_text_field($data['keyword']);
			if (strlen($keyword) > 100) {
				$errors[] = 'Keyword must be 100 characters or less';
			} else {
				$clean_data['keyword'] = $keyword;
			}
		}

		// Business name (required, max 50 chars)
		if (empty($data['business_name'])) {
			$errors[] = 'Business name is required';
		} else {
			$business = sanitize_text_field($data['business_name']);
			if (strlen($business) > 50) {
				$errors[] = 'Business name must be 50 characters or less';
			} else {
				$clean_data['business_name'] = $business;
			}
		}

		// Description (required, max 300 chars)
		if (empty($data['description'])) {
			$errors[] = 'Description is required';
		} else {
			$description = sanitize_textarea_field($data['description']);
			if (strlen($description) > 300) {
				$errors[] = 'Description must be 300 characters or less';
			} else {
				$clean_data['description'] = $description;
			}
		}

		// Page type (optional, must be valid value)
		$valid_page_types = ['home', 'service', 'blog', 'product', 'about', 'contact'];
		$page_type = isset($data['page_type']) ? sanitize_text_field($data['page_type']) : 'service';

		if (!in_array($page_type, $valid_page_types, true)) {
			$page_type = 'service';
		}

		$clean_data['page_type'] = $page_type;

		return [
			'valid' => empty($errors),
			'data' => $clean_data,
			'errors' => $errors
		];
	}

	/**
	 * Validate URL for link checker and keyword density
	 * Includes SSRF protection
	 *
	 * @param string $url URL to validate
	 * @return array ['valid' => bool, 'url' => string, 'errors' => array]
	 * @since 1.0.0
	 */
	public function validate_url(string $url): array
	{
		$errors = [];

		// Basic sanitization
		$url = esc_url_raw($url);

		// Check if URL is provided
		if (empty($url)) {
			$errors[] = 'URL is required';
			return ['valid' => false, 'url' => '', 'errors' => $errors];
		}

		// Check URL length
		if (strlen($url) > 2048) {
			$errors[] = 'URL must be 2048 characters or less';
		}

		// Validate URL format
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			$errors[] = 'Invalid URL format';
			return ['valid' => false, 'url' => $url, 'errors' => $errors];
		}

		// Parse URL components
		$parsed = parse_url($url);

		if ($parsed === false || !isset($parsed['scheme']) || !isset($parsed['host'])) {
			$errors[] = 'Invalid URL structure';
			return ['valid' => false, 'url' => $url, 'errors' => $errors];
		}

		// SSRF Protection: Only allow HTTP/HTTPS
		if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
			$errors[] = 'Only HTTP and HTTPS protocols are allowed';
		}

		// SSRF Protection: Block internal/private IPs
		if ($this->is_internal_ip($parsed['host'])) {
			$errors[] = 'Cannot access internal or private IP addresses';
		}

		// SSRF Protection: Block localhost variations
		if ($this->is_localhost($parsed['host'])) {
			$errors[] = 'Cannot access localhost';
		}

		return [
			'valid' => empty($errors),
			'url' => $url,
			'errors' => $errors
		];
	}

	/**
	 * Check if hostname resolves to internal/private IP
	 * SSRF Protection
	 *
	 * @param string $host Hostname
	 * @return bool True if internal/private
	 * @since 1.0.0
	 */
	private function is_internal_ip(string $host): bool
	{
		// Resolve hostname to IP
		$ip = gethostbyname($host);

		// If resolution failed, block it
		if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
			return false; // Let it through if we can't resolve (might be valid domain)
		}

		// Check if it's a private or reserved IP
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			// Private IPv4 ranges
			$private_ranges = [
				['10.0.0.0', '10.255.255.255'],
				['172.16.0.0', '172.31.255.255'],
				['192.168.0.0', '192.168.255.255'],
				['127.0.0.0', '127.255.255.255'],
				['169.254.0.0', '169.254.255.255'],
				['0.0.0.0', '0.255.255.255'],
			];

			$ip_long = ip2long($ip);

			foreach ($private_ranges as $range) {
				$start = ip2long($range[0]);
				$end = ip2long($range[1]);

				if ($ip_long >= $start && $ip_long <= $end) {
					return true;
				}
			}
		}

		// Check for private IPv6
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			// Block ::1 (localhost) and fc00::/7 (private)
			if ($ip === '::1' || strpos($ip, 'fc') === 0 || strpos($ip, 'fd') === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if hostname is localhost variation
	 * SSRF Protection
	 *
	 * @param string $host Hostname
	 * @return bool True if localhost
	 * @since 1.0.0
	 */
	private function is_localhost(string $host): bool
	{
		$localhost_variations = [
			'localhost',
			'localhost.localdomain',
			'local',
			'127.0.0.1',
			'::1',
			'0.0.0.0'
		];

		return in_array(strtolower($host), $localhost_variations, true);
	}

	/**
	 * Validate text content for keyword density checker
	 *
	 * @param string $text Text content
	 * @return array ['valid' => bool, 'text' => string, 'errors' => array]
	 * @since 1.0.0
	 */
	public function validate_text_content(string $text): array
	{
		$errors = [];

		// Check if text is provided
		if (empty(trim($text))) {
			$errors[] = 'Text content is required';
			return ['valid' => false, 'text' => '', 'errors' => $errors];
		}

		// Word count limits
		$word_count = str_word_count($text);

		if ($word_count < 10) {
			$errors[] = 'Text must contain at least 10 words';
		}

		if ($word_count > 10000) {
			$errors[] = 'Text must contain 10,000 words or less';
		}

		// Sanitize (allow basic HTML for content analysis)
		$allowed_tags = '<p><br><h1><h2><h3><h4><h5><h6><strong><em><b><i><u><a>';
		$clean_text = strip_tags($text, $allowed_tags);

		return [
			'valid' => empty($errors),
			'text' => $clean_text,
			'word_count' => $word_count,
			'errors' => $errors
		];
	}

	/**
	 * Validate and verify reCAPTCHA response
	 *
	 * @param string $response reCAPTCHA response token
	 * @param string $secret_key reCAPTCHA secret key
	 * @param string $action Expected action (for v3)
	 * @return array ['valid' => bool, 'score' => float|null, 'error' => string|null]
	 * @since 1.0.0
	 */
	public function verify_recaptcha(string $response, string $secret_key, string $action = ''): array
	{
		if (empty($response)) {
			return [
				'valid' => false,
				'score' => null,
				'error' => 'reCAPTCHA response is required'
			];
		}

		if (empty($secret_key)) {
			return [
				'valid' => false,
				'score' => null,
				'error' => 'reCAPTCHA is not configured'
			];
		}

		// Call Google reCAPTCHA API
		$verify_url = 'https://www.google.com/recaptcha/api/siteverify';

		$response_data = wp_remote_post($verify_url, [
			'body' => [
				'secret' => $secret_key,
				'response' => $response,
				'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
			],
			'timeout' => 10
		]);

		if (is_wp_error($response_data)) {
			return [
				'valid' => false,
				'score' => null,
				'error' => 'reCAPTCHA verification failed: ' . $response_data->get_error_message()
			];
		}

		$result = json_decode(wp_remote_retrieve_body($response_data), true);

		if (!isset($result['success'])) {
			return [
				'valid' => false,
				'score' => null,
				'error' => 'Invalid reCAPTCHA response'
			];
		}

		// For v3, check score and action
		if (isset($result['score'])) {
			$score = (float) $result['score'];
			$valid = $result['success'] && $score >= 0.5;

			// Check action if provided
			if (!empty($action) && isset($result['action']) && $result['action'] !== $action) {
				$valid = false;
			}

			return [
				'valid' => $valid,
				'score' => $score,
				'error' => $valid ? null : 'reCAPTCHA score too low or action mismatch'
			];
		}

		// For v2, just check success
		return [
			'valid' => $result['success'],
			'score' => null,
			'error' => $result['success'] ? null : 'reCAPTCHA verification failed'
		];
	}

	/**
	 * Sanitize filename for CSV export
	 *
	 * @param string $filename Filename
	 * @return string Safe filename
	 * @since 1.0.0
	 */
	public function sanitize_filename(string $filename): string
	{
		// Remove any path separators
		$filename = basename($filename);

		// Remove special characters
		$filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);

		// Limit length
		if (strlen($filename) > 255) {
			$filename = substr($filename, 0, 255);
		}

		return $filename;
	}
}
