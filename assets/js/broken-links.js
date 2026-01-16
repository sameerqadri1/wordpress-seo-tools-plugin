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
        });
        
        // Export CSV
        $('#export-csv').on('click', function() {
            exportToCSV();
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
     * Check links on page
     */
    function checkLinks(url, recaptchaResponse) {
        const $btn = $('#check-btn');
        $btn.prop('disabled', true);
        $btn.find('.btn-text').hide();
        $btn.find('.btn-loader').show();
        
        $('#link-results').hide();
        $('#error-message').hide();
        
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
                    currentResults = response.data;
                    displayResults(response.data);
                    loadRateLimitStatus(); // Refresh rate limit
                } else {
                    showError(response.data.message || 'Failed to check links');
                }
            },
            error: function(xhr) {
                if (xhr.status === 504) {
                    showError('Request timeout. The page may have too many links or be slow to respond.');
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
     * Display check results
     */
    function displayResults(data) {
        // Update stats
        $('#total-links').text(data.total_links);
        $('#working-links').text(data.working_links);
        $('#broken-links').text(data.broken_links);
        $('#scan-time').text(data.scan_time + 's');
        
        // Filter links
        let filteredLinks = data.links;
        if (currentFilter === 'broken') {
            filteredLinks = data.links.filter(link => link.status === 'broken' || link.status === 'error');
        } else if (currentFilter === 'working') {
            filteredLinks = data.links.filter(link => link.status === 'working');
        }
        
        // Build table
        const $tbody = $('#links-tbody');
        $tbody.empty();
        
        if (filteredLinks.length === 0) {
            $tbody.append('<tr><td colspan="4" style="text-align:center;">No links found for this filter</td></tr>');
        } else {
            filteredLinks.forEach(function(link) {
                let statusBadge, statusClass;
                
                switch(link.status) {
                    case 'working':
                        statusBadge = '✓ Working';
                        statusClass = 'working';
                        break;
                    case 'broken':
                        statusBadge = '✗ Broken';
                        statusClass = 'broken';
                        break;
                    case 'redirect':
                        statusBadge = '↻ Redirect';
                        statusClass = 'warning';
                        break;
                    case 'error':
                        statusBadge = '⚠ Error';
                        statusClass = 'warning';
                        break;
                    default:
                        statusBadge = '? Unknown';
                        statusClass = '';
                }
                
                const row = `
                    <tr>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                            <a href="${escapeHtml(link.url)}" target="_blank" rel="noopener" style="color: #2563eb;">
                                ${escapeHtml(truncateUrl(link.url, 50))}
                            </a>
                        </td>
                        <td>${escapeHtml(truncate(link.anchor_text, 40))}</td>
                        <td><span class="status-badge ${statusClass}">${statusBadge}</span></td>
                        <td>
                            <strong>${link.status_code || 'N/A'}</strong>
                            <br/>
                            <small style="color: #6b7280;">${escapeHtml(link.status_text)}</small>
                            ${link.response_time ? `<br/><small style="color: #6b7280;">${link.response_time}ms</small>` : ''}
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
     * Export results to CSV
     */
    function exportToCSV() {
        if (!currentResults) return;
        
        let csv = 'URL,Anchor Text,Status,Status Code,Status Text,Response Time (ms)\n';
        
        currentResults.links.forEach(function(link) {
            csv += `"${link.url}","${link.anchor_text}","${link.status}",${link.status_code || 'N/A'},"${link.status_text}",${link.response_time || 'N/A'}\n`;
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
