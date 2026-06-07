<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

function admin_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function render_admin_header(string $title): void
{
    admin_security_headers();
    $admin = admin_current_user();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . h($title) . ' | HOBC Master Admin</title>';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<style>';
    echo ':root{--bg:#050708;--panel:#101417;--panel-soft:#171d22;--gold:#f6b928;--gold-2:#ffd764;--text:#f7f5ed;--muted:#b9b5a6;--line:rgba(246,185,40,.32);--danger:#ffcf66;--bad:#ff7a7a;--good:#77e6a8;--shadow:0 18px 48px rgba(0,0,0,.45)}';
    echo '*{box-sizing:border-box}body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;background:radial-gradient(circle at top left,rgba(246,185,40,.14),transparent 28rem),#050708;color:var(--text)}';
    echo 'a{color:var(--gold-2);text-decoration:none}a:hover{color:#fff}.shell{display:flex;min-height:100vh}.sidebar{width:270px;background:linear-gradient(180deg,rgba(16,20,23,.98),rgba(7,9,10,.98));border-right:1px solid var(--line);color:var(--text);padding:20px;position:sticky;top:0;height:100vh;overflow:auto}';
    echo '.admin-brand{display:flex;align-items:center;gap:12px;margin-bottom:20px}.admin-brand img{width:52px;height:52px;object-fit:contain}.admin-brand h2{font-size:18px;letter-spacing:.08em;text-transform:uppercase;margin:0}.admin-brand small{color:var(--muted);display:block;font-size:11px;letter-spacing:.12em;text-transform:uppercase;margin-top:3px}';
    echo '.sidebar a{display:block;color:var(--muted);text-decoration:none;margin:8px 0;padding:10px 12px;border:1px solid rgba(246,185,40,.14);border-radius:999px;background:rgba(255,255,255,.025);font-weight:700}.sidebar a:hover{background:rgba(246,185,40,.16);border-color:var(--gold);color:var(--text)}';
    echo '.sidebar .admin-note{border:1px solid rgba(255,207,102,.4);border-radius:14px;background:rgba(255,207,102,.08);color:var(--muted);font-size:13px;line-height:1.55;margin-top:18px;padding:12px}.main{flex:1;padding:clamp(18px,3vw,34px);max-width:1280px;width:100%;margin:0 auto}.top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px}.top h1{font-size:clamp(30px,5vw,54px);line-height:1;margin:0;text-transform:uppercase}.admin-user{border:1px solid rgba(246,185,40,.2);border-radius:999px;background:rgba(255,255,255,.04);color:var(--muted);padding:10px 13px;white-space:nowrap}';
    echo '.card{background:linear-gradient(180deg,rgba(255,255,255,.055),rgba(255,255,255,.02));border:1px solid rgba(246,185,40,.18);border-radius:16px;box-shadow:0 10px 28px rgba(0,0,0,.25);padding:18px;margin:16px 0}.card h3{font-size:22px;margin:0 0 10px}.card h4{margin:18px 0 8px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:16px 0}.metric{font-size:clamp(24px,4vw,34px);font-weight:900;margin-top:8px;overflow-wrap:anywhere}.metric-card-label,.card>div:first-child{color:var(--muted);letter-spacing:.08em;text-transform:uppercase;font-size:12px;font-weight:800}';
    echo '.ok{color:var(--good)}.warn{color:var(--danger)}.err{color:var(--bad)}p,small{color:var(--muted);line-height:1.6}table{border-collapse:separate;border-spacing:0;width:100%;background:#080b0d;border:1px solid rgba(246,185,40,.14);border-radius:12px;overflow:hidden}th,td{border-bottom:1px solid rgba(255,255,255,.08);padding:10px;text-align:left;vertical-align:top;color:var(--muted)}tr:last-child td{border-bottom:0}th{color:var(--gold-2);font-size:12px;letter-spacing:.08em;text-transform:uppercase;background:rgba(246,185,40,.08)}tr:hover td{background:rgba(255,255,255,.025)}';
    echo 'form:not([style]){display:grid;gap:10px}form:not([style])>br{display:none}label{color:var(--muted);display:grid;font-weight:700;gap:5px;line-height:1.3}input,select,textarea{max-width:100%;padding:10px 12px;border:1px solid rgba(246,185,40,.25);border-radius:12px;background:#090d10;color:var(--text)}input:not([type=checkbox]),select,textarea{width:100%}input[type=checkbox]{width:auto;margin-right:8px;accent-color:var(--gold)}textarea{min-height:110px;resize:vertical}button,.button{border:1px solid #ffd45d;border-radius:999px;color:#110d05;display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 14px;background:linear-gradient(180deg,#ffd45d,#b77906);font-weight:900;cursor:pointer}button:hover,.button:hover{filter:brightness(1.08);color:#110d05}code{background:#06090b;border:1px solid rgba(246,185,40,.22);border-radius:8px;color:#fff;display:inline-block;max-width:100%;overflow-wrap:anywhere;padding:3px 6px}';
    echo '.module-card{min-height:150px}.module-status{display:inline-block;border:1px solid rgba(246,185,40,.25);border-radius:999px;color:var(--gold-2);font-size:12px;font-weight:900;letter-spacing:.08em;margin-bottom:10px;padding:5px 9px;text-transform:uppercase}.admin-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px}.table-wrap{overflow:auto}.modal-backdrop{align-items:center;background:rgba(0,0,0,.76);display:none;inset:0;justify-content:center;padding:18px;position:fixed;z-index:40}.modal-backdrop.is-open{display:flex}.modal-card{background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.025)),#0b0f12;border:1px solid rgba(246,185,40,.34);border-radius:18px;box-shadow:0 24px 70px rgba(0,0,0,.62);max-height:min(85vh,46rem);max-width:520px;overflow:auto;padding:20px;width:min(100%,520px)}.modal-card.authenticator-modal{max-height:min(92vh,42rem);max-width:34rem;padding:16px;width:min(100%,34rem)}.authenticator-modal h3{font-size:clamp(20px,4vw,27px)}.authenticator-modal p{line-height:1.45;margin:10px 0}.modal-header{align-items:flex-start;display:flex;gap:14px;justify-content:space-between}.qr-code-wrap{background:#fff;border:1px solid rgba(246,185,40,.34);border-radius:16px;margin:16px auto;max-width:18rem;overflow:hidden;padding:16px}.authenticator-modal .qr-code-wrap{max-width:min(10.5rem,46vw);padding:10px;margin:12px auto}.authenticator-modal code{font-size:12px}.qr-code-wrap svg{display:block;height:auto;max-width:100%;width:100%}';
    echo 'input:-webkit-autofill,input:-webkit-autofill:hover,input:-webkit-autofill:focus,select:-webkit-autofill,select:-webkit-autofill:hover,select:-webkit-autofill:focus,textarea:-webkit-autofill,textarea:-webkit-autofill:hover,textarea:-webkit-autofill:focus{-webkit-box-shadow:0 0 0 1000px #090d10 inset;-webkit-text-fill-color:var(--text);caret-color:var(--text);transition:background-color 9999s ease-in-out 0s}';
    echo '@media(max-width:860px){.shell{display:block}.sidebar{height:auto;position:static;width:auto}.main{padding:18px}.top{display:block}.admin-user{display:inline-block;margin-top:12px}.sidebar a{display:inline-block;margin:5px}.grid{grid-template-columns:1fr}table{font-size:14px}}';
    echo '</style></head><body><div class="shell"><aside class="sidebar">';
    echo '<div class="admin-brand"><img src="/assets/images/logo-round.png" alt="HOBC logo"><div><h2>HOBC Admin</h2><small>Master control center</small></div></div>';
    echo '<a href="' . h(admin_url('/index.php')) . '">Control Center</a>';
    echo '<a href="' . h(admin_url('/site-config.php')) . '">Site Config</a>';
    echo '<a href="' . h(admin_url('/authenticator.php')) . '">Authenticator</a>';
    echo '<a href="' . h(admin_url('/wallet.php')) . '">Wallet Ops</a>';
    echo '<a href="' . h(admin_url('/withdrawals.php')) . '">Withdrawals</a>';
    echo '<a href="' . h(admin_url('/tickets.php')) . '">Support Tickets</a>';
    echo '<a href="' . h(admin_url('/smtp.php')) . '">SMTP Settings</a>';
    echo '<a href="' . h(admin_url('/audit.php')) . '">Audit Log</a>';
    echo '<a href="' . h(wallet_url('/dashboard.php')) . '">User Wallet</a>';
    echo '<a href="' . h(admin_url('/logout.php')) . '">Logout</a>';
    echo '<div class="admin-note"><b>Master Admin</b><br>This panel is the protected command center. Future modules will control pool, node, wallet, explorer, and public-site systems from here.</div>';
    echo '</aside><main class="main"><div class="top"><h1>' . h($title) . '</h1>';
    echo '<div class="admin-user">' . ($admin ? 'Signed in as ' . h((string)$admin['username']) : 'Restricted access') . '</div></div>';
}

function render_admin_footer(): void
{
    echo '</main></div></body></html>';
}
