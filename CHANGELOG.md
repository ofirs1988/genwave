# Changelog

Developer-facing changelog for the GenWave (free / anchor) plugin. The customer
changelog for WordPress.org lives in `readme.txt` and is written at release time
(security items are described there generically, not by vulnerability).

## Unreleased

### Fixed
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
