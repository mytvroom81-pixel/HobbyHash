<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/view.php';

render_header('Maintenance');
?>
<div class="card">
  <h3>Wallet Maintenance</h3>
  <p class="warn">HOBC Web Wallet is currently in maintenance mode.</p>
  <p>Please try again later. Deposits and withdrawals may be temporarily unavailable.</p>
</div>
<?php render_footer(); ?>
