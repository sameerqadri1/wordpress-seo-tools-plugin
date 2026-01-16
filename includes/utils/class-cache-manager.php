<?php

/**
 * Cache Manager Class
 *
 * Handles caching of API responses and expensive operations.
 *
 * @package    SEO_Marketing_Tools
 * @subpackage Utils
 * @since      1.0.0
 */

namespace SEO_Marketing_Tools\Utils;

if (!defined('ABSPATH')) {
	exit;
}

class Cache_Manager
{

	/**
	 * Cache duration in seconds (24 hours default)
	 *
	 * @var int
	 */
	private int $cache_duration;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->cache_duration = (int) get_option('seo_tools_cache_duration', 24 * HOUR_IN_SECONDS);
	}

	/**
	 * Generate cache key from input data
	 *
	 * @param string $prefix Cache key prefix (tool name)
	 * @param array $data Input data
	 * @return string Cache key
	 * @since 1.0.0
	 */
	public function generate_cache_key(string $prefix, array $data): string
	{
		// Sort array for consistent keys
		ksort($data);

		// Create hash of data
		$data_string = wp_json_encode($data);
		$hash = md5($data_string);

		// Include current date to auto-expire daily
		$date = current_time('Y-m-d');

		return sprintf('seo_cache_%s_%s_%s', $prefix, $date, $hash);
	}

	/**
	 * Get cached data
	 *
	 * @param string $cache_key Cache key
	 * @return mixed|null Cached data or null if not found/expired
	 * @since 1.0.0
	 */
	public function get(string $cache_key): mixed
	{
		$cached = get_transient($cache_key);

		if ($cached !== false) {
			// Verify cache structure
			if (is_array($cached) && isset($cached['data'], $cached['timestamp'])) {
				return $cached['data'];
			}
		}

		return null;
	}

	/**
	 * Set cached data
	 *
	 * @param string $cache_key Cache key
	 * @param mixed $data Data to cache
	 * @param int|null $duration Custom cache duration (null = use default)
	 * @return bool Success status
	 * @since 1.0.0
	 */
	public function set(string $cache_key, mixed $data, ?int $duration = null): bool
	{
		$duration = $duration ?? $this->cache_duration;

		$cache_data = [
			'data' => $data,
			'timestamp' => time(),
			'expires' => time() + $duration
		];

		return set_transient($cache_key, $cache_data, $duration);
	}

	/**
	 * Delete cached data
	 *
	 * @param string $cache_key Cache key
	 * @return bool Success status
	 * @since 1.0.0
	 */
	public function delete(string $cache_key): bool
	{
		return delete_transient($cache_key);
	}

	/**
	 * Clear all plugin caches
	 *
	 * @return int Number of caches cleared
	 * @since 1.0.0
	 */
	public function clear_all(): int
	{
		global $wpdb;

		$count = 0;

		// Delete all transients starting with our prefix
		$transients = $wpdb->get_col(
			"SELECT option_name FROM $wpdb->options 
            WHERE option_name LIKE '_transient_seo_cache_%'"
		);

		foreach ($transients as $transient) {
			// Remove '_transient_' prefix to get key
			$key = str_replace('_transient_', '', $transient);
			if (delete_transient($key)) {
				$count++;
			}
		}

		// Also clear timeout transients
		$timeouts = $wpdb->get_col(
			"SELECT option_name FROM $wpdb->options 
            WHERE option_name LIKE '_transient_timeout_seo_cache_%'"
		);

		foreach ($timeouts as $timeout) {
			$wpdb->delete($wpdb->options, ['option_name' => $timeout]);
		}

		return $count;
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Cache stats
	 * @since 1.0.0
	 */
	public function get_stats(): array
	{
		global $wpdb;

		// Count active caches
		$active_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM $wpdb->options 
            WHERE option_name LIKE '_transient_seo_cache_%'"
		);

		// Calculate total cache size (approximate)
		$cache_size = $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM $wpdb->options 
            WHERE option_name LIKE '_transient_seo_cache_%'"
		);

		return [
			'active_count' => (int) $active_count,
			'cache_size_bytes' => (int) $cache_size,
			'cache_size_kb' => round($cache_size / 1024, 2),
			'cache_duration_hours' => round($this->cache_duration / 3600, 2)
		];
	}

	/**
	 * Check if cache exists and is valid
	 *
	 * @param string $cache_key Cache key
	 * @return bool True if cache exists and valid
	 * @since 1.0.0
	 */
	public function has(string $cache_key): bool
	{
		return get_transient($cache_key) !== false;
	}

	/**
	 * Get or set cache (convenience method)
	 *
	 * @param string $cache_key Cache key
	 * @param callable $callback Callback to generate data if cache miss
	 * @param int|null $duration Custom cache duration
	 * @return mixed Cached or generated data
	 * @since 1.0.0
	 */
	public function remember(string $cache_key, callable $callback, ?int $duration = null): mixed
	{
		$cached = $this->get($cache_key);

		if ($cached !== null) {
			return $cached;
		}

		// Cache miss - generate data
		$data = $callback();

		// Cache the result
		$this->set($cache_key, $data, $duration);

		return $data;
	}

	/**
	 * Warm cache for common queries (admin function)
	 *
	 * @param string $tool_name Tool to warm cache for
	 * @return int Number of caches created
	 * @since 1.0.0
	 */
	public function warm_cache(string $tool_name): int
	{
		// This would be tool-specific
		// For now, return 0 (can be implemented per tool)
		return 0;
	}
}
