# SEO Marketing Tools - Architecture & Technical Documentation

**Version:** 1.0.0  
**Developer:** Sameer Qadri  
**Last Updated:** January 2026  
**Live Demo:** https://saasmarketing.ca/seo-tools/

---

## Table of Contents

1. [Overview](#overview)
2. [System Architecture](#system-architecture)
3. [Core Components](#core-components)
4. [Meta Generator - Deep Dive](#meta-generator---deep-dive)
5. [Keyword Density Checker - Deep Dive](#keyword-density-checker---deep-dive)
6. [Broken Link Checker - Deep Dive](#broken-link-checker---deep-dive)
7. [Security & Rate Limiting](#security--rate-limiting)
8. [Data Flow](#data-flow)
9. [Limitations & Constraints](#limitations--constraints)
10. [Configuration](#configuration)
11. [Database Schema](#database-schema)
12. [API Integration](#api-integration)
13. [Troubleshooting](#troubleshooting)

---

## Overview

### Plugin Purpose
WordPress plugin providing three SEO tools:
1. **Meta Title & Description Generator** (AI-powered via Google Gemini)
2. **Keyword Density Checker** (Hybrid: client-side analysis + server-side URL fetching with rate limits)
3. **Broken Link Checker** (Server-side crawling with concurrent limits)

### Technology Stack
- **Backend:** PHP 8.1+, WordPress 6.0+
- **Frontend:** JavaScript (ES6+), jQuery, HTML5, CSS3
- **AI API:** Google Gemini 2.5 Flash-Lite (Free Tier, default)
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

**Global Gemini API Limits (Free Tier):**
- **Requests Per Day (RPD):** 20 requests/day (shared across all users)
- **Requests Per Minute (RPM):** 10 requests/minute (shared across all users)
- **Tokens Per Minute (TPM):** 250,000 tokens/minute (shared across all users)
- **Per-User Limits:** 5 meta generations/day, 2 requests/minute

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
- **Limit:** 3 scans per website per day per user (increased from 1)
- **Storage:** Transient `seo_scanned_urls_{user_id}` → `['url' => count]`
- **Purpose:** Prevent abuse, allow reasonable retesting, encourage testing diverse sites

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

## Meta Generator - Deep Dive

### Overview

The Meta Generator tool uses Google Gemini 2.5 Flash-Lite AI to generate SEO-optimized meta titles and descriptions. It combines intelligent prompt engineering with strict length validation to produce compelling, keyword-rich meta tags that improve click-through rates.

**Key Features:**
- AI-powered generation using Google Gemini 2.5 Flash-Lite
- Strict character limits (50-60 for title, 150-160 for description)
- Real-time character counting with visual indicators
- Page-type context awareness (homepage, service, blog, product, etc.)
- Multi-layer rate limiting (per-user + global API limits)
- Token tracking and usage monitoring
- Circuit breaker pattern for API reliability

### Components

- **Template:** `templates/meta-generator.php`
- **Frontend logic:** `assets/js/meta-generator.js`
- **AJAX handler:** `includes/ajax/class-meta-ajax.php::handle_generate_meta()`
- **Business logic:** `includes/services/class-meta-generator.php`
- **API wrapper:** `includes/services/class-gemini-api.php`
- **Rate limiting:** `includes/database/class-rate-limiter.php`
- **Validation:** `includes/utils/class-validator.php`

### Data Flow

```
User Fills Form (meta-generator.php)
        │
        ▼
Frontend (meta-generator.js)
  ✓ Validate form fields
  ✓ Check reCAPTCHA completion
  ✓ Check rate limit status (display remaining)
  → AJAX: seo_generate_meta
        │
        ▼
AJAX Handler (Meta_Ajax::handle_generate_meta)
  ✓ Verify nonce (CSRF protection)
  ✓ Verify reCAPTCHA (server-side)
  ✓ Check global Gemini RPD (20/day)
  ✓ Check global Gemini RPM (10/minute)
  ✓ Estimate tokens → Check global Gemini TPM (250k/min)
  ✓ Check per-user rate limit (5/day, 2/minute)
  ✓ Acquire per-minute lock (prevent burst)
        │
        ▼
Meta Generator Service (Meta_Generator::generate)
  → Build prompt with context
  → Call Gemini_API::generate_meta()
        │
        ▼
Gemini API Wrapper (Gemini_API)
  ✓ Build AI prompt (page-type aware)
  ✓ Call API with retry logic (max 2 retries)
  ✓ Parse JSON response
  ✓ Extract title & description
  ✓ Track tokens used
  ✓ Update circuit breaker state
        │
        ▼
Response Processing
  ✓ Validate character lengths
  ✓ Truncate if needed (with ellipsis)
  ✓ Return results + token usage
        │
        ▼
Frontend (meta-generator.js)
  ✓ Display generated title & description
  ✓ Show character counts with color indicators
  ✓ Enable copy buttons
  ✓ Update rate limit status
  ✓ Log usage (Logger)
```

### AI Prompt Engineering

The tool uses context-aware prompts that adapt based on page type:

**Prompt Structure:**
```
You are an expert SEO specialist. Generate an optimized meta title and meta description for a webpage.

Context:
- Keyword: [user's keyword]
- Business Name: [user's business name]
- Page Description: [user's description]
- Page Type: [homepage/service/blog/product/about/contact]

Requirements:
1. Meta Title:
   - Length: Exactly 50-60 characters (strict requirement)
   - Include the keyword naturally
   - Include business name if it fits
   - Make it compelling and click-worthy
   - Use power words when appropriate

2. Meta Description:
   - Length: Exactly 150-160 characters (strict requirement)
   - Include the keyword naturally (1-2 times)
   - Compelling call-to-action
   - Describe the value proposition
   - Make it engaging and click-worthy

Format your response EXACTLY like this (no extra text):
TITLE: [your optimized title here]
DESCRIPTION: [your optimized description here]
```

**Page-Type Context:**
- **Homepage:** Emphasizes brand and main value proposition
- **Service:** Focuses on service benefits and keywords
- **Blog:** Highlights topic and engagement
- **Product:** Emphasizes features and benefits
- **About:** Personalizes brand story
- **Contact:** Encourages action and accessibility

### Rate Limiting & API Management

**Multi-Layer Protection:**

1. **Global Gemini API Limits (Enforced First):**
   - **RPD (Requests Per Day):** 20 requests/day (shared across all users)
   - **RPM (Requests Per Minute):** 10 requests/minute (shared across all users)
   - **TPM (Tokens Per Minute):** 250,000 tokens/minute (shared across all users)
   - **Purpose:** Protect free-tier API quota from exhaustion
   - **Storage:** WordPress transients with date-based keys

2. **Per-User Daily Limit:**
   - **Limit:** 5 generations per day per IP/user
   - **Storage:** `wp_seo_rate_limits` table
   - **Reset:** Midnight (site timezone)
   - **Admin Bypass:** Administrators have unlimited access

3. **Per-User Per-Minute Limit:**
   - **Limit:** 2 requests per minute per IP/user
   - **Purpose:** Prevent burst traffic and API abuse
   - **Storage:** Transient with 60-second expiry
   - **Admin Bypass:** Administrators still subject to global API limits

**Rate Limit Display:**
- Frontend shows remaining generations for the day
- Color-coded indicators:
  - **Green:** Unlimited (admin)
  - **Blue:** 3+ remaining
  - **Yellow:** 1-2 remaining
  - **Red:** 0 remaining (disabled button)
- Real-time updates after each generation

### Character Validation & Truncation

**Validation Rules:**
- **Title:** 50-60 characters (optimal SEO length)
- **Description:** 150-160 characters (optimal for SERP display)

**Truncation Logic:**
```php
// If AI generates content that's too long:
if ($title_length > 60) {
    $result['title'] = substr($result['title'], 0, 57) . '...';
    $title_length = 60;
}

if ($desc_length > 160) {
    $result['description'] = substr($result['description'], 0, 157) . '...';
    $desc_length = 160;
}
```

**Frontend Indicators:**
- Real-time character counting as user types
- Color-coded length indicators:
  - **Green:** Optimal range
  - **Yellow:** Close to limit
  - **Red:** Over limit (truncated)

### Token Tracking & Usage

**Token Estimation:**
- **Formula:** `1 token ≈ 4 characters` (English text)
- **Input tokens:** Prompt length / 4
- **Output tokens:** Response length / 4 (estimated ~75 tokens)
- **Total:** Input + Output tokens

**Token Tracking:**
- Tracked per request in `Gemini_API::call_api()`
- Returned in AJAX response: `tokens_used`, `tokens_breakdown`
- Used for global TPM limit enforcement
- Displayed in admin dashboard (Global Gemini API Status)

### Error Handling & Retry Logic

**Retry Strategy:**
- **Max Retries:** 2 attempts (3 total)
- **Exponential Backoff:** 2s, 4s delays
- **No Retry For:**
  - Invalid API key
  - Quota exceeded (429)
  - Invalid request (400)

**Error Codes:**
- `INVALID_NONCE`: CSRF token expired
- `CAPTCHA_FAILED`: reCAPTCHA verification failed
- `GEMINI_RPD_EXCEEDED`: Global daily limit reached
- `GEMINI_RPM_EXCEEDED`: Global per-minute limit reached
- `GEMINI_TPM_EXCEEDED`: Global token limit reached
- `RATE_LIMIT_EXCEEDED`: Per-user daily limit reached
- `API_ERROR`: Gemini API error (with retry)
- `NETWORK_ERROR`: Connection timeout/failure

### Circuit Breaker Pattern

**Purpose:** Prevent hammering the API when it's down or slow.

**States:**
- **Closed:** Normal operation, API calls allowed
- **Open:** API failing, requests blocked (cooldown period)
- **Half-Open:** Testing if API recovered

**Implementation:**
- **Failure Threshold:** 3 consecutive failures
- **Cooldown Period:** 5 minutes
- **Storage:** Transient `seo_gemini_circuit_state`
- **Auto-Recovery:** Automatically transitions to half-open after cooldown

**Benefits:**
- Reduces unnecessary API calls during outages
- Faster error responses (no waiting for timeout)
- Protects API quota from wasted requests

### UI Features

**Form Fields:**
- **Target Keyword:** Max 100 chars, required
- **Business Name:** Max 50 chars, required
- **Page Description:** Max 300 chars, required
- **Page Type:** Dropdown (homepage, service, blog, product, about, contact)

**Real-Time Feedback:**
- Character counters for all text inputs
- Rate limit status display (remaining generations)
- Loading state during generation ("⏳ Generating...")
- Success/error messages

**Results Display:**
- Generated title with character count indicator
- Generated description with character count indicator
- Copy buttons for easy copying
- "Generate Another" button to reset form
- Color-coded length indicators (green/yellow/red)

**Global API Status (Admin):**
- Real-time display of RPD, RPM, TPM usage
- Shows current counts vs limits
- Helps admins monitor API quota consumption

### Mobile Responsiveness

The Meta Generator UI is fully responsive:
- Form fields stack vertically on mobile
- Character counters remain visible
- Copy buttons are touch-friendly (44px height)
- Results cards adapt to screen width
- reCAPTCHA widget scales appropriately

---

## Keyword Density Checker - Deep Dive

### Overview

The Keyword Density tool has evolved from a basic "word counter" into a **Content Strategist** that evaluates how well a page is optimized around its topic, not just how often words appear.

It has two modes:

- **Paste Text Mode (Client-side only)**
  - All analysis happens in the browser.
  - No server calls, no rate limits.
  - Unlimited usage.

- **URL Mode (Hybrid: Client + Server)**
  - Server fetches page HTML (with rate limits, reCAPTCHA, caching).
  - Browser performs all content analysis.
  - Protected by per-minute, concurrent, and daily limits.

### Components

- Template: `templates/keyword-density.php`
- Frontend logic: `assets/js/keyword-density.js`
- AJAX handler for URL mode: `includes/ajax/class-meta-ajax.php::handle_fetch_url_content()`
- Rate limiting + caching: `includes/database/class-rate-limiter.php`
- Validation + reCAPTCHA: `includes/utils/class-validator.php`

### Data Flow

#### A. Paste Text Mode (100% Client-Side)

```
User Pastes Text (keyword-density.php)
        │
        ▼
keyword-density.js
  - Clean text (lowercase, strip HTML)
  - Count words
  - Build 1–4 word n-grams
  - Apply stop words + min length (≥ 3)
  - Calculate density & prominence
  - Analyze SEO elements (Title/H1/etc. - when available)
  - Compute readability (Flesch Reading Ease)
  - Compute overall relevancy score (0–100)
        │
        ▼
Render Results (no server calls)
```

#### B. URL Mode (Hybrid)

```
User Enters URL (keyword-density.php)
        │
        ▼
Frontend (keyword-density.js)
  ✓ Validate URL format
  ✓ Ensure reCAPTCHA completed
  → AJAX: seo_fetch_url_content
        │
        ▼
AJAX Handler (Meta_Ajax::handle_fetch_url_content)
  ✓ Verify nonce (seo_keyword_nonce)
  ✓ Validate URL (Validator::validate_url - SSRF safe)
  ✓ Verify reCAPTCHA (server-side)
  ✓ Check per-minute limit (3/min/IP)
  ✓ Check concurrent limit (5 active/IP)
  ✓ Check daily limit (20/day/IP)
  ✓ Check cache (15-minute transient, URL-based key)
     ├─ If cached & !force_refresh → return cached text
     └─ Else → fetch fresh HTML via wp_remote_get()
  ✓ Extract text from HTML (wp_strip_all_tags)
  ✓ Normalize whitespace
  ✓ Cache result for 15 minutes
  ✓ Log usage (Logger)
        │
        ▼
Frontend (keyword-density.js)
  - Analyze text (same pipeline as Paste Text)
  - Show cache age / expiry and force-refresh tip
  - Render full Content Strategist view
```

### Content Strategist Model

The tool has evolved from a basic "word counter" into a **Content Strategist** that evaluates how well a page is optimized around its topic, not just how often words appear. It surfaces a **Content Relevancy Score (0–100)**, not just raw frequency. It combines four pillars:

1. **Prominence (0–40 points)**
   - Where do key phrases appear, not just how often?
   - Heuristics (implemented in JS):
     - Presence in **first 100 words**
     - Presence in **first paragraph**
     - Presence in **H1** (when available)
   - Higher weight for early and structural appearances.

2. **SEO Elements (0–30 points)**
   - Evaluates core on-page elements when available in the input:
     - Title tag
     - Meta description
     - H1
     - Alt attributes (where present)
   - Flags "must-fix" issues (e.g., keyword missing from Title/H1) in the UI.

3. **Keyword Density (0–20 points)**
   - Calculates density based on **filtered tokens**:
     - Minimum word length: **≥ 3 characters**
     - Expanded stop word list (100+ common words)
     - Phrase-based analysis (1–4 word n-grams):
       - 1-word: core terms
       - 2–3-word: key phrases
       - 4-word: **long-tail opportunities** (new in v1.0.0)
   - Density is recomputed **after filtering** so percentages match what users see.

4. **Readability (0–10 points)**
   - Uses **Flesch Reading Ease**:
     - Splits on sentence boundaries (., !, ?) with punctuation preserved
     - Falls back to heuristic splitting when punctuation is sparse
     - Computes:
       - Average sentence length (words per sentence)
       - Average syllables per word (approximate)
   - Maps score to:
     - Grade Level (e.g., 8th–9th, College)
     - Status (e.g., "standard", "very difficult")
   - Exposed in the UI with color-coded badges:
     - Green: easy
     - Yellow: medium
     - Red: hard

The final **Relevancy Score (0–100)** is derived from these components and displayed prominently, with a breakdown per pillar so users understand *why* a page scores high or low.

### Stemming & Variant Analysis

To avoid misleading users about "low density" when they use many variants, the tool includes a lightweight **Porter Stemmer** implementation in JavaScript.

- **Goal:** Group morphological variants that search engines treat as the same concept:
  - `running`, `runs`, `runner` → `run`
  - `optimization`, `optimize`, `optimized` → `optim`

#### How It Works

- For 1–3 word phrases:
  - Generate the normal keyword list (phrases + counts + density).
  - Generate a **stemmed view** where each phrase is reduced to its root form.
  - Aggregate counts and density per stemmed phrase.
- UI:
  - Toggle: **"Show Stemmed (Google's View)"**
  - When enabled, the table shows:
    - Root phrase
    - Combined count / density
    - List of variants (e.g., `running, runs, runner`).

#### Over-Optimization Warnings

The tool detects when multiple variants combine into an SEO risk:

- Condition (1-word stems):
  - At least **3 variants** (e.g., `run, running, runner`)
  - Combined density **> 2.5%**
- Surface a warning block:
  - Root term
  - Variant list
  - Total appearances + density
  - Guidance that search engines may see this as over-optimization.

This makes the tool behave much closer to how modern search engines interpret text (entity/intent-based rather than raw tokens).

### Stop Words & Token Filtering

To focus on meaningful terms:

- Stop word list expanded to **100+ terms**, including:
  - Articles, pronouns, helper verbs
  - Common question words (what, how, where…)
  - Filler words ("basically", "actually", etc.) where applicable
- Single-word phrases:
  - Discard if:
    - Word is a stop word
    - Word length \< 3
- Multi-word phrases:
  - Built from the cleaned token stream (stop words removed where appropriate).

### URL Mode: Rate Limits, Caching & Force Refresh

URL mode is protected by a **multi-layer** system to balance UX with server safety:

- **Daily Limit (database-backed):**
  - Tool key: `keyword_density_url`
  - Default: **20 requests/day per IP**
- **Per-Minute Limit (transient):**
  - Max **3 requests/minute per IP**.
  - Returns a friendly "Too many requests. Please wait a minute" error when exceeded.
- **Concurrent Requests (transient):**
  - Max **5 active requests per IP** for this tool.
  - Prevents a single user from overwhelming the server.
- **Caching (transient, 15 minutes):**
  - Keyed by URL hash: `seo_url_content_{md5(url)}`
  - Stores:
    - Extracted text
    - Word count
    - Cached timestamp + expiry timestamp
  - Benefits:
    - Subsequent users analyzing the same URL get **instant** results.
    - Cached responses **do not** consume daily/minute/concurrent budgets (aside from reCAPTCHA).
  - **Force Refresh Option:** Users can bypass cache with a checkbox to fetch fresh content (still subject to rate limits).

#### Force Refresh

Some users want to re-test a page immediately after editing it. For this, the UI exposes:

- Checkbox: **"Force fresh analysis (bypasses 15-minute cache)"**
- Behavior:
  - Sends `force_refresh = true` in the AJAX payload.
  - Skips cache lookup; always refetches from origin.
  - Still subject to:
    - Daily limit (20/day)
    - Per-minute limit (3/min)
    - Concurrent limit (5 active)
  - Checkbox auto-resets after each run to avoid accidental overuse.
- Response metadata includes:
  - `cached` (bool)
  - `cache_age_minutes`
  - `cache_expires_minutes`
  - `cached_at` (human-readable)

The frontend renders a clear info banner when cached data is used, telling the user when it was analyzed and when it will refresh, plus a tip to enable force refresh if content changed.

### Mobile Responsiveness

The Keyword Density tool UI is heavily optimized for mobile, implemented in `assets/css/public.css`:

- **Breakpoints:**
  - `@media (max-width: 768px)` – tablets / small laptops
  - `@media (max-width: 480px)` – phones
- **Responsive Behaviors:**
  - Forms and result cards reduce padding on small screens.
  - Input mode tabs and filter tabs wrap and become full-width buttons where needed.
  - Stats row and score breakdown switch from multi-column to 2-column (tablet) then 1-column (phone).
  - Readability, prominence, and SEO element sections stack vertically.
  - Results table becomes horizontally scrollable with touch-friendly scrolling.
  - Buttons use at least **44px** height (Apple HIG recommendation) for tappability.
  - Font sizes are tuned to avoid iOS zoom on input focus (minimum 16px for form controls).
  - reCAPTCHA widget is scaled down on small screens while remaining usable.

This ensures the Content Strategist experience is consistent and usable on desktop, tablets, and phones.

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
- **Duration:** 30 seconds - 2 minutes
- **UI:** Radio button selection, no default mode

```php
check_page_links(string $url, int $max_links = 100): array
```

#### Full Site Audit
- **Target:** Entire website
- **Max Pages:** 1,000 pages
- **Process:** Chunked (50 pages per chunk), auto-continue with progress bar
- **Use Case:** Comprehensive site scan
- **Duration:** 10-30 minutes (depending on site size)
- **UI:** Radio button selection, no default mode
- **Features:** Dynamic progress bar, estimated vs real progress, total elapsed time tracking

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
| **Meta Generation (Per Minute)** | 2/min | ∞ | Per user | Rolling window |
| **Keyword Density (URL)** | 20/day | ∞ | Per user | Midnight |
| **Keyword Density (URL Per Minute)** | 3/min | ∞ | Per user | Rolling window |
| **Keyword Density (Text)** | ∞ | ∞ | Client-side | N/A |
| **Global Gemini RPD** | 20/day | N/A | Sitewide | Midnight PST |
| **Global Gemini RPM** | 10/min | N/A | Sitewide | Rolling window |
| **Global Gemini TPM** | 250k/min | N/A | Sitewide | Rolling window |

---

## Data Flow

This section provides high-level data flow diagrams for all three tools. For detailed technical flows, see each tool's "Deep Dive" section above.

### Meta Generator - Complete Flow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. USER SUBMITS FORM                                        │
│    - Enters keyword, business name, description            │
│    - Selects page type                                      │
│    - Completes reCAPTCHA                                    │
│    - Clicks "Generate Meta Tags"                           │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. FRONTEND VALIDATION (meta-generator.js)                 │
│    ✓ Form fields filled?                                    │
│    ✓ reCAPTCHA completed?                                   │
│    ✓ Rate limit remaining?                                  │
│    → Send AJAX request to backend                           │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. AJAX HANDLER (class-meta-ajax.php)                      │
│    ✓ Verify nonce (CSRF protection)                         │
│    ✓ Verify reCAPTCHA (server-side)                         │
│    ✓ Check global Gemini RPD (20/day)                        │
│    ✓ Check global Gemini RPM (10/minute)                     │
│    ✓ Estimate tokens → Check global Gemini TPM (250k/min)   │
│    ✓ Check per-user rate limit (5/day, 2/minute)            │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. META GENERATOR SERVICE (class-meta-generator.php)       │
│    - Build AI prompt (page-type aware)                       │
│    - Call Gemini_API::generate_meta()                        │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. GEMINI API WRAPPER (class-gemini-api.php)               │
│    - Build prompt with context                               │
│    - Call API with retry logic (max 2 retries)               │
│    - Parse JSON response                                     │
│    - Extract title & description                              │
│    - Track tokens used                                        │
│    - Update circuit breaker state                             │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 6. RESPONSE PROCESSING                                      │
│    ✓ Validate character lengths                              │
│    ✓ Truncate if needed (with ellipsis)                      │
│    ✓ Return results + token usage                            │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 7. FRONTEND DISPLAY (meta-generator.js)                    │
│    - Display generated title & description                   │
│    - Show character counts with color indicators              │
│    - Enable copy buttons                                      │
│    - Update rate limit status                                 │
│    - Log usage (Logger)                                       │
└─────────────────────────────────────────────────────────────┘
```

### Keyword Density Checker - Complete Flow

**Paste Text Mode (100% Client-Side):**
```
User Pastes Text → Frontend Analysis → Display Results
(No server calls, unlimited usage)
```

**URL Mode (Hybrid):**
```
┌─────────────────────────────────────────────────────────────┐
│ 1. USER ENTERS URL                                          │
│    - Enters URL to analyze                                   │
│    - Optionally checks "Force fresh analysis"                │
│    - Completes reCAPTCHA                                     │
│    - Clicks "Analyze"                                        │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. FRONTEND VALIDATION (keyword-density.js)                │
│    ✓ URL format valid?                                       │
│    ✓ reCAPTCHA completed?                                    │
│    → AJAX: seo_fetch_url_content                             │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. AJAX HANDLER (class-meta-ajax.php)                      │
│    ✓ Verify nonce                                            │
│    ✓ Validate URL (SSRF safe)                               │
│    ✓ Verify reCAPTCHA                                        │
│    ✓ Check per-minute limit (3/min)                         │
│    ✓ Check concurrent limit (5 active)                      │
│    ✓ Check daily limit (20/day)                              │
│    ✓ Check cache (15 min) or fetch fresh                     │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. CONTENT EXTRACTION                                       │
│    - Fetch HTML via wp_remote_get()                          │
│    - Extract main content (strip HTML)                        │
│    - Normalize whitespace                                    │
│    - Cache result for 15 minutes                             │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. FRONTEND ANALYSIS (keyword-density.js)                 │
│    - Build 1-4 word n-grams                                   │
│    - Apply stop words + min length (≥ 3)                      │
│    - Calculate density & prominence                           │
│    - Analyze SEO elements (Title/H1/etc.)                     │
│    - Compute readability (Flesch Reading Ease)               │
│    - Compute relevancy score (0-100)                          │
│    - Apply Porter Stemmer (group variations)                 │
│    - Detect over-optimization                                 │
└────────────────┬────────────────────────────────────────────┘
                 ▼
┌─────────────────────────────────────────────────────────────┐
│ 6. DISPLAY RESULTS                                          │
│    - Show relevancy score breakdown                           │
│    - Display keyword tables (1-4 word phrases)               │
│    - Show prominence, SEO elements, readability               │
│    - Display stemming warnings (if any)                      │
│    - Show cache info (if cached)                             │
└─────────────────────────────────────────────────────────────┘
```

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
GEMINI_MODEL = 'gemini-2.5-flash-lite'  // Default model (upgraded from 2.0)
GEMINI_MAX_TOKENS = 500         // Response length limit
GEMINI_TEMPERATURE = 0.7        // Creativity (0=deterministic, 1=random)
GEMINI_RPD_LIMIT = 20           // Global requests per day (free tier)
GEMINI_RPM_LIMIT = 10           // Global requests per minute (free tier)
GEMINI_TPM_LIMIT = 250000       // Global tokens per minute (free tier)
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
POST https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent
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
- ✅ Meta Title & Description Generator (Gemini 2.5 Flash-Lite AI)
- ✅ Keyword Density Checker → **Content Strategist** (client-side analysis)
- ✅ Broken Link Checker (Quick Scan + Full Site Audit modes)
- ✅ reCAPTCHA v2 integration
- ✅ Multi-layer rate limiting (daily, per-minute, concurrent, global API)
- ✅ Auto-continue for large scans with progress bar
- ✅ Dynamic progress bar (estimated + real progress)
- ✅ URL duplicate prevention (3 scans/day per website)
- ✅ Concurrent scan limits (1 user, 3 global)
- ✅ Content Strategist features:
  - 4-word phrase analysis (long-tail keywords)
  - Porter Stemmer (group word variations)
  - Prominence scoring (first 100 words, H1, first paragraph)
  - SEO elements analysis (Title, Meta Description, H1, Alt text)
  - Readability score (Flesch Reading Ease)
  - Weighted relevancy score (0-100)
  - Over-optimization warnings
- ✅ URL mode caching (15 minutes) with force refresh option
- ✅ Global Gemini API limits tracking (RPD, RPM, TPM)
- ✅ Mobile-responsive UI for all tools
- ✅ CSS scoping to prevent theme conflicts (Elementor-compatible)

---

## Credits & License

**Developer:** Sameer Qadri  
**Website:** [saasmarketing.ca](https://saasmarketing.ca)  
**Live Demo:** [https://saasmarketing.ca/seo-tools/](https://saasmarketing.ca/seo-tools/)  
**License:** GPL v2 or later  
**Repository:** [GitHub](https://github.com/sameerqadri1/wordpress-seo-tools-plugin)

**Third-Party Services:**
- Google Gemini API (AI content generation)
- Google reCAPTCHA v2 (bot protection)

---

**End of Documentation**
