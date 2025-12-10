/**
 * LiteLLM Poller - Enhanced polling for LiteLLM API
 * Handles both single and bulk processing job status checks
 */
class LiteLLMPoller {
    constructor() {
        this.isPolling = false;
        this.pollInterval = 3000; // 3 seconds for LiteLLM API
        this.maxRetries = 5;
        this.retryCount = 0;
        this.activeJobs = new Map(); // Track multiple jobs
        this.backoffMultiplier = 1.5;

        // Use configuration from PHP
        this.config = window.aiAwesomeConfig || {};
        this.isDev = this.config.isDev || false;
        this.isDebug = this.config.isDebug || false;
        this.litellmUrl = this.config.litellmUrl || 'http://localhost:8000';

        // Authentication details
        this.token = this.config.token || '';
        this.uidd = this.config.uidd || '';
        this.licenseKey = this.config.licenseKey || '';
        this.domain = this.config.domain || this.extractDomainFromUrl();

        this.init();
    }

    init() {
        this.logInfo('Initializing LiteLLM Poller');
        this.logInfo(`LiteLLM URL: ${this.litellmUrl}`);
        this.logInfo(`Poll interval: ${this.pollInterval}ms`);

        // Listen for LiteLLM request events
        document.addEventListener('litellm-request-sent', (event) => {
            const { requestId, jobType } = event.detail;
            this.addJob(requestId, jobType);
        });

        // Listen for single post completion (for popup requests)
        document.addEventListener('litellm-single-completed', (event) => {
            const { requestId, data } = event.detail;
            this.handleSingleCompletion(requestId, data);
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
            console.log(`[LiteLLM Poller] ${message}`, data || '');
        }
    }

    logError(message, error = null) {
        console.error(`[LiteLLM Poller ERROR] ${message}`, error || '');
    }

    logWarning(message, data = null) {
        if (this.isDev || this.isDebug) {
            console.warn(`[LiteLLM Poller WARNING] ${message}`, data || '');
        }
    }

    /**
     * Add a job to be polled
     */
    addJob(requestId, jobType = 'bulk') {
        this.activeJobs.set(requestId, {
            type: jobType,
            startTime: Date.now(),
            lastChecked: null,
            retryCount: 0
        });

        this.logInfo(`Added job to polling: ${requestId} (${jobType})`);

        if (!this.isPolling) {
            this.startPolling();
        }
    }

    /**
     * Remove a job from polling
     */
    removeJob(requestId) {
        if (this.activeJobs.has(requestId)) {
            this.activeJobs.delete(requestId);
            this.logInfo(`Removed job from polling: ${requestId}`);

            // Stop polling if no active jobs
            if (this.activeJobs.size === 0) {
                this.stopPolling();
            }
        }
    }

    /**
     * Start polling for all active jobs
     */
    startPolling() {
        if (this.isPolling) return;

        this.isPolling = true;
        this.retryCount = 0;

        this.logInfo('Starting LiteLLM polling...');
        this.poll();
    }

    /**
     * Stop polling
     */
    stopPolling() {
        this.isPolling = false;
        this.logInfo('LiteLLM polling stopped');
    }

    /**
     * Calculate backoff delay for retries
     */
    getBackoffDelay() {
        return this.pollInterval * Math.pow(this.backoffMultiplier, this.retryCount);
    }

    /**
     * Poll all active jobs
     */
    async poll() {
        if (!this.isPolling || this.activeJobs.size === 0) {
            this.stopPolling();
            return;
        }

        const jobPromises = [];

        // Poll each active job
        for (const [requestId, jobInfo] of this.activeJobs) {
            jobPromises.push(this.pollSingleJob(requestId, jobInfo));
        }

        try {
            await Promise.allSettled(jobPromises);
            this.retryCount = 0; // Reset on successful batch
        } catch (error) {
            this.logError('Error during polling batch:', error);
            this.retryCount++;
        }

        // Schedule next poll if still active
        if (this.isPolling && this.activeJobs.size > 0) {
            const delay = this.retryCount > 0 ? this.getBackoffDelay() : this.pollInterval;
            setTimeout(() => this.poll(), delay);
        }
    }

    /**
     * Poll a single job
     */
    async pollSingleJob(requestId, jobInfo) {
        try {
            const response = await this.checkJobStatus(requestId);

            if (response.success && response.data) {
                const status = response.data.status;

                this.logInfo(`Job ${requestId} status: ${status}`, response.data);

                if (status === 'completed') {
                    // Get the results
                    const results = await this.getJobResults(requestId);
                    if (results.success) {
                        this.handleJobCompletion(requestId, results.data, jobInfo);
                    } else {
                        this.handleJobError(requestId, 'Failed to get results', jobInfo);
                    }
                } else if (status === 'failed') {
                    this.handleJobError(requestId, response.data.error || 'Job failed', jobInfo);
                } else {
                    // Still processing, update progress if available
                    if (response.data.progress !== undefined) {
                        this.updateJobProgress(requestId, response.data, jobInfo);
                    }
                }
            } else {
                jobInfo.retryCount++;
                if (jobInfo.retryCount >= this.maxRetries) {
                    this.handleJobError(requestId, 'Max retries reached', jobInfo);
                }
            }

            jobInfo.lastChecked = Date.now();

        } catch (error) {
            this.logError(`Error polling job ${requestId}:`, error);
            jobInfo.retryCount++;

            if (jobInfo.retryCount >= this.maxRetries) {
                this.handleJobError(requestId, error.message, jobInfo);
            }
        }
    }

    /**
     * Check job status via LiteLLM API
     */
    async checkJobStatus(requestId) {
        const url = `${this.litellmUrl}/api/v1/job/${requestId}`;

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'from-domain': this.domain,
            'license-key': this.licenseKey
        };

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }
        if (this.uidd) {
            headers['uidd'] = this.uidd;
        }

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: headers
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            return { success: true, data: data };

        } catch (error) {
            this.logError('Job status check failed:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Get job results via LiteLLM API
     */
    async getJobResults(requestId) {
        const url = `${this.litellmUrl}/api/v1/job/${requestId}/results`;

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'from-domain': this.domain,
            'license-key': this.licenseKey
        };

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }
        if (this.uidd) {
            headers['uidd'] = this.uidd;
        }

        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: headers
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            return { success: true, data: data };

        } catch (error) {
            this.logError('Job results fetch failed:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Handle job completion
     */
    handleJobCompletion(requestId, resultsData, jobInfo) {
        this.logInfo(`Job ${requestId} completed successfully`, resultsData);

        // Remove from active jobs
        this.removeJob(requestId);

        // Transform results to WordPress format
        const transformedResults = this.transformResultsForWordPress(resultsData);

        // Trigger completion event
        const event = new CustomEvent('litellm-job-completed', {
            detail: {
                requestId: requestId,
                jobType: jobInfo.type,
                results: transformedResults,
                processingTime: Date.now() - jobInfo.startTime
            }
        });

        document.dispatchEvent(event);

        // Send to WordPress for storage
        this.sendResultsToWordPress(transformedResults, requestId);
    }

    /**
     * Handle job error
     */
    handleJobError(requestId, errorMessage, jobInfo) {
        this.logError(`Job ${requestId} failed: ${errorMessage}`);

        // Remove from active jobs
        this.removeJob(requestId);

        // Trigger error event
        const event = new CustomEvent('litellm-job-error', {
            detail: {
                requestId: requestId,
                jobType: jobInfo.type,
                error: errorMessage,
                processingTime: Date.now() - jobInfo.startTime
            }
        });

        document.dispatchEvent(event);
    }

    /**
     * Update job progress
     */
    updateJobProgress(requestId, statusData, jobInfo) {
        // Trigger progress event
        const event = new CustomEvent('litellm-job-progress', {
            detail: {
                requestId: requestId,
                jobType: jobInfo.type,
                progress: statusData.progress || 0,
                completed: statusData.completed_items || 0,
                total: statusData.total_items || 0,
                estimated_time: statusData.estimated_time_remaining || null
            }
        });

        document.dispatchEvent(event);
    }

    /**
     * Handle single post completion (for popup requests)
     */
    handleSingleCompletion(requestId, data) {
        this.logInfo(`Single post request ${requestId} completed`, data);

        // Transform and send to WordPress
        const transformedResults = this.transformResultsForWordPress(data);
        this.sendResultsToWordPress(transformedResults, requestId);

        // Trigger completion event
        const event = new CustomEvent('litellm-single-completed-processed', {
            detail: {
                requestId: requestId,
                results: transformedResults
            }
        });

        document.dispatchEvent(event);
    }

    /**
     * Transform LiteLLM results to WordPress format
     */
    transformResultsForWordPress(resultsData) {
        if (!resultsData.results || !Array.isArray(resultsData.results)) {
            return [];
        }

        return resultsData.results.map(result => ({
            post_id: result.post_id,
            content: result.content,
            status: result.status,
            tokens_used: result.tokens_used,
            processing_time: result.processing_time,
            error_message: result.error_message || null
        }));
    }

    /**
     * Send results to WordPress REST API
     */
    async sendResultsToWordPress(results, requestId) {
        try {
            const formData = new FormData();
            formData.append('action', 'ai_process_litellm_results');
            formData.append('request_id', requestId);
            formData.append('results', JSON.stringify(results));
            formData.append('nonce', this.config.nonce || '');

            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                this.logInfo(`Results sent to WordPress for request ${requestId}`);
            } else {
                this.logError(`Failed to send results to WordPress: ${response.status}`);
            }

        } catch (error) {
            this.logError('Error sending results to WordPress:', error);
        }
    }
}

// Initialize the LiteLLM poller when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (window.aiAwesomeConfig && window.aiAwesomeConfig.useLiteLLM) {
        window.litellmPoller = new LiteLLMPoller();
        console.log('✅ LiteLLM Poller initialized');
    }
});