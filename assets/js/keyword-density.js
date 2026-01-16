/**
 * SEO Marketing Tools - Keyword Density Checker
 * 
 * @package SEO_Marketing_Tools
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    let currentResults = null;
    let currentPhraseLength = 1;
    
    /**
     * Initialize keyword density checker
     */
    $(document).ready(function() {
        initModeTabs();
        initForms();
        initFilters();
    });
    
    /**
     * Initialize mode tabs (text vs URL)
     */
    function initModeTabs() {
        $('.mode-tab').on('click', function() {
            const mode = $(this).data('mode');
            
            // Update active tab
            $('.mode-tab').removeClass('active');
            $(this).addClass('active');
            
            // Show corresponding form
            if (mode === 'text') {
                $('#text-mode').show();
                $('#url-mode').hide();
            } else {
                $('#text-mode').hide();
                $('#url-mode').show();
            }
            
            // Hide results
            $('#keyword-results').hide();
            $('#error-message').hide();
        });
    }
    
    /**
     * Initialize forms
     */
    function initForms() {
        // Text mode form
        $('#keyword-text-form').on('submit', function(e) {
            e.preventDefault();
            
            const text = $('#content-text').val().trim();
            
            if (!text) {
                showError('Please enter some text to analyze');
                return;
            }
            
            analyzeText(text);
        });
        
        // Update word count in real-time
        $('#content-text').on('input', function() {
            const text = $(this).val();
            const wordCount = countWords(text);
            $('#word-count-text').text(`${formatNumber(wordCount)} words`);
        });
        
        // URL mode form
        $('#keyword-url-form').on('submit', function(e) {
            e.preventDefault();
            
            const url = $('#content-url').val().trim();
            
            if (!url) {
                showError('Please enter a URL');
                return;
            }
            
            if (!isValidUrl(url)) {
                showError('Please enter a valid URL');
                return;
            }
            
            fetchAndAnalyze(url);
        });
        
        // Analyze another button
        $('#analyze-another').on('click', function() {
            $('#keyword-results').slideUp();
            $('#content-text').val('');
            $('#content-url').val('');
            $('#word-count-text').text('0 words');
        });
        
        // Export CSV
        $('#export-csv').on('click', function() {
            exportToCSV();
        });
    }
    
    /**
     * Initialize phrase length filters
     */
    function initFilters() {
        $('.filter-tab').on('click', function() {
            const length = parseInt($(this).data('length'));
            
            $('.filter-tab').removeClass('active');
            $(this).addClass('active');
            
            currentPhraseLength = length;
            displayResults(currentResults);
        });
    }
    
    /**
     * Analyze text content (client-side)
     */
    function analyzeText(text) {
        const startTime = performance.now();
        
        // Show loading
        const $btn = $('#keyword-text-form button');
        $btn.prop('disabled', true);
        $btn.find('.btn-text').hide();
        $btn.find('.btn-loader').show();
        
        // Process in next tick to allow UI update
        setTimeout(function() {
            // Clean text
            const cleanedText = text
                .toLowerCase()
                .replace(/<[^>]*>/g, ' ') // Remove HTML tags
                .replace(/[^\w\s]/g, ' ') // Remove special characters
                .replace(/\s+/g, ' ') // Normalize whitespace
                .trim();
            
            // Count words
            const words = cleanedText.split(' ').filter(w => w.length > 0);
            const totalWords = words.length;
            
            if (totalWords < 10) {
                showError('Please enter at least 10 words for analysis');
                $btn.prop('disabled', false);
                $btn.find('.btn-text').show();
                $btn.find('.btn-loader').hide();
                return;
            }
            
            // Analyze keywords
            const results = {
                totalWords: totalWords,
                uniqueWords: 0,
                keywords: {
                    1: analyzeNGrams(words, 1, totalWords),
                    2: analyzeNGrams(words, 2, totalWords),
                    3: analyzeNGrams(words, 3, totalWords)
                },
                analysisTime: ((performance.now() - startTime) / 1000).toFixed(2)
            };
            
            results.uniqueWords = results.keywords[1].length;
            
            currentResults = results;
            displayResults(results);
            
            $btn.prop('disabled', false);
            $btn.find('.btn-text').show();
            $btn.find('.btn-loader').hide();
        }, 100);
    }
    
    /**
     * Analyze n-grams (phrases)
     */
    function analyzeNGrams(words, n, totalWords) {
        const ngrams = {};
        const stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'it', 'this', 'that', 'these', 'those'];
        
        for (let i = 0; i <= words.length - n; i++) {
            const phrase = [];
            let skipPhrase = false;
            
            for (let j = 0; j < n; j++) {
                const word = words[i + j];
                
                // Skip if word is too short (except for n=1)
                if (word.length < 2 && n === 1) {
                    skipPhrase = true;
                    break;
                }
                
                phrase.push(word);
            }
            
            if (skipPhrase) continue;
            
            const phraseStr = phrase.join(' ');
            
            // Skip if phrase is only stop words (for n > 1)
            if (n > 1) {
                const allStopWords = phrase.every(w => stopWords.includes(w));
                if (allStopWords) continue;
            }
            
            ngrams[phraseStr] = (ngrams[phraseStr] || 0) + 1;
        }
        
        // Convert to array and sort by count
        const sorted = Object.entries(ngrams)
            .map(([phrase, count]) => ({
                phrase,
                count,
                density: ((count / totalWords) * 100).toFixed(2)
            }))
            .sort((a, b) => b.count - a.count)
            .slice(0, 50); // Top 50
        
        return sorted;
    }
    
    /**
     * Fetch URL and analyze
     */
    function fetchAndAnalyze(url) {
        const $btn = $('#keyword-url-form button');
        $btn.prop('disabled', true);
        $btn.find('.btn-text').hide();
        $btn.find('.btn-loader').show();
        
        $('#keyword-results').hide();
        $('#error-message').hide();
        
        $.ajax({
            url: seoToolsConfig.ajax_url,
            type: 'POST',
            data: {
                action: 'seo_fetch_url_content',
                nonce: seoToolsConfig.nonces.keyword,
                url: url
            },
            success: function(response) {
                if (response.success) {
                    analyzeText(response.data.text);
                } else {
                    showError(response.data.message || 'Failed to fetch URL content');
                }
            },
            error: function() {
                showError('Network error. Please try again.');
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('.btn-text').show();
                $btn.find('.btn-loader').hide();
            }
        });
    }
    
    /**
     * Display analysis results
     */
    function displayResults(results) {
        // Update stats
        $('#total-words').text(formatNumber(results.totalWords));
        $('#unique-words').text(formatNumber(results.uniqueWords));
        $('#analysis-time').text(results.analysisTime + 's');
        
        // Get keywords for current phrase length
        const keywords = results.keywords[currentPhraseLength];
        
        // Build table
        const $tbody = $('#keywords-tbody');
        $tbody.empty();
        
        if (!keywords || keywords.length === 0) {
            $tbody.append('<tr><td colspan="4" style="text-align:center;">No keywords found</td></tr>');
        } else {
            keywords.slice(0, 20).forEach(function(kw) {
                const density = parseFloat(kw.density);
                let status, statusClass;
                
                if (density <= 2) {
                    status = '✓ Optimal';
                    statusClass = 'working';
                } else if (density <= 3) {
                    status = '⚠ Warning';
                    statusClass = 'warning';
                } else {
                    status = '✗ Too High';
                    statusClass = 'broken';
                }
                
                const row = `
                    <tr>
                        <td>${escapeHtml(kw.phrase)}</td>
                        <td>${kw.count}</td>
                        <td>${kw.density}%</td>
                        <td><span class="status-badge ${statusClass}">${status}</span></td>
                    </tr>
                `;
                $tbody.append(row);
            });
        }
        
        // Show results
        $('#keyword-results').slideDown();
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $('#keyword-results').offset().top - 100
        }, 500);
    }
    
    /**
     * Export results to CSV
     */
    function exportToCSV() {
        if (!currentResults) return;
        
        const keywords = currentResults.keywords[currentPhraseLength];
        
        let csv = 'Keyword/Phrase,Count,Density %\n';
        keywords.forEach(function(kw) {
            csv += `"${kw.phrase}",${kw.count},${kw.density}\n`;
        });
        
        // Download
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `keyword-density-${currentPhraseLength}word-${Date.now()}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }
    
    /**
     * Count words in text
     */
    function countWords(text) {
        const cleaned = text.trim().replace(/\s+/g, ' ');
        if (!cleaned) return 0;
        return cleaned.split(' ').length;
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
