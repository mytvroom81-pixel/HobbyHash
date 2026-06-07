<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/support_context.php';
require_once __DIR__ . '/app/mailer.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/view.php';

$err = '';
$trackUrl = '';
$sourceContext = support_context_from_request('Public contact');
$currentUser = auth_current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate_or_fail();
    support_context_ensure_schema();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        $err = 'Please fill out all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email address.';
    } else {
        $pdo = wallet_db();
        $token = bin2hex(random_bytes(32));
        $pdo->beginTransaction();
        try {
            $ticket = $pdo->prepare(
                "INSERT INTO support_tickets
                (user_id, public_token, requester_name, requester_email, subject, source, source_context, created_ip, created_user_agent)
                 VALUES (?, ?, ?, ?, ?, 'public', ?, ?, ?)"
            );
            $ticket->execute([
                $currentUser ? (int)$currentUser['id'] : null,
                $token,
                substr($name, 0, 120),
                substr($email, 0, 190),
                substr($subject, 0, 190),
                $sourceContext,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            ]);
            $ticketId = (int)$pdo->lastInsertId();
            $msg = $pdo->prepare(
                "INSERT INTO support_ticket_messages (ticket_id, sender_type, message)
                 VALUES (?, 'guest', ?)"
            );
            $msg->execute([$ticketId, $message]);
            $pdo->commit();
            $trackUrl = '/ticket.php?token=' . $token;
            $trackFullUrl = 'https://hobbyhashcoin.com' . $trackUrl;
            $emailRows = [
                'Section' => $sourceContext,
                'Subject' => substr($subject, 0, 190),
                'Tracking link' => $trackFullUrl,
            ];
            $emailText = mailer_support_text(
                'HOBC support ticket received',
                $emailRows,
                'Your HOBC support ticket was created. We will reply as soon as possible.',
                $trackFullUrl
            );
            $emailHtml = mailer_support_html(
                'Support Ticket Received',
                $emailRows,
                '<p>Your HOBC support ticket was created. We will reply as soon as possible.</p>',
                $trackFullUrl
            );
            mailer_send(
                substr($email, 0, 190),
                'HOBC support ticket received: ' . substr($subject, 0, 190),
                $emailText,
                $emailHtml
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            wallet_log_error('public ticket create failed: ' . $e->getMessage());
            $err = 'Ticket could not be created.';
        }
    }
}

render_header('Contact');
?>
<div class="card">
  <h3>Contact Support</h3>
  <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
  <?php if ($trackUrl): ?>
    <p class="ok">Ticket created. Track it here: <a href="<?= h($trackUrl) ?>"><?= h($trackUrl) ?></a></p>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="source_context" value="<?= h($sourceContext) ?>">
      <p><b>Support section:</b> <?= h($sourceContext) ?></p>
      <label>Name<br><input name="name" maxlength="120" required></label><br><br>
      <label>Email<br><input type="email" name="email" maxlength="190" required></label><br><br>
      <label>Subject<br><input name="subject" maxlength="190" required></label><br><br>
      <label>Message<br><textarea name="message" rows="8" required></textarea></label><br><br>
      <button type="submit">Create Ticket</button>
    </form>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
