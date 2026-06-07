<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/view.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
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
    $err = 'Ticket not found. Check your tracking link.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    $message = trim((string)($_POST['message'] ?? ''));
    if ($message === '') {
        $err = 'Message is required.';
    } elseif ((string)$ticket['status'] === 'closed') {
        $err = 'This ticket is closed.';
    } else {
        $msg = wallet_db()->prepare(
            "INSERT INTO support_ticket_messages (ticket_id, sender_type, message)
             VALUES (?, ?, ?)"
        );
        $msg->execute([(int)$ticket['id'], $ticket['user_id'] ? 'user' : 'guest', $message]);
        wallet_db()->prepare("UPDATE support_tickets SET status = 'waiting_admin' WHERE id = ?")->execute([(int)$ticket['id']]);
        $ok = 'Reply added.';
        $stmt = wallet_db()->prepare("SELECT * FROM support_tickets WHERE public_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $ticket = $stmt->fetch();
    }
}

if ($ticket) {
    $m = wallet_db()->prepare("SELECT sender_type, message, created_at FROM support_ticket_messages WHERE ticket_id = ? ORDER BY id ASC");
    $m->execute([(int)$ticket['id']]);
    $messages = $m->fetchAll();
}

render_header('Ticket');
?>
<div class="card">
  <h3>Ticket Tracking</h3>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <?php if ($ticket): ?>
    <p><b><?= h($ticket['subject']) ?></b></p>
    <p>Status: <?= h($ticket['status']) ?></p>
    <?php foreach ($messages as $msg): ?>
      <div class="card">
        <p><b><?= h(ucfirst((string)$msg['sender_type'])) ?></b> <small><?= h($msg['created_at']) ?></small></p>
        <p><?= nl2br(h($msg['message'])) ?></p>
      </div>
    <?php endforeach; ?>
    <?php if ((string)$ticket['status'] !== 'closed'): ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <label>Reply<br><textarea name="message" rows="6" required></textarea></label><br><br>
        <button type="submit">Add Reply</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
