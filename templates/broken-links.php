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
				<label for="check-url">
					Enter URL to Check <span class="required">*</span>
				</label>
				<input type="url"
					id="check-url"
					name="url"
					class="form-control"
					placeholder="https://example.com/page"
					required />
				<p class="form-help">
					We'll scan this page and check all links (up to <?php echo esc_html(get_option('seo_tools_max_links_check', 50)); ?> links)
				</p>
			</div>

			<!-- reCAPTCHA -->
			<div class="form-group recaptcha-wrapper">
				<div class="g-recaptcha" data-sitekey="<?php echo esc_attr(get_option('seo_tools_recaptcha_site_key')); ?>"></div>
			</div>

			<!-- Submit Button -->
			<button type="submit" id="check-btn" class="btn-primary">
				<span class="btn-text">Check Links</span>
				<span class="btn-loader" style="display:none;">⏳ Scanning... This may take a minute</span>
			</button>
		</form>
	</div>

	<!-- Results Section -->
	<div id="link-results" class="tool-results-card" style="display:none;">
		<h2>🔍 Link Check Results</h2>

		<!-- Summary Stats -->
		<div class="stats-row">
			<div class="stat-box stat-total">
				<div class="stat-value" id="total-links">0</div>
				<div class="stat-label">Total Links</div>
			</div>
			<div class="stat-box stat-working">
				<div class="stat-value" id="working-links">0</div>
				<div class="stat-label">✓ Working</div>
			</div>
			<div class="stat-box stat-broken">
				<div class="stat-value" id="broken-links">0</div>
				<div class="stat-label">✗ Broken</div>
			</div>
			<div class="stat-box">
				<div class="stat-value" id="scan-time">0s</div>
				<div class="stat-label">Scan Time</div>
			</div>
		</div>

		<!-- Status Filter -->
		<div class="filter-tabs">
			<button type="button" class="filter-tab active" data-status="all">
				All Links
			</button>
			<button type="button" class="filter-tab" data-status="broken">
				Broken Only
			</button>
			<button type="button" class="filter-tab" data-status="working">
				Working Only
			</button>
		</div>

		<!-- Results Table -->
		<div class="results-table-wrapper">
			<table class="results-table">
				<thead>
					<tr>
						<th>URL</th>
						<th>Anchor Text</th>
						<th>Status</th>
						<th>Response</th>
					</tr>
				</thead>
				<tbody id="links-tbody">
					<!-- Results populated by JavaScript -->
				</tbody>
			</table>
		</div>

		<div class="result-actions">
			<button type="button" id="check-another" class="btn-secondary">
				Check Another URL
			</button>
			<button type="button" id="export-csv" class="btn-secondary">
				📥 Export Report (CSV)
			</button>
		</div>
	</div>

	<!-- Error Message -->
	<div id="error-message" class="error-alert" style="display:none;"></div>

	<!-- How to Use -->
	<div class="tool-info-card">
		<h2>💡 How to Use This Tool</h2>
		<ol class="info-list">
			<li><strong>Enter URL:</strong> Paste the URL of the page you want to check.</li>
			<li><strong>Start Scan:</strong> Click "Check Links" and wait while we analyze all links.</li>
			<li><strong>Review Results:</strong> See which links are working, broken, or redirecting.</li>
			<li><strong>Fix Issues:</strong> Update or remove broken links to improve your site.</li>
			<li><strong>Export Report:</strong> Download a CSV file for your records.</li>
		</ol>

		<h3>🔴 Understanding Status Codes</h3>
		<ul class="info-list">
			<li><strong>200 OK:</strong> Link is working perfectly</li>
			<li><strong>301/302:</strong> Redirect (update link to final destination)</li>
			<li><strong>404:</strong> Page not found (broken link - fix immediately)</li>
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