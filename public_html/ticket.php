<?php
declare(strict_types=1);

require_once __DIR__ . '/app/i18n.php';
hobc_i18n_bootstrap();
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/view.php';
require_once __DIR__ . '/app/support_i18n.php';

$pageId = 'ticket';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
support_i18n_ensure_schema();
$ticket = null;
$messages = [];
$err = '';
$ok = '';

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $pdo = wallet_db();
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE public_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $ticket = $stmt->fetch();
}

if (!$ticket) {
    $err = hobc_tp($pageId, 'error.not_found', [], 'Ticket not found.');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $message = trim((string)($_POST['message'] ?? ''));
    if ($message === '') {
        $err = hobc_tp($pageId, 'error.message_required', [], 'Message is required.');
    } elseif ((string)$ticket['status'] === 'closed') {
        $err = hobc_tp($pageId, 'error.closed', [], 'This ticket is closed.');
    } else {
        $msg = wallet_db()->prepare(
            "INSERT INTO support_ticket_messages (ticket_id, sender_type, message)
             VALUES (?, ?, ?)"
        );
        $msg->execute([(int)$ticket['id'], $ticket['user_id'] ? 'user' : 'guest', $message]);
        $messageId = (int)wallet_db()->lastInsertId();
        HobcSupportI18n::onUserMessage((int)$ticket['id'], $messageId, $message, support_i18n_current_locale());
        wallet_db()->prepare("UPDATE support_tickets SET status = 'waiting_admin' WHERE id = ?")->execute([(int)$ticket['id']]);
        $ok = hobc_tp($pageId, 'success.reply_added', [], 'Reply added.');
        $stmt = wallet_db()->prepare("SELECT * FROM support_tickets WHERE public_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $ticket = $stmt->fetch();
    }
}

if ($ticket) {
    $m = wallet_db()->prepare("SELECT id, sender_type, message, created_at FROM support_ticket_messages WHERE ticket_id = ? ORDER BY id ASC");
    $m->execute([(int)$ticket['id']]);
    $messages = $m->fetchAll();
}

render_header(hobc_tp($pageId, 'title', [], 'Support Ticket'));
?>
<div class="card">
  <h3><?= hobc_tpe($pageId, 'title', [], 'Support Ticket') ?></h3>
  <?php if ($err): ?><p class="err"><?= hobc_e($err) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= hobc_e($ok) ?></p><?php endif; ?>
  <?php if ($ticket): ?>
    <p><b><?= h($ticket['subject']) ?></b></p>
    <p><?= hobc_tpe($pageId, 'label.status', [], 'Status:') ?> <?= h(hobc_tp($pageId, 'status.' . (string)$ticket['status'], [], (string)$ticket['status'])) ?></p>
    <?php foreach ($messages as $msg): ?>
      <?php
        $senderKey = 'sender.' . (string)$msg['sender_type'];
        $senderLabel = hobc_tp($pageId, $senderKey, [], ucfirst((string)$msg['sender_type']));
      ?>
      <div class="card">
        <p><b><?= hobc_e($senderLabel) ?></b> <small><?= h($msg['created_at']) ?></small></p>
        <p><?= nl2br(h(HobcSupportI18n::messageForUser($msg, $ticket))) ?></p>
      </div>
    <?php endforeach; ?>
    <?php if ((string)$ticket['status'] !== 'closed'): ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <label><?= hobc_tpe($pageId, 'label.reply', [], 'Reply') ?><br><textarea name="message" rows="6" required></textarea></label><br><br>
        <button type="submit"><?= hobc_tpe($pageId, 'button.add_reply', [], 'Add Reply') ?></button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
