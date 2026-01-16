<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @package    SEO_Marketing_Tools
 * @subpackage Public
 * @since      1.0.0
 */

namespace SEO_Marketing_Tools\Public;

if (!defined('ABSPATH')) {
	exit;
}

class Public_Facing
{

	/**
	 * The ID of this plugin.
	 *
	 * @var string
	 */
	private string $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version The version of this plugin.
	 */
	public function __construct(string $plugin_name, string $version)
	{
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 */
	public function enqueue_styles(): void
	{
		wp_enqueue_style(
			$this->plugin_name,
			SEO_MARKETING_TOOLS_PLUGIN_URL . 'assets/css/public.css',
			[],
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 */
	public function enqueue_scripts(): void
	{
		// Common JS
		wp_enqueue_script(
			$this->plugin_name . '-common',
			SEO_MARKETING_TOOLS_PLUGIN_URL . 'assets/js/common.js',
			['jquery'],
			$this->version,
			true
		);

		// Localize script with data
		wp_localize_script(
			$this->plugin_name . '-common',
			'seoToolsConfig',
			[
				'ajax_url' => admin_url('admin-ajax.php'),
				'site_url' => get_site_url(),
				'recaptcha_site_key' => get_option('seo_tools_recaptcha_site_key', ''),
				'recaptcha_version' => get_option('seo_tools_recaptcha_version', 'v2'),
				'nonces' => [
					'meta' => wp_create_nonce('seo_meta_nonce'),
					'links' => wp_create_nonce('seo_links_nonce'),
					'keyword' => wp_create_nonce('seo_keyword_nonce')
				]
			]
		);

		// Load reCAPTCHA script
		$recaptcha_key = get_option('seo_tools_recaptcha_site_key', '');
		if (!empty($recaptcha_key)) {
			$recaptcha_version = get_option('seo_tools_recaptcha_version', 'v2');

			if ($recaptcha_version === 'v3') {
				wp_enqueue_script(
					'google-recaptcha',
					'https://www.google.com/recaptcha/api.js?render=' . $recaptcha_key,
					[],
					null,
					true
				);
			} else {
				wp_enqueue_script(
					'google-recaptcha',
					'https://www.google.com/recaptcha/api.js',
					[],
					null,
					true
				);
			}
		}
	}

	/**
	 * Register all shortcodes.
	 */
	public function register_shortcodes(): void
	{
		add_shortcode('seo_tools_hub', [$this, 'hub_shortcode']);
		add_shortcode('seo_meta_generator', [$this, 'meta_generator_shortcode']);
		add_shortcode('seo_keyword_density', [$this, 'keyword_density_shortcode']);
		add_shortcode('seo_broken_link_checker', [$this, 'broken_link_checker_shortcode']);
	}

	/**
	 * Hub page shortcode.
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function hub_shortcode($atts = []): string
	{
		// Enqueue tool-specific scripts
		wp_enqueue_script(
			$this->plugin_name . '-hub',
			SEO_MARKETING_TOOLS_PLUGIN_URL . 'assets/js/hub.js',
			['jquery', $this->plugin_name . '-common'],
			$this->version,
			true
		);

		ob_start();
		include SEO_MARKETING_TOOLS_PLUGIN_DIR . 'templates/hub.php';
		return ob_get_clean();
	}

	/**
	 * Meta generator shortcode.
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function meta_generator_shortcode($atts = []): string
	{
		// Enqueue tool-specific scripts
		wp_enqueue_script(
			$this->plugin_name . '-meta',
			SEO_MARKETING_TOOLS_PLUGIN_URL . 'assets/js/meta-generator.js',
			['jquery', $this->plugin_name . '-common'],
			$this->version,
			true
		);

		ob_start();
		include SEO_MARKETING_TOOLS_PLUGIN_DIR . 'templates/meta-generator.php';
		return ob_get_clean();
	}

	/**
	 * Keyword density shortcode.
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function keyword_density_shortcode($atts = []): string
	{
		// Enqueue tool-specific scripts
		wp_enqueue_script(
			$this->plugin_name . '-keyword',
			SEO_MARKETING_TOOLS_PLUGIN_URL . 'assets/js/keyword-density.js',
			['jquery', $this->plugin_name . '-common'],
			$this->version,
			true
		);

		ob_start();
		include SEO_MARKETING_TOOLS_PLUGIN_DIR . 'templates/keyword-density.php';
		return ob_get_clean();
	}

	/**
	 * Broken link checker shortcode.
	 *
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function broken_link_checker_shortcode($atts = []): string
	{
		// Enqueue tool-specific scripts
		wp_enqueue_script(
			$this->plugin_name . '-links',
			SEO_MARKETING_TOOLS_PLUGIN_URL . 'assets/js/broken-links.js',
			['jquery', $this->plugin_name . '-common'],
			$this->version,
			true
		);

		ob_start();
		include SEO_MARKETING_TOOLS_PLUGIN_DIR . 'templates/broken-links.php';
		return ob_get_clean();
	}
}
