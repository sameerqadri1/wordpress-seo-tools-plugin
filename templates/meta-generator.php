<?php

/**
 * Meta Title & Description Generator Template
 *
 * @package SEO_Marketing_Tools
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="seo-tool-container seo-meta-generator">
	<!-- Breadcrumb -->
	<div class="seo-breadcrumb">
		<a href="<?php echo esc_url(site_url('/seo-tools/')); ?>">← Back to All Tools</a>
	</div>

	<!-- Header -->
	<div class="tool-header">
		<h1> AI-Powered Meta Title & Description Generator</h1>
		<p class="tool-description">
			Create SEO-optimized meta titles and descriptions in seconds using advanced AI technology.
			Perfect length, keyword-optimized, and designed to boost your click-through rates.
		</p>
	</div>

	<!-- Rate Limit Info -->
	<div class="rate-limit-info" id="rate-limit-status">
		<span class="limit-text">Loading...</span>
	</div>

	<!-- Generator Form -->
	<div class="tool-form-card">
		<form id="meta-generator-form">
			<div class="form-group">
				<label for="keyword">
					Target Keyword <span class="required">*</span>
					<span class="help-tip" title="The main keyword you want to rank for">?</span>
				</label>
				<input type="text"
					id="keyword"
					name="keyword"
					class="form-control"
					placeholder="e.g., WordPress SEO Tools"
					maxlength="100"
					required />
				<span class="char-count" data-for="keyword">0/100</span>
			</div>

			<div class="form-group">
				<label for="business_name">
					Business Name <span class="required">*</span>
				</label>
				<input type="text"
					id="business_name"
					name="business_name"
					class="form-control"
					placeholder="e.g., SaaS Marketing"
					maxlength="50"
					required />
				<span class="char-count" data-for="business_name">0/50</span>
			</div>

			<div class="form-group">
				<label for="description">
					Page Description <span class="required">*</span>
				</label>
				<textarea id="description"
					name="description"
					class="form-control"
					rows="4"
					placeholder="Briefly describe what this page is about..."
					maxlength="300"
					required></textarea>
				<span class="char-count" data-for="description">0/300</span>
			</div>

			<div class="form-group">
				<label for="page_type">
					Page Type
				</label>
				<select id="page_type" name="page_type" class="form-control">
					<option value="service">Service Page</option>
					<option value="home">Homepage</option>
					<option value="blog">Blog Post</option>
					<option value="product">Product Page</option>
					<option value="about">About Page</option>
					<option value="contact">Contact Page</option>
				</select>
			</div>

			<!-- reCAPTCHA -->
			<div class="form-group recaptcha-wrapper">
				<div class="g-recaptcha" data-sitekey="<?php echo esc_attr(get_option('seo_tools_recaptcha_site_key')); ?>"></div>
			</div>

			<!-- Submit Button -->
			<button type="submit" id="generate-btn" class="btn-primary">
				<span class="btn-text">Generate Meta Tags</span>
				<span class="btn-loader" style="display:none;">⏳ Generating...</span>
			</button>
		</form>
	</div>

	<!-- Results Section -->
	<div id="meta-results" class="tool-results-card" style="display:none;">
		<h2> Generated Meta Tags</h2>

		<div class="result-item">
			<div class="result-header">
				<label>Meta Title</label>
				<span class="char-indicator" id="title-length"></span>
			</div>
			<div class="result-content">
				<div id="generated-title" class="generated-text"></div>
				<button type="button" class="btn-copy" data-target="generated-title"> Copy</button>
			</div>
			<p class="result-note">Optimal: 50-60 characters</p>
		</div>

		<div class="result-item">
			<div class="result-header">
				<label>Meta Description</label>
				<span class="char-indicator" id="description-length"></span>
			</div>
			<div class="result-content">
				<div id="generated-description" class="generated-text"></div>
				<button type="button" class="btn-copy" data-target="generated-description"> Copy</button>
			</div>
			<p class="result-note">Optimal: 150-160 characters</p>
		</div>

		<div class="result-actions">
			<button type="button" id="generate-another" class="btn-secondary">
				Generate Another
			</button>
		</div>
	</div>

	<!-- Error Message -->
	<div id="error-message" class="error-alert" style="display:none;"></div>

	<!-- How to Use -->
	<div class="tool-info-card">
		<h2> How to Use This Tool</h2>
		<ol class="info-list">
			<li><strong>Enter Your Keyword:</strong> Type the main keyword you want to target for SEO.</li>
			<li><strong>Add Business Name:</strong> Your company or website name.</li>
			<li><strong>Describe Your Page:</strong> Write a brief description of what the page is about.</li>
			<li><strong>Select Page Type:</strong> Choose the type of page for better context.</li>
			<li><strong>Generate:</strong> Click the button and get AI-optimized meta tags instantly!</li>
			<li><strong>Copy & Use:</strong> Copy the generated tags and add them to your website's HTML or CMS.</li>
		</ol>

		<h3> Implementation</h3>
		<p>Add the generated meta tags to your page's <code>&lt;head&gt;</code> section:</p>
		<pre class="code-example">&lt;title&gt;Your Generated Title Here&lt;/title&gt;
&lt;meta name="description" content="Your Generated Description Here"&gt;</pre>
	</div>

	<!-- Try Other Tools -->
	<div class="other-tools">
		<h3>Try Our Other Tools</h3>
		<div class="tools-links">
			<a href="<?php echo esc_url(site_url('/seo-tools/keyword-density/')); ?>" class="tool-link">
				Keyword Density Checker
			</a>
			<a href="<?php echo esc_url(site_url('/seo-tools/broken-link-checker/')); ?>" class="tool-link">
				Broken Link Checker
			</a>
		</div>
	</div>

	<!-- Footer -->
	<div class="tool-footer">
		<p>Powered by <strong><a href="<?php echo esc_url(home_url('/')); ?>">SaaS Marketing</a></strong> ❤️</p>
		<p class="footer-cta">
			Need unlimited access? <a href="<?php echo esc_url(site_url('/contact/')); ?>">Contact us</a> about our premium services.
		</p>
	</div>
</div>