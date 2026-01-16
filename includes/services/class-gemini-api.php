<?php

/**
 * Gemini API Wrapper Class
 *
 * Handles all interactions with Google Gemini API.
 * Includes circuit breaker pattern and retry logic.
 *
 * @package    SEO_Marketing_Tools
 * @subpackage Services
 * @since      1.0.0
 */

namespace SEO_Marketing_Tools\Services;

if (!defined('ABSPATH')) {
	exit;
}

class Gemini_API
{

	/**
	 * API endpoint
	 *
	 * @var string
	 */
	private const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

	/**
	 * API key
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Model to use
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * Circuit breaker state
	 *
	 * @var string
	 */
	private string $circuit_state = 'closed'; // closed, open, half_open

	/**
	 * Constructor
	 *
	 * @param string|null $api_key API key (if null, loads from options)
	 */
	public function __construct(?string $api_key = null)
	{
		$this->api_key = $api_key ?? $this->get_api_key_from_options();
		$this->model = get_option('seo_tools_gemini_model', 'gemini-2.0-flash-lite');
		$this->load_circuit_state();
	}

	/**
	 * Generate meta title and description using Gemini AI
	 *
	 * @param string $keyword Target keyword
	 * @param string $business_name Business name
	 * @param string $description Page description
	 * @param string $page_type Type of page
	 * @return array ['success' => bool, 'title' => string, 'description' => string, 'error' => string]
	 * @since 1.0.0
	 */
	public function generate_meta(
		string $keyword,
		string $business_name,
		string $description,
		string $page_type = 'service'
	): array {
		// Check API key
		if (empty($this->api_key)) {
			return [
				'success' => false,
				'error' => 'API key not configured',
				'code' => 'api_key_missing'
			];
		}

		// Check circuit breaker
		if (!$this->allow_request()) {
			return [
				'success' => false,
				'error' => 'Service temporarily unavailable. Please try again in a few minutes.',
				'code' => 'circuit_open'
			];
		}

		// Build prompt
		$prompt = $this->build_meta_prompt($keyword, $business_name, $description, $page_type);

		// Make API call with retry
		$result = $this->call_api_with_retry($prompt);

		// Update circuit breaker
		if ($result['success']) {
			$this->record_success();
		} else {
			$this->record_failure();
		}

		return $result;
	}

	/**
	 * Build prompt for meta generation
	 *
	 * @param string $keyword Keyword
	 * @param string $business_name Business name
	 * @param string $description Description
	 * @param string $page_type Page type
	 * @return string Formatted prompt
	 * @since 1.0.0
	 */
	private function build_meta_prompt(
		string $keyword,
		string $business_name,
		string $description,
		string $page_type
	): string {
		$context_by_type = [
			'home' => 'This is the main homepage',
			'service' => 'This is a service page',
			'blog' => 'This is a blog post',
			'product' => 'This is a product page',
			'about' => 'This is an about page',
			'contact' => 'This is a contact page'
		];

		$context = $context_by_type[$page_type] ?? 'This is a webpage';

		return <<<PROMPT
You are an expert SEO specialist. Generate an optimized meta title and meta description for a webpage.

Context:
- Keyword: {$keyword}
- Business Name: {$business_name}
- Page Description: {$description}
- Page Type: {$context}

Requirements:
1. Meta Title:
   - Length: Exactly 50-60 characters (strict requirement)
   - Include the keyword naturally
   - Include business name if it fits
   - Make it compelling and click-worthy
   - Use power words when appropriate

2. Meta Description:
   - Length: Exactly 150-160 characters (strict requirement)
   - Include the keyword naturally (1-2 times)
   - Compelling call-to-action
   - Describe the value proposition
   - Make it engaging and click-worthy

Format your response EXACTLY like this (no extra text):
TITLE: [your optimized title here]
DESCRIPTION: [your optimized description here]
PROMPT;
	}

	/**
	 * Call Gemini API with retry logic
	 *
	 * @param string $prompt Prompt text
	 * @param int $max_retries Maximum retry attempts
	 * @return array Response data
	 * @since 1.0.0
	 */
	private function call_api_with_retry(string $prompt, int $max_retries = 2): array
	{
		$attempt = 0;
		$last_error = '';

		while ($attempt <= $max_retries) {
			$result = $this->call_api($prompt);

			if ($result['success']) {
				return $result;
			}

			// Don't retry on specific errors
			$no_retry_codes = ['api_key_invalid', 'quota_exceeded', 'invalid_request'];
			if (isset($result['code']) && in_array($result['code'], $no_retry_codes, true)) {
				return $result;
			}

			$last_error = $result['error'] ?? 'Unknown error';
			$attempt++;

			// Exponential backoff
			if ($attempt <= $max_retries) {
				sleep(pow(2, $attempt)); // 2s, 4s
			}
		}

		return [
			'success' => false,
			'error' => $last_error,
			'code' => 'max_retries_exceeded'
		];
	}

	/**
	 * Make API call to Gemini
	 *
	 * @param string $prompt Prompt text
	 * @return array Response data
	 * @since 1.0.0
	 */
	private function call_api(string $prompt): array
	{
		$url = self::API_ENDPOINT . $this->model . ':generateContent?key=' . $this->api_key;

		$body = [
			'contents' => [
				[
					'parts' => [
						['text' => $prompt]
					]
				]
			],
			'generationConfig' => [
				'temperature' => 0.7,
				'maxOutputTokens' => 200,
				'topP' => 0.95,
				'topK' => 40
			]
		];

		$response = wp_remote_post($url, [
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'body' => wp_json_encode($body),
			'timeout' => 15,
			'sslverify' => true
		]);

		// Check for WordPress errors
		if (is_wp_error($response)) {
			return [
				'success' => false,
				'error' => 'Network error: ' . $response->get_error_message(),
				'code' => 'network_error'
			];
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		// Handle HTTP errors
		if ($status_code !== 200) {
			return $this->handle_api_error($status_code, $body);
		}

		// Parse response
		$data = json_decode($body, true);

		if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
			return [
				'success' => false,
				'error' => 'Invalid API response structure',
				'code' => 'invalid_response'
			];
		}

		$generated_text = $data['candidates'][0]['content']['parts'][0]['text'];

		// Parse the generated content
		$parsed = $this->parse_meta_response($generated_text);

		if (!$parsed['success']) {
			return $parsed;
		}

		return [
			'success' => true,
			'title' => $parsed['title'],
			'description' => $parsed['description'],
			'title_length' => strlen($parsed['title']),
			'description_length' => strlen($parsed['description']),
			'raw_response' => $generated_text
		];
	}

	/**
	 * Parse meta response from AI
	 *
	 * @param string $text Generated text
	 * @return array Parsed data
	 * @since 1.0.0
	 */
	private function parse_meta_response(string $text): array
	{
		// Extract title
		if (preg_match('/TITLE:\s*(.+?)(?:\n|$)/i', $text, $title_match)) {
			$title = trim($title_match[1]);
		} else {
			return [
				'success' => false,
				'error' => 'Could not parse title from response',
				'code' => 'parse_error'
			];
		}

		// Extract description
		if (preg_match('/DESCRIPTION:\s*(.+?)(?:\n|$)/is', $text, $desc_match)) {
			$description = trim($desc_match[1]);
		} else {
			return [
				'success' => false,
				'error' => 'Could not parse description from response',
				'code' => 'parse_error'
			];
		}

		return [
			'success' => true,
			'title' => $title,
			'description' => $description
		];
	}

	/**
	 * Handle API errors
	 *
	 * @param int $status_code HTTP status code
	 * @param string $body Response body
	 * @return array Error information
	 * @since 1.0.0
	 */
	private function handle_api_error(int $status_code, string $body): array
	{
		$error_data = json_decode($body, true);
		$error_message = $error_data['error']['message'] ?? 'Unknown API error';

		$error_map = [
			400 => ['message' => 'Invalid request', 'code' => 'invalid_request'],
			401 => ['message' => 'Invalid API key', 'code' => 'api_key_invalid'],
			403 => ['message' => 'Access forbidden', 'code' => 'forbidden'],
			429 => ['message' => 'API quota exceeded. Please try again tomorrow.', 'code' => 'quota_exceeded'],
			500 => ['message' => 'API server error', 'code' => 'server_error'],
			503 => ['message' => 'API service unavailable', 'code' => 'service_unavailable']
		];

		$error_info = $error_map[$status_code] ?? ['message' => $error_message, 'code' => 'unknown_error'];

		return [
			'success' => false,
			'error' => $error_info['message'],
			'code' => $error_info['code'],
			'status_code' => $status_code
		];
	}

	/**
	 * Get API key from options
	 *
	 * @return string API key
	 * @since 1.0.0
	 */
	private function get_api_key_from_options(): string
	{
		// TODO: Add decryption if key is encrypted
		// For now, get it directly
		// You will add your key in admin settings
		return get_option('seo_tools_gemini_api_key', '');
	}

	/**
	 * Circuit Breaker: Check if request should be allowed
	 *
	 * @return bool Whether request is allowed
	 * @since 1.0.0
	 */
	private function allow_request(): bool
	{
		if ($this->circuit_state === 'closed') {
			return true;
		}

		if ($this->circuit_state === 'open') {
			// Check if timeout expired (5 minutes)
			$opened_at = (int) get_transient('seo_circuit_opened_at');
			if (time() - $opened_at > 300) {
				$this->circuit_state = 'half_open';
				set_transient('seo_circuit_state', 'half_open', 600);
				return true;
			}
			return false;
		}

		// half_open state - allow one request to test
		return true;
	}

	/**
	 * Record successful request
	 *
	 * @since 1.0.0
	 */
	private function record_success(): void
	{
		if ($this->circuit_state === 'half_open') {
			$this->circuit_state = 'closed';
			set_transient('seo_circuit_state', 'closed', 600);
			delete_transient('seo_circuit_failure_count');
		}
	}

	/**
	 * Record failed request
	 *
	 * @since 1.0.0
	 */
	private function record_failure(): void
	{
		$failure_count = (int) get_transient('seo_circuit_failure_count');
		$failure_count++;

		set_transient('seo_circuit_failure_count', $failure_count, 300);

		// Open circuit after 5 failures in 5 minutes
		if ($failure_count >= 5) {
			$this->circuit_state = 'open';
			set_transient('seo_circuit_state', 'open', 600);
			set_transient('seo_circuit_opened_at', time(), 600);
		}
	}

	/**
	 * Load circuit breaker state
	 *
	 * @since 1.0.0
	 */
	private function load_circuit_state(): void
	{
		$this->circuit_state = get_transient('seo_circuit_state') ?: 'closed';
	}

	/**
	 * Test API connection (admin function)
	 *
	 * @return array Test result
	 * @since 1.0.0
	 */
	public function test_connection(): array
	{
		if (empty($this->api_key)) {
			return [
				'success' => false,
				'message' => 'API key is not configured'
			];
		}

		$result = $this->generate_meta(
			'SEO tools',
			'Test Company',
			'Testing API connection',
			'service'
		);

		if ($result['success']) {
			return [
				'success' => true,
				'message' => 'API connection successful! Model: ' . $this->model
			];
		}

		return [
			'success' => false,
			'message' => $result['error'] ?? 'API test failed'
		];
	}
}
