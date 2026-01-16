<?php
/**
 * Logger Class
 *
 * Handles logging of tool usage, errors, and analytics.
 *
 * @package    SEO_Marketing_Tools
 * @subpackage Utils
 * @since      1.0.0
 */

namespace SEO_Marketing_Tools\Utils;

if (!defined('ABSPATH')) {
    exit;
}

class Logger {
    
    /**
     * Log tool usage
     *
     * @param string $tool_name Tool identifier
     * @param array $request_data Request parameters (sanitized)
     * @param string $response_status Status (success, error, rate_limited, etc.)
     * @param array $options Additional options (api_tokens_used, cached, error_message)
     * @return bool Success status
     * @since 1.0.0
     */
    public function log_usage(
        string $tool_name,
        array $request_data,
        string $response_status,
        array $options = []
    ): bool {
        global $wpdb;
        
        // Check if logging is enabled
        if (get_option('seo_tools_enable_logging', 'yes') !== 'yes') {
            return false;
        }
        
        $table = $wpdb->prefix . 'seo_tools_logs';
        
        // Get client IP (hashed for privacy)
        $ip = $this->get_client_ip();
        $hashed_ip = $this->hash_ip($ip);
        
        // Sanitize request data (remove sensitive info)
        $safe_request_data = $this->sanitize_request_data($request_data);
        
        $result = $wpdb->insert(
            $table,
            [
                'tool_name' => sanitize_text_field($tool_name),
                'user_ip' => $hashed_ip,
                'request_data' => wp_json_encode($safe_request_data),
                'response_status' => sanitize_text_field($response_status),
                'api_tokens_used' => (int) ($options['api_tokens_used'] ?? 0),
                'cached' => (int) ($options['cached'] ?? 0),
                'error_message' => isset($options['error_message']) 
                    ? sanitize_text_field($options['error_message']) 
                    : null,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Get usage statistics for admin dashboard
     *
     * @param int $days Number of days to retrieve (default 30)
     * @return array Statistics data
     * @since 1.0.0
     */
    public function get_usage_stats(int $days = 30): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'seo_tools_logs';
        
        // Total usage by tool
        $usage_by_tool = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT tool_name, COUNT(*) as count, 
                        SUM(CASE WHEN cached = 1 THEN 1 ELSE 0 END) as cached_count,
                        SUM(api_tokens_used) as total_tokens
                FROM $table 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                GROUP BY tool_name
                ORDER BY count DESC",
                $days
            ),
            ARRAY_A
        );
        
        // Daily usage trend
        $daily_usage = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) as date, COUNT(*) as count
                FROM $table 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC",
                $days
            ),
            ARRAY_A
        );
        
        // Unique users (hashed IPs)
        $unique_users = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_ip) 
                FROM $table 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
        
        // Error rate
        $error_stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN response_status = 'error' THEN 1 ELSE 0 END) as errors
                FROM $table 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ),
            ARRAY_A
        );
        
        $error_rate = $error_stats['total'] > 0 
            ? ($error_stats['errors'] / $error_stats['total']) * 100 
            : 0;
        
        return [
            'usage_by_tool' => $usage_by_tool,
            'daily_usage' => $daily_usage,
            'unique_users' => (int) $unique_users,
            'error_rate' => round($error_rate, 2),
            'total_requests' => (int) $error_stats['total'],
            'period_days' => $days
        ];
    }
    
    /**
     * Get today's usage for quota monitoring
     *
     * @return array Today's statistics
     * @since 1.0.0
     */
    public function get_today_stats(): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'seo_tools_logs';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_requests,
                COUNT(DISTINCT user_ip) as unique_users,
                SUM(api_tokens_used) as total_tokens,
                SUM(CASE WHEN cached = 1 THEN 1 ELSE 0 END) as cached_requests
            FROM $table 
            WHERE DATE(created_at) = CURDATE()",
            ARRAY_A
        );
        
        return [
            'total_requests' => (int) ($stats['total_requests'] ?? 0),
            'unique_users' => (int) ($stats['unique_users'] ?? 0),
            'total_tokens' => (int) ($stats['total_tokens'] ?? 0),
            'cached_requests' => (int) ($stats['cached_requests'] ?? 0),
            'cache_hit_rate' => $stats['total_requests'] > 0 
                ? round(($stats['cached_requests'] / $stats['total_requests']) * 100, 2)
                : 0
        ];
    }
    
    /**
     * Get client IP address
     *
     * @return string IP address
     * @since 1.0.0
     */
    private function get_client_ip(): string {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Hash IP for privacy (GDPR compliance)
     *
     * @param string $ip IP address
     * @return string Hashed IP
     * @since 1.0.0
     */
    private function hash_ip(string $ip): string {
        // Use WordPress salt for hashing
        $salt = defined('AUTH_KEY') ? AUTH_KEY : 'seo_tools_salt';
        return hash('sha256', $ip . $salt);
    }
    
    /**
     * Sanitize request data (remove sensitive information)
     *
     * @param array $data Request data
     * @return array Sanitized data
     * @since 1.0.0
     */
    private function sanitize_request_data(array $data): array {
        // Remove sensitive keys
        $sensitive_keys = ['password', 'api_key', 'secret', 'token', 'nonce'];
        
        foreach ($sensitive_keys as $key) {
            if (isset($data[$key])) {
                $data[$key] = '[REDACTED]';
            }
        }
        
        // Limit data size (max 1000 chars per field)
        foreach ($data as $key => $value) {
            if (is_string($value) && strlen($value) > 1000) {
                $data[$key] = substr($value, 0, 1000) . '... [TRUNCATED]';
            }
        }
        
        return $data;
    }
    
    /**
     * Export logs as CSV (admin function)
     *
     * @param int $days Number of days to export
     * @return string CSV content
     * @since 1.0.0
     */
    public function export_logs_csv(int $days = 30): string {
        global $wpdb;
        
        $table = $wpdb->prefix . 'seo_tools_logs';
        
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT tool_name, user_ip, response_status, 
                        api_tokens_used, cached, created_at
                FROM $table 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                ORDER BY created_at DESC",
                $days
            ),
            ARRAY_A
        );
        
        // Create CSV
        $csv = "Tool,User IP (Hashed),Status,API Tokens,Cached,Timestamp\n";
        
        foreach ($logs as $log) {
            $csv .= sprintf(
                "%s,%s,%s,%d,%s,%s\n",
                $log['tool_name'],
                $log['user_ip'],
                $log['response_status'],
                $log['api_tokens_used'],
                $log['cached'] ? 'Yes' : 'No',
                $log['created_at']
            );
        }
        
        return $csv;
    }
}
