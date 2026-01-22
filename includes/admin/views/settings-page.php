<?php

/**
 * Admin Settings Page Template
 *
 * @package SEO_Marketing_Tools
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Save settings message
if (isset($_GET['settings-updated'])) {
    add_settings_error('seo_tools_messages', 'seo_tools_message', 'Settings Saved', 'updated');
}

settings_errors('seo_tools_messages');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="seo-tools-admin-container">
        <!-- Statistics Dashboard -->
        <div class="seo-tools-dashboard">
            <h2>📊 Today's Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html($stats['total_requests']); ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html($stats['unique_users']); ?></div>
                    <div class="stat-label">Unique Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html($stats['cache_hit_rate']); ?>%</div>
                    <div class="stat-label">Cache Hit Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html($cache_stats['active_count']); ?></div>
                    <div class="stat-label">Active Caches</div>
                </div>
            </div>

            <?php if ($stats['total_requests'] > 800): ?>
                <div class="notice notice-warning">
                    <p>⚠️ <strong>Approaching API Limit:</strong> You've used <?php echo esc_html($stats['total_requests']); ?> requests today. Consider upgrading to paid tier.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Settings Form -->
        <form method="post" action="options.php">
            <?php settings_fields('seo_tools_options'); ?>

            <!-- API Configuration -->
            <div class="seo-tools-section">
                <h2>🤖 Gemini API Configuration</h2>
                <p class="description">
                    Get your free API key from <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="seo_tools_gemini_api_key">Gemini API Key</label>
                        </th>
                        <td>
                            <input type="password"
                                id="seo_tools_gemini_api_key"
                                name="seo_tools_gemini_api_key"
                                value="<?php echo esc_attr(get_option('seo_tools_gemini_api_key')); ?>"
                                class="regular-text"
                                placeholder="AIzaSy..." />
                            <p class="description">
                                Your Google Gemini API key. Free tier: 1,000 requests/day.
                            </p>
                            <button type="button" id="test-api-key" class="button">Test API Connection</button>
                            <span id="api-test-result"></span>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="seo_tools_gemini_model">Model</label>
                        </th>
                        <td>
                            <select id="seo_tools_gemini_model" name="seo_tools_gemini_model">
                                <option value="gemini-2.5-flash-lite" <?php selected(get_option('seo_tools_gemini_model', 'gemini-2.5-flash-lite'), 'gemini-2.5-flash-lite'); ?>>
                                    Gemini 2.5 Flash-Lite (Recommended - Free Tier: 10 RPM, 20 RPD)
                                </option>
                                <option value="gemini-2.0-flash-lite" <?php selected(get_option('seo_tools_gemini_model'), 'gemini-2.0-flash-lite'); ?>>
                                    Gemini 2.0 Flash-Lite (Legacy)
                                </option>
                                <option value="gemini-1.5-flash" <?php selected(get_option('seo_tools_gemini_model'), 'gemini-1.5-flash'); ?>>
                                    Gemini 1.5 Flash (Paid)
                                </option>
                                <option value="gemini-1.5-pro" <?php selected(get_option('seo_tools_gemini_model'), 'gemini-1.5-pro'); ?>>
                                    Gemini 1.5 Pro (Paid)
                                </option>
                            </select>
                            <p class="description">Select the Gemini model to use. 2.5 Flash-Lite is recommended for free tier (10 requests/min, 20/day, 250K tokens/min).</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- reCAPTCHA Configuration -->
            <div class="seo-tools-section">
                <h2>🔒 reCAPTCHA Configuration</h2>
                <p class="description">
                    Get your reCAPTCHA keys from <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin</a>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="seo_tools_recaptcha_version">reCAPTCHA Version</label>
                        </th>
                        <td>
                            <select id="seo_tools_recaptcha_version" name="seo_tools_recaptcha_version">
                                <option value="v2" <?php selected(get_option('seo_tools_recaptcha_version', 'v2'), 'v2'); ?>>
                                    v2 - Checkbox (Recommended)
                                </option>
                                <option value="v3" <?php selected(get_option('seo_tools_recaptcha_version'), 'v3'); ?>>
                                    v3 - Invisible
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="seo_tools_recaptcha_site_key">Site Key</label>
                        </th>
                        <td>
                            <input type="text"
                                id="seo_tools_recaptcha_site_key"
                                name="seo_tools_recaptcha_site_key"
                                value="<?php echo esc_attr(get_option('seo_tools_recaptcha_site_key')); ?>"
                                class="regular-text"
                                placeholder="6Lc..." />
                            <p class="description">Your reCAPTCHA site key (public key).</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="seo_tools_recaptcha_secret_key">Secret Key</label>
                        </th>
                        <td>
                            <input type="password"
                                id="seo_tools_recaptcha_secret_key"
                                name="seo_tools_recaptcha_secret_key"
                                value="<?php echo esc_attr(get_option('seo_tools_recaptcha_secret_key')); ?>"
                                class="regular-text"
                                placeholder="6Lc..." />
                            <p class="description">Your reCAPTCHA secret key (private key).</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Rate Limiting -->
            <div class="seo-tools-section">
                <h2>⚡ Rate Limiting</h2>
                <p class="description">Control how many times users can use each tool per day.</p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="seo_tools_rate_limit_meta">Meta Generator</label>
                        </th>
                        <td>
                            <input type="number"
                                id="seo_tools_rate_limit_meta"
                                name="seo_tools_rate_limit_meta"
                                value="<?php echo esc_attr(get_option('seo_tools_rate_limit_meta', 5)); ?>"
                                min="1"
                                max="100"
                                class="small-text" />
                            <span>requests per day per user</span>
                            <p class="description">Recommended: 5 (to stay within free tier limit)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="seo_tools_rate_limit_links">Broken Link Checker</label>
                        </th>
                        <td>
                            <input type="number"
                                id="seo_tools_rate_limit_links"
                                name="seo_tools_rate_limit_links"
                                value="<?php echo esc_attr(get_option('seo_tools_rate_limit_links', 5)); ?>"
                                min="1"
                                max="100"
                                class="small-text" />
                            <span>requests per day per user</span>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="seo_tools_rate_limit_kw_url">Keyword Density (URL Mode)</label>
                        </th>
                        <td>
                            <input type="number"
                                id="seo_tools_rate_limit_kw_url"
                                name="seo_tools_rate_limit_kw_url"
                                value="<?php echo esc_attr(get_option('seo_tools_rate_limit_kw_url', 20)); ?>"
                                min="1"
                                max="100"
                                class="small-text" />
                            <span>requests per day per user</span>
                            <p class="description">Text mode is unlimited (client-side only)</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Cache Settings -->
            <div class="seo-tools-section">
                <h2>💾 Cache Settings</h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="seo_tools_cache_duration">Cache Duration</label>
                        </th>
                        <td>
                            <input type="number"
                                id="seo_tools_cache_duration"
                                name="seo_tools_cache_duration"
                                value="<?php echo esc_attr(get_option('seo_tools_cache_duration', 86400)); ?>"
                                min="3600"
                                max="604800"
                                class="small-text" />
                            <span>seconds (<?php echo esc_html(round(get_option('seo_tools_cache_duration', 86400) / 3600, 1)); ?> hours)</span>
                            <p class="description">Recommended: 86400 (24 hours)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Cache Management</th>
                        <td>
                            <button type="button" id="clear-cache" class="button">Clear All Caches</button>
                            <p class="description">
                                Currently caching: <?php echo esc_html($cache_stats['active_count']); ?> items
                                (<?php echo esc_html($cache_stats['cache_size_kb']); ?> KB)
                            </p>
                            <span id="cache-clear-result"></span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Logging Settings -->
            <div class="seo-tools-section">
                <h2>📝 Logging & Analytics</h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="seo_tools_enable_logging">Enable Logging</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    id="seo_tools_enable_logging"
                                    name="seo_tools_enable_logging"
                                    value="yes"
                                    <?php checked(get_option('seo_tools_enable_logging', 'yes'), 'yes'); ?> />
                                Track tool usage for analytics
                            </label>
                            <p class="description">IPs are hashed for privacy (GDPR compliant)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="seo_tools_log_retention_days">Log Retention</label>
                        </th>
                        <td>
                            <input type="number"
                                id="seo_tools_log_retention_days"
                                name="seo_tools_log_retention_days"
                                value="<?php echo esc_attr(get_option('seo_tools_log_retention_days', 30)); ?>"
                                min="7"
                                max="365"
                                class="small-text" />
                            <span>days</span>
                            <p class="description">Logs older than this will be automatically deleted</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Export Logs</th>
                        <td>
                            <button type="button" id="export-logs" class="button">Export Logs (CSV)</button>
                            <p class="description">Export last 30 days of usage data</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Tool Settings -->
            <div class="seo-tools-section">
                <h2>🔧 Tool Settings</h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="seo_tools_max_links_check">Max Links per Check</label>
                        </th>
                        <td>
                            <input type="number"
                                id="seo_tools_max_links_check"
                                name="seo_tools_max_links_check"
                                value="<?php echo esc_attr(get_option('seo_tools_max_links_check', 50)); ?>"
                                min="10"
                                max="200"
                                class="small-text" />
                            <span>links</span>
                            <p class="description">Maximum number of links to check on a page</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="seo_tools_link_timeout">Link Check Timeout</label>
                        </th>
                        <td>
                            <input type="number"
                                id="seo_tools_link_timeout"
                                name="seo_tools_link_timeout"
                                value="<?php echo esc_attr(get_option('seo_tools_link_timeout', 5)); ?>"
                                min="3"
                                max="30"
                                class="small-text" />
                            <span>seconds per link</span>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button('Save Settings'); ?>
        </form>

        <!-- Usage Statistics (Last 7 Days) -->
        <div class="seo-tools-section">
            <h2>📈 Usage Statistics (Last 7 Days)</h2>

            <?php if (!empty($weekly_stats['usage_by_tool'])): ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Tool</th>
                            <th>Total Uses</th>
                            <th>Cached</th>
                            <th>Cache Rate</th>
                            <th>API Tokens</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weekly_stats['usage_by_tool'] as $tool): ?>
                            <tr>
                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $tool['tool_name']))); ?></td>
                                <td><?php echo esc_html($tool['count']); ?></td>
                                <td><?php echo esc_html($tool['cached_count']); ?></td>
                                <td><?php echo $tool['count'] > 0 ? esc_html(round(($tool['cached_count'] / $tool['count']) * 100, 1)) : 0; ?>%</td>
                                <td><?php echo esc_html($tool['total_tokens']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No usage data available yet. Start using the tools to see statistics.</p>
            <?php endif; ?>

            <p>
                <strong>Unique Users:</strong> <?php echo esc_html($weekly_stats['unique_users']); ?> |
                <strong>Error Rate:</strong> <?php echo esc_html($weekly_stats['error_rate']); ?>%
            </p>
        </div>
    </div>
</div>

<style>
    .seo-tools-admin-container {
        max-width: 1200px;
    }

    .seo-tools-dashboard {
        background: #fff;
        border: 1px solid #ccd0d4;
        padding: 20px;
        margin: 20px 0;
        border-radius: 4px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }

    .stat-card {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 4px;
        text-align: center;
        border: 1px solid #e1e4e8;
    }

    .stat-value {
        font-size: 32px;
        font-weight: bold;
        color: #2271b1;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 14px;
        color: #50575e;
    }

    .seo-tools-section {
        background: #fff;
        border: 1px solid #ccd0d4;
        padding: 20px;
        margin: 20px 0;
        border-radius: 4px;
    }

    .seo-tools-section h2 {
        margin-top: 0;
    }

    #api-test-result,
    #cache-clear-result {
        margin-left: 10px;
        font-weight: bold;
    }

    #api-test-result.success,
    #cache-clear-result.success {
        color: #00a32a;
    }

    #api-test-result.error,
    #cache-clear-result.error {
        color: #d63638;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Test API Key
        $('#test-api-key').on('click', function() {
            const $button = $(this);
            const $result = $('#api-test-result');
            const apiKey = $('#seo_tools_gemini_api_key').val();

            if (!apiKey) {
                $result.text('Please enter an API key first').removeClass('success').addClass('error');
                return;
            }

            $button.prop('disabled', true).text('Testing...');
            $result.text('');

            $.post(ajaxurl, {
                action: 'seo_test_api_key',
                nonce: seoToolsAdmin.nonce,
                api_key: apiKey
            }, function(response) {
                $button.prop('disabled', false).text('Test API Connection');

                if (response.success) {
                    $result.text('✓ ' + response.data.message).removeClass('error').addClass('success');
                } else {
                    $result.text('✗ ' + response.data.message).removeClass('success').addClass('error');
                }
            });
        });

        // Clear Cache
        $('#clear-cache').on('click', function() {
            if (!confirm('Are you sure you want to clear all caches?')) {
                return;
            }

            const $button = $(this);
            const $result = $('#cache-clear-result');

            $button.prop('disabled', true).text('Clearing...');
            $result.text('');

            $.post(ajaxurl, {
                action: 'seo_clear_cache',
                nonce: seoToolsAdmin.nonce
            }, function(response) {
                $button.prop('disabled', false).text('Clear All Caches');

                if (response.success) {
                    $result.text('✓ ' + response.data.message).removeClass('error').addClass('success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $result.text('✗ Error clearing cache').removeClass('success').addClass('error');
                }
            });
        });

        // Export Logs
        $('#export-logs').on('click', function() {
            window.location.href = ajaxurl + '?action=seo_export_logs&nonce=' + seoToolsAdmin.nonce;
        });
    });
</script>