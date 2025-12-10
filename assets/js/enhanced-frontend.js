class EnhancedAIPoller {
    constructor() {
        this.isPolling = false;
        this.basePollInterval = 5000; // Base 5 seconds for active jobs
        this.currentPollInterval = this.basePollInterval;
        this.maxPollInterval = 120000; // Max 2 minutes
        this.maxRetries = 5;
        this.retryCount = 0;
        this.backoffMultiplier = 1.5;
        this.consecutiveEmptyPolls = 0;
        this.maxEmptyPollsBeforeSlowdown = 3;

        // Enhanced configuration from PHP
        this.config = window.aiAwesomeConfig || {};
        this.isDev = this.config.isDev || false;
        this.isDebug = this.config.isDebug || false;
        this.environment = this.config.environment || 'production';
        this.useEnhancedPolling = this.config.useEnhancedPolling || true;

        // Get domain and API URL from config
        this.domain = this.config.domain || this.extractDomainFromUrl();
        this.apiUrl = this.config.apiUrl || 'https://account.genwave.ai/api';

        // Polling state management
        this.activeJobs = new Map(); // Track active jobs
        this.lastPolledTimestamp = null;
        this.pollingTimeoutId = null;

        // Statistics for optimization
        this.stats = {
            totalPolls: 0,
            successfulPolls: 0,
            emptyPolls: 0,
            errorPolls: 0,
            averageResponseTime: 0,
            lastSuccessfulPoll: null
        };

        this.init();
    }

    init() {
        this.logInfo('🚀 Enhanced AI Poller initialized');
        this.logInfo(`Environment: ${this.environment}`);
        this.logInfo(`Enhanced Polling: ${this.useEnhancedPolling ? 'ENABLED' : 'DISABLED'}`);
        this.logInfo(`Base interval: ${this.basePollInterval}ms`);
        this.logInfo(`Domain: ${this.domain}`);

        // Listen for AI request events
        document.addEventListener('ai-request-sent', (e) => {
            this.handleNewRequest(e.detail);
        });

        // Listen for bulk request events
        document.addEventListener('ai-bulk-request-sent', (e) => {
            this.handleNewBulkRequest(e.detail);
        });

        // Start intelligent polling
        this.startIntelligentPolling();
    }

    /**
     * Handle new AI request - start polling with higher frequency
     */
    handleNewRequest(requestDetails) {
        this.logInfo('📨 New AI request detected', requestDetails);

        if (requestDetails) {
            this.activeJobs.set(requestDetails.requestId || Date.now(), {
                type: requestDetails.type || 'single',
                timestamp: Date.now(),
                requestId: requestDetails.requestId
            });
        }

        // Reset to fast polling when new request detected
        this.resetToFastPolling();
        this.startIntelligentPolling();
    }

    /**
     * Handle new bulk AI request
     */
    handleNewBulkRequest(requestDetails) {
        this.logInfo('📦 New bulk AI request detected', requestDetails);

        if (requestDetails) {
            this.activeJobs.set(requestDetails.requestId || Date.now(), {
                type: 'bulk',
                timestamp: Date.now(),
                requestId: requestDetails.requestId,
                expectedItems: requestDetails.itemCount || 1
            });
        }

        // Reset to fast polling for bulk requests
        this.resetToFastPolling();
        this.startIntelligentPolling();
    }

    /**
     * Start intelligent polling with adaptive intervals
     */
    startIntelligentPolling() {
        if (this.isPolling) {
            return; // Already polling
        }

        this.isPolling = true;
        this.retryCount = 0;
        this.consecutiveEmptyPolls = 0;

        this.logInfo('🔄 Starting intelligent polling');
        this.poll();
    }

    /**
     * Stop polling
     */
    stopPolling() {
        this.isPolling = false;
        if (this.pollingTimeoutId) {
            clearTimeout(this.pollingTimeoutId);
            this.pollingTimeoutId = null;
        }
        this.logInfo('⏹️ Polling stopped');
    }

    /**
     * Reset to fast polling when activity detected
     */
    resetToFastPolling() {
        this.currentPollInterval = this.basePollInterval;
        this.consecutiveEmptyPolls = 0;
        this.retryCount = 0;
    }

    /**
     * Enhanced polling logic with adaptive intervals
     */
    async poll() {
        if (!this.isPolling) return;

        const startTime = Date.now();
        this.stats.totalPolls++;

        try {
            let response;
            const domainForApi = this.domain.replace(/^https?:\/\//, '').split('/')[0];

            // Choose polling endpoint based on configuration
            const pollUrl = this.useEnhancedPolling
                ? `${this.apiUrl}/bulk-poll-results/${domainForApi}`
                : `${this.apiUrl}/poll-results/${domainForApi}`;

            this.logInfo(`🔍 Polling: ${pollUrl}`);

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

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            const responseTime = Date.now() - startTime;

            // Update statistics
            this.updateStats(responseTime, data.success);

            this.logInfo('📊 Poll response', {
                success: data.success,
                has_results: data.has_results,
                results_count: data.results ? data.results.length : 0,
                response_time: responseTime,
                token_balance: data.token_balance
            });

            if (data.success && data.has_results && data.results?.length > 0) {
                this.handleResults(data.results);
                this.consecutiveEmptyPolls = 0;

                // Update token balance if provided
                if (data.token_balance !== null && data.token_balance !== undefined) {
                    this.updateTokenBalance(data.token_balance);
                }

                // Reset to fast polling after successful result
                this.resetToFastPolling();
            } else {
                this.consecutiveEmptyPolls++;
                this.logInfo(`📭 Empty poll #${this.consecutiveEmptyPolls}`);
            }

            // Reset retry count on successful request
            this.retryCount = 0;
            this.stats.successfulPolls++;

        } catch (error) {
            this.handlePollingError(error);
        }

        // Schedule next poll with adaptive interval
        this.scheduleNextPoll();
    }

    /**
     * Handle polling errors with exponential backoff
     */
    handlePollingError(error) {
        this.logError('❌ Polling error:', error);
        this.retryCount++;
        this.stats.errorPolls++;

        // Special handling for Cloudflare errors
        if (this.isCloudflareError(error)) {
            this.logWarning('☁️ Cloudflare protection detected');

            if (this.retryCount >= this.maxRetries) {
                this.logError('🚫 Max retries reached due to Cloudflare blocking');
                this.stopPolling();
                return;
            }
        }

        // Regular error handling
        if (this.retryCount >= this.maxRetries) {
            this.logError('🚫 Max retries reached, stopping polling');
            this.stopPolling();
            return;
        }
    }

    /**
     * Schedule next poll with adaptive interval
     */
    scheduleNextPoll() {
        if (!this.isPolling) return;

        let nextInterval = this.calculateNextInterval();

        this.logInfo(`⏰ Next poll in ${Math.round(nextInterval/1000)}s`);

        this.pollingTimeoutId = setTimeout(() => {
            this.poll();
        }, nextInterval);
    }

    /**
     * Calculate adaptive polling interval
     */
    calculateNextInterval() {
        let interval = this.currentPollInterval;

        // Increase interval after consecutive empty polls
        if (this.consecutiveEmptyPolls >= this.maxEmptyPollsBeforeSlowdown) {
            interval = Math.min(
                interval * Math.pow(this.backoffMultiplier,
                    this.consecutiveEmptyPolls - this.maxEmptyPollsBeforeSlowdown + 1),
                this.maxPollInterval
            );
        }

        // Add exponential backoff for retries
        if (this.retryCount > 0) {
            interval = interval * Math.pow(2, this.retryCount);
        }

        // Add jitter to prevent synchronized requests
        const jitter = Math.random() * 5000; // 0-5 seconds
        return interval + jitter;
    }

    /**
     * Enhanced result handling with job tracking
     */
    handleResults(results) {
        this.logInfo('🎯 Processing results:', results.length);

        results.forEach(result => {
            try {
                let responseData;

                // Handle both string and object response data
                if (typeof result.response_data === 'string') {
                    responseData = JSON.parse(result.response_data);
                } else {
                    responseData = result.response_data;
                }

                this.logInfo('📋 Processing result:', {
                    id: result.id,
                    request_id: result.request_id,
                    type: responseData.type || 'unknown'
                });

                // Remove from active jobs if completed
                if (this.activeJobs.has(result.request_id)) {
                    this.activeJobs.delete(result.request_id);
                    this.logInfo('✅ Job completed and removed from tracking');
                }

                // Handle token usage for bulk results
                if (result.tokens_info) {
                    this.logInfo('💎 Token usage info found:', result.tokens_info);
                    this.saveTokenUsageForBulk(result.tokens_info, result.id);
                }

                // Mark job as delivered
                this.markJobAsDelivered(result.id);

                // Trigger events for other scripts
                this.dispatchResultEvent(result, responseData);

                // Process the result content
                this.processResultContent(responseData, result.request_id);

            } catch (error) {
                this.logError('❌ Error processing result:', error);
            }
        });

        // Update polling strategy based on remaining active jobs
        this.updatePollingStrategy();
    }

    /**
     * Update polling strategy based on active jobs
     */
    updatePollingStrategy() {
        if (this.activeJobs.size === 0) {
            this.logInfo('📝 No active jobs, slowing down polling');
            this.consecutiveEmptyPolls = this.maxEmptyPollsBeforeSlowdown;
        } else {
            this.logInfo(`📋 ${this.activeJobs.size} active jobs remaining`);
        }
    }

    /**
     * Enhanced job delivery marking with bulk support
     */
    async markJobAsDelivered(jobId) {
        try {
            const url = this.useEnhancedPolling
                ? `${this.apiUrl}/bulk-mark-delivered`
                : `${this.apiUrl}/mark-delivered/${jobId}`;

            const method = this.useEnhancedPolling ? 'POST' : 'POST';
            const body = this.useEnhancedPolling ? JSON.stringify({ job_id: jobId }) : null;

            this.logInfo('📤 Marking job as delivered:', { jobId, url, method });

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'User-Agent': this.getUserAgent()
                },
                body: body
            });

            if (response.ok) {
                this.logInfo('✅ Job marked as delivered:', jobId);
            } else {
                this.logWarning('⚠️ Failed to mark job as delivered:', {
                    jobId,
                    status: response.status,
                    statusText: response.statusText
                });
            }
        } catch (error) {
            this.logError('❌ Error marking job as delivered:', error);
        }
    }

    /**
     * Update statistics for optimization
     */
    updateStats(responseTime, success) {
        if (success) {
            this.stats.lastSuccessfulPoll = Date.now();

            // Calculate moving average response time
            if (this.stats.averageResponseTime === 0) {
                this.stats.averageResponseTime = responseTime;
            } else {
                this.stats.averageResponseTime = (this.stats.averageResponseTime * 0.8) + (responseTime * 0.2);
            }
        }
    }

    /**
     * Check if error is Cloudflare-related
     */
    isCloudflareError(error) {
        return error.message.includes('Cloudflare') ||
               error.message.includes('403') ||
               error.message.includes('429') ||
               error.message.includes('Service temporarily unavailable');
    }

    /**
     * Dispatch custom event for result
     */
    dispatchResultEvent(result, responseData) {
        const event = new CustomEvent('ai-results-received', {
            detail: {
                requestId: result.request_id,
                jobId: result.id,
                data: responseData,
                status: result.status || 'completed',
                domain: result.domain_name,
                type: responseData.type || 'content'
            }
        });
        document.dispatchEvent(event);
    }

    /**
     * Process result content (inherited from original)
     */
    processResultContent(responseData, requestId) {
        const waitingElements = document.querySelectorAll(`[data-ai-request-id="${requestId}"]`);

        this.logInfo(`🎯 Found ${waitingElements.length} waiting elements for request ${requestId}`);

        waitingElements.forEach(element => {
            try {
                // Handle different response types
                if (responseData.type === 'bulk_content' && responseData.results) {
                    this.handleBulkContentResponse(element, responseData);
                } else if (responseData.type === 'image' && responseData.image_url) {
                    this.handleImageResponse(element, responseData);
                } else if (responseData.content) {
                    element.innerHTML = responseData.content;
                }

                // Update element state
                element.classList.remove('ai-loading');
                element.classList.add('ai-completed');
                element.removeAttribute('data-ai-request-id');

                this.logInfo(`✅ Updated element for request ${requestId}`);

            } catch (error) {
                this.logError('❌ Error updating element:', error);
                element.innerHTML = '<p>Error loading AI content</p>';
                element.classList.remove('ai-loading');
                element.classList.add('ai-error');
            }
        });
    }

    /**
     * Handle bulk content response
     */
    handleBulkContentResponse(element, responseData) {
        if (responseData.results && Array.isArray(responseData.results)) {
            const combinedContent = responseData.results.map(result => {
                return result.response ? JSON.stringify(result.response, null, 2) : 'No content';
            }).join('\n\n');

            element.innerHTML = `<pre>${combinedContent}</pre>`;

            // Save content to WordPress database
            this.saveContentToDatabase(responseData);
        }
    }

    /**
     * Save bulk content to WordPress database
     */
    saveContentToDatabase(responseData) {
        if (!responseData.results || !Array.isArray(responseData.results)) {
            this.logError('❌ No results to save to database');
            return;
        }

        this.logInfo('💾 Saving content to WordPress database...');

        // Prepare the data for WordPress
        const saveData = {
            action: 'ai_awesome_handle_response',
            nonce: aiAwesomeConfig.nonce || window.aiAwesome?.nonce || '',
            response: JSON.stringify(responseData)
        };

        // Send AJAX request to save data
        fetch(aiAwesomeConfig.ajaxUrl || window.aiAwesome?.ajaxurl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(saveData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.logInfo('✅ Content saved to database successfully');
            } else {
                this.logError('❌ Failed to save content to database:', data.data || data.message);
            }
        })
        .catch(error => {
            this.logError('❌ Error saving content to database:', error);
        });
    }

    /**
     * Handle image response (inherited from original)
     */
    handleImageResponse(element, imageData) {
        this.logInfo('🖼️ Processing image response:', imageData);

        if (element.tagName === 'IMG') {
            element.src = imageData.image_url;
            element.alt = imageData.metadata?.prompt || 'AI Generated Image';
        } else {
            const img = document.createElement('img');
            img.src = imageData.image_url;
            img.alt = imageData.metadata?.prompt || 'AI Generated Image';
            img.className = 'ai-generated-image';
            img.style.maxWidth = '100%';
            img.style.height = 'auto';

            element.innerHTML = '';
            element.appendChild(img);
        }

        if (imageData.metadata) {
            element.setAttribute('data-ai-prompt', imageData.metadata.prompt || '');
            element.setAttribute('data-ai-model', imageData.metadata.model || '');
            element.setAttribute('data-ai-size', imageData.metadata.size || '');
        }
    }

    /**
     * Token balance update (inherited)
     */
    updateTokenBalance(newBalance) {
        try {
            this.logInfo('💰 Updating token balance:', newBalance);

            const adminBarToken = document.querySelector('li#wp-admin-bar-custom_text_with_icon span');
            if (adminBarToken) {
                adminBarToken.textContent = parseFloat(newBalance).toFixed(2);
            }

            const tokenDisplays = document.querySelectorAll('[data-token-display]');
            tokenDisplays.forEach(display => {
                display.textContent = parseFloat(newBalance).toFixed(2);
            });

            const event = new CustomEvent('token-balance-updated', {
                detail: {
                    newBalance: parseFloat(newBalance),
                    formattedBalance: parseFloat(newBalance).toFixed(2)
                }
            });
            document.dispatchEvent(event);

            this.syncTokenBalanceToWP(newBalance);

        } catch (error) {
            this.logError('❌ Error updating token balance:', error);
        }
    }

    /**
     * Sync token balance to WordPress (inherited)
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
                    this.logInfo('✅ Token balance synced to WordPress');
                }
            }

        } catch (error) {
            this.logError('❌ Error syncing token balance:', error);
        }
    }

    /**
     * Save token usage for bulk processing (inherited)
     */
    async saveTokenUsageForBulk(tokenInfo, jobRequestId) {
        try {
            this.logInfo('💎 Saving bulk token usage:', { tokenInfo, jobRequestId });

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

            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(tokenData)
            });

            const result = await response.json();

            if (result.success) {
                this.logInfo('✅ Bulk token usage saved successfully');
            } else {
                this.logError('❌ Failed to save bulk token usage:', result);
            }

        } catch (error) {
            this.logError('❌ Error saving bulk token usage:', error);
        }
    }

    /**
     * Utility methods
     */
    extractDomainFromUrl() {
        const fullPath = window.location.origin + window.location.pathname;
        let domain = fullPath.replace(/\/$/, '').replace(/^https?:\/\//, '');

        if (domain === window.location.hostname) {
            domain = window.location.hostname;
        }

        return domain;
    }

    getUserAgent() {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    }

    logInfo(message, data = null) {
        if (this.isDev || this.isDebug) {
            console.log(`[Enhanced AI Poller ${this.environment.toUpperCase()}] ${message}`, data || '');
        }
    }

    logError(message, error = null) {
        if (this.isDev || this.isDebug) {
            console.error(`[Enhanced AI Poller ${this.environment.toUpperCase()} ERROR] ${message}`, error || '');
        } else {
            console.error(`[Enhanced AI Poller ERROR] ${message}`);
        }
    }

    logWarning(message, data = null) {
        if (this.isDev || this.isDebug) {
            console.warn(`[Enhanced AI Poller ${this.environment.toUpperCase()} WARNING] ${message}`, data || '');
        }
    }

    /**
     * Development and monitoring methods
     */
    getStatus() {
        return {
            isPolling: this.isPolling,
            currentInterval: this.currentPollInterval,
            consecutiveEmptyPolls: this.consecutiveEmptyPolls,
            activeJobs: Array.from(this.activeJobs.entries()),
            stats: this.stats,
            config: {
                environment: this.environment,
                useEnhancedPolling: this.useEnhancedPolling,
                domain: this.domain,
                apiUrl: this.apiUrl
            }
        };
    }

    getStats() {
        return {
            ...this.stats,
            successRate: this.stats.totalPolls > 0 ?
                ((this.stats.successfulPolls / this.stats.totalPolls) * 100).toFixed(2) + '%' : '0%',
            averageResponseTimeFormatted: Math.round(this.stats.averageResponseTime) + 'ms'
        };
    }

    // Force polling for development
    forcePoll() {
        this.logInfo('🔧 Force poll triggered');
        this.resetToFastPolling();
        this.poll();
    }

    // Clear active jobs for development
    clearActiveJobs() {
        this.activeJobs.clear();
        this.logInfo('🧹 Active jobs cleared');
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.enhancedAIPoller === 'undefined') {
        window.enhancedAIPoller = new EnhancedAIPoller();
    }
});

// Enhanced global interface
window.aiAwesome = {
    // Keep original methods for compatibility
    startPolling: function() {
        if (window.enhancedAIPoller) {
            window.enhancedAIPoller.startIntelligentPolling();
        }
    },

    stopPolling: function() {
        if (window.enhancedAIPoller) {
            window.enhancedAIPoller.stopPolling();
        }
    },

    checkNow: function() {
        if (window.enhancedAIPoller) {
            window.enhancedAIPoller.forcePoll();
        }
    },

    // Enhanced methods
    getStatus: function() {
        return window.enhancedAIPoller ? window.enhancedAIPoller.getStatus() : null;
    },

    getStats: function() {
        return window.enhancedAIPoller ? window.enhancedAIPoller.getStats() : null;
    },

    resetPolling: function() {
        if (window.enhancedAIPoller) {
            window.enhancedAIPoller.resetToFastPolling();
        }
    },

    clearActiveJobs: function() {
        if (window.enhancedAIPoller) {
            window.enhancedAIPoller.clearActiveJobs();
        }
    },

    // Legacy compatibility
    isDev: function() {
        return window.enhancedAIPoller ? window.enhancedAIPoller.isDev : false;
    },

    getEnvironment: function() {
        return window.enhancedAIPoller ? window.enhancedAIPoller.environment : 'unknown';
    },

    getApiUrl: function() {
        return window.enhancedAIPoller ? window.enhancedAIPoller.apiUrl : 'unknown';
    }
};