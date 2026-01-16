<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package    SEO_Marketing_Tools
 * @subpackage Core
 * @since      1.0.0
 */

namespace SEO_Marketing_Tools\Core;

use SEO_Marketing_Tools\Admin\Admin;
use SEO_Marketing_Tools\Public\Public_Facing;
use SEO_Marketing_Tools\Ajax\Meta_Ajax;
use SEO_Marketing_Tools\Ajax\Links_Ajax;
use SEO_Marketing_Tools\Database\Database;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @var Loader
     */
    protected Loader $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @var string
     */
    protected string $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @var string
     */
    protected string $version;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct()
    {
        $this->version = SEO_MARKETING_TOOLS_VERSION;
        $this->plugin_name = 'seo-marketing-tools';

        $this->loader = new Loader();

        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_ajax_hooks();
        $this->define_cron_hooks();
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     */
    private function define_admin_hooks(): void
    {
        $admin = new Admin($this->plugin_name, $this->version);

        // Enqueue admin styles and scripts
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');

        // Add admin menu
        $this->loader->add_action('admin_menu', $admin, 'add_admin_menu');

        // Register settings
        $this->loader->add_action('admin_init', $admin, 'register_settings');

        // Add settings link on plugins page
        $this->loader->add_filter(
            'plugin_action_links_' . SEO_MARKETING_TOOLS_PLUGIN_BASENAME,
            $admin,
            'add_settings_link'
        );
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     */
    private function define_public_hooks(): void
    {
        $public = new Public_Facing($this->plugin_name, $this->version);

        // Enqueue public styles and scripts
        $this->loader->add_action('wp_enqueue_scripts', $public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $public, 'enqueue_scripts');

        // Register shortcodes
        $this->loader->add_action('init', $public, 'register_shortcodes');
    }

    /**
     * Register all AJAX handlers.
     */
    private function define_ajax_hooks(): void
    {
        // Meta Generator AJAX
        $meta_ajax = new Meta_Ajax();
        $this->loader->add_action('wp_ajax_seo_generate_meta', $meta_ajax, 'handle_generate_meta');
        $this->loader->add_action('wp_ajax_nopriv_seo_generate_meta', $meta_ajax, 'handle_generate_meta');

        // Link Checker AJAX
        $links_ajax = new Links_Ajax();
        $this->loader->add_action('wp_ajax_seo_check_links', $links_ajax, 'handle_check_links');
        $this->loader->add_action('wp_ajax_nopriv_seo_check_links', $links_ajax, 'handle_check_links');

        // Link Checker Progress AJAX
        $this->loader->add_action('wp_ajax_seo_get_scan_progress', $links_ajax, 'handle_get_scan_progress');
        $this->loader->add_action('wp_ajax_nopriv_seo_get_scan_progress', $links_ajax, 'handle_get_scan_progress');

        // Link Checker Cancel AJAX
        $this->loader->add_action('wp_ajax_seo_cancel_scan', $links_ajax, 'handle_cancel_scan');
        $this->loader->add_action('wp_ajax_nopriv_seo_cancel_scan', $links_ajax, 'handle_cancel_scan');

        // Keyword Density AJAX (URL fetch only)
        $this->loader->add_action('wp_ajax_seo_fetch_url_content', $meta_ajax, 'handle_fetch_url_content');
        $this->loader->add_action('wp_ajax_nopriv_seo_fetch_url_content', $meta_ajax, 'handle_fetch_url_content');

        // Rate limit status check
        $this->loader->add_action('wp_ajax_seo_get_rate_limit_status', $meta_ajax, 'handle_get_rate_limit_status');
        $this->loader->add_action('wp_ajax_nopriv_seo_get_rate_limit_status', $meta_ajax, 'handle_get_rate_limit_status');
    }

    /**
     * Register cron hooks for maintenance tasks.
     */
    private function define_cron_hooks(): void
    {
        // Schedule daily cleanup if not already scheduled
        if (!wp_next_scheduled('seo_tools_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'seo_tools_daily_cleanup');
        }

        // Hook the cleanup function
        add_action('seo_tools_daily_cleanup', function () {
            $database = new Database();
            $database->cleanup_old_rate_limits();
            $database->cleanup_old_logs();
        });
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run(): void
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it.
     *
     * @return string The name of the plugin.
     */
    public function get_plugin_name(): string
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @return Loader Orchestrates the hooks of the plugin.
     */
    public function get_loader(): Loader
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @return string The version number of the plugin.
     */
    public function get_version(): string
    {
        return $this->version;
    }
}
