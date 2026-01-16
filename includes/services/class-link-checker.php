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
	 * Social media domains to exclude from checking
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $social_domains = [
		'facebook.com',
		'fb.com',
		'fb.me',
		'twitter.com',
		'x.com',
		't.co',
		'instagram.com',
		'instagr.am',
		'linkedin.com',
		'lnkd.in',
		'youtube.com',
		'youtu.be',
		'pinterest.com',
		'pin.it',
		'tiktok.com',
		'snapchat.com',
		'reddit.com',
		'redd.it'
	];

	/**
	 * Crawl entire website and check all links
	 *
	 * @param string $start_url Starting URL
	 * @param string $job_id Unique job ID for progress tracking
	 * @return array Crawl results
	 * @since 1.0.0
	 */
	public function crawl_website(string $start_url, string $job_id): array
	{
		$start_time = microtime(true);

		// Initialize crawl state
		// Use associative arrays for O(1) lookup instead of O(n) in_array
		$visited_urls = [];
		$queued_urls = []; // Track normalized URLs already in queue to prevent duplicates
		$crawl_queue = []; // Store as [normalized_url => original_url] for proper deduplication

		// Normalize and add start URL
		$normalized_start = $this->normalize_url_for_comparison($start_url);
		$crawl_queue[$normalized_start] = $start_url; // Store normalized as key, original as value
		$queued_urls[$normalized_start] = true;

		$all_checked_links = [];
		$checked_link_urls = []; // Track which links we've already checked to avoid duplicates
		$broken_links = [];
		$pages_crawled = 0;
		$base_domain = $this->get_base_domain($start_url);
		$start_path = parse_url($start_url, PHP_URL_PATH) ?: '/';
		$start_path = rtrim($start_path, '/') ?: '/';

		// Configuration
		$max_pages = (int) get_option('seo_tools_max_pages_crawl', 1000);
		$max_depth = (int) get_option('seo_tools_max_crawl_depth', 10);

		// Initialize progress
		$this->update_progress($job_id, [
			'status' => 'scanning',
			'pages_crawled' => 0,
			'links_checked' => 0,
			'broken_links_found' => 0,
			'start_time' => $start_time,
			'cancel_requested' => false
		]);

		// Crawl loop
		while (!empty($crawl_queue) && $pages_crawled < $max_pages) {
			// Check for cancel request
			if ($this->is_cancel_requested($job_id)) {
				return [
					'success' => false,
					'error' => 'Scan cancelled by user',
					'code' => 'CANCELLED'
				];
			}

			// Get next URL from queue (normalized URL is the key, original URL is the value)
			$normalized_url = array_key_first($crawl_queue);
			$current_url = $crawl_queue[$normalized_url];
			unset($crawl_queue[$normalized_url]);

			// Skip if already visited (double-check after dequeue)
			if (isset($visited_urls[$normalized_url])) {
				continue;
			}

			// Check depth (actual depth from start URL)
			$current_path = parse_url($current_url, PHP_URL_PATH) ?: '/';
			$current_path = rtrim($current_path, '/') ?: '/';
			$depth = $this->calculate_depth($current_path, $start_path);

			if ($depth > $max_depth) {
				// Mark as visited even if depth exceeded to prevent re-queuing
				$visited_urls[$normalized_url] = true;
				unset($queued_urls[$normalized_url]);
				continue;
			}

			// Mark as visited
			$visited_urls[$normalized_url] = true;
			unset($queued_urls[$normalized_url]);

			// Fetch page
			$page_content = $this->fetch_page($current_url);

			if (!$page_content['success']) {
				$pages_crawled++;
				continue;
			}

			// Extract links
			$links = $this->extract_links_with_categorization($page_content['html'], $current_url, $base_domain);

			// Add internal links to crawl queue (with deduplication)
			foreach ($links['internal'] as $internal_link) {
				$normalized_internal = $this->normalize_url_for_comparison($internal_link['url']);

				// Only add if not visited AND not already in queue
				if (!isset($visited_urls[$normalized_internal]) && !isset($queued_urls[$normalized_internal])) {
					// Store normalized URL as key, original URL as value for proper deduplication
					$crawl_queue[$normalized_internal] = $internal_link['url'];
					$queued_urls[$normalized_internal] = true;
				}
			}

			// Check all links (internal + external)
			$links_to_check = array_merge($links['internal'], $links['external']);

			foreach ($links_to_check as $link) {
				// Normalize link URL for deduplication
				$normalized_link_url = $this->normalize_url_for_comparison($link['url']);

				// Skip if we've already checked this link
				if (isset($checked_link_urls[$normalized_link_url])) {
					continue;
				}

				// Mark as checked
				$checked_link_urls[$normalized_link_url] = true;

				// Check link status
				$timeout = (int) get_option('seo_tools_link_timeout', 5);
				$result = $this->check_single_link($link['url'], $timeout);

				$checked_link = [
					'url' => $link['url'],
					'anchor_text' => $link['anchor_text'],
					'status' => $result['status'],
					'status_code' => $result['status_code'],
					'status_text' => $result['status_text'],
					'response_time' => $result['response_time'],
					'found_on_page' => $current_url
				];

				$all_checked_links[] = $checked_link;

				// Track broken links
				if ($result['status'] === 'broken' || $result['status'] === 'error') {
					$broken_links[] = $checked_link;
				}
			}

			$pages_crawled++;

			// Update progress
			$elapsed_time = (int) (microtime(true) - $start_time);
			$this->update_progress($job_id, [
				'status' => 'scanning',
				'pages_crawled' => $pages_crawled,
				'links_checked' => count($all_checked_links),
				'broken_links_found' => count($broken_links),
				'elapsed_time' => $elapsed_time,
				'current_page' => $current_url
			]);
		}

		// Calculate final stats
		$scan_time = round(microtime(true) - $start_time, 2);
		$working_links = count($all_checked_links) - count($broken_links);

		// Update final progress
		$this->update_progress($job_id, [
			'status' => 'completed',
			'pages_crawled' => $pages_crawled,
			'links_checked' => count($all_checked_links),
			'broken_links_found' => count($broken_links)
		]);

		return [
			'success' => true,
			'total_links_checked' => count($all_checked_links),
			'working_links' => $working_links,
			'broken_links_count' => count($broken_links),
			'pages_crawled' => $pages_crawled,
			'scan_time' => $scan_time,
			'broken_links' => $broken_links
		];
	}

	/**
	 * Check all links on a page (legacy method - kept for backward compatibility)
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

	/**
	 * Extract links with categorization (internal vs external)
	 *
	 * @param string $html HTML content
	 * @param string $base_url Base URL for resolving relative links
	 * @param string $base_domain Base domain for comparison
	 * @return array Categorized links
	 * @since 1.0.0
	 */
	private function extract_links_with_categorization(string $html, string $base_url, string $base_domain): array
	{
		$links = $this->extract_links($html, $base_url);

		$internal = [];
		$external = [];

		foreach ($links as $link) {
			// Skip social media links
			if ($this->is_social_link($link['url'])) {
				continue;
			}

			// Categorize as internal or external
			if ($this->is_internal_link($link['url'], $base_domain)) {
				$internal[] = $link;
			} else {
				$external[] = $link;
			}
		}

		return [
			'internal' => $internal,
			'external' => $external
		];
	}

	/**
	 * Check if URL is a social media link
	 *
	 * @param string $url URL to check
	 * @return bool True if social media link
	 * @since 1.0.0
	 */
	private function is_social_link(string $url): bool
	{
		$parsed = parse_url($url);

		if (!isset($parsed['host'])) {
			return false;
		}

		$host = strtolower($parsed['host']);

		// Remove www. prefix for comparison
		$host = preg_replace('/^www\./i', '', $host);

		// Check against social domains
		foreach ($this->social_domains as $social_domain) {
			if ($host === $social_domain || strpos($host, '.' . $social_domain) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if URL is internal (same domain)
	 *
	 * @param string $url URL to check
	 * @param string $base_domain Base domain
	 * @return bool True if internal
	 * @since 1.0.0
	 */
	private function is_internal_link(string $url, string $base_domain): bool
	{
		$link_domain = $this->get_base_domain($url);

		return $link_domain === $base_domain;
	}

	/**
	 * Get base domain from URL (handles www normalization)
	 *
	 * @param string $url URL
	 * @return string Base domain
	 * @since 1.0.0
	 */
	private function get_base_domain(string $url): string
	{
		$parsed = parse_url($url);

		if (!isset($parsed['host'])) {
			return '';
		}

		$host = strtolower($parsed['host']);

		// Remove www. prefix for normalization
		$host = preg_replace('/^www\./i', '', $host);

		return $host;
	}

	/**
	 * Normalize URL for comparison (remove fragment, normalize www, normalize trailing slash)
	 *
	 * @param string $url URL to normalize
	 * @return string Normalized URL
	 * @since 1.0.0
	 */
	private function normalize_url_for_comparison(string $url): string
	{
		$parsed = parse_url($url);

		if (!$parsed) {
			return $url;
		}

		// Rebuild URL without fragment
		$normalized = '';

		if (isset($parsed['scheme'])) {
			$normalized .= $parsed['scheme'] . '://';
		}

		if (isset($parsed['host'])) {
			// Normalize www
			$host = strtolower($parsed['host']);
			$host = preg_replace('/^www\./i', '', $host);
			$normalized .= $host;
		}

		if (isset($parsed['port'])) {
			$normalized .= ':' . $parsed['port'];
		}

		if (isset($parsed['path'])) {
			// Normalize trailing slash - remove it for comparison (except root)
			$path = rtrim($parsed['path'], '/');
			$normalized .= $path ?: '/';
		} else {
			$normalized .= '/';
		}

		if (isset($parsed['query'])) {
			$normalized .= '?' . $parsed['query'];
		}

		// Don't include fragment (#section)

		return $normalized;
	}

	/**
	 * Calculate depth of URL from start URL path
	 *
	 * @param string $current_path Current URL path
	 * @param string $start_path Start URL path
	 * @return int Depth level
	 * @since 1.0.0
	 */
	private function calculate_depth(string $current_path, string $start_path): int
	{
		// Normalize paths
		$current_path = rtrim($current_path, '/') ?: '/';
		$start_path = rtrim($start_path, '/') ?: '/';

		// If paths are the same, depth is 0
		if ($current_path === $start_path) {
			return 0;
		}

		// Get path segments
		$current_segments = array_filter(explode('/', trim($current_path, '/')));
		$start_segments = array_filter(explode('/', trim($start_path, '/')));

		// If start path is root, depth is just the number of segments in current path
		if (empty($start_segments) || $start_path === '/') {
			return count($current_segments);
		}

		// Check if current path starts with start path
		if (strpos($current_path, $start_path) === 0) {
			// Calculate depth based on path segments difference
			$relative_path = substr($current_path, strlen($start_path));
			$relative_path = ltrim($relative_path, '/');

			if (empty($relative_path)) {
				return 0;
			}

			// Count segments in relative path
			$segments = array_filter(explode('/', $relative_path));
			return count($segments);
		}

		// If current path doesn't start with start path, find common prefix
		$common_segments = 0;
		$min_length = min(count($current_segments), count($start_segments));

		for ($i = 0; $i < $min_length; $i++) {
			if ($current_segments[$i] === $start_segments[$i]) {
				$common_segments++;
			} else {
				break;
			}
		}

		// Depth is the difference in segments from common prefix
		return abs(count($current_segments) - $common_segments);
	}

	/**
	 * Update scan progress in transient
	 *
	 * @param string $job_id Job ID
	 * @param array $progress Progress data
	 * @return void
	 * @since 1.0.0
	 */
	private function update_progress(string $job_id, array $progress): void
	{
		$transient_key = 'seo_scan_progress_' . $job_id;
		set_transient($transient_key, $progress, 3600); // 1 hour expiry
	}

	/**
	 * Check if cancel has been requested
	 *
	 * @param string $job_id Job ID
	 * @return bool True if cancel requested
	 * @since 1.0.0
	 */
	private function is_cancel_requested(string $job_id): bool
	{
		$transient_key = 'seo_scan_progress_' . $job_id;
		$progress = get_transient($transient_key);

		return isset($progress['cancel_requested']) && $progress['cancel_requested'] === true;
	}

	/**
	 * Get scan progress
	 *
	 * @param string $job_id Job ID
	 * @return array|false Progress data or false if not found
	 * @since 1.0.0
	 */
	public function get_scan_progress(string $job_id)
	{
		$transient_key = 'seo_scan_progress_' . $job_id;
		return get_transient($transient_key);
	}

	/**
	 * Cancel scan
	 *
	 * @param string $job_id Job ID
	 * @return bool True if cancelled
	 * @since 1.0.0
	 */
	public function cancel_scan(string $job_id): bool
	{
		$transient_key = 'seo_scan_progress_' . $job_id;
		$progress = get_transient($transient_key);

		if ($progress) {
			$progress['cancel_requested'] = true;
			set_transient($transient_key, $progress, 3600);
			return true;
		}

		return false;
	}
}
