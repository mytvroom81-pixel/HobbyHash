<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/i18n.php';
hobc_i18n_bootstrap();
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/support_context.php';
require_once __DIR__ . '/../app/support_i18n.php';
require_once __DIR__ . '/../app/mailer_i18n.php';
require_once __DIR__ . '/../app/mailer.php';
require_once __DIR__ . '/../app/view.php';

$user = auth_require_user();
$err = '';
$ok = '';
$sourceContext = support_context_from_request(wallet_te('wallet.page.support.context_default', [], 'Wallet support'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    support_context_ensure_schema();
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    if ($subject === '' || $message === '') {
        $err = wallet_te('wallet.error.support_required', [], 'Subject and message are required.');
    } else {
        $pdo = wallet_db();
        $token = bin2hex(random_bytes(32));
        $requesterLocale = support_i18n_current_locale();
        $pdo->beginTransaction();
        try {
            $ticket = $pdo->prepare(
                "INSERT INTO support_tickets
                (user_id, public_token, requester_name, requester_email, subject, source, source_context, requester_locale, created_ip, created_user_agent)
                 VALUES (?, ?, ?, ?, ?, 'wallet', ?, ?, ?, ?)"
            );
            $ticket->execute([
                (int)$user['id'],
                $token,
                (string)$user['username'],
                (string)$user['email'],
                substr($subject, 0, 190),
                $sourceContext,
                $requesterLocale,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            ]);
            $ticketId = (int)$pdo->lastInsertId();
            $msg = $pdo->prepare(
                "INSERT INTO support_ticket_messages (ticket_id, sender_type, sender_user_id, message)
                 VALUES (?, 'user', ?, ?)"
            );
            $msg->execute([$ticketId, (int)$user['id'], $message]);
            $messageId = (int)$pdo->lastInsertId();
            $pdo->commit();
            HobcSupportI18n::onUserMessage($ticketId, $messageId, $message, $requesterLocale);
            HobcSupportI18n::onTicketCreated(['id' => $ticketId], $subject, $requesterLocale);
            $ok = wallet_te('wallet.success.ticket_created', [], 'Support ticket created.');
            $trackUrl = mailer_support_ticket_url($token, $requesterLocale);
            $emailRows = mailer_support_rows_localized([
                'Section' => $sourceContext,
                'Subject' => substr($subject, 0, 190),
                'Tracking link' => $trackUrl,
            ], $requesterLocale);
            $emailTitle = mailer_i18n_t('email.support.ticket_received.title', [], 'Support Ticket Received', $requesterLocale);
            $emailText = mailer_support_text_localized(
                $emailTitle,
                $emailRows,
                mailer_i18n_t('email.support.ticket_received.body_text', [], 'Your HOBC support ticket was created. We will reply as soon as possible.', $requesterLocale),
                $trackUrl,
                $requesterLocale
            );
            $emailHtml = mailer_support_html_localized(
                $emailTitle,
                $emailRows,
                mailer_i18n_t('email.support.ticket_received.body_html', [], '<p>Your HOBC support ticket was created. We will reply as soon as possible.</p>', $requesterLocale),
                $trackUrl,
                $requesterLocale
            );
            mailer_send(
                (string)$user['email'],
                mailer_i18n_t('email.support.ticket_received.subject', ['subject' => substr($subject, 0, 190)], 'HOBC support ticket received: ' . substr($subject, 0, 190), $requesterLocale),
                $emailText,
                $emailHtml
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            wallet_log_error('wallet ticket create failed: ' . $e->getMessage());
            $err = wallet_te('wallet.error.ticket_create_failed', [], 'Support ticket could not be created.');
        }
    }
}

$ticketPage = max(1, (int)($_GET['ticket_page'] ?? 1));
$ticketTotalStmt = wallet_db()->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ?");
$ticketTotalStmt->execute([(int)$user['id']]);
$ticketTotal = (int)$ticketTotalStmt->fetchColumn();
$ticketTotalPages = max(1, (int)ceil($ticketTotal / 10));
$ticketPage = min($ticketPage, $ticketTotalPages);
$ticketOffset = ($ticketPage - 1) * 10;
$tickets = wallet_db()->prepare("SELECT id, public_token, subject, status, updated_at FROM support_tickets WHERE user_id = ? ORDER BY id DESC LIMIT 10 OFFSET {$ticketOffset}");
$tickets->execute([(int)$user['id']]);

render_header('wallet.page.support.title');
?>
<div class="card">
  <h3><?= h(wallet_te('wallet.page.support.title', [], 'Support')) ?></h3>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="source_context" value="<?= h($sourceContext) ?>">
    <p><b><?= h(wallet_te('wallet.page.support.section', [], 'Support section:')) ?></b> <?= h($sourceContext) ?></p>
    <label><?= h(wallet_te('wallet.page.support.subject', [], 'Subject')) ?><br><input name="subject" maxlength="190" required></label><br><br>
    <label><?= h(wallet_te('wallet.page.support.message', [], 'Message')) ?><br><textarea name="message" rows="8" required></textarea></label><br><br>
    <button class="button primary" type="submit"><?= h(wallet_te('wallet.page.support.create_ticket', [], 'Create Ticket')) ?></button>
  </form>
</div>

<div class="card">
  <h3><?= h(wallet_te('wallet.page.support.your_tickets', [], 'Your Tickets')) ?></h3>
  <table>
    <tr><th><?= h(wallet_te('wallet.table.id', [], 'ID')) ?></th><th><?= h(wallet_te('wallet.page.support.subject', [], 'Subject')) ?></th><th><?= h(wallet_te('wallet.table.status', [], 'Status')) ?></th><th><?= h(wallet_te('wallet.table.updated', [], 'Updated')) ?></th><th><?= h(wallet_te('wallet.table.track', [], 'Track')) ?></th></tr>
    <?php foreach ($tickets as $ticket): ?>
      <tr>
        <td><?= h((string)$ticket['id']) ?></td>
        <td><?= h($ticket['subject']) ?></td>
        <td><?= h(hobc_tp('ticket', 'status.' . (string)$ticket['status'], [], (string)$ticket['status'])) ?></td>
        <td><?= h($ticket['updated_at']) ?></td>
        <td><a href="<?= h(wallet_pp('/ticket.php') . '?token=' . urlencode((string)$ticket['public_token'])) ?>"><?= h(wallet_te('wallet.page.support.open', [], 'Open')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($ticketTotal === 0): ?>
      <tr><td colspan="5"><?= h(wallet_te('wallet.page.support.empty', [], 'No tickets found yet.')) ?></td></tr>
    <?php endif; ?>
  </table>
  <?= wallet_pagination('/support.php', 'ticket_page', $ticketPage, $ticketTotalPages, ['section' => $sourceContext]) ?>
</div>
<?php render_footer(); ?>
