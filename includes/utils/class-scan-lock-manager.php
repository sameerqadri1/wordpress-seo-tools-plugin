<?php

/**
 * Scan Lock Manager
 *
 * Manages concurrent scan limits and per-user scan locks.
 *
 * @package    SEO_Marketing_Tools
 * @subpackage Utils
 * @since      1.0.0
 */

namespace SEO_Marketing_Tools\Utils;

if (!defined('ABSPATH')) {
	exit;
}

class Scan_Lock_Manager
{
	/**
	 * Maximum concurrent scans allowed globally
	 *
	 * @var int
	 */
	private const MAX_CONCURRENT_SCANS = 3;

	/**
	 * Lock timeout in seconds (30 minutes)
	 *
	 * @var int
	 */
	private const LOCK_TIMEOUT = 1800;

	/**
	 * Get unique identifier for current user
	 *
	 * @return string User identifier (user ID or IP hash)
	 * @since 1.0.0
	 */
	private function get_user_identifier(): string
	{
		// If user is logged in, use user ID
		if (is_user_logged_in()) {
			return 'user_' . get_current_user_id();
		}

		// Otherwise, use hashed IP (for privacy)
		$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		return 'ip_' . md5($ip);
	}

	/**
	 * Check if user can start a new scan
	 *
	 * @return array ['allowed' => bool, 'message' => string, 'code' => string]
	 * @since 1.0.0
	 */
	public function can_start_scan(): array
	{
		// 1. Clean up abandoned scans first
		$this->cleanup_abandoned_scans();

		// 2. Check per-user limit
		$user_id = $this->get_user_identifier();
		$user_lock_key = 'seo_scan_lock_' . $user_id;
		$user_lock = get_transient($user_lock_key);

		if ($user_lock !== false) {
			return [
				'allowed' => false,
				'message' => 'You already have a scan in progress. Please wait for it to complete or cancel it first.',
				'code' => 'USER_SCAN_IN_PROGRESS'
			];
		}

		// 3. Check global concurrent limit
		$active_scans_key = 'seo_active_scans_count';
		$active_scans = (int) get_transient($active_scans_key);

		if ($active_scans >= self::MAX_CONCURRENT_SCANS) {
			return [
				'allowed' => false,
				'message' => sprintf(
					'Server is currently processing %d scans. Please try again in a few minutes.',
					$active_scans
				),
				'code' => 'SERVER_BUSY'
			];
		}

		return [
			'allowed' => true,
			'message' => 'Scan allowed',
			'code' => 'OK'
		];
	}

	/**
	 * Acquire scan lock for current user
	 *
	 * @return bool True if lock acquired successfully
	 * @since 1.0.0
	 */
	public function acquire_lock(): bool
	{
		$user_id = $this->get_user_identifier();

		// 1. Set user lock
		$user_lock_key = 'seo_scan_lock_' . $user_id;
		$lock_data = [
			'user_id' => $user_id,
			'start_time' => time(),
			'last_activity' => time()
		];
		set_transient($user_lock_key, $lock_data, self::LOCK_TIMEOUT);

		// 2. Increment global counter
		$active_scans_key = 'seo_active_scans_count';
		$active_scans = (int) get_transient($active_scans_key);
		set_transient($active_scans_key, $active_scans + 1, self::LOCK_TIMEOUT);

		// 3. Track active scan in global list
		$active_list_key = 'seo_active_scans_list';
		$active_list = get_transient($active_list_key);
		if (!is_array($active_list)) {
			$active_list = [];
		}
		$active_list[$user_id] = $lock_data;
		set_transient($active_list_key, $active_list, self::LOCK_TIMEOUT);

		return true;
	}

	/**
	 * Update lock activity timestamp (keeps lock alive)
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function update_lock_activity(): void
	{
		$user_id = $this->get_user_identifier();
		$user_lock_key = 'seo_scan_lock_' . $user_id;
		$lock_data = get_transient($user_lock_key);

		if ($lock_data !== false && is_array($lock_data)) {
			$lock_data['last_activity'] = time();
			set_transient($user_lock_key, $lock_data, self::LOCK_TIMEOUT);
		}
	}

	/**
	 * Release scan lock for current user
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function release_lock(): void
	{
		$user_id = $this->get_user_identifier();

		// 1. Delete user lock
		$user_lock_key = 'seo_scan_lock_' . $user_id;
		delete_transient($user_lock_key);

		// 2. Decrement global counter
		$active_scans_key = 'seo_active_scans_count';
		$active_scans = (int) get_transient($active_scans_key);
		if ($active_scans > 0) {
			set_transient($active_scans_key, $active_scans - 1, self::LOCK_TIMEOUT);
		} else {
			delete_transient($active_scans_key);
		}

		// 3. Remove from active list
		$active_list_key = 'seo_active_scans_list';
		$active_list = get_transient($active_list_key);
		if (is_array($active_list) && isset($active_list[$user_id])) {
			unset($active_list[$user_id]);
			if (empty($active_list)) {
				delete_transient($active_list_key);
			} else {
				set_transient($active_list_key, $active_list, self::LOCK_TIMEOUT);
			}
		}
	}

	/**
	 * Cleanup abandoned scans (inactive for > 30 minutes)
	 *
	 * @return int Number of locks cleaned up
	 * @since 1.0.0
	 */
	public function cleanup_abandoned_scans(): int
	{
		$active_list_key = 'seo_active_scans_list';
		$active_list = get_transient($active_list_key);

		if (!is_array($active_list) || empty($active_list)) {
			return 0;
		}

		$current_time = time();
		$cleaned_count = 0;

		foreach ($active_list as $user_id => $lock_data) {
			// Check if last activity was more than 30 minutes ago
			if (isset($lock_data['last_activity'])) {
				$inactive_time = $current_time - $lock_data['last_activity'];
				if ($inactive_time > self::LOCK_TIMEOUT) {
					// Delete user lock
					$user_lock_key = 'seo_scan_lock_' . $user_id;
					delete_transient($user_lock_key);

					// Remove from active list
					unset($active_list[$user_id]);
					$cleaned_count++;
				}
			}
		}

		// Update active list and counter if any locks were cleaned
		if ($cleaned_count > 0) {
			if (empty($active_list)) {
				delete_transient($active_list_key);
				delete_transient('seo_active_scans_count');
			} else {
				set_transient($active_list_key, $active_list, self::LOCK_TIMEOUT);

				// Recalculate global counter
				$active_scans_key = 'seo_active_scans_count';
				set_transient($active_scans_key, count($active_list), self::LOCK_TIMEOUT);
			}
		}

		return $cleaned_count;
	}

	/**
	 * Get current scan statistics
	 *
	 * @return array Statistics about active scans
	 * @since 1.0.0
	 */
	public function get_scan_stats(): array
	{
		$active_list_key = 'seo_active_scans_list';
		$active_list = get_transient($active_list_key);

		if (!is_array($active_list)) {
			$active_list = [];
		}

		$active_scans_key = 'seo_active_scans_count';
		$active_count = (int) get_transient($active_scans_key);

		return [
			'active_scans' => $active_count,
			'max_concurrent' => self::MAX_CONCURRENT_SCANS,
			'available_slots' => max(0, self::MAX_CONCURRENT_SCANS - $active_count),
			'active_users' => count($active_list)
		];
	}

	/**
	 * Check if URL was already scanned by this user today
	 *
	 * @param string $url URL to check
	 * @return array ['allowed' => bool, 'message' => string, 'code' => string]
	 * @since 1.0.0
	 */
	public function can_scan_url(string $url): array
	{
		// Max scans per URL per user per day
		$max_scans_per_url = 3;

		// Normalize URL for consistent comparison
		$normalized_url = $this->normalize_url_for_tracking($url);
		$user_id = $this->get_user_identifier();

		// Get scanned URLs for this user today (now stores url => count)
		$scanned_urls_key = 'seo_scanned_urls_' . $user_id;
		$scanned_urls = get_transient($scanned_urls_key);

		if (!is_array($scanned_urls)) {
			$scanned_urls = [];
		}

		// Check how many times this URL was scanned
		$scan_count = $scanned_urls[$normalized_url] ?? 0;

		if ($scan_count >= $max_scans_per_url) {
			return [
				'allowed' => false,
				'message' => sprintf(
					'You have already scanned this website %d times today (maximum allowed). Please try a different URL or wait until tomorrow.',
					$scan_count
				),
				'code' => 'URL_SCAN_LIMIT_REACHED'
			];
		}

		// Calculate remaining scans for this URL
		$remaining = $max_scans_per_url - $scan_count;

		return [
			'allowed' => true,
			'message' => sprintf('URL can be scanned (%d of %d scans remaining)', $remaining, $max_scans_per_url),
			'code' => 'OK',
			'remaining' => $remaining,
			'scan_count' => $scan_count
		];
	}

	/**
	 * Record that a URL was scanned by this user
	 *
	 * @param string $url URL that was scanned
	 * @return void
	 * @since 1.0.0
	 */
	public function record_scanned_url(string $url): void
	{
		$normalized_url = $this->normalize_url_for_tracking($url);
		$user_id = $this->get_user_identifier();

		// Get existing scanned URLs (url => count format)
		$scanned_urls_key = 'seo_scanned_urls_' . $user_id;
		$scanned_urls = get_transient($scanned_urls_key);

		if (!is_array($scanned_urls)) {
			$scanned_urls = [];
		}

		// Increment scan count for this URL
		if (!isset($scanned_urls[$normalized_url])) {
			$scanned_urls[$normalized_url] = 1;
		} else {
			$scanned_urls[$normalized_url]++;
		}

		// Store until end of day (resets at midnight)
		$seconds_until_midnight = strtotime('tomorrow') - time();
		set_transient($scanned_urls_key, $scanned_urls, $seconds_until_midnight);
	}

	/**
	 * Normalize URL for consistent tracking
	 *
	 * @param string $url URL to normalize
	 * @return string Normalized URL
	 * @since 1.0.0
	 */
	private function normalize_url_for_tracking(string $url): string
	{
		// Parse URL
		$parsed = parse_url(strtolower(trim($url)));

		if (!$parsed || !isset($parsed['host'])) {
			return strtolower(trim($url));
		}

		// Remove www prefix for consistency
		$host = $parsed['host'];
		if (strpos($host, 'www.') === 0) {
			$host = substr($host, 4);
		}

		// Build normalized URL (scheme + host, ignore path/query for site-level tracking)
		$normalized = ($parsed['scheme'] ?? 'https') . '://' . $host;

		return $normalized;
	}
}
