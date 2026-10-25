# Changelog

Developer-facing changelog for the GenWave (free / anchor) plugin. The customer
changelog for WordPress.org lives in `readme.txt` and is written at release time
(security items are described there generically, not by vulnerability).

## 1.3.1

### Changed

- **This plugin no longer installs or activates other plugins (WordPress.org
  guideline 8).** `Core\SiteCommands` keeps only the read-only `op=status`
  (which Genwave plugins are installed/active, WP and PHP versions) and now
  always reports `can_install: false`. `op=install` no longer downloads from
  cdn.genwave.ai or calls `Plugin_Upgrader`/`activate_plugin`; it answers 422
  `cannot_install` with the manual path, so accounts built for 1.3.0 show
  their zip fallback. The `PLUGINS` map of CDN URLs is gone.
- **No auto-install after connecting.** `PanelConnect::start()` sends
  `agent=0`, so the approval page no longer offers "Also install Genwave
  Agent", and the callback ignores `install_agent`: no more redirect to
  `app.genwave.ai/sites/install?...&auto=1`. The admin lands on the Account
  screen.
- **Missing Genwave plugins link to the account.** `Links::install()`,
  `Links::download()` and `Links::agentDownload()` are removed;
  `Links::getPlugins()` (app.genwave.ai/connect, the page with every Genwave
  plugin's zip) and `Links::uploadPlugin()` (Plugins > Add New > Upload
  Plugin) replace them. `AgentPlugin::url()` for a missing agent and
  `PluginsHandler` (`get_url`, no `install_url`/`zip_url`) use them. Account,
  Overview and Plugins screens say plainly that the agent is not on
  WordPress.org and is uploaded by hand ("Get Genwave Agent" + "Upload
  Plugin").
- **`GENWAVE_API_URL` defaults to https://app.genwave.ai.** account.genwave.ai
  is retired. Nothing in this plugin requests that base (`ApiManager`'s
  request methods have no caller here); the constant stays for older Genwave
  plugins.
- `.distignore`: `webpack.config.js` now ships (build tools with the source,
  guideline 4); `.wordpress-org/` (listing assets) is excluded from trunk.
- `wporg-release.yml`: new `version_type: none` releases the version already in
  the files and keeps an existing readme changelog entry; the bump commit is
  skipped when nothing changed. Note the deploy mirrors `.wordpress-org/` into
  SVN `assets/` (with delete), so banners and icons are kept there too.

### Fixed

- **AI writing could not write a short description.** The app sent
  `generation_method=shortDescription`; `GenerationHandler` allows only
  `title`, `short_description`, `description`, so it always answered "Invalid
  generation method". `Generate.jsx` now sends `short_description` (saving still
  uses the `shortDescription` field name `genwave_apply_content` expects).
  `GenerationHandler` then passed `short_description` on to the brain's
  `/generate-single`, which knows only `shortDescription` and fell back to a
  full description (the edit-screen AI box had the same problem); it now sends
  `shortDescription`.
- **AI box on edit screens said "Connection Required" on connected sites.**
  `MetaBox` only checked the legacy `uidd`; it now uses
  `AgentAuth::isConnected()` (legacy `uidd` still counts).
- **Plugin Check:** the four `parse_url()` calls in `AgentAuth` are
  `wp_parse_url()`. Plugin Check 2.1.0 on a release-like build: 0 errors,
  0 warnings.

### Removed

- `assets/js/streaming-client.js` (old LiteLLM client, dead since 1.1.2, with
  a localhost:8000 fallback) and its enqueue/`genwaveConfig` localize, plus the
  `Enqueue` helpers only it used. The unused `plugins_nonce` in
  `genwave_admin_data` is gone too.
- `POST gen-wave/v1/responses` no longer accepts the license key in an
  `X-Genwave-API-Key` header. No client sends it (searched the brain, portal,
  dashboard, liteLLM and the Genwave plugins).

### Security

- **`POST gen-wave/v1/responses` is admin-only (audit G9).** It writes rows
  into `gen_requests_posts` for any post id and used to allow any user with
  `edit_posts` (authors, contributors). It now uses `check_admin_permission`
  (`manage_options`), like `GET response-data` and `GET converting-products`
  that read those rows back. Nothing in the plugin calls the route, so no
  screen changes. `AiRestApi::check_api_permission()` is removed (no other
  user).

### Docs

- readme: version 1.3.1, how to add Genwave Agent by hand, a FAQ on why the
  plugin does not install it, External services updated (account pages on
  app.genwave.ai, read-only status requests, no cdn.genwave.ai downloads),
  screenshot caption 4 matches the Plugins screen. Listing assets now live in
  `.wordpress-org/` (banners, icons and the current screenshots copied from
  SVN so the deploy's asset sync deletes nothing).
- New screenshots 1-5 in English (1280x900, taken on a hosted test site
  running this build with the admin temporarily in English): Account, Overview,
  AI writing with a generated description (not saved), Genwave plugins, and
  Genwave Agent answering a read-only question about the store. The license
  key on the Account screen is masked. Captions 1, 3 and 5 updated; caption 5
  says Genwave Agent is a separate plugin added from the Genwave account.
- **WordPress.org readme rewritten to match the 1.3.0 code** (readme only, no
  code change, no version bump; meant for an in-place update of
  `trunk/readme.txt` and `tags/1.3.0/readme.txt` on SVN). The description now
  lists what this plugin really does (connect, AI writing screen, Genwave
  plugins installed from the account, Overview, Maintenance, light/dark, RTL)
  and keeps Genwave Agent claims to the separate plugin. Removed: hosting
  offer, "included in every paid plan", "any language", "full activity log"
  and "undo" as claims of this plugin. Plans are named with a link to
  /pricing, no numbers. FAQ languages list the 15 AI-writing languages in
  `Generate.jsx` and the two UI languages (English, Hebrew). "Third Party
  Services" became "External services" with what is sent, when, per host
  (app.genwave.ai, agent.genwave.ai, cdn.genwave.ai), including the server IP
  sent with the plugin list and the WP/PHP versions returned to signed
  commands. Tags: `chatbot`/`ecommerce` replaced by `ai-writing`/`woocommerce`.
  Screenshot captions now match the 1.3.0 screens. Changelog history: em
  dashes removed, "250+ actions", retired product names (Pro, SEO, Site
  Transfer) and the dead account.genwave.ai link dropped.

## 1.3.0

### Changed

- **The Genwave screens in the Genwave Agent's design.** Overview, Account,
  AI writing, Plugins and Maintenance are one full-screen app
  (`views/src/app`, `Core\AdminApp`) in the shell and tokens of the Agent and
  the Chatbot: grouped navigation, "Back to WordPress" (Esc), light and dark
  themes (the shared `genwave-theme` setting), RTL, English and Hebrew. Each
  screen keeps its wp-admin page; moving between them does not reload. Fonts
  (Outfit, Assistant) are bundled in `assets/fonts` with their OFL licence.
  These screens load no Bootstrap, SweetAlert, Font Awesome or Select2; those
  stay only on post and product edit screens for the AI box. Connect results
  show inside the app (`PanelConnect::takeNotice`). Removed `views/settings.php`
  and the old React pages and stylesheet.

- **Genwave plugins are installed from the Genwave account, not from inside
  WordPress** (WordPress.org guideline 8: a plugin hosted there may not install
  plugins from other servers itself; a management service that pushes software
  from its own domain may). New `Core\SiteCommands`: `POST gen-wave/v1/command`,
  signed by app.genwave.ai with `HMAC-SHA256(site_key, "genwave-site-command-v1")`
  over `POST\n/gen-wave/v1/command\nsha256(body)\nts\nnonce` (±300 s, nonce
  once, `site` in the body must be this site's uid). Ops: `status` (the Genwave
  plugins here, `can_install`) and `install` (slug from a fixed list, zip from
  cdn.genwave.ai, then activate). The command never carries a URL.
- The Plugins page and every "Install Genwave Agent" button link to
  `app.genwave.ai/sites/install?plugin=&site=&return=` (`Links::install`), with
  the zip as the other way. A plugin already here is activated with WordPress's
  own link. After "Connect to Genwave" with the Agent ticked, the admin goes to
  that page (it installs at once) and comes back to the chat.
- Removed the `genwave_install_plugin` AJAX action, the in-page install after
  connecting (`admin.js`, `PanelConnect::takeAgentInstall`) and
  `PluginsHandler::install_and_activate`.
- **Nothing calls account.genwave.ai any more.** The plugin list comes from
  `app.genwave.ai/api/plugins`; a pasted license key connects at once through
  `AgentAuth::connect()` (the separate `/validate-license` step is gone).
- Removed dead code that pointed at the old dashboard: the integration sign-in
  flow (`IntegrationCallbackController`, `VerifyLoginController::redirectLogin`/
  `getUrl`), `PollingHandler` and the unhooked polling methods in `AjaxManager`,
  `InstantGeneratorSettings` and its script, `Helper\License`, the `apiUrl`
  script value, the update-server rows on the Tools page, and the
  `Access-Control-Allow-*` headers for account.genwave.ai sent on every request.
  `GENWAVE_API_URL` stays defined for the premium plugins.

## 1.2.2 - 2026-10-25

### Security

- **REST authentication filter no longer clears core's cookie check.**
  `AiRestApi::allow_own_routes_through` (`rest_authentication_errors`, 9999)
  cleared any denial when the raw `REQUEST_URI` contained `/wp-json/gen-wave/v1/`,
  query string included, and that also cleared `rest_cookie_invalid_nonce`. With
  a wrong nonce WordPress keeps the cookie user logged in, so a request such as
  `/wp-json/wp/v2/users?x=/wp-json/gen-wave/v1/` from another site ran as the
  logged-in administrator (CSRF on every core route). Now
  `rest_cookie_invalid_nonce` is never cleared, and "ours" is the route WordPress
  parsed (`rest_route` query var, or `$_GET['rest_route']` on plain permalinks)
  starting with `/gen-wave/v1/`.
- **verify-domain answers a challenge only while the site is connecting.**
  `AgentAuth::connect()` / `disconnect()` open a five-minute window
  (`genwave_challenge_open` transient) before calling the panel and close it
  after; outside it `GET gen-wave/v1/verify-domain?challenge=` answers 403.
  Before, anyone holding the license key could have the panel connect the site
  for them and receive its signing key. Connect to Genwave (`PanelConnect`) goes
  through `connect()`, so it is covered.
- The legacy `token` echo of verify-domain is gone (only the retired
  account.genwave.ai flow used it): a request without a challenge gets 400.
- The license key is no longer written to `debug.log` (`GenerationHandler`,
  under `WP_DEBUG`); only whether one is set.
- `genwave_get_post_data` (AJAX) is logged-in only and checks `edit_post` for the
  post asked for; a nonce alone no longer reads any post.
- Removed the `genwave_polling_results` and `genwave_poll_proxy` AJAX actions
  (priv and nopriv): nothing called them but the unused streaming client.

## 1.2.1 - 2026-10-02

### Changed

- Copy without counts or superlatives, as on genwave.ai: the plugin description
  (Plugins screen) no longer says "#1", "250+ actions", "7-layer security" or
  "48+ languages", and the Account page's feature tile reads "Every area of
  WordPress" instead of "250+ actions".
- The "Don't have an account? Sign up free" link is gone from the Account page:
  "Connect to Genwave" takes a visitor without an account through sign-up.

## 1.2.0 - 2026-10-02

### Added

- **Connect to Genwave, no key to copy** (`Core\PanelConnect`). The Account
  page leads with one button. `start()` (nonced admin-post, `manage_options`)
  mints a one-time `state` and a PKCE verifier, keeps them in a per-user
  transient (30 min, long enough to sign up and confirm the email on the way)
  and sends the browser to `{panel}/connect/authorize` with the site address,
  the state and sha256(verifier). The customer signs in or signs up there and
  approves the site; the panel sends the browser back with a two-minute,
  single-use, encrypted code. `maybeHandleCallback()` (admin_init) checks the
  state against this admin's transient, burns it, and redeems the code at
  `/api/plugin/connect/exchange` with the verifier, server to server. The
  answer is the license key, and `AgentAuth::connect()` finishes the
  connection exactly as a pasted key would (HMAC challenge, per-site signing
  key). If that fails the previous key is put back. The license key never
  appears in a URL, a code is useless without the verifier, and a callback
  this admin did not start is rejected.
- **Genwave Agent in the same step.** When the Agent is missing and the admin
  may install plugins, the approval offers it (ticked). Back on the Account
  page it is installed through the existing one-click route with progress on
  screen, then the chat opens; if that fails the page says so and links to
  the manual install.
- **The Account page opens after activation** (`genwave_activation_redirect`
  transient), not on bulk or network activation, and not when already
  connected.

### Changed

- Pasting the license key is now the fallback, under "Have an API key?
  Connect with it instead" (open when a key is already saved).

## 1.1.2 – 1.1.9

### Changed

- **No credits to count.** The admin bar, the settings screen, the Dashboard
  and content generation no longer show a credit balance or cost. The admin
  bar reads the plan state the Genwave Agent keeps
  (`genwave_agent_plan_state`, `AdminBar::usage_label()`): nothing on a paid
  plan, "Light mode" when a month went far past normal use (the lighter model
  until renewal), about how many AI actions are left on the Free plan. It links
  to the usage page instead of the credit shop.

### Fixed

- **The credit balance never refreshed on recently connected sites.**
  `TokensController::RefreshCredits()` required the old `token` option, which
  the current connection (`AgentAuth::connect`, site_uid + site_key) never
  sets, so it answered "Connect your Genwave account first" on a connected
  site. It now requires `AgentAuth::isConnected()` and sends no Bearer header:
  the agent accepts only signed requests since 2026-09-27, and this call is
  signed on its way out.

### Changed

- The brand name reads "Genwave" everywhere a person reads it (it said "GenWave").

### Changed
- readme.txt rewritten for site owners in the voice of genwave.ai: short plain
  sentences, no em dashes or marketing filler, and the same terms as the site
  (Genwave Agent, Studio, Chatbot, "you see it first", "bound by your role",
  undo for content edits, activity log, keep your hosting). Removed claims we
  could not stand behind (daily free messages, named models, "7-layer
  security", "never store"). Third Party Services now lists what the code
  really calls: app.genwave.ai, account.genwave.ai, agent.genwave.ai (not
  api.genwave.ai). Upgrade notices for 1.1.5 and 1.1.6.
- In-plugin notes about installing Genwave Agent reworded without dashes.

### Fixed
- Genwave → Plugins told sites connected the current way "Connect your Genwave
  account first", because it still required the old token and uidd, which the
  current connection never stores. It now works for any connected site, so the
  agent really is one click away. It also installs only from Genwave's own
  download hosts (cdn.genwave.ai, the dashboard host, downloads.wordpress.org),
  whatever the list says.
- When Genwave Agent is missing, the Account and Dashboard buttons now say
  "Install GenWave Agent" and open that page, instead of handing over a zip
  file to upload. Users who cannot install plugins still get the download link
  and are asked to contact an administrator. readme Installation and FAQ follow.
- readme.txt Installation and FAQ (the tabs in wp-admin's plugin details): the
  steps now match the real connect flow (license key, "Connect to GenWave") and
  say that Genwave Agent is a separate plugin, where to get it and to keep this
  one installed - the step a customer got stuck on. New FAQ entry for "Open the
  Agent says I am not allowed". Links point at genwave.ai/agent, /pricing and
  app.genwave.ai/support instead of the home page.
- Sites on WordPress 7.1 saw "This plugin has not been tested with your current
  version of WordPress": readme.txt said `Tested up to: 7.0`. It now says 7.1,
  and the WordPress.org release workflow sets it to the current WordPress
  release on every run, so it cannot fall behind again.
- The release workflow passes the changelog through the environment. Pasted
  into the script, a double quote in it ended the string and ran the rest as a
  shell command; 1.1.5's first run failed that way before deploying anything.

### Fixed
- "Open the Agent" no longer lands on "Sorry, you are not allowed to access this
  page". The agent chat is the separate GenWave Agent plugin, and the button
  linked to its admin page even on sites where it was not installed - an
  administrator reported it from a live site after reinstalling everything. A new
  `Core\AgentPlugin` reads where that plugin stands, and both the Dashboard and
  the Account page follow it: **Open the Agent** when it is active, **Activate
  GenWave Agent** (nonced activation link) when it is installed but inactive, and
  **Download GenWave Agent** plus an upload link and a short explanation when it
  is missing. The Account page's "Getting started" step changes with it.
- The Dashboard link no longer hardcodes `/wp-admin/`, which broke on sites
  installed in a subdirectory; it comes from `admin_url()`.
- Every button that opens a GenWave page now reaches that page. They pointed at
  account.genwave.ai, the old dashboard, which now sends everything to the app's
  sign-in screen and drops the path: "Sign up free" opened a login form, and
  Renew License, Buy more credits, Support and the Pro upgrade button all landed
  on sign-in instead of their page. The Dashboard's "Learn more" was a 404. A new
  `Core\Links` holds them all, pointing at app.genwave.ai (which returns a
  signed-out visitor to the page they asked for after sign-in) and at
  genwave.ai/agent; it follows `GENWAVE_PANEL_URL` like the connect flow. The
  agent download goes through the app's tracked-download route, so downloads
  from the plugin are counted with the others. API calls are unchanged.

## 1.1.1 - 2026-08-03

### UI, copy & assets
- Modernized the Account, Plugins and Generate admin pages; rebuilt the React bundle.
- Rewrote the readme.txt description and in-plugin copy to read naturally (removed marketing/AI-tell phrasing); added the full product description and a "Why Genwave Is Different" section with site/account links.
- New WordPress.org banner (1544x500 / 772x250) and icon (128/256); corrected stale "AES-256-CBC" claims in the readme to reflect HTTPS/TLS transport (that application-level encryption was removed in the auth-code migration).
- Genericized user-facing error messages so no internal service names (LiteLLM, endpoints, "agent backend") leak; detail retained in debug logs.
- Repointed content generation and credit balance to the agent backend endpoints.
- Plugin Check: added a translators comment for a placeholder string and a justified nonce `phpcs:ignore` in PluginsHandler.

### Security — hardening pass
- **Removed the hardcoded shared AES key.** It shipped in this public plugin
  (`gen-wave.php`, `Config.php`) and was therefore public. Credentials now travel
  in plaintext over the authenticated, one-time `credentials_session` channel and
  are stored as-is; `EncryptionService` and the client-side decrypt are gone.
- **`verify_login`** now requires `manage_options` and the unauthenticated
  (`nopriv`) hook was removed — the auto-login URL carries the owner's SaaS session.
- **`response-data`** REST endpoint is admin-only (was any logged-in user — an
  IDOR that leaked arbitrary post content).
- **Connect flow** gained an anti-CSRF `state` token bound to the initiating admin,
  and the legacy `$_GET` credential handler was removed (session-fixation / CSRF).
- **Image sideload** validates the URL against internal/reserved IPs before
  fetching (SSRF guard, incl. the cloud-metadata address).
- **Disconnect** and the credit-balance actions require `manage_options`; the
  unauthenticated credit-balance write path was removed.
- `verify-domain` no longer discloses the exact plugin version to anonymous callers.

### Changed — credits + content generation run on the agent backend, not liteLLM
- Credit balance is fetched from the agent backend (`GET /credits`) and cached in
  the shared `aiaw_credits` option, so the admin bar, Account page and Dashboard
  all show the same, synced number — no dashboard round-trip, works even without
  the agent plugin.
- Content generation (Generate page) calls the agent backend
  (`POST /generate-single`) instead of the retired liteLLM service.
- Added the `GENWAVE_AGENT_API_URL` constant (prod default `agent.genwave.ai`) and
  removed the retired `GEN_WAVE_SMART_API` (liteLLM) define.

### Changed — UI refresh (Deep Ocean)
- Redesigned the **Plugins**, **Dashboard** and **Generate** React pages and the
  **Account** page: cleaner cards, removed the Pro/upsell and empty "locked"
  clutter, one restrained cyan/blue accent. The credits mark matches the agent's.
- The Plugins marketplace hides retired products (Pro, SEO, Transfer) and drops
  the "Paid" tag.

### Removed — dead code
- Deleted a ~580-line dead duplicate content-generation cluster in `AjaxManager`
  and guarded the retired Pro views path in `ViewManager`.
