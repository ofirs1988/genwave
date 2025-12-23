<?php
if (!defined('ABSPATH')) {
    exit;
}

$is_connected = isset($data['uidd']) && !is_null($data['uidd']) && strlen($data['uidd']) > 3;
$has_license = strlen($data['license_key'] ?? '') > 10;
$is_expired = isset($data['license_expired']) && $data['license_expired'] === '1';
$tokens = $data['tokens'] ?? 0;
?>
<section class="gen-wave">
    <div class="gw-dashboard">
        <!-- Header -->
        <div class="gw-header">
            <div class="gw-header-content">
                <div class="gw-logo">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Gen Wave', 'gen-wave'); ?></span>
                </div>
                <?php if ($is_connected && !$is_expired): ?>
                    <div class="gw-status gw-status-connected">
                        <span class="gw-status-dot"></span>
                        <?php esc_html_e('Connected', 'gen-wave'); ?>
                    </div>
                <?php elseif ($is_connected && $is_expired): ?>
                    <div class="gw-status gw-status-expired">
                        <span class="gw-status-dot"></span>
                        <?php esc_html_e('License Expired', 'gen-wave'); ?>
                    </div>
                <?php else: ?>
                    <div class="gw-status gw-status-disconnected">
                        <span class="gw-status-dot"></span>
                        <?php esc_html_e('Not Connected', 'gen-wave'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alerts Container -->
        <div id="gw-alerts">
            <?php if (isset($message) && strlen($message) > 0) : ?>
                <div class="gw-alert gw-alert-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <span><?php echo esc_html($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($is_expired): ?>
                <div class="gw-alert gw-alert-warning">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <div class="gw-alert-content">
                        <strong><?php esc_html_e('License Expired', 'gen-wave'); ?></strong>
                        <p><?php esc_html_e('Your license has expired. Renew now to continue using AI content generation.', 'gen-wave'); ?></p>
                        <div class="gw-alert-buttons">
                            <a href="<?php echo esc_url(GENWAVE_API_URL . '/user/billing'); ?>" target="_blank" class="gw-btn gw-btn-warning gw-btn-sm">
                                <?php esc_html_e('Renew License', 'gen-wave'); ?>
                            </a>
                            <button type="button" id="refresh_license" class="gw-btn gw-btn-outline gw-btn-sm">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M23 4v6h-6"/>
                                    <path d="M1 20v-6h6"/>
                                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                                </svg>
                                <?php esc_html_e('Check Again', 'gen-wave'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Main Content -->
        <div class="gw-content">
            <?php if ($is_connected): ?>
                <!-- Connected State - Show Dashboard -->

                <!-- Welcome Section -->
                <div class="gw-welcome-connected">
                    <div class="gw-check-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                    <div class="gw-welcome-text">
                        <h3><?php esc_html_e('Your account is connected!', 'gen-wave'); ?></h3>
                        <p><?php esc_html_e('You can now generate AI content for your products, pages, and posts.', 'gen-wave'); ?></p>
                    </div>
                </div>

                <!-- Quick Start Guide -->
                <div class="gw-quickstart">
                    <h4><?php esc_html_e('How to use Gen Wave', 'gen-wave'); ?></h4>
                    <div class="gw-quickstart-steps">
                        <div class="gw-step">
                            <div class="gw-step-number">1</div>
                            <div class="gw-step-content">
                                <strong><?php esc_html_e('Go to any product, page, or post', 'gen-wave'); ?></strong>
                                <span><?php esc_html_e('Open the editor for the content you want to enhance', 'gen-wave'); ?></span>
                            </div>
                        </div>
                        <div class="gw-step">
                            <div class="gw-step-number">2</div>
                            <div class="gw-step-content">
                                <strong><?php esc_html_e('Click the Gen Wave button', 'gen-wave'); ?></strong>
                                <span><?php esc_html_e('Look for the AI icon in the editor toolbar', 'gen-wave'); ?></span>
                            </div>
                        </div>
                        <div class="gw-step">
                            <div class="gw-step-number">3</div>
                            <div class="gw-step-content">
                                <strong><?php esc_html_e('Choose what to generate', 'gen-wave'); ?></strong>
                                <span><?php esc_html_e('Select title, description, or other content options', 'gen-wave'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Token Balance Card -->
                <div class="gw-card gw-card-stats">
                    <div class="gw-stat-header">
                        <span class="gw-stat-title"><?php esc_html_e('Your Token Balance', 'gen-wave'); ?></span>
                        <button type="button" id="refresh_tokens" class="gw-btn-icon" title="<?php esc_attr_e('Refresh balance', 'gen-wave'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M23 4v6h-6"/>
                                <path d="M1 20v-6h6"/>
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                            </svg>
                        </button>
                    </div>
                    <div class="gw-stat-value-large" id="token-balance"><?php echo number_format((float)$tokens, 2); ?></div>
                    <div class="gw-stat-hint">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 16v-4"/>
                            <path d="M12 8h.01"/>
                        </svg>
                        <?php esc_html_e('Tokens are used each time you generate AI content', 'gen-wave'); ?>
                    </div>
                    <a href="<?php echo esc_url(GENWAVE_API_URL . '/user/plans'); ?>" target="_blank" class="gw-btn gw-btn-outline gw-btn-sm gw-mt-12">
                        <?php esc_html_e('Buy More Tokens', 'gen-wave'); ?>
                    </a>
                </div>

                <!-- Account Info Card -->
                <div class="gw-card">
                    <div class="gw-card-header">
                        <h3><?php esc_html_e('Account Details', 'gen-wave'); ?></h3>
                    </div>
                    <div class="gw-card-body">
                        <div class="gw-info-row">
                            <span class="gw-info-label"><?php esc_html_e('License Key', 'gen-wave'); ?></span>
                            <span class="gw-info-value gw-license-key">
                                <?php
                                $key = $data['license_key'];
                                echo esc_html(substr($key, 0, 8) . '••••••••' . substr($key, -4));
                                ?>
                            </span>
                        </div>
                        <div class="gw-info-row">
                            <span class="gw-info-label"><?php esc_html_e('Status', 'gen-wave'); ?></span>
                            <span class="gw-info-value">
                                <?php if ($is_expired): ?>
                                    <span class="gw-badge gw-badge-warning"><?php esc_html_e('Expired', 'gen-wave'); ?></span>
                                <?php else: ?>
                                    <span class="gw-badge gw-badge-success"><?php esc_html_e('Active', 'gen-wave'); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <div class="gw-card-footer">
                        <a href="<?php echo esc_url(GENWAVE_API_URL); ?>" target="_blank" class="gw-btn gw-btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                <polyline points="15 3 21 3 21 9"/>
                                <line x1="10" y1="14" x2="21" y2="3"/>
                            </svg>
                            <?php esc_html_e('Manage Account', 'gen-wave'); ?>
                        </a>
                        <button type="button" id="disconnect_account" class="gw-btn gw-btn-outline-danger">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                            <?php esc_html_e('Disconnect', 'gen-wave'); ?>
                        </button>
                    </div>
                </div>

            <?php else: ?>
                <!-- Not Connected State - Show Setup Wizard -->

                <!-- Welcome Section -->
                <div class="gw-welcome">
                    <div class="gw-welcome-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z"/>
                            <path d="M2 17L12 22L22 17"/>
                            <path d="M2 12L12 17L22 12"/>
                        </svg>
                    </div>
                    <h2><?php esc_html_e('Welcome to Gen Wave', 'gen-wave'); ?></h2>
                    <p><?php esc_html_e('Connect your account to unlock AI-powered content generation for your WordPress site.', 'gen-wave'); ?></p>
                </div>

                <!-- Features Preview -->
                <div class="gw-features">
                    <div class="gw-feature">
                        <div class="gw-feature-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </div>
                        <div class="gw-feature-text">
                            <strong><?php esc_html_e('Product Descriptions', 'gen-wave'); ?></strong>
                            <span><?php esc_html_e('Generate compelling WooCommerce product content', 'gen-wave'); ?></span>
                        </div>
                    </div>
                    <div class="gw-feature">
                        <div class="gw-feature-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                        </div>
                        <div class="gw-feature-text">
                            <strong><?php esc_html_e('Blog Posts & Pages', 'gen-wave'); ?></strong>
                            <span><?php esc_html_e('Create engaging content in seconds', 'gen-wave'); ?></span>
                        </div>
                    </div>
                    <div class="gw-feature">
                        <div class="gw-feature-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polygon points="10 8 16 12 10 16 10 8"/>
                            </svg>
                        </div>
                        <div class="gw-feature-text">
                            <strong><?php esc_html_e('One-Click Generation', 'gen-wave'); ?></strong>
                            <span><?php esc_html_e('Simple AI button in every editor', 'gen-wave'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Setup Steps -->
                <div class="gw-setup">
                    <h4><?php esc_html_e('Quick Setup', 'gen-wave'); ?></h4>

                    <div class="gw-setup-steps">
                        <!-- Step 1: Enter License -->
                        <div class="gw-setup-step <?php echo $has_license ? 'completed' : 'active'; ?>">
                            <div class="gw-setup-step-header">
                                <div class="gw-setup-step-number">
                                    <?php if ($has_license): ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                    <?php else: ?>
                                        1
                                    <?php endif; ?>
                                </div>
                                <span class="gw-setup-step-title"><?php esc_html_e('Enter your license key', 'gen-wave'); ?></span>
                            </div>

                            <form method="post" action="" class="gw-form">
                                <?php wp_nonce_field('save_ai_settings', 'ai_settings_nonce'); ?>

                                <div class="gw-form-group">
                                    <div class="gw-input-wrapper">
                                        <input type="hidden" id="hiddenLicenseKey" value="<?php echo esc_attr($data['license_key']); ?>" />
                                        <input type="text"
                                               name="ai_license_key"
                                               id="licenseKey"
                                               class="gw-input"
                                               placeholder="<?php esc_attr_e('XXXX-XXXX-XXXX-XXXX', 'gen-wave'); ?>"
                                               value="<?php echo esc_attr($data['license_key']); ?>" />
                                        <div id="input-loader" class="gw-input-loader" style="display: none;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <p class="gw-input-hint">
                                        <?php esc_html_e('Find your license key in your Gen Wave account dashboard', 'gen-wave'); ?>
                                    </p>
                                </div>

                                <?php if (!$has_license): ?>
                                    <button type="submit" name="save_settings" class="gw-btn gw-btn-primary gw-btn-full">
                                        <?php esc_html_e('Save License Key', 'gen-wave'); ?>
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="save_settings" class="gw-btn gw-btn-outline gw-btn-sm">
                                        <?php esc_html_e('Update Key', 'gen-wave'); ?>
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Step 2: Connect Account -->
                        <?php if ($has_license): ?>
                        <div class="gw-setup-step active">
                            <div class="gw-setup-step-header">
                                <div class="gw-setup-step-number">2</div>
                                <span class="gw-setup-step-title"><?php esc_html_e('Connect your account', 'gen-wave'); ?></span>
                            </div>
                            <p class="gw-setup-step-desc">
                                <?php esc_html_e('Click the button below to securely connect your Gen Wave account. You will be redirected to login and authorize this site.', 'gen-wave'); ?>
                            </p>
                            <button type="button" id="verify_by_login" class="gw-btn gw-btn-primary gw-btn-lg gw-btn-full gw-btn-connect">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                                    <polyline points="10 17 15 12 10 7"/>
                                    <line x1="15" y1="12" x2="3" y2="12"/>
                                </svg>
                                <?php esc_html_e('Connect to Gen Wave', 'gen-wave'); ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Help Section -->
                <div class="gw-help-section">
                    <div class="gw-help-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        <a href="<?php echo esc_url(GENWAVE_API_URL . '/register'); ?>" target="_blank">
                            <?php esc_html_e("Don't have an account? Sign up free", 'gen-wave'); ?>
                        </a>
                    </div>
                    <div class="gw-help-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <a href="<?php echo esc_url(GENWAVE_API_URL . '/support'); ?>" target="_blank">
                            <?php esc_html_e('Need help? Contact support', 'gen-wave'); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pro Upgrade Banner -->
        <div class="gw-pro-banner">
            <div class="gw-pro-content">
                <div class="gw-pro-badge">PRO</div>
                <div class="gw-pro-text">
                    <h4><?php esc_html_e('Unlock More Power', 'gen-wave'); ?></h4>
                    <p><?php esc_html_e('Bulk generation, custom prompts, SEO optimization & more', 'gen-wave'); ?></p>
                </div>
            </div>
            <a href="<?php echo esc_url(GENWAVE_API_URL . '/pro'); ?>" target="_blank" class="gw-pro-btn">
                <?php esc_html_e('Learn More', 'gen-wave'); ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <!-- Footer -->
        <div class="gw-footer">
            <span><?php esc_html_e('Gen Wave', 'gen-wave'); ?> v<?php echo esc_html(defined('GENWAVE_VERSION') ? GENWAVE_VERSION : '1.0.0'); ?></span>
            <a href="<?php echo esc_url(GENWAVE_API_URL . '/support'); ?>" target="_blank"><?php esc_html_e('Support', 'gen-wave'); ?></a>
        </div>
    </div>
</section>

<style>
/* Gen Wave Dashboard Styles */
.gw-dashboard {
    max-width: 600px;
    margin: 20px auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
}

/* Header */
.gw-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px 16px 0 0;
    padding: 20px 24px;
    color: #fff;
}

.gw-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.gw-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
    font-weight: 700;
}

.gw-logo svg {
    opacity: 0.9;
}

/* Status Badge */
.gw-status {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
}

.gw-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    animation: pulse-dot 2s infinite;
}

.gw-status-connected .gw-status-dot { background: #4ade80; }
.gw-status-expired .gw-status-dot { background: #fbbf24; animation: none; }
.gw-status-disconnected .gw-status-dot { background: #f87171; animation: none; }

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.2); }
}

/* Content Area */
.gw-content {
    background: #fff;
    padding: 28px;
    border-left: 1px solid #e5e7eb;
    border-right: 1px solid #e5e7eb;
}

/* Alerts */
#gw-alerts {
    background: #fff;
    border-left: 1px solid #e5e7eb;
    border-right: 1px solid #e5e7eb;
}

.gw-alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px 24px;
    border-bottom: 1px solid #e5e7eb;
}

.gw-alert svg {
    flex-shrink: 0;
    margin-top: 2px;
}

.gw-alert-success {
    background: #ecfdf5;
    color: #065f46;
}

.gw-alert-success svg { stroke: #10b981; }

.gw-alert-warning {
    background: #fffbeb;
    color: #92400e;
}

.gw-alert-warning svg { stroke: #f59e0b; }

.gw-alert-content {
    flex: 1;
}

.gw-alert-content strong {
    display: block;
    margin-bottom: 4px;
}

.gw-alert-content p {
    margin: 0 0 12px 0;
    font-size: 14px;
    opacity: 0.9;
}

.gw-alert-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Welcome Section (Not Connected) */
.gw-welcome {
    text-align: center;
    padding: 10px 0 30px;
}

.gw-welcome-icon {
    width: 72px;
    height: 72px;
    margin: 0 auto 16px;
    border-radius: 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}

.gw-welcome h2 {
    margin: 0 0 8px;
    font-size: 22px;
    font-weight: 700;
    color: #1f2937;
}

.gw-welcome p {
    margin: 0;
    color: #6b7280;
    font-size: 15px;
    line-height: 1.5;
}

/* Welcome Connected */
.gw-welcome-connected {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border-radius: 12px;
    margin-bottom: 20px;
}

.gw-check-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #10b981;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.gw-welcome-text h3 {
    margin: 0 0 2px;
    font-size: 16px;
    font-weight: 600;
    color: #065f46;
}

.gw-welcome-text p {
    margin: 0;
    font-size: 13px;
    color: #047857;
}

/* Quick Start Guide */
.gw-quickstart {
    background: #f9fafb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.gw-quickstart h4 {
    margin: 0 0 16px;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.gw-quickstart-steps {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.gw-step {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.gw-step-number {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.gw-step-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.gw-step-content strong {
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
}

.gw-step-content span {
    font-size: 13px;
    color: #6b7280;
}

/* Features */
.gw-features {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 28px;
}

.gw-feature {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    background: #f9fafb;
    border-radius: 10px;
    transition: all 0.2s;
}

.gw-feature:hover {
    background: #f3f4f6;
}

.gw-feature-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.gw-feature-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.gw-feature-text strong {
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
}

.gw-feature-text span {
    font-size: 13px;
    color: #6b7280;
}

/* Setup Steps */
.gw-setup {
    background: #fff;
    border: 2px solid #e5e7eb;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 24px;
}

.gw-setup h4 {
    margin: 0 0 20px;
    font-size: 16px;
    font-weight: 700;
    color: #1f2937;
}

.gw-setup-steps {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.gw-setup-step {
    padding-left: 0;
}

.gw-setup-step.completed {
    opacity: 0.7;
}

.gw-setup-step-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.gw-setup-step-number {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    flex-shrink: 0;
}

.gw-setup-step.active .gw-setup-step-number {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
}

.gw-setup-step.completed .gw-setup-step-number {
    background: #10b981;
    color: #fff;
}

.gw-setup-step-title {
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
}

.gw-setup-step-desc {
    margin: 0 0 16px;
    padding-left: 40px;
    font-size: 14px;
    color: #6b7280;
    line-height: 1.5;
}

.gw-setup-step .gw-form {
    padding-left: 40px;
}

/* Cards */
.gw-card {
    background: #f9fafb;
    border-radius: 12px;
    margin-bottom: 16px;
    overflow: hidden;
}

.gw-card:last-child {
    margin-bottom: 0;
}

.gw-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
}

.gw-card-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #374151;
}

.gw-card-body {
    padding: 16px 20px;
}

.gw-card-footer {
    padding: 16px 20px;
    background: #fff;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 12px;
}

/* Stats Card */
.gw-card-stats {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    padding: 20px;
    text-align: center;
}

.gw-stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.gw-stat-title {
    font-size: 13px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.gw-stat-value-large {
    font-size: 42px;
    font-weight: 800;
    color: #1f2937;
    line-height: 1;
    margin-bottom: 8px;
}

.gw-stat-hint {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 0;
}

.gw-mt-12 {
    margin-top: 12px;
}

.gw-btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    background: #fff;
    color: #6b7280;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.gw-btn-icon:hover {
    background: #f3f4f6;
    color: #374151;
}

.gw-btn-icon.loading svg {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Info Rows */
.gw-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e5e7eb;
}

.gw-info-row:last-child {
    border-bottom: none;
}

.gw-info-label {
    font-size: 14px;
    color: #6b7280;
}

.gw-info-value {
    font-size: 14px;
    font-weight: 500;
    color: #1f2937;
}

.gw-license-key {
    font-family: 'SF Mono', Monaco, monospace;
    font-size: 13px;
    background: #f3f4f6;
    padding: 4px 10px;
    border-radius: 6px;
}

/* Badges */
.gw-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.gw-badge-success {
    background: #dcfce7;
    color: #166534;
}

.gw-badge-warning {
    background: #fef3c7;
    color: #92400e;
}

/* Form */
.gw-form {
    text-align: left;
}

.gw-form-group {
    margin-bottom: 16px;
}

.gw-form-label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 8px;
}

.gw-input-wrapper {
    position: relative;
}

.gw-input {
    width: 100%;
    padding: 12px 16px;
    font-size: 15px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    transition: all 0.2s;
    box-sizing: border-box;
}

.gw-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.gw-input-hint {
    margin: 8px 0 0;
    font-size: 12px;
    color: #9ca3af;
}

.gw-input-loader {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #667eea;
}

.gw-input-loader svg {
    animation: spin 1s linear infinite;
}

/* Buttons */
.gw-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.gw-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    box-shadow: 0 4px 14px rgba(102, 126, 234, 0.3);
}

.gw-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: #fff;
}

.gw-btn-connect {
    padding: 16px 28px;
    font-size: 16px;
}

.gw-btn-outline {
    background: #fff;
    color: #374151;
    border: 2px solid #e5e7eb;
}

.gw-btn-outline:hover {
    background: #f9fafb;
    border-color: #d1d5db;
    color: #374151;
}

.gw-btn-outline-danger {
    background: #fff;
    color: #dc2626;
    border: 2px solid #fecaca;
}

.gw-btn-outline-danger:hover {
    background: #fef2f2;
    border-color: #f87171;
    color: #dc2626;
}

.gw-btn-warning {
    background: #f59e0b;
    color: #fff;
}

.gw-btn-warning:hover {
    background: #d97706;
    color: #fff;
}

.gw-btn-lg {
    padding: 14px 28px;
    font-size: 15px;
}

.gw-btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

.gw-btn-full {
    width: 100%;
}

.gw-btn-group {
    display: flex;
    gap: 12px;
}

.gw-btn-group .gw-btn {
    flex: 1;
}

.gw-btn.loading {
    opacity: 0.7;
    pointer-events: none;
}

/* Help Section */
.gw-help-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.gw-help-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: #f9fafb;
    border-radius: 8px;
}

.gw-help-item svg {
    color: #9ca3af;
    flex-shrink: 0;
}

.gw-help-item a {
    color: #667eea;
    text-decoration: none;
    font-size: 14px;
}

.gw-help-item a:hover {
    text-decoration: underline;
}

/* Pro Banner */
.gw-pro-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 24px;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-left: 1px solid #fbbf24;
    border-right: 1px solid #fbbf24;
}

.gw-pro-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.gw-pro-badge {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 4px;
    letter-spacing: 0.5px;
}

.gw-pro-text h4 {
    margin: 0 0 2px;
    font-size: 14px;
    font-weight: 600;
    color: #92400e;
}

.gw-pro-text p {
    margin: 0;
    font-size: 12px;
    color: #a16207;
}

.gw-pro-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #fff;
    color: #92400e;
    font-size: 13px;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none;
    border: 1px solid #fbbf24;
    transition: all 0.2s;
    white-space: nowrap;
}

.gw-pro-btn:hover {
    background: #92400e;
    color: #fff;
    border-color: #92400e;
}

/* Footer */
.gw-footer {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-top: none;
    border-radius: 0 0 16px 16px;
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: #6b7280;
}

.gw-footer a {
    color: #667eea;
    text-decoration: none;
}

.gw-footer a:hover {
    text-decoration: underline;
}

/* Action Messages */
.gw-action-message {
    margin-top: 16px;
    padding: 14px 16px;
    border-radius: 10px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.gw-action-message.loading {
    background: #eff6ff;
    color: #1e40af;
}

.gw-action-message.success {
    background: #ecfdf5;
    color: #065f46;
}

.gw-action-message.error {
    background: #fef2f2;
    color: #991b1b;
}

.gw-action-message.warning {
    background: #fffbeb;
    color: #92400e;
}

.gw-action-message.warning a {
    color: #667eea;
    text-decoration: underline;
    margin-left: 4px;
}

.gw-btn.confirming {
    background: #dc2626 !important;
    border-color: #dc2626 !important;
    color: #fff !important;
    box-shadow: 0 4px 14px rgba(220, 38, 38, 0.3);
}

/* Responsive */
@media (max-width: 600px) {
    .gw-dashboard {
        margin: 10px;
    }

    .gw-header {
        padding: 16px 20px;
    }

    .gw-content {
        padding: 20px;
    }

    .gw-btn-group {
        flex-direction: column;
    }

    .gw-card-footer {
        flex-direction: column;
    }

    .gw-card-footer .gw-btn {
        width: 100%;
    }

    .gw-setup-step .gw-form,
    .gw-setup-step-desc {
        padding-left: 0;
    }

    .gw-pro-banner {
        flex-direction: column;
        text-align: center;
        gap: 12px;
    }

    .gw-pro-content {
        flex-direction: column;
        gap: 8px;
    }
}
</style>
