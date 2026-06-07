<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/support_context.php';
require_once __DIR__ . '/../app/mailer.php';
require_once __DIR__ . '/../app/view.php';

$user = auth_require_user();
$err = '';
$ok = '';
$sourceContext = support_context_from_request('Wallet support');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    support_context_ensure_schema();
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    if ($subject === '' || $message === '') {
        $err = 'Subject and message are required.';
    } else {
        $pdo = wallet_db();
        $token = bin2hex(random_bytes(32));
        $pdo->beginTransaction();
        try {
            $ticket = $pdo->prepare(
                "INSERT INTO support_tickets
                (user_id, public_token, requester_name, requester_email, subject, source, source_context, created_ip, created_user_agent)
                 VALUES (?, ?, ?, ?, ?, 'wallet', ?, ?, ?)"
            );
            $ticket->execute([
                (int)$user['id'],
                $token,
                (string)$user['username'],
                (string)$user['email'],
                substr($subject, 0, 190),
                $sourceContext,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            ]);
            $ticketId = (int)$pdo->lastInsertId();
            $msg = $pdo->prepare(
                "INSERT INTO support_ticket_messages (ticket_id, sender_type, sender_user_id, message)
                 VALUES (?, 'user', ?, ?)"
            );
            $msg->execute([$ticketId, (int)$user['id'], $message]);
            $pdo->commit();
            $ok = 'Support ticket created.';
            $trackUrl = 'https://hobbyhashcoin.com/ticket.php?token=' . $token;
            $emailRows = [
                'Section' => $sourceContext,
                'Subject' => substr($subject, 0, 190),
                'Tracking link' => $trackUrl,
            ];
            $emailText = mailer_support_text(
                'HOBC support ticket received',
                $emailRows,
                'Your HOBC support ticket was created. We will reply as soon as possible.',
                $trackUrl
            );
            $emailHtml = mailer_support_html(
                'Support Ticket Received',
                $emailRows,
                '<p>Your HOBC support ticket was created. We will reply as soon as possible.</p>',
                $trackUrl
            );
            mailer_send(
                (string)$user['email'],
                'HOBC support ticket received: ' . substr($subject, 0, 190),
                $emailText,
                $emailHtml
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            wallet_log_error('wallet ticket create failed: ' . $e->getMessage());
            $err = 'Support ticket could not be created.';
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

render_header('Support');
?>
<div class="card">
  <h3>Support</h3>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <?php if ($ok): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="source_context" value="<?= h($sourceContext) ?>">
    <p><b>Support section:</b> <?= h($sourceContext) ?></p>
    <label>Subject<br><input name="subject" maxlength="190" required></label><br><br>
    <label>Message<br><textarea name="message" rows="8" required></textarea></label><br><br>
    <button type="submit">Create Ticket</button>
  </form>
</div>

<div class="card">
  <h3>Your Tickets</h3>
  <table>
    <tr><th>ID</th><th>Subject</th><th>Status</th><th>Updated</th><th>Track</th></tr>
    <?php foreach ($tickets as $ticket): ?>
      <tr>
        <td><?= h((string)$ticket['id']) ?></td>
        <td><?= h($ticket['subject']) ?></td>
        <td><?= h($ticket['status']) ?></td>
        <td><?= h($ticket['updated_at']) ?></td>
        <td><a href="<?= h('/ticket.php?token=' . $ticket['public_token']) ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($ticketTotal === 0): ?>
      <tr><td colspan="5">No tickets found yet.</td></tr>
    <?php endif; ?>
  </table>
  <?= wallet_pagination('/support.php', 'ticket_page', $ticketPage, $ticketTotalPages, ['section' => $sourceContext]) ?>
</div>
<?php render_footer(); ?>
