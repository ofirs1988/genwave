/**
 * LiteLLM Streaming Client - Real-time AI content generation
 * Shows live progress and content generation as it happens
 */
class LiteLLMStreamingClient {
    constructor() {
        this.config = window.aiAwesomeConfig || {};
        this.litellmUrl = this.config.litellmUrl || 'http://localhost:8000';
        this.isDev = this.config.isDev || false;
        this.isDebug = this.config.isDebug || false;

        // Authentication details
        this.token = this.config.token || '';
        this.uidd = this.config.uidd || '';
        this.licenseKey = this.config.licenseKey || '';
        this.domain = this.config.domain || this.extractDomainFromUrl();

        // Streaming state
        this.activeStreams = new Map();
        this.currentProgress = {};

        this.init();
    }

    init() {
        // Listen for streaming requests
        document.addEventListener('start-litellm-streaming', (event) => {
            const { requestData, callback, onError, onProgress } = event.detail;
            this.startStreaming(requestData, { callback, onError, onProgress });
        });

        // Create streaming UI elements
        this.createStreamingUI();
    }

    extractDomainFromUrl() {
        return window.location.hostname;
    }

    logInfo(message, data = null) {
        if (this.isDev || this.isDebug) {
            console.log(`[LiteLLM Streaming] ${message}`, data || '');
        }
    }

    logError(message, error = null) {
        console.error(`[LiteLLM Streaming ERROR] ${message}`, error || '');
    }

    /**
     * Create streaming UI elements
     */
    createStreamingUI() {
        // Check if UI already exists
        if (document.getElementById('litellm-streaming-container')) {
            return;
        }

        // Create streaming container
        const container = document.createElement('div');
        container.id = 'litellm-streaming-container';
        container.className = 'litellm-streaming-hidden';
        container.innerHTML = `
            <div class="litellm-streaming-overlay">
                <div class="litellm-streaming-modal">
                    <div class="litellm-streaming-header">
                        <h3>🤖 AI Content Generation</h3>
                        <button class="litellm-streaming-close" onclick="window.litellmStreamingClient.hideStreaming()">×</button>
                    </div>
                    <div class="litellm-streaming-content">
                        <div class="litellm-progress-section">
                            <div class="litellm-progress-bar">
                                <div class="litellm-progress-fill" style="width: 0%"></div>
                            </div>
                            <div class="litellm-progress-text">Preparing...</div>
                            <div class="litellm-progress-stats">
                                <span class="completed">0</span> completed,
                                <span class="failed">0</span> failed,
                                <span class="remaining">0</span> remaining
                            </div>
                        </div>
                        <div class="litellm-posts-container">
                            <!-- Posts will be added here dynamically -->
                        </div>
                        <div class="litellm-log-container">
                            <div class="litellm-log-header">
                                <h4>📋 Generation Log</h4>
                                <button onclick="this.parentElement.nextElementSibling.innerHTML=''">Clear</button>
                            </div>
                            <div class="litellm-log-content"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(container);

        // Add CSS styles
        this.addStreamingStyles();
    }

    /**
     * Add CSS styles for streaming UI
     */
    addStreamingStyles() {
        if (document.getElementById('litellm-streaming-styles')) {
            return;
        }

        const styles = document.createElement('style');
        styles.id = 'litellm-streaming-styles';
        styles.textContent = `
            .litellm-streaming-hidden { display: none; }

            .litellm-streaming-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .litellm-streaming-modal {
                background: white;
                border-radius: 10px;
                width: 90%;
                max-width: 800px;
                max-height: 90vh;
                overflow: hidden;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            }

            .litellm-streaming-header {
                background: #2196F3;
                color: white;
                padding: 15px 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .litellm-streaming-header h3 {
                margin: 0;
                font-size: 18px;
            }

            .litellm-streaming-close {
                background: none;
                border: none;
                color: white;
                font-size: 24px;
                cursor: pointer;
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .litellm-streaming-content {
                padding: 20px;
                max-height: 70vh;
                overflow-y: auto;
            }

            .litellm-progress-section {
                margin-bottom: 20px;
            }

            .litellm-progress-bar {
                width: 100%;
                height: 20px;
                background: #f0f0f0;
                border-radius: 10px;
                overflow: hidden;
                margin-bottom: 10px;
            }

            .litellm-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #4CAF50, #45a049);
                transition: width 0.3s ease;
                position: relative;
            }

            .litellm-progress-fill::after {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
                animation: shimmer 2s infinite;
            }

            @keyframes shimmer {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }

            .litellm-progress-text {
                font-weight: bold;
                text-align: center;
                margin-bottom: 5px;
            }

            .litellm-progress-stats {
                text-align: center;
                font-size: 14px;
                color: #666;
            }

            .litellm-progress-stats .completed { color: #4CAF50; font-weight: bold; }
            .litellm-progress-stats .failed { color: #f44336; font-weight: bold; }
            .litellm-progress-stats .remaining { color: #ff9800; font-weight: bold; }

            .litellm-posts-container {
                margin-bottom: 20px;
            }

            .litellm-post-item {
                border: 1px solid #ddd;
                border-radius: 5px;
                margin-bottom: 10px;
                overflow: hidden;
            }

            .litellm-post-header {
                background: #f5f5f5;
                padding: 10px 15px;
                font-weight: bold;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .litellm-post-status {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                text-transform: uppercase;
            }

            .litellm-post-status.pending { background: #ffc107; color: #000; }
            .litellm-post-status.processing { background: #2196F3; color: white; }
            .litellm-post-status.completed { background: #4CAF50; color: white; }
            .litellm-post-status.failed { background: #f44336; color: white; }

            .litellm-post-content {
                padding: 15px;
                max-height: 200px;
                overflow-y: auto;
            }

            .litellm-post-content-streaming {
                font-family: monospace;
                background: #f8f8f8;
                padding: 10px;
                border-radius: 3px;
                white-space: pre-wrap;
                min-height: 40px;
            }

            .litellm-log-container {
                border-top: 1px solid #ddd;
                padding-top: 20px;
            }

            .litellm-log-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }

            .litellm-log-header h4 {
                margin: 0;
                font-size: 16px;
            }

            .litellm-log-header button {
                background: #ff9800;
                color: white;
                border: none;
                padding: 5px 10px;
                border-radius: 3px;
                cursor: pointer;
                font-size: 12px;
            }

            .litellm-log-content {
                background: #f8f8f8;
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 10px;
                max-height: 200px;
                overflow-y: auto;
                font-family: monospace;
                font-size: 12px;
                line-height: 1.4;
            }

            .litellm-log-entry {
                margin-bottom: 5px;
                padding: 2px 0;
            }

            .litellm-log-entry.info { color: #2196F3; }
            .litellm-log-entry.success { color: #4CAF50; }
            .litellm-log-entry.error { color: #f44336; }
            .litellm-log-entry.warning { color: #ff9800; }

            .litellm-typing-indicator {
                display: inline-block;
                width: 3px;
                height: 12px;
                background: #2196F3;
                animation: blink 1s infinite;
                margin-left: 2px;
            }

            @keyframes blink {
                0%, 50% { opacity: 1; }
                51%, 100% { opacity: 0; }
            }
        `;

        document.head.appendChild(styles);
    }

    /**
     * Start streaming content generation
     */
    async startStreaming(requestData, callbacks = {}) {
        this.logInfo('Starting streaming generation', requestData);

        // Store callbacks for use throughout the streaming process
        this.currentCallbacks = callbacks;

        // Show streaming UI
        this.showStreaming();

        // Initialize progress
        this.currentProgress = {
            total_posts: requestData.posts.length,
            completed_posts: 0,
            failed_posts: 0,
            progress: 0
        };

        // Initialize post items
        this.initializePostItems(requestData.posts);

        // Update progress display
        this.updateProgressDisplay();

        // Add log entry
        this.addLogEntry('info', `Starting generation for ${requestData.posts.length} posts...`);

        // Enrich posts with title and content data from WordPress
        for (let i = 0; i < requestData.posts.length; i++) {
            const post = requestData.posts[i];
            if (!post.title || post.title === `Post ${post.id}`) {
                try {
                    const response = await fetch(this.config.ajaxUrl || '/wp-admin/admin-ajax.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'get_post_data',
                            post_id: post.id,
                            nonce: this.config.nonce || ''
                        })
                    });

                    const result = await response.json();
                    if (result.success && result.data) {
                        requestData.posts[i] = {
                            id: post.id,
                            title: result.data.title || `Post ${post.id}`,
                            content: result.data.content || result.data.excerpt || 'Generate content for this post'
                        };
                    } else {
                        requestData.posts[i] = {
                            id: post.id,
                            title: `Post ${post.id}`,
                            content: 'Generate content for this post'
                        };
                    }
                } catch (error) {
                    console.error(`❌ [LiteLLM Streaming] Error fetching post ${post.id}:`, error);
                    requestData.posts[i] = {
                        id: post.id,
                        title: `Post ${post.id}`,
                        content: 'Generate content for this post'
                    };
                }
            }
        }

        try {
            const url = `${this.litellmUrl}/api/v1/generate-stream`;

            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'from-domain': this.domain,
                'license-key': this.licenseKey
            };

            if (this.token) {
                headers['Authorization'] = `Bearer ${this.token}`;
            }
            if (this.uidd) {
                headers['uidd'] = this.uidd;
            }

            // Start SSE connection
            const response = await fetch(url, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify(requestData)
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            // Process streaming response
            await this.processStreamingResponse(response);

        } catch (error) {
            this.logError('Streaming error:', error);
            this.addLogEntry('error', `Streaming failed: ${error.message}`);
            this.updateProgressText('❌ Generation failed');
        }
    }

    /**
     * Process streaming response
     */
    async processStreamingResponse(response) {
        const reader = response.body.getReader();
        const decoder = new TextDecoder();

        try {
            while (true) {
                const { done, value } = await reader.read();

                if (done) {
                    this.logInfo('Streaming completed');
                    break;
                }

                const chunk = decoder.decode(value, { stream: true });
                const lines = chunk.split('\n');

                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        const dataStr = line.slice(6);
                        if (dataStr.trim()) {
                            try {
                                const data = JSON.parse(dataStr);
                                this.handleStreamingEvent(data);
                            } catch (e) {
                                this.logError('Error parsing streaming data:', e);
                            }
                        }
                    }
                }
            }
        } finally {
            reader.releaseLock();
        }
    }

    /**
     * Handle streaming events
     */
    handleStreamingEvent(data) {
        this.logInfo('Streaming event:', data);

        switch (data.type) {
            case 'status':
                this.handleStatusEvent(data);
                break;
            case 'post_start':
                this.handlePostStartEvent(data);
                break;
            case 'post_content_chunk':
                this.handleContentChunkEvent(data);
                break;
            case 'post_complete':
                this.handlePostCompleteEvent(data);
                break;
            case 'post_error':
                this.handlePostErrorEvent(data);
                break;
            case 'progress':
                this.handleProgressEvent(data);
                break;
            case 'complete':
                this.handleCompleteEvent(data);
                break;
            case 'error':
                this.handleErrorEvent(data);
                break;
        }
    }

    handleStatusEvent(data) {
        this.updateProgressText(data.message);
        this.addLogEntry('info', data.message);
    }

    handlePostStartEvent(data) {
        this.updatePostStatus(data.post_id, 'processing');
        this.addLogEntry('info', `🚀 ${data.message}`);
    }

    handleContentChunkEvent(data) {
        this.updatePostContent(data.post_id, data.accumulated_content, true);
        // Don't log every chunk to avoid spam
    }

    handlePostCompleteEvent(data) {
        this.updatePostStatus(data.post_id, 'completed');
        this.updatePostContent(data.post_id, data.content, false);
        this.currentProgress.completed_posts = data.completed_posts;
        this.currentProgress.failed_posts = data.failed_posts;
        this.currentProgress.progress = data.progress;
        this.updateProgressDisplay();
        this.addLogEntry('success', `✅ ${data.message}`);
    }

    handlePostErrorEvent(data) {
        this.updatePostStatus(data.post_id, 'failed');
        this.updatePostContent(data.post_id, `Error: ${data.error_message}`, false);
        this.currentProgress.completed_posts = data.completed_posts;
        this.currentProgress.failed_posts = data.failed_posts;
        this.currentProgress.progress = data.progress;
        this.updateProgressDisplay();
        this.addLogEntry('error', `❌ ${data.message}`);
    }

    handleProgressEvent(data) {
        this.currentProgress = { ...this.currentProgress, ...data };
        this.updateProgressDisplay();
    }

    handleCompleteEvent(data) {
        this.currentProgress.progress = 100;
        this.updateProgressDisplay();
        this.updateProgressText(`🎉 ${data.message}`);
        this.addLogEntry('success', `🎉 ${data.message}`);

        // Save streaming results to localStorage and redirect to streaming viewer
        if (data.status === 'completed') {
            this.saveStreamingResultsAndRedirect(data);
        }
    }

    /**
     * Save streaming results to localStorage and redirect to streaming viewer
     */
    saveStreamingResultsAndRedirect(data) {
        try {
            // Collect all generated content from posts
            const posts = [];
            const streamingContent = {};

            // Extract content from UI
            document.querySelectorAll('.litellm-post-item').forEach(postItem => {
                const postId = postItem.dataset.postId;
                const contentElement = postItem.querySelector('.litellm-post-content-streaming');
                const content = contentElement.textContent.replace('Waiting for generation...', '').trim();

                if (content && postId) {
                    posts.push(postId);

                    // For now, use the content as description
                    // In a real scenario, you might have title, description, etc.
                    streamingContent.title = `AI Generated Title for Post ${postId}`;
                    streamingContent.description = content;
                    streamingContent.short_description = content.substring(0, 150) + '...';
                }
            });

            // Create streaming results object
            const streamingResults = {
                request_id: 'stream-' + Date.now(),
                results: streamingContent,
                posts: posts,
                completed_at: new Date().toISOString(),
                status: 'completed',
                progress: this.currentProgress
            };

            // Save to localStorage
            localStorage.setItem('streamingResults', JSON.stringify(streamingResults));

            this.addLogEntry('info', '💾 Results saved to localStorage');

            // Call callback if provided (for popup/modal scenarios)
            if (this.currentCallbacks && this.currentCallbacks.callback) {
                try {
                    this.currentCallbacks.callback(streamingResults);
                    this.addLogEntry('success', '✅ Callback executed successfully');
                    return; // Don't redirect if callback is handled
                } catch (error) {
                    this.logError('Callback execution failed', error);
                }
            }

            // Also save to WordPress database
            this.saveToDatabase(streamingResults);

            // Redirect to streaming viewer page (only if no callback)
            setTimeout(() => {
                this.hideStreaming();

                // Navigate to streaming viewer in admin
                const currentUrl = window.location.href;
                let redirectUrl;

                if (currentUrl.includes('wp-admin')) {
                    // We're already in admin, just change the page
                    const baseAdminUrl = currentUrl.split('admin.php')[0] + 'admin.php';
                    redirectUrl = `${baseAdminUrl}?page=gen-wave-plugin-streaming-viewer`;
                } else {
                    // We're on frontend, go to admin
                    redirectUrl = `${window.location.origin}/wp-admin/admin.php?page=gen-wave-plugin-streaming-viewer`;
                }

                this.addLogEntry('success', '🚀 Redirecting to streaming viewer...');

                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 1000);

            }, 2000);

        } catch (error) {
            this.logError('Error saving streaming results:', error);
            this.addLogEntry('error', `❌ Failed to save results: ${error.message}`);

            // Call error callback if provided
            if (this.currentCallbacks && this.currentCallbacks.onError) {
                try {
                    this.currentCallbacks.onError(error);
                } catch (callbackError) {
                    this.logError('Error callback execution failed', callbackError);
                }
            }
        }
    }

    /**
     * Save streaming results to WordPress database
     */
    async saveToDatabase(streamingResults) {
        try {
            this.addLogEntry('info', '💾 Saving to WordPress database...');

            const formData = new FormData();
            formData.append('action', 'save_streaming_results');
            formData.append('nonce', this.config.nonce || window.aiAwesome?.nonce || '');
            formData.append('request_id', streamingResults.request_id);
            formData.append('results', JSON.stringify(streamingResults.results));
            formData.append('posts', JSON.stringify(streamingResults.posts));
            formData.append('completed_at', streamingResults.completed_at);
            formData.append('status', streamingResults.status);
            formData.append('progress', JSON.stringify(streamingResults.progress));
            formData.append('type', 'streaming');
            formData.append('user_id', 1);
            formData.append('provider', 'openai');
            formData.append('model', 'gpt-4');

            const response = await fetch(this.config.ajaxUrl || window.aiAwesome?.ajaxUrl || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.addLogEntry('success', '✅ Saved to database successfully');
            } else {
                this.addLogEntry('warning', `⚠️ Database save failed: ${result.data || 'Unknown error'}`);
            }

        } catch (error) {
            this.logError('Error saving to database:', error);
            this.addLogEntry('error', `❌ Database save error: ${error.message}`);
        }
    }

    handleErrorEvent(data) {
        this.updateProgressText(`❌ ${data.message}`);
        this.addLogEntry('error', `💥 ${data.message}`);
    }

    /**
     * Initialize post items in UI
     */
    initializePostItems(posts) {
        const container = document.querySelector('.litellm-posts-container');
        container.innerHTML = '';

        posts.forEach((post, index) => {
            const postItem = document.createElement('div');
            postItem.className = 'litellm-post-item';
            postItem.dataset.postId = post.id;
            postItem.innerHTML = `
                <div class="litellm-post-header">
                    <span>📄 ${post.title || `Post ${post.id}`}</span>
                    <span class="litellm-post-status pending">Pending</span>
                </div>
                <div class="litellm-post-content">
                    <div class="litellm-post-content-streaming">Waiting for generation...</div>
                </div>
            `;
            container.appendChild(postItem);
        });
    }

    /**
     * Update post status
     */
    updatePostStatus(postId, status) {
        const postItem = document.querySelector(`[data-post-id="${postId}"]`);
        if (postItem) {
            const statusElement = postItem.querySelector('.litellm-post-status');
            statusElement.textContent = status;
            statusElement.className = `litellm-post-status ${status}`;
        }
    }

    /**
     * Update post content
     */
    updatePostContent(postId, content, isStreaming = false) {
        const postItem = document.querySelector(`[data-post-id="${postId}"]`);
        if (postItem) {
            const contentElement = postItem.querySelector('.litellm-post-content-streaming');
            contentElement.textContent = content;

            if (isStreaming) {
                contentElement.innerHTML = content + '<span class="litellm-typing-indicator"></span>';
            }
        }
    }

    /**
     * Update progress display
     */
    updateProgressDisplay() {
        const progressFill = document.querySelector('.litellm-progress-fill');
        const completedSpan = document.querySelector('.litellm-progress-stats .completed');
        const failedSpan = document.querySelector('.litellm-progress-stats .failed');
        const remainingSpan = document.querySelector('.litellm-progress-stats .remaining');

        if (progressFill) {
            progressFill.style.width = `${this.currentProgress.progress}%`;
        }

        if (completedSpan) {
            completedSpan.textContent = this.currentProgress.completed_posts;
        }

        if (failedSpan) {
            failedSpan.textContent = this.currentProgress.failed_posts;
        }

        if (remainingSpan) {
            const remaining = this.currentProgress.total_posts -
                             this.currentProgress.completed_posts -
                             this.currentProgress.failed_posts;
            remainingSpan.textContent = remaining;
        }
    }

    /**
     * Update progress text
     */
    updateProgressText(text) {
        const progressText = document.querySelector('.litellm-progress-text');
        if (progressText) {
            progressText.textContent = text;
        }
    }

    /**
     * Add log entry
     */
    addLogEntry(type, message) {
        const logContent = document.querySelector('.litellm-log-content');
        if (logContent) {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.className = `litellm-log-entry ${type}`;
            logEntry.textContent = `[${timestamp}] ${message}`;
            logContent.appendChild(logEntry);
            logContent.scrollTop = logContent.scrollHeight;
        }
    }

    /**
     * Show streaming UI
     */
    showStreaming() {
        const container = document.getElementById('litellm-streaming-container');
        if (container) {
            container.classList.remove('litellm-streaming-hidden');
        }
    }

    /**
     * Hide streaming UI
     */
    hideStreaming() {
        const container = document.getElementById('litellm-streaming-container');
        if (container) {
            container.classList.add('litellm-streaming-hidden');
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (window.aiAwesomeConfig && window.aiAwesomeConfig.useLiteLLM) {
        window.litellmStreamingClient = new LiteLLMStreamingClient();
    }
});

// Export for global use
window.LiteLLMStreamingClient = LiteLLMStreamingClient;