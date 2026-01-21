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
                // Reset reCAPTCHA when switching to URL mode
                resetRecaptcha();
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
        $('.filter-tab').on('click', function(e) {
            e.preventDefault(); // Prevent any default behavior
            
            const length = parseInt($(this).data('length'));
            
            $('.filter-tab').removeClass('active');
            $(this).addClass('active');
            
            currentPhraseLength = length;
            
            // Hide stemmed toggle for 4-word phrases (not computed)
            if (length === 4) {
                $('#stemmed-toggle-wrapper').hide();
                $('#view-stemmed').prop('checked', false);
            } else {
                $('#stemmed-toggle-wrapper').show();
            }
            
            // Don't scroll when switching filters
            displayResults(currentResults, false);
        });
        
        // Stemmed view toggle
        $('#view-stemmed').on('change', function() {
            if (currentResults) {
                // Don't scroll when toggling stemmed view
                displayResults(currentResults, false);
            }
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
            // Clean text for keyword analysis (remove special chars)
            const cleanedText = text
                .toLowerCase()
                .replace(/<[^>]*>/g, ' ') // Remove HTML tags
                .replace(/[^\w\s]/g, ' ') // Remove special characters
                .replace(/\s+/g, ' ') // Normalize whitespace
                .trim();
            
            // Prepare text for readability (preserve sentence endings)
            const readabilityText = text
                .replace(/<[^>]*>/g, ' ') // Remove HTML tags
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
            
            // Analyze keywords (1-4 word phrases)
            const results = {
                totalWords: totalWords,
                uniqueWords: 0,
                keywords: {
                    1: analyzeNGrams(words, 1, totalWords),
                    2: analyzeNGrams(words, 2, totalWords),
                    3: analyzeNGrams(words, 3, totalWords),
                    4: analyzeNGrams(words, 4, totalWords)  // Long-tail keywords
                },
                stemmedKeywords: {
                    1: groupByStem(analyzeNGrams(words, 1, totalWords)),
                    2: groupByStem(analyzeNGrams(words, 2, totalWords)),
                    3: groupByStem(analyzeNGrams(words, 3, totalWords))
                },
                prominence: calculateProminence(cleanedText, words),
                seoElements: analyzeSEOElements(text),
                readability: calculateReadability(readabilityText), // Use text with punctuation
                relevancyScore: 0,  // Calculated after all metrics
                analysisTime: ((performance.now() - startTime) / 1000).toFixed(2)
            };
            
            results.uniqueWords = results.keywords[1].length;
            
            // Calculate relevancy score
            results.relevancyScore = calculateRelevancyScore(results);
            
            // Check for stemming warnings (over-optimization via variants)
            results.stemmingWarnings = checkStemmingWarnings(results.stemmedKeywords);
            
            currentResults = results;
            displayResults(results);
            
            $btn.prop('disabled', false);
            $btn.find('.btn-text').show();
            $btn.find('.btn-loader').hide();
        }, 100);
    }
    
    /**
     * Calculate keyword prominence (first 100 words, headings, distribution)
     */
    function calculateProminence(cleanedText, words) {
        const first100Words = words.slice(0, 100).join(' ');
        const firstParagraph = cleanedText.split('.')[0] || '';
        
        // Get top keyword (most frequent 2-3 word phrase)
        const topKeyword = getTopKeyword(words);
        
        const prominence = {
            topKeyword: topKeyword,
            inFirst100: first100Words.includes(topKeyword),
            inFirstParagraph: firstParagraph.includes(topKeyword),
            keywordPosition: first100Words.indexOf(topKeyword),
            distribution: calculateDistribution(cleanedText, topKeyword),
            score: 0
        };
        
        // Calculate prominence score (0-40)
        if (prominence.inFirst100) prominence.score += 20;
        if (prominence.inFirstParagraph) prominence.score += 10;
        if (prominence.distribution.isEven) prominence.score += 10;
        
        return prominence;
    }
    
    /**
     * Get top keyword (most frequent 2-word phrase)
     */
    function getTopKeyword(words) {
        const ngrams = {};
        
        // Build 2-word phrases
        for (let i = 0; i <= words.length - 2; i++) {
            if (words[i].length >= 3 && words[i+1].length >= 3) {
                const phrase = words[i] + ' ' + words[i+1];
                ngrams[phrase] = (ngrams[phrase] || 0) + 1;
            }
        }
        
        // Get top phrase
        let topPhrase = '';
        let maxCount = 0;
        
        for (const [phrase, count] of Object.entries(ngrams)) {
            if (count > maxCount) {
                maxCount = count;
                topPhrase = phrase;
            }
        }
        
        return topPhrase || words[0] || '';
    }
    
    /**
     * Calculate keyword distribution (beginning/middle/end)
     */
    function calculateDistribution(text, keyword) {
        const length = text.length;
        const beginning = text.substring(0, length * 0.25);
        const middle = text.substring(length * 0.25, length * 0.75);
        const end = text.substring(length * 0.75);
        
        const inBeginning = beginning.includes(keyword);
        const inMiddle = middle.includes(keyword);
        const inEnd = end.includes(keyword);
        const isEven = inBeginning && inMiddle && inEnd;
        
        return {
            inBeginning,
            inMiddle,
            inEnd,
            isEven
        };
    }
    
    /**
     * Analyze SEO elements (Title, Meta, H1, Alt)
     */
    function analyzeSEOElements(text) {
        // Extract title tag
        const titleMatch = text.match(/<title[^>]*>(.*?)<\/title>/i);
        const title = titleMatch ? titleMatch[1] : '';
        
        // Extract meta description
        const metaMatch = text.match(/<meta[^>]*name=["']description["'][^>]*content=["']([^"']*)["']/i);
        const meta = metaMatch ? metaMatch[1] : '';
        
        // Extract H1
        const h1Match = text.match(/<h1[^>]*>(.*?)<\/h1>/gi);
        const h1Text = h1Match ? h1Match[0].replace(/<[^>]*>/g, '') : '';
        const h1Count = h1Match ? h1Match.length : 0;
        
        // Extract images with alt text
        const imgMatches = text.match(/<img[^>]*>/gi) || [];
        const totalImages = imgMatches.length;
        const imagesWithAlt = imgMatches.filter(img => /alt=/i.test(img)).length;
        
        const elements = {
            title: {
                exists: title.length > 0,
                text: title,
                length: title.length,
                lengthStatus: title.length >= 50 && title.length <= 60 ? 'optimal' : 
                             title.length < 50 ? 'too-short' : 'too-long'
            },
            meta: {
                exists: meta.length > 0,
                text: meta,
                length: meta.length,
                lengthStatus: meta.length >= 150 && meta.length <= 160 ? 'optimal' : 
                             meta.length < 150 ? 'too-short' : 'too-long'
            },
            h1: {
                exists: h1Text.length > 0,
                text: h1Text,
                count: h1Count,
                countStatus: h1Count === 1 ? 'optimal' : h1Count === 0 ? 'missing' : 'multiple'
            },
            alt: {
                totalImages: totalImages,
                imagesWithAlt: imagesWithAlt,
                coverage: totalImages > 0 ? Math.round((imagesWithAlt / totalImages) * 100) : 0
            },
            score: 0
        };
        
        // Calculate SEO elements score (0-30)
        // Note: Keyword presence will be checked in displayResults with user's top keyword
        if (elements.title.exists && elements.title.lengthStatus === 'optimal') elements.score += 10;
        if (elements.h1.exists && elements.h1.countStatus === 'optimal') elements.score += 10;
        if (elements.meta.exists && elements.meta.lengthStatus === 'optimal') elements.score += 5;
        if (elements.alt.coverage >= 80) elements.score += 5;
        
        return elements;
    }
    
    /**
     * Calculate readability score (Flesch Reading Ease)
     */
    function calculateReadability(text) {
        // Split by sentence endings, but also handle cases with no punctuation
        let sentences = text.split(/[.!?]+/).filter(s => s.trim().length > 0);
        const words = text.split(/\s+/).filter(w => w.length > 0);
        const totalWords = words.length;
        
        // If no sentence endings found, treat entire text as one sentence
        // But try to detect sentences by looking for capital letters after periods/spaces
        if (sentences.length === 0 || (sentences.length === 1 && totalWords > 50)) {
            // Try alternative: split by double newlines or periods followed by space and capital
            sentences = text.split(/(?:\.\s+[A-Z]|\n\n+)/).filter(s => s.trim().length > 0);
            // If still no sentences, estimate: assume ~20 words per sentence
            if (sentences.length === 0) {
                sentences = [text]; // Fallback: entire text as one sentence
            }
        }
        
        const totalSentences = Math.max(1, sentences.length); // At least 1 sentence
        
        // Count syllables (approximation)
        let totalSyllables = 0;
        words.forEach(word => {
            // Clean word for syllable counting (remove punctuation)
            const cleanWord = word.replace(/[^\w]/g, '');
            if (cleanWord.length > 0) {
                totalSyllables += countSyllables(cleanWord);
            }
        });
        
        // Calculate averages
        const avgSentenceLength = totalWords / totalSentences;
        const avgSyllablesPerWord = totalWords > 0 ? totalSyllables / totalWords : 0;
        
        // Flesch Reading Ease formula
        const fleschScore = 206.835 - (1.015 * avgSentenceLength) - (84.6 * avgSyllablesPerWord);
        const clampedScore = Math.max(0, Math.min(100, fleschScore));
        
        // Determine grade level and status
        let gradeLevel = '';
        let status = '';
        
        if (clampedScore >= 90) {
            gradeLevel = '5th grade';
            status = 'very-easy';
        } else if (clampedScore >= 80) {
            gradeLevel = '6th grade';
            status = 'easy';
        } else if (clampedScore >= 70) {
            gradeLevel = '7th grade';
            status = 'fairly-easy';
        } else if (clampedScore >= 60) {
            gradeLevel = '8th-9th grade';
            status = 'standard';
        } else if (clampedScore >= 50) {
            gradeLevel = '10th-12th grade';
            status = 'fairly-difficult';
        } else if (clampedScore >= 30) {
            gradeLevel = 'College';
            status = 'difficult';
        } else {
            gradeLevel = 'College Graduate';
            status = 'very-difficult';
        }
        
        return {
            fleschScore: Math.round(clampedScore),
            gradeLevel: gradeLevel,
            status: status,
            avgSentenceLength: Math.round(avgSentenceLength * 10) / 10,
            avgSyllablesPerWord: Math.round(avgSyllablesPerWord * 10) / 10,
            totalSentences: totalSentences,
            totalWords: totalWords,
            score: Math.min(Math.round(clampedScore / 10), 10)  // 0-10 points
        };
    }
    
    /**
     * Count syllables in a word (approximation)
     */
    function countSyllables(word) {
        word = word.toLowerCase();
        if (word.length <= 3) return 1;
        
        word = word.replace(/(?:[^laeiouy]es|ed|[^laeiouy]e)$/, '');
        word = word.replace(/^y/, '');
        const syllables = word.match(/[aeiouy]{1,2}/g);
        
        return syllables ? syllables.length : 1;
    }
    
    /**
     * Porter Stemmer - Reduce words to their root form
     * (Simplified implementation for common English word patterns)
     */
    function stem(word) {
        word = word.toLowerCase();
        
        // Step 1: Remove common suffixes
        // Plurals and possessives
        word = word.replace(/^(.+?)('s|'s)$/,'$1');  // Remove possessive
        
        // Handle -ies, -es, -s
        if (word.match(/.*[^aeiou]ies$/)) {
            word = word.replace(/ies$/, 'y');  // companies -> company
        } else if (word.match(/.*(ss|zz)es$/)) {
            word = word.replace(/es$/, '');  // dresses -> dress
        } else if (word.match(/.*[^s]s$/)) {
            word = word.replace(/s$/, '');  // cats -> cat (but not ss)
        }
        
        // -ing, -ed endings
        if (word.match(/.*ing$/)) {
            if (word.match(/.{4,}ing$/)) {  // At least 4 chars before -ing
                word = word.replace(/ing$/, '');  // running -> runn
                // Handle doubled consonants
                if (word.match(/(.)\1$/)) {
                    word = word.slice(0, -1);  // runn -> run
                }
            }
        }
        
        if (word.match(/.*ed$/)) {
            if (word.match(/.{3,}ed$/)) {  // At least 3 chars before -ed
                word = word.replace(/ed$/, '');  // worked -> work
                // Handle doubled consonants
                if (word.match(/(.)\1$/)) {
                    word = word.slice(0, -1);
                }
            }
        }
        
        // -er, -est (comparatives)
        word = word.replace(/er$/, '');  // faster -> fast
        word = word.replace(/est$/, '');  // fastest -> fast
        
        // -ly (adverbs)
        word = word.replace(/ly$/, '');  // quickly -> quick
        
        // -tion, -sion, -ation
        word = word.replace(/ation$/, 'ate');  // optimization -> optim
        word = word.replace(/tion$/, '');  // creation -> creat
        word = word.replace(/sion$/, '');  // decision -> deci
        
        // -ness, -ment, -ful, -less
        word = word.replace(/ness$/, '');  // happiness -> happi
        word = word.replace(/ment$/, '');  // development -> develop
        word = word.replace(/ful$/, '');  // beautiful -> beauti
        word = word.replace(/less$/, '');  // helpless -> help
        
        return word;
    }
    
    /**
     * Group keywords by stem (root form)
     * Shows both individual variants and stemmed totals
     */
    function groupByStem(keywords) {
        const stemGroups = {};
        const stemToVariants = {};
        
        keywords.forEach(kw => {
            const words = kw.phrase.split(' ');
            const stemmedPhrase = words.map(w => stem(w)).join(' ');
            
            if (!stemGroups[stemmedPhrase]) {
                stemGroups[stemmedPhrase] = {
                    stemmedPhrase: stemmedPhrase,
                    originalPhrase: kw.phrase,
                    count: 0,
                    density: 0,
                    variants: []
                };
                stemToVariants[stemmedPhrase] = [];
            }
            
            stemGroups[stemmedPhrase].count += kw.count;
            stemGroups[stemmedPhrase].density = (parseFloat(stemGroups[stemmedPhrase].density) + parseFloat(kw.density)).toFixed(2);
            stemGroups[stemmedPhrase].variants.push({
                phrase: kw.phrase,
                count: kw.count,
                density: kw.density
            });
            
            stemToVariants[stemmedPhrase].push(kw.phrase);
        });
        
        // Convert to array and sort by count
        return Object.values(stemGroups)
            .sort((a, b) => b.count - a.count);
    }
    
    /**
     * Check for stemming warnings (variants causing over-optimization)
     */
    function checkStemmingWarnings(stemmedKeywords) {
        const warnings = [];
        
        // Check 1-word stems for over-optimization
        stemmedKeywords[1].forEach(stem => {
            const density = parseFloat(stem.density);
            const variantCount = stem.variants.length;
            
            // Warning: Multiple variants with combined high density
            if (variantCount >= 3 && density > 2.5) {
                warnings.push({
                    type: 'over-optimization',
                    stemmed: stem.stemmedPhrase,
                    variants: stem.variants.map(v => v.phrase).join(', '),
                    totalDensity: density,
                    variantCount: variantCount,
                    message: `"${stem.originalPhrase}" and its variants (${stem.variants.map(v => v.phrase).join(', ')}) appear ${stem.count} times (${density}% density). Google may see this as over-optimization.`
                });
            }
        });
        
        return warnings;
    }
    
    /**
     * Calculate overall relevancy score (0-100)
     */
    function calculateRelevancyScore(results) {
        const prominenceScore = results.prominence.score;  // 0-40
        const seoScore = results.seoElements.score;         // 0-30
        const readabilityScore = results.readability.score; // 0-10
        
        // Density score (0-20) - based on top keyword density
        const topKeywordData = results.keywords[2][0] || results.keywords[1][0];
        const density = topKeywordData ? parseFloat(topKeywordData.density) : 0;
        
        let densityScore = 0;
        if (density >= 0.5 && density <= 2.0) {
            densityScore = 20;  // Optimal
        } else if (density < 0.5) {
            densityScore = 10;  // Too low
        } else if (density <= 3.0) {
            densityScore = 15;  // Warning range
        } else {
            densityScore = 0;   // Too high (keyword stuffing)
        }
        
        const total = prominenceScore + seoScore + densityScore + readabilityScore;
        
        return {
            total: total,
            breakdown: {
                prominence: prominenceScore,
                seoElements: seoScore,
                density: densityScore,
                readability: readabilityScore
            }
        };
    }
    
    /**
     * Analyze n-grams (phrases)
     */
    function analyzeNGrams(words, n, totalWords) {
        const ngrams = {};
        
        // Expanded stop words list (100+ words) - Modern SEO entities
        const stopWords = [
            // Articles
            "the", "a", "an",
            // Pronouns
            "i", "you", "he", "she", "it", "we", "they", "me", "him", "her", "us", "them",
            "my", "your", "his", "her", "its", "our", "their", "mine", "yours", "hers", "ours", "theirs",
            "myself", "yourself", "himself", "herself", "itself", "ourselves", "yourselves", "themselves",
            // Common verbs
            "is", "am", "are", "was", "were", "be", "been", "being",
            "have", "has", "had", "having",
            "do", "does", "did", "doing", "done",
            "will", "would", "could", "should", "may", "might", "must", "can", "cannot",
            "get", "got", "getting", "go", "went", "going", "gone",
            "come", "came", "coming",
            "see", "saw", "seen", "seeing",
            "know", "knew", "known", "knowing",
            "think", "thought", "thinking",
            "take", "took", "taken", "taking",
            "give", "gave", "given", "giving",
            "make", "made", "making",
            "say", "said", "saying",
            // Question words
            "what", "how", "where", "when", "why", "which", "who", "whom", "whose",
            // Demonstratives
            "this", "that", "these", "those",
            // Conjunctions
            "and", "or", "but", "if", "then", "than", "so", "because", "since", "while", "although", "though",
            // Prepositions
            "in", "on", "at", "to", "from", "by", "for", "with", "of", "about", "into", "onto", "upon",
            "over", "under", "above", "below", "between", "among", "through", "during", "before", "after",
            // Common adjectives/adverbs (clutter words)
            "very", "really", "quite", "just", "only", "also", "too", "even", "still", "already", "yet",
            "actually", "basically", "literally", "probably", "maybe", "perhaps", "certainly", "definitely",
            "good", "bad", "new", "old", "big", "small", "long", "short", "high", "low", "great", "little",
            // Numbers (common)
            "one", "two", "three", "first", "second", "third",
            // Common filler words
            "well", "now", "here", "there", "some", "any", "all", "both", "each", "every", "other", "another", "such", "same"
        ];
        
        // Filter words: remove stop words and words < 3 characters
        const filteredWords = words.filter(word => {
            return word.length >= 3 && !stopWords.includes(word);
        });
        
        // Calculate total valid words for density calculation
        const validWordCount = filteredWords.length;
        
        if (validWordCount === 0) {
            return [];
        }
        
        // Build n-grams from filtered words
        for (let i = 0; i <= filteredWords.length - n; i++) {
            const phrase = [];
            
            for (let j = 0; j < n; j++) {
                phrase.push(filteredWords[i + j]);
            }
            
            const phraseStr = phrase.join(' ');
            ngrams[phraseStr] = (ngrams[phraseStr] || 0) + 1;
        }
        
        // Convert to array and sort by count
        // Density is calculated based on filtered words, not original total
        const sorted = Object.entries(ngrams)
            .map(([phrase, count]) => ({
                phrase,
                count,
                density: ((count / validWordCount) * 100).toFixed(2)
            }))
            .sort((a, b) => b.count - a.count)
            .slice(0, 50); // Top 50
        
        return sorted;
    }
    
    /**
     * Fetch URL and analyze
     */
    function fetchAndAnalyze(url) {
        // Get reCAPTCHA response
        const recaptchaResponse = getRecaptchaResponse();
        if (!recaptchaResponse && seoToolsConfig.recaptcha_site_key) {
            showError('Please complete the reCAPTCHA verification');
            return;
        }
        
        // Check if user wants to force refresh
        const forceRefresh = $('#force-refresh').is(':checked');
        
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
                url: url,
                'g-recaptcha-response': recaptchaResponse,
                'force_refresh': forceRefresh
            },
            success: function(response) {
                if (response.success) {
                    // Show detailed cache status
                    if (response.data.cached) {
                        showCacheInfo(response.data.cache_age_minutes, response.data.cache_expires_minutes);
                    } else if (forceRefresh) {
                        showInfo('✅ Fresh content fetched successfully (bypassed cache)');
                    }
                    analyzeText(response.data.text);
                    resetRecaptcha();
                    // Uncheck force refresh for next analysis
                    $('#force-refresh').prop('checked', false);
                } else {
                    showError(response.data.message || 'Failed to fetch URL content');
                    resetRecaptcha();
                }
            },
            error: function() {
                showError('Network error. Please try again.');
                resetRecaptcha();
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('.btn-text').show();
                $btn.find('.btn-loader').hide();
            }
        });
    }
    
    /**
     * Show cache information
     */
    function showCacheInfo(ageMinutes, expiresMinutes) {
        let message = '';
        
        if (ageMinutes === null || expiresMinutes === null) {
            message = '📦 Using cached results.';
        } else if (ageMinutes < 1) {
            message = `📦 Using cached results (analyzed just now). Cache expires in ${expiresMinutes} minute${expiresMinutes !== 1 ? 's' : ''}.`;
        } else {
            message = `📦 Using cached results (analyzed ${ageMinutes} minute${ageMinutes !== 1 ? 's' : ''} ago). Cache expires in ${expiresMinutes} minute${expiresMinutes !== 1 ? 's' : ''}.`;
        }
        
        message += '<br><small style="color: var(--text-secondary);">💡 Tip: Check "Force fresh analysis" if content changed recently.</small>';
        
        showInfo(message);
    }
    
    /**
     * Display analysis results
     * @param {Object} results - Analysis results
     * @param {boolean} scrollToResults - Whether to scroll to results (default: true)
     */
    function displayResults(results, scrollToResults = true) {
        // Update basic stats
        $('#total-words').text(formatNumber(results.totalWords));
        $('#unique-words').text(formatNumber(results.uniqueWords));
        $('#analysis-time').text(results.analysisTime + 's');
        
        // Display relevancy score
        displayRelevancyScore(results.relevancyScore);
        
        // Display SEO elements
        displaySEOElements(results.seoElements, results.prominence.topKeyword);
        
        // Display prominence
        displayProminence(results.prominence);
        
        // Display readability
        displayReadability(results.readability);
        
        // Display stemming warnings (if any)
        displayStemmingWarnings(results.stemmingWarnings);
        
        // Get keywords for current phrase length
        const keywords = results.keywords[currentPhraseLength];
        const stemmedKeywords = results.stemmedKeywords[currentPhraseLength];
        
        // Build table - show individual or stemmed based on toggle
        const $tbody = $('#keywords-tbody');
        $tbody.empty();
        
        const showStemmed = $('#view-stemmed').is(':checked');
        const dataToShow = showStemmed && currentPhraseLength <= 3 ? stemmedKeywords : keywords;
        
        if (!dataToShow || dataToShow.length === 0) {
            $tbody.append('<tr><td colspan="4" style="text-align:center;">No keywords found</td></tr>');
        } else {
            dataToShow.slice(0, 20).forEach(function(kw) {
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
                
                // For stemmed view, show variants
                let phraseDisplay = escapeHtml(kw.phrase || kw.originalPhrase);
                if (showStemmed && kw.variants && kw.variants.length > 1) {
                    const variantList = kw.variants.map(v => escapeHtml(v.phrase)).join(', ');
                    phraseDisplay += `<br><small style="color: #9ca3af;">Variants: ${variantList}</small>`;
                }
                
                const row = `
                    <tr>
                        <td>${phraseDisplay}</td>
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
        
        // Scroll to results only if requested (not when switching filters)
        if (scrollToResults) {
            $('html, body').animate({
                scrollTop: $('#keyword-results').offset().top - 100
            }, 500);
        }
    }
    
    /**
     * Display relevancy score
     */
    function displayRelevancyScore(score) {
        $('#relevancy-score').text(score.total);
        $('#score-prominence').text(score.breakdown.prominence);
        $('#score-seo').text(score.breakdown.seoElements);
        $('#score-density').text(score.breakdown.density);
        $('#score-readability').text(score.breakdown.readability);
        
        // Update score status
        let scoreStatus = '';
        if (score.total >= 80) {
            scoreStatus = 'Excellent - Well optimized content';
        } else if (score.total >= 60) {
            scoreStatus = 'Good - Room for improvement';
        } else if (score.total >= 40) {
            scoreStatus = 'Fair - Needs optimization';
        } else {
            scoreStatus = 'Poor - Requires significant work';
        }
        $('#score-status').text(scoreStatus);
    }
    
    /**
     * Display SEO elements
     */
    function displaySEOElements(elements, topKeyword) {
        const $container = $('#seo-elements-list');
        $container.empty();
        
        let html = '';
        
        // Title tag
        if (elements.title.exists) {
            const hasKeyword = elements.title.text.toLowerCase().includes(topKeyword.toLowerCase());
            const icon = hasKeyword ? '✓' : '✗';
            const cssClass = hasKeyword ? 'working' : 'broken';
            html += `<div class="seo-check-item ${cssClass}">
                <span class="check-icon">${icon}</span>
                <span>Keyword in Title tag (${elements.title.length} chars - ${elements.title.lengthStatus})</span>
            </div>`;
        } else {
            html += `<div class="seo-check-item broken">
                <span class="check-icon">✗</span>
                <span>Title tag missing</span>
            </div>`;
        }
        
        // H1 tag
        if (elements.h1.exists) {
            const hasKeyword = elements.h1.text.toLowerCase().includes(topKeyword.toLowerCase());
            const icon = hasKeyword && elements.h1.countStatus === 'optimal' ? '✓' : '⚠';
            const cssClass = hasKeyword && elements.h1.countStatus === 'optimal' ? 'working' : 'warning';
            const countText = elements.h1.countStatus === 'multiple' ? ` (${elements.h1.count} H1s - should be 1)` : '';
            html += `<div class="seo-check-item ${cssClass}">
                <span class="check-icon">${icon}</span>
                <span>Keyword in H1 tag${countText}</span>
            </div>`;
        } else {
            html += `<div class="seo-check-item broken">
                <span class="check-icon">✗</span>
                <span>H1 tag missing</span>
            </div>`;
        }
        
        // Meta description
        if (elements.meta.exists) {
            const hasKeyword = elements.meta.text.toLowerCase().includes(topKeyword.toLowerCase());
            const icon = hasKeyword ? '✓' : '⚠';
            const cssClass = hasKeyword ? 'working' : 'warning';
            html += `<div class="seo-check-item ${cssClass}">
                <span class="check-icon">${icon}</span>
                <span>Keyword in Meta description (${elements.meta.length} chars - ${elements.meta.lengthStatus})</span>
            </div>`;
        } else {
            html += `<div class="seo-check-item warning">
                <span class="check-icon">⚠</span>
                <span>Meta description missing</span>
            </div>`;
        }
        
        // Alt text
        if (elements.alt.totalImages > 0) {
            const icon = elements.alt.coverage >= 80 ? '✓' : '⚠';
            const cssClass = elements.alt.coverage >= 80 ? 'working' : 'warning';
            html += `<div class="seo-check-item ${cssClass}">
                <span class="check-icon">${icon}</span>
                <span>Alt text coverage: ${elements.alt.coverage}% (${elements.alt.imagesWithAlt}/${elements.alt.totalImages} images)</span>
            </div>`;
        }
        
        $container.html(html);
    }
    
    /**
     * Display prominence
     */
    function displayProminence(prominence) {
        const $container = $('#prominence-list');
        $container.empty();
        
        let html = `<p class="prominence-keyword">Primary keyword: <strong>"${escapeHtml(prominence.topKeyword)}"</strong></p>`;
        
        // First 100 words
        const first100Icon = prominence.inFirst100 ? '✓' : '✗';
        const first100Class = prominence.inFirst100 ? 'working' : 'broken';
        html += `<div class="seo-check-item ${first100Class}">
            <span class="check-icon">${first100Icon}</span>
            <span>Found in first 100 words</span>
        </div>`;
        
        // First paragraph
        const firstParaIcon = prominence.inFirstParagraph ? '✓' : '✗';
        const firstParaClass = prominence.inFirstParagraph ? 'working' : 'broken';
        html += `<div class="seo-check-item ${firstParaClass}">
            <span class="check-icon">${firstParaIcon}</span>
            <span>Found in first paragraph</span>
        </div>`;
        
        // Distribution
        const distIcon = prominence.distribution.isEven ? '✓' : '⚠';
        const distClass = prominence.distribution.isEven ? 'working' : 'warning';
        const distText = prominence.distribution.isEven ? 'Even distribution' : 'Uneven distribution';
        html += `<div class="seo-check-item ${distClass}">
            <span class="check-icon">${distIcon}</span>
            <span>${distText} (Beginning ${prominence.distribution.inBeginning ? '✓' : '✗'} | Middle ${prominence.distribution.inMiddle ? '✓' : '✗'} | End ${prominence.distribution.inEnd ? '✓' : '✗'})</span>
        </div>`;
        
        $container.html(html);
    }
    
    /**
     * Display readability
     */
    function displayReadability(readability) {
        $('#readability-score').text(readability.fleschScore);
        $('#readability-grade').text(readability.gradeLevel);
        $('#readability-status').text(readability.status.replace('-', ' '));
        $('#avg-sentence-length').text(readability.avgSentenceLength + ' words');
        $('#avg-syllables').text(readability.avgSyllablesPerWord);
        
        // Update readability status class
        const $scoreElem = $('#readability-score');
        $scoreElem.removeClass('score-good score-medium score-poor');
        
        if (readability.fleschScore >= 60) {
            $scoreElem.addClass('score-good');
        } else if (readability.fleschScore >= 40) {
            $scoreElem.addClass('score-medium');
        } else {
            $scoreElem.addClass('score-poor');
        }
    }
    
    /**
     * Display stemming warnings
     */
    function displayStemmingWarnings(warnings) {
        const $container = $('#stemming-warnings');
        
        if (!warnings || warnings.length === 0) {
            $container.hide();
            return;
        }
        
        $container.show();
        const $list = $('#stemming-warnings-list');
        $list.empty();
        
        warnings.forEach(warning => {
            const html = `<div class="warning-item">
                <span class="warning-icon">⚠</span>
                <div class="warning-content">
                    <strong>Keyword Variant Over-Optimization</strong>
                    <p>${escapeHtml(warning.message)}</p>
                    <div class="warning-details">
                        <span>Root: "${escapeHtml(warning.stemmed)}"</span> | 
                        <span>${warning.variantCount} variants</span> | 
                        <span>Combined density: ${warning.totalDensity}%</span>
                    </div>
                </div>
            </div>`;
            $list.append(html);
        });
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
