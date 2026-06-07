<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/security_log.php';
require_once __DIR__ . '/../app/settings.php';
require_once __DIR__ . '/../app/admin_view.php';
require_once __DIR__ . '/../app/content_admin.php';

$admin = admin_require_user();
$pdo = wallet_db();
$tabs = [
    'pages' => 'Pages & Content',
    'docs' => 'Docs',
    'downloads' => 'Downloads',
    'announcements' => 'Announcements',
    'burn' => 'Burn Events',
    'reserve' => 'Treasury / Reserve',
    'support' => 'Support Messages',
];
$requestedTab = (string)($_GET['tab'] ?? 'pages');
$tab = array_key_exists($requestedTab, $tabs) ? $requestedTab : 'pages';
$editId = (int)($_GET['id'] ?? 0);
$previewId = (int)($_GET['preview'] ?? 0);
$msg = '';
$err = '';

function content_admin_redirect(string $tab, int $id = 0): void
{
    $url = admin_url('/content.php?tab=' . rawurlencode($tab));
    if ($id > 0) {
        $url .= '&id=' . $id;
    }
    wallet_redirect($url);
}

function content_admin_status_options(array $values, string $selected): string
{
    $html = '';
    foreach ($values as $value => $label) {
        $html .= '<option value="' . h((string)$value) . '"' . ($selected === (string)$value ? ' selected' : '') . '>' . h((string)$label) . '</option>';
    }
    return $html;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_validate_or_fail();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'save_doc') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $slug = content_admin_slug((string)($_POST['slug'] ?? $title));
            $body = trim((string)($_POST['body'] ?? ''));
            $status = in_array((string)($_POST['status'] ?? 'draft'), ['draft', 'published', 'archived'], true) ? (string)$_POST['status'] : 'draft';
            if ($title === '' || $body === '') {
                throw new RuntimeException('Docs title and body are required.');
            }
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE docs_pages SET title=?, slug=?, category=?, body=?, status=?, sort_order=?, seo_title=?, seo_description=?, updated_by_admin_id=?, published_at=IF(?='published', COALESCE(published_at, UTC_TIMESTAMP()), published_at) WHERE id=?");
                $stmt->execute([$title, $slug, trim((string)($_POST['category'] ?? '')), $body, $status, (int)($_POST['sort_order'] ?? 0), trim((string)($_POST['seo_title'] ?? '')), trim((string)($_POST['seo_description'] ?? '')), (int)$admin['id'], $status, $id]);
                admin_audit((int)$admin['id'], 'docs_page_update', 'docs_page', (string)$id, ['slug' => $slug, 'status' => $status]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO docs_pages (title, slug, category, body, status, sort_order, seo_title, seo_description, created_by_admin_id, updated_by_admin_id, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, IF(?='published', UTC_TIMESTAMP(), NULL))");
                $stmt->execute([$title, $slug, trim((string)($_POST['category'] ?? '')), $body, $status, (int)($_POST['sort_order'] ?? 0), trim((string)($_POST['seo_title'] ?? '')), trim((string)($_POST['seo_description'] ?? '')), (int)$admin['id'], (int)$admin['id'], $status]);
                $id = (int)$pdo->lastInsertId();
                admin_audit((int)$admin['id'], 'docs_page_create', 'docs_page', (string)$id, ['slug' => $slug, 'status' => $status]);
            }
            require_once __DIR__ . '/../app/i18n_db_content.php';
            hobc_i18n_admin_publish('docs_pages', $id);
            content_admin_redirect('docs', $id);
        } elseif ($action === 'save_download') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $fileUrl = trim((string)($_POST['file_url'] ?? ''));
            $checksum = strtolower(trim((string)($_POST['checksum_sha256'] ?? '')));
            $status = in_array((string)($_POST['status'] ?? 'draft'), ['draft', 'published', 'archived'], true) ? (string)$_POST['status'] : 'draft';
            if ($title === '' || $fileUrl === '') {
                throw new RuntimeException('Download title and file URL are required.');
            }
            if ($checksum !== '' && !preg_match('/^[a-f0-9]{64}$/', $checksum)) {
                throw new RuntimeException('SHA256 checksum must be 64 hex characters.');
            }
            if ($status === 'published' && admin_setting_bool('downloads.require_checksum_before_publish', true) && $checksum === '') {
                throw new RuntimeException('A SHA256 checksum is required before publishing this download.');
            }
            $values = [$title, trim((string)($_POST['description'] ?? '')), trim((string)($_POST['platform'] ?? '')), $fileUrl, trim((string)($_POST['version'] ?? '')), $checksum !== '' ? $checksum : null, $status, isset($_POST['is_recommended']) ? 1 : 0, isset($_POST['is_deprecated']) ? 1 : 0, (int)($_POST['sort_order'] ?? 0)];
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE downloads SET title=?, description=?, platform=?, file_url=?, version=?, checksum_sha256=?, status=?, is_recommended=?, is_deprecated=?, sort_order=? WHERE id=?");
                $stmt->execute([...$values, $id]);
                admin_audit((int)$admin['id'], 'download_update', 'download', (string)$id, ['status' => $status]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO downloads (title, description, platform, file_url, version, checksum_sha256, status, is_recommended, is_deprecated, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute($values);
                $id = (int)$pdo->lastInsertId();
                admin_audit((int)$admin['id'], 'download_create', 'download', (string)$id, ['status' => $status]);
            }
            require_once __DIR__ . '/../app/i18n_db_content.php';
            hobc_i18n_admin_publish('downloads', $id);
            content_admin_redirect('downloads', $id);
        } elseif ($action === 'save_announcement') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $slug = content_admin_slug((string)($_POST['slug'] ?? $title));
            $body = trim((string)($_POST['body'] ?? ''));
            $status = in_array((string)($_POST['status'] ?? 'draft'), ['draft', 'published', 'archived', 'unpublished'], true) ? (string)$_POST['status'] : 'draft';
            if ($title === '' || $body === '') {
                throw new RuntimeException('Announcement title and body are required.');
            }
            $publishedAt = trim((string)($_POST['published_at'] ?? ''));
            $values = [$title, $slug, $body, trim((string)($_POST['seo_title'] ?? '')), trim((string)($_POST['seo_description'] ?? '')), $status, isset($_POST['pinned']) ? 1 : 0, isset($_POST['show_on_homepage']) ? 1 : 0, isset($_POST['show_in_wallet_dashboard']) ? 1 : 0, $publishedAt !== '' ? $publishedAt : null];
            if ($id > 0) {
                $pdo->prepare("UPDATE announcements SET title=?, slug=?, body=?, seo_title=?, seo_description=?, status=?, pinned=?, show_on_homepage=?, show_in_wallet_dashboard=?, published_at=? WHERE id=?")->execute([...$values, $id]);
                admin_audit((int)$admin['id'], 'announcement_update', 'announcement', (string)$id, ['status' => $status]);
            } else {
                $pdo->prepare("INSERT INTO announcements (title, slug, body, seo_title, seo_description, status, pinned, show_on_homepage, show_in_wallet_dashboard, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute($values);
                $id = (int)$pdo->lastInsertId();
                admin_audit((int)$admin['id'], 'announcement_create', 'announcement', (string)$id, ['status' => $status]);
            }
            require_once __DIR__ . '/../app/i18n_db_content.php';
            hobc_i18n_admin_publish('announcements', $id);
            content_admin_redirect('announcements', $id);
        } elseif ($action === 'save_burn') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $status = in_array((string)($_POST['status'] ?? 'planned'), ['draft', 'planned', 'pending', 'completed', 'confirmed', 'cancelled', 'rejected', 'archived'], true) ? (string)$_POST['status'] : 'planned';
            if ($title === '') {
                throw new RuntimeException('Burn event title is required.');
            }
            $values = [$title, content_admin_hobc((string)($_POST['amount'] ?? '0')), trim((string)($_POST['txid'] ?? '')), trim((string)($_POST['burn_address'] ?? '')), trim((string)($_POST['proof_url'] ?? '')), $status, isset($_POST['is_published']) ? 1 : 0, trim((string)($_POST['event_date'] ?? '')) ?: null, trim((string)($_POST['notes'] ?? '')), trim((string)($_POST['public_notes'] ?? ''))];
            if ($id > 0) {
                $pdo->prepare("UPDATE burn_events SET title=?, amount=?, txid=?, burn_address=?, proof_url=?, status=?, is_published=?, event_date=?, notes=?, public_notes=? WHERE id=?")->execute([...$values, $id]);
                admin_audit((int)$admin['id'], 'burn_event_update', 'burn_event', (string)$id, ['status' => $status]);
            } else {
                $pdo->prepare("INSERT INTO burn_events (title, amount, txid, burn_address, proof_url, status, is_published, event_date, notes, public_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute($values);
                $id = (int)$pdo->lastInsertId();
                admin_audit((int)$admin['id'], 'burn_event_create', 'burn_event', (string)$id, ['status' => $status]);
            }
            require_once __DIR__ . '/../app/i18n_db_content.php';
            hobc_i18n_admin_publish('burn_events', $id);
            content_admin_redirect('burn', $id);
        } elseif ($action === 'save_reserve_category') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $slug = content_admin_slug((string)($_POST['slug'] ?? $name));
            $status = in_array((string)($_POST['status'] ?? 'active'), ['pending_launch', 'active', 'paused', 'completed', 'inactive', 'archived'], true) ? (string)$_POST['status'] : 'active';
            if ($name === '') {
                throw new RuntimeException('Reserve category name is required.');
            }
            $values = [$name, $slug, (float)($_POST['percentage'] ?? 0), $status, isset($_POST['is_public']) ? 1 : 0, trim((string)($_POST['notes'] ?? ''))];
            if ($id > 0) {
                $pdo->prepare("UPDATE treasury_reserve_categories SET name=?, slug=?, percentage=?, status=?, is_public=?, notes=? WHERE id=?")->execute([...$values, $id]);
                admin_audit((int)$admin['id'], 'reserve_category_update', 'treasury_reserve_category', (string)$id);
            } else {
                $pdo->prepare("INSERT INTO treasury_reserve_categories (name, slug, percentage, status, is_public, notes) VALUES (?, ?, ?, ?, ?, ?)")->execute($values);
                $id = (int)$pdo->lastInsertId();
                admin_audit((int)$admin['id'], 'reserve_category_create', 'treasury_reserve_category', (string)$id);
            }
            require_once __DIR__ . '/../app/i18n_db_content.php';
            hobc_i18n_admin_publish('treasury_reserve_categories', $id);
            content_admin_redirect('reserve', $id);
        } elseif ($action === 'save_reserve_movement') {
            $id = (int)($_POST['id'] ?? 0);
            $status = in_array((string)($_POST['status'] ?? 'pending'), ['draft', 'pending', 'completed', 'confirmed', 'cancelled', 'rejected', 'archived'], true) ? (string)$_POST['status'] : 'pending';
            $type = in_array((string)($_POST['movement_type'] ?? 'outgoing'), ['allocation', 'incoming', 'outgoing', 'adjustment'], true) ? (string)$_POST['movement_type'] : 'outgoing';
            $values = [(int)($_POST['category_id'] ?? 0) ?: null, content_admin_hobc((string)($_POST['amount'] ?? '0')), trim((string)($_POST['txid'] ?? '')), $type, $status, isset($_POST['is_public']) ? 1 : 0, trim((string)($_POST['notes'] ?? ''))];
            if ($id > 0) {
                $pdo->prepare("UPDATE treasury_reserve_movements SET category_id=?, amount=?, txid=?, movement_type=?, status=?, is_public=?, notes=? WHERE id=?")->execute([...$values, $id]);
                admin_audit((int)$admin['id'], 'reserve_movement_update', 'treasury_reserve_movement', (string)$id);
            } else {
                $pdo->prepare("INSERT INTO treasury_reserve_movements (category_id, amount, txid, movement_type, status, is_public, notes) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute($values);
                $id = (int)$pdo->lastInsertId();
                admin_audit((int)$admin['id'], 'reserve_movement_create', 'treasury_reserve_movement', (string)$id);
            }
            require_once __DIR__ . '/../app/i18n_db_content.php';
            hobc_i18n_admin_publish('treasury_reserve_movements', $id);
            content_admin_redirect('reserve', $id);
        } elseif ($action === 'support_message_status') {
            $id = (int)($_POST['id'] ?? 0);
            $status = in_array((string)($_POST['status'] ?? 'open'), ['new', 'open', 'waiting', 'waiting_user', 'waiting_admin', 'closed', 'spam', 'archived'], true) ? (string)$_POST['status'] : 'open';
            $pdo->prepare("UPDATE support_messages SET status=?, is_read=?, is_spam=?, admin_notes=? WHERE id=?")->execute([$status, isset($_POST['is_read']) ? 1 : 0, isset($_POST['is_spam']) ? 1 : 0, trim((string)($_POST['admin_notes'] ?? '')), $id]);
            admin_audit((int)$admin['id'], 'support_message_update', 'support_message', (string)$id, ['status' => $status]);
            content_admin_redirect('support', $id);
        } else {
            throw new RuntimeException('Unknown content admin action.');
        }
    } catch (Throwable $e) {
        wallet_log_error('content admin action failed: ' . $e->getMessage());
        $err = $e->getMessage();
    }
}

$docEdit = $editId > 0 && $tab === 'docs' ? ($pdo->query("SELECT * FROM docs_pages WHERE id = " . (int)$editId)->fetch() ?: null) : null;
$downloadEdit = $editId > 0 && $tab === 'downloads' ? ($pdo->query("SELECT * FROM downloads WHERE id = " . (int)$editId)->fetch() ?: null) : null;
$announcementEdit = $editId > 0 && $tab === 'announcements' ? ($pdo->query("SELECT * FROM announcements WHERE id = " . (int)$editId)->fetch() ?: null) : null;
$burnEdit = $editId > 0 && $tab === 'burn' ? ($pdo->query("SELECT * FROM burn_events WHERE id = " . (int)$editId)->fetch() ?: null) : null;
$categoryEdit = $editId > 0 && $tab === 'reserve' ? ($pdo->query("SELECT * FROM treasury_reserve_categories WHERE id = " . (int)$editId)->fetch() ?: null) : null;

render_admin_header('Public Content Admin', ['Public Content']);
?>
<?php if ($msg): ?><?php admin_render_alert('success', $msg); ?><?php endif; ?>
<?php if ($err): ?><?php admin_render_alert('error', $err); ?><?php endif; ?>
<div class="admin-card">
  <div class="admin-actions">
    <?php foreach ($tabs as $key => $label): ?><a class="admin-action <?= $tab === $key ? 'admin-action-primary' : 'admin-action-secondary' ?>" href="<?= h(admin_url('/content.php?tab=' . rawurlencode($key))) ?>"><?= h($label) ?></a><?php endforeach; ?>
  </div>
</div>

<?php if ($previewId > 0 && $tab === 'docs'): ?>
  <?php $preview = $pdo->query("SELECT * FROM docs_pages WHERE id = " . (int)$previewId)->fetch(); ?>
  <?php if ($preview): ?><div class="admin-card"><h2>Preview: <?= h((string)$preview['title']) ?></h2><p><b>SEO title:</b> <?= h((string)($preview['seo_title'] ?? '')) ?></p><p><b>SEO description:</b> <?= h((string)($preview['seo_description'] ?? '')) ?></p><hr><?= nl2br(h((string)$preview['body'])) ?></div><?php endif; ?>
<?php endif; ?>

<?php if ($tab === 'pages'): ?>
  <div class="admin-grid admin-grid-tight">
    <?= admin_stat_card('Hardcoded Public Docs', (string)count(content_admin_public_doc_rows()), 'info') ?>
    <?= admin_stat_card('Managed Docs', (string)content_admin_count($pdo, 'docs_pages'), 'ok') ?>
    <?= admin_stat_card('Downloads', (string)content_admin_count($pdo, 'downloads'), 'info') ?>
    <?= admin_stat_card('Announcements', (string)content_admin_count($pdo, 'announcements'), 'warn') ?>
    <?= admin_stat_card('Support Messages', (string)content_admin_count($pdo, 'support_messages'), 'warn') ?>
  </div>
  <div class="admin-card"><h3>Pages & Content</h3><p>Existing hardcoded public pages are preserved. Managed content created here can be previewed and used by public templates when ready.</p><?php admin_render_table(['Page', 'Route', 'Tracked Views', 'Updated'], array_map(static fn(array $row): array => [h($row['title']), '<a href="' . h($row['url']) . '">' . h($row['url']) . '</a>', h((string)content_admin_page_views(wallet_db(), $row['url'])), h($row['updated'])], content_admin_public_doc_rows()), 'No public docs found', 'No docs index files were detected.'); ?></div>
<?php elseif ($tab === 'docs'): ?>
  <div class="admin-card"><h3><?= $docEdit ? 'Edit Docs Page' : 'Create Docs Page' ?></h3><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_doc"><input type="hidden" name="id" value="<?= h((string)($docEdit['id'] ?? 0)) ?>"><label>Title<br><input name="title" value="<?= h((string)($docEdit['title'] ?? '')) ?>" required></label><br><br><label>Slug<br><input name="slug" value="<?= h((string)($docEdit['slug'] ?? '')) ?>"></label><br><br><label>Category<br><input name="category" value="<?= h((string)($docEdit['category'] ?? '')) ?>"></label><br><br><label>Order<br><input type="number" name="sort_order" value="<?= h((string)($docEdit['sort_order'] ?? 0)) ?>"></label><br><br><label>Status<br><select name="status"><?= content_admin_status_options(['draft'=>'Draft','published'=>'Published','archived'=>'Archived'], (string)($docEdit['status'] ?? 'draft')) ?></select></label><br><br><label>SEO Title<br><input name="seo_title" value="<?= h((string)($docEdit['seo_title'] ?? '')) ?>"></label><br><br><label>SEO Description<br><textarea name="seo_description" rows="2"><?= h((string)($docEdit['seo_description'] ?? '')) ?></textarea></label><br><br><label>Body<br><textarea name="body" rows="12" required><?= h((string)($docEdit['body'] ?? '')) ?></textarea></label><br><br><button type="submit">Save Docs Page</button></form></div>
  <?php $rows = $pdo->query("SELECT * FROM docs_pages ORDER BY sort_order ASC, id DESC")->fetchAll(); ?>
  <div class="admin-card"><h3>Managed Docs Pages</h3><?php admin_filter_box('Filter docs'); ?><?php admin_render_table(['Title','Slug','Category','Status','Order','Views','Actions'], array_map(static fn(array $row): array => [h((string)$row['title']), h((string)$row['slug']), h((string)$row['category']), h((string)$row['status']), h((string)$row['sort_order']), h((string)content_admin_page_views(wallet_db(), '/docs/' . $row['slug'] . '/')), '<a href="' . h(admin_url('/content.php?tab=docs&id=' . (int)$row['id'])) . '">Edit</a> | <a href="' . h(admin_url('/content.php?tab=docs&preview=' . (int)$row['id'])) . '">Preview</a>'], $rows), 'No managed docs yet', 'Create the first managed docs page above.'); ?></div>
<?php elseif ($tab === 'downloads'): ?>
  <div class="admin-card"><h3><?= $downloadEdit ? 'Edit Download' : 'Add Download' ?></h3><p class="warn">File upload is not enabled in this admin. Use an external or already-uploaded file URL and paste its SHA256 checksum.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_download"><input type="hidden" name="id" value="<?= h((string)($downloadEdit['id'] ?? 0)) ?>"><label>Title<br><input name="title" value="<?= h((string)($downloadEdit['title'] ?? '')) ?>" required></label><br><br><label>Description<br><textarea name="description" rows="3"><?= h((string)($downloadEdit['description'] ?? '')) ?></textarea></label><br><br><label>Platform<br><input name="platform" value="<?= h((string)($downloadEdit['platform'] ?? '')) ?>" required></label><br><br><label>Version<br><input name="version" value="<?= h((string)($downloadEdit['version'] ?? '')) ?>"></label><br><br><label>File URL<br><input name="file_url" value="<?= h((string)($downloadEdit['file_url'] ?? '')) ?>" required></label><br><br><label>SHA256<br><input name="checksum_sha256" value="<?= h((string)($downloadEdit['checksum_sha256'] ?? '')) ?>" maxlength="64"></label><br><br><label>Status<br><select name="status"><?= content_admin_status_options(['draft'=>'Draft','published'=>'Published','archived'=>'Archived'], (string)($downloadEdit['status'] ?? 'draft')) ?></select></label><br><br><label>Order<br><input type="number" name="sort_order" value="<?= h((string)($downloadEdit['sort_order'] ?? 0)) ?>"></label><br><label><input type="checkbox" name="is_recommended" <?= !empty($downloadEdit['is_recommended']) ? 'checked' : '' ?>> Recommended</label><br><label><input type="checkbox" name="is_deprecated" <?= !empty($downloadEdit['is_deprecated']) ? 'checked' : '' ?>> Deprecated</label><br><br><button type="submit">Save Download</button></form></div>
  <?php $rows = $pdo->query("SELECT d.*, COUNT(de.id) AS event_count FROM downloads d LEFT JOIN download_events de ON de.download_id=d.id GROUP BY d.id ORDER BY d.sort_order ASC, d.id DESC")->fetchAll(); ?>
  <div class="admin-card"><h3>Downloads</h3><?php admin_render_table(['Title','Platform','Version','Status','Count','Events','Flags','Actions'], array_map(static fn(array $row): array => [h((string)$row['title']), h((string)$row['platform']), h((string)$row['version']), h((string)$row['status']), h((string)$row['download_count']), h((string)$row['event_count']), (!empty($row['is_recommended'])?'Recommended ':'') . (!empty($row['is_deprecated'])?'Deprecated':''), '<a href="' . h(admin_url('/content.php?tab=downloads&id=' . (int)$row['id'])) . '">Edit</a>'], $rows), 'No downloads', 'Add the first download above.'); ?></div>
  <?php $events = $pdo->query("SELECT de.*, d.title FROM download_events de LEFT JOIN downloads d ON d.id=de.download_id ORDER BY de.id DESC LIMIT 100")->fetchAll(); ?>
  <div class="admin-card"><h3>Download Event Logs</h3><?php admin_render_table(['When','Download','IP','Referrer','User Agent'], array_map(static fn(array $row): array => [admin_h_datetime($row['created_at'] ?? null), h((string)($row['title'] ?? 'unknown')), '<code>' . h((string)($row['ip_address'] ?? (substr((string)$row['ip_hash'],0,16) . '...'))) . '</code>', h((string)$row['referrer']), h(content_admin_short((string)$row['user_agent']))], $events), 'No download events', 'Download click events will appear after visitors click tracked downloads.'); ?></div>
<?php elseif ($tab === 'announcements'): ?>
  <div class="admin-card"><h3><?= $announcementEdit ? 'Edit Announcement' : 'Create Announcement' ?></h3><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_announcement"><input type="hidden" name="id" value="<?= h((string)($announcementEdit['id'] ?? 0)) ?>"><label>Title<br><input name="title" value="<?= h((string)($announcementEdit['title'] ?? '')) ?>" required></label><br><br><label>Slug<br><input name="slug" value="<?= h((string)($announcementEdit['slug'] ?? '')) ?>"></label><br><br><label>Body<br><textarea name="body" rows="8" required><?= h((string)($announcementEdit['body'] ?? '')) ?></textarea></label><br><br><label>Status<br><select name="status"><?= content_admin_status_options(['draft'=>'Draft','published'=>'Published','unpublished'=>'Unpublished','archived'=>'Archived'], (string)($announcementEdit['status'] ?? 'draft')) ?></select></label><br><br><label>Schedule Publish Date UTC<br><input name="published_at" value="<?= h((string)($announcementEdit['published_at'] ?? '')) ?>" placeholder="YYYY-MM-DD HH:MM:SS"></label><br><label><input type="checkbox" name="pinned" <?= !empty($announcementEdit['pinned']) ? 'checked' : '' ?>> Pinned</label><br><label><input type="checkbox" name="show_on_homepage" <?= !empty($announcementEdit['show_on_homepage']) ? 'checked' : '' ?>> Show on homepage</label><br><label><input type="checkbox" name="show_in_wallet_dashboard" <?= !empty($announcementEdit['show_in_wallet_dashboard']) ? 'checked' : '' ?>> Show in wallet dashboard</label><br><br><label>SEO Title<br><input name="seo_title" value="<?= h((string)($announcementEdit['seo_title'] ?? '')) ?>"></label><br><br><label>SEO Description<br><textarea name="seo_description" rows="2"><?= h((string)($announcementEdit['seo_description'] ?? '')) ?></textarea></label><br><br><button type="submit">Save Announcement</button></form></div>
  <?php $rows = $pdo->query("SELECT * FROM announcements ORDER BY pinned DESC, published_at DESC, id DESC")->fetchAll(); ?>
  <div class="admin-card"><h3>Announcements</h3><?php admin_render_table(['Title','Slug','Status','Pinned','Homepage','Wallet','Publish Date','Actions'], array_map(static fn(array $row): array => [h((string)$row['title']), h((string)$row['slug']), h((string)$row['status']), !empty($row['pinned'])?'Yes':'No', !empty($row['show_on_homepage'])?'Yes':'No', !empty($row['show_in_wallet_dashboard'])?'Yes':'No', admin_h_utc_datetime($row['published_at'] ?? null), '<a href="' . h(admin_url('/content.php?tab=announcements&id=' . (int)$row['id'])) . '">Edit</a>'], $rows), 'No announcements', 'Create the first announcement above.'); ?></div>
<?php elseif ($tab === 'burn'): ?>
  <div class="admin-card"><h3><?= $burnEdit ? 'Edit Burn Event' : 'Add Burn Event' ?></h3><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_burn"><input type="hidden" name="id" value="<?= h((string)($burnEdit['id'] ?? 0)) ?>"><label>Title<br><input name="title" value="<?= h((string)($burnEdit['title'] ?? '')) ?>" required></label><br><br><label>Amount<br><input type="number" step="0.00000001" name="amount" value="<?= h(content_admin_hobc($burnEdit['amount'] ?? 0)) ?>"></label><br><br><label>Burn Address<br><input name="burn_address" value="<?= h((string)($burnEdit['burn_address'] ?? '')) ?>"></label><br><br><label>TXID<br><input name="txid" value="<?= h((string)($burnEdit['txid'] ?? '')) ?>"></label><br><br><label>Proof URL<br><input name="proof_url" value="<?= h((string)($burnEdit['proof_url'] ?? '')) ?>"></label><br><br><label>Status<br><select name="status"><?= content_admin_status_options(['draft'=>'Draft','planned'=>'Planned','pending'=>'Pending','completed'=>'Completed','confirmed'=>'Confirmed','cancelled'=>'Cancelled','rejected'=>'Rejected','archived'=>'Archived'], (string)($burnEdit['status'] ?? 'planned')) ?></select></label><br><br><label>Event Date<br><input name="event_date" value="<?= h((string)($burnEdit['event_date'] ?? '')) ?>" placeholder="YYYY-MM-DD"></label><br><label><input type="checkbox" name="is_published" <?= !empty($burnEdit['is_published']) ? 'checked' : '' ?>> Published</label><br><br><label>Private Notes<br><textarea name="notes" rows="3"><?= h((string)($burnEdit['notes'] ?? '')) ?></textarea></label><br><br><label>Public Notes<br><textarea name="public_notes" rows="3"><?= h((string)($burnEdit['public_notes'] ?? '')) ?></textarea></label><br><br><button type="submit">Save Burn Event</button></form></div>
  <?php $rows = $pdo->query("SELECT * FROM burn_events ORDER BY id DESC")->fetchAll(); ?><div class="admin-card"><h3>Burn Events</h3><?php admin_render_table(['Title','Amount','TXID','Status','Published','Date','Actions'], array_map(static fn(array $row): array => [h((string)$row['title']), h(content_admin_hobc($row['amount'])), admin_hash_cell((string)$row['txid'], (string)$row['txid'] !== ''), h((string)$row['status']), !empty($row['is_published'])?'Yes':'No', h((string)$row['event_date']), '<a href="' . h(admin_url('/content.php?tab=burn&id=' . (int)$row['id'])) . '">Edit</a>'], $rows), 'No burn events', 'Add planned or completed burn events above.'); ?></div>
<?php elseif ($tab === 'reserve'): ?>
  <div class="admin-grid"><div class="admin-card"><h3><?= $categoryEdit ? 'Edit Reserve Category' : 'Create Reserve Category' ?></h3><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_reserve_category"><input type="hidden" name="id" value="<?= h((string)($categoryEdit['id'] ?? 0)) ?>"><label>Name<br><input name="name" value="<?= h((string)($categoryEdit['name'] ?? '')) ?>" required></label><br><br><label>Slug<br><input name="slug" value="<?= h((string)($categoryEdit['slug'] ?? '')) ?>"></label><br><br><label>Percentage<br><input type="number" step="0.0001" name="percentage" value="<?= h((string)($categoryEdit['percentage'] ?? 0)) ?>"></label><br><br><label>Status<br><select name="status"><?= content_admin_status_options(['pending_launch'=>'Pending launch','active'=>'Active','paused'=>'Paused','completed'=>'Completed','inactive'=>'Inactive','archived'=>'Archived'], (string)($categoryEdit['status'] ?? 'active')) ?></select></label><br><label><input type="checkbox" name="is_public" <?= !isset($categoryEdit['is_public']) || !empty($categoryEdit['is_public']) ? 'checked' : '' ?>> Public</label><br><br><label>Notes<br><textarea name="notes" rows="3"><?= h((string)($categoryEdit['notes'] ?? '')) ?></textarea></label><br><br><button type="submit">Save Category</button></form></div><div class="admin-card"><h3>Add Reserve Movement</h3><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="save_reserve_movement"><label>Category<br><select name="category_id"><option value="">None</option><?php foreach ($pdo->query("SELECT id,name FROM treasury_reserve_categories ORDER BY name") as $cat): ?><option value="<?= h((string)$cat['id']) ?>"><?= h((string)$cat['name']) ?></option><?php endforeach; ?></select></label><br><br><label>Amount<br><input type="number" step="0.00000001" name="amount" value="0.00000000"></label><br><br><label>TXID<br><input name="txid"></label><br><br><label>Type<br><select name="movement_type"><?= content_admin_status_options(['allocation'=>'Allocation','incoming'=>'Incoming','outgoing'=>'Outgoing','adjustment'=>'Adjustment'], 'outgoing') ?></select></label><br><br><label>Status<br><select name="status"><?= content_admin_status_options(['draft'=>'Draft','pending'=>'Pending','completed'=>'Completed','confirmed'=>'Confirmed','cancelled'=>'Cancelled','rejected'=>'Rejected','archived'=>'Archived'], 'pending') ?></select></label><br><label><input type="checkbox" name="is_public" checked> Public</label><br><br><label>Notes<br><textarea name="notes" rows="3"></textarea></label><br><br><button type="submit">Save Movement</button></form></div></div>
  <?php $cats = $pdo->query("SELECT * FROM treasury_reserve_categories ORDER BY percentage DESC, name")->fetchAll(); ?><div class="admin-card"><h3>Reserve Categories</h3><?php admin_render_table(['Name','Slug','Percent','Status','Public','Notes','Actions'], array_map(static fn(array $row): array => [h((string)$row['name']), h((string)$row['slug']), h((string)$row['percentage']), h((string)$row['status']), !empty($row['is_public'])?'Yes':'No', h(content_admin_short((string)$row['notes'])), '<a href="' . h(admin_url('/content.php?tab=reserve&id=' . (int)$row['id'])) . '">Edit</a>'], $cats), 'No categories', 'Create reserve categories above.'); ?></div>
  <?php $movementCount = (int)$pdo->query('SELECT COUNT(*) FROM treasury_reserve_movements')->fetchColumn(); $movementPager = admin_pagination_meta(admin_page_state(50)['page'], 50, $movementCount); $movements = $pdo->query("SELECT m.*, c.name AS category_name FROM treasury_reserve_movements m LEFT JOIN treasury_reserve_categories c ON c.id=m.category_id ORDER BY m.id DESC LIMIT {$movementPager['per_page']} OFFSET {$movementPager['offset']}")->fetchAll(); ?><div class="admin-card"><h3>Reserve Movements</h3><?php admin_render_table(['Category','Amount','TXID','Type','Status','Public','Notes'], array_map(static fn(array $row): array => [h((string)($row['category_name'] ?? '')), h(content_admin_hobc($row['amount'])), admin_hash_cell((string)$row['txid'], (string)$row['txid'] !== ''), h((string)$row['movement_type']), h((string)$row['status']), !empty($row['is_public'])?'Yes':'No', h(content_admin_short((string)$row['notes']))], $movements), 'No movements', 'Add reserve movements above.'); ?><?php admin_pagination($movementPager['page'], $movementPager['total_pages'], admin_url('/content.php'), ['tab' => 'reserve'], $movementPager['total_rows']); ?></div>
<?php elseif ($tab === 'support'): ?>
  <?php $q = trim((string)($_GET['q'] ?? '')); $where = $q !== '' ? "WHERE name LIKE " . $pdo->quote('%'.$q.'%') . " OR email LIKE " . $pdo->quote('%'.$q.'%') . " OR subject LIKE " . $pdo->quote('%'.$q.'%') . " OR message LIKE " . $pdo->quote('%'.$q.'%') : ''; $supportCount = (int)$pdo->query("SELECT COUNT(*) FROM support_messages {$where}")->fetchColumn(); $supportPager = admin_pagination_meta(admin_page_state(50)['page'], 50, $supportCount); $messages = $pdo->query("SELECT * FROM support_messages {$where} ORDER BY is_read ASC, FIELD(status,'new','open','waiting','waiting_admin','waiting_user','closed','spam','archived'), id DESC LIMIT {$supportPager['per_page']} OFFSET {$supportPager['offset']}")->fetchAll(); ?>
  <div class="admin-card"><h3>Support Inbox</h3><form method="get" class="admin-filter-form"><input type="hidden" name="tab" value="support"><label>Search<input name="q" value="<?= h($q) ?>" placeholder="Name, email, subject, message"></label><button type="submit">Search</button></form><?php admin_filter_box('Filter support table'); ?><?php admin_render_table(['When','From','Subject','Status','Read','Spam','Message','Admin Controls'], array_map(static fn(array $row): array => [admin_h_datetime($row['created_at'] ?? null), h((string)$row['name']) . '<br><small>' . h((string)$row['email']) . '</small>', h((string)$row['subject']), h((string)$row['status']), !empty($row['is_read'])?'Read':'Unread', !empty($row['is_spam'])?'Spam':'No', nl2br(h(content_admin_short((string)$row['message'], 180))), '<form method="post"><input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="support_message_status"><input type="hidden" name="id" value="' . h((string)$row['id']) . '"><select name="status">' . content_admin_status_options(['new'=>'New','open'=>'Open','waiting'=>'Waiting','waiting_user'=>'Waiting User','waiting_admin'=>'Waiting Admin','closed'=>'Closed','spam'=>'Spam','archived'=>'Archived'], (string)$row['status']) . '</select><br><label><input type="checkbox" name="is_read" ' . (!empty($row['is_read'])?'checked':'') . '> Read</label><br><label><input type="checkbox" name="is_spam" ' . (!empty($row['is_spam'])?'checked':'') . '> Spam</label><br><textarea name="admin_notes" rows="2" placeholder="Admin notes">' . h((string)$row['admin_notes']) . '</textarea><br><button type="submit" data-confirm="Update this support message?">Save</button></form>'], $messages), 'No support messages', 'Support messages will appear here when submitted.'); ?><?php admin_pagination($supportPager['page'], $supportPager['total_pages'], admin_url('/content.php'), ['tab' => 'support', 'q' => $q], $supportPager['total_rows']); ?></div>
  <div class="admin-card"><h3>Support Tickets Link</h3><p>The existing ticket reply workflow remains available for threaded tickets.</p><div class="admin-actions"><a class="admin-action admin-action-secondary" href="<?= h(admin_url('/tickets.php')) ?>">Open Support Tickets</a></div></div>
<?php endif; ?>

<?php render_admin_footer(); ?>
