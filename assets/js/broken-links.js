/**
 * SEO Marketing Tools - Broken Link Checker
 * 
 * @package SEO_Marketing_Tools
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    let currentResults = null;
    let currentFilter = 'all';
    let currentJobId = null;
    let progressInterval = null;
    let timerInterval = null;
    let scanStartTime = null;
    
    /**
     * Initialize broken link checker
     */
    $(document).ready(function() {
        loadRateLimitStatus();
        initForm();
        initFilters();
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
        $('#link-checker-form').on('submit', function(e) {
            e.preventDefault();
            
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
            
            // Get reCAPTCHA response
            const recaptchaResponse = getRecaptchaResponse();
            if (!recaptchaResponse && seoToolsConfig.recaptcha_site_key) {
                showError('Please complete the reCAPTCHA verification');
                return;
            }
            
            // Check links
            checkLinks(url, recaptchaResponse);
        });
        
        // Check another button
        $('#check-another').on('click', function() {
            $('#link-results').slideUp();
            $('#link-checker-form')[0].reset();
            resetRecaptcha();
            scanStartTime = null;
        });
        
        // Export CSV
        $('#export-csv').on('click', function() {
            exportToCSV();
        });
        
        // Cancel scan button
        $('#cancel-scan-btn').on('click', function() {
            cancelScan();
        });
    }
    
    /**
     * Initialize filter tabs
     */
    function initFilters() {
        $('.filter-tab').on('click', function() {
            const filter = $(this).data('status');
            
            $('.filter-tab').removeClass('active');
            $(this).addClass('active');
            
            currentFilter = filter;
            displayResults(currentResults);
        });
    }
    
    /**
     * Check links on page (full website crawl)
     */
    function checkLinks(url, recaptchaResponse) {
        const $btn = $('#check-btn');
        $btn.prop('disabled', true);
        $btn.find('.btn-text').hide();
        $btn.find('.btn-loader').show();
        
        $('#link-results').hide();
        $('#error-message').hide();
        $('#progress-display').show();
        $('#cancel-scan-btn').show();
        
        // Start timer
        scanStartTime = Date.now();
        startTimer();
        
        $.ajax({
            url: seoToolsConfig.ajax_url,
            type: 'POST',
            data: {
                action: 'seo_check_links',
                nonce: seoToolsConfig.nonces.links,
                url: url,
                'g-recaptcha-response': recaptchaResponse
            },
            success: function(response) {
                if (response.success) {
                    stopTimer();
                    stopProgressPolling();
                    
                    currentResults = {
                        total_links_checked: response.data.total_links_checked,
                        working_links: response.data.working_links,
                        broken_links_count: response.data.broken_links_count,
                        broken_links: response.data.broken_links,
                        pages_crawled: response.data.pages_crawled,
                        scan_time: response.data.scan_time
                    };
                    
                    displayResults(currentResults);
                    loadRateLimitStatus(); // Refresh rate limit
                    
                    $('#progress-display').hide();
                    $('#cancel-scan-btn').hide();
                } else {
                    stopTimer();
                    stopProgressPolling();
                    showError(response.data.message || 'Failed to check links');
                    $('#progress-display').hide();
                    $('#cancel-scan-btn').hide();
                }
            },
            error: function(xhr) {
                stopTimer();
                stopProgressPolling();
                $('#progress-display').hide();
                $('#cancel-scan-btn').hide();
                
                if (xhr.status === 504) {
                    showError('Request timeout. The scan took too long. Please try a smaller website.');
                } else {
                    showError('Network error. Please try again.');
                }
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('.btn-text').show();
                $btn.find('.btn-loader').hide();
                resetRecaptcha();
            }
        });
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
     * Start progress polling
     */
    function startProgressPolling(jobId) {
        currentJobId = jobId;
        pollProgress();
        progressInterval = setInterval(pollProgress, 2000); // Poll every 2 seconds
    }
    
    /**
     * Stop progress polling
     */
    function stopProgressPolling() {
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
        currentJobId = null;
    }
    
    /**
     * Poll for scan progress
     */
    function pollProgress() {
        if (!currentJobId) return;
        
        $.ajax({
            url: seoToolsConfig.ajax_url,
            type: 'POST',
            data: {
                action: 'seo_get_scan_progress',
                nonce: seoToolsConfig.nonces.links,
                job_id: currentJobId
            },
            success: function(response) {
                if (response.success) {
                    updateProgressDisplay(response.data);
                }
            }
        });
    }
    
    /**
     * Update progress display
     */
    function updateProgressDisplay(progress) {
        $('#pages-crawled').text(progress.pages_crawled || 0);
        $('#links-checked').text(progress.links_checked || 0);
        $('#broken-found').text(progress.broken_links_found || 0);
    }
    
    /**
     * Cancel scan
     */
    function cancelScan() {
        if (!currentJobId) return;
        
        $('#cancel-scan-btn').prop('disabled', true).text('Cancelling...');
        
        $.ajax({
            url: seoToolsConfig.ajax_url,
            type: 'POST',
            data: {
                action: 'seo_cancel_scan',
                nonce: seoToolsConfig.nonces.links,
                job_id: currentJobId
            },
            success: function(response) {
                stopTimer();
                stopProgressPolling();
                $('#progress-display').hide();
                $('#cancel-scan-btn').hide();
                
                if (response.success) {
                    showError('Scan cancelled by user.');
                } else {
                    showError('Failed to cancel scan.');
                }
                
                // Re-enable button
                const $btn = $('#check-btn');
                $btn.prop('disabled', false);
                $btn.find('.btn-text').show();
                $btn.find('.btn-loader').hide();
                resetRecaptcha();
            }
        });
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
