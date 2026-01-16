/**
 * SEO Marketing Tools - Common JavaScript
 * Shared utilities and functions
 * 
 * @package SEO_Marketing_Tools
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    /**
     * Initialize common functionality
     */
    $(document).ready(function() {
        // Character counters
        initCharacterCounters();
        
        // Copy to clipboard buttons
        initCopyButtons();
        
        // Form validation
        initFormValidation();
    });
    
    /**
     * Initialize character counters
     */
    function initCharacterCounters() {
        $('input[maxlength], textarea[maxlength]').each(function() {
            const $input = $(this);
            const maxLength = $input.attr('maxlength');
            const inputId = $input.attr('id');
            const $counter = $(`[data-for="${inputId}"]`);
            
            if ($counter.length) {
                // Update counter on input
                $input.on('input', function() {
                    const currentLength = $(this).val().length;
                    $counter.text(`${currentLength}/${maxLength}`);
                    
                    // Change color based on usage
                    if (currentLength >= maxLength * 0.9) {
                        $counter.css('color', '#ef4444');
                    } else if (currentLength >= maxLength * 0.7) {
                        $counter.css('color', '#f59e0b');
                    } else {
                        $counter.css('color', '#6b7280');
                    }
                });
                
                // Trigger initial update
                $input.trigger('input');
            }
        });
    }
    
    /**
     * Initialize copy to clipboard functionality
     */
    function initCopyButtons() {
        $(document).on('click', '.btn-copy', function() {
            const $btn = $(this);
            const targetId = $btn.data('target');
            const $target = $('#' + targetId);
            
            if ($target.length) {
                const text = $target.text();
                
                // Copy to clipboard
                copyToClipboard(text);
                
                // Show feedback
                const originalText = $btn.text();
                $btn.text('✓ Copied!');
                $btn.css('background', '#10b981');
                
                setTimeout(function() {
                    $btn.text(originalText);
                    $btn.css('background', '');
                }, 2000);
            }
        });
    }
    
    /**
     * Copy text to clipboard
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text);
        } else {
            // Fallback for older browsers
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
        }
    }
    
    /**
     * Initialize form validation
     */
    function initFormValidation() {
        $('form').on('submit', function(e) {
            const $form = $(this);
            
            // Check required fields
            let isValid = true;
            $form.find('[required]').each(function() {
                const $field = $(this);
                if (!$field.val().trim()) {
                    isValid = false;
                    $field.addClass('error');
                    $field.one('input', function() {
                        $(this).removeClass('error');
                    });
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showError('Please fill in all required fields');
                return false;
            }
        });
    }
    
    /**
     * Show error message
     */
    window.showError = function(message) {
        const $errorDiv = $('#error-message');
        if ($errorDiv.length) {
            $errorDiv.html(message).slideDown();
            $('html, body').animate({
                scrollTop: $errorDiv.offset().top - 100
            }, 500);
            
            setTimeout(function() {
                $errorDiv.slideUp();
            }, 5000);
        } else {
            alert(message);
        }
    };
    
    /**
     * Show success message
     */
    window.showSuccess = function(message) {
        // Could implement a success notification
        console.log('Success:', message);
    };
    
    /**
     * Format number with commas
     */
    window.formatNumber = function(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    };
    
    /**
     * Validate URL format
     */
    window.isValidUrl = function(string) {
        try {
            const url = new URL(string);
            return url.protocol === "http:" || url.protocol === "https:";
        } catch (_) {
            return false;
        }
    };
    
    /**
     * Get reCAPTCHA response
     */
    window.getRecaptchaResponse = function() {
        if (typeof grecaptcha !== 'undefined') {
            const version = seoToolsConfig.recaptcha_version;
            if (version === 'v2') {
                return grecaptcha.getResponse();
            }
        }
        return '';
    };
    
    /**
     * Reset reCAPTCHA
     */
    window.resetRecaptcha = function() {
        if (typeof grecaptcha !== 'undefined') {
            const version = seoToolsConfig.recaptcha_version;
            if (version === 'v2') {
                grecaptcha.reset();
            }
        }
    };
    
})(jQuery);
