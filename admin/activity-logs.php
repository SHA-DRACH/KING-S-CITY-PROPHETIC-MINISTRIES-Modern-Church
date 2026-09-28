<?php
/** Audit trail of administrative actions (Super Administrator / authorised users). */
require __DIR__ . '/partials/init.php';
require_permission('activity_logs.view');

$where = ['1'];
$params = [];
if ($q = trim((string) ($_GET['q'] ?? ''))) {
    $where[] = '(l.description LIKE ? OR l.ip_address LIKE ?)';
    array_push($params, "%$q%", "%$q%");
}
if ($module = (string) ($_GET['module'] ?? '')) {
    $where[] = 'l.module = ?';
    $params[] = $module;
}
if ($uid = query_int('user')) {
    $where[] = 'l.user_id = ?';
    $params[] = $uid;
}
if (($from = (string) ($_GET['from'] ?? '')) && DateTime::createFromFormat('Y-m-d', $from)) {
    $where[] = 'l.created_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if (($to = (string) ($_GET['to'] ?? '')) && DateTime::createFromFormat('Y-m-d', $to)) {
    $where[] = 'l.created_at <= ?';
    $params[] = $to . ' 23:59:59';
}
$w = implode(' AND ', $where);

// CSV export of the current filter
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity-log-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'User', 'Action', 'Module', 'Description', 'IP Address']);
    foreach (DB::all("SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id WHERE $w ORDER BY l.id DESC LIMIT 10000", $params) as $r) {
        // Neutralise spreadsheet formula injection
        fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v, [$r['created_at'], $r['user_name'] ?: 'System', $r['action'], $r['module'], $r['description'], $r['ip_address']]));
    }
    log_activity('export', 'activity_logs', 'Exported activity log CSV');
    exit;
}

$p = paginate((int) DB::value("SELECT COUNT(*) FROM activity_logs l WHERE $w", $params), 25);
$logs = DB::all("SELECT l.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name, r.name AS role_name FROM activity_logs l
    LEFT JOIN users u ON u.id = l.user_id LEFT JOIN roles r ON r.id = u.role_id WHERE $w ORDER BY l.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}", $params);
$modules = array_column(DB::all('SELECT DISTINCT module FROM activity_logs ORDER BY module'), 'module');
$users = User::staffOptions();
$danger = ['delete', 'login_failed', 'access_denied', 'revoke', 'disable', 'password_reset', 'role_change'];

admin_header('Activity Logs', 'activity_logs');
?>
<div class="page-head"><div><h1 class="page-title"><i class="fa-solid fa-clock-rotate-left"></i> Activity Logs</h1><p class="page-sub">Who did what, when and from where.</p></div>
<a class="btn btn-light" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"><i class="fa-solid fa-file-csv"></i> Export CSV</a></div>
<form class="toolbar card-lite" method="get">
  <div class="input-icon"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" class="form-control" placeholder="Search description or IP…" value="<?= e($q) ?>" aria-label="Search"></div>
  <select name="module" class="form-select" aria-label="Module"><option value="">All Modules</option><?php foreach ($modules as $m): ?><option value="<?= e($m) ?>" <?= $module === $m ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $m))) ?></option><?php endforeach; ?></select>
  <select name="user" class="form-select" aria-label="User"><option value="">All Users</option><?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= $uid === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
  <input type="date" name="from" class="form-control" value="<?= e($from) ?>" aria-label="From date">
  <input type="date" name="to" class="form-control" value="<?= e($to) ?>" aria-label="To date">
  <button class="btn btn-navy">Filter</button>
</form>
<?php if (!$logs): echo empty_state('fa-clock-rotate-left', 'No log entries match');
else: ?>
<div class="table-card"><div class="table-responsive"><table class="table admin-table align-middle">
  <thead><tr><th>Date</th><th>User</th><th>Action</th><th>Module</th><th>Description</th><th>IP Address</th></tr></thead><tbody>
  <?php foreach ($logs as $l): ?>
    <tr><td data-label="Date" class="small text-nowrap"><?= e(format_date($l['created_at'], 'M j, Y')) ?><div class="cell-sub"><?= e(format_date($l['created_at'], 'g:i:s A')) ?></div></td>
      <td data-label="User"><strong class="cell-title"><?= e($l['user_name'] ?: 'System / Visitor') ?></strong><div class="cell-sub"><?= e($l['role_name'] ?? '') ?></div></td>
      <td data-label="Action"><span class="badge <?= in_array($l['action'], $danger, true) ? 'text-bg-danger' : 'text-bg-light' ?>"><?= e(str_replace('_', ' ', $l['action'])) ?></span></td>
      <td data-label="Module" class="small"><?= e(ucwords(str_replace('_', ' ', $l['module']))) ?></td>
      <td data-label="Description" class="small"><?= e($l['description']) ?></td>
      <td data-label="IP" class="small font-monospace"><?= e($l['ip_address']) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <div class="table-foot"><span class="small text-muted"><?= number_format($p['total']) ?> entries</span><?= pagination_links($p) ?></div></div>
<?php endif;
admin_footer();
