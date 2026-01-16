<?php

/**
 * Keyword Density Checker Template
 *
 * @package SEO_Marketing_Tools
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="seo-tool-container seo-keyword-density">
	<!-- Breadcrumb -->
	<div class="seo-breadcrumb">
		<a href="<?php echo esc_url(site_url('/seo-tools/')); ?>">← Back to All Tools</a>
	</div>

	<!-- Header -->
	<div class="tool-header">
		<h1>📊 Keyword Density Checker</h1>
		<p class="tool-description">
			Analyze keyword frequency and density in your content. Find out if you're over-optimizing
			or under-utilizing your target keywords. Helps improve your SEO naturally.
		</p>
	</div>

	<!-- Input Mode Tabs -->
	<div class="input-mode-tabs">
		<button type="button" class="mode-tab active" data-mode="text">
			📝 Paste Text
		</button>
		<button type="button" class="mode-tab" data-mode="url">
			🌐 Enter URL
		</button>
	</div>

	<!-- Text Input Mode -->
	<div class="tool-form-card" id="text-mode">
		<form id="keyword-text-form">
			<div class="form-group">
				<label for="content-text">
					Paste Your Content
					<span class="badge">Unlimited · Instant</span>
				</label>
				<textarea id="content-text"
					class="form-control"
					rows="12"
					placeholder="Paste your article, blog post, or any content here..."></textarea>
				<div class="form-meta">
					<span id="word-count-text">0 words</span>
					<span class="limit-note">Max: 10,000 words</span>
				</div>
			</div>

			<button type="submit" class="btn-primary">
				<span class="btn-text">Analyze Keyword Density</span>
				<span class="btn-loader" style="display:none;">⏳ Analyzing...</span>
			</button>
		</form>
	</div>

	<!-- URL Input Mode -->
	<div class="tool-form-card" id="url-mode" style="display:none;">
		<form id="keyword-url-form">
			<div class="form-group">
				<label for="content-url">
					Enter URL to Analyze
					<span class="badge">20 per day</span>
				</label>
				<input type="url"
					id="content-url"
					class="form-control"
					placeholder="https://example.com/page"
					required />
				<p class="form-help">We'll fetch and analyze the content from this URL</p>
			</div>

			<button type="submit" class="btn-primary">
				<span class="btn-text">Fetch & Analyze</span>
				<span class="btn-loader" style="display:none;">⏳ Fetching...</span>
			</button>
		</form>
	</div>

	<!-- Results Section -->
	<div id="keyword-results" class="tool-results-card" style="display:none;">
		<h2>📈 Keyword Analysis Results</h2>

		<div class="stats-row">
			<div class="stat-box">
				<div class="stat-value" id="total-words">0</div>
				<div class="stat-label">Total Words</div>
			</div>
			<div class="stat-box">
				<div class="stat-value" id="unique-words">0</div>
				<div class="stat-label">Unique Words</div>
			</div>
			<div class="stat-box">
				<div class="stat-value" id="analysis-time">0s</div>
				<div class="stat-label">Analysis Time</div>
			</div>
		</div>

		<!-- Phrase Length Filter -->
		<div class="filter-tabs">
			<button type="button" class="filter-tab active" data-length="1">1-Word</button>
			<button type="button" class="filter-tab" data-length="2">2-Word</button>
			<button type="button" class="filter-tab" data-length="3">3-Word</button>
		</div>

		<!-- Results Table -->
		<div class="results-table-wrapper">
			<table class="results-table">
				<thead>
					<tr>
						<th>Keyword/Phrase</th>
						<th>Count</th>
						<th>Density %</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody id="keywords-tbody">
					<!-- Results populated by JavaScript -->
				</tbody>
			</table>
		</div>

		<div class="result-actions">
			<button type="button" id="analyze-another" class="btn-secondary">
				Analyze Another
			</button>
			<button type="button" id="export-csv" class="btn-secondary">
				📥 Export CSV
			</button>
		</div>
	</div>

	<!-- Error Message -->
	<div id="error-message" class="error-alert" style="display:none;"></div>

	<!-- How to Use -->
	<div class="tool-info-card">
		<h2>💡 How to Use This Tool</h2>
		<ol class="info-list">
			<li><strong>Choose Input Mode:</strong> Select "Paste Text" for instant analysis or "Enter URL" to analyze a live page.</li>
			<li><strong>Add Your Content:</strong> Paste your text or enter a URL.</li>
			<li><strong>Analyze:</strong> Click the analyze button to see keyword density.</li>
			<li><strong>Review Results:</strong> See the most used keywords, their frequency, and density percentage.</li>
			<li><strong>Optimize:</strong> Adjust your content if any keywords are over-optimized (>2-3% density).</li>
		</ol>

		<h3>📊 Understanding Keyword Density</h3>
		<ul class="info-list">
			<li><strong>Optimal:</strong> 0.5% - 2% for primary keyword</li>
			<li><strong>Warning:</strong> 2% - 3% might be over-optimized</li>
			<li><strong>Danger:</strong> >3% likely keyword stuffing (can harm SEO)</li>
		</ul>
	</div>

	<!-- Try Other Tools -->
	<div class="other-tools">
		<h3>Try Our Other Tools</h3>
		<div class="tools-links">
			<a href="<?php echo esc_url(site_url('/seo-tools/meta-generator/')); ?>" class="tool-link">
				🎯 AI Meta Generator
			</a>
			<a href="<?php echo esc_url(site_url('/seo-tools/broken-link-checker/')); ?>" class="tool-link">
				🔗 Broken Link Checker
			</a>
		</div>
	</div>

	<!-- Footer -->
	<div class="tool-footer">
		<p>Powered by <strong><a href="<?php echo esc_url(home_url('/')); ?>">SaaS Marketing</a></strong> ❤️</p>
	</div>
</div>