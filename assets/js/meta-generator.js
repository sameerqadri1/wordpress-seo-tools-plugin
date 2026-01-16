/**
 * SEO Marketing Tools - Meta Generator
 * 
 * @package SEO_Marketing_Tools
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    let currentLimitStatus = null;
    
    /**
     * Initialize meta generator
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
                tool_name: 'meta_generator'
            },
            success: function(response) {
                if (response.success) {
                    currentLimitStatus = response.data;
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
                $status.html(`<strong>${remaining} of ${limit}</strong> generations remaining today · Resets in ${data.reset_time}`);
                
                if (remaining <= 2) {
                    $('#rate-limit-status').css('background', '#fef3c7');
                } else {
                    $('#rate-limit-status').css('background', '#dbeafe');
                }
            } else {
                $status.html(`⏰ Daily limit reached · Try again in ${data.reset_time}`);
                $('#rate-limit-status').css('background', '#fee2e2');
                $('#generate-btn').prop('disabled', true);
            }
        }
    }
    
    /**
     * Initialize form submission
     */
    function initForm() {
        $('#meta-generator-form').on('submit', function(e) {
            e.preventDefault();
            
            // Check if limit reached
            if (currentLimitStatus && !currentLimitStatus.unlimited && currentLimitStatus.remaining <= 0) {
                showError('Daily limit reached. Please try again tomorrow.');
                return;
            }
            
            // Get form data
            const formData = {
                action: 'seo_generate_meta',
                nonce: seoToolsConfig.nonces.meta,
                keyword: $('#keyword').val().trim(),
                business_name: $('#business_name').val().trim(),
                description: $('#description').val().trim(),
                page_type: $('#page_type').val()
            };
            
            // Get reCAPTCHA response
            const recaptchaResponse = getRecaptchaResponse();
            if (!recaptchaResponse && seoToolsConfig.recaptcha_site_key) {
                showError('Please complete the reCAPTCHA verification');
                return;
            }
            formData['g-recaptcha-response'] = recaptchaResponse;
            
            // Disable form
            const $btn = $('#generate-btn');
            $btn.prop('disabled', true);
            $btn.find('.btn-text').hide();
            $btn.find('.btn-loader').show();
            
            // Hide previous results/errors
            $('#meta-results').hide();
            $('#error-message').hide();
            
            // Make AJAX request
            $.ajax({
                url: seoToolsConfig.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        displayResults(response.data);
                        
                        // Update rate limit
                        if (response.data.remaining !== undefined) {
                            currentLimitStatus.remaining = response.data.remaining;
                            displayRateLimitStatus(currentLimitStatus);
                        }
                    } else {
                        showError(response.data.message || 'Failed to generate meta tags');
                    }
                },
                error: function() {
                    showError('Network error. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $btn.find('.btn-text').show();
                    $btn.find('.btn-loader').hide();
                    resetRecaptcha();
                }
            });
        });
        
        // Generate another button
        $('#generate-another').on('click', function() {
            $('#meta-results').slideUp();
            $('#meta-generator-form')[0].reset();
            $('.char-count').each(function() {
                $(this).text('0/' + $(this).text().split('/')[1]);
            });
            $('html, body').animate({
                scrollTop: $('#meta-generator-form').offset().top - 100
            }, 500);
        });
    }
    
    /**
     * Display generated results
     */
    function displayResults(data) {
        // Set title
        $('#generated-title').text(data.title);
        const titleLength = data.title_length;
        const $titleIndicator = $('#title-length');
        
        if (titleLength >= 50 && titleLength <= 60) {
            $titleIndicator.text(`${titleLength} chars ✓`).removeClass('warning').addClass('good');
        } else {
            $titleIndicator.text(`${titleLength} chars ⚠`).removeClass('good').addClass('warning');
        }
        
        // Set description
        $('#generated-description').text(data.description);
        const descLength = data.description_length;
        const $descIndicator = $('#description-length');
        
        if (descLength >= 150 && descLength <= 160) {
            $descIndicator.text(`${descLength} chars ✓`).removeClass('warning').addClass('good');
        } else {
            $descIndicator.text(`${descLength} chars ⚠`).removeClass('good').addClass('warning');
        }
        
        // Show results
        $('#meta-results').slideDown();
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $('#meta-results').offset().top - 100
        }, 500);
        
        // Show cached indicator if cached
        if (data.cached) {
            $('#meta-results h2').append(' <span style="font-size:0.875rem;color:#6b7280;">(Cached)</span>');
        }
    }
    
})(jQuery);
