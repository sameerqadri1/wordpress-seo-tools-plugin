<?php

/**
 * Broken Link Checker Template
 *
 * @package SEO_Marketing_Tools
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="seo-tool-container seo-broken-links">
	<!-- Breadcrumb -->
	<div class="seo-breadcrumb">
		<a href="<?php echo esc_url(site_url('/seo-tools/')); ?>">← Back to All Tools</a>
	</div>

	<!-- Header -->
	<div class="tool-header">
		<h1>🔗 Broken Link Checker</h1>
		<p class="tool-description">
			Find and fix broken links on any webpage. Broken links harm user experience and SEO.
			Get a detailed report with status codes and response times.
		</p>
	</div>

	<!-- Rate Limit Info -->
	<div class="rate-limit-info" id="rate-limit-status">
		<span class="limit-icon">⚡</span>
		<span class="limit-text">Loading...</span>
	</div>

	<!-- Checker Form -->
	<div class="tool-form-card">
		<form id="link-checker-form">
			<div class="form-group">
				<label>
					Scan Mode <span class="required">*</span>
				</label>
				<div class="radio-group">
					<label class="radio-option">
						<input type="radio" name="scan_mode" value="quick" id="mode-quick" />
						<span class="radio-label">
							<strong>Quick Scan</strong> - Check links on a single page only
							<small style="display:block; color: var(--text-secondary); margin-top: 4px;">
								⚡ Fast (30 seconds - 2 minutes) · Checks up to 100 links on one page
							</small>
						</span>
					</label>
					<label class="radio-option">
						<input type="radio" name="scan_mode" value="full" id="mode-full" />
						<span class="radio-label">
							<strong>Full Site Audit</strong> - Crawl and check entire website
							<small style="display:block; color: var(--text-secondary); margin-top: 4px;">
								🔍 Comprehensive (10-30 minutes) · Scans up to 1,000 pages
							</small>
						</span>
					</label>
				</div>
			</div>

			<div class="form-group">
				<label for="check-url">
					Enter Website URL <span class="required">*</span>
				</label>
				<input type="url"
					id="check-url"
					name="url"
					class="form-control"
					placeholder="https://example.com"
					required />
				<p class="form-help" id="url-help-text">
					Please select a scan mode above.
				</p>
			</div>

			<!-- reCAPTCHA -->
			<div class="form-group recaptcha-wrapper">
				<div class="g-recaptcha" data-sitekey="<?php echo esc_attr(get_option('seo_tools_recaptcha_site_key')); ?>"></div>
			</div>

			<!-- Submit Button -->
			<button type="submit" id="check-btn" class="btn-primary">
				<span class="btn-text">Start Scanning</span>
				<span class="btn-loader" style="display:none;">⏳ Scanning website...</span>
			</button>
		</form>
	</div>

	<!-- Loading Message -->
	<div id="loading-message" class="info-message-card" style="display:none;">
		<p class="info-message">🔄 Scanning... Please wait (this may take 2-5 minutes)</p>
		<div class="timer-display">
			<span class="timer-icon">⏱️</span>
			<span class="timer-label">Elapsed:</span>
			<strong id="elapsed-time">0s</strong>
		</div>
		<button type="button" id="cancel-scan-btn" class="btn-cancel">Cancel Scan</button>
	</div>

	<!-- Continue Prompt -->
	<div id="continue-prompt" class="info-message-card info-success" style="display:none;">
		<p class="info-message">✓ Scanned 100 pages. Continue scanning?</p>
		<div class="continue-actions">
			<button type="button" id="continue-scan-btn" class="btn-primary">Continue Scanning</button>
			<button type="button" id="cancel-scan-btn-continue" class="btn-cancel" onclick="$('#cancel-scan-btn').trigger('click')">Stop Here</button>
		</div>
	</div>

	<!-- Results Section -->
	<div id="link-results" class="tool-results-card" style="display:none;">
		<h2>🔍 Scan Complete</h2>

		<!-- Summary Stats -->
		<div class="stats-row">
			<div class="stat-box stat-total">
				<div class="stat-value" id="total-links">0</div>
				<div class="stat-label">Total Links Checked</div>
			</div>
			<div class="stat-box stat-working">
				<div class="stat-value" id="working-links">0</div>
				<div class="stat-label">✓ Links Passed</div>
			</div>
			<div class="stat-box stat-broken">
				<div class="stat-value" id="broken-links">0</div>
				<div class="stat-label">✗ Broken Links</div>
			</div>
			<div class="stat-box">
				<div class="stat-value" id="pages-crawled-stat">0</div>
				<div class="stat-label">Pages Crawled</div>
			</div>
			<div class="stat-box">
				<div class="stat-value" id="scan-time">0s</div>
				<div class="stat-label">Scan Time</div>
			</div>
		</div>

		<!-- Results Table (Broken Links Only) -->
		<div class="results-section">
			<h3>Broken Links Found</h3>
			<p style="color: #6b7280; margin-bottom: 20px;">
				Only broken links are displayed below. All other links passed successfully.
			</p>
		</div>

		<div class="results-table-wrapper">
			<table class="results-table">
				<thead>
					<tr>
						<th>URL</th>
						<th>Anchor Text</th>
						<th>Status</th>
						<th>Response</th>
						<th>Found On Page</th>
					</tr>
				</thead>
				<tbody id="links-tbody">
					<!-- Results populated by JavaScript -->
				</tbody>
			</table>
		</div>

		<div class="result-actions">
			<button type="button" id="check-another" class="btn-secondary">
				Scan Another Website
			</button>
			<button type="button" id="export-csv" class="btn-secondary">
				📥 Export Broken Links (CSV)
			</button>
		</div>
	</div>

	<!-- Error Message -->
	<div id="error-message" class="error-alert" style="display:none;"></div>

	<!-- How to Use -->
	<div class="tool-info-card">
		<h2>💡 How to Use This Tool</h2>
		<ol class="info-list">
			<li><strong>Enter Website URL:</strong> Paste the homepage or any page URL of your website.</li>
			<li><strong>Start Scan:</strong> Click "Start Scanning" and wait while we crawl your entire website.</li>
			<li><strong>Monitor Progress:</strong> Watch real-time updates of pages crawled and links checked.</li>
			<li><strong>Review Results:</strong> See all broken links with details about where they were found.</li>
			<li><strong>Fix Issues:</strong> Update or remove broken links to improve your site.</li>
			<li><strong>Export Report:</strong> Download a CSV file for your records.</li>
		</ol>

		<h3>🔍 What Gets Scanned?</h3>
		<ul class="info-list">
			<li><strong>Full Website Crawl:</strong> Up to 1,000 pages on your domain</li>
			<li><strong>Internal Links:</strong> All links within your website</li>
			<li><strong>External Links:</strong> All links to other websites</li>
			<li><strong>Excluded:</strong> Social media links (Facebook, Twitter, etc.)</li>
			<li><strong>Results:</strong> Only broken links are displayed</li>
		</ul>

		<h3>🔴 Understanding Status Codes</h3>
		<ul class="info-list">
			<li><strong>404:</strong> Page not found (broken link - fix immediately)</li>
			<li><strong>403:</strong> Forbidden (access denied)</li>
			<li><strong>500:</strong> Server error (temporary issue or broken)</li>
			<li><strong>Timeout:</strong> Link didn't respond (check URL or server)</li>
		</ul>

		<h3>⚡ Why Fix Broken Links?</h3>
		<ul class="info-list">
			<li>Improves user experience</li>
			<li>Maintains SEO rankings</li>
			<li>Prevents loss of "link juice"</li>
			<li>Shows professionalism</li>
			<li>Reduces bounce rate</li>
		</ul>
	</div>

	<!-- Try Other Tools -->
	<div class="other-tools">
		<h3>Try Our Other Tools</h3>
		<div class="tools-links">
			<a href="<?php echo esc_url(site_url('/seo-tools/meta-generator/')); ?>" class="tool-link">
				🎯 AI Meta Generator
			</a>
			<a href="<?php echo esc_url(site_url('/seo-tools/keyword-density/')); ?>" class="tool-link">
				📊 Keyword Density Checker
			</a>
		</div>
	</div>

	<!-- Footer -->
	<div class="tool-footer">
		<p>Powered by <strong><a href="<?php echo esc_url(home_url('/')); ?>">SaaS Marketing</a></strong> ❤️</p>
		<p class="footer-cta">
			Need a comprehensive site audit? <a href="<?php echo esc_url(site_url('/contact/')); ?>">Let's talk</a> about our professional SEO services.
		</p>
	</div>
</div>