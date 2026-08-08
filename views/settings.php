<?php
if (!defined('ABSPATH')) {
    exit;
}

$genwave_is_connected = isset($data['uidd']) && !is_null($data['uidd']) && strlen($data['uidd']) > 3;
$genwave_has_license = strlen($data['license_key'] ?? '') > 10;
$genwave_is_expired = isset($data['license_expired']) && $data['license_expired'] === '1';
// Balance is synced with the agent: prefer the shared `aiaw_credits` option
// (written by both the agent and the anchor's RefreshCredits), fall back to the
// anchor's own copy.
$genwave_credits = get_option('aiaw_credits', $data['credits'] ?? 0);
?>
<section class="gw-acct">
    <div class="gw-acct__shell">

        <!-- Header -->
        <header class="gw-acct__head">
            <div class="gw-acct__brand">
                <span class="gw-acct__mark">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z"/>
                        <path d="M2 17L12 22L22 17"/>
                        <path d="M2 12L12 17L22 12"/>
                    </svg>
                </span>
                <div>
                    <span class="gw-acct__brand-name"><?php esc_html_e('GenWave', 'gen-wave'); ?></span>
                    <span class="gw-acct__brand-sub"><?php esc_html_e('Account', 'gen-wave'); ?></span>
                </div>
            </div>
            <?php if ($genwave_is_connected && !$genwave_is_expired): ?>
                <span class="gw-acct__status is-ok"><span class="gw-acct__dot"></span><?php esc_html_e('Connected', 'gen-wave'); ?></span>
            <?php elseif ($genwave_is_connected && $genwave_is_expired): ?>
                <span class="gw-acct__status is-warn"><span class="gw-acct__dot"></span><?php esc_html_e('License Expired', 'gen-wave'); ?></span>
            <?php else: ?>
                <span class="gw-acct__status is-off"><span class="gw-acct__dot"></span><?php esc_html_e('Not Connected', 'gen-wave'); ?></span>
            <?php endif; ?>
        </header>

        <div class="gw-acct__body">

            <!-- Alerts -->
            <div id="gw-alerts">
                <?php if (isset($message) && strlen($message) > 0) : ?>
                    <div class="gw-note gw-note--ok">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span><?php echo esc_html($message); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($genwave_is_expired): ?>
                    <div class="gw-note gw-note--warn gw-alert gw-alert-warning">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <div class="gw-note__body gw-alert-content">
                            <strong><?php esc_html_e('License expired', 'gen-wave'); ?></strong>
                            <p><?php esc_html_e('Renew now to keep using the AI Agent.', 'gen-wave'); ?></p>
                            <div class="gw-note__actions gw-alert-buttons">
                                <a href="<?php echo esc_url(GENWAVE_API_URL . '/user/billing'); ?>" target="_blank" class="gw-b gw-b--warn gw-b--sm"><?php esc_html_e('Renew License', 'gen-wave'); ?></a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($genwave_is_connected): ?>
                <!-- ===== Connected ===== -->

                <div class="gw-acct__confirm">
                    <span class="gw-acct__confirm-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <?php esc_html_e('Your account is connected. Manage your site through the GenWave Agent chat.', 'gen-wave'); ?>
                </div>

                <div class="gw-acct__cols">
                    <!-- Credit balance -->
                    <div class="gw-panel gw-panel--accent gw-card-stats">
                        <div class="gw-panel__row">
                            <span class="gw-panel__label"><?php esc_html_e('Credit balance', 'gen-wave'); ?></span>
                            <button type="button" id="refresh_credits" class="gw-iconbtn" title="<?php esc_attr_e('Refresh balance', 'gen-wave'); ?>">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                            </button>
                        </div>
                        <div class="gw-panel__value" id="credit-balance"><?php echo number_format(floor((float)$genwave_credits * 100) / 100, 2); ?></div>
                        <a href="<?php echo esc_url(GENWAVE_API_URL . '/user/plans'); ?>" target="_blank" class="gw-b gw-b--soft gw-b--sm gw-b--block"><?php esc_html_e('Buy more credits', 'gen-wave'); ?></a>
                    </div>

                    <!-- Account details -->
                    <div class="gw-panel gw-card">
                        <span class="gw-panel__label"><?php esc_html_e('Account details', 'gen-wave'); ?></span>
                        <dl class="gw-kv">
                            <div class="gw-kv__row">
                                <dt><?php esc_html_e('License key', 'gen-wave'); ?></dt>
                                <dd><code><?php $genwave_license_key = $data['license_key']; echo esc_html(substr($genwave_license_key, 0, 8) . '••••' . substr($genwave_license_key, -4)); ?></code></dd>
                            </div>
                            <div class="gw-kv__row">
                                <dt><?php esc_html_e('Status', 'gen-wave'); ?></dt>
                                <dd>
                                    <?php if ($genwave_is_expired): ?>
                                        <span class="gw-tag gw-tag--warn"><?php esc_html_e('Expired', 'gen-wave'); ?></span>
                                    <?php else: ?>
                                        <span class="gw-tag gw-tag--ok"><?php esc_html_e('Active', 'gen-wave'); ?></span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        </dl>
                        <div class="gw-panel__foot gw-card-footer">
                            <a href="<?php echo esc_url(GENWAVE_API_URL); ?>" target="_blank" class="gw-b gw-b--primary gw-b--sm"><?php esc_html_e('Manage account', 'gen-wave'); ?></a>
                            <button type="button" id="disconnect_account" class="gw-b gw-b--danger gw-b--sm"><span class="gw-btn-text"><?php esc_html_e('Disconnect', 'gen-wave'); ?></span></button>
                        </div>
                    </div>
                </div>

                <!-- Quick start (secondary) -->
                <div class="gw-panel gw-panel--muted">
                    <span class="gw-panel__label"><?php esc_html_e('Getting started', 'gen-wave'); ?></span>
                    <ol class="gw-steps">
                        <li><strong><?php esc_html_e('Open the GenWave Agent', 'gen-wave'); ?></strong><span><?php esc_html_e('Find it in your WordPress admin menu.', 'gen-wave'); ?></span></li>
                        <li><strong><?php esc_html_e('Describe what you need', 'gen-wave'); ?></strong><span><?php esc_html_e('Plain language — build pages, manage products, fix errors.', 'gen-wave'); ?></span></li>
                        <li><strong><?php esc_html_e('Review &amp; approve', 'gen-wave'); ?></strong><span><?php esc_html_e('You see a preview before anything changes.', 'gen-wave'); ?></span></li>
                    </ol>
                </div>

            <?php else: ?>
                <!-- ===== Not connected ===== -->

                <div class="gw-acct__hero">
                    <h2><?php esc_html_e('Connect your GenWave account', 'gen-wave'); ?></h2>
                    <p><?php esc_html_e('Add the AI Agent to your site to build pages, fix errors, and manage everything through conversation.', 'gen-wave'); ?></p>
                </div>

                <div class="gw-acct__features">
                    <div class="gw-feat"><span class="gw-feat__ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span><div><strong><?php esc_html_e('Natural conversation', 'gen-wave'); ?></strong><span><?php esc_html_e('Run your whole site by talking to the AI.', 'gen-wave'); ?></span></div></div>
                    <div class="gw-feat"><span class="gw-feat__ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></span><div><strong><?php esc_html_e('250+ actions', 'gen-wave'); ?></strong><span><?php esc_html_e('Pages, WooCommerce, plugins and error fixing.', 'gen-wave'); ?></span></div></div>
                    <div class="gw-feat"><span class="gw-feat__ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span><div><strong><?php esc_html_e('Safe by design', 'gen-wave'); ?></strong><span><?php esc_html_e('A preview before every change to your site.', 'gen-wave'); ?></span></div></div>
                </div>

                <div class="gw-panel">
                    <span class="gw-panel__label"><?php esc_html_e('Quick setup', 'gen-wave'); ?></span>

                    <div class="gw-wiz">
                        <!-- Step 1 -->
                        <div class="gw-wiz__step <?php echo $genwave_has_license ? 'is-done' : 'is-active'; ?>">
                            <div class="gw-wiz__head">
                                <span class="gw-wiz__num">
                                    <?php if ($genwave_has_license): ?>
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                    <?php else: ?>1<?php endif; ?>
                                </span>
                                <span class="gw-wiz__title"><?php esc_html_e('Enter your license key', 'gen-wave'); ?></span>
                            </div>
                            <form method="post" action="" class="gw-wiz__form">
                                <?php wp_nonce_field('save_ai_settings', 'ai_settings_nonce'); ?>
                                <div class="gw-field">
                                    <input type="hidden" id="hiddenLicenseKey" value="<?php echo esc_attr($data['license_key']); ?>" />
                                    <input type="text" name="ai_license_key" id="licenseKey" class="gw-input" placeholder="XXXX-XXXX-XXXX-XXXX" value="<?php echo esc_attr($data['license_key']); ?>" />
                                    <div id="input-loader" class="gw-input-loader" style="display:none;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                                    </div>
                                </div>
                                <p class="gw-field__hint"><?php esc_html_e('Find your license key in your GenWave account.', 'gen-wave'); ?></p>
                                <?php if (!$genwave_has_license): ?>
                                    <button type="submit" name="save_settings" class="gw-b gw-b--primary gw-b--block"><?php esc_html_e('Save license key', 'gen-wave'); ?></button>
                                <?php else: ?>
                                    <button type="submit" name="save_settings" class="gw-b gw-b--ghost gw-b--sm"><?php esc_html_e('Update key', 'gen-wave'); ?></button>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Step 2 -->
                        <?php if ($genwave_has_license): ?>
                        <div class="gw-wiz__step is-active">
                            <div class="gw-wiz__head">
                                <span class="gw-wiz__num">2</span>
                                <span class="gw-wiz__title"><?php esc_html_e('Connect your account', 'gen-wave'); ?></span>
                            </div>
                            <p class="gw-wiz__desc"><?php esc_html_e('You will be redirected to log in and authorize this site.', 'gen-wave'); ?></p>
                            <button type="button" id="verify_by_login" class="gw-b gw-b--primary gw-b--block gw-b--lg">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                                <?php esc_html_e('Connect to GenWave', 'gen-wave'); ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="gw-acct__help">
                    <a href="<?php echo esc_url(GENWAVE_API_URL . '/register'); ?>" target="_blank"><?php esc_html_e("Don't have an account? Sign up free", 'gen-wave'); ?></a>
                    <a href="<?php echo esc_url(GENWAVE_API_URL . '/support'); ?>" target="_blank"><?php esc_html_e('Need help? Contact support', 'gen-wave'); ?></a>
                </div>

            <?php endif; ?>
        </div>

        <!-- Footer -->
        <footer class="gw-acct__foot">
            <span><?php esc_html_e('GenWave', 'gen-wave'); ?> · v<?php echo esc_html(defined('GEN_WAVE_VERSION') ? GEN_WAVE_VERSION : '1.0.0'); ?></span>
            <a href="<?php echo esc_url(GENWAVE_API_URL . '/support'); ?>" target="_blank"><?php esc_html_e('Support', 'gen-wave'); ?></a>
        </footer>
    </div>
</section>

<style>
/* ===== GenWave Account — clean, professional layout ===== */
.gw-acct {
    --a1: #06b6d4;
    --a2: #3b82f6;
    --ink: #0f172a;
    --muted: #64748b;
    --line: #e6e8ec;
    --bg: #f8fafc;
    --radius: 14px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
}
.gw-acct * { box-sizing: border-box; }

.gw-acct__shell {
    max-width: 680px;
    margin: 22px auto;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 20px 45px -30px rgba(15, 23, 42, 0.35);
}

/* Header */
.gw-acct__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 24px;
    color: #fff;
    background:
        radial-gradient(120% 180% at 100% 0%, rgba(255,255,255,0.18), transparent 55%),
        linear-gradient(120deg, #0e7490 0%, var(--a1) 45%, var(--a2) 100%);
}
.gw-acct__brand { display: flex; align-items: center; gap: 12px; }
.gw-acct__mark {
    width: 40px; height: 40px; border-radius: 11px;
    display: grid; place-items: center;
    background: rgba(255,255,255,0.16);
    border: 1px solid rgba(255,255,255,0.25);
}
.gw-acct__brand-name { display: block; font-size: 16px; font-weight: 700; line-height: 1.1; }
.gw-acct__brand-sub { display: block; font-size: 12px; opacity: 0.85; }

.gw-acct__status {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 6px 12px; border-radius: 999px;
    font-size: 12.5px; font-weight: 600;
    background: rgba(255,255,255,0.16);
    border: 1px solid rgba(255,255,255,0.25);
}
.gw-acct__dot { width: 7px; height: 7px; border-radius: 50%; background: #fff; }
.gw-acct__status.is-ok .gw-acct__dot { background: #4ade80; box-shadow: 0 0 0 3px rgba(74,222,128,0.3); }
.gw-acct__status.is-warn .gw-acct__dot { background: #fbbf24; }
.gw-acct__status.is-off .gw-acct__dot { background: #f87171; }

/* Body */
.gw-acct__body { padding: 24px; display: flex; flex-direction: column; gap: 16px; background: var(--bg); }

/* Notes / alerts */
.gw-note {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 13px 15px; border-radius: 11px; font-size: 13.5px;
    border: 1px solid transparent;
}
.gw-note svg { flex-shrink: 0; margin-top: 1px; }
.gw-note--ok { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
.gw-note--ok svg { stroke: #10b981; }
.gw-note--warn { background: #fffbeb; color: #92400e; border-color: #fde68a; }
.gw-note--warn svg { stroke: #f59e0b; }
.gw-note__body { flex: 1; }
.gw-note__body strong { display: block; margin-bottom: 2px; }
.gw-note__body p { margin: 0 0 10px; font-size: 13px; opacity: 0.9; }
.gw-note__actions { display: flex; gap: 8px; flex-wrap: wrap; }

/* Connected confirm strip */
.gw-acct__confirm {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 15px; border-radius: 11px;
    font-size: 13.5px; color: var(--ink);
    background: #fff; border: 1px solid var(--line);
}
.gw-acct__confirm-icon {
    width: 26px; height: 26px; border-radius: 8px; flex-shrink: 0;
    display: grid; place-items: center; color: #fff;
    background: linear-gradient(135deg, #10b981, #059669);
}

/* Two-column card row */
.gw-acct__cols { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Panels (unified card) */
.gw-panel {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.gw-panel--muted { background: #fff; }
.gw-panel__label {
    font-size: 11.5px; font-weight: 700; letter-spacing: 0.06em;
    text-transform: uppercase; color: var(--muted);
}
.gw-panel__row { display: flex; align-items: center; justify-content: space-between; }
.gw-panel--accent {
    position: relative; overflow: hidden;
    background:
        radial-gradient(120% 130% at 100% 0%, rgba(59,130,246,0.10), transparent 60%),
        linear-gradient(180deg, #fff, #f6fbff);
    border-color: rgba(59,130,246,0.22);
}
.gw-panel__value {
    font-size: 40px; font-weight: 800; line-height: 1; letter-spacing: -0.02em;
    color: var(--ink);
    background: linear-gradient(120deg, var(--ink), #155e75 70%, var(--a1));
    -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
}
.gw-panel__foot { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 2px; }

/* Key/value list */
.gw-kv { margin: 0; display: flex; flex-direction: column; }
.gw-kv__row { display: flex; align-items: center; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid var(--line); }
.gw-kv__row:last-child { border-bottom: 0; }
.gw-kv dt { font-size: 13px; color: var(--muted); margin: 0; }
.gw-kv dd { margin: 0; font-size: 13px; font-weight: 600; color: var(--ink); }
.gw-kv code { font-family: ui-monospace, 'SF Mono', Monaco, monospace; font-size: 12px; background: var(--bg); padding: 3px 8px; border-radius: 6px; border: 1px solid var(--line); }

.gw-tag { display: inline-flex; padding: 3px 9px; border-radius: 7px; font-size: 11.5px; font-weight: 700; }
.gw-tag--ok { background: #dcfce7; color: #166534; }
.gw-tag--warn { background: #fef3c7; color: #92400e; }

/* Steps (getting started) */
.gw-steps { margin: 0; padding: 0; list-style: none; counter-reset: gw; display: flex; flex-direction: column; gap: 11px; }
.gw-steps li { position: relative; padding-left: 34px; counter-increment: gw; }
.gw-steps li::before {
    content: counter(gw); position: absolute; left: 0; top: 0;
    width: 22px; height: 22px; border-radius: 7px;
    display: grid; place-items: center; font-size: 11px; font-weight: 700; color: #0e7490;
    background: rgba(6,182,212,0.12);
}
.gw-steps strong { display: block; font-size: 13.5px; color: var(--ink); }
.gw-steps span { font-size: 12.5px; color: var(--muted); }

/* Not-connected hero */
.gw-acct__hero { text-align: center; padding: 6px 0 2px; }
.gw-acct__hero h2 { margin: 0 0 6px; font-size: 21px; font-weight: 800; letter-spacing: -0.02em; color: var(--ink); }
.gw-acct__hero p { margin: 0 auto; max-width: 46ch; font-size: 14px; line-height: 1.55; color: var(--muted); }

.gw-acct__features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.gw-feat { display: flex; flex-direction: column; gap: 8px; padding: 15px; background: #fff; border: 1px solid var(--line); border-radius: var(--radius); }
.gw-feat__ic { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, var(--a1), var(--a2)); }
.gw-feat strong { display: block; font-size: 13.5px; color: var(--ink); }
.gw-feat span { font-size: 12.5px; line-height: 1.45; color: var(--muted); }

/* Setup wizard */
.gw-wiz { display: flex; flex-direction: column; gap: 18px; }
.gw-wiz__step.is-done { opacity: 0.65; }
.gw-wiz__head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.gw-wiz__num { width: 26px; height: 26px; border-radius: 8px; display: grid; place-items: center; font-size: 12px; font-weight: 700; color: var(--muted); background: var(--bg); border: 1px solid var(--line); }
.gw-wiz__step.is-active .gw-wiz__num { color: #fff; background: linear-gradient(135deg, var(--a1), var(--a2)); border-color: transparent; }
.gw-wiz__step.is-done .gw-wiz__num { color: #fff; background: #10b981; border-color: transparent; }
.gw-wiz__title { font-size: 14.5px; font-weight: 700; color: var(--ink); }
.gw-wiz__desc { margin: 0 0 12px; padding-left: 36px; font-size: 13px; color: var(--muted); }
.gw-wiz__form { padding-left: 36px; }

.gw-field { position: relative; }
.gw-input { width: 100%; padding: 11px 14px; font-size: 14px; color: var(--ink); border: 1px solid var(--line); border-radius: 10px; transition: border-color 0.15s, box-shadow 0.15s; }
.gw-input:focus { outline: none; border-color: var(--a1); box-shadow: 0 0 0 3px rgba(6,182,212,0.14); }
.gw-field__hint { margin: 8px 0 12px; padding-left: 0; font-size: 12px; color: #94a3b8; }
.gw-input-loader { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: var(--a1); }
.gw-input-loader svg { animation: gwspin 1s linear infinite; }

/* Help */
.gw-acct__help { display: flex; flex-direction: column; gap: 8px; }
.gw-acct__help a { font-size: 13px; color: #0e7490; text-decoration: none; }
.gw-acct__help a:hover { text-decoration: underline; }

/* Footer */
.gw-acct__foot { display: flex; align-items: center; justify-content: space-between; padding: 14px 24px; font-size: 12.5px; color: var(--muted); background: #fff; border-top: 1px solid var(--line); }
.gw-acct__foot a { color: #0e7490; text-decoration: none; }
.gw-acct__foot a:hover { text-decoration: underline; }

/* Buttons */
.gw-b { display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 9px 16px; font-size: 13.5px; font-weight: 600; border-radius: 10px; border: 1px solid transparent; cursor: pointer; text-decoration: none; transition: transform 0.1s, box-shadow 0.2s, background 0.15s, filter 0.2s; }
.gw-b--block { width: 100%; }
.gw-b--sm { padding: 8px 13px; font-size: 12.5px; }
.gw-b--lg { padding: 13px 18px; font-size: 14.5px; }
.gw-b--primary { color: #fff; background: linear-gradient(135deg, var(--a1), var(--a2)); box-shadow: 0 10px 20px -12px rgba(59,130,246,0.7); }
.gw-b--primary:hover { filter: brightness(1.05); transform: translateY(-1px); color: #fff; }
.gw-b--soft { color: #0e7490; background: rgba(6,182,212,0.10); border-color: rgba(6,182,212,0.28); }
.gw-b--soft:hover { background: rgba(6,182,212,0.16); color: #0e7490; }
.gw-b--ghost { color: var(--ink); background: #fff; border-color: var(--line); }
.gw-b--ghost:hover { background: var(--bg); }
.gw-b--danger { color: #dc2626; background: #fff; border-color: #fecaca; }
.gw-b--danger:hover { background: #fef2f2; border-color: #f87171; color: #dc2626; }
.gw-b--warn { color: #fff; background: #f59e0b; }
.gw-b--warn:hover { background: #d97706; color: #fff; }
.gw-b.loading, .gw-b:disabled { opacity: 0.7; pointer-events: none; }
.gw-b.confirming { background: #dc2626 !important; border-color: #dc2626 !important; color: #fff !important; }

.gw-iconbtn { width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--line); background: #fff; color: var(--muted); cursor: pointer; display: grid; place-items: center; transition: background 0.15s, color 0.15s; }
.gw-iconbtn:hover { background: var(--bg); color: var(--ink); }
.gw-iconbtn.loading svg { animation: gwspin 1s linear infinite; }

/* Action messages (JS-injected) */
.gw-action-message { margin-top: 14px; padding: 12px 15px; border-radius: 10px; font-size: 13.5px; display: flex; align-items: center; gap: 9px; animation: gwslide 0.25s ease; }
.gw-action-message.loading { background: #eff6ff; color: #1e40af; }
.gw-action-message.success { background: #ecfdf5; color: #065f46; }
.gw-action-message.error { background: #fef2f2; color: #991b1b; }
.gw-action-message.warning { background: #fffbeb; color: #92400e; }
.gw-action-message.warning a { color: #0e7490; text-decoration: underline; margin-left: 4px; }

@keyframes gwspin { to { transform: translateY(-50%) rotate(360deg); } }
.gw-iconbtn.loading svg, .gw-b .anticon-spin { animation: gwspin2 1s linear infinite; }
@keyframes gwspin2 { to { transform: rotate(360deg); } }
@keyframes gwslide { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

/* Responsive */
@media (max-width: 640px) {
    .gw-acct__shell { margin: 12px; }
    .gw-acct__body { padding: 18px; }
    .gw-acct__cols { grid-template-columns: 1fr; }
    .gw-acct__features { grid-template-columns: 1fr; }
    .gw-wiz__form, .gw-wiz__desc { padding-left: 0; }
}
</style>
