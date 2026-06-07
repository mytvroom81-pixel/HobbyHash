<?php
declare(strict_types=1);

require_once __DIR__ . '/app/i18n.php';
hobc_i18n_bootstrap();
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/support_context.php';
require_once __DIR__ . '/app/support_i18n.php';
require_once __DIR__ . '/app/mailer_i18n.php';
require_once __DIR__ . '/app/mailer.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/settings.php';

$pageId = 'contact';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');

$err = '';
$trackUrl = '';
$supportSectionMap = [
    'General support' => 'support.sections.general_support',
    'Wallet' => 'support.sections.wallet',
    'Mining' => 'support.sections.mining',
    'Main Pool' => 'support.sections.main_pool',
    'Nano Pool' => 'support.sections.nano_pool',
    'Explorer' => 'support.sections.explorer',
    'Launch Reserve' => 'support.sections.launch_reserve',
    'Burn Tracker' => 'support.sections.burn_tracker',
    'Privacy' => 'support.sections.privacy',
    'Terms' => 'support.sections.terms',
    'SMS Support' => 'support.sections.sms_support',
];
$supportSections = array_keys($supportSectionMap);
$sourceContext = support_context_from_request('General support');
if (!in_array($sourceContext, $supportSections, true)) {
    $supportSections[] = $sourceContext;
}
$currentUser = auth_current_user();
$useWalletLayout = strcasecmp($sourceContext, 'Wallet') === 0;
if ($useWalletLayout) {
    require_once __DIR__ . '/app/view.php';
} else {
    $activePage = 'contact';
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/includes/nav.php';
    require __DIR__ . '/includes/status-bar.php';
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_or_fail();
    support_context_ensure_schema();
    $name = $currentUser ? (string)$currentUser['username'] : trim((string)($_POST['name'] ?? ''));
    $email = $currentUser ? (string)$currentUser['email'] : trim((string)($_POST['email'] ?? ''));
    $sourceContext = support_context_from_request('General support');
    if (!in_array($sourceContext, $supportSections, true)) {
        $sourceContext = 'General support';
    }
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        $err = hobc_tp($pageId, 'error.please_fill_out_all_fields');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = hobc_tp($pageId, 'error.please_enter_a_valid_email_address');
    } else {
        $pdo = wallet_db();
        $token = bin2hex(random_bytes(32));
        $requesterLocale = support_i18n_current_locale();
        $pdo->beginTransaction();
        try {
            $ticket = $pdo->prepare(
                "INSERT INTO support_tickets
                (user_id, public_token, requester_name, requester_email, subject, source, source_context, requester_locale, created_ip, created_user_agent)
                 VALUES (?, ?, ?, ?, ?, 'public', ?, ?, ?, ?)"
            );
            $ticket->execute([
                $currentUser ? (int)$currentUser['id'] : null,
                $token,
                substr($name, 0, 120),
                substr($email, 0, 190),
                substr($subject, 0, 190),
                $sourceContext,
                $requesterLocale,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            ]);
            $ticketId = (int)$pdo->lastInsertId();
            $msg = $pdo->prepare(
                "INSERT INTO support_ticket_messages (ticket_id, sender_type, message)
                 VALUES (?, 'guest', ?)"
            );
            $msg->execute([$ticketId, $message]);
            $messageId = (int)$pdo->lastInsertId();
            $pdo->commit();
            HobcSupportI18n::onUserMessage($ticketId, $messageId, $message, $requesterLocale);
            HobcSupportI18n::onTicketCreated(['id' => $ticketId], $subject, $requesterLocale);
            $trackUrl = hobc_pp('/ticket.php') . '?token=' . urlencode($token);
            $trackFullUrl = mailer_support_ticket_url($token, $requesterLocale);
            $emailRows = mailer_support_rows_localized([
                'Section' => $sourceContext,
                'Subject' => substr($subject, 0, 190),
                'Tracking link' => $trackFullUrl,
            ], $requesterLocale);
            $emailTitle = mailer_i18n_t('email.support.ticket_received.title', [], 'Support Ticket Received', $requesterLocale);
            $emailText = mailer_support_text_localized(
                $emailTitle,
                $emailRows,
                mailer_i18n_t('email.support.ticket_received.body_text', [], hobc_tp($pageId, 'email.ticket_created_text'), $requesterLocale),
                $trackFullUrl,
                $requesterLocale
            );
            $emailHtml = mailer_support_html_localized(
                $emailTitle,
                $emailRows,
                mailer_i18n_t('email.support.ticket_received.body_html', [], hobc_tp($pageId, 'email.ticket_created_html'), $requesterLocale),
                $trackFullUrl,
                $requesterLocale
            );
            mailer_send(
                substr($email, 0, 190),
                mailer_i18n_t('email.support.ticket_received.subject', ['subject' => substr($subject, 0, 190)], 'HOBC support ticket received: ' . substr($subject, 0, 190), $requesterLocale),
                $emailText,
                $emailHtml
            );
            $adminNotify = trim((string)admin_setting_get('notifications.admin_email', ''));
            if (admin_setting_bool('notifications.support_enabled', true) && filter_var($adminNotify, FILTER_VALIDATE_EMAIL)) {
                $adminText = mailer_support_text(
                    'New HOBC support ticket',
                    [
                        'Ticket ID' => (string)$ticketId,
                        'Section' => $sourceContext,
                        'Requester' => substr($name, 0, 120),
                        'Email' => substr($email, 0, 190),
                        'Subject' => substr($subject, 0, 190),
                        'Admin link' => 'https://hobbyhashcoin.com/admin/tickets.php?id=' . $ticketId,
                    ],
                    'A new support ticket was created from the public website.',
                    'https://hobbyhashcoin.com/admin/tickets.php?id=' . $ticketId
                );
                $adminHtml = mailer_support_html(
                    'New Support Ticket',
                    [
                        'Ticket ID' => (string)$ticketId,
                        'Section' => $sourceContext,
                        'Requester' => substr($name, 0, 120),
                        'Email' => substr($email, 0, 190),
                        'Subject' => substr($subject, 0, 190),
                    ],
                    '<p>A new support ticket was created from the public website.</p>',
                    'https://hobbyhashcoin.com/admin/tickets.php?id=' . $ticketId
                );
                mailer_send($adminNotify, 'New HOBC support ticket: ' . substr($subject, 0, 120), $adminText, $adminHtml);
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            wallet_log_error('public ticket create failed: ' . $e->getMessage());
            $err = hobc_tp($pageId, 'error.ticket_could_not_be_created');
        }
    }
}

if ($useWalletLayout) {
    render_header(hobc_tp($pageId, 'wallet_layout.title'));
}
?>
<?php if (!$useWalletLayout): ?>
<main id="main-content">
  <div class="page">
    <section class="hero">
      <div class="hero-content">
        <span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span>
        <h1><?= hobc_tpe($pageId, 'form.title') ?></h1>
        <p><?= hobc_tpe($pageId, 'hero.lead') ?></p>
      </div>
    </section>
<?php endif; ?>
  <div class="card">
    <h3><?= hobc_tpe($pageId, 'hero.title') ?></h3>
    <?php if ($err): ?><p class="err"><?= hobc_e($err) ?></p><?php endif; ?>
    <?php if ($trackUrl): ?>
      <p class="ok"><?= hobc_tpe($pageId, 'success.created') ?><a href="<?= h($trackUrl) ?>"><?= h($trackUrl) ?></a></p>
    <?php else: ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label><?= hobc_tpe($pageId, 'form.section_label') ?><br>
          <select name="source_context" required>
            <?php foreach ($supportSections as $section): ?>
              <option value="<?= h($section) ?>" <?= $section === $sourceContext ? 'selected' : '' ?>><?= hobc_te((string)($supportSectionMap[$section] ?? 'support.sections.general_support'), [], $section) ?></option>
            <?php endforeach; ?>
          </select>
        </label><br><br>
        <?php if (!$currentUser): ?>
          <label><?= hobc_tpe($pageId, 'form.name_label') ?><br><input name="name" maxlength="120" required></label><br><br>
          <label><?= hobc_tpe($pageId, 'form.email_label') ?><br><input type="email" name="email" maxlength="190" required></label><br><br>
        <?php endif; ?>
        <label><?= hobc_tpe($pageId, 'form.subject_label') ?><br><input name="subject" maxlength="190" required></label><br><br>
        <label><?= hobc_tpe($pageId, 'form.message_label') ?><br><textarea name="message" rows="8" required></textarea></label><br><br>
        <button class="button primary" type="submit"><?= hobc_tpe($pageId, 'form.submit') ?></button>
      </form>
    <?php endif; ?>
  </div>
  <?php $socialLinksVariant = 'contact'; require __DIR__ . '/includes/social-links.php'; ?>
<?php if ($useWalletLayout): ?>
  <?php render_footer(); ?>
<?php else: ?>
  </div></main>
  <?php require __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>
