# SEO Marketing Tools - Architecture & Technical Documentation

**Version:** 1.0.0  
**Developer:** Sameer Qadri  
**Last Updated:** January 2026

---

## Table of Contents

1. [Overview](#overview)
2. [System Architecture](#system-architecture)
3. [Core Components](#core-components)
4. [Broken Link Checker - Deep Dive](#broken-link-checker---deep-dive)
5. [Security & Rate Limiting](#security--rate-limiting)
6. [Data Flow](#data-flow)
7. [Limitations & Constraints](#limitations--constraints)
8. [Configuration](#configuration)
9. [Database Schema](#database-schema)
10. [API Integration](#api-integration)
11. [Troubleshooting](#troubleshooting)

---

## Overview

### Plugin Purpose
WordPress plugin providing three SEO tools:
1. **Meta Title & Description Generator** (AI-powered via Google Gemini)
2. **Keyword Density Checker** (Client-side analysis)
3. **Broken Link Checker** (Server-side crawling with concurrent limits)

### Technology Stack
- **Backend:** PHP 8.1+, WordPress 6.0+
- **Frontend:** JavaScript (ES6+), jQuery, HTML5, CSS3
- **AI API:** Google Gemini 2.0 Flash-Lite (Free Tier)
- **Security:** reCAPTCHA v2, WordPress Nonces
- **Storage:** WordPress Transients API + Custom Database Tables

---

## System Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    WordPress Website                        │
│  ┌───────────────────────────────────────────────────────┐ │
│  │              Frontend (Public Pages)                   │ │
│  │  - Shortcode rendering                                 │ │
│  │  - AJAX requests                                       │ │
│  │  - reCAPTCHA validation                                │ │
│  │  - Progress bar & auto-continue                        │ │
│  └────────────────┬──────────────────────────────────────┘ │
│                   │                                          │
│  ┌────────────────▼──────────────────────────────────────┐ │
│  │           AJAX Handlers (class-*-ajax.php)            │ │
│  │  - Request validation                                  │ │
│  │  - Rate limit checks                                   │ │
│  │  - Concurrent scan limits                              │ │
│  │  - URL duplicate prevention                            │ │
│  └────────────────┬──────────────────────────────────────┘ │
│                   │                                          │
│  ┌────────────────▼──────────────────────────────────────┐ │
│  │          Business Logic (Services)                     │ │
│  │  - Link_Checker: Website crawling & link validation   │ │
│  │  - Gemini_API: AI content generation                  │ │
│  │  - Keyword analysis (frontend)                         │ │
│  └────────────────┬──────────────────────────────────────┘ │
│                   │                                          │
│  ┌────────────────▼──────────────────────────────────────┐ │
│  │            Utilities & Managers                        │ │
│  │  - Rate_Limiter: Daily usage tracking                 │ │
│  │  - Scan_Lock_Manager: Concurrent & duplicate control  │ │
│  │  - Validator: Input sanitization & reCAPTCHA          │ │
│  │  - Cache_Manager: Result caching                      │ │
│  │  - Logger: Usage logging                              │ │
│  └────────────────┬──────────────────────────────────────┘ │
│                   │                                          │
│  ┌────────────────▼──────────────────────────────────────┐ │
│  │          Data Storage Layer                            │ │
│  │  - WordPress Transients (caching, locks, progress)    │ │
│  │  - Custom DB Tables (rate limits, logs)               │ │
│  └───────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
          │                           │
          ▼                           ▼
┌──────────────────┐      ┌──────────────────────┐
│  Google Gemini   │      │  External Websites   │
│  API (AI)        │      │  (for link checking) │
└──────────────────┘      └──────────────────────┘
```

### Directory Structure

```
seo-marketing-tools/
├── seo-marketing-tools.php          # Main plugin file, autoloader
├── includes/
│   ├── core/
│   │   └── class-plugin.php         # Orchestrator, hook registration
│   ├── admin/
│   │   └── class-admin.php          # Settings page, API key management
│   ├── public/
│   │   └── class-public-facing.php  # Shortcode registration
│   ├── ajax/
│   │   ├── class-meta-ajax.php      # Meta generator AJAX handler
│   │   └── class-links-ajax.php     # Link checker AJAX handler
│   ├── services/
│   │   ├── class-gemini-api.php     # Gemini API wrapper
│   │   └── class-link-checker.php   # Crawling & link validation logic
│   ├── database/
│   │   ├── class-database.php       # DB table creation
│   │   └── class-rate-limiter.php   # Rate limit enforcement
│   └── utils/
│       ├── class-validator.php      # Input validation, reCAPTCHA
│       ├── class-cache-manager.php  # Caching abstraction
│       ├── class-logger.php         # Usage logging
│       └── class-scan-lock-manager.php # Concurrent & duplicate limits
├── templates/
│   ├── meta-generator.php           # Meta tool UI
│   ├── keyword-density.php          # Keyword tool UI
│   └── broken-links.php             # Link checker UI
├── assets/
│   ├── css/
│   │   ├── admin.css                # Admin panel styles
│   │   └── public.css               # Tool UI styles (dark theme)
│   └── js/
│       ├── meta-generator.js        # Meta tool frontend logic
│       ├── keyword-density.js       # Keyword analysis (client-side)
│       └── broken-links.js          # Link checker frontend (auto-continue)
└── languages/                       # i18n translations
```

---

## Core Components

### 1. Plugin Class (`class-plugin.php`)
**Responsibility:** Central orchestrator

**Key Methods:**
- `run()`: Initialize plugin, register hooks
- `should_load_admin()`: Conditional loading (admin area only)
- `should_load_public()`: Conditional loading (frontend only)
- `define_admin_hooks()`: Register admin-specific actions
- `define_public_hooks()`: Register public-facing hooks
- `define_ajax_hooks()`: Register AJAX endpoints

**Hook Registration:**
```php
// Admin hooks (settings page)
add_action('admin_menu', [Admin, 'add_settings_page']);
add_action('admin_init', [Admin, 'register_settings']);

// Public hooks (shortcodes, assets)
add_shortcode('seo_broken_links', [Public_Facing, 'render_broken_links']);
add_action('wp_enqueue_scripts', [Public_Facing, 'enqueue_scripts']);

// AJAX hooks (authenticated + non-authenticated)
add_action('wp_ajax_seo_check_links', [Links_Ajax, 'handle_check_links']);
add_action('wp_ajax_nopriv_seo_check_links', [Links_Ajax, 'handle_check_links']);
```

---

### 2. Rate Limiter (`class-rate-limiter.php`)
**Responsibility:** Daily usage limits per tool

**Database Table:** `wp_seo_rate_limits`
```sql
CREATE TABLE wp_seo_rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_identifier VARCHAR(255) NOT NULL,  -- Hashed IP
    tool_name VARCHAR(50) NOT NULL,           -- 'meta_generator', 'link_checker'
    usage_count INT UNSIGNED DEFAULT 1,       -- Number of uses today
    last_reset_date DATE NOT NULL,            -- Date of last reset
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_client_tool_date (client_identifier, tool_name, last_reset_date)
);
```

**Daily Limits:**
- Meta Generator: **5 uses/day**
- Link Checker: **5 uses/day**
- Keyword Density (URL mode): **20 uses/day**
- Keyword Density (text mode): **Unlimited** (client-side)

**Methods:**
- `check_rate_limit($tool_name)`: Verify if user can use tool
- `increment_usage($tool_name)`: Record usage
- `get_usage_count($tool_name)`: Current usage for today
- `should_bypass_rate_limit()`: Check if user is admin (unlimited)

**Admin Bypass:**
```php
if (current_user_can('manage_options')) {
    return true; // Admins have unlimited access
}
```

---

### 3. Scan Lock Manager (`class-scan-lock-manager.php`)
**Responsibility:** Concurrent scan limits + URL duplicate prevention

**Three-Layer Protection:**

#### Layer 1: Per-User Concurrent Limit
- **Limit:** 1 active scan per user
- **Storage:** Transient `seo_scan_lock_{user_id}`
- **Purpose:** Prevent single user from running multiple scans simultaneously

#### Layer 2: Global Concurrent Limit
- **Limit:** 3 active scans sitewide
- **Storage:** Transient `seo_active_scans_count`
- **Purpose:** Protect server from overload

#### Layer 3: URL Duplicate Prevention
- **Limit:** 3 scans per website per day per user
- **Storage:** Transient `seo_scanned_urls_{user_id}` → `['url' => count]`
- **Purpose:** Prevent abuse, encourage testing diverse sites

**Key Methods:**
```php
// Concurrent limits
can_start_scan(): array                     // Check if user can start scan
acquire_lock(): bool                        // Acquire scan lock
release_lock(): void                        // Release lock on completion
update_lock_activity(): void                // Keep lock alive (30min timeout)
cleanup_abandoned_scans(): int              // Remove stale locks

// URL tracking
can_scan_url(string $url): array            // Check scan count for URL
record_scanned_url(string $url): void       // Increment scan count
normalize_url_for_tracking(string $url): string  // Consistent URL format
```

**Lock Timeout:**
- **Duration:** 30 minutes
- **Purpose:** Auto-release abandoned scans
- **Trigger:** `cleanup_abandoned_scans()` runs on each new scan request

**URL Normalization:**
```php
// All these URLs are treated as the SAME:
https://example.com
http://example.com
https://www.example.com
https://example.com/
https://example.com/page
https://example.com?query=1

// Normalized to:
https://example.com
```

---

### 4. Validator (`class-validator.php`)
**Responsibility:** Input validation + reCAPTCHA verification

**URL Validation:**
```php
validate_url(string $url): array
// Returns: ['valid' => bool, 'url' => string, 'errors' => array]

// Checks:
1. Not empty
2. Valid URL format
3. Has scheme (http/https)
4. Has valid host
5. Not localhost/private IP (SSRF protection)
```

**SSRF Protection:**
```php
// Blocked hosts:
- localhost, 127.0.0.1
- 0.0.0.0, ::1
- Private IPs: 10.*, 192.168.*, 172.16-31.*
- Link-local: 169.254.*
```

**reCAPTCHA v2 Verification:**
```php
verify_recaptcha(string $response, string $secret, string $action): array

// Process:
1. Check if response token provided
2. Send verification request to Google
3. Validate response signature
4. Check score threshold (v3) or success (v2)
5. Return validation result
```

---

## Broken Link Checker - Deep Dive

### Architecture Overview

```
User Interface (broken-links.php)
        │
        ▼
Frontend Controller (broken-links.js)
  - Form submission
  - Progress bar updates
  - Auto-continue logic
  - State management
        │
        ▼
AJAX Handler (class-links-ajax.php)
  - Request validation
  - Rate limit check
  - Concurrent limit check
  - URL duplicate check
  - Scan orchestration
        │
        ▼
Link Checker Service (class-link-checker.php)
  - Website crawling (BFS)
  - Link extraction
  - Link validation
  - State persistence
        │
        ▼
External HTTP Requests
  - wp_remote_get() for page fetching
  - wp_remote_head() for link checking
```

---

### Scan Modes

#### Quick Scan
- **Target:** Single webpage
- **Max Links:** 100 links per page
- **Process:** Synchronous, completes in one request
- **Use Case:** Fast check of specific page
- **Duration:** 10-30 seconds

```php
check_page_links(string $url, int $max_links = 100): array
```

#### Full Site Audit
- **Target:** Entire website
- **Max Pages:** 1,000 pages
- **Process:** Chunked (50 pages per chunk), auto-continue
- **Use Case:** Comprehensive site scan
- **Duration:** 2-30 minutes (depending on site size)

```php
crawl_website(string $start_url, int $max_pages = 50, ?array $resume_state = null): array
```

---

### Crawling Algorithm (BFS - Breadth-First Search)

```
Start URL: https://example.com
    │
    ▼
┌─────────────────────────────────┐
│ 1. Fetch page HTML               │
│ 2. Extract all links             │
│ 3. Filter out social media      │
│ 4. Classify: internal/external  │
└─────────────────┬───────────────┘
                  │
    ┌─────────────┼─────────────┐
    ▼                           ▼
Internal Links              External Links
(add to crawl queue)        (check status only)
    │                           │
    ▼                           ▼
Queue for crawling          Check HTTP status
(avoid duplicates)          (parallel batches)
    │
    ▼
Crawl next page
(repeat until queue empty or 1000 pages)
```

**Key Data Structures:**
```php
$visited_urls = [];          // Already crawled pages (normalized URLs)
$crawl_queue = [];           // Pages to crawl (original URLs)
$checked_link_urls = [];     // Already checked links (avoid duplicates)
$broken_links = [];          // Failed links
$working_count = 0;          // Successful links count
```

**URL Deduplication:**
```php
// Normalize URL for comparison
normalize_url(string $url): string {
    $parsed = parse_url(trim($url));
    $normalized = $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'];
    return rtrim($normalized, '/'); // Remove trailing slash
}

// Example:
https://example.com/page/  →  https://example.com/page
https://example.com/page   →  https://example.com/page
// Both treated as same page
```

---

### Link Checking (Parallel Batching)

**Per-Host Rate Limiting:**
```php
// Max 2 concurrent requests per host to avoid rate limiting
check_links_batch(array $links, int $max_concurrent = 10): array

Process:
1. Group links by hostname
2. For each host, max 2 concurrent requests
3. Use wp_remote_head() (faster than GET)
4. Timeout: 3 seconds per link
5. Retry: None (fails immediately if timeout)
```

**Link Status Classification:**
```php
// Working (200-399):
- 200 OK
- 301/302 Redirect
- 304 Not Modified

// Broken (400+):
- 404 Not Found
- 403 Forbidden  
- 500 Server Error
- Timeout
- DNS failure
```

**Social Media Link Filtering:**
```php
// Excluded domains (not crawled, not checked):
$social_domains = [
    'facebook.com', 'twitter.com', 'x.com', 'instagram.com',
    'linkedin.com', 'youtube.com', 'tiktok.com', 'pinterest.com',
    'reddit.com', 'whatsapp.com', 'telegram.org', 'discord.com',
    'snapchat.com', 'twitch.tv', 'vimeo.com', 'medium.com'
];
```

---

### State Management (Chunked Scanning)

**Why Chunking?**
- PHP `max_execution_time`: 300 seconds (5 min)
- AJAX timeout: 600 seconds (10 min)
- Large sites (500+ pages) cannot complete in single request
- Solution: Break into 50-page chunks, resume with state

**State Storage:**
```php
// Transient key: seo_scan_state_{md5($url)}
$state = [
    'visited_urls' => [],        // Crawled pages
    'queue' => [],               // Pending pages
    'broken_links' => [],        // Found broken links
    'working_count' => 0,        // Working links count
    'total_pages_crawled' => 0,  // Total pages processed
    'checked_link_urls' => []    // Already checked links
];

// Stored for 1 hour
set_transient($state_key, $state, 3600);
```

**Resume Logic:**
```php
// First request (new scan):
$result = crawl_website($url, 50, null);

// Subsequent requests (auto-continue):
$resume_state = get_transient($state_key);
$result = crawl_website($url, 50, $resume_state);

// Result includes:
[
    'has_more' => true/false,         // More pages to crawl?
    'pages_crawled' => 150,           // Total pages so far
    'estimated_remaining' => 50,      // Pages left in queue
    'state' => [...]                  // State for next chunk
]
```

---

### Auto-Continue (Frontend)

**Progress Bar System:**

```javascript
// 1. Estimated Progress (before real data)
startEstimatedProgress() {
    - Starts at 1% immediately
    - Increments 1-2% every 5-8 seconds (random)
    - Caps at 95% maximum
    - Prevents "frozen at 0%" appearance
}

// 2. Real Progress (from server)
updateProgressBar(data) {
    - Calculates: pages_crawled / estimated_total_pages
    - Uses max(estimated, real) to avoid going backwards
    - Shows 100% only when has_more = false
}

// 3. Auto-Continue Logic
if (data.has_more && autoContinueEnabled) {
    // Wait 500ms, then trigger next chunk
    setTimeout(() => continueScan(url), 500);
} else {
    // Scan complete, show results
    displayResults(data);
}
```

**State Variables:**
```javascript
let currentResults = null;              // Accumulated results
let totalPagesScanned = 0;              // Total pages across chunks
let totalLinksChecked = 0;              // Total links checked
let estimatedTotalPages = 0;            // Estimated site size
let currentEstimatedProgress = 0;       // Estimated % (0-95)
let realProgressReceived = false;       // Switch from estimated to real
```

**Results Display Timing:**
```javascript
// During auto-continue: HIDE results
if (data.has_more && autoContinueEnabled) {
    updateProgressBar(data);          // Update progress
    $('#link-results').hide();        // Keep results hidden
    continueScan(url);                // Start next chunk
}

// Scan complete: SHOW results
else {
    updateProgressBar(data);          // Update to 100%
    displayResults(data);             // Show results table
    scrollToResults();                // Scroll to results
}
```

---

## Security & Rate Limiting

### Multi-Layer Security

```
┌────────────────────────────────────────────┐
│ Layer 1: reCAPTCHA v2 (Bot Protection)    │
│  - Checkbox verification                   │
│  - Server-side validation                  │
│  - Required for new scans only             │
└────────────────┬───────────────────────────┘
                 ▼
┌────────────────────────────────────────────┐
│ Layer 2: WordPress Nonce (CSRF Protection)│
│  - Generated per-session                   │
│  - Verified on every AJAX request          │
│  - Expires after 12-24 hours               │
└────────────────┬───────────────────────────┘
                 ▼
┌────────────────────────────────────────────┐
│ Layer 3: URL Duplicate Prevention         │
│  - 3 scans per website per day             │
│  - Per-user tracking (ID or IP)            │
│  - Resets at midnight                      │
└────────────────┬───────────────────────────┘
                 ▼
┌────────────────────────────────────────────┐
│ Layer 4: Daily Rate Limits                │
│  - 5 scans per day total                   │
│  - Per-tool limits                         │
│  - Database-backed (atomic)                │
└────────────────┬───────────────────────────┘
                 ▼
┌────────────────────────────────────────────┐
│ Layer 5: Concurrent Scan Limits           │
│  - 1 active scan per user                  │
│  - 3 active scans globally                 │
│  - 30-minute auto-cleanup                  │
└────────────────┬───────────────────────────┘
                 ▼
┌────────────────────────────────────────────┐
│ Layer 6: Input Validation & SSRF          │
│  - URL format validation                   │
│  - Private IP blocking                     │
│  - Localhost blocking                      │
└────────────────────────────────────────────┘
```

### Rate Limit Matrix

| Limit Type | Free User | Admin | Scope | Reset |
|------------|-----------|-------|-------|-------|
| **Daily Scans (Total)** | 5 | ∞ | Per user | Midnight |
| **Same Website Scans** | 3 | ∞ | Per URL, per user | Midnight |
| **Concurrent Scans (User)** | 1 | 1 | Per user | On completion |
| **Concurrent Scans (Global)** | 3 | 3 | Sitewide | On completion |
| **Meta Generation** | 5/day | ∞ | Per user | Midnight |
| **Keyword Density (URL)** | 20/day | ∞ | Per user | Midnight |
| **Keyword Density (Text)** | ∞ | ∞ | Client-side | N/A |

---

## Data Flow

### Broken Link Checker - Complete Flow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. USER SUBMITS FORM                                        │
│    - Enters URL                                             │
│    - Selects scan mode (Quick/Full)                         │
│    - Completes reCAPTCHA                                    │
│    - Clicks "Start Scanning"                                │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. FRONTEND VALIDATION (broken-links.js)                   │
│    ✓ Scan mode selected?                                    │
│    ✓ URL provided and valid format?                         │
│    ✓ reCAPTCHA completed?                                   │
│    → Send AJAX request to backend                           │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. AJAX HANDLER (class-links-ajax.php)                     │
│    ✓ Verify nonce (CSRF protection)                         │
│    ✓ Validate scan mode (quick/full)                        │
│    ✓ Validate URL (format + SSRF check)                     │
│    ✓ Check if URL already scanned (< 3 times)              │
│    ✓ Check concurrent scan limits (user + global)           │
│    ✓ Verify reCAPTCHA (server-side)                         │
│    ✓ Check daily rate limit (< 5 scans)                     │
│    ✓ Acquire scan lock                                      │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. LINK CHECKER SERVICE (class-link-checker.php)           │
│                                                              │
│    Quick Scan:                  Full Site Audit:            │
│    - Fetch 1 page               - Crawl 50 pages (chunk)    │
│    - Extract links              - BFS algorithm             │
│    - Check up to 100            - Extract all links         │
│    - Return results             - Filter social media       │
│                                 - Check links (parallel)    │
│                                 - Save state for resume     │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. RESPONSE TO FRONTEND                                     │
│    {                                                         │
│      "success": true,                                        │
│      "pages_crawled": 50,                                    │
│      "total_links_checked": 234,                             │
│      "broken_links_count": 5,                                │
│      "broken_links": [...],                                  │
│      "has_more": true,                      // More to scan? │
│      "estimated_total_pages": 150,          // Site size     │
│      "estimated_remaining": 100             // Pages left    │
│    }                                                         │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 6. FRONTEND AUTO-CONTINUE (broken-links.js)                │
│                                                              │
│    if (has_more == true):                                   │
│      - Update progress bar (e.g., 15%)                      │
│      - Keep results hidden                                  │
│      - Wait 500ms                                           │
│      - Trigger next chunk (back to step 3)                  │
│                                                              │
│    if (has_more == false):                                  │
│      - Update progress to 100%                              │
│      - Display results table                                │
│      - Scroll to results                                    │
│      - Record URL as scanned                                │
│      - Release scan lock                                    │
└─────────────────────────────────────────────────────────────┘
```

---

## Limitations & Constraints

### Server Limits (Shared Hosting)

| Resource | Typical Limit | Plugin Usage | Impact |
|----------|---------------|--------------|--------|
| **PHP max_execution_time** | 300s (5 min) | 50-page chunks | Chunking required |
| **PHP memory_limit** | 256MB-512MB | ~50MB per scan | Safe |
| **max_input_vars** | 1000 | ~100 per request | Safe |
| **Apache timeout** | 300-600s | AJAX 10 min timeout | May timeout on slow sites |
| **Database connections** | 15-25 | 1-2 per request | Safe |

### Crawling Constraints

| Constraint | Limit | Reason |
|------------|-------|--------|
| **Max pages per site** | 1,000 | Prevent excessive resource use |
| **Pages per chunk** | 50 | Balance speed vs timeout |
| **Links per page (Quick)** | 100 | Reasonable sample size |
| **Link check timeout** | 3 seconds | Fast fail for dead links |
| **Concurrent link checks** | 10 total, 2/host | Avoid rate limiting |
| **Social media filtering** | Yes | Reduce noise, save time |

### User Limits

| Limit Type | Value | Scope | Purpose |
|------------|-------|-------|---------|
| **Daily scans (total)** | 5 | Per user | Fair resource allocation |
| **Same website scans** | 3 | Per URL, per user | Allow iteration, prevent spam |
| **Concurrent scans** | 1 | Per user | Prevent self-DDoS |
| **Global concurrent** | 3 | Sitewide | Server protection |
| **Scan lock timeout** | 30 min | Per scan | Auto-cleanup abandoned scans |

### API Constraints (Google Gemini)

| Metric | Free Tier (Flash-Lite) | Plugin Behavior |
|--------|------------------------|-----------------|
| **Requests per day** | 1,000 | User limit: 5/day → max 5k users |
| **Requests per minute** | 15 | No burst limiting (sequential) |
| **Tokens per request** | ~8k input | Safe (typical request: 500 tokens) |
| **Rate limit errors** | 429 status | No retry, show error immediately |
| **Quota exceeded** | Daily reset | Block further requests until tomorrow |

---

## Configuration

### Admin Settings (`/wp-admin` → SEO Marketing Tools)

```php
// API Keys
seo_tools_gemini_api_key        // Google Gemini API key (encrypted)
seo_tools_recaptcha_site_key    // reCAPTCHA v2 Site Key
seo_tools_recaptcha_secret_key  // reCAPTCHA v2 Secret Key

// Rate Limits (per day)
seo_tools_rate_limit_meta       // Meta Generator: default 5
seo_tools_rate_limit_links      // Link Checker: default 5
seo_tools_rate_limit_kw_url     // Keyword Density (URL): default 20
```

### Hardcoded Constants

```php
// Scan Lock Manager
MAX_CONCURRENT_SCANS = 3        // Global concurrent scan limit
LOCK_TIMEOUT = 1800             // 30 minutes in seconds
MAX_SCANS_PER_URL = 3           // Per-website daily limit

// Link Checker
MAX_PAGES_TOTAL = 1000          // Max pages to crawl per site
MAX_PAGES_PER_CHUNK = 50        // Pages per chunk (Full Site Audit)
MAX_LINKS_QUICK_SCAN = 100      // Links to check (Quick Scan)
LINK_CHECK_TIMEOUT = 3          // Seconds per link check
MAX_CONCURRENT_LINKS = 10       // Parallel link checks
MAX_CONCURRENT_PER_HOST = 2     // Requests per hostname

// Cache
CACHE_EXPIRY = 3600             // 1 hour in seconds

// Gemini API
GEMINI_MODEL = 'gemini-2.0-flash-lite'
GEMINI_MAX_TOKENS = 500         // Response length limit
GEMINI_TEMPERATURE = 0.7        // Creativity (0=deterministic, 1=random)
```

---

## Database Schema

### Rate Limits Table

```sql
CREATE TABLE `wp_seo_rate_limits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_identifier` VARCHAR(255) NOT NULL COMMENT 'Hashed IP or User ID',
  `tool_name` VARCHAR(50) NOT NULL COMMENT 'meta_generator, link_checker, etc.',
  `usage_count` INT UNSIGNED NOT NULL DEFAULT 1,
  `last_reset_date` DATE NOT NULL COMMENT 'Date of last counter reset',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_client_tool_date` (`client_identifier`, `tool_name`, `last_reset_date`),
  INDEX `idx_client` (`client_identifier`),
  INDEX `idx_tool` (`tool_name`),
  INDEX `idx_reset_date` (`last_reset_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Usage Logs Table

```sql
CREATE TABLE `wp_seo_usage_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tool_name` VARCHAR(50) NOT NULL,
  `client_ip` VARCHAR(45) NOT NULL COMMENT 'Hashed IP (GDPR compliant)',
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `request_data` TEXT DEFAULT NULL COMMENT 'JSON of request params',
  `response_status` VARCHAR(20) NOT NULL COMMENT 'success, error, rate_limited',
  `response_data` TEXT DEFAULT NULL COMMENT 'JSON of response (partial)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_tool` (`tool_name`),
  INDEX `idx_status` (`response_status`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Transients (WordPress Options Table)

```php
// Scan state (1 hour expiry)
'seo_scan_state_{md5($url)}' => [
    'visited_urls' => [...],
    'queue' => [...],
    'broken_links' => [...],
    'working_count' => 123,
    'total_pages_crawled' => 150
]

// Scan locks (30 min expiry)
'seo_scan_lock_{user_id}' => [
    'user_id' => 'user_123',
    'start_time' => 1234567890,
    'last_activity' => 1234567900
]

'seo_active_scans_count' => 2
'seo_active_scans_list' => ['user_123' => [...], 'user_456' => [...]]

// URL tracking (expires at midnight)
'seo_scanned_urls_{user_id}' => [
    'https://example.com' => 2,      // Scanned 2 times
    'https://site2.com' => 1         // Scanned 1 time
]

// Cache (1 hour expiry)
'seo_cache_links_{hash}' => [
    'total_links_checked' => 234,
    'broken_links_count' => 5,
    'broken_links' => [...]
]
```

---

## API Integration

### Google Gemini API

**Endpoint:**
```
POST https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent
```

**Request Format:**
```json
{
  "contents": [
    {
      "parts": [
        {
          "text": "Generate SEO meta title and description for: https://example.com\n\nTitle requirements: 50-60 characters, engaging, keyword-rich...\n\nReturn ONLY valid JSON: {\"title\": \"...\", \"description\": \"...\"}"
        }
      ]
    }
  ],
  "generationConfig": {
    "temperature": 0.7,
    "maxOutputTokens": 500,
    "topP": 1,
    "topK": 1
  }
}
```

**Headers:**
```
Content-Type: application/json
x-goog-api-key: {API_KEY}
```

**Response:**
```json
{
  "candidates": [
    {
      "content": {
        "parts": [
          {
            "text": "{\"title\":\"Best SEO Tools...\",\"description\":\"Discover...\"}"
          }
        ]
      }
    }
  ]
}
```

**Error Handling:**
- **429 (Quota Exceeded):** Show error, no retry
- **400 (Invalid Request):** Log error, show generic message
- **500 (Server Error):** Retry once after 2 seconds
- **Network Timeout:** Show network error

**Circuit Breaker:**
- Consecutive failures: 3
- Cooldown period: 5 minutes
- Purpose: Prevent hammering API when it's down

---

## Troubleshooting

### Common Issues

#### 1. "API quota exceeded" Error
**Symptoms:** Gemini API returns 429 after few requests  
**Causes:**
- Free tier daily limit reached (1,000 RPD)
- Too many users on same API key
- Rate limit hit (15 RPM)

**Solutions:**
- Wait until tomorrow (quota resets at midnight PST)
- Upgrade to paid tier
- Implement per-API-key rotation

---

#### 2. Scan Stuck at 0% Progress
**Symptoms:** Progress bar doesn't move for 5+ minutes  
**Causes:**
- Server timeout (max_execution_time too low)
- AJAX request aborted
- JavaScript error in console

**Solutions:**
- Check browser console for errors
- Verify server `max_execution_time >= 300`
- Check `debug.log` for PHP errors
- Clear browser cache and retry

---

#### 3. "Server busy" Error (3 scans running)
**Symptoms:** Cannot start scan, message says server busy  
**Causes:**
- 3 concurrent scans already active
- Abandoned scans not cleaned up

**Solutions:**
- Wait 5-10 minutes for scans to complete
- Clear transients: `delete_transient('seo_active_scans_*')`
- Check for stuck PHP processes

---

#### 4. "Already scanned this website" (false positive)
**Symptoms:** Cannot scan website, but user claims they haven't  
**Causes:**
- Transient still active from previous day
- URL normalization issue (www vs non-www)

**Solutions:**
- Clear transient: `delete_transient('seo_scanned_urls_*')`
- Check URL normalization logic
- Wait until midnight for automatic reset

---

#### 5. Results Show After First Chunk (Pre-mature display)
**Symptoms:** Results appear while scan is still running  
**Causes:**
- Bug in `handleScanComplete()` logic
- `has_more` flag not respected

**Solutions:**
- Verify `has_more` check in frontend
- Ensure results only shown when `has_more = false`
- Check `displayResults()` is only called on completion

---

### Debug Mode

**Enable WordPress Debug:**
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**Plugin-Specific Logging:**
```php
// Logs to wp-content/debug.log
error_log('[SEO Tools] Scan started: ' . $url);
error_log('[SEO Tools] Chunk complete: ' . $pages_crawled . ' pages');
```

**Browser Console:**
```javascript
// broken-links.js
console.log('Progress:', currentEstimatedProgress, '%');
console.log('Real data received:', data);
```

---

## Performance Optimization

### Caching Strategy

| Data Type | Cache Duration | Invalidation |
|-----------|----------------|--------------|
| Link check results | 1 hour | Manual or auto-expire |
| Scan state (chunks) | 1 hour | On completion or cancel |
| Rate limit data | Until midnight | Daily reset |
| Scan locks | 30 minutes | On release or timeout |

### Database Queries

**Optimized:**
```php
// Single query with UPSERT
INSERT INTO wp_seo_rate_limits (client_identifier, tool_name, usage_count, last_reset_date)
VALUES (?, ?, 1, CURDATE())
ON DUPLICATE KEY UPDATE usage_count = usage_count + 1
```

**Indexed Columns:**
- `(client_identifier, tool_name, last_reset_date)` - UNIQUE
- `client_identifier` - Lookup by user
- `tool_name` - Lookup by tool
- `created_at` - Date range queries

---

## Future Enhancements

### Planned Features
1. **Email Reports:** Send scan results via email
2. **Scheduled Scans:** Cron-based weekly/monthly scans
3. **Historical Tracking:** Compare scans over time
4. **Multi-API Support:** Fallback to alternative AI providers
5. **Premium Tier:** Unlimited scans, priority queue
6. **Export Formats:** PDF, CSV, JSON
7. **Webhook Integration:** Notify external services
8. **White-Label:** Custom branding options

---

## Version History

### v1.0.0 (January 2026)
- ✅ Initial release
- ✅ Meta Title & Description Generator (Gemini AI)
- ✅ Keyword Density Checker (client-side)
- ✅ Broken Link Checker (full site crawling)
- ✅ reCAPTCHA v2 integration
- ✅ Multi-layer rate limiting
- ✅ Auto-continue for large scans
- ✅ Dynamic progress bar
- ✅ URL duplicate prevention (3 scans/day)
- ✅ Concurrent scan limits (1 user, 3 global)

---

## Credits & License

**Developer:** Sameer Qadri  
**Website:** [saasmarketing.ca](https://saasmarketing.ca)  
**License:** GPL v2 or later  
**Repository:** [GitHub](https://github.com/sameerqadri1/wordpress-seo-tools-plugin)

**Third-Party Services:**
- Google Gemini API (AI content generation)
- Google reCAPTCHA v2 (bot protection)

---

**End of Documentation**
