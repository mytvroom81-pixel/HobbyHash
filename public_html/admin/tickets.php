<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/mailer.php';
require_once __DIR__ . '/../app/support_context.php';
require_once __DIR__ . '/../app/support_i18n.php';
require_once __DIR__ . '/../app/mailer_i18n.php';
require_once __DIR__ . '/../app/admin_view.php';

$admin = admin_require_user();
$pdo = wallet_db();
support_context_ensure_schema();
$ticketId = (int)($_GET['id'] ?? $_POST['ticket_id'] ?? 0);
$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? 'reply');
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ? LIMIT 1");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        $err = 'Ticket not found.';
    } elseif ($action === 'reply') {
        $message = trim((string)($_POST['message'] ?? ''));
        if ($message === '') {
            $err = 'Reply message is required.';
        } else {
            $msg = $pdo->prepare(
                "INSERT INTO support_ticket_messages (ticket_id, sender_type, sender_admin_id, message)
                 VALUES (?, 'admin', ?, ?)"
            );
            $msg->execute([$ticketId, (int)$admin['id'], $message]);
            $messageId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE support_tickets SET status = 'waiting_user' WHERE id = ?")->execute([$ticketId]);
            admin_audit((int)$admin['id'], 'support_ticket_reply', 'support_ticket', (string)$ticketId);
            $userMessage = HobcSupportI18n::onAdminReply($ticket, $messageId, $message);
            $userLocale = HobcSupportI18n::ticketRequesterLocale($ticket);
            $trackUrl = mailer_support_ticket_url((string)$ticket['public_token'], $userLocale);
            $emailRows = mailer_support_rows_localized([
                'Subject' => (string)$ticket['subject'],
                'Status' => 'waiting_user',
                'Tracking link' => $trackUrl,
            ], $userLocale);
            $emailTitle = mailer_i18n_t('email.support.reply.title', [], 'Support Reply', $userLocale);
            $replyHtml = '<p>' . nl2br(h($userMessage)) . '</p>';
            $emailText = mailer_support_text_localized(
                $emailTitle,
                $emailRows,
                mailer_i18n_t('email.support.reply.body_text', [], 'HOBC support replied to your ticket.', $userLocale) . "\n\n" . $userMessage,
                $trackUrl,
                $userLocale
            );
            $emailHtml = mailer_support_html_localized(
                $emailTitle,
                $emailRows,
                $replyHtml,
                $trackUrl,
                $userLocale
            );
            mailer_send(
                (string)$ticket['requester_email'],
                mailer_i18n_t('email.support.reply.subject', ['subject' => (string)$ticket['subject']], 'Support ticket reply: ' . (string)$ticket['subject'], $userLocale),
                $emailText,
                $emailHtml
            );
            $ok = 'Reply saved.';
        }
    } elseif ($action === 'status') {
        $status = (string)($_POST['status'] ?? 'open');
        if (!in_array($status, ['open', 'waiting_user', 'waiting_admin', 'closed'], true)) {
            $err = 'Invalid status.';
        } else {
            $pdo->prepare("UPDATE support_tickets SET status = ?, closed_at = IF(? = 'closed', UTC_TIMESTAMP(), NULL) WHERE id = ?")->execute([$status, $status, $ticketId]);
            admin_audit((int)$admin['id'], 'support_ticket_status', 'support_ticket', (string)$ticketId, ['status' => $status]);
            $userLocale = HobcSupportI18n::ticketRequesterLocale($ticket);
            $trackUrl = mailer_support_ticket_url((string)$ticket['public_token'], $userLocale);
            $emailRows = mailer_support_rows_localized([
                'Subject' => (string)$ticket['subject'],
                'New status' => $status,
                'Tracking link' => $trackUrl,
            ], $userLocale);
            $emailTitle = mailer_i18n_t('email.support.status_updated.title', [], 'Ticket Status Updated', $userLocale);
            $emailText = mailer_support_text_localized(
                $emailTitle,
                $emailRows,
                mailer_i18n_t('email.support.status_updated.body', [], 'Your HOBC support ticket status was updated.', $userLocale),
                $trackUrl,
                $userLocale
            );
            $emailHtml = mailer_support_html_localized(
                $emailTitle,
                $emailRows,
                '<p>' . h(mailer_i18n_t('email.support.status_updated.body', [], 'Your HOBC support ticket status was updated.', $userLocale)) . '</p>',
                $trackUrl,
                $userLocale
            );
            mailer_send(
                (string)$ticket['requester_email'],
                mailer_i18n_t('email.support.status_updated.subject', ['subject' => (string)$ticket['subject']], 'HOBC support ticket status updated: ' . (string)$ticket['subject'], $userLocale),
                $emailText,
                $emailHtml
            );
            $ok = 'Ticket status updated.';
        }
    }
}

$tickets = $pdo->query(
    "SELECT st.id, st.user_id, st.requester_name, st.requester_email, st.subject, st.status, st.source,
            st.source_context, st.requester_locale, st.created_ip, st.created_user_agent, st.updated_at,
            u.username AS account_username, u.email AS account_email
     FROM support_tickets st
     LEFT JOIN users u ON u.id = st.user_id
     ORDER BY FIELD(st.status, 'open','waiting_admin','waiting_user','closed'), st.updated_at DESC
     LIMIT 100"
)->fetchAll();

$selected = null;
$messages = [];
if ($ticketId > 0) {
    $stmt = $pdo->prepare(
        "SELECT st.*, u.username AS account_username, u.email AS account_email
         FROM support_tickets st
         LEFT JOIN users u ON u.id = st.user_id
         WHERE st.id = ?
         LIMIT 1"
    );
    $stmt->execute([$ticketId]);
    $selected = $stmt->fetch();
    if ($selected) {
        $m = $pdo->prepare("SELECT id, sender_type, message, created_at FROM support_ticket_messages WHERE ticket_id = ? ORDER BY id ASC");
        $m->execute([$ticketId]);
        $messages = $m->fetchAll();
    }
}

render_admin_header('Support Tickets');
?>
<div class="card">
  <h3>Ticket Inbox</h3>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <table>
    <tr><th>ID</th><th>Requester</th><th>Subject</th><th>Status</th><th>Lang</th><th>Source</th><th>Section</th><th>IP</th><th>Updated</th></tr>
    <?php foreach ($tickets as $ticket): ?>
      <tr>
        <td><a href="<?= h(admin_url('/tickets.php?id=' . (int)$ticket['id'])) ?>"><?= h((string)$ticket['id']) ?></a></td>
        <td><?= h($ticket['requester_name']) ?><br><small><?= h($ticket['requester_email']) ?></small><?php if (!empty($ticket['account_username'])): ?><br><small>Account: <?= h((string)$ticket['account_username']) ?> (#<?= h((string)$ticket['user_id']) ?>)</small><?php endif; ?></td>
        <td><?= h(HobcSupportI18n::subjectForAdmin($ticket)) ?></td>
        <td><?= h($ticket['status']) ?></td>
        <td><?= h((string)($ticket['requester_locale'] ?? 'en')) ?></td>
        <td><?= h($ticket['source']) ?></td>
        <td><?= h((string)($ticket['source_context'] ?? '')) ?></td>
        <td><?= h((string)($ticket['created_ip'] ?? '')) ?></td>
        <td><?= admin_h_datetime($ticket['updated_at'] ?? null) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($selected): ?>
<div class="card">
  <h3><?= h(HobcSupportI18n::subjectForAdmin($selected)) ?></h3>
  <p>Status: <?= h($selected['status']) ?> | Language: <?= h(HobcSupportI18n::ticketRequesterLocale($selected)) ?> | Source: <?= h((string)$selected['source']) ?> | Section: <?= h((string)($selected['source_context'] ?? '')) ?> | Track: <a href="<?= h('/ticket.php?token=' . $selected['public_token']) ?>">public link</a></p>
  <div class="card">
    <h4>Requester Details</h4>
    <p><b>Name:</b> <?= h((string)$selected['requester_name']) ?></p>
    <p><b>Email:</b> <?= h((string)$selected['requester_email']) ?></p>
    <?php if (!empty($selected['account_username'])): ?>
      <p><b>Logged-in user:</b> <?= h((string)$selected['account_username']) ?> (#<?= h((string)$selected['user_id']) ?>), <?= h((string)$selected['account_email']) ?></p>
    <?php else: ?>
      <p><b>Logged-in user:</b> None / public visitor</p>
    <?php endif; ?>
    <p><b>IP:</b> <?= h((string)($selected['created_ip'] ?? '')) ?></p>
    <p><b>Browser:</b> <?= h((string)($selected['created_user_agent'] ?? '')) ?></p>
  </div>
  <?php foreach ($messages as $message): ?>
    <?php
      $adminBody = HobcSupportI18n::messageForAdmin($message, $selected);
      $showOriginal = HobcSupportI18n::showOriginalHintForAdmin($message, $selected);
    ?>
    <div class="card">
      <p><b><?= h(ucfirst((string)$message['sender_type'])) ?></b> <small><?= admin_h_datetime($message['created_at'] ?? null) ?></small></p>
      <p><?= nl2br(h($adminBody)) ?></p>
      <?php if ($showOriginal): ?><p><small><b>Original (<?= h(HobcSupportI18n::ticketRequesterLocale($selected)) ?>):</b> <?= nl2br(h((string)$message['message'])) ?></small></p><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="ticket_id" value="<?= h((string)$selected['id']) ?>">
    <input type="hidden" name="action" value="reply">
    <label>Admin Reply<br><textarea name="message" rows="7" required></textarea></label><br><br>
    <button type="submit">Send Reply</button>
  </form>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="ticket_id" value="<?= h((string)$selected['id']) ?>">
    <input type="hidden" name="action" value="status">
    <label>Status
      <select name="status">
        <?php foreach (['open', 'waiting_user', 'waiting_admin', 'closed'] as $status): ?>
          <option value="<?= h($status) ?>" <?= $selected['status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit">Update Status</button>
  </form>
</div>
<?php endif; ?>
<?php render_admin_footer(); ?>
