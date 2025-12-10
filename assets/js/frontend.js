class AIAwesomePoller {
    constructor() {
        this.isPolling = false;
        this.pollInterval = 2000; // Fast polling every 2 seconds with Node.js
        this.maxRetries = 3;
        this.retryCount = 0;
        this.backoffMultiplier = 2; // For exponential backoff

        // Use configuration from PHP
        this.config = window.aiAwesomeConfig || {};
        this.isDev = this.config.isDev || false;
        this.isDebug = this.config.isDebug || false;
        this.environment = this.config.environment || 'production';
        this.useProxy = this.config.useProxy || false;

        // Get domain and API URL from config
        this.domain = this.config.domain || this.extractDomainFromUrl();
        this.apiUrl = this.config.apiUrl || 'https://account.genwave.ai/api';
        // Adjust polling interval for development (but still reasonable)
        if (this.isDev) {
            this.pollInterval = 2000; // 2 seconds for dev with fast Node.js API
            this.maxRetries = 5;
        }

        this.init();
    }

    init() {
        this.logInfo('Initializing Gen Wave Poller');
        this.logInfo(`Environment: ${this.environment}`);
        this.logInfo(`Is Dev: ${this.isDev}`);
        this.logInfo(`API URL: ${this.apiUrl}`);
        this.logInfo(`Domain: ${this.domain}`);
        this.logInfo(`Poll interval: ${this.pollInterval}ms`);

        // Start polling when page loads
        this.startPolling();

        // Listen for AI request events
        document.addEventListener('ai-request-sent', () => {
            this.startPolling();
        });
    }

    /**
     * Extract domain from current URL if not provided by config
     */
    extractDomainFromUrl() {
        const fullPath = window.location.origin + window.location.pathname;
        let domain = fullPath.replace(/\/$/, '').replace(/^https?:\/\//, '');

        if (domain === window.location.hostname) {
            domain = window.location.hostname;
        }

        return domain;
    }

    /**
     * Enhanced logging that respects environment
     */
    logInfo(message, data = null) {
        if (this.isDev || this.isDebug) {
            console.log(`[Gen Wave ${this.environment.toUpperCase()}] ${message}`, data || '');
        }
    }

    logError(message, error = null) {
        if (this.isDev || this.isDebug) {
            console.error(`[Gen Wave ${this.environment.toUpperCase()} ERROR] ${message}`, error || '');
        } else {
            console.error(`[Gen Wave ERROR] ${message}`);
        }
    }

    logWarning(message, data = null) {
        if (this.isDev || this.isDebug) {
            console.warn(`[Gen Wave ${this.environment.toUpperCase()} WARNING] ${message}`, data || '');
        }
    }

    startPolling() {
        if (this.isPolling) return;

        this.isPolling = true;
        this.retryCount = 0;

        this.logInfo('Starting polling for results...');
        this.poll();
    }

    stopPolling() {
        this.isPolling = false;
        this.logInfo('Polling stopped');
    }

    /**
     * Calculate backoff delay for retries
     */
    getBackoffDelay() {
        return this.pollInterval * Math.pow(this.backoffMultiplier, this.retryCount);
    }

    /**
     * Generate realistic User-Agent
     */
    getUserAgent() {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    }

    /**
     * Add random jitter to prevent synchronized requests
     */
    addJitter(delay) {
        const jitter = Math.random() * 5000; // 0-5 seconds
        return delay + jitter;
    }

    async poll() {
        if (!this.isPolling) return;

        try {
            let response;
            const domainForApi = this.domain.replace(/^https?:\/\//, '').split('/')[0];

            if (this.useProxy) {
                // Use WordPress proxy to avoid CORS issues
                this.logInfo(`Polling via proxy: domain ${domainForApi}`);

                if (!this.config.nonce) {
                    throw new Error('Nonce not available for proxy request');
                }

                const formData = new FormData();
                formData.append('action', 'ai_poll_proxy');
                formData.append('domain', domainForApi);
                formData.append('nonce', this.config.nonce);

                response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'User-Agent': this.getUserAgent()
                    }
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    this.logError('Proxy response error:', errorText);

                    // Check for Cloudflare-specific errors
                    if (response.status === 403 || response.status === 429) {
                        throw new Error(`Cloudflare blocked request: ${response.status}`);
                    }

                    throw new Error(`Proxy error ${response.status}: ${response.statusText}`);
                }
            } else {
                // Direct API call with enhanced headers
                const pollUrl = `${this.apiUrl}/poll-results/${domainForApi}`;
                this.logInfo(`Polling directly: ${pollUrl}`);

                response = await fetch(pollUrl, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'User-Agent': this.getUserAgent(),
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });
            }

            if (!response.ok) {
                // Handle Cloudflare-specific status codes
                if (response.status === 403) {
                    throw new Error('Cloudflare blocked request (403 Forbidden)');
                }
                if (response.status === 429) {
                    throw new Error('Rate limited by Cloudflare (429 Too Many Requests)');
                }
                if (response.status === 503) {
                    throw new Error('Service temporarily unavailable (503)');
                }

                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            
            // ✅ ADD: Enhanced logging for debugging
            this.logInfo('Poll response:', {
                success: data.success,
                has_results: data.has_results,
                results_count: data.results ? data.results.length : 0,
                domain: domainForApi
            });

            if (data.success && data.has_results) {
                this.logInfo('Results received:', data.results);
                this.handleResults(data.results);
                
                // ✅ Update token balance if provided
                console.log('🔍 RECEIVED TOKEN BALANCE:', data.token_balance);
                if (data.token_balance !== null && data.token_balance !== undefined) {
                    console.log('🎯 CALLING updateTokenBalance with:', data.token_balance);
                    this.updateTokenBalance(data.token_balance);
                } else {
                    console.log('❌ No token_balance in response');
                }
            } else {
                this.logInfo('No results yet, continuing to poll...');
            }

            // Reset retry count on successful request
            this.retryCount = 0;

        } catch (error) {
            this.logError('Polling Error:', error);
            this.retryCount++;

            // Special handling for Cloudflare errors
            if (error.message.includes('Cloudflare') || error.message.includes('403') || error.message.includes('429')) {
                this.logWarning('Cloudflare protection detected, using exponential backoff');

                if (this.retryCount >= this.maxRetries) {
                    this.logError('Max retries reached due to Cloudflare blocking, stopping polling');
                    this.stopPolling();
                    return;
                }

                // Use exponential backoff for Cloudflare errors
                const backoffDelay = this.getBackoffDelay();
                const delayWithJitter = this.addJitter(backoffDelay);

                this.logInfo(`Waiting ${Math.round(delayWithJitter/1000)} seconds before retry due to Cloudflare protection`);

                setTimeout(() => this.poll(), delayWithJitter);
                return;
            }

            if (this.retryCount >= this.maxRetries) {
                this.logError('Max retries reached, stopping polling');
                this.stopPolling();
                return;
            }
        }

        // Schedule next poll with jitter to prevent synchronized requests
        if (this.isPolling) {
            const nextPollDelay = this.addJitter(this.pollInterval);
            setTimeout(() => this.poll(), nextPollDelay);
        }
    }

    handleResults(results) {
        console.log('🎯 HANDLING RESULTS:', results.length, 'results');
        results.forEach(result => {
            try {
                const responseData = JSON.parse(result.response_data);
                console.log('📋 PROCESSING RESULT:', result.request_id, 'Job ID:', result.id);

                this.logInfo(`Processing result for request ${result.request_id}:`, responseData);

                // ✅ ADD: Save token usage info for bulk processing
                if (result.tokens_info) {
                    console.log('💎 Found token info in bulk result:', result.tokens_info);
                    this.saveTokenUsageForBulk(result.tokens_info, result.id);
                } else {
                    console.log('❌ No token info found in bulk result');
                }

                // NEW: Mark this job as delivered to the API
                this.markJobAsDelivered(result.id);

                // Trigger custom event for other scripts to handle
                const event = new CustomEvent('ai-results-received', {
                    detail: {
                        requestId: result.request_id,
                        jobId: result.id,
                        data: responseData,
                        status: result.status,
                        domain: result.domain_name
                    }
                });

                document.dispatchEvent(event);

                // Send results to WordPress REST API
                this.sendToWordPress(responseData, result.request_id);

                // If there's a specific handler for this request
                this.processResult(responseData, result.request_id);

            } catch (error) {
                this.logError('Error processing result:', error, result);
            }
        });
    }

    processResult(responseData, requestId) {
        // Look for waiting elements with this request ID
        const waitingElements = document.querySelectorAll(`[data-ai-request-id="${requestId}"]`);

        this.logInfo(`Found ${waitingElements.length} waiting elements for request ${requestId}`);

        waitingElements.forEach(element => {
            try {
                // Check if this is an image response
                if (responseData.type === 'image' && responseData.image_url) {
                    // Handle image response
                    this.handleImageResponse(element, responseData);
                } 
                // Check if response contains multiple types (text and image)
                else if (responseData.results && Array.isArray(responseData.results)) {
                    responseData.results.forEach(result => {
                        if (result.type === 'image' && result.image_url) {
                            this.handleImageResponse(element, result);
                        } else if (result.content) {
                            element.innerHTML = result.content;
                        }
                    });
                }
                // Handle standard text content
                else if (responseData.content) {
                    element.innerHTML = responseData.content;
                }

                // Remove loading state
                element.classList.remove('ai-loading');
                element.classList.add('ai-completed');

                // Remove the request ID attribute
                element.removeAttribute('data-ai-request-id');

                this.logInfo(`Updated element for request ${requestId}`);

            } catch (error) {
                this.logError('Error updating element:', error);
                element.innerHTML = '<p>Error loading AI content</p>';
                element.classList.remove('ai-loading');
                element.classList.add('ai-error');
            }
        });
    }
    
    /**
     * Handle image response and update element accordingly
     */
    handleImageResponse(element, imageData) {
        this.logInfo('Processing image response:', imageData);
        
        // Check if element is an img tag
        if (element.tagName === 'IMG') {
            element.src = imageData.image_url;
            element.alt = imageData.metadata?.prompt || 'AI Generated Image';
        }
        // Check if element is a WordPress featured image container
        else if (element.classList.contains('wp-post-image') || element.classList.contains('featured-image')) {
            const img = element.querySelector('img') || document.createElement('img');
            img.src = imageData.image_url;
            img.alt = imageData.metadata?.prompt || 'AI Generated Image';
            img.className = 'wp-post-image ai-generated-image';
            
            if (!element.querySelector('img')) {
                element.appendChild(img);
            }
        }
        // For WooCommerce product images
        else if (element.classList.contains('woocommerce-product-gallery__image')) {
            const img = element.querySelector('img') || document.createElement('img');
            img.src = imageData.image_url;
            img.alt = imageData.metadata?.prompt || 'AI Generated Product Image';
            img.className = 'wp-post-image ai-generated-image';
            
            // Update data attributes for WooCommerce
            img.setAttribute('data-large_image', imageData.image_url);
            img.setAttribute('data-large_image_width', imageData.metadata?.size?.split('x')[0] || '1024');
            img.setAttribute('data-large_image_height', imageData.metadata?.size?.split('x')[1] || '1024');
            
            if (!element.querySelector('img')) {
                element.appendChild(img);
            }
        }
        // Generic container - create img element
        else {
            const img = document.createElement('img');
            img.src = imageData.image_url;
            img.alt = imageData.metadata?.prompt || 'AI Generated Image';
            img.className = 'ai-generated-image';
            img.style.maxWidth = '100%';
            img.style.height = 'auto';
            
            // Clear existing content and add image
            element.innerHTML = '';
            element.appendChild(img);
        }
        
        // Add metadata as data attributes
        if (imageData.metadata) {
            element.setAttribute('data-ai-prompt', imageData.metadata.prompt || '');
            element.setAttribute('data-ai-model', imageData.metadata.model || '');
            element.setAttribute('data-ai-size', imageData.metadata.size || '');
        }
    }

    // NEW: Mark job as delivered to prevent duplicate delivery
    async markJobAsDelivered(jobId) {
        try {
            const url = `${this.apiUrl}/mark-delivered/${jobId}`;
            console.log('📤 MARKING JOB AS DELIVERED:', jobId, 'URL:', url);
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'User-Agent': this.getUserAgent(),
                }
            });

            if (response.ok) {
                console.log('✅ JOB MARKED AS DELIVERED:', jobId);
                this.logInfo(`Marked job ${jobId} as delivered`);
            } else {
                console.log('❌ FAILED TO MARK JOB AS DELIVERED:', jobId, 'Status:', response.status);
                this.logWarning(`Failed to mark job ${jobId} as delivered: ${response.status}`);
            }
        } catch (error) {
            this.logError('Error marking job as delivered:', error);
        }
    }

    async sendToWordPress(responseData, requestId) {
        try {
            this.logInfo('Sending results to WordPress...', responseData);

            const ajaxUrl = this.config.ajaxUrl || `${window.location.origin}/wp-admin/admin-ajax.php`;

            const formData = new FormData();
            formData.append('action', 'ai_polling_results');
            formData.append('response', JSON.stringify(responseData));

            if (this.config.nonce) {
                formData.append('nonce', this.config.nonce);
            }

            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'User-Agent': this.getUserAgent()
                }
            });

            this.logInfo(`AJAX response status: ${response.status}`);

            if (!response.ok) {
                const errorText = await response.text();
                this.logError('AJAX error response:', errorText);
                throw new Error(`WordPress API error: ${response.status} ${response.statusText}`);
            }

            const result = await response.json();
            this.logInfo('Successfully sent to WordPress:', result);

            if (result.success) {
                this.logInfo(`WordPress processed ${result.processed_count} posts`);
                if (result.errors) {
                    this.logWarning('Some errors occurred:', result.errors);
                }
            } else {
                this.logError('WordPress processing failed:', result.message);
            }

        } catch (error) {
            this.logError('Error sending to WordPress:', error);
        }
    }

    /**
     * Development helper methods
     */
    getStatus() {
        return {
            isPolling: this.isPolling,
            environment: this.environment,
            isDev: this.isDev,
            isDebug: this.isDebug,
            useProxy: this.useProxy,
            domain: this.domain,
            apiUrl: this.apiUrl,
            pollInterval: this.pollInterval,
            retryCount: this.retryCount,
            maxRetries: this.maxRetries,
            backoffMultiplier: this.backoffMultiplier
        };
    }

    // Force a poll (dev helper)
    forcePoll() {
        this.logInfo('Force polling triggered');
        this.poll();
    }
    
    // ✅ ADD: Force poll for specific job
    async forceCheckJob(requestId) {
        this.logInfo(`Force checking job ${requestId}`);
        const domain = this.domain.replace(/^https?:\/\//, '').split('/')[0];
        const pollUrl = `${this.apiUrl}/poll-results/${domain}`;
        
        try {
            const response = await fetch(pollUrl, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'User-Agent': this.getUserAgent(),
                    'Cache-Control': 'no-cache'
                }
            });
            
            const data = await response.json();
            this.logInfo(`Force poll result for job ${requestId}:`, data);
            
            if (data.success && data.has_results) {
                this.handleResults(data.results);
            }
            
            return data;
        } catch (error) {
            this.logError(`Force poll error for job ${requestId}:`, error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Update token balance in WordPress
     */
    updateTokenBalance(newBalance) {
        try {
            console.log('🔄 TOKEN UPDATE CALLED:', newBalance);
            this.logInfo('Updating token balance:', newBalance);
            
            // Update admin bar token display (if exists)
            const adminBarToken = document.querySelector('li#wp-admin-bar-custom_text_with_icon span');
            if (adminBarToken) {
                adminBarToken.textContent = parseFloat(newBalance).toFixed(2);
                this.logInfo('Admin bar token updated');
            }
            
            // Update any other token displays on the page
            const tokenDisplays = document.querySelectorAll('[data-token-display]');
            tokenDisplays.forEach(display => {
                display.textContent = parseFloat(newBalance).toFixed(2);
            });
            
            // Trigger custom event for other scripts
            const event = new CustomEvent('token-balance-updated', {
                detail: { 
                    newBalance: parseFloat(newBalance),
                    formattedBalance: parseFloat(newBalance).toFixed(2)
                }
            });
            document.dispatchEvent(event);
            
            // Save to WordPress options using AJAX
            this.syncTokenBalanceToWP(newBalance);
            
        } catch (error) {
            this.logError('Error updating token balance:', error);
        }
    }

    /**
     * Sync token balance to WordPress options
     */
    async syncTokenBalanceToWP(newBalance) {
        try {
            const ajaxUrl = this.config.ajaxUrl || `${window.location.origin}/wp-admin/admin-ajax.php`;
            
            const formData = new FormData();
            formData.append('action', 'update_token_balance');
            formData.append('token_balance', newBalance);
            
            if (this.config.nonce) {
                formData.append('security', this.config.nonce);
            }
            
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    this.logInfo('Token balance synced to WordPress successfully');
                } else {
                    this.logWarning('Token balance sync failed:', result.data?.message);
                }
            }
            
        } catch (error) {
            this.logError('Error syncing token balance to WordPress:', error);
        }
    }

    /**
     * Save token usage info for bulk processing
     * Same as AI Pro single processing but for bulk results from frontend.js
     */
    async saveTokenUsageForBulk(tokenInfo, jobRequestId) {
        try {
            console.log('💎 Saving bulk token usage:', { tokenInfo, jobRequestId });
            
            const tokenData = {
                action: 'ai_save_token_usage',
                job_id: jobRequestId,
                tokens_estimated: tokenInfo.tokens_estimated || 0,
                tokens_actually_used: tokenInfo.tokens_actually_used || 0,
                tokens_refunded: tokenInfo.tokens_refunded || 0,
                tokens_charged_to_user: tokenInfo.tokens_charged_to_user || 0,
                refund_applied: tokenInfo.refund_applied ? 1 : 0,
                usage_efficiency: tokenInfo.usage_efficiency || 0,
                nonce: this.config.nonce || 'no-nonce'
            };

            console.log('📤 Sending bulk token data to WordPress:', tokenData);

            const response = await fetch(this.config.ajaxUrl || `${window.location.origin}/wp-admin/admin-ajax.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(tokenData)
            });

            const result = await response.json();
            
            if (result.success) {
                console.log('✅ Bulk token usage saved successfully:', result);
            } else {
                console.error('❌ Failed to save bulk token usage:', result);
            }

        } catch (error) {
            console.error('❌ Error saving bulk token usage:', error);
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.aiAwesomePoller === 'undefined') {
        window.aiAwesomePoller = new AIAwesomePoller();
    }
});

// Enhanced global interface
window.aiAwesome = {
    startPolling: function() {
        if (window.aiAwesomePoller) {
            window.aiAwesomePoller.startPolling();
        }
    },

    stopPolling: function() {
        if (window.aiAwesomePoller) {
            window.aiAwesomePoller.stopPolling();
        }
    },

    checkNow: function() {
        if (window.aiAwesomePoller) {
            window.aiAwesomePoller.poll();
        }
    },

    isDev: function() {
        return window.aiAwesomePoller ? window.aiAwesomePoller.isDev : false;
    },

    getEnvironment: function() {
        return window.aiAwesomePoller ? window.aiAwesomePoller.environment : 'unknown';
    },

    getApiUrl: function() {
        return window.aiAwesomePoller ? window.aiAwesomePoller.apiUrl : 'unknown';
    },

    getStatus: function() {
        return window.aiAwesomePoller ? window.aiAwesomePoller.getStatus() : null;
    },

    forcePoll: function() {
        if (window.aiAwesomePoller) {
            window.aiAwesomePoller.forcePoll();
        }
    },
    
    // ✅ ADD: Force check specific job
    forceCheckJob: function(requestId) {
        if (window.aiAwesomePoller) {
            return window.aiAwesomePoller.forceCheckJob(requestId);
        }
        return Promise.reject('Poller not initialized');
    },

    isDevEnvironment: function() {
        return this.isDev();
    },

    isProductionEnvironment: function() {
        return this.getEnvironment() === 'production';
    },

    isStagingEnvironment: function() {
        return this.getEnvironment() === 'staging';
    }
};