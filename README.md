# SEO Marketing Tools - WordPress Plugin

Professional SEO tools suite for WordPress including AI-powered meta generator, keyword density checker, and broken link checker.

## Features

### 🎯 AI Meta Title & Description Generator
- Powered by Google Gemini AI
- Generates SEO-optimized meta titles (50-60 chars)
- Creates compelling meta descriptions (150-160 chars)
- Smart character counting and validation
- **Rate Limit:** 5 generations per day per user

### 📊 Keyword Density Checker
- Analyze text content or URLs
- Find 1-word, 2-word, and 3-word phrases
- Calculate keyword density percentages
- Identify over-optimization (keyword stuffing)
- **Rate Limit:** Unlimited for text mode, 20/day for URL mode

### 🔗 Broken Link Checker
- Scan any webpage for broken links
- Check up to 50 links per scan
- Detailed status codes and response times
- Export results to CSV
- **Rate Limit:** 5 checks per day per user

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
- Meta Generator: 5 requests
- Broken Link Checker: 5 requests
- Keyword Density (URL mode): 20 requests
- Keyword Density (Text mode): Unlimited

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

- Results are cached for 24 hours (configurable)
- Reduces API usage
- Improves performance
- Can be cleared manually from admin panel

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
- Page may have too many links (limit: 50)
- Increase timeout in settings
- Try a smaller page

### Plugin activation error
- Check PHP version (must be 8.1+)
- Check WordPress version (must be 6.0+)
- Verify server has required PHP extensions

## Performance

- Optimized for speed (<3s average response time)
- Lazy loading of JavaScript
- Aggressive caching strategy
- Minimal database queries
- No jQuery dependency on frontend (uses vanilla JS)

## Privacy & GDPR

- IP addresses are hashed before storage
- No personally identifiable information stored
- Cookie consent for reCAPTCHA
- Data retention policy (30 days default)
- Easy data deletion

## Support

For support, feature requests, or bug reports:
- Visit: https://saasmarketing.ca/contact/
- Email: info@saasmarketing.ca

## Credits

- **Developed by:** Sameer Qadri
- **For:** SaaS Marketing (saasmarketing.ca)
- **Powered by:** Google Gemini AI
- **Security:** Google reCAPTCHA v2

## Changelog

### Version 1.0.0 (2026-01-16)
- Initial release
- AI-powered meta generator
- Keyword density checker
- Broken link checker
- Database-based rate limiting
- Admin dashboard with analytics
- GDPR compliant logging
- reCAPTCHA v2 security

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
