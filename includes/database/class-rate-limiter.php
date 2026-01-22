<?php

/**
 * Rate Limiter Class
 *
 * Database-based rate limiting for API calls and tool usage.
 * Thread-safe using MySQL atomic operations.
 *
 * @package    SEO_Marketing_Tools
 * @subpackage Database
 * @since      1.0.0
 */

namespace SEO_Marketing_Tools\Database;

if (!defined('ABSPATH')) {
    exit;
}

class Rate_Limiter
{

    /**
     * Maximum concurrent requests per tool per IP
     */
    private const MAX_CONCURRENT_REQUESTS = 5;

    /**
     * Maximum requests per minute per tool per IP
     */
    private const MAX_REQUESTS_PER_MINUTE = 2;

    /**
     * Gemini API global limits (free tier)
     */
    private const GEMINI_RPM_LIMIT = 10;        // Requests per minute
    private const GEMINI_RPD_LIMIT = 20;        // Requests per day
    private const GEMINI_TPM_LIMIT = 250000;    // Tokens per minute

    /**
     * Check if request is allowed and increment counter atomically
     *
     * @param string $tool_name Tool identifier (meta_generator, link_checker, etc.)
     * @param string|null $ip IP address (defaults to current user IP)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => string]
     * @since 1.0.0
     */
    public function check_rate_limit(string $tool_name, ?string $ip = null): array
    {
        global $wpdb;

        $ip = $ip ?? $this->get_client_ip();
        $limit = $this->get_limit_for_tool($tool_name);
        $table = $wpdb->prefix . 'seo_rate_limits';
        $reset_date = current_time('Y-m-d');

        // Start transaction for atomic operation
        $wpdb->query('START TRANSACTION');

        try {
            // Try to get existing record with lock
            $record = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table 
                    WHERE user_ip = %s 
                    AND tool_name = %s 
                    AND reset_date = %s 
                    FOR UPDATE",
                    $ip,
                    $tool_name,
                    $reset_date
                )
            );

            if ($record) {
                // Record exists - check if limit reached
                if ($record->request_count >= $limit) {
                    $wpdb->query('COMMIT');

                    return [
                        'allowed' => false,
                        'remaining' => 0,
                        'reset_time' => $this->get_reset_time(),
                        'current_count' => (int) $record->request_count
                    ];
                }

                // Increment counter
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE $table 
                        SET request_count = request_count + 1,
                            last_request = %s
                        WHERE id = %d",
                        current_time('mysql'),
                        $record->id
                    )
                );

                $new_count = $record->request_count + 1;
            } else {
                // Create new record
                $wpdb->insert(
                    $table,
                    [
                        'user_ip' => $ip,
                        'tool_name' => $tool_name,
                        'request_count' => 1,
                        'reset_date' => $reset_date,
                        'first_request' => current_time('mysql'),
                        'last_request' => current_time('mysql')
                    ],
                    ['%s', '%s', '%d', '%s', '%s', '%s']
                );

                $new_count = 1;
            }

            $wpdb->query('COMMIT');

            return [
                'allowed' => true,
                'remaining' => max(0, $limit - $new_count),
                'reset_time' => $this->get_reset_time(),
                'current_count' => $new_count
            ];
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');

            // Log error
            error_log('Rate Limiter Error: ' . $e->getMessage());

            // Fail open (allow request) on error
            return [
                'allowed' => true,
                'remaining' => $limit,
                'reset_time' => $this->get_reset_time(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check rate limit without incrementing (read-only)
     *
     * @param string $tool_name Tool identifier
     * @param string|null $ip IP address
     * @return array Status information
     * @since 1.0.0
     */
    public function get_rate_limit_status(string $tool_name, ?string $ip = null): array
    {
        global $wpdb;

        $ip = $ip ?? $this->get_client_ip();
        $limit = $this->get_limit_for_tool($tool_name);
        $table = $wpdb->prefix . 'seo_rate_limits';
        $reset_date = current_time('Y-m-d');

        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table 
                WHERE user_ip = %s 
                AND tool_name = %s 
                AND reset_date = %s",
                $ip,
                $tool_name,
                $reset_date
            )
        );

        $current_count = $record ? (int) $record->request_count : 0;

        return [
            'allowed' => $current_count < $limit,
            'remaining' => max(0, $limit - $current_count),
            'current_count' => $current_count,
            'limit' => $limit,
            'reset_time' => $this->get_reset_time()
        ];
    }

    /**
     * Get daily limit for specific tool
     *
     * @param string $tool_name Tool identifier
     * @return int Daily limit
     * @since 1.0.0
     */
    private function get_limit_for_tool(string $tool_name): int
    {
        $limits = [
            'meta_generator' => (int) get_option('seo_tools_rate_limit_meta', 5),
            'link_checker' => (int) get_option('seo_tools_rate_limit_links', 5),
            'keyword_density_url' => (int) get_option('seo_tools_rate_limit_kw_url', 20),
        ];

        return $limits[$tool_name] ?? 10; // Default to 10 if not found
    }

    /**
     * Get client IP address with proxy support
     *
     * @return string Client IP address
     * @since 1.0.0
     */
    private function get_client_ip(): string
    {
        // Check for proxy headers
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];

                // Handle multiple IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Calculate time until rate limit resets (midnight)
     *
     * @return string Human-readable time until reset
     * @since 1.0.0
     */
    private function get_reset_time(): string
    {
        $now = current_time('timestamp');
        $midnight = strtotime('tomorrow midnight', $now);
        $seconds = $midnight - $now;

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return sprintf('%d hours %d minutes', $hours, $minutes);
        }

        return sprintf('%d minutes', $minutes);
    }

    /**
     * Reset rate limit for specific user/tool (admin function)
     *
     * @param string $tool_name Tool identifier
     * @param string $ip IP address
     * @return bool Success status
     * @since 1.0.0
     */
    public function reset_rate_limit(string $tool_name, string $ip): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'seo_rate_limits';
        $reset_date = current_time('Y-m-d');

        $result = $wpdb->delete(
            $table,
            [
                'user_ip' => $ip,
                'tool_name' => $tool_name,
                'reset_date' => $reset_date
            ],
            ['%s', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Check if user is admin and should bypass limits
     *
     * @return bool Whether to bypass rate limits
     * @since 1.0.0
     */
    public function should_bypass_rate_limit(): bool
    {
        // Check if logged in as admin
        if (current_user_can('manage_options')) {
            return true;
        }

        return false;
    }

    /**
     * Check concurrent request limit (prevents overwhelming server)
     *
     * @param string $tool_name Tool identifier
     * @param string|null $ip IP address
     * @return array ['allowed' => bool, 'retry_after' => int, 'current_count' => int]
     * @since 1.0.0
     */
    public function check_concurrent_limit(string $tool_name, ?string $ip = null): array
    {
        $ip = $ip ?? $this->get_client_ip();
        $key = "seo_concurrent_{$tool_name}_" . md5($ip);

        $current = (int) get_transient($key);

        if ($current >= self::MAX_CONCURRENT_REQUESTS) {
            return [
                'allowed' => false,
                'retry_after' => 60,
                'current_count' => $current,
                'max_concurrent' => self::MAX_CONCURRENT_REQUESTS
            ];
        }

        // Increment concurrent counter with 60-second expiry
        set_transient($key, $current + 1, 60);

        return [
            'allowed' => true,
            'current_count' => $current + 1,
            'max_concurrent' => self::MAX_CONCURRENT_REQUESTS
        ];
    }

    /**
     * Release concurrent request slot
     *
     * @param string $tool_name Tool identifier
     * @param string|null $ip IP address
     * @return void
     * @since 1.0.0
     */
    public function release_concurrent_slot(string $tool_name, ?string $ip = null): void
    {
        $ip = $ip ?? $this->get_client_ip();
        $key = "seo_concurrent_{$tool_name}_" . md5($ip);

        $current = (int) get_transient($key);
        if ($current > 0) {
            set_transient($key, $current - 1, 60);
        }
    }

    /**
     * Check per-minute rate limit (prevents burst traffic)
     *
     * @param string $tool_name Tool identifier
     * @param string|null $ip IP address
     * @return array ['allowed' => bool, 'retry_after' => int, 'current_count' => int]
     * @since 1.0.0
     */
    public function check_per_minute_limit(string $tool_name, ?string $ip = null): array
    {
        $ip = $ip ?? $this->get_client_ip();
        $current_minute = date('Y-m-d-H-i');
        $key = "seo_minute_{$tool_name}_{$current_minute}_" . md5($ip);

        $count = (int) get_transient($key);

        if ($count >= self::MAX_REQUESTS_PER_MINUTE) {
            return [
                'allowed' => false,
                'retry_after' => 60,
                'current_count' => $count,
                'max_per_minute' => self::MAX_REQUESTS_PER_MINUTE
            ];
        }

        // Increment minute counter with 60-second expiry
        set_transient($key, $count + 1, 60);

        return [
            'allowed' => true,
            'current_count' => $count + 1,
            'max_per_minute' => self::MAX_REQUESTS_PER_MINUTE
        ];
    }

    /**
     * Get cached URL content
     *
     * @param string $url URL to check cache for
     * @return array|null ['text' => string, 'word_count' => int] or null if not cached
     * @since 1.0.0
     */
    public function get_cached_url_content(string $url): ?array
    {
        $cache_key = 'seo_url_content_' . md5($url);
        $cached = get_transient($cache_key);

        return $cached !== false ? $cached : null;
    }

    /**
     * Cache URL content for 15 minutes
     *
     * @param string $url URL being cached
     * @param string $text Extracted text content
     * @param int $word_count Word count
     * @return bool Success status
     * @since 1.0.0
     */
    public function cache_url_content(string $url, string $text, int $word_count): bool
    {
        $cache_key = 'seo_url_content_' . md5($url);

        return set_transient($cache_key, [
            'text' => $text,
            'word_count' => $word_count,
            'cached_at' => current_time('mysql'),
            'cached_timestamp' => time(),
            'expires_at' => time() + 900  // 15 minutes from now
        ], 900); // 15 minutes
    }

    /**
     * Check global Gemini RPD (Requests Per Day) limit
     *
     * @return array ['allowed' => bool, 'current_count' => int, 'remaining' => int, 'retry_after' => string]
     * @since 1.0.0
     */
    public function check_global_gemini_rpd(): array
    {
        // Get current date
        $current_date = date('Y-m-d');
        $key = "seo_gemini_rpd_{$current_date}";

        // Get current count
        $current_count = (int) get_transient($key);

        // Check limit
        if ($current_count >= self::GEMINI_RPD_LIMIT) {
            // Calculate time until midnight
            $midnight = strtotime('tomorrow midnight');
            $seconds_until_reset = $midnight - time();
            $hours = floor($seconds_until_reset / 3600);
            $minutes = floor(($seconds_until_reset % 3600) / 60);

            $retry_time = $hours > 0
                ? sprintf('%d hours %d minutes', $hours, $minutes)
                : sprintf('%d minutes', $minutes);

            return [
                'allowed' => false,
                'current_count' => $current_count,
                'limit' => self::GEMINI_RPD_LIMIT,
                'remaining' => 0,
                'retry_after' => $retry_time,
                'message' => "Daily API limit reached ({$current_count}/{$current_count} requests). Resets in {$retry_time}."
            ];
        }

        // Increment counter (expires at midnight)
        $seconds_until_midnight = strtotime('tomorrow midnight') - time();
        set_transient($key, $current_count + 1, $seconds_until_midnight);

        return [
            'allowed' => true,
            'current_count' => $current_count + 1,
            'limit' => self::GEMINI_RPD_LIMIT,
            'remaining' => self::GEMINI_RPD_LIMIT - ($current_count + 1)
        ];
    }

    /**
     * Check global Gemini RPM (Requests Per Minute) limit
     *
     * @return array ['allowed' => bool, 'current_count' => int, 'remaining' => int, 'retry_after' => int]
     * @since 1.0.0
     */
    public function check_global_gemini_rpm(): array
    {
        // Get current minute
        $current_minute = date('Y-m-d-H-i');
        $key = "seo_gemini_rpm_{$current_minute}";

        // Get current count
        $current_count = (int) get_transient($key);

        // Check limit
        if ($current_count >= self::GEMINI_RPM_LIMIT) {
            // Calculate seconds until next minute
            $seconds_until_reset = 60 - (int) date('s');

            return [
                'allowed' => false,
                'current_count' => $current_count,
                'limit' => self::GEMINI_RPM_LIMIT,
                'remaining' => 0,
                'retry_after' => $seconds_until_reset,
                'message' => "High traffic. Please try again in {$seconds_until_reset} seconds."
            ];
        }

        // Increment counter (expires in 60 seconds)
        set_transient($key, $current_count + 1, 60);

        return [
            'allowed' => true,
            'current_count' => $current_count + 1,
            'limit' => self::GEMINI_RPM_LIMIT,
            'remaining' => self::GEMINI_RPM_LIMIT - ($current_count + 1)
        ];
    }

    /**
     * Check global Gemini TPM (Tokens Per Minute) limit
     *
     * @param int $estimated_tokens Estimated tokens for this request
     * @return array ['allowed' => bool, 'current_tokens' => int, 'remaining' => int, 'retry_after' => int]
     * @since 1.0.0
     */
    public function check_global_gemini_tpm(int $estimated_tokens): array
    {
        // Get current minute
        $current_minute = date('Y-m-d-H-i');
        $key = "seo_gemini_tpm_{$current_minute}";

        // Get current token count
        $current_tokens = (int) get_transient($key);
        $new_total = $current_tokens + $estimated_tokens;

        // Check limit
        if ($new_total > self::GEMINI_TPM_LIMIT) {
            // Calculate seconds until next minute
            $seconds_until_reset = 60 - (int) date('s');

            return [
                'allowed' => false,
                'current_tokens' => $current_tokens,
                'estimated_request_tokens' => $estimated_tokens,
                'limit' => self::GEMINI_TPM_LIMIT,
                'remaining' => max(0, self::GEMINI_TPM_LIMIT - $current_tokens),
                'retry_after' => $seconds_until_reset,
                'message' => "Token limit approaching. Please try again in {$seconds_until_reset} seconds."
            ];
        }

        // Increment token counter (expires in 60 seconds)
        set_transient($key, $new_total, 60);

        return [
            'allowed' => true,
            'current_tokens' => $new_total,
            'estimated_request_tokens' => $estimated_tokens,
            'limit' => self::GEMINI_TPM_LIMIT,
            'remaining' => self::GEMINI_TPM_LIMIT - $new_total
        ];
    }

    /**
     * Get global Gemini API status (for admin dashboard)
     *
     * @return array Current status of all limits
     * @since 1.0.0
     */
    public function get_global_gemini_status(): array
    {
        $current_date = date('Y-m-d');
        $current_minute = date('Y-m-d-H-i');

        $rpd_count = (int) get_transient("seo_gemini_rpd_{$current_date}");
        $rpm_count = (int) get_transient("seo_gemini_rpm_{$current_minute}");
        $tpm_count = (int) get_transient("seo_gemini_tpm_{$current_minute}");

        return [
            'rpd' => [
                'current' => $rpd_count,
                'limit' => self::GEMINI_RPD_LIMIT,
                'remaining' => max(0, self::GEMINI_RPD_LIMIT - $rpd_count),
                'percentage' => round(($rpd_count / self::GEMINI_RPD_LIMIT) * 100, 1)
            ],
            'rpm' => [
                'current' => $rpm_count,
                'limit' => self::GEMINI_RPM_LIMIT,
                'remaining' => max(0, self::GEMINI_RPM_LIMIT - $rpm_count),
                'percentage' => round(($rpm_count / self::GEMINI_RPM_LIMIT) * 100, 1)
            ],
            'tpm' => [
                'current' => $tpm_count,
                'limit' => self::GEMINI_TPM_LIMIT,
                'remaining' => max(0, self::GEMINI_TPM_LIMIT - $tpm_count),
                'percentage' => round(($tpm_count / self::GEMINI_TPM_LIMIT) * 100, 1)
            ]
        ];
    }
}
