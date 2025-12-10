/**
 * Smart Polling System for AI Job Management
 *
 * This intelligent polling system replaces the traditional frontend.js
 * with advanced features:
 * - Adaptive polling intervals based on job activity
 * - Smart detection of active jobs
 * - Memory efficient data management
 * - Error recovery with exponential backoff
 * - Performance monitoring and optimization
 * - Real-time progress synchronization with Laravel webhooks
 */

class SmartAIPoller {
    constructor() {
        // Core configuration
        this.config = window.aiAwesome || window.aiAwesomeConfig || {};
        this.apiUrl = this.config.apiUrl || 'https://account.genwave.ai/api';
        this.domain = this.config.domain || this.extractDomainFromUrl();
        this.nonce = this.config.nonce || '';

        // Polling state management
        this.isActive = false;
        this.currentInterval = 5000; // Start with 5 seconds
        this.baseInterval = 5000;
        this.maxInterval = 60000; // Max 1 minute
        this.intervalId = null;

        // Performance tracking
        this.stats = {
            totalPolls: 0,
            successfulPolls: 0,
            errorPolls: 0,
            activeJobsDetected: 0,
            lastActiveJobTime: null,
            averageResponseTime: 0,
            responseTimeSamples: []
        };

        // Error handling
        this.consecutiveErrors = 0;
        this.maxConsecutiveErrors = 5;
        this.retryBackoffMultiplier = 1.5;

        // Job state tracking
        this.activeJobs = new Map();
        this.lastJobCheck = null;
        this.hasActiveJobs = false;

        // Memory management
        this.maxStoredResults = 100;
        this.cleanupInterval = 300000; // 5 minutes
        this.lastCleanup = Date.now();

        // Debug mode
        this.debugMode = this.config.isDev || this.config.isDebug || false;

        this.init();
    }

    /**
     * Initialize the smart polling system
     */
    init() {
        this.log('🚀 Smart AI Poller initialized');
        this.log(`Configuration: Domain=${this.domain}, API=${this.apiUrl}`);
        this.log(`Debug mode: ${this.debugMode ? 'ENABLED' : 'DISABLED'}`);

        // Check for existing active jobs on startup
        this.performInitialCheck();

        // Listen for new AI requests
        this.setupEventListeners();

        // Setup periodic cleanup
        this.setupCleanupScheduler();

        // Start monitoring for active jobs
        this.startActiveJobMonitoring();
    }

    /**
     * Setup event listeners for AI request events
     */
    setupEventListeners() {
        // Listen for new AI requests
        document.addEventListener('ai-request-sent', (event) => {
            this.log('🎯 New AI request detected', event.detail);
            this.onNewRequestDetected(event.detail);
        });

        // Listen for manual refresh requests
        document.addEventListener('smart-poller-refresh', () => {
            this.log('🔄 Manual refresh requested');
            this.performImmediateCheck();
        });

        // Listen for page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.onPageHidden();
            } else {
                this.onPageVisible();
            }
        });

        // Listen for progress updates from Laravel webhooks
        this.setupWebhookListener();
    }

    /**
     * Setup webhook listener for real-time progress updates
     */
    setupWebhookListener() {
        // Create a hidden iframe to listen for Laravel webhook postMessages
        if (window.addEventListener) {
            window.addEventListener('message', (event) => {
                if (event.origin === this.apiUrl.replace('/api', '')) {
                    this.handleWebhookUpdate(event.data);
                }
            }, false);
        }
    }

    /**
     * Handle webhook updates from Laravel
     */
    handleWebhookUpdate(data) {
        if (data && data.type === 'progress_update') {
            this.log('📡 Webhook progress update received', data);

            const { request_id, progress, stage, status } = data;

            // Update local job state
            if (this.activeJobs.has(request_id)) {
                const job = this.activeJobs.get(request_id);
                job.progress = progress;
                job.stage = stage;
                job.status = status;
                job.lastUpdate = Date.now();

                // Dispatch event for UI components
                this.dispatchProgressEvent(request_id, { progress, stage, status });
            }

            // If job completed or failed, schedule a check
            if (['completed', 'failed', 'cancelled'].includes(status)) {
                setTimeout(() => this.checkActiveJobs(), 1000);
            }
        }
    }

    /**
     * Perform initial check for active jobs
     */
    async performInitialCheck() {
        this.log('🔍 Performing initial active jobs check');

        try {
            const hasActive = await this.checkForActiveJobs();
            if (hasActive) {
                this.log('✅ Active jobs detected on startup - beginning polling');
                this.startPolling();
            } else {
                this.log('📭 No active jobs found - entering monitoring mode');
                this.startActiveJobMonitoring();
            }
        } catch (error) {
            this.log('❌ Initial check failed:', error);
            // Fallback to monitoring mode
            this.startActiveJobMonitoring();
        }
    }

    /**
     * Check for active jobs in WordPress database
     */
    async checkForActiveJobs() {
        const startTime = Date.now();

        try {
            const response = await fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'check_active_jobs',
                    nonce: this.nonce,
                    quick_check: '1' // Fast check mode
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            const responseTime = Date.now() - startTime;

            // Update performance stats
            this.updatePerformanceStats(responseTime, true);

            if (data.success) {
                const hasActive = data.data.hasActiveJobs || false;
                const activeCount = data.data.activeCount || 0;

                this.hasActiveJobs = hasActive;
                this.lastJobCheck = Date.now();

                if (hasActive) {
                    this.stats.activeJobsDetected++;
                    this.stats.lastActiveJobTime = Date.now();
                    this.log(`📊 Found ${activeCount} active jobs`);
                } else {
                    this.log('📭 No active jobs found');
                }

                return hasActive;
            } else {
                throw new Error(data.data || 'Unknown error checking active jobs');
            }
        } catch (error) {
            this.updatePerformanceStats(Date.now() - startTime, false);
            this.log('❌ Error checking active jobs:', error);
            throw error;
        }
    }

    /**
     * Start active job monitoring (low frequency checks)
     */
    startActiveJobMonitoring() {
        if (this.isActive) return; // Already monitoring

        this.isActive = true;
        this.currentInterval = 30000; // 30 seconds for monitoring

        this.log(`🔍 Starting active job monitoring (${this.currentInterval}ms intervals)`);

        this.intervalId = setInterval(async () => {
            try {
                const hasActive = await this.checkForActiveJobs();
                if (hasActive) {
                    this.log('🎯 Active jobs detected - switching to active polling');
                    this.stopCurrentInterval();
                    this.startPolling();
                }

                // Perform cleanup if needed
                this.performCleanupIfNeeded();

            } catch (error) {
                this.handlePollingError(error);
            }
        }, this.currentInterval);
    }

    /**
     * Start active polling for job results
     */
    startPolling() {
        if (this.isActive && this.currentInterval === this.baseInterval) return; // Already polling

        this.stopCurrentInterval();
        this.isActive = true;
        this.currentInterval = this.baseInterval;
        this.consecutiveErrors = 0;

        this.log(`🚀 Starting active polling (${this.currentInterval}ms intervals)`);

        this.intervalId = setInterval(async () => {
            await this.performActivePoll();
        }, this.currentInterval);
    }

    /**
     * Perform active polling check
     */
    async performActivePoll() {
        this.stats.totalPolls++;
        const startTime = Date.now();

        try {
            // First check if there are still active jobs
            const hasActive = await this.checkForActiveJobs();

            if (!hasActive) {
                this.log('📭 No more active jobs - switching to monitoring mode');
                this.stopCurrentInterval();
                this.startActiveJobMonitoring();
                return;
            }

            // Poll Laravel for completed results
            await this.pollLaravelResults();

            // Update performance stats
            this.updatePerformanceStats(Date.now() - startTime, true);
            this.consecutiveErrors = 0;

        } catch (error) {
            this.handlePollingError(error);
        }

        // Adaptive interval adjustment
        this.adjustPollingInterval();
    }

    /**
     * Poll Laravel for completed results
     */
    async pollLaravelResults() {
        try {
            const response = await fetch(`${this.apiUrl}/bulk-poll-results/${this.domain}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'from-domain': this.config.domain || 'wp.local'
                }
            });

            if (!response.ok) {
                throw new Error(`Laravel API error: ${response.status}`);
            }

            const data = await response.json();

            if (data.success && data.has_results) {
                this.log(`📦 Received ${data.results.length} completed jobs from Laravel`);
                await this.processLaravelResults(data.results);

                // Update token balance if provided
                if (data.token_balance !== undefined) {
                    this.dispatchTokenBalanceUpdate(data.token_balance);
                }
            } else {
                this.log('📭 No new results from Laravel');
            }

        } catch (error) {
            this.log('❌ Error polling Laravel:', error);
            throw error;
        }
    }

    /**
     * Process results received from Laravel
     */
    async processLaravelResults(results) {
        for (const result of results) {
            try {
                await this.processSingleResult(result);
            } catch (error) {
                this.log(`❌ Error processing result ${result.id}:`, error);
            }
        }
    }

    /**
     * Process a single result from Laravel
     */
    async processSingleResult(result) {
        this.log(`📝 Processing result for job ${result.id}`);

        try {
            // Send result to WordPress for saving
            const response = await fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'ai_awesome_handle_response',
                    nonce: this.nonce,
                    response: JSON.stringify(result)
                })
            });

            const data = await response.json();

            if (data.success) {
                this.log(`✅ Result ${result.id} saved to WordPress successfully`);

                // Mark job as completed in Laravel
                await this.markJobAsDelivered(result.id);

                // Dispatch success event
                this.dispatchResultProcessedEvent(result, true);
            } else {
                throw new Error(data.data || 'WordPress save failed');
            }

        } catch (error) {
            this.log(`❌ Failed to process result ${result.id}:`, error);
            this.dispatchResultProcessedEvent(result, false, error.message);
            throw error;
        }
    }

    /**
     * Mark job as delivered in Laravel
     */
    async markJobAsDelivered(jobId) {
        try {
            const response = await fetch(`${this.apiUrl}/mark-job-delivered`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'from-domain': this.config.domain || 'wp.local'
                },
                body: JSON.stringify({ job_id: jobId })
            });

            if (response.ok) {
                this.log(`✅ Job ${jobId} marked as delivered in Laravel`);
            } else {
                this.log(`⚠️ Failed to mark job ${jobId} as delivered (non-critical)`);
            }
        } catch (error) {
            this.log(`⚠️ Error marking job ${jobId} as delivered:`, error);
        }
    }

    /**
     * Handle polling errors with exponential backoff
     */
    handlePollingError(error) {
        this.consecutiveErrors++;
        this.stats.errorPolls++;

        this.log(`❌ Polling error (${this.consecutiveErrors}/${this.maxConsecutiveErrors}):`, error);

        if (this.consecutiveErrors >= this.maxConsecutiveErrors) {
            this.log('🛑 Too many consecutive errors - stopping polling');
            this.stopPolling();
            this.dispatchErrorEvent('Too many consecutive polling errors', error);
            return;
        }

        // Exponential backoff
        const newInterval = Math.min(
            this.currentInterval * this.retryBackoffMultiplier,
            this.maxInterval
        );

        if (newInterval !== this.currentInterval) {
            this.log(`⏰ Increasing polling interval to ${newInterval}ms due to errors`);
            this.adjustInterval(newInterval);
        }
    }

    /**
     * Adjust polling interval dynamically
     */
    adjustPollingInterval() {
        const timeSinceLastActive = Date.now() - (this.stats.lastActiveJobTime || Date.now());
        const avgResponseTime = this.stats.averageResponseTime;

        let newInterval = this.baseInterval;

        // Increase interval if no recent activity
        if (timeSinceLastActive > 60000) { // 1 minute
            newInterval = Math.min(this.baseInterval * 2, this.maxInterval);
        }

        // Adjust based on response time
        if (avgResponseTime > 2000) { // Slow responses
            newInterval = Math.min(newInterval * 1.2, this.maxInterval);
        } else if (avgResponseTime < 500) { // Fast responses
            newInterval = Math.max(newInterval * 0.8, this.baseInterval);
        }

        if (newInterval !== this.currentInterval) {
            this.log(`⏰ Adjusting polling interval from ${this.currentInterval}ms to ${newInterval}ms`);
            this.adjustInterval(newInterval);
        }
    }

    /**
     * Adjust the current polling interval
     */
    adjustInterval(newInterval) {
        this.currentInterval = newInterval;

        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = setInterval(async () => {
                await this.performActivePoll();
            }, this.currentInterval);
        }
    }

    /**
     * Update performance statistics
     */
    updatePerformanceStats(responseTime, success) {
        if (success) {
            this.stats.successfulPolls++;
        }

        // Update response time average
        this.stats.responseTimeSamples.push(responseTime);
        if (this.stats.responseTimeSamples.length > 20) {
            this.stats.responseTimeSamples.shift(); // Keep only last 20 samples
        }

        this.stats.averageResponseTime = this.stats.responseTimeSamples.reduce((a, b) => a + b, 0) / this.stats.responseTimeSamples.length;
    }

    /**
     * Event dispatchers
     */
    dispatchProgressEvent(requestId, progressData) {
        document.dispatchEvent(new CustomEvent('ai-progress-update', {
            detail: { requestId, ...progressData }
        }));
    }

    dispatchResultProcessedEvent(result, success, error = null) {
        document.dispatchEvent(new CustomEvent('ai-result-processed', {
            detail: { result, success, error }
        }));
    }

    dispatchTokenBalanceUpdate(balance) {
        document.dispatchEvent(new CustomEvent('ai-token-balance-update', {
            detail: { balance }
        }));
    }

    dispatchErrorEvent(message, error) {
        document.dispatchEvent(new CustomEvent('ai-polling-error', {
            detail: { message, error }
        }));
    }

    /**
     * Handle new request detection
     */
    onNewRequestDetected(requestData) {
        this.log('🎯 New AI request detected - ensuring polling is active');

        // Add to active jobs tracking
        if (requestData.request_id) {
            this.activeJobs.set(requestData.request_id, {
                id: requestData.request_id,
                startTime: Date.now(),
                status: 'pending',
                progress: 0
            });
        }

        // Ensure we're in active polling mode
        if (!this.isActive || this.currentInterval > this.baseInterval) {
            this.startPolling();
        }
    }

    /**
     * Handle page visibility changes
     */
    onPageHidden() {
        this.log('👁️ Page hidden - reducing polling frequency');
        if (this.isActive) {
            this.adjustInterval(Math.min(this.currentInterval * 2, this.maxInterval));
        }
    }

    onPageVisible() {
        this.log('👁️ Page visible - restoring polling frequency');
        if (this.isActive && this.hasActiveJobs) {
            this.adjustInterval(this.baseInterval);
        }
    }

    /**
     * Perform immediate check (for manual refresh)
     */
    async performImmediateCheck() {
        this.log('🔄 Performing immediate check');
        try {
            await this.performActivePoll();
        } catch (error) {
            this.log('❌ Immediate check failed:', error);
        }
    }

    /**
     * Memory management and cleanup
     */
    setupCleanupScheduler() {
        setInterval(() => {
            this.performCleanupIfNeeded();
        }, this.cleanupInterval);
    }

    performCleanupIfNeeded() {
        const now = Date.now();
        if (now - this.lastCleanup > this.cleanupInterval) {
            this.performCleanup();
            this.lastCleanup = now;
        }
    }

    performCleanup() {
        this.log('🧹 Performing memory cleanup');

        // Clean old active jobs
        const cutoffTime = Date.now() - (24 * 60 * 60 * 1000); // 24 hours
        for (const [key, job] of this.activeJobs.entries()) {
            if (job.startTime < cutoffTime) {
                this.activeJobs.delete(key);
            }
        }

        // Limit response time samples
        if (this.stats.responseTimeSamples.length > 20) {
            this.stats.responseTimeSamples = this.stats.responseTimeSamples.slice(-20);
        }

        this.log(`🧹 Cleanup completed - ${this.activeJobs.size} active jobs remaining`);
    }

    /**
     * Stop polling
     */
    stopPolling() {
        this.stopCurrentInterval();
        this.isActive = false;
        this.log('🛑 Polling stopped');
    }

    stopCurrentInterval() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }

    /**
     * Extract domain from current URL
     */
    extractDomainFromUrl() {
        return window.location.hostname;
    }

    /**
     * Logging utility
     */
    log(message, ...args) {
        if (this.debugMode) {
            const timestamp = new Date().toISOString();
            console.log(`[SmartPoller ${timestamp}] ${message}`, ...args);
        }
    }

    /**
     * Get current statistics
     */
    getStats() {
        return {
            ...this.stats,
            isActive: this.isActive,
            currentInterval: this.currentInterval,
            hasActiveJobs: this.hasActiveJobs,
            activeJobsCount: this.activeJobs.size,
            consecutiveErrors: this.consecutiveErrors
        };
    }

    /**
     * Public API methods
     */
    start() {
        if (!this.isActive) {
            this.performInitialCheck();
        }
    }

    stop() {
        this.stopPolling();
    }

    forceRefresh() {
        this.performImmediateCheck();
    }

    getActiveJobs() {
        return Array.from(this.activeJobs.values());
    }
}

// Initialize smart poller when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if not already exists
    if (!window.smartAIPoller) {
        window.smartAIPoller = new SmartAIPoller();

        // Expose public API
        window.aiAwesome = window.aiAwesome || {};
        window.aiAwesome.poller = window.smartAIPoller;

        console.log('🚀 Smart AI Poller initialized and ready');
    }
});

// Handle page unload
window.addEventListener('beforeunload', () => {
    if (window.smartAIPoller) {
        window.smartAIPoller.stop();
    }
});