<?php

namespace GenWavePlugin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

class InstantGeneratorSettings
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'init_settings']);
        add_action('wp_ajax_genwave_test_connection', [$this, 'test_api_connection']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Enqueue scripts for the settings page
     */
    public function enqueue_scripts($hook)
    {
        // Only load on our settings page
        if ($hook !== 'settings_page_ai-generator-settings') {
            return;
        }

        wp_enqueue_script(
            'gen-wave-instant-generator',
            GEN_WAVE_ASSETS_URL . '/js/instant-generator.js',
            ['jquery'],
            GEN_WAVE_VERSION,
            true
        );

        wp_localize_script('gen-wave-instant-generator', 'genwaveInstantGenerator', [
            'nonce' => wp_create_nonce('genwave_test_connection'),
        ]);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_options_page(
            'AI Content Generator Settings',
            'AI Generator',
            'manage_options',
            'ai-generator-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Initialize settings
     */
    public function init_settings()
    {
        register_setting('genwave_generator_settings', 'genwave_api_base_url', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_url',
            'default' => 'https://account.genwave.ai/api/v1'
        ]);

        register_setting('genwave_generator_settings', 'genwave_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);

        register_setting('genwave_generator_settings', 'genwave_default_model', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'gpt-3.5-turbo'
        ]);

        register_setting('genwave_generator_settings', 'genwave_default_provider', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'OpenAI'
        ]);

        register_setting('genwave_generator_settings', 'genwave_rate_limit', [
            'type' => 'integer',
            'sanitize_callback' => 'intval',
            'default' => 10
        ]);

        // Add settings sections
        add_settings_section(
            'genwave_generator_api_section',
            'API Configuration',
            [$this, 'render_api_section_description'],
            'genwave_generator_settings'
        );

        add_settings_section(
            'genwave_generator_defaults_section',
            'Default Settings',
            [$this, 'render_defaults_section_description'],
            'genwave_generator_settings'
        );

        // Add settings fields
        add_settings_field(
            'genwave_api_base_url',
            'API Base URL',
            [$this, 'render_api_base_url_field'],
            'genwave_generator_settings',
            'genwave_generator_api_section'
        );

        add_settings_field(
            'genwave_api_key',
            'API Key',
            [$this, 'render_api_key_field'],
            'genwave_generator_settings',
            'genwave_generator_api_section'
        );

        add_settings_field(
            'genwave_default_provider',
            'Default Provider',
            [$this, 'render_default_provider_field'],
            'genwave_generator_settings',
            'genwave_generator_defaults_section'
        );

        add_settings_field(
            'genwave_default_model',
            'Default Model',
            [$this, 'render_default_model_field'],
            'genwave_generator_settings',
            'genwave_generator_defaults_section'
        );

        add_settings_field(
            'genwave_rate_limit',
            'Rate Limit (per minute)',
            [$this, 'render_rate_limit_field'],
            'genwave_generator_settings',
            'genwave_generator_defaults_section'
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle form submission
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'genwave_generator_messages',
                'genwave_generator_message',
                'Settings saved successfully!',
                'updated'
            );
        }

        settings_errors('genwave_generator_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="ai-settings-header" style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2 style="margin: 0 0 10px 0;">🤖 AI Content Generator</h2>
                <p>Configure your AI content generation settings. This plugin connects to your Laravel AI service to generate content instantly while editing posts and products.</p>
                
                <div class="connection-status" style="margin-top: 15px;">
                    <button type="button" id="test-connection-btn" class="button button-secondary">
                        Test API Connection
                    </button>
                    <span id="connection-status" style="margin-left: 10px;"></span>
                </div>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields('genwave_generator_settings');
                do_settings_sections('genwave_generator_settings');
                submit_button('Save Settings');
                ?>
            </form>

            <div class="ai-settings-info" style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3>How to Use</h3>
                <ol>
                    <li><strong>Configure API:</strong> Enter your Laravel API base URL and API key above.</li>
                    <li><strong>Test Connection:</strong> Click "Test API Connection" to verify your settings.</li>
                    <li><strong>Generate Content:</strong> When editing posts or products, look for the "🤖 Generate AI Content" button.</li>
                    <li><strong>Select Options:</strong> Choose what content to generate (title, content, excerpts, etc.).</li>
                    <li><strong>Apply Results:</strong> Review generated content and apply it to your editor.</li>
                </ol>

                <h3>Features</h3>
                <ul>
                    <li>✅ Instant content generation (no waiting for jobs)</li>
                    <li>✅ Works with both Classic and Gutenberg editors</li>
                    <li>✅ Support for posts, pages, and WooCommerce products</li>
                    <li>✅ Token calculation and cost estimation</li>
                    <li>✅ Rate limiting to prevent abuse</li>
                    <li>✅ Customizable content length and instructions</li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Test API connection via AJAX
     */
    public function test_api_connection()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'genwave_test_connection')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        try {
            $api_helper = new \AI\Helper\ApiHelper();
            $result = $api_helper->test_connection();

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            if ($result === true) {
                wp_send_json_success('API connection is working correctly');
            } else {
                wp_send_json_error('API returned unexpected response');
            }

        } catch (Exception $e) {
            wp_send_json_error('Connection test failed: ' . $e->getMessage());
        }
    }

    // Field renderers
    public function render_api_section_description()
    {
        echo '<p>Configure the connection to your Laravel AI service.</p>';
    }

    public function render_defaults_section_description()
    {
        echo '<p>Set default values for AI content generation.</p>';
    }

    public function render_api_base_url_field()
    {
        $value = get_option('genwave_api_base_url', 'https://account.genwave.ai/api/v1');
        printf(
            '<input type="url" id="genwave_api_base_url" name="genwave_api_base_url" value="%s" class="regular-text" placeholder="https://your-domain.com/api/v1" />',
            esc_attr($value)
        );
        echo '<p class="description">The base URL of your Laravel AI API (without trailing slash).</p>';
    }

    public function render_api_key_field()
    {
        $value = get_option('genwave_api_key', '');
        printf(
            '<input type="password" id="genwave_api_key" name="genwave_api_key" value="%s" class="regular-text" placeholder="your-api-key-here" />',
            esc_attr($value)
        );
        echo '<p class="description">Your API key for authenticating with the Laravel service.</p>';
    }

    public function render_default_provider_field()
    {
        $value = get_option('genwave_default_provider', 'OpenAI');
        $providers = ['OpenAI', 'Anthropic', 'Google'];

        echo '<select id="genwave_default_provider" name="genwave_default_provider">';
        foreach ($providers as $provider) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($provider),
                selected($value, $provider, false),
                esc_html($provider)
            );
        }
        echo '</select>';
        echo '<p class="description">Default AI provider for content generation.</p>';
    }

    public function render_default_model_field()
    {
        $value = get_option('genwave_default_model', 'gpt-3.5-turbo');
        $models = [
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (OpenAI)',
            'gpt-4' => 'GPT-4 (OpenAI)',
            'claude-3-haiku' => 'Claude 3 Haiku (Anthropic)',
            'claude-3-sonnet' => 'Claude 3 Sonnet (Anthropic)',
            'gemini-pro' => 'Gemini Pro (Google)'
        ];

        echo '<select id="genwave_default_model" name="genwave_default_model">';
        foreach ($models as $model => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($model),
                selected($value, $model, false),
                esc_html($label)
            );
        }
        echo '</select>';
        echo '<p class="description">Default AI model for content generation.</p>';
    }

    public function render_rate_limit_field()
    {
        $value = get_option('genwave_rate_limit', 10);
        printf(
            '<input type="number" id="genwave_rate_limit" name="genwave_rate_limit" value="%s" min="1" max="100" class="small-text" />',
            esc_attr($value)
        );
        echo '<p class="description">Maximum number of AI requests per minute per user.</p>';
    }
}