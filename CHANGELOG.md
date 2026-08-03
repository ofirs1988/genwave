# Changelog

Developer-facing changelog for the GenWave (free / anchor) plugin. The customer
changelog for WordPress.org lives in `readme.txt` and is written at release time
(security items are described there generically, not by vulnerability).

## Unreleased

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
