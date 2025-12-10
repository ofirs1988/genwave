=== Gen Wave ===
Contributors: ofirshurdeker
Tags: ai, authentication, license-management, api, content-generation
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Securely connects WordPress to Gen Wave AI. Handles authentication, licensing, and API routing so your Gen Wave services run smoothly.

== Description ==

**Powering the Gen Wave Ecosystem.**

Gen Wave serves as the secure communication bridge and core infrastructure layer between your WordPress site and our AI cloud. We handle the complex infrastructure—authentication, security, license management, and API routing—so your Gen Wave ecosystem tools (like Gen Wave Pro) can run reliably, efficiently, and securely.

This plugin provides the essential foundation for Gen Wave Pro and other Gen Wave services through:

* **Secure Authentication**: AES-256-CBC encryption for token-based authentication.
* **License Management**: Complete license verification and management system.
* **User Balance Tracking**: Track and manage user token balances and usage.
* **API Gateway**: Centralized, encrypted API communication layer for Gen Wave Pro integration.
* **Domain Verification**: Secure domain verification and validation.
* **Background Processing**: Efficient handling of bulk operations.
* **REST API**: Secure REST endpoints for external integrations.

= Features =

* Token-based authentication system with AES-256-CBC encryption
* License key verification and management system
* User balance and usage tracking
* Secure API communication layer (API Gateway)
* Domain verification system
* Background processing for bulk operations
* REST API endpoints for integration
* Multi-language support ready

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* Active Gen Wave account (for full functionality)

= Security Architecture =

**Encryption Key Design:**
This plugin uses a shared encryption key (AES-256-CBC) that is synchronized between:
* Your WordPress installation
* Gen Wave backend services (account.genwave.ai)
* Gen Wave AI API (api.genwave.ai)

The encryption key is stored securely in your WordPress database (wp_options table) and can be rotated through the Gen Wave dashboard. The default key in the code serves as a fallback for initial setup and ensures compatibility with Gen Wave services.

**Note:** This is a security-by-design choice - the shared secret enables secure end-to-end encryption between your WordPress site and Gen Wave services without exposing sensitive data.

= Privacy =

This plugin communicates with external Gen Wave API services (account.genwave.ai and api.genwave.ai) for:
* License verification
* Authentication
* Token balance management
* Content generation (when used with Gen Wave Pro)

No user data is collected or transmitted without explicit user action. All communication is encrypted using AES-256-CBC.

== Installation ==

1. Upload the `gen-wave` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Gen Wave settings page
4. Enter your license key (obtain from account.genwave.ai)
5. Complete the authentication process

== Frequently Asked Questions ==

= Do I need a license key? =

Yes, Gen Wave requires a valid license key to function. You can obtain a license key by registering at account.genwave.ai.

= Is this plugin free? =

The core Gen Wave plugin is free. Premium features require a Gen Wave Pro subscription.

= What data is sent to external servers? =

The plugin communicates with Gen Wave API servers for license verification, authentication, and content generation services. All data is encrypted and transmitted securely.

= Is the plugin compatible with multisite? =

The plugin can be installed on multisite, but each site requires its own license key.

= Where can I get support? =

For support, please visit our support portal or contact us through the Gen Wave dashboard.

== Screenshots ==

1. Settings page - Configure your license key and authentication
2. Dashboard - View token balance and account status

== Changelog ==

= 1.0.0 =
* Initial release
* Core authentication and licensing infrastructure
* AES-256-CBC encryption for secure token-based authentication
* License key verification and management system
* Domain verification and validation
* User balance tracking and management
* REST API endpoints for integration
* Integration with Gen Wave backend services (account.genwave.ai)
* Support for Gen Wave AI API (api.genwave.ai)
* Background processing for bulk operations
* Secure API communication layer

== Upgrade Notice ==

= 1.0.0 =
Initial release - provides core authentication and licensing infrastructure for Gen Wave services.

== Third Party Services ==

This plugin relies on the following external services:

**Gen Wave Account API** (account.genwave.ai)
* Used for: License verification, user authentication, account management
* Privacy Policy: https://genwave.ai/privacy/
* Terms of Service: https://genwave.ai/terms/

**Gen Wave AI API** (api.genwave.ai)
* Used for: AI content generation, token balance management
* Privacy Policy: https://genwave.ai/privacy/
* Terms of Service: https://genwave.ai/terms

All data transmitted to these services is encrypted using AES-256-CBC encryption.