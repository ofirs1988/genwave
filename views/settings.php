<style>
    .position-relative {
        position: relative;
    }

    .spinner-border {
        height: 1.5rem;
        width: 1.5rem;
        border-width: 0.2rem;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }

    .form-control[disabled] {
        background-color: #f8f9fa;
        color: #adb5bd;
    }

    .form-control {
        padding-right: 2.5rem;
    }

    /* Action Message Styles */
    .action-message {
        margin-top: 12px;
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideDown 0.3s ease-out;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-left: 4px solid;
    }

    .action-message i {
        font-size: 16px;
        flex-shrink: 0;
    }

    .action-message.loading {
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        border-left-color: #2196f3;
        color: #1565c0;
    }

    .action-message.success {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        border-left-color: #4caf50;
        color: #2e7d32;
    }

    .action-message.error {
        background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
        border-left-color: #f44336;
        color: #c62828;
    }

    .action-message.warning {
        background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
        border-left-color: #ff9800;
        color: #e65100;
    }

    .action-message a {
        font-weight: 600;
        transition: opacity 0.2s;
    }

    .action-message a:hover {
        opacity: 0.8;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Button loading state */
    .aiaw-btn.loading {
        opacity: 0.6;
        pointer-events: none;
    }
</style>

<section class="gen-wave">
    <div class="wrap container mt-5" style="max-width: 650px;">
        <h1 class="mb-3"><?php esc_html_e('Gen Wave Plugin Settings', 'gen-wave'); ?></h1>

        <?php if (isset($message) && strlen($message) > 0) : ?>
            <div class="alert alert-success" role="alert">
                <?php echo esc_html($message); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field('save_ai_settings', 'ai_settings_nonce'); ?>

            <!-- License Key -->
            <div class="mb-3">
                <label for="licenseKey" class="form-label">
                    <i class="fa-solid fa-key"></i> <?php esc_html_e('License Key', 'gen-wave'); ?>
                </label>
                <div class="position-relative">
                    <input type="hidden" id="hiddenLicenseKey" value="<?php echo esc_attr($data['license_key']); ?>" />
                    <input type="text" name="ai_license_key" id="licenseKey" class="form-control" disabled placeholder="<?php esc_attr_e('Loading...', 'gen-wave'); ?>" />
                    <div id="input-loader" class="spinner-border position-absolute top-50 end-0 translate-middle" style="margin-right: 10px;"></div>
                </div>
            </div>

            <?php if (strlen($data['license_key']) > 10 && isset($data['uidd']) && (is_null($data['uidd']) || strlen($data['uidd']) == 0)): ?>
                <!-- Login Button -->
                <div class="mb-3">
                    <div class="aiaw-default aiaw-btn">
                        <a href="#" id="verify_by_login">
                            <?php esc_html_e('Login has been verified', 'gen-wave'); ?> <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            if (isset($data['uidd'])):
                if (strlen($data['license_key']) > 10 && (!is_null($data['uidd']) && strlen($data['uidd']) > 3)): ?>

                    <!-- Success Message -->
                    <div class="mb-3">
                        <div class="aiaw-success aiaw-btn">
                            <a href="<?php echo esc_url(GENWAVE_API_URL . '/home'); ?>" target="_blank">
                                <i class="fa-solid fa-check-circle"></i> <?php esc_html_e('Successfully connected', 'gen-wave'); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Refresh Tokens -->
                    <div class="mb-3">
                        <div class="aiaw-btn">
                            <a id="refresh_tokens" style="cursor: pointer;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                                    <path d="M12 8V12L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M17 4L20 7L17 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span><?php esc_html_e('Refresh Tokens', 'gen-wave'); ?></span>
                            </a>
                        </div>
                    </div>

                    <!-- Disconnect -->
                    <div class="mb-3">
                        <div class="aiaw-btn aiaw-danger">
                            <a href="#" id="disconnect_account" style="cursor: pointer;">
                                <i class="fa-solid fa-power-off"></i> <span><?php esc_html_e('Disconnect', 'gen-wave'); ?></span>
                            </a>
                        </div>
                    </div>
                <?php
                endif;
            endif;
            ?>

            <!-- Submit Button - Only show when not connected -->
            <?php if (!isset($data['uidd']) || is_null($data['uidd']) || strlen($data['uidd']) < 3): ?>
                <div class="mb-3">
                    <input type="submit" name="save_settings" class="btn aiaw-btn btn-primary"
                           value="<?php echo strlen($data['license_key']) > 10 ? esc_attr__('Update License', 'gen-wave') : esc_attr__('Save License', 'gen-wave'); ?> "/>
                </div>
            <?php endif; ?>
        </form>
    </div>
</section>