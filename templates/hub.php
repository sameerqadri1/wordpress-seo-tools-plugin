<?php

/**
 * SEO Tools Hub Page Template
 *
 * @package SEO_Marketing_Tools
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

// Get usage stats for display
$logger = new \SEO_Marketing_Tools\Utils\Logger();
$stats = $logger->get_today_stats();
?>

<div class="seo-tools-hub">
	<!-- Hero Section -->
	<div class="seo-tools-hero">
		<h1>Free SEO Tools by SaaS Marketing</h1>
		<p class="hero-subtitle">Professional SEO tools to optimize your website and improve your search engine rankings.</p>
	</div>

	<!-- Tools Grid -->
	<div class="seo-tools-grid">
		<!-- Meta Generator Tool -->
		<div class="tool-card">
			<h2>AI Meta Generator</h2>
			<p>Generate SEO-optimized meta titles and descriptions powered by AI. Perfect for improving click-through rates.</p>
			<ul class="tool-features">
				<li>✓ AI-powered generation</li>
				<li>✓ Character count validation</li>
				<li>✓ Instant results</li>
				<li>⚡ 5 free per day</li>
			</ul>
			<a href="<?php echo esc_url(site_url('/seo-tools/ai-meta-generator/')); ?>" class="tool-button">
				Use Tool →
			</a>
			<span class="tool-stat">Free Meta Generator Tool</span>
		</div>

		<!-- Keyword Density Tool -->
		<div class="tool-card">
			<h2>Keyword Density Checker</h2>
			<p>Analyze keyword frequency and density in your content. Avoid over-optimization and improve your SEO.</p>
			<ul class="tool-features">
				<li>✓ Instant analysis</li>
				<li>✓ Text or URL input</li>
				<li>✓ 1, 2, 3-word phrases</li>
				<li>⚡ Unlimited (text mode)</li>
			</ul>
			<a href="<?php echo esc_url(site_url('/seo-tools/keyword-density/')); ?>" class="tool-button">
				Use Tool →
			</a>
			<span class="tool-stat">Most popular tool</span>
		</div>

		<!-- Broken Link Checker Tool -->
		<div class="tool-card">
			<h2>Broken Link Checker</h2>
			<p>Find and fix broken links on your website. Improve user experience and maintain your SEO rankings.</p>
			<ul class="tool-features">
				<li>✓ Fast scanning</li>
				<li>✓ Detailed reports</li>
				<li>✓ Export to CSV</li>
				<li>⚡ 5 free per day</li>
			</ul>
			<a href="<?php echo esc_url(site_url('/seo-tools/broken-link-checker/')); ?>" class="tool-button">
				Use Tool →
			</a>
			<span class="tool-stat">Check up to 50 links</span>
		</div>
	</div>

	<!-- Why Use Our Tools -->
	<div class="seo-tools-benefits">
		<h2>Why Use Our SEO Tools?</h2>
		<div class="benefits-grid">
			<div class="benefit-item">
				<h3>Fast & Reliable</h3>
				<p>Instant results with 99.9% uptime. No registration required.</p>
			</div>
			<div class="benefit-item">
				<h3>Secure & Private</h3>
				<p>Your data is never stored or shared. GDPR compliant.</p>
			</div>
			<div class="benefit-item">
				<h3>AI-Powered</h3>
				<p>Leveraging Google's Gemini AI for smart content generation.</p>
			</div>
			<div class="benefit-item">
				<h3>100% Free</h3>
				<p>Free daily usage limits. No hidden fees or upsells.</p>
			</div>
		</div>
	</div>

	<!-- CTA Section -->
	<div class="seo-tools-cta">
		<h2>Need Professional SEO Services?</h2>
		<p>Our team of experts can help you dominate search rankings and drive more traffic to your website.</p>
		<a href="<?php echo esc_url(site_url('/contact/')); ?>" class="cta-button">Contact Us Today</a>
	</div>

	<!-- Footer -->
	<div class="seo-tools-footer">
		<p>Powered by <strong><a href="<?php echo esc_url(home_url('/')); ?>">SaaS Marketing</a></strong> ❤️</p>
	</div>
</div>