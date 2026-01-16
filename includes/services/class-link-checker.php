<?php

/**
 * Link Checker Service
 *
 * Business logic for checking broken links on pages.
 *
 * @package    SEO_Marketing_Tools
 * @subpackage Services
 * @since      1.0.0
 */

namespace SEO_Marketing_Tools\Services;

if (!defined('ABSPATH')) {
	exit;
}

class Link_Checker
{

	/**
	 * Check all links on a page
	 *
	 * @param string $url Page URL to check
	 * @return array Check results
	 * @since 1.0.0
	 */
	public function check_page_links(string $url): array
	{
		$start_time = microtime(true);

		// 1. Fetch page content
		$page_content = $this->fetch_page($url);

		if (!$page_content['success']) {
			return $page_content;
		}

		// 2. Extract all links
		$links = $this->extract_links($page_content['html'], $url);

		if (empty($links)) {
			return [
				'success' => false,
				'error' => 'No links found on the page',
				'code' => 'NO_LINKS_FOUND'
			];
		}

		// 3. Limit number of links to check
		$max_links = (int) get_option('seo_tools_max_links_check', 50);
		if (count($links) > $max_links) {
			$links = array_slice($links, 0, $max_links);
		}

		// 4. Check each link
		$checked_links = $this->check_links($links);

		// 5. Compile results
		$broken_links = array_filter($checked_links, function ($link) {
			return $link['status'] !== 'working';
		});

		$scan_time = round(microtime(true) - $start_time, 2);

		return [
			'success' => true,
			'total_links' => count($checked_links),
			'broken_links' => count($broken_links),
			'working_links' => count($checked_links) - count($broken_links),
			'links' => $checked_links,
			'scan_time' => $scan_time
		];
	}

	/**
	 * Fetch page HTML
	 *
	 * @param string $url Page URL
	 * @return array Fetch result
	 * @since 1.0.0
	 */
	private function fetch_page(string $url): array
	{
		$response = wp_remote_get($url, [
			'timeout' => 15,
			'user-agent' => 'SEO Tools Link Checker/1.0',
			'sslverify' => true,
			'redirection' => 5
		]);

		if (is_wp_error($response)) {
			return [
				'success' => false,
				'error' => 'Failed to fetch page: ' . $response->get_error_message(),
				'code' => 'FETCH_FAILED'
			];
		}

		$status_code = wp_remote_retrieve_response_code($response);

		if ($status_code !== 200) {
			return [
				'success' => false,
				'error' => 'Page returned status code: ' . $status_code,
				'code' => 'INVALID_STATUS'
			];
		}

		$html = wp_remote_retrieve_body($response);

		return [
			'success' => true,
			'html' => $html
		];
	}

	/**
	 * Extract links from HTML
	 *
	 * @param string $html HTML content
	 * @param string $base_url Base URL for resolving relative links
	 * @return array Extracted links
	 * @since 1.0.0
	 */
	private function extract_links(string $html, string $base_url): array
	{
		$links = [];

		// Parse HTML
		libxml_use_internal_errors(true);
		$dom = new \DOMDocument();
		$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();

		// Get all <a> tags
		$anchors = $dom->getElementsByTagName('a');

		foreach ($anchors as $anchor) {
			$href = $anchor->getAttribute('href');
			$anchor_text = trim($anchor->textContent);

			// Skip empty links, anchors, and javascript
			if (
				empty($href) ||
				$href === '#' ||
				strpos($href, 'javascript:') === 0 ||
				strpos($href, 'mailto:') === 0 ||
				strpos($href, 'tel:') === 0
			) {
				continue;
			}

			// Convert relative URLs to absolute
			$absolute_url = $this->make_absolute_url($href, $base_url);

			// Skip invalid URLs
			if (!filter_var($absolute_url, FILTER_VALIDATE_URL)) {
				continue;
			}

			// Skip data URLs and other non-http protocols
			$parsed = parse_url($absolute_url);
			if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'], true)) {
				continue;
			}

			$links[] = [
				'url' => $absolute_url,
				'anchor_text' => $anchor_text ?: '(no text)',
				'original_href' => $href
			];
		}

		// Remove duplicates
		$unique_links = [];
		$seen_urls = [];

		foreach ($links as $link) {
			if (!in_array($link['url'], $seen_urls, true)) {
				$unique_links[] = $link;
				$seen_urls[] = $link['url'];
			}
		}

		return $unique_links;
	}

	/**
	 * Convert relative URL to absolute
	 *
	 * @param string $url URL (may be relative)
	 * @param string $base_url Base URL
	 * @return string Absolute URL
	 * @since 1.0.0
	 */
	private function make_absolute_url(string $url, string $base_url): string
	{
		// Already absolute
		if (parse_url($url, PHP_URL_SCHEME) !== null) {
			return $url;
		}

		$base_parts = parse_url($base_url);

		// Protocol-relative URL
		if (strpos($url, '//') === 0) {
			return $base_parts['scheme'] . ':' . $url;
		}

		$base = $base_parts['scheme'] . '://' . $base_parts['host'];

		if (isset($base_parts['port'])) {
			$base .= ':' . $base_parts['port'];
		}

		// Absolute path
		if (strpos($url, '/') === 0) {
			return $base . $url;
		}

		// Relative path
		$path = isset($base_parts['path']) ? $base_parts['path'] : '/';
		$path = substr($path, 0, strrpos($path, '/') + 1);

		return $base . $path . $url;
	}

	/**
	 * Check multiple links (concurrent)
	 *
	 * @param array $links Links to check
	 * @return array Checked links with status
	 * @since 1.0.0
	 */
	private function check_links(array $links): array
	{
		$timeout = (int) get_option('seo_tools_link_timeout', 5);
		$checked = [];

		foreach ($links as $link) {
			$result = $this->check_single_link($link['url'], $timeout);

			$checked[] = [
				'url' => $link['url'],
				'anchor_text' => $link['anchor_text'],
				'status' => $result['status'],
				'status_code' => $result['status_code'],
				'status_text' => $result['status_text'],
				'response_time' => $result['response_time']
			];
		}

		return $checked;
	}

	/**
	 * Check a single link
	 *
	 * @param string $url Link URL
	 * @param int $timeout Timeout in seconds
	 * @return array Check result
	 * @since 1.0.0
	 */
	private function check_single_link(string $url, int $timeout = 5): array
	{
		$start_time = microtime(true);

		// Use HEAD request for efficiency
		$response = wp_remote_head($url, [
			'timeout' => $timeout,
			'user-agent' => 'SEO Tools Link Checker/1.0',
			'sslverify' => false, // Some sites have SSL issues
			'redirection' => 5
		]);

		$response_time = round((microtime(true) - $start_time) * 1000); // ms

		if (is_wp_error($response)) {
			return [
				'status' => 'error',
				'status_code' => 0,
				'status_text' => $response->get_error_message(),
				'response_time' => $response_time
			];
		}

		$status_code = wp_remote_retrieve_response_code($response);

		// Determine status
		$status = 'working';
		$status_text = 'OK';

		if ($status_code >= 400 && $status_code < 500) {
			$status = 'broken';
			$status_text = $this->get_status_text($status_code);
		} elseif ($status_code >= 500) {
			$status = 'error';
			$status_text = $this->get_status_text($status_code);
		} elseif ($status_code >= 300 && $status_code < 400) {
			$status = 'redirect';
			$status_text = 'Redirect';
		}

		return [
			'status' => $status,
			'status_code' => $status_code,
			'status_text' => $status_text,
			'response_time' => $response_time
		];
	}

	/**
	 * Get HTTP status text
	 *
	 * @param int $code Status code
	 * @return string Status text
	 * @since 1.0.0
	 */
	private function get_status_text(int $code): string
	{
		$statuses = [
			200 => 'OK',
			301 => 'Moved Permanently',
			302 => 'Found',
			304 => 'Not Modified',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not Found',
			410 => 'Gone',
			429 => 'Too Many Requests',
			500 => 'Internal Server Error',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout'
		];

		return $statuses[$code] ?? 'Unknown Status';
	}
}
