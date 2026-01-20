<?php

/**
 * Link Checker Service - Simplified Version
 *
 * Business logic for checking broken links with full website crawling.
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
	 * Social media domains to exclude from checking and crawling
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
		'redd.it',
		'whatsapp.com',
		'telegram.org',
		't.me',
		'discord.com',
		'discord.gg',
		'twitch.tv',
		'vimeo.com',
		'medium.com',
		'tumblr.com'
	];

	/**
	 * Crawl website and check all links (synchronous, chunked processing)
	 *
	 * @param string $start_url Starting URL
	 * @param int $max_pages Maximum pages to crawl in this chunk (default 100)
	 * @param array|null $resume_state State from previous chunk (for resuming)
	 * @return array Crawl results with state for resuming
	 * @since 1.0.0
	 */
	public function crawl_website(string $start_url, int $max_pages = 100, ?array $resume_state = null): array
	{
		$start_time = microtime(true);

		// Initialize or resume state
		if ($resume_state) {
			$visited_urls = $resume_state['visited_urls'] ?? [];
			$crawl_queue = $resume_state['queue'] ?? [];
			$broken_links = $resume_state['broken_links'] ?? [];
			$working_count = $resume_state['working_count'] ?? 0;
			$total_pages_crawled = $resume_state['total_pages_crawled'] ?? 0;
			$checked_link_urls = $resume_state['checked_link_urls'] ?? [];
		} else {
			// Fresh start
			$visited_urls = [];
			$crawl_queue = [$start_url];
			$broken_links = [];
			$working_count = 0;
			$total_pages_crawled = 0;
			$checked_link_urls = [];
		}

		$base_domain = $this->get_base_domain($start_url);
		$pages_crawled_this_chunk = 0;

		// Crawl loop - process up to max_pages in this chunk
		while (!empty($crawl_queue) && $pages_crawled_this_chunk < $max_pages && $total_pages_crawled < 1000) {
			// Get next URL from queue
			$current_url = array_shift($crawl_queue);
			$normalized_url = $this->normalize_url($current_url);

			// Skip if already visited
			if (in_array($normalized_url, $visited_urls, true)) {
				continue;
			}

			// Mark as visited
			$visited_urls[] = $normalized_url;

			// Fetch page
			$page_content = $this->fetch_page($current_url);

			if (!$page_content['success']) {
				$pages_crawled_this_chunk++;
				$total_pages_crawled++;
				continue;
			}

		// Extract links
		$links = $this->extract_links($page_content['html'], $current_url);

		// Collect links to check (filter and deduplicate)
		$links_to_check = [];

		foreach ($links as $link) {
			// Skip social media links completely
			if ($this->is_social_link($link['url'])) {
				continue;
			}

			$link_domain = $this->get_base_domain($link['url']);
			$is_internal = ($link_domain === $base_domain);

			// Add internal links to crawl queue
			if ($is_internal) {
				$normalized_link = $this->normalize_url($link['url']);
				if (!in_array($normalized_link, $visited_urls, true) && !in_array($link['url'], $crawl_queue, true)) {
					$crawl_queue[] = $link['url'];
				}
			}

			// Collect link for batch checking (skip already checked)
			$normalized_link_url = $this->normalize_url($link['url']);
			if (!in_array($normalized_link_url, $checked_link_urls, true)) {
				$checked_link_urls[] = $normalized_link_url;

				$links_to_check[] = [
					'url' => $link['url'],
					'anchor_text' => $link['anchor_text'],
					'found_on_page' => $current_url
				];
			}
		}

		// Check all links from this page in parallel batches
		if (!empty($links_to_check)) {
			$batch_results = $this->check_links_batch($links_to_check, 10);

			// Process batch results
			foreach ($batch_results as $result) {
				if ($result['status'] === 'broken' || $result['status'] === 'error') {
					$broken_links[] = $result;
				} else {
					$working_count++;
				}
			}
		}

			$pages_crawled_this_chunk++;
			$total_pages_crawled++;
		}

		// Calculate final stats
		$scan_time = round(microtime(true) - $start_time, 2);
		$total_links_checked = count($broken_links) + $working_count;
		$has_more = !empty($crawl_queue) && $total_pages_crawled < 1000;

		// Build state for resuming
		$state = [
			'visited_urls' => $visited_urls,
			'queue' => $crawl_queue,
			'broken_links' => $broken_links,
			'working_count' => $working_count,
			'total_pages_crawled' => $total_pages_crawled,
			'checked_link_urls' => $checked_link_urls
		];

		return [
			'success' => true,
			'pages_crawled' => $total_pages_crawled,
			'pages_this_chunk' => $pages_crawled_this_chunk,
			'total_links_checked' => $total_links_checked,
			'working_links' => $working_count,
			'broken_links_count' => count($broken_links),
			'broken_links' => $broken_links,
			'scan_time' => $scan_time,
			'has_more' => $has_more,
			'estimated_remaining' => count($crawl_queue),
			'state' => $state
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
			$normalized = $this->normalize_url($link['url']);
			if (!in_array($normalized, $seen_urls, true)) {
				$unique_links[] = $link;
				$seen_urls[] = $normalized;
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
	 * Check multiple links in parallel (batch checking)
	 * Based on broken-link-checker architecture
	 *
	 * @param array $links Array of links to check
	 * @param int $batch_size Number of links to check simultaneously
	 * @return array Results for all links
	 * @since 1.0.0
	 */
	private function check_links_batch(array $links, int $batch_size = 10): array
	{
		if (empty($links)) {
			return [];
		}

		// Group links by host for per-host rate limiting
		$links_by_host = $this->group_links_by_host($links);
		$all_results = [];

		// Process each host's links with per-host limiting (max 2 concurrent per host)
		foreach ($links_by_host as $host => $host_links) {
			// Check max 2 links per host at a time to avoid overwhelming servers
			$host_batches = array_chunk($host_links, 2);

			foreach ($host_batches as $host_batch) {
				// Prepare requests for parallel execution
				$requests = [];
				$request_map = []; // Map request keys to original link data

				foreach ($host_batch as $link) {
					$url = $link['url'];
					$request_key = md5($url);

					$requests[$request_key] = [
						'url' => $url,
						'type' => \WpOrg\Requests\Requests::HEAD,
						'headers' => [
							'User-Agent' => 'SEO Tools Link Checker/1.0'
						],
						'options' => [
							'timeout' => 3,
							'follow_redirects' => true,
							'redirects' => 5,
							'verify' => false // Some sites have SSL issues
						]
					];

					$request_map[$request_key] = $link;
				}

				// Execute all requests in parallel
				try {
					$responses = \WpOrg\Requests\Requests::request_multiple($requests);

					// Process each response
					foreach ($responses as $request_key => $response) {
						$link = $request_map[$request_key];
						$url = $link['url'];

						if (is_a($response, 'WpOrg\Requests\Exception')) {
							// Request failed with exception
							$all_results[] = [
								'url' => $url,
								'anchor_text' => $link['anchor_text'],
								'status' => 'error',
								'status_code' => 0,
								'status_text' => $response->getMessage(),
								'response_time' => 0,
								'found_on_page' => $link['found_on_page']
							];
						} else {
							// Successful response
							$status_code = $response->status_code;

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

							$all_results[] = [
								'url' => $url,
								'anchor_text' => $link['anchor_text'],
								'status' => $status,
								'status_code' => $status_code,
								'status_text' => $status_text,
								'response_time' => 0, // Not easily available in batch mode
								'found_on_page' => $link['found_on_page']
							];
						}
					}
				} catch (\Exception $e) {
					// Fallback to single link checking if batch fails
					foreach ($host_batch as $link) {
						$result = $this->check_single_link($link['url'], 3);
						$all_results[] = [
							'url' => $link['url'],
							'anchor_text' => $link['anchor_text'],
							'status' => $result['status'],
							'status_code' => $result['status_code'],
							'status_text' => $result['status_text'],
							'response_time' => $result['response_time'],
							'found_on_page' => $link['found_on_page']
						];
					}
				}
			}
		}

		return $all_results;
	}

	/**
	 * Group links by host for per-host rate limiting
	 *
	 * @param array $links Array of links
	 * @return array Links grouped by host
	 * @since 1.0.0
	 */
	private function group_links_by_host(array $links): array
	{
		$links_by_host = [];

		foreach ($links as $link) {
			$parsed = parse_url($link['url']);
			$host = isset($parsed['host']) ? strtolower($parsed['host']) : 'unknown';

			if (!isset($links_by_host[$host])) {
				$links_by_host[$host] = [];
			}

			$links_by_host[$host][] = $link;
		}

		return $links_by_host;
	}

	/**
	 * Check a single link
	 *
	 * @param string $url Link URL
	 * @param int $timeout Timeout in seconds
	 * @return array Check result
	 * @since 1.0.0
	 */
	private function check_single_link(string $url, int $timeout = 3): array
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
	 * Normalize URL for comparison (simple version)
	 *
	 * @param string $url URL to normalize
	 * @return string Normalized URL
	 * @since 1.0.0
	 */
	private function normalize_url(string $url): string
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
			// Normalize www and lowercase
			$host = strtolower($parsed['host']);
			$host = preg_replace('/^www\./i', '', $host);
			$normalized .= $host;
		}

		if (isset($parsed['port'])) {
			$normalized .= ':' . $parsed['port'];
		}

		if (isset($parsed['path'])) {
			// Normalize trailing slash - remove it (except for root)
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
}
