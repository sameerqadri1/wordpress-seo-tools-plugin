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

class Rate_Limiter {
    
    /**
     * Check if request is allowed and increment counter atomically
     *
     * @param string $tool_name Tool identifier (meta_generator, link_checker, etc.)
     * @param string|null $ip IP address (defaults to current user IP)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => string]
     * @since 1.0.0
     */
    public function check_rate_limit(string $tool_name, ?string $ip = null): array {
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
    public function get_rate_limit_status(string $tool_name, ?string $ip = null): array {
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
    private function get_limit_for_tool(string $tool_name): int {
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
    private function get_client_ip(): string {
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
    private function get_reset_time(): string {
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
    public function reset_rate_limit(string $tool_name, string $ip): bool {
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
    public function should_bypass_rate_limit(): bool {
        // Check if logged in as admin
        if (current_user_can('manage_options')) {
            return true;
        }
        
        return false;
    }
}
