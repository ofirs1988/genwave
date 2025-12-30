<?php

namespace GenWavePlugin;

if (!defined('ABSPATH')) {
    exit;
}

class MetaBox {

    public function __construct() {
        // Only register metabox if Genwave Pro is NOT active
        if (!$this->is_pro_active()) {
            add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
            add_action('save_post', [$this, 'save_meta_box_data']);
        }
    }

    /**
     * Check if Genwave Pro plugin is active
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
            __('Genwave - AI Content Generation', 'gen-wave'),
            [$this, 'render_meta_box'],
            'post',
            'side',
            'high'
        );

        // Add metabox for WooCommerce products if WooCommerce is active
        if (class_exists('WooCommerce')) {
            add_meta_box(
                'genwave_generate_box',
                __('Genwave - AI Content Generation', 'gen-wave'),
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
        $uidd = \GenWavePlugin\Core\Config::get('uidd');
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
                        <?php esc_html_e('Please connect your Genwave account to use AI content generation.', 'gen-wave'); ?>
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
                        printf(esc_html__('Use Genwave to automatically generate content for this %s.', 'gen-wave'), esc_html(strtolower($post_type_label)));
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

            <!-- Generate Button at Bottom of Options -->
            <button type="button"
                    id="genwave-generate-bottom"
                    class="genwave-generate-bottom-btn">
                <span class="dashicons dashicons-welcome-write-blog" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px; margin-left: -4px;"></span>
                <?php esc_html_e('Generate Content', 'gen-wave'); ?>
            </button>
            </div><!-- End Advanced Options -->

            <!-- Status Message Container -->
            <div id="genwave-status-container" style="margin-top: 12px;"></div>

            <div class="genwave-upgrade-notice" style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                <p style="margin: 0; font-size: 12px; color: #856404;">
                    <strong><?php esc_html_e('Note:', 'gen-wave'); ?></strong> <?php
                    printf(
                        /* translators: %s: link to Genwave Pro */
                        esc_html__('For advanced features like bulk generation, multiple AI providers, and real-time streaming, upgrade to %s.', 'gen-wave'),
                        '<a href="https://genwave.ai" target="_blank" style="color: #856404; text-decoration: underline;">' . esc_html__('Genwave Pro', 'gen-wave') . '</a>'
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
