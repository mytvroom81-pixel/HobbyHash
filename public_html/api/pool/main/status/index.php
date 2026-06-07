<?php
require_once __DIR__ . '/../../../_bootstrap.php';
$lite = isset($_GET['lite']) && $_GET['lite'] !== '0';
hobc_json(hobc_pool_status('main', $lite));
