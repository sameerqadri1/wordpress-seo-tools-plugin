# SEO Marketing Tools - WordPress Plugin

Professional SEO tools suite for WordPress including AI-powered meta generator, keyword density checker (Content Strategist), and broken link checker.

**Live Demo:** [https://saasmarketing.ca/seo-tools/](https://saasmarketing.ca/seo-tools/)

## Features

### 🎯 AI Meta Title & Description Generator
- Powered by Google Gemini 2.5 Flash-Lite AI
- Generates SEO-optimized meta titles (50-60 chars)
- Creates compelling meta descriptions (150-160 chars)
- Smart character counting and validation
- Global API status monitoring (RPD, RPM, TPM)
- **Rate Limit:** 5 generations per day per user, 2 per minute
- **Global API Limit:** 20 requests/day, 10 requests/minute (shared across all users)

### 📊 Keyword Density Checker → **Content Strategist**
- **Two modes:** Paste text (unlimited) or analyze URL (rate-limited)
- **Advanced Analysis:**
  - 1-word, 2-word, 3-word, and **4-word phrases** (long-tail keywords)
  - Porter Stemmer: Groups word variations (running, runs, runner → run)
  - Prominence Score: Analyzes keyword placement (first 100 words, H1, first paragraph)
  - SEO Elements Analysis: Checks Title, Meta Description, H1, Alt text
  - Readability Score: Flesch Reading Ease with grade level
  - **Weighted Relevancy Score (0-100):** Combines all factors for actionable insights
- Over-optimization warnings (stemming-based detection)
- **Rate Limit:** Unlimited for text mode, 20/day + 3/min for URL mode
- **Caching:** 15-minute cache for URL content with force refresh option

### 🔗 Broken Link Checker
- **Two scan modes:**
  - **Quick Scan:** Single webpage, up to 100 links (30 seconds - 2 minutes)
  - **Full Site Audit:** Entire website, up to 1,000 pages (10-30 minutes)
- Chunked processing with auto-continue
- Dynamic progress bar (estimated + real progress)
- Detailed status codes and response times
- Export results to CSV
- **Rate Limit:** 5 checks per day per user, 3 scans per website per day
- **Concurrent Limits:** 1 scan per user, 3 scans globally

## Requirements

- **WordPress:** 6.0 or higher
- **PHP:** 8.1 or higher
- **HTTPS:** Required for API calls
- **Google Gemini API Key:** Required for meta generator
- **Google reCAPTCHA v2:** Required for security

## Installation

### Method 1: WordPress Admin (Recommended)

1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now" and then "Activate"

### Method 2: Manual Installation

1. Extract the ZIP file
2. Upload `seo-marketing-tools` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress

## Configuration

### Step 1: Get API Keys

#### Gemini API Key (Required)
1. Visit https://aistudio.google.com/app/apikey
2. Sign in with your Google account
3. Click "Create API Key"
4. Copy your API key

#### reCAPTCHA Keys (Required)
1. Visit https://www.google.com/recaptcha/admin
2. Register your site
3. Select reCAPTCHA v2 (checkbox)
4. Get your Site Key and Secret Key

### Step 2: Plugin Settings

1. Go to **Settings → SEO Tools** in WordPress Admin
2. Enter your **Gemini API Key**
3. Enter your **reCAPTCHA Site Key** and **Secret Key**
4. Configure rate limits (default: 5/day recommended)
5. Enable logging for analytics (recommended)
6. Click "Save Settings"

### Step 3: Test Configuration

1. Click "Test API Connection" to verify Gemini API
2. Try generating meta tags on a public page
3. Verify reCAPTCHA is working

## Usage

### Creating Pages

Create 4 new pages in WordPress:

1. **SEO Tools Hub** - `/seo-tools/`
   - Add shortcode: `[seo_tools_hub]`
   
2. **Meta Generator** - `/seo-tools/meta-generator/`
   - Add shortcode: `[seo_meta_generator]`
   - Parent: SEO Tools Hub
   
3. **Keyword Density** - `/seo-tools/keyword-density/`
   - Add shortcode: `[seo_keyword_density]`
   - Parent: SEO Tools Hub
   
4. **Broken Link Checker** - `/seo-tools/broken-link-checker/`
   - Add shortcode: `[seo_broken_link_checker]`
   - Parent: SEO Tools Hub

### Shortcodes

```
[seo_tools_hub] - Main hub page with all tools
[seo_meta_generator] - AI Meta Generator tool
[seo_keyword_density] - Keyword Density Checker tool
[seo_broken_link_checker] - Broken Link Checker tool
```

## Rate Limiting

### Default Limits (Per Day Per IP)
- Meta Generator: 5 requests/day, 2 requests/minute
- Broken Link Checker: 5 requests/day
- Keyword Density (URL mode): 20 requests/day, 3 requests/minute
- Keyword Density (Text mode): Unlimited

### Global API Limits (Shared Across All Users)
- Gemini API: 20 requests/day, 10 requests/minute, 250,000 tokens/minute
- These limits are enforced globally to protect the free-tier API quota

### Admin Bypass
Logged-in administrators bypass all rate limits.

### Customization
Adjust limits in **Settings → SEO Tools → Rate Limiting**

## Database Tables

The plugin creates two custom tables:

### wp_seo_rate_limits
Tracks daily usage per IP and tool. Auto-cleaned after 7 days.

### wp_seo_tools_logs
Stores usage logs with hashed IPs (GDPR compliant). Auto-cleaned based on retention setting (default: 30 days).

## Security Features

- ✅ WordPress nonce verification
- ✅ reCAPTCHA v2 protection
- ✅ Database-based rate limiting (atomic)
- ✅ SSRF prevention for URL inputs
- ✅ Input validation and sanitization
- ✅ IP address hashing (privacy)
- ✅ Encrypted API key storage
- ✅ Circuit breaker for API failures

## Caching

- **Link Checker Results:** Cached for 1 hour
- **URL Content (Keyword Density):** Cached for 15 minutes
- **Force Refresh Option:** Available for URL mode to bypass cache
- Reduces API usage and improves performance
- Cache metadata displayed to users (age, expiry time)

## Troubleshooting

### "API key not configured"
- Ensure you've entered your Gemini API key in settings
- Click "Test API Connection" to verify

### "reCAPTCHA verification failed"
- Check your reCAPTCHA keys are correct
- Ensure domain matches your site
- Try disabling browser extensions

### "Rate limit reached"
- Wait until the daily reset (midnight site timezone)
- Or login as admin to bypass limits

### "No links found" or timeout
- Quick Scan: Checks up to 100 links per page
- Full Site Audit: Processes 50 pages per chunk
- Increase timeout in settings if needed
- Try Quick Scan mode for faster results

### Plugin activation error
- Check PHP version (must be 8.1+)
- Check WordPress version (must be 6.0+)
- Verify server has required PHP extensions

## Performance

- Optimized for speed (<3s average response time)
- Lazy loading of JavaScript
- Aggressive caching strategy (15 min - 1 hour)
- Minimal database queries
- jQuery used for AJAX and DOM manipulation
- Mobile-responsive design (tablet + phone breakpoints)
- CSS scoped to prevent theme conflicts (Elementor-compatible)

## Privacy & GDPR

- IP addresses are hashed before storage
- No personally identifiable information stored
- Cookie consent for reCAPTCHA
- Data retention policy (30 days default)
- Easy data deletion

## Live Demo

**Test the tools live:** [https://saasmarketing.ca/seo-tools/](https://saasmarketing.ca/seo-tools/)

Try all three tools with real-time results:
- AI Meta Generator
- Keyword Density (Content Strategist)
- Broken Link Checker

## Support

For support, feature requests, or bug reports:
- Visit: https://saasmarketing.ca/contact/
- Email: info@saasmarketing.ca
- Live Demo: https://saasmarketing.ca/seo-tools/

## Credits

- **Developed by:** Sameer Qadri
- **For:** SaaS Marketing (saasmarketing.ca)
- **Powered by:** Google Gemini AI
- **Security:** Google reCAPTCHA v2

## Changelog

### Version 1.0.0 (January 2026)
- ✅ Initial release
- ✅ AI-powered meta generator (Gemini 2.5 Flash-Lite)
- ✅ Keyword Density Checker → **Content Strategist**
  - 4-word phrase analysis (long-tail keywords)
  - Porter Stemmer (word variation grouping)
  - Prominence scoring (first 100 words, H1, first paragraph)
  - SEO elements analysis (Title, Meta Description, H1, Alt text)
  - Readability score (Flesch Reading Ease)
  - Weighted relevancy score (0-100)
  - Over-optimization warnings
- ✅ Broken Link Checker (Quick Scan + Full Site Audit)
  - Two scan modes with radio button selection
  - Chunked processing with auto-continue
  - Dynamic progress bar (estimated + real progress)
  - Total elapsed time tracking
- ✅ Database-based rate limiting (daily, per-minute, concurrent)
- ✅ Global API limits tracking (RPD, RPM, TPM)
- ✅ Admin dashboard with analytics
- ✅ GDPR compliant logging
- ✅ reCAPTCHA v2 security
- ✅ URL mode caching (15 minutes) with force refresh
- ✅ Mobile-responsive UI for all tools
- ✅ CSS scoping to prevent theme conflicts

## License

GPL v2 or later

Copyright (C) 2026 Sameer Qadri

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
