<?php

/**
 * Plugin Name:       SEO Marketing Tools
 * Plugin URI:        https://saasmarketing.ca/seo-tools/
 * Description:       Professional SEO tools suite including AI-powered meta generator, keyword density checker, and broken link checker.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Sameer Qadri
 * Author URI:        https://github.com/sameerqadri
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       seo-marketing-tools
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 * Using SemVer - https://semver.org
 */
define('SEO_MARKETING_TOOLS_VERSION', '1.0.0');
define('SEO_MARKETING_TOOLS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SEO_MARKETING_TOOLS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SEO_MARKETING_TOOLS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check PHP version requirement
 */
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p>';
        echo '<strong>SEO Marketing Tools:</strong> This plugin requires PHP 8.1 or higher. ';
        echo 'You are running PHP ' . PHP_VERSION . '. Please upgrade PHP to use this plugin.';
        echo '</p></div>';
    });
    return;
}

/**
 * Check WordPress version requirement
 */
global $wp_version;
if (version_compare($wp_version, '6.0', '<')) {
    add_action('admin_notices', function () {
        global $wp_version;
        echo '<div class="error"><p>';
        echo '<strong>SEO Marketing Tools:</strong> This plugin requires WordPress 6.0 or higher. ';
        echo 'You are running WordPress ' . $wp_version . '. Please upgrade WordPress to use this plugin.';
        echo '</p></div>';
    });
    return;
}

/**
 * Autoloader for plugin classes
 */
spl_autoload_register(function ($class) {
    // Project-specific namespace prefix
    $prefix = 'SEO_Marketing_Tools\\';

    // Base directory for the namespace prefix
    $base_dir = __DIR__ . '/includes/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace namespace separators with directory separators
    $relative_class = str_replace('\\', '/', $relative_class);

    // Split into directory path and class name
    $parts = explode('/', $relative_class);
    $class_name = array_pop($parts);
    $directory = implode('/', $parts);

    // Convert class name: Plugin -> class-plugin.php
    // Convert from CamelCase to kebab-case with class- prefix
    $class_name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name));
    $file_name = 'class-' . $class_name . '.php';

    // Convert directory names to lowercase
    $directory = strtolower($directory);

    // Build full file path
    $file = $base_dir . ($directory ? $directory . '/' : '') . $file_name;

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * The code that runs during plugin activation.
 */
function activate_seo_marketing_tools()
{
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Create database tables
    $database = new SEO_Marketing_Tools\Database\Database();
    $database->create_tables();

    // Set default options
    add_option('seo_tools_version', SEO_MARKETING_TOOLS_VERSION);
    add_option('seo_tools_cache_duration', 24 * HOUR_IN_SECONDS);
    add_option('seo_tools_rate_limit_meta', 5);
    add_option('seo_tools_rate_limit_links', 5);
    add_option('seo_tools_rate_limit_kw_url', 20);
    add_option('seo_tools_enable_logging', 'yes');
    add_option('seo_tools_max_links_check', 50);
    add_option('seo_tools_link_timeout', 5);
    add_option('seo_tools_log_retention_days', 30);

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_seo_marketing_tools()
{
    // Clean up transients
    delete_transient('seo_tools_daily_stats');

    // Flush rewrite rules
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'activate_seo_marketing_tools');
register_deactivation_hook(__FILE__, 'deactivate_seo_marketing_tools');

/**
 * Begins execution of the plugin.
 */
function run_seo_marketing_tools()
{
    $plugin = new SEO_Marketing_Tools\Core\Plugin();
    $plugin->run();
}

run_seo_marketing_tools();
