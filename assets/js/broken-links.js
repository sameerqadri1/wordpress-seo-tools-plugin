/**
 * SEO Marketing Tools - Broken Link Checker (Simplified)
 * 
 * @package SEO_Marketing_Tools
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    let currentResults = null;
    let currentUrl = null;
    let currentAjaxRequest = null;
    let timerInterval = null;
    let scanStartTime = null;
    
    const STATE = {
        IDLE: 'idle',
        SCANNING: 'scanning',
        PAUSED: 'paused',
        COMPLETE: 'complete',
        CANCELLED: 'cancelled'
    };
    
    let currentState = STATE.IDLE;
    
    /**
     * Initialize broken link checker
     */
    $(document).ready(function() {
        loadRateLimitStatus();
        initForm();
    });
    
    /**
     * Load and display rate limit status
     */
    function loadRateLimitStatus() {
        $.ajax({
            url: seoToolsConfig.ajax_url,
            type: 'POST',
            data: {
                action: 'seo_get_rate_limit_status',
                nonce: seoToolsConfig.nonces.meta,
                tool_name: 'link_checker'
            },
            success: function(response) {
                if (response.success) {
                    displayRateLimitStatus(response.data);
                }
            }
        });
    }
    
    /**
     * Display rate limit status
     */
    function displayRateLimitStatus(data) {
        const $status = $('#rate-limit-status .limit-text');
        
        if (data.unlimited) {
            $status.html('<strong>Unlimited</strong> (Administrator)');
            $('#rate-limit-status').css('background', '#d1fae5');
        } else {
            const remaining = data.remaining;
            const limit = data.limit;
            
            if (remaining > 0) {
                $status.html(`<strong>${remaining} of ${limit}</strong> checks remaining today · Resets in ${data.reset_time}`);
                
                if (remaining <= 2) {
                    $('#rate-limit-status').css('background', '#fef3c7');
                } else {
                    $('#rate-limit-status').css('background', '#dbeafe');
                }
            } else {
                $status.html(`⏰ Daily limit reached · Try again in ${data.reset_time}`);
                $('#rate-limit-status').css('background', '#fee2e2');
                $('#check-btn').prop('disabled', true);
            }
        }
    }
    
    /**
     * Initialize form submission
     */
    function initForm() {
        // Handle scan mode changes
        $('input[name="scan_mode"]').on('change', function() {
            const mode = $(this).val();
            if (mode === 'quick') {
                $('#url-help-text').text('Enter the URL of the page you want to check (checks all links on that single page).');
            } else {
                $('#url-help-text').text('Enter your website homepage URL (we\'ll crawl your entire site up to 1,000 pages).');
            }
        });

        $('#link-checker-form').on('submit', function(e) {
            e.preventDefault();
            
            // Get scan mode
            const scanMode = $('input[name="scan_mode"]:checked').val();
            
            if (!scanMode) {
                showError('Please select a scan mode (Quick Scan or Full Site Audit)');
                return;
            }
            
            // Get URL
            const url = $('#check-url').val().trim();
            
            if (!url) {
                showError('Please enter a URL to check');
                return;
            }
            
            if (!isValidUrl(url)) {
                showError('Please enter a valid URL (including http:// or https://)');
                return;
            }
            
            // Get reCAPTCHA response (only for new scans, not continuations)
            let recaptchaResponse = '';
            if (currentState !== STATE.PAUSED) {
                recaptchaResponse = getRecaptchaResponse();
                if (!recaptchaResponse && seoToolsConfig.recaptcha_site_key) {
                    showError('Please complete the reCAPTCHA verification');
                    return;
                }
            }
            
            // Check links
            checkLinks(url, recaptchaResponse, scanMode);
        });
        
        // Check another button
        $('#check-another').on('click', function() {
            resetToInitialState();
            $('#link-checker-form')[0].reset();
            resetRecaptcha();
        });
        
        // Export CSV
        $('#export-csv').on('click', function() {
            exportToCSV();
        });
        
        // Cancel scan button
        $('#cancel-scan-btn').on('click', function() {
            cancelScan();
        });
        
        // Continue scanning button
        $('#continue-scan-btn').on('click', function() {
            if (currentUrl) {
                continueScan(currentUrl);
            }
        });
    }
    
    /**
     * Check links (synchronous)
     */
    function checkLinks(url, recaptchaResponse, scanMode) {
        currentUrl = url;
        currentState = STATE.SCANNING;
        
        // Store scan mode for continuations
        if (!scanMode) {
            scanMode = $('input[name="scan_mode"]:checked').val() || 'full';
        }
        
        const $btn = $('#check-btn');
        $btn.prop('disabled', true);
        $btn.find('.btn-text').hide();
        $btn.find('.btn-loader').show();
        
        $('#link-results').hide();
        $('#error-message').hide();
        $('#loading-message').show();
        $('#loading-message .info-message').text('🔄 Scanning... Please wait (this may take 2-5 minutes)');
        $('#cancel-scan-btn').show().prop('disabled', false).text('Cancel Scan');
        $('#continue-prompt').hide();
        
        // Start timer
        scanStartTime = Date.now();
        startTimer();
        
        currentAjaxRequest = $.ajax({
            url: seoToolsConfig.ajax_url,
            type: 'POST',
            timeout: 600000, // 10 minutes
            data: {
                action: 'seo_check_links',
                nonce: seoToolsConfig.nonces.links,
                url: url,
                scan_mode: scanMode,
                'g-recaptcha-response': recaptchaResponse
            },
            success: function(response) {
                if (response.success) {
                    handleScanComplete(response.data);
                } else {
                    handleScanError(response.data.message || 'Scan failed');
                }
            },
            error: function(xhr, status) {
                if (status === 'abort') {
                    // User cancelled - expected
                    return;
                } else if (xhr.status === 504) {
                    handleScanError('Request timeout. Please try again.');
                } else {
                    handleScanError('Network error. Please try again.');
                }
            },
            complete: function() {
                currentAjaxRequest = null;
                resetRecaptcha();
            }
        });
    }
    
    /**
     * Continue scanning
     */
    function continueScan(url) {
        currentState = STATE.SCANNING;
        
        $('#continue-prompt').hide();
        $('#loading-message').show();
        $('#loading-message .info-message').text('🔄 Continuing scan... Please wait');
        $('#cancel-scan-btn').show().prop('disabled', false).text('Cancel Scan');
        
        // Resume timer
        startTimer();
        
        currentAjaxRequest = $.ajax({
            url: seoToolsConfig.ajax_url,
            type: 'POST',
            timeout: 600000, // 10 minutes
            data: {
                action: 'seo_check_links',
                nonce: seoToolsConfig.nonces.links,
                url: url,
                scan_mode: 'full' // Continuations are always full mode
                // No reCAPTCHA for continuations
            },
            success: function(response) {
                if (response.success) {
                    handleScanComplete(response.data);
                } else {
                    handleScanError(response.data.message || 'Scan failed');
                }
            },
            error: function(xhr, status) {
                if (status === 'abort') {
                    return;
                } else {
                    handleScanError('Network error. Please try again.');
                }
            },
            complete: function() {
                currentAjaxRequest = null;
            }
        });
    }
    
    /**
     * Handle scan complete
     */
    function handleScanComplete(data) {
        stopTimer();
        $('#loading-message').hide();
        $('#cancel-scan-btn').hide();
        
        // Update accumulated results
        if (currentResults) {
            // Merge with previous results (continuation)
            currentResults = {
                total_links_checked: data.total_links_checked,
                working_links: data.working_links,
                broken_links_count: data.broken_links_count,
                broken_links: data.broken_links,
                pages_crawled: data.pages_crawled,
                scan_time: data.scan_time
            };
        } else {
            // First chunk results
            currentResults = data;
        }
        
        // Display current results
        displayResults(currentResults);
        
        if (data.has_more) {
            // Show continue prompt
            currentState = STATE.PAUSED;
            const estimated = data.estimated_remaining || '?';
            $('#continue-prompt .info-message').html(
                `✓ Scanned <strong>${data.pages_crawled}</strong> pages. Continue scanning?<br>` +
                `<small style="color: #9ca3af;">(Estimated ${estimated} more pages remaining)</small>`
            );
            $('#continue-prompt').show();
            
            // Re-enable check button
            const $btn = $('#check-btn');
            $btn.prop('disabled', false);
            $btn.find('.btn-text').show();
            $btn.find('.btn-loader').hide();
        } else {
            // Scan complete
            currentState = STATE.COMPLETE;
            loadRateLimitStatus();
            
            // Re-enable check button
            const $btn = $('#check-btn');
            $btn.prop('disabled', false);
            $btn.find('.btn-text').show();
            $btn.find('.btn-loader').hide();
        }
    }
    
    /**
     * Handle scan error
     */
    function handleScanError(message) {
        stopTimer();
        $('#loading-message').hide();
        $('#cancel-scan-btn').hide();
        $('#continue-prompt').hide();
        showError(message);
        
        currentState = STATE.IDLE;
        
        const $btn = $('#check-btn');
        $btn.prop('disabled', false);
        $btn.find('.btn-text').show();
        $btn.find('.btn-loader').hide();
    }
    
    /**
     * Cancel scan
     */
    function cancelScan() {
        if (!currentUrl) return;
        
        // Abort current AJAX request
        if (currentAjaxRequest) {
            currentAjaxRequest.abort();
            currentAjaxRequest = null;
        }
        
        // Call backend to cleanup state
        $.ajax({
            url: seoToolsConfig.ajax_url,
            type: 'POST',
            data: {
                action: 'seo_cancel_scan',
                nonce: seoToolsConfig.nonces.links,
                url: currentUrl
            }
        });
        
        // Reset UI immediately (don't wait for response)
        resetToInitialState();
        showInfo('Scan cancelled.');
    }
    
    /**
     * Reset to initial state
     */
    function resetToInitialState() {
        stopTimer();
        
        $('#loading-message').hide();
        $('#cancel-scan-btn').hide();
        $('#continue-prompt').hide();
        $('#link-results').hide();
        
        currentResults = null;
        currentUrl = null;
        currentState = STATE.IDLE;
        scanStartTime = null;
        
        const $btn = $('#check-btn');
        $btn.prop('disabled', false);
        $btn.find('.btn-text').show();
        $btn.find('.btn-loader').hide();
        
        $('#check-url').prop('disabled', false);
    }
    
    /**
     * Start timer
     */
    function startTimer() {
        updateTimer();
        timerInterval = setInterval(updateTimer, 1000);
    }
    
    /**
     * Stop timer
     */
    function stopTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }
    
    /**
     * Update timer display
     */
    function updateTimer() {
        if (!scanStartTime) return;
        
        const elapsed = Math.floor((Date.now() - scanStartTime) / 1000);
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;
        
        let timeStr = '';
        if (minutes > 0) {
            timeStr = minutes + 'm ' + seconds + 's';
        } else {
            timeStr = seconds + 's';
        }
        
        $('#elapsed-time').text(timeStr);
    }
    
    /**
     * Display check results (show only broken links)
     */
    function displayResults(data) {
        // Update stats
        $('#total-links').text(data.total_links_checked || 0);
        $('#working-links').text(data.working_links || 0);
        $('#broken-links').text(data.broken_links_count || 0);
        $('#pages-crawled-stat').text(data.pages_crawled || 0);
        $('#scan-time').text(data.scan_time + 's');
        
        // Build table - ONLY show broken links
        const $tbody = $('#links-tbody');
        $tbody.empty();
        
        const brokenLinks = data.broken_links || [];
        
        if (brokenLinks.length === 0) {
            $tbody.append('<tr><td colspan="5" style="text-align:center; padding: 40px; color: #10b981;"><strong>✓ No broken links found! All links are working perfectly.</strong></td></tr>');
        } else {
            brokenLinks.forEach(function(link) {
                let statusBadge, statusClass;
                
                switch(link.status) {
                    case 'broken':
                        statusBadge = '✗ Broken';
                        statusClass = 'broken';
                        break;
                    case 'error':
                        statusBadge = '⚠ Error';
                        statusClass = 'warning';
                        break;
                    case 'redirect':
                        statusBadge = '↻ Redirect';
                        statusClass = 'warning';
                        break;
                    default:
                        statusBadge = '✗ Broken';
                        statusClass = 'broken';
                }
                
                const row = `
                    <tr>
                        <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis;">
                            <a href="${escapeHtml(link.url)}" target="_blank" rel="noopener" style="color: #2563eb;">
                                ${escapeHtml(truncateUrl(link.url, 45))}
                            </a>
                        </td>
                        <td>${escapeHtml(truncate(link.anchor_text, 30))}</td>
                        <td><span class="status-badge ${statusClass}">${statusBadge}</span></td>
                        <td>
                            <strong>${link.status_code || 'N/A'}</strong>
                            <br/>
                            <small style="color: #9ca3af;">${escapeHtml(link.status_text)}</small>
                            ${link.response_time ? `<br/><small style="color: #9ca3af;">${link.response_time}ms</small>` : ''}
                        </td>
                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                            <a href="${escapeHtml(link.found_on_page)}" target="_blank" rel="noopener" style="color: #6b7280; font-size: 0.875rem;">
                                ${escapeHtml(truncateUrl(link.found_on_page, 35))}
                            </a>
                        </td>
                    </tr>
                `;
                $tbody.append(row);
            });
        }
        
        // Show results
        $('#link-results').slideDown();
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $('#link-results').offset().top - 100
        }, 500);
    }
    
    /**
     * Export results to CSV (broken links only)
     */
    function exportToCSV() {
        if (!currentResults) return;
        
        let csv = 'URL,Anchor Text,Status,Status Code,Status Text,Response Time (ms),Found On Page\n';
        
        const brokenLinks = currentResults.broken_links || [];
        
        brokenLinks.forEach(function(link) {
            csv += `"${link.url}","${link.anchor_text}","${link.status}",${link.status_code || 'N/A'},"${link.status_text}",${link.response_time || 'N/A'},"${link.found_on_page}"\n`;
        });
        
        // Download
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `broken-links-report-${Date.now()}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        $('#error-message').html(`<strong>Error:</strong> ${escapeHtml(message)}`).slideDown();
    }
    
    /**
     * Show info message
     */
    function showInfo(message) {
        $('#error-message').html(`<strong>ℹ</strong> ${escapeHtml(message)}`).slideDown();
        setTimeout(function() {
            $('#error-message').slideUp();
        }, 3000);
    }
    
    /**
     * Validate URL
     */
    function isValidUrl(url) {
        try {
            new URL(url);
            return url.startsWith('http://') || url.startsWith('https://');
        } catch(e) {
            return false;
        }
    }
    
    /**
     * Get reCAPTCHA response
     */
    function getRecaptchaResponse() {
        if (typeof grecaptcha !== 'undefined' && seoToolsConfig.recaptcha_site_key) {
            return grecaptcha.getResponse();
        }
        return '';
    }
    
    /**
     * Reset reCAPTCHA
     */
    function resetRecaptcha() {
        if (typeof grecaptcha !== 'undefined' && seoToolsConfig.recaptcha_site_key) {
            grecaptcha.reset();
        }
    }
    
    /**
     * Truncate text
     */
    function truncate(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }
    
    /**
     * Truncate URL for display
     */
    function truncateUrl(url, maxLength) {
        if (url.length <= maxLength) return url;
        
        try {
            const urlObj = new URL(url);
            const domain = urlObj.hostname;
            const path = urlObj.pathname;
            
            if ((domain + path).length <= maxLength) {
                return domain + path;
            }
            
            return domain + '...' + path.substring(path.length - 20);
        } catch(e) {
            return truncate(url, maxLength);
        }
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
})(jQuery);
