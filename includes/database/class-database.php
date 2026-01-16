<?php
/**
 * Database Management Class
 *
 * Handles all database operations including table creation and schema updates.
 *
 * @package    SEO_Marketing_Tools
 * @subpackage Database
 * @since      1.0.0
 */

namespace SEO_Marketing_Tools\Database;

if (!defined('ABSPATH')) {
    exit;
}

class Database {
    
    /**
     * Create plugin database tables
     *
     * @since 1.0.0
     */
    public function create_tables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table 1: Rate Limiting
        $table_rate_limits = $wpdb->prefix . 'seo_rate_limits';
        
        $sql_rate_limits = "CREATE TABLE IF NOT EXISTS $table_rate_limits (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_ip VARCHAR(45) NOT NULL,
            tool_name VARCHAR(50) NOT NULL,
            request_count INT UNSIGNED DEFAULT 1,
            reset_date DATE NOT NULL,
            first_request DATETIME NOT NULL,
            last_request DATETIME NOT NULL,
            UNIQUE KEY unique_rate_limit (user_ip, tool_name, reset_date),
            INDEX idx_ip (user_ip),
            INDEX idx_tool (tool_name),
            INDEX idx_reset (reset_date)
        ) $charset_collate ENGINE=InnoDB;";
        
        // Table 2: Usage Logs
        $table_logs = $wpdb->prefix . 'seo_tools_logs';
        
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tool_name VARCHAR(50) NOT NULL,
            user_ip VARCHAR(45) NOT NULL,
            request_data TEXT,
            response_status VARCHAR(20) NOT NULL,
            api_tokens_used INT DEFAULT 0,
            cached TINYINT(1) DEFAULT 0,
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tool_date (tool_name, created_at),
            INDEX idx_ip_date (user_ip, created_at),
            INDEX idx_status (response_status)
        ) $charset_collate ENGINE=InnoDB;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_rate_limits);
        dbDelta($sql_logs);
        
        // Store database version
        update_option('seo_tools_db_version', '1.0.0');
    }
    
    /**
     * Drop plugin database tables (used on uninstall)
     *
     * @since 1.0.0
     */
    public function drop_tables(): void {
        global $wpdb;
        
        $table_rate_limits = $wpdb->prefix . 'seo_rate_limits';
        $table_logs = $wpdb->prefix . 'seo_tools_logs';
        
        $wpdb->query("DROP TABLE IF EXISTS $table_rate_limits");
        $wpdb->query("DROP TABLE IF EXISTS $table_logs");
        
        delete_option('seo_tools_db_version');
    }
    
    /**
     * Clean up old rate limit records
     *
     * @since 1.0.0
     */
    public function cleanup_old_rate_limits(): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'seo_rate_limits';
        
        // Delete records older than 7 days
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE reset_date < DATE_SUB(CURDATE(), INTERVAL %d DAY)",
                7
            )
        );
    }
    
    /**
     * Clean up old logs based on retention policy
     *
     * @since 1.0.0
     */
    public function cleanup_old_logs(): void {
        global $wpdb;
        
        $table = $wpdb->prefix . 'seo_tools_logs';
        $retention_days = (int) get_option('seo_tools_log_retention_days', 30);
        
        // Delete logs older than retention period
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            )
        );
    }
}
