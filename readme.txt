=== Genwave - AI Agent ===
Contributors: ofirs1988
Tags: ai, ai-agent, ai-writing, content-generation, woocommerce
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your site to Genwave, write post and product text with AI, and add Genwave Agent to run your site by describing what you need.

== Description ==

Genwave helps you run the WordPress site you already have by describing what you need in plain words. You keep your admin, your theme, your plugins and your checkout. There is nothing to move.

This plugin is the starting point. It connects your site to your Genwave account, adds an AI writing screen for posts and products, and installs Genwave Agent, the chat where you ask for changes to your site.

[Visit Genwave](https://genwave.ai/) · [Create a free account](https://app.genwave.ai/register)

= What this plugin does =

* **Connect your site.** Click "Connect to Genwave", sign in or create a free account, and approve the site. There is no key to copy. If you prefer, you can paste your license key instead.
* **AI writing.** Pick a product or a post and ask for a new title, description or short description. You can add your own instructions and choose the language. You read the text first and save it only if you like it.
* **Genwave plugins.** See the Genwave plugins (Genwave Agent, Genwave Chatbot and Genwave Elements) and install them from your Genwave account in one click. You can also download a zip and upload it yourself.
* **Overview.** See whether your site is connected, whether Genwave Agent is installed and active, and a link to your usage and plan.
* **Maintenance.** Check and repair the few database tables this plugin keeps. Nothing is deleted.
* **Light and dark screens.** The Genwave screens follow your choice of a light or dark theme, and work in right-to-left languages.
* **No AI provider account needed.** You do not need an account or API key with any AI company. Genwave takes care of that.

= Genwave Agent =

Genwave Agent is a separate plugin that adds the agent chat. Install it in the same step as connecting your account, or later from Genwave → Plugins.

1. **Say what you need.** For example a new page, a product change, an SEO fix or help with an error.
2. **See what will change.** The agent shows you the change before it runs. You approve it or change your mind.
3. **It does the work.** The result appears in your normal WordPress admin.

In the agent chat you can work on pages and posts, WooCommerce products, coupons and orders, SEO, images, plugins and errors on your site. The agent can only do what your WordPress user is allowed to do. Learn more on the [Genwave Agent page](https://genwave.ai/agent/).

= Other Genwave products =

* **Genwave Chatbot** answers your visitors' questions using your own pages and products.
* **Genwave Studio** gives your site a new, fast front end that you change by describing what you want, while WordPress stays where you manage your content.

See [genwave.ai](https://genwave.ai/) for details and [genwave.ai/pricing](https://genwave.ai/pricing/) for the plans that include them.

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* A Genwave account. You can [create one for free](https://app.genwave.ai/register).

= Privacy =

This plugin talks to Genwave's services only to connect your account and to do what you ask, such as writing a description. Everything is sent over an encrypted (HTTPS) connection. The details, including what is sent and when, are listed under "External services" below.

== Installation ==

1. In your WordPress admin, go to Plugins → Add New and search for "Genwave".
2. Click Install Now, then Activate.
3. The Genwave Account screen opens. Click "Connect to Genwave".
4. Sign in to your Genwave account, or create one for free, and approve this site. Leave "Also install Genwave Agent" ticked and Genwave Agent is installed for you before you return.
5. Open Genwave Agent from your WordPress menu and tell it what you need.

Prefer to paste your license key? Open "Have an API key? Connect with it instead" under the Connect button.

To install Genwave Agent later, go to Genwave → Plugins and click Install next to Genwave Agent. You can also download it from [genwave.ai/agent](https://genwave.ai/agent/) and upload it under Plugins → Add New → Upload Plugin.

Please keep both plugins installed. Genwave Agent uses the connection that this plugin sets up.

To install this plugin by hand, download the zip file and upload it under Plugins → Add New → Upload Plugin. Then continue from step 3.

== Frequently Asked Questions ==

= Do I need my own AI account or API keys? =

No. Genwave provides the AI. Connect your Genwave account and you are ready.

= What is the difference between Genwave and Genwave Agent? =

Genwave (this plugin) connects your site to your Genwave account and adds AI writing for posts and products. Genwave Agent is a separate plugin that adds the agent chat, where you work on your whole site by conversation. Install both for the full experience.

= "Open the Agent" says I am not allowed to access the page. What should I do? =

This means Genwave Agent is not installed or not active yet. Go to Genwave → Plugins and click Install next to Genwave Agent. Your Genwave account installs it on this site and brings you back. You can also download the zip there and upload it under Plugins → Add New. The Genwave Account screen also shows an install or activate button whenever the agent is missing.

= Is the plugin free? =

Yes. The plugin is free. AI writing and the agent use your Genwave plan, and the Free plan lets you try them.

= How do the plans work? =

Genwave has a Free plan and two paid plans, Pro and Business. The Free plan includes a small number of AI actions each month for one site. Paid plans have no credits to count. Each one has a fair-use allowance shared by the sites on the plan. See the current plans on our [pricing page](https://genwave.ai/pricing/).

= Which languages does it support? =

AI writing can write in English, Hebrew, Arabic, Spanish, French, German, Italian, Portuguese, Russian, Chinese, Japanese, Dutch, Polish, Turkish and Hindi. The Genwave screens are in English and Hebrew, and they work in right-to-left languages.

= Does it work with WooCommerce? =

Yes. When WooCommerce is active, AI writing works on products as well as posts. Genwave Agent can also work with products, prices, stock, coupons and orders.

= Is it safe for my site? =

AI writing never saves anything until you click to save it. Genwave Agent shows you what will change before it runs, and it can only do what your WordPress user is allowed to do. We still recommend keeping a recent backup, as you would with any tool that changes your site.

= What information is sent to Genwave? =

Only what is needed for what you ask. For AI writing, that is the text of the post or product you picked and your instructions. The full list is under "External services" below, and our [privacy policy](https://genwave.ai/privacy/) has the details.

= Does it work on multisite? =

Yes. Each site in the network connects to Genwave on its own.

= How do I get help? =

* The support page in your Genwave account: [app.genwave.ai/support](https://app.genwave.ai/support)
* Email: support@genwave.ai
* The WordPress.org support forum for this plugin

== Screenshots ==

1. Connecting your site to your Genwave account
2. The Overview screen
3. Writing a product description with AI
4. Installing Genwave plugins from your Genwave account
5. Genwave Agent showing a change before it runs

== Changelog ==

= 1.3.0 =
* New design for the Genwave screens. Genwave plugins now install from your Genwave account.

= 1.2.2 =
* Security hardening for the REST API and site verification.

= 1.2.1 =
* Clearer wording on the plugin and Account pages, and no separate sign-up link: Connect to Genwave takes you through sign-up.

= 1.2.0 =
* Connect to Genwave: connect your site by signing in and approving it in your Genwave account, with no key to copy. Genwave Agent can be installed in the same step.

= 1.1.9 =
* No credits to count on Pro and Business. The WordPress toolbar shows Light mode if a month goes far past normal use, and about how many AI actions are left on the Free plan.

= 1.1.8 =
* Your AI credit balance now updates on sites connected recently. The name reads Genwave everywhere.

= 1.1.7 =
* Genwave Agent can now be installed in one click from Genwave > Plugins, including on recently connected sites. Clearer setup steps and FAQ.

= 1.1.6 =
* Compatibility: tested with WordPress 7.1.

= 1.1.5 =
* Fixed: the Open the Agent button now tells you when the Genwave Agent plugin is missing or inactive and offers to download or activate it, instead of showing an access error. Account, billing, credits, sign-up and support buttons now open the right page.

= 1.1.4 =
* Keep Genwave REST routes reachable when a security plugin restricts the REST API, so site connection works on locked-down sites

= 1.1.3 =
* Fix connection status and disconnect for the new secure connect flow

= 1.1.2 =
* New connection security: each site now signs every request to the AI Agent with its own key, verified end-to-end. No shared secrets or tokens are stored.
* Simpler connect: link your site with your license key in one step; a clear Connected / Not connected status is shown.
* Removed the retired LiteLLM integration and legacy authentication code.
* Stability and security improvements.

= 1.1.1 =
* Security hardening across account connection and admin actions.
* Modernized admin interface: refreshed Account, Plugins and content Generation pages.
* Refreshed plugin branding: new banner, icon and description.
* Clearer, friendlier status and error messages.
* Internal code cleanup and improvements.

= 1.1.0 =
**New:**
* New "Plugins" admin page to browse and install Genwave plugins from inside WordPress.
* WordPress 7.0 compatibility verified

**Improvements:**
* Faster bundle build pipeline
* Refreshed dashboard styles

= 1.0.6 =
**New:**
* Added AI Agent section to dashboard: download the AI Agent directly from your account
* Feature highlights: Build Plugins, Auto-Fix Errors, Elementor Widgets, WooCommerce management
* Direct download link to your Genwave account

= 1.0.5 =
**Rebranding:**
* Plugin renamed from "Genwave - AI Generate" to "Genwave - AI Agent for WordPress"
* Updated all descriptions, features, and messaging to focus on the AI Agent capabilities
* New feature highlights: Plugin Builder, Auto Error Fix, SEO Optimization
* Updated Quick Start guide for AI Agent workflow
* Updated upgrade messaging to reference Genwave Agent
* Updated tags, FAQ, and screenshots descriptions

= 1.0.4 =
**Improvements:**
* Removed duplicate Global folder and consolidated the code structure
* Removed debug console.log statements from admin JavaScript
* Code cleanup and optimization across multiple files
* Improved code organization (Global → Core namespace)

**Bug Fixes:**
* Various code fixes and improvements

= 1.0.3 =
**New Features:**
* New React-based Dashboard page with modern UI
* New Generate page for content generation with item selection
* Custom instructions field: tell the AI exactly what to write about
* Multi-language support with 28 preset languages (works with any language)
* MetaBox improvements: subtle top button + prominent bottom generate button

**Improvements:**
* Complete UI redesign with modern card-based interface
* Better token usage display and tracking
* Improved API communication for custom instructions
* Streamlined settings page with React components
* Removed unused vendor dependencies (smaller plugin size)

**Technical:**
* Added webpack build system for React components
* New PHP pages: Dashboard.php, Generate.php
* Updated ApiManager for custom instructions support
* Enhanced GenerationHandler with language parameter

= 1.0.2 =
* Improved readme documentation
* Added clearer feature descriptions
* Updated FAQ section

= 1.0.1 =
* Bug fixes and performance improvements

= 1.0.0 =
* Initial release
* Account connection and authentication
* Single content generation for posts and products
* Credit balance tracking
* Multi-language support (6 languages)
* WooCommerce integration
* Secure encrypted communication

== Upgrade Notice ==

= 1.1.6 =
Tested with WordPress 7.1.

= 1.1.5 =
If the Genwave Agent plugin is missing or inactive, the Account page now tells you and offers a download or activate button. Account, billing and support buttons open the right pages.

= 1.1.1 =
Security hardening and a refreshed, modernized admin interface. Recommended for all users.

= 1.1.0 =
A new Plugins page in the Genwave menu lets you install other Genwave plugins from inside WordPress. Tested with WordPress 7.0.

= 1.0.5 =
The plugin now focuses on the Genwave Agent: build plugins, fix errors and manage WooCommerce through conversation.

= 1.0.4 =
Minor update: Code cleanup and optimization.

= 1.0.3 =
Major update: New Dashboard & Generate pages, custom instructions, multi-language support, modern UI redesign.

= 1.0.2 =
Documentation improvements. No code changes.

= 1.0.0 =
First release. Connect your WordPress site to Genwave.

== External services ==

This plugin connects to services run by Genwave. Everything is sent over an encrypted (HTTPS) connection. Genwave's [Terms of Service](https://genwave.ai/terms/) and [Privacy Policy](https://genwave.ai/privacy/) apply to all of them.

= Genwave account app (app.genwave.ai) =

Used to connect your site to your Genwave account, to list and install Genwave plugins, and for the account pages the plugin links to.

* **When you click "Connect to Genwave":** your browser opens app.genwave.ai with your site's address, the address to come back to, and a one-time code that ties the request to you. After you approve, this plugin sends app.genwave.ai that one-time code and your site's address, and receives your license key. It then sends the license key and your site's address to finish the connection. During this step app.genwave.ai checks back with your site to confirm the plugin is installed there. Your license key is never put in a web address.
* **When you paste a license key instead:** the license key and your site's address are sent.
* **When you disconnect:** the license key and your site's address are sent, so the license can be used again.
* **When you open Genwave → Plugins:** the plugin asks app.genwave.ai for the list of Genwave plugins and sends your site's domain, your server's IP address and your license key. The list is kept on your site for a short time.
* **When you install a Genwave plugin from your Genwave account:** app.genwave.ai sends your site a request signed with a key only your site and your account share. It names one Genwave plugin, which this plugin downloads from cdn.genwave.ai and activates. Requests that are not signed for your site are refused. To show you what is installed, your site answers these requests with the Genwave plugins it has, whether they are active and their versions, and your WordPress and PHP versions.

= Genwave AI service (agent.genwave.ai) =

Used to write text for you and to read your plan's usage. Nothing is sent before your site is connected.

* **When you ask for AI writing:** the plugin sends the title, content, short description and type of the post or product you picked, the field you want written, the language you chose, your instructions, your site's domain and your license key.
* **When the usage is refreshed:** your site's domain and your license key are sent.

Each request is signed with your site's own key, so the service can check it came from your site.

= Genwave downloads (cdn.genwave.ai) =

Where Genwave plugins are downloaded from when you install one from your Genwave account. Only the download itself is requested. No information about your site is sent with it.
