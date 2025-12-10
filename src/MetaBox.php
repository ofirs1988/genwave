<?php

namespace GenWavePlugin;

class MetaBox {

    public function __construct() {
        // Only register metabox if Gen Wave Pro is NOT active
        if (!$this->is_pro_active()) {
            add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
            add_action('save_post', [$this, 'save_meta_box_data']);
        }
    }

    /**
     * Check if Gen Wave Pro plugin is active
     */
    private function is_pro_active() {
        return defined('GEN_WAVE_PRO_VERSION');
    }

    /**
     * Register meta boxes for posts and products
     */
    public function register_meta_boxes() {
        // Add metabox for posts
        add_meta_box(
            'genwave_generate_box',
            __('Gen Wave - AI Content Generation', 'gen-wave'),
            [$this, 'render_meta_box'],
            'post',
            'side',
            'high'
        );

        // Add metabox for WooCommerce products if WooCommerce is active
        if (class_exists('WooCommerce')) {
            add_meta_box(
                'genwave_generate_box',
                __('Gen Wave - AI Content Generation', 'gen-wave'),
                [$this, 'render_meta_box'],
                'product',
                'side',
                'high'
            );
        }
    }

    /**
     * Render the meta box content
     */
    public function render_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('genwave_meta_box_nonce', 'genwave_meta_box_nonce_field');

        // Check if user is connected
        $uidd = \GenWavePlugin\Global\Config::get('uidd');
        $is_connected = !empty($uidd) && strlen($uidd) > 3;

        // Get current post type
        $post_type = get_post_type($post);
        $post_type_label = $post_type === 'product' ? __('Product', 'gen-wave') : __('Post', 'gen-wave');

        // Get saved generation method
        $generation_method = get_post_meta($post->ID, '_genwave_generation_method', true);
        if (empty($generation_method)) {
            $generation_method = 'title'; // Default to title
        }

        // If not connected, show connection required message
        if (!$is_connected) {
            ?>
            <div class="genwave-connection-required" style="padding: 20px 10px; text-align: center;">
                <div style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding: 30px 20px; border-radius: 10px; border: 2px solid #e2e8f0;">
                    <div style="font-size: 48px; margin-bottom: 15px;">🔒</div>
                    <h3 style="margin: 0 0 10px 0; color: #2d3748; font-size: 16px;"><?php esc_html_e('Connection Required', 'gen-wave'); ?></h3>
                    <p style="margin: 0 0 20px 0; color: #4a5568; font-size: 13px; line-height: 1.6;">
                        <?php esc_html_e('Please connect your Gen Wave account to use AI content generation.', 'gen-wave'); ?>
                    </p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gen-wave-plugin-settings')); ?>"
                       class="button button-primary"
                       style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 10px 24px; text-decoration: none; border-radius: 6px; font-weight: 600; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);">
                        <span class="dashicons dashicons-admin-network" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Connect Account', 'gen-wave'); ?>
                    </a>
                </div>
            </div>
            <?php
            return; // Stop rendering the rest of the metabox
        }

        ?>
        <div class="genwave-metabox-wrapper" style="padding: 10px 0;">
            <!-- Compact View (Default) -->
            <div class="genwave-metabox-actions" style="margin-bottom: 15px;">
                <button type="button"
                        id="genwave-generate-btn"
                        class="genwave-modern-button">
                    <span class="genwave-button-content">
                        <span class="genwave-button-icon">✨</span>
                        <span class="genwave-button-text"><?php esc_html_e('Generate AI Content', 'gen-wave'); ?></span>
                        <span class="genwave-button-shine"></span>
                    </span>
                </button>
                <button type="button"
                        id="genwave-toggle-options"
                        class="genwave-toggle-button">
                    <span class="dashicons dashicons-arrow-down-alt2" id="genwave-toggle-icon"></span>
                    <span id="genwave-toggle-text"><?php esc_html_e('Show Options', 'gen-wave'); ?></span>
                </button>
            </div>

            <!-- Advanced Options (Hidden by default) -->
            <div id="genwave-advanced-options" style="display: none;">
                <div class="genwave-metabox-info" style="background: #f0f0f1; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                    <p style="margin: 0; font-size: 12px; color: #646970;">
                        <?php
                        /* translators: %s: post type name (e.g., "post", "product") */
                        printf(esc_html__('Use Gen Wave to automatically generate content for this %s.', 'gen-wave'), esc_html(strtolower($post_type_label)));
                        ?>
                    </p>
                </div>

            <!-- Language Selection -->
            <div class="genwave-language-selection" style="margin-bottom: 15px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                <label for="genwave_language" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px;">
                    <span class="dashicons dashicons-translation" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                    <?php esc_html_e('Language:', 'gen-wave'); ?>
                </label>
                <select id="genwave_language" name="genwave_language" style="width: 100%; padding: 6px;">
                    <option value=""><?php esc_html_e('Loading languages...', 'gen-wave'); ?></option>
                </select>
            </div>

            <!-- Content Length Selection -->
            <div class="genwave-length-selection" style="margin-bottom: 15px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                <label for="genwave_length" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px;">
                    <span class="dashicons dashicons-editor-alignleft" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                    <?php esc_html_e('Content Length:', 'gen-wave'); ?>
                </label>
                <select id="genwave_length" name="genwave_length" style="width: 100%; padding: 6px;">
                    <option value="500"><?php esc_html_e('Short (~500 characters)', 'gen-wave'); ?></option>
                    <option value="1000" selected><?php esc_html_e('Medium (~1000 characters)', 'gen-wave'); ?></option>
                </select>
            </div>

            <!-- Instructions Field (for title and description generation) -->
            <div id="genwave-instructions-field" class="genwave-instructions-field" style="margin-bottom: 15px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px; display: none;">
                <label for="genwave_instructions" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px;">
                    <span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                    <?php esc_html_e('Instructions for AI:', 'gen-wave'); ?>
                </label>
                <textarea id="genwave_instructions"
                          name="genwave_instructions"
                          maxlength="1000"
                          rows="4"
                          placeholder="<?php esc_attr_e('Enter instructions for the AI to generate content... (max 1000 characters)', 'gen-wave'); ?>"
                          style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; resize: vertical;"></textarea>
                <div style="text-align: right; font-size: 11px; color: #666; margin-top: 5px;">
                    <span id="genwave-char-count">0</span>/1000 <?php esc_html_e('characters', 'gen-wave'); ?>
                </div>
            </div>

            <!-- Generation Method Selection -->
            <div class="genwave-generation-method" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                <p style="margin: 0 0 10px 0; font-weight: 600; font-size: 13px;">
                    <span class="dashicons dashicons-admin-settings" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                    <?php esc_html_e('Generation Method:', 'gen-wave'); ?>
                </p>

                <label style="display: block; margin-bottom: 10px; cursor: pointer;">
                    <input type="radio"
                           name="genwave_generation_method"
                           value="title"
                           <?php checked($generation_method, 'title'); ?>
                           style="margin-right: 8px;">
                    <strong><?php esc_html_e('Generate Title', 'gen-wave'); ?></strong>
                    <span style="display: block; margin-left: 24px; font-size: 12px; color: #646970; margin-top: 4px;">
                        <?php
                        $title_desc = $post_type === 'product'
                            ? __('Create product title using AI', 'gen-wave')
                            : __('Create post title using AI', 'gen-wave');
                        echo esc_html($title_desc);
                        ?>
                    </span>
                </label>

                <?php if ($post_type === 'product'): ?>
                <label style="display: block; margin-bottom: 10px; cursor: pointer;">
                    <input type="radio"
                           name="genwave_generation_method"
                           value="short_description"
                           <?php checked($generation_method, 'short_description'); ?>
                           style="margin-right: 8px;">
                    <strong><?php esc_html_e('Generate Short Description', 'gen-wave'); ?></strong>
                    <span style="display: block; margin-left: 24px; font-size: 12px; color: #646970; margin-top: 4px;">
                        <?php esc_html_e('Create product short description using AI', 'gen-wave'); ?>
                    </span>
                </label>
                <?php endif; ?>

                <label style="display: block; cursor: pointer;">
                    <input type="radio"
                           name="genwave_generation_method"
                           value="description"
                           <?php checked($generation_method, 'description'); ?>
                           style="margin-right: 8px;">
                    <strong><?php esc_html_e('Generate Description', 'gen-wave'); ?></strong>
                    <span style="display: block; margin-left: 24px; font-size: 12px; color: #646970; margin-top: 4px;">
                        <?php
                        $desc_text = $post_type === 'product'
                            ? __('Create full product description using AI', 'gen-wave')
                            : __('Create full post content using AI', 'gen-wave');
                        echo esc_html($desc_text);
                        ?>
                    </span>
                </label>
            </div>
            </div><!-- End Advanced Options -->

            <!-- Status Message Container -->
            <div id="genwave-status-container" style="margin-top: 12px;"></div>

            <div class="genwave-upgrade-notice" style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                <p style="margin: 0; font-size: 12px; color: #856404;">
                    <strong><?php esc_html_e('Note:', 'gen-wave'); ?></strong> <?php
                    printf(
                        /* translators: %s: link to Gen Wave Pro */
                        esc_html__('For advanced features like bulk generation, multiple AI providers, and real-time streaming, upgrade to %s.', 'gen-wave'),
                        '<a href="https://genwave.ai" target="_blank" style="color: #856404; text-decoration: underline;">' . esc_html__('Gen Wave Pro', 'gen-wave') . '</a>'
                    ); ?>
                </p>
            </div>
        </div>

        <!-- Instructions Help Modal -->
        <div id="genwave-instructions-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6);">
            <div class="genwave-modal-content" style="background-color: #fff; margin: 10% auto; padding: 0; border-radius: 8px; width: 80%; max-width: 600px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
                <!-- Modal Header -->
                <div style="padding: 20px; border-bottom: 1px solid #ddd; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px 8px 0 0;">
                    <h2 style="margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                        <span class="dashicons dashicons-info" style="font-size: 24px;"></span>
                        <?php esc_html_e('Instructions Required', 'gen-wave'); ?>
                    </h2>
                    <span class="genwave-instructions-close" style="float: right; margin-top: -30px; font-size: 28px; font-weight: bold; color: white; cursor: pointer; opacity: 0.8;">&times;</span>
                </div>

                <!-- Modal Body -->
                <div style="padding: 25px;">
                    <p style="font-size: 14px; line-height: 1.6; margin-bottom: 15px;">
                        <?php esc_html_e('To generate', 'gen-wave'); ?> <strong id="genwave-help-type"><?php esc_html_e('content', 'gen-wave'); ?></strong>, <?php esc_html_e('please provide instructions for the AI.', 'gen-wave'); ?>
                    </p>

                    <div style="background: #f0f6fc; padding: 15px; border-radius: 6px; border-left: 4px solid #667eea; margin-bottom: 15px;">
                        <p style="margin: 0 0 10px 0; font-weight: 600; font-size: 13px;">
                            <span class="dashicons dashicons-lightbulb" style="color: #667eea;"></span>
                            <?php esc_html_e('Examples:', 'gen-wave'); ?>
                        </p>
                        <ul style="margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.8;">
                            <li><?php esc_html_e('"Write a catchy title about sustainable gardening tips"', 'gen-wave'); ?></li>
                            <li><?php esc_html_e('"Create a professional description for a WordPress development service"', 'gen-wave'); ?></li>
                            <li><?php esc_html_e('"Generate an engaging product description for organic coffee beans"', 'gen-wave'); ?></li>
                            <li><?php esc_html_e('"Write content about the benefits of meditation for beginners"', 'gen-wave'); ?></li>
                        </ul>
                    </div>

                    <p style="font-size: 13px; color: #666; margin: 0;">
                        <strong><?php esc_html_e('Tip:', 'gen-wave'); ?></strong> <?php esc_html_e('The more specific your instructions, the better the AI-generated content will be.', 'gen-wave'); ?>
                    </p>
                </div>

                <!-- Modal Footer -->
                <div style="padding: 20px; border-top: 1px solid #ddd; text-align: right; background: #f9f9f9; border-radius: 0 0 8px 8px;">
                    <button type="button" class="button button-primary genwave-instructions-close" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 8px 20px;">
                        <?php esc_html_e('Got it!', 'gen-wave'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Generated Content Modal -->
        <div id="genwave-content-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6);">
            <!-- Confetti Container -->
            <div id="genwave-confetti-container" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; overflow: hidden;"></div>

            <div class="genwave-modal-content" style="background-color: #fff; margin: 5% auto; padding: 0; border-radius: 8px; width: 80%; max-width: 800px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); position: relative; z-index: 2;">
                <!-- Modal Header -->
                <div style="padding: 20px; border-bottom: 1px solid #ddd; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px 8px 0 0;">
                    <h2 style="margin: 0; font-size: 20px; display: flex; align-items: center; gap: 10px;">
                        <span class="dashicons dashicons-yes-alt" style="font-size: 24px;"></span>
                        <?php esc_html_e('Generated Content', 'gen-wave'); ?>
                    </h2>
                    <span class="genwave-modal-close" style="float: right; margin-top: -30px; font-size: 28px; font-weight: bold; color: white; cursor: pointer; opacity: 0.8;">&times;</span>
                </div>

                <!-- Modal Body -->
                <div style="padding: 25px; max-height: 500px; overflow-y: auto;">
                    <!-- Token Usage Info -->
                    <div id="genwave-token-info" style="display: flex; gap: 15px; margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); border-radius: 8px; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 150px;">
                            <div style="font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e('Input Tokens', 'gen-wave'); ?></div>
                            <div id="genwave-input-tokens" style="font-size: 20px; font-weight: bold; color: #667eea;">-</div>
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <div style="font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e('Output Tokens', 'gen-wave'); ?></div>
                            <div id="genwave-output-tokens" style="font-size: 20px; font-weight: bold; color: #764ba2;">-</div>
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <div style="font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e('Total Tokens', 'gen-wave'); ?></div>
                            <div id="genwave-total-tokens" style="font-size: 20px; font-weight: bold; color: #f093fb;">-</div>
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <div style="font-size: 11px; color: #666; text-transform: uppercase; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e('Token Cost', 'gen-wave'); ?></div>
                            <div id="genwave-token-cost" style="font-size: 20px; font-weight: bold; color: #43e97b;">-</div>
                        </div>
                    </div>

                    <!-- Generated Content -->
                    <div id="genwave-content-wrapper" style="margin-bottom: 20px;">
                        <h3 id="genwave-content-title" style="margin: 0 0 10px 0; font-size: 16px; color: #333;">
                            <span class="dashicons dashicons-edit" style="font-size: 18px; margin-top: 2px;"></span>
                            <?php esc_html_e('Generated Content:', 'gen-wave'); ?>
                        </h3>
                        <div id="genwave-generated-content" style="padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px; white-space: pre-wrap; line-height: 1.8; max-height: 300px; overflow-y: auto; font-size: 14px; color: #333;">
                            <?php esc_html_e('Loading...', 'gen-wave'); ?>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div style="padding: 20px; border-top: 1px solid #ddd; text-align: right; background: #f9f9f9; border-radius: 0 0 8px 8px;">
                    <button type="button" class="button" id="genwave-modal-cancel" style="margin-right: 10px; background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color: white; border: none; padding: 8px 20px; box-shadow: 0 2px 8px rgba(108, 117, 125, 0.2);">
                        <span class="dashicons dashicons-no" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Cancel', 'gen-wave'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="genwave-update-content" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 8px 20px; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);">
                        <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                        <span id="genwave-update-btn-text"><?php esc_html_e('Update Post', 'gen-wave'); ?></span>
                    </button>
                </div>
            </div>
        </div>

        <style>
            /* Modern Generate Button */
            .genwave-modern-button {
                position: relative;
                width: 100%;
                padding: 12px 20px;
                border: none;
                border-radius: 10px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                overflow: hidden;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .genwave-modern-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
                background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            }

            .genwave-modern-button:active {
                transform: translateY(0);
                box-shadow: 0 2px 10px rgba(102, 126, 234, 0.4);
            }

            .genwave-modern-button:disabled {
                opacity: 0.7;
                cursor: not-allowed;
                transform: none;
            }

            .genwave-button-content {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                position: relative;
                z-index: 1;
            }

            .genwave-button-icon {
                font-size: 18px;
                animation: sparkle 2s ease-in-out infinite;
            }

            @keyframes sparkle {
                0%, 100% {
                    transform: scale(1) rotate(0deg);
                    opacity: 1;
                }
                50% {
                    transform: scale(1.2) rotate(180deg);
                    opacity: 0.8;
                }
            }

            .genwave-button-text {
                font-size: 14px;
                letter-spacing: 0.3px;
            }

            /* Shine effect */
            .genwave-button-shine {
                position: absolute;
                top: -50%;
                left: -100%;
                width: 30%;
                height: 200%;
                background: rgba(255, 255, 255, 0.3);
                transform: rotate(30deg);
                transition: left 0.5s ease;
            }

            .genwave-modern-button:hover .genwave-button-shine {
                left: 150%;
            }

            /* Toggle Options Button */
            .genwave-toggle-button {
                width: 100%;
                margin-top: 10px;
                padding: 10px 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                background: #fff;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                color: #666;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .genwave-toggle-button:hover {
                background: #f8f9fa;
                border-color: #667eea;
                color: #667eea;
            }

            #genwave-toggle-icon {
                transition: transform 0.3s ease;
                margin-top: 3px;
            }

            #genwave-advanced-options {
                overflow: hidden;
            }

            /* Loading state */
            .genwave-modern-button.loading .genwave-button-icon {
                animation: rotation 1s linear infinite;
            }

            .genwave-metabox-wrapper .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }

            .genwave-status-message.error {
                border-left-color: #dc3232;
                background: #fef7f7;
            }

            .genwave-status-message.success {
                border-left-color: #00a32a;
                background: #f0f6fc;
            }

            /* Action Message Styles */
            .genwave-action-message {
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

            .genwave-action-message i {
                font-size: 16px;
                flex-shrink: 0;
            }

            .genwave-action-message.loading {
                background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
                border-left-color: #2196f3;
                color: #1565c0;
            }

            .genwave-action-message.success {
                background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
                border-left-color: #4caf50;
                color: #2e7d32;
            }

            .genwave-action-message.error {
                background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
                border-left-color: #f44336;
                color: #c62828;
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
        </style>

        <script>
        jQuery(document).ready(function($) {
            // SECURITY: Create nonces for AJAX requests
            var generateNonce = '<?php echo esc_js(wp_create_nonce('genwave_generate_nonce')); ?>';
            var markConvertedNonce = '<?php echo esc_js(wp_create_nonce('genwave_mark_converted_nonce')); ?>';

            // Show/hide instructions field based on generation method
            function updateInstructionsField() {
                var generationMethod = $('input[name="genwave_generation_method"]:checked').val();
                var $instructionsField = $('#genwave-instructions-field');

                if (generationMethod === 'title' || generationMethod === 'description') {
                    $instructionsField.slideDown(300);
                } else {
                    $instructionsField.slideUp(300);
                }
            }

            // Update instructions field on radio button change
            $('input[name="genwave_generation_method"]').on('change', updateInstructionsField);

            // Initialize on page load
            updateInstructionsField();

            // Character counter for instructions
            $('#genwave_instructions').on('input', function() {
                var charCount = $(this).val().length;
                $('#genwave-char-count').text(charCount);
            });

            // Instructions modal close handlers
            $('.genwave-instructions-close').on('click', function() {
                $('#genwave-instructions-modal').fadeOut();
                // Focus on the instructions textarea
                $('#genwave_instructions').focus();
            });

            // Toggle advanced options
            $('#genwave-toggle-options').on('click', function() {
                var $options = $('#genwave-advanced-options');
                var $icon = $('#genwave-toggle-icon');
                var $text = $('#genwave-toggle-text');

                if ($options.is(':visible')) {
                    $options.slideUp(300);
                    $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                    $text.text('Show Options');
                } else {
                    $options.slideDown(300);
                    $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                    $text.text('Hide Options');
                }
            });

            // Confetti function
            function createConfetti() {
                var container = $('#genwave-confetti-container');
                container.empty(); // Clear previous confetti

                var colors = ['#667eea', '#764ba2', '#f093fb', '#43e97b', '#ffd700', '#ff6b6b', '#4ecdc4', '#45b7d1'];
                var confettiCount = 50;

                for (var i = 0; i < confettiCount; i++) {
                    var confetti = $('<div class="genwave-confetti"></div>');

                    // Random properties
                    var left = Math.random() * 100;
                    var animationDelay = Math.random() * 0.5;
                    var color = colors[Math.floor(Math.random() * colors.length)];
                    var size = Math.random() * 8 + 6; // 6-14px
                    var rotation = Math.random() * 360;

                    confetti.css({
                        'left': left + '%',
                        'background': color,
                        'width': size + 'px',
                        'height': size + 'px',
                        'animation-delay': animationDelay + 's',
                        'transform': 'rotate(' + rotation + 'deg)',
                        'border-radius': Math.random() > 0.5 ? '50%' : '0'
                    });

                    container.append(confetti);
                }

                // Clean up confetti after animation
                setTimeout(function() {
                    container.empty();
                }, 3000);
            }

            // Load available languages (comprehensive WordPress language list)
            var languages = [
                'Afrikaans', 'Albanian', 'Arabic', 'Armenian', 'Azerbaijani',
                'Basque', 'Belarusian', 'Bengali', 'Bosnian', 'Bulgarian',
                'Catalan', 'Chinese (Simplified)', 'Chinese (Traditional)', 'Croatian', 'Czech',
                'Danish', 'Dutch', 'English', 'Estonian', 'Finnish',
                'French', 'Galician', 'Georgian', 'German', 'Greek',
                'Gujarati', 'Hebrew', 'Hindi', 'Hungarian', 'Icelandic',
                'Indonesian', 'Irish', 'Italian', 'Japanese', 'Kannada',
                'Kazakh', 'Khmer', 'Korean', 'Latvian', 'Lithuanian',
                'Macedonian', 'Malay', 'Malayalam', 'Marathi', 'Mongolian',
                'Nepali', 'Norwegian', 'Persian', 'Polish', 'Portuguese (Brazil)',
                'Portuguese (Portugal)', 'Punjabi', 'Romanian', 'Russian', 'Serbian',
                'Sinhala', 'Slovak', 'Slovenian', 'Spanish', 'Spanish (Mexico)',
                'Swahili', 'Swedish', 'Tamil', 'Telugu', 'Thai',
                'Turkish', 'Ukrainian', 'Urdu', 'Uzbek', 'Vietnamese',
                'Welsh', 'Yoruba'
            ];

            var $select = $('#genwave_language');
            var currentLang = $select.val() || 'English';
            $select.empty();

            // Add languages to dropdown
            languages.forEach(function(lang) {
                var selected = lang === currentLang ? ' selected' : '';
                $select.append('<option value="' + lang + '"' + selected + '>' + lang + '</option>');
            });

            // Initialize Select2 with search for language dropdown
            if (typeof $select.select2 === 'function') {
                $select.select2({
                    placeholder: 'Select a language',
                    allowClear: false,
                    width: '100%',
                    dropdownAutoWidth: true
                });
            }

            console.log('Languages loaded (static list):', languages.length + ' languages');

            $('#genwave-generate-btn').on('click', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var $statusContainer = $('#genwave-status-container');
                var $options = $('#genwave-advanced-options');

                // Remove any existing messages
                $statusContainer.empty();

                // If options are hidden, show them first
                if (!$options.is(':visible')) {
                    $('#genwave-toggle-options').trigger('click');

                    // Show message to configure options
                    $statusContainer.html('<div class="genwave-action-message success"><i class="dashicons dashicons-info"></i><span><strong>Configure Options:</strong> Please select your generation preferences below.</span></div>');

                    return;
                }

                // Get selected generation method, language and length
                var generationMethod = $('input[name="genwave_generation_method"]:checked').val();
                var language = $('#genwave_language').val();
                var length = $('#genwave_length').val();
                var instructions = $('#genwave_instructions').val().trim();
                var postId = <?php echo (int) $post->ID; ?>;

                // Validate selection
                if (!generationMethod) {
                    $statusContainer.html('<div class="genwave-action-message error"><i class="dashicons dashicons-warning"></i><span>Please select a generation method</span></div>');
                    return;
                }

                if (!language) {
                    $statusContainer.html('<div class="genwave-action-message error"><i class="dashicons dashicons-warning"></i><span>Please select a language</span></div>');
                    return;
                }

                if (!length) {
                    $statusContainer.html('<div class="genwave-action-message error"><i class="dashicons dashicons-warning"></i><span>Please select content length</span></div>');
                    return;
                }

                // Check if instructions are required and missing
                if ((generationMethod === 'title' || generationMethod === 'description') && !instructions) {
                    // Update help modal text
                    var helpType = generationMethod === 'title' ? 'a title' : 'description';
                    $('#genwave-help-type').text(helpType);

                    // Show instructions help modal
                    $('#genwave-instructions-modal').fadeIn();
                    return;
                }

                // Disable button and show loading state
                $btn.prop('disabled', true).addClass('loading');
                $btn.find('.genwave-button-icon').text('⏳');
                $btn.find('.genwave-button-text').text('Generating...');

                // Show loading message
                $statusContainer.html('<div class="genwave-action-message loading"><i class="dashicons dashicons-update" style="animation: rotation 1s linear infinite;"></i><span>Generating content, please wait...</span></div>');

                // Open modal immediately with staging indication
                $('#genwave-content-modal').fadeIn();

                // Replace entire content wrapper with staging indication
                $('#genwave-content-wrapper').html(`
                    <div style="
                        background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
                        border: 2px solid #667eea30;
                        border-radius: 12px;
                        padding: 20px;
                        position: relative;
                        overflow: hidden;
                    ">
                        <!-- Animated background -->
                        <div style="
                            position: absolute;
                            top: -50%;
                            left: -50%;
                            width: 200%;
                            height: 200%;
                            background: radial-gradient(circle, #667eea08 0%, transparent 70%);
                            animation: rotate 20s linear infinite;
                        "></div>

                        <div style="position: relative; z-index: 1;">
                            <!-- Header -->
                            <div style="text-align: center; margin-bottom: 15px;">
                                <div style="
                                    display: inline-flex;
                                    align-items: center;
                                    gap: 8px;
                                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                    color: white;
                                    padding: 8px 16px;
                                    border-radius: 20px;
                                    font-size: 13px;
                                    font-weight: 600;
                                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
                                ">
                                    <span style="font-size: 16px;">✨</span>
                                    <span>AI is Generating</span>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div style="margin-bottom: 15px;">
                                <div style="
                                    width: 100%;
                                    height: 6px;
                                    background: rgba(102, 126, 234, 0.1);
                                    border-radius: 3px;
                                    overflow: hidden;
                                    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
                                ">
                                    <div style="
                                        height: 100%;
                                        background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #667eea 100%);
                                        background-size: 200% 100%;
                                        animation: progressSlide 1.5s ease-in-out infinite;
                                        border-radius: 3px;
                                        box-shadow: 0 0 10px rgba(102, 126, 234, 0.5);
                                    "></div>
                                </div>
                            </div>

                            <!-- Stages -->
                            <div class="genwave-stages">
                                <div class="genwave-stage" style="
                                    display: flex;
                                    align-items: center;
                                    padding: 10px 14px;
                                    margin-bottom: 8px;
                                    background: white;
                                    border-radius: 8px;
                                    border-left: 4px solid #28a745;
                                    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.15);
                                    transform: translateX(0);
                                ">
                                    <div style="
                                        width: 24px;
                                        height: 24px;
                                        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                                        border-radius: 50%;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin-right: 10px;
                                        box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
                                    ">
                                        <span class="dashicons dashicons-yes" style="color: white; font-size: 12px;"></span>
                                    </div>
                                    <span style="color: #2d3748; font-size: 12px; font-weight: 600;">Connecting to AI Engine</span>
                                </div>

                                <div class="genwave-stage" id="genwave-stage-analyzing" style="
                                    display: flex;
                                    align-items: center;
                                    padding: 10px 14px;
                                    margin-bottom: 8px;
                                    background: white;
                                    border-radius: 8px;
                                    border-left: 4px solid #667eea;
                                    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
                                    opacity: 0.6;
                                    transform: translateX(0);
                                ">
                                    <div style="
                                        width: 24px;
                                        height: 24px;
                                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                        border-radius: 50%;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin-right: 10px;
                                        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
                                    ">
                                        <span class="dashicons dashicons-update" style="color: white; font-size: 12px; animation: rotation 1s linear infinite;"></span>
                                    </div>
                                    <span style="color: #4a5568; font-size: 12px; font-weight: 500;">Analyzing Content</span>
                                </div>

                                <div class="genwave-stage" id="genwave-stage-generating" style="
                                    display: flex;
                                    align-items: center;
                                    padding: 10px 14px;
                                    margin-bottom: 8px;
                                    background: white;
                                    border-radius: 8px;
                                    border-left: 4px solid #e2e8f0;
                                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                                    opacity: 0.4;
                                    transform: translateX(0);
                                ">
                                    <div style="
                                        width: 24px;
                                        height: 24px;
                                        background: #e2e8f0;
                                        border-radius: 50%;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin-right: 10px;
                                    ">
                                        <span class="dashicons dashicons-edit" style="color: #a0aec0; font-size: 12px;"></span>
                                    </div>
                                    <span style="color: #718096; font-size: 12px; font-weight: 500;">Generating Content</span>
                                </div>

                                <div class="genwave-stage" id="genwave-stage-finalizing" style="
                                    display: flex;
                                    align-items: center;
                                    padding: 10px 14px;
                                    background: white;
                                    border-radius: 8px;
                                    border-left: 4px solid #e2e8f0;
                                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                                    opacity: 0.4;
                                    transform: translateX(0);
                                ">
                                    <div style="
                                        width: 24px;
                                        height: 24px;
                                        background: #e2e8f0;
                                        border-radius: 50%;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin-right: 10px;
                                    ">
                                        <span class="dashicons dashicons-saved" style="color: #a0aec0; font-size: 12px;"></span>
                                    </div>
                                    <span style="color: #718096; font-size: 12px; font-weight: 500;">Finalizing</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `);

                // Animate stages with enhanced effects
                setTimeout(function() {
                    $('#genwave-stage-analyzing').css({
                        'opacity': '1',
                        'transform': 'translateX(0)',
                        'box-shadow': '0 2px 8px rgba(102, 126, 234, 0.15)'
                    });
                }, 500);

                setTimeout(function() {
                    // Complete analyzing stage
                    $('#genwave-stage-analyzing').css({
                        'border-left-color': '#28a745',
                        'box-shadow': '0 2px 8px rgba(40, 167, 69, 0.15)'
                    }).find('div:first').css({
                        'background': 'linear-gradient(135deg, #28a745 0%, #20c997 100%)',
                        'box-shadow': '0 2px 8px rgba(40, 167, 69, 0.3)'
                    });
                    $('#genwave-stage-analyzing .dashicons').removeClass('dashicons-update').addClass('dashicons-yes').css({
                        'color': 'white',
                        'animation': 'none'
                    });
                    $('#genwave-stage-analyzing span:last').css({
                        'color': '#2d3748',
                        'font-weight': '600'
                    });

                    // Start generating stage
                    $('#genwave-stage-generating').css({
                        'border-left-color': '#667eea',
                        'opacity': '1',
                        'box-shadow': '0 2px 8px rgba(102, 126, 234, 0.15)',
                        'transform': 'translateX(0)'
                    }).find('div:first').css({
                        'background': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                        'box-shadow': '0 2px 8px rgba(102, 126, 234, 0.3)'
                    });
                    $('#genwave-stage-generating .dashicons').removeClass('dashicons-edit').addClass('dashicons-update').css({
                        'color': 'white',
                        'animation': 'rotation 1s linear infinite'
                    });
                    $('#genwave-stage-generating span:last').css('color', '#4a5568');
                }, 1500);

                setTimeout(function() {
                    // Complete generating stage
                    $('#genwave-stage-generating').css({
                        'border-left-color': '#28a745',
                        'box-shadow': '0 2px 8px rgba(40, 167, 69, 0.15)'
                    }).find('div:first').css({
                        'background': 'linear-gradient(135deg, #28a745 0%, #20c997 100%)',
                        'box-shadow': '0 2px 8px rgba(40, 167, 69, 0.3)'
                    });
                    $('#genwave-stage-generating .dashicons').removeClass('dashicons-update').addClass('dashicons-yes').css({
                        'color': 'white',
                        'animation': 'none'
                    });
                    $('#genwave-stage-generating span:last').css({
                        'color': '#2d3748',
                        'font-weight': '600'
                    });

                    // Start finalizing stage
                    $('#genwave-stage-finalizing').css({
                        'border-left-color': '#667eea',
                        'opacity': '1',
                        'box-shadow': '0 2px 8px rgba(102, 126, 234, 0.15)',
                        'transform': 'translateX(0)'
                    }).find('div:first').css({
                        'background': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                        'box-shadow': '0 2px 8px rgba(102, 126, 234, 0.3)'
                    });
                    $('#genwave-stage-finalizing .dashicons').removeClass('dashicons-saved').addClass('dashicons-update').css({
                        'color': 'white',
                        'animation': 'rotation 1s linear infinite'
                    });
                    $('#genwave-stage-finalizing span:last').css('color', '#4a5568');
                }, 2500);

                // Hide token info during loading
                $('.genwave-token-info').hide();

                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'genwave_generate_single',
                        post_id: postId,
                        generation_method: generationMethod,
                        language: language,
                        length: length,
                        instructions: instructions,
                        nonce: generateNonce // SECURITY: Add nonce for CSRF protection
                    },
                    success: function(response) {
                        console.log('=== GENERATION RESPONSE ===');
                        console.log('Full response:', response);
                        console.log('response.success:', response.success);
                        console.log('response.data:', response.data);
                        if (response.data) {
                            console.log('response.data.data:', response.data.data);
                        }

                        // Re-enable button and reset state
                        $btn.prop('disabled', false).removeClass('loading');
                        $btn.find('.genwave-button-icon').text('✨');
                        $btn.find('.genwave-button-text').text('Generate AI Content');

                        if (response.success && response.data && response.data.data) {
                            console.log('=== INSIDE IF CONDITION ===');
                            console.log('Full response data:', response.data.data);

                            var data = response.data.data;
                            var results = data.results;

                            console.log('Results object:', results);

                            if (results && results.results && results.results.length > 0) {
                                var result = results.results[0];
                                console.log('First result:', result);

                                var content = result.content;

                                // Token info is at results level, not result level!
                                var tokenUsage = results.tokens_used || {};
                                var tokenCost = results.token_charge_id || 0;
                                var tokenData = results.token_usage || {}; // Balance is here

                                console.log('Token usage:', tokenUsage);
                                console.log('Token data:', tokenData);
                                console.log('Token cost:', tokenCost);

                                // Modal is already open, just trigger confetti
                                createConfetti();

                                // Show token info again
                                $('.genwave-token-info').show();

                                // Populate token info
                                $('#genwave-input-tokens').text((tokenUsage.input_tokens || 0).toLocaleString());
                                $('#genwave-output-tokens').text((tokenUsage.output_tokens || 0).toLocaleString());
                                $('#genwave-total-tokens').text((tokenUsage.total_tokens || 0).toLocaleString());
                                $('#genwave-token-cost').text(parseFloat(tokenCost || 0).toFixed(6));

                                // Get the right content based on generation method
                                var generatedText = '';
                                if (generationMethod === 'title') {
                                    generatedText = content.title || content.description || content || 'No content generated';
                                } else if (generationMethod === 'short_description') {
                                    generatedText = content.short_description || content.description || content || 'No content generated';
                                } else {
                                    generatedText = content.description || content.title || content || 'No content generated';
                                }

                                // Update modal title based on generation method
                                var contentTypeLabel = '';
                                if (generationMethod === 'title') {
                                    contentTypeLabel = 'Generated Title:';
                                } else if (generationMethod === 'short_description') {
                                    contentTypeLabel = 'Generated Short Description:';
                                } else {
                                    contentTypeLabel = 'Generated Description:';
                                }

                                // Restore original wrapper with title and content
                                $('#genwave-content-wrapper').html(`
                                    <h3 id="genwave-content-title" style="margin: 0 0 10px 0; font-size: 16px; color: #333;">
                                        <span class="dashicons dashicons-edit" style="font-size: 18px; margin-top: 2px;"></span>
                                        ${contentTypeLabel}
                                    </h3>
                                    <div id="genwave-generated-content" style="padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px; white-space: pre-wrap; line-height: 1.8; max-height: 300px; overflow-y: auto; font-size: 14px; color: #333;">${generatedText}</div>
                                `);

                                // Update button text
                                var updateBtnText = 'Update Post';
                                if (generationMethod === 'title') {
                                    updateBtnText = 'Update Title';
                                } else if (generationMethod === 'short_description') {
                                    updateBtnText = 'Update Short Description';
                                } else {
                                    updateBtnText = 'Update Description';
                                }
                                $('#genwave-update-btn-text').text(updateBtnText);

                                // Store the generated content for update
                                $('#genwave-update-content').data('generated-content', generatedText);
                                $('#genwave-update-content').data('generation-method', generationMethod);

                                // Store post_request_id for marking as converted later
                                if (response.data.post_request_id) {
                                    $('#genwave-update-content').data('post-request-id', response.data.post_request_id);
                                    console.log('Stored post_request_id:', response.data.post_request_id);
                                }

                                // Update admin bar token balance if available
                                if (tokenData.tokens_balance !== undefined) {
                                    var newBalance = parseFloat(tokenData.tokens_balance).toFixed(2);
                                    $('#wp-admin-bar-custom_text_with_icon .ab-item span').text(newBalance);
                                    console.log('Updated admin bar balance to:', newBalance);
                                }

                                // Success message
                                $statusContainer.html('<div class="genwave-action-message success"><i class="dashicons dashicons-yes"></i><span><strong>Success!</strong> Content generated successfully!</span></div>');
                            } else {
                                $statusContainer.html('<div class="genwave-action-message error"><i class="dashicons dashicons-no"></i><span><strong>Error:</strong> No content was generated.</span></div>');

                                // Show error in modal - replace entire wrapper
                                $('#genwave-content-wrapper').html(`
                                    <div style="text-align: center; padding: 40px 20px;">
                                        <div style="font-size: 60px; margin-bottom: 20px;">🤷</div>
                                        <h3 style="margin: 0 0 10px 0; color: #dc3545; font-size: 20px;">No Content Generated</h3>
                                        <p style="color: #6c757d; margin: 0 0 20px 0; font-size: 14px;">The AI couldn't generate content. Please try again with different settings.</p>
                                        <button type="button" class="button" onclick="jQuery('#genwave-content-modal').fadeOut();" style="
                                            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
                                            color: white;
                                            border: none;
                                            padding: 10px 24px;
                                            border-radius: 8px;
                                            cursor: pointer;
                                            font-weight: 500;
                                        ">Close</button>
                                    </div>
                                `);
                            }
                        } else {
                            var errorMsg = (response.data && response.data.message) ? response.data.message : 'Failed to generate content.';
                            $statusContainer.html('<div class="genwave-action-message error"><i class="dashicons dashicons-no"></i><span><strong>Error:</strong> ' + errorMsg + '</span></div>');

                            // Show error in modal - replace entire wrapper
                            $('#genwave-content-wrapper').html(`
                                <div style="text-align: center; padding: 40px 20px;">
                                    <div style="font-size: 60px; margin-bottom: 20px;">❌</div>
                                    <h3 style="margin: 0 0 10px 0; color: #dc3545; font-size: 20px;">Generation Failed</h3>
                                    <p style="color: #6c757d; margin: 0 0 20px 0; font-size: 14px;">${errorMsg}</p>
                                    <button type="button" class="button" onclick="jQuery('#genwave-content-modal').fadeOut();" style="
                                        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
                                        color: white;
                                        border: none;
                                        padding: 10px 24px;
                                        border-radius: 8px;
                                        cursor: pointer;
                                        font-weight: 500;
                                    ">Close</button>
                                </div>
                            `);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error, xhr.responseText);

                        $statusContainer.html('<div class="genwave-action-message error"><i class="dashicons dashicons-no"></i><span><strong>Error:</strong> Failed to connect to the server. Please try again.</span></div>');

                        // Show error in modal - replace entire wrapper
                        $('#genwave-content-wrapper').html(`
                            <div style="text-align: center; padding: 40px 20px;">
                                <div style="font-size: 60px; margin-bottom: 20px;">⚠️</div>
                                <h3 style="margin: 0 0 10px 0; color: #dc3545; font-size: 20px;">Oops! Something Went Wrong</h3>
                                <p style="color: #6c757d; margin: 0 0 20px 0; font-size: 14px;">Failed to connect to the server. Please check your connection and try again.</p>
                                <button type="button" class="button" onclick="jQuery('#genwave-content-modal').fadeOut();" style="
                                    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
                                    color: white;
                                    border: none;
                                    padding: 10px 24px;
                                    border-radius: 8px;
                                    cursor: pointer;
                                    font-weight: 500;
                                ">Close</button>
                            </div>
                        `);

                        // Re-enable button and reset state
                        $btn.prop('disabled', false).removeClass('loading');
                        $btn.find('.genwave-button-icon').text('✨');
                        $btn.find('.genwave-button-text').text('Generate AI Content');
                    }
                });
            });

            // Modal close handlers
            $('.genwave-modal-close, #genwave-modal-cancel').on('click', function() {
                $('#genwave-content-modal').fadeOut();
            });

            // Close modal on outside click
            $('#genwave-content-modal').on('click', function(e) {
                if (e.target.id === 'genwave-content-modal') {
                    $(this).fadeOut();
                }
            });

            // Update content button
            $('#genwave-update-content').on('click', function() {
                var generatedContent = $(this).data('generated-content');
                var generationMethod = $(this).data('generation-method');
                var postRequestId = $(this).data('post-request-id');
                var postId = <?php echo (int) $post->ID; ?>;

                if (!generatedContent) {
                    alert('No content to update');
                    return;
                }

                console.log('Updating with method:', generationMethod);
                console.log('Content:', generatedContent);
                console.log('Post request ID:', postRequestId);

                // Update based on generation method
                if (generationMethod === 'title') {
                    // Update title field
                    $('#title').val(generatedContent);

                    // Also update Gutenberg title if it exists
                    if (wp.data && wp.data.dispatch('core/editor')) {
                        wp.data.dispatch('core/editor').editPost({
                            title: generatedContent
                        });
                    }

                    $('.genwave-status-message').removeClass('error').addClass('success')
                        .find('p').html('<strong>Updated!</strong> Title has been updated. Don\'t forget to save the post!');

                } else if (generationMethod === 'short_description') {
                    // Update excerpt/short description
                    $('#excerpt').val(generatedContent);

                    // For WooCommerce products, update short description
                    if ($('#woocommerce-product-data').length > 0) {
                        // WooCommerce short description
                        if (typeof tinymce !== 'undefined' && tinymce.get('excerpt')) {
                            tinymce.get('excerpt').setContent(generatedContent);
                        }
                    }

                    // Also update Gutenberg excerpt if it exists
                    if (wp.data && wp.data.dispatch('core/editor')) {
                        wp.data.dispatch('core/editor').editPost({
                            excerpt: generatedContent
                        });
                    }

                    $('.genwave-status-message').removeClass('error').addClass('success')
                        .find('p').html('<strong>Updated!</strong> Short description has been updated. Don\'t forget to save!');

                } else {
                    // Update main content (description)
                    if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                        // Classic editor (TinyMCE)
                        var editor = tinymce.get('content');
                        if (editor) {
                            editor.setContent(generatedContent);
                        }
                    } else if (wp.data && wp.data.select('core/editor')) {
                        // Gutenberg editor
                        var blocks = wp.blocks.parse(generatedContent);
                        wp.data.dispatch('core/block-editor').resetBlocks(blocks);
                    } else {
                        // Fallback to textarea
                        $('#content').val(generatedContent);
                    }

                    $('.genwave-status-message').removeClass('error').addClass('success')
                        .find('p').html('<strong>Updated!</strong> Content has been inserted into the editor. Don\'t forget to save the post!');
                }

                // Mark as converted in database if we have post_request_id
                if (postRequestId) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'genwave_mark_converted',
                            post_request_id: postRequestId,
                            nonce: markConvertedNonce // SECURITY: Add nonce for CSRF protection
                        },
                        success: function(response) {
                            console.log('Post marked as converted:', response);
                        },
                        error: function(xhr, status, error) {
                            console.error('Failed to mark as converted:', error);
                        }
                    });
                }

                // Close modal
                $('#genwave-content-modal').fadeOut();

                // Show success message
                $('.genwave-metabox-status').fadeIn();

                // Scroll to top to see the updated content
                $('html, body').animate({ scrollTop: 0 }, 500);
            });
        });
        </script>

        <style>
            @keyframes rotation {
                from { transform: rotate(0deg); }
                to { transform: rotate(359deg); }
            }

            /* Modal hover effects */
            .genwave-modal-close:hover {
                opacity: 1 !important;
                transform: scale(1.1);
                transition: all 0.2s ease;
            }

            #genwave-update-content:hover {
                opacity: 0.9;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                transition: all 0.2s ease;
            }

            #genwave-modal-cancel {
                transition: all 0.2s ease;
            }

            #genwave-modal-cancel:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3) !important;
                opacity: 0.9;
            }

            #genwave-update-content {
                transition: all 0.2s ease;
            }

            #genwave-update-content:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3) !important;
                opacity: 0.9;
            }

            /* Scrollbar styling for modal content */
            #genwave-generated-content::-webkit-scrollbar {
                width: 8px;
            }

            #genwave-generated-content::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }

            #genwave-generated-content::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 4px;
            }

            #genwave-generated-content::-webkit-scrollbar-thumb:hover {
                background: #555;
            }

            /* Staging Animations */
            @keyframes progressSlide {
                0% {
                    background-position: 200% 0;
                }
                100% {
                    background-position: -200% 0;
                }
            }

            @keyframes rotate {
                0% {
                    transform: rotate(0deg);
                }
                100% {
                    transform: rotate(360deg);
                }
            }

            /* Confetti Animation */
            .genwave-confetti {
                position: absolute;
                width: 10px;
                height: 10px;
                background: #f0f;
                animation: confetti-fall 3s linear forwards;
                opacity: 1;
            }

            @keyframes confetti-fall {
                0% {
                    transform: translateY(0) rotate(0deg);
                    opacity: 1;
                }
                100% {
                    transform: translateY(100vh) rotate(720deg);
                    opacity: 0;
                }
            }

            .genwave-modal-content {
                animation: modalZoomIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }

            @keyframes modalZoomIn {
                0% {
                    transform: scale(0.7);
                    opacity: 0;
                }
                100% {
                    transform: scale(1);
                    opacity: 1;
                }
            }

            /* Instructions modal hover effects */
            .genwave-instructions-close:hover {
                opacity: 1 !important;
                transform: scale(1.05);
                transition: all 0.2s ease;
            }
        </style>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_meta_box_data($post_id) {
        // Check if nonce is set
        if (!isset($_POST['genwave_meta_box_nonce_field'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['genwave_meta_box_nonce_field'])), 'genwave_meta_box_nonce')) {
            return;
        }

        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save the generation method selection
        if (isset($_POST['genwave_generation_method'])) {
            $generation_method = sanitize_text_field(wp_unslash($_POST['genwave_generation_method']));

            // Validate the value
            if (in_array($generation_method, ['title', 'short_description', 'description'], true)) {
                update_post_meta($post_id, '_genwave_generation_method', $generation_method);
            }
        }
    }
}
