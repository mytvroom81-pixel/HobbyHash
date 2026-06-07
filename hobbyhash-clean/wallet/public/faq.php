<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/settings.php';
require_once __DIR__ . '/app/view.php';

$section = trim((string)($_GET['section'] ?? 'Wallet'));
$section = $section !== '' ? substr($section, 0, 80) : 'Wallet';
$sectionKey = strtolower($section);
$sectionTitles = [
    'wallet' => 'Wallet FAQ',
    'mining' => 'Mining FAQ',
    'main pool' => 'Main Pool FAQ',
    'nano pool' => 'Nano Pool FAQ',
    'explorer' => 'Explorer FAQ',
    'launch reserve' => 'Launch Reserve FAQ',
    'burn tracker' => 'Burn Tracker FAQ',
    'docs' => 'Docs FAQ',
];
$title = $sectionTitles[$sectionKey] ?? 'FAQ';
$walletSettings = [];
try {
    $walletSettings = wallet_settings();
} catch (Throwable $e) {
    wallet_log_error('FAQ wallet settings read failed: ' . $e->getMessage());
}
$walletSmsEnabled = (int)($walletSettings['wallet_sms_registration_required'] ?? 0) === 1
    || (int)($walletSettings['wallet_sms_login_required'] ?? 0) === 1
    || (int)($walletSettings['wallet_sms_withdrawal_required'] ?? 0) === 1;

$sectionFaqs = [
    'wallet' => [
        ['Is the HOBC web wallet custodial?', 'Yes. The HOBC web wallet is custodial. The website controls wallet keys and funds until you withdraw. Use a local wallet for larger balances.'],
        ['What should I use the web wallet for?', 'Use it for convenient access, testing, receiving pool payouts, and smaller balances. For larger balances or long-term storage, use a local wallet where you control the keys.'],
        ['How do I create an account?', 'Open the wallet Register page, choose a username, enter your email, optional phone number if enabled, and create a strong password. Keep your login details private.'],
        ['Can my admin and wallet logins be different?', 'Yes. The admin panel and wallet use separate login roles. Your browser may save both, so make sure you choose the correct saved login for the page you are on.'],
        ['How do deposits work?', 'Use Receive to get a HOBC address. Send HOBC to that address from another wallet, miner payout, or pool payout. The deposit is detected by the wallet scanner and credited after the required confirmations.'],
        ['What are confirmations?', 'Confirmations are new blocks added after your transaction. They help protect against chain reorganizations. Deposits may show detected or confirming before they become credited.'],
        ['Why does my deposit say pending or confirming?', 'The transaction has been seen but has not met the wallet confirmation requirement yet, or the scanner is still processing it. The wallet only shows real backend data.'],
        ['What if the wallet backend, node, or scanner is offline?', 'Wallet pages should show the real status, such as offline, syncing, pending, or unavailable. The site must not show fake balances, fake deposits, fake withdrawals, or fake txids.'],
        ['What are receive wallets?', 'Receive wallets are labels for organizing incoming payments. They are not separate spendable balances. Your total wallet balance is still one internal custodial balance.'],
        ['Can I name receive wallets?', 'Yes. Receive wallet names are labels to help you organize incoming payments, such as Mining, Savings, or Exchange deposits. Unnamed wallets show as None.'],
        ['Can I generate QR codes for receiving?', 'Yes. Use the QR button next to a receive address or on the dashboard primary receive wallet. QR codes are generated locally by the site for your own logged-in receive addresses.'],
        ['Why do I see full addresses but shortened txids?', 'Wallet addresses are shown in full so you can copy and verify them. Long txids may be shortened for layout, with the full value available in details or hover text.'],
        ['How do withdrawals work?', 'Open Withdraw, enter the destination address and amount, then create a withdrawal request. The wallet may place a hold, require admin approval, queue the withdrawal, or pause withdrawals depending on wallet settings and hot-wallet status.'],
        ['Why might a withdrawal need admin approval?', 'Withdrawals above the configured threshold, suspicious activity, maintenance, or wallet safety checks may require admin review before broadcast.'],
        ['Can I withdraw to one of my own receive addresses?', 'No. The wallet blocks sending to your own deposit address because those addresses already belong to your custodial wallet account.'],
        ['What happens if I enter the wrong withdrawal address?', 'Blockchain transactions are usually irreversible. Always verify the full destination address before submitting a withdrawal.'],
        ['Why are withdrawals paused?', 'Withdrawals may be paused for maintenance, wallet backend issues, hot-wallet safety, node problems, or admin review. The page should say when withdrawals are paused.'],
        ['What are wallet liabilities and hot wallet balance?', 'Liabilities are the total internal balances owed to users. Hot wallet balance is the spendable backend wallet balance used to process withdrawals. Admins use this to reconcile wallet health.'],
        ['How do transaction details work?', 'The Transactions page shows deposits and withdrawals with status, amount, address, txid, confirmations, and a details modal for cleaner viewing. Rows are paginated 10 per page.'],
        ['Why do confirmations show 20+?', 'After confirmations are above 20, the wallet displays 20+ to keep tables clean while still showing that the transaction is well-confirmed.'],
        ['How do support tickets work?', 'Use Support to open a wallet support ticket. The ticket records that it came from the wallet section, your account details if logged in, IP address, and your message so admin can help.'],
        ['Will I get support emails?', 'When email is configured, the support system sends ticket confirmations, admin replies, and status updates. Use the ticket system for replies instead of replying directly to notification emails.'],
        ['What does the Security page show?', 'The Security page shows authenticator status, last login time and IP, password change tools, authenticator setup, and recent security events with IP addresses.'],
        ['Should I enable an authenticator app?', 'Yes. Authenticator apps such as Google Authenticator, Authy, Microsoft Authenticator, or 1Password are stronger than password-only login and do not depend on SMS carrier delivery.'],
        ['How do I set up an authenticator app?', 'Go to Security, choose Set Up Authenticator, scan the QR code with your authenticator app, then enter the 6-digit code to confirm and enable it.'],
        ['What if I lose access to my authenticator app?', 'Open a support ticket. Admin review may be required before access can be restored. Keep your device and recovery options safe.'],
        ['How do I change my password?', 'Go to Security, enter your current password and a new password of at least 12 characters, then save it. Use a unique password you do not use on other sites.'],
        ['Can I see more than 10 rows in wallet tables?', 'Wallet tables show 10 rows per page with Previous 10 and Next 10 links. Pagination is not capped, so you can continue through all available records.'],
        ['Does the wallet show market price or market cap?', 'No. The wallet and portal do not show fake price, fake market cap, fake balances, fake txids, fake burns, or fake pool data.'],
        ['Where should I ask for wallet help?', 'Open a Wallet support ticket from the wallet Support page or use the Open Wallet Ticket button on this FAQ page.'],
    ],
    'mining' => [
        ['What does solo mining mean?', 'Solo mining means your worker only receives a payout when it finds a real block.'],
        ['What worker name should I use?', 'Use YOUR_HOBC_ADDRESS.worker1 for Main Pool or YOUR_HOBC_ADDRESS.nano1 for Nano Pool.'],
        ['What password should I use?', 'Use x unless the pool page says otherwise.'],
    ],
    'main pool' => [
        ['What is the Main Pool URL?', 'stratum+tcp://pool.hobbyhashcoin.com:5555'],
        ['What is the Main Pool start difficulty?', 'Main Pool starts at difficulty 5000.'],
        ['Is Main Pool solo only?', 'Yes. The payout goes to the HOBC address in your worker name.'],
    ],
    'nano pool' => [
        ['What is the Nano Pool URL?', 'stratum+tcp://pool.hobbyhashcoin.com:5556'],
        ['What is the Nano Pool start difficulty?', 'Nano Pool starts at difficulty 0.005.'],
        ['Who should use Nano Pool?', 'Small SHA-256 miners and nano miners should use Nano Pool.'],
    ],
    'explorer' => [
        ['Why does explorer data say syncing?', 'Explorer data stays syncing or not available until a real index is ready.'],
        ['Can I search blocks, txids, and addresses?', 'Search should only return real indexed data. If data is unavailable, it must say syncing or not available.'],
    ],
    'launch reserve' => [
        ['How large is the launch reserve?', 'The launch reserve is 8,400,000 HOBC, which is 10% of the 84,000,000 HOBC total supply.'],
        ['Are reserve balances live?', 'Reserve balances and outgoing transactions must come from real chain tracking.'],
    ],
    'burn tracker' => [
        ['Are burns shown before they happen?', 'No. Burn totals and txids are only shown after real burn tracking exists.'],
        ['What if burn data is not ready?', 'The page must show pending launch or not available, never fake burn data.'],
    ],
    'docs' => [
        ['What do the docs cover?', 'Node setup, wallet setup, main pool mining, nano pool mining, pool troubleshooting, solo mining, wallet risk, and launch reserve.'],
        ['Are docs beginner friendly?', 'Yes. Docs should explain the safe setup path without assuming advanced mining experience.'],
    ],
];

if ($sectionKey === 'wallet' && $walletSmsEnabled) {
    $sectionFaqs['wallet'][] = ['When does the wallet use SMS codes?', 'SMS codes may be required for registration, login, or withdrawals only when those options are enabled by admin in Site Config.'];
    $sectionFaqs['wallet'][] = ['Why am I not receiving SMS codes?', 'SMS delivery depends on the configured SMS provider, carrier filtering, phone number formatting, and A2P/verification status. If SMS is unavailable, use support or an authenticator app if enabled.'];
    $sectionFaqs['wallet'][] = ['Can I opt out of SMS?', 'Reply STOP to opt out of SMS messages. If SMS requirements are enabled, opting out may prevent access to protected login or withdrawal features until another approved security method is available.'];
    $sectionFaqs['wallet'][] = ['What does HELP do for SMS?', 'Reply HELP for SMS help. You can also open a wallet support ticket for account-specific help.'];
    $sectionFaqs['wallet'][] = ['Are SMS codes as strong as authenticator apps?', 'No. SMS is better than password-only, but authenticator apps are stronger because SMS can be affected by carrier delivery issues or SIM-swap risk.'];
}

$items = $sectionFaqs[$sectionKey] ?? [];
if (!$items) {
    $dbItems = wallet_db()->query("SELECT question, answer FROM faq_items WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    foreach ($dbItems as $item) {
        $items[] = [(string)$item['question'], (string)$item['answer']];
    }
}
if (!$items) {
    $items = [
        ['What is HOBC?', 'HOBC is HobbyHash Coin, a SHA-256 coin focused on home solo mining.'],
        ['Where should I ask for help?', 'Open a section-specific support ticket so admin can see where the question came from.'],
    ];
}

render_header($title);
?>
<div class="card">
  <h3><?= h($title) ?></h3>
  <p>Help for the <?= h($section) ?> section. These links match the wallet pages and keep the support ticket source attached.</p>
  <div class="grid cards">
    <?php foreach ($items as $item): ?>
      <article class="card">
        <h4><?= h($item[0]) ?></h4>
        <p><?= nl2br(h($item[1])) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
  <div class="actions">
    <a class="button primary" href="<?= h('/contact.php?section=' . rawurlencode($section)) ?>">Open <?= h($section) ?> Ticket</a>
    <a class="button" href="<?= h('/docs/') ?>">Read Docs</a>
    <a class="button" href="<?= h('/wallet/') ?>">Wallet Overview</a>
  </div>
</div>
<?php render_footer(); ?>
