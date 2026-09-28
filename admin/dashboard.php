<?php
/**
 * Role-aware dashboard. Every widget is gated by permission, so the same page
 * becomes the Admin, Pastor, or Department-Head dashboard automatically.
 */
require __DIR__ . '/partials/init.php';
require_permission('dashboard.view');

$user = current_user();
$isPastor = $user['role_slug'] === 'senior_pastor';
$myDepts = user_department_ids();
$scoped = !has_all_department_access();
$deptIn = $myDepts ? implode(',', array_map('intval', $myDepts)) : '0';
$eventScope = $scoped ? " AND department_id IN ($deptIn)" : '';

// ---- Stat cards (each gated) ------------------------------------------
$cards = [];
if (can('sermons.view')) {
    $cards[] = ['Total Sermons', DB::value("SELECT COUNT(*) FROM sermons WHERE status = 'published'"), 'fa-book-bible', 'blue', 'admin/sermons.php', DB::value("SELECT COUNT(*) FROM sermons WHERE status = 'draft'") . ' drafts'];
}
if (can('events.view')) {
    $cards[] = ['Upcoming Events', DB::value("SELECT COUNT(*) FROM events WHERE status = 'published' AND event_date >= CURDATE()$eventScope"), 'fa-calendar-days', 'green', 'admin/events.php', $scoped ? 'Your department' : 'Next 90 days: ' . DB::value("SELECT COUNT(*) FROM events WHERE status='published' AND event_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 90 DAY")];
}
if (can('prayer_requests.view')) {
    $cards[] = ['Prayer Requests', DB::value("SELECT COUNT(*) FROM prayer_requests WHERE status NOT IN ('answered','closed')"), 'fa-hands-praying', 'purple', 'admin/prayer_requests.php', DB::value("SELECT COUNT(*) FROM prayer_requests WHERE status = 'new'") . ' new'];
}
if (can('departments.view')) {
    $cards[] = ['Departments', $scoped ? count($myDepts) : DB::value("SELECT COUNT(*) FROM departments WHERE status = 'active'"), 'fa-sitemap', 'navy', 'admin/departments.php', $scoped ? 'Assigned to you' : 'Active ministries'];
}
if (can('users.view')) {
    $cards[] = ['Users', DB::value('SELECT COUNT(*) FROM users'), 'fa-users', 'teal', 'admin/users.php', DB::value("SELECT COUNT(*) FROM users WHERE status = 'active'") . ' active'];
}
if (can('giving.view')) {
    $cards[] = ['Giving (Month)', money((float) DB::value("SELECT COALESCE(SUM(amount),0) FROM giving_transactions WHERE status='confirmed' AND currency='USD' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")), 'fa-hand-holding-dollar', 'gold', 'admin/giving.php', DB::value("SELECT COUNT(*) FROM giving_transactions WHERE status='pending'") . ' awaiting confirmation'];
}
if (can('gallery.view')) {
    $cards[] = ['Gallery Items', DB::value('SELECT COUNT(*) FROM gallery WHERE is_published = 1'), 'fa-images', 'pink', 'admin/gallery.php', 'Published'];
}
if (can('testimonies.view')) {
    $cards[] = ['Testimonies', DB::value("SELECT COUNT(*) FROM testimonies WHERE status = 'published'"), 'fa-heart', 'red', 'admin/testimonies.php', DB::value("SELECT COUNT(*) FROM testimonies WHERE status = 'pending'") . ' pending review'];
}

// ---- Quick actions ----------------------------------------------------
$quick = array_filter([
    can('sermons.create') ? ['Upload Sermon', 'fa-cloud-arrow-up', 'admin/sermons.php?new=1', 'blue'] : null,
    can('events.create') ? ['Create Event', 'fa-calendar-plus', 'admin/events.php?new=1', 'green'] : null,
    can('announcements.create') ? ['New Announcement', 'fa-bullhorn', 'admin/announcements.php?new=1', 'red'] : null,
    can('gallery.upload') ? ['Upload Photos', 'fa-images', 'admin/gallery.php?new=1', 'pink'] : null,
    can('pastor.edit') ? ['Pastor\'s Message', 'fa-feather-pointed', 'admin/pastor.php', 'gold'] : null,
    can('users.create') ? ['Add User', 'fa-user-plus', 'admin/users.php?action=new', 'navy'] : null,
    can('hero_video.upload') ? ['Hero Video', 'fa-film', 'admin/hero-video.php', 'purple'] : null,
]);

// ---- Lists ------------------------------------------------------------
$upcoming = can('events.view') ? DB::all("SELECT id, title, event_date, start_time, location FROM events WHERE status = 'published' AND event_date >= CURDATE()$eventScope ORDER BY event_date LIMIT 5") : [];
$activity = can('activity_logs.view')
    ? DB::all('SELECT l.*, CONCAT(u.first_name, " ", u.last_name) AS user_name FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id WHERE l.action NOT IN ("login_failed","access_denied") ORDER BY l.id DESC LIMIT 7')
    : DB::all('SELECT l.*, "You" AS user_name FROM activity_logs l WHERE l.user_id = ? ORDER BY l.id DESC LIMIT 7', [$user['id']]);
$assignedPrayers = DB::all("SELECT id, name, request, status, created_at FROM prayer_requests WHERE assigned_to = ? AND status NOT IN ('answered','closed') ORDER BY created_at DESC LIMIT 5", [$user['id']]);
$pendingTestimonies = can('testimonies.approve') ? DB::all("SELECT id, name, title, testimony FROM testimonies WHERE status = 'pending' ORDER BY created_at DESC LIMIT 4") : [];
$myDepartments = $myDepts ? DB::all("SELECT d.*, (SELECT COUNT(*) FROM department_users du WHERE du.department_id = d.id) members,
    (SELECT COUNT(*) FROM events e WHERE e.department_id = d.id AND e.event_date >= CURDATE() AND e.status='published') upcoming
    FROM departments d WHERE d.id IN ($deptIn)") : [];

// ---- Chart data ---------------------------------------------------------
$charts = [];
if (can('giving.view')) {
    $charts['giving'] = Giving::monthlyTotals(6);
}
if (can('prayer_requests.view')) {
    $charts['prayer'] = ['labels' => array_values(PRAYER_STATUSES), 'data' => array_values(PrayerRequest::statusCounts())];
}
if (can('statistics.view') || can('sermons.view')) {
    $rows = DB::all("SELECT DATE_FORMAT(d, '%Y-%m') ym, SUM(s) sermons, SUM(e) events FROM (
        SELECT sermon_date d, 1 s, 0 e FROM sermons WHERE sermon_date >= CURDATE() - INTERVAL 6 MONTH
        UNION ALL SELECT event_date, 0, 1 FROM events WHERE event_date >= CURDATE() - INTERVAL 6 MONTH AND event_date <= CURDATE()) x GROUP BY ym");
    $map = array_column($rows, null, 'ym');
    $labels = $s = $ev = [];
    for ($i = 5; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("first day of -$i month"));
        $labels[] = date('M', strtotime("$ym-01"));
        $s[] = (int) ($map[$ym]['sermons'] ?? 0);
        $ev[] = (int) ($map[$ym]['events'] ?? 0);
    }
    $charts['content'] = ['labels' => $labels, 'sermons' => $s, 'events' => $ev];
}

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$displayName = $isPastor ? trim(($user['title'] ? $user['title'] . ' ' : '') . $user['first_name']) : $user['first_name'];
$pastor = $isPastor ? Pastor::primary() : null;

$actionIcons = ['create' => 'fa-plus', 'update' => 'fa-pen', 'delete' => 'fa-trash', 'login' => 'fa-right-to-bracket', 'logout' => 'fa-right-from-bracket', 'publish' => 'fa-eye', 'unpublish' => 'fa-eye-slash', 'upload' => 'fa-upload', 'activate' => 'fa-toggle-on', 'grant' => 'fa-key', 'revoke' => 'fa-key', 'password_reset' => 'fa-lock', 'role_change' => 'fa-user-shield'];

admin_header('Dashboard', 'dashboard');
?>
<section class="welcome-banner<?= $isPastor ? ' pastor' : '' ?>">
  <div>
    <span class="eyebrow"><?= e($user['role_name']) ?><?= $scoped && $myDepartments ? ' · ' . e(implode(', ', array_column($myDepartments, 'name'))) : '' ?></span>
    <h1><?= e($greeting) ?>, <?= e($displayName) ?></h1>
    <?php if ($pastor && $pastor['scripture']): ?>
      <p class="scripture">“<?= e(excerpt($pastor['scripture'], 140)) ?>” <cite>— <?= e($pastor['scripture_ref']) ?></cite></p>
    <?php else: ?>
      <p>Here is what is happening at <?= e(setting('church_name')) ?> today, <?= date('l, F j') ?>.</p>
    <?php endif; ?>
  </div>
  <?= logo_img(120, 'banner-logo') ?>
</section>

<?php if ($cards): ?>
<div class="row g-3 mb-4">
  <?php foreach ($cards as [$label, $value, $icon, $tone, $href, $sub]): ?>
    <div class="col-6 col-lg-3">
      <a class="stat-card tone-<?= e($tone) ?>" href="<?= e(url($href)) ?>">
        <div class="stat-icon"><i class="fa-solid <?= e($icon) ?>"></i></div>
        <div><div class="stat-label"><?= e($label) ?></div><div class="stat-value"><?= e((string) $value) ?></div><div class="stat-sub"><?= e($sub) ?></div></div>
      </a>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-xl-8">
    <?php if ($quick): ?>
    <div class="panel mb-4">
      <h2 class="panel-title">Quick Actions</h2>
      <div class="quick-grid">
        <?php foreach ($quick as [$label, $icon, $href, $tone]): ?>
          <a class="quick-action tone-<?= e($tone) ?>" href="<?= e(url($href)) ?>"><i class="fa-solid <?= e($icon) ?>"></i><span><?= e($label) ?></span></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($charts): ?>
    <div class="row g-4 mb-4">
      <?php if (isset($charts['giving'])): ?><div class="col-md-<?= isset($charts['prayer']) ? 7 : 12 ?>"><div class="panel h-100"><h2 class="panel-title">Giving — Last 6 Months (USD)</h2><div class="chart-box"><canvas id="givingChart" aria-label="Giving by month chart" role="img"></canvas></div></div></div><?php endif; ?>
      <?php if (isset($charts['prayer'])): ?><div class="col-md-<?= isset($charts['giving']) ? 5 : 6 ?>"><div class="panel h-100"><h2 class="panel-title">Prayer Requests</h2><div class="chart-box"><canvas id="prayerChart" aria-label="Prayer requests by status chart" role="img"></canvas></div></div></div><?php endif; ?>
      <?php if (isset($charts['content'])): ?><div class="col-md-<?= isset($charts['giving']) ? 12 : 6 ?>"><div class="panel h-100"><h2 class="panel-title">Ministry Activity — Sermons &amp; Events</h2><div class="chart-box"><canvas id="contentChart" aria-label="Sermons and events per month chart" role="img"></canvas></div></div></div><?php endif; ?>
    </div>
    <script type="application/json" id="chartData"><?= json_encode($charts) ?></script>
    <?php endif; ?>

    <?php if ($myDepartments && $scoped): ?>
    <div class="panel mb-4">
      <h2 class="panel-title">My Department<?= count($myDepartments) > 1 ? 's' : '' ?></h2>
      <div class="row g-3">
        <?php foreach ($myDepartments as $d): ?>
          <div class="col-md-6"><div class="dept-tile"><span class="icon-chip"><i class="fa-solid <?= e($d['icon'] ?: 'fa-church') ?>"></i></span>
            <div><strong><?= e($d['name']) ?></strong><div class="small text-muted"><?= e($d['meeting_schedule']) ?></div>
            <div class="small mt-1"><i class="fa-solid fa-users"></i> <?= (int) $d['members'] ?> members · <i class="fa-solid fa-calendar"></i> <?= (int) $d['upcoming'] ?> upcoming events</div></div></div></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($upcoming): ?>
    <div class="panel mb-4">
      <div class="panel-head"><h2 class="panel-title">Upcoming Events</h2><a class="small" href="<?= e(url('admin/events.php')) ?>">View all →</a></div>
      <ul class="list-clean">
        <?php foreach ($upcoming as $ev): ?>
          <li class="list-row"><?= date_chip($ev['event_date']) ?><div class="flex-grow-1"><strong><?= e($ev['title']) ?></strong><div class="small text-muted"><?= e(format_time($ev['start_time'])) ?> · <?= e($ev['location']) ?></div></div></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-xl-4">
    <?php if ($assignedPrayers): ?>
    <div class="panel mb-4 accent-gold">
      <h2 class="panel-title"><i class="fa-solid fa-hands-praying"></i> Assigned to You</h2>
      <ul class="list-clean">
        <?php foreach ($assignedPrayers as $pr): ?>
          <li class="list-row small"><div><strong><?= e($pr['name']) ?></strong> <?= status_badge($pr['status']) ?><div class="text-muted"><?= e(excerpt($pr['request'], 90)) ?></div></div></li>
        <?php endforeach; ?>
      </ul>
      <?php if (can('prayer_requests.view')): ?><a class="small" href="<?= e(url('admin/prayer_requests.php')) ?>">Open prayer requests →</a><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($pendingTestimonies): ?>
    <div class="panel mb-4">
      <div class="panel-head"><h2 class="panel-title">Testimonies Awaiting Approval</h2><a class="small" href="<?= e(url('admin/testimonies.php?status=pending')) ?>">Review →</a></div>
      <ul class="list-clean">
        <?php foreach ($pendingTestimonies as $t): ?>
          <li class="list-row small"><div><strong><?= e($t['title'] ?: $t['name']) ?></strong><div class="text-muted"><?= e(excerpt($t['testimony'], 90)) ?></div></div></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-head"><h2 class="panel-title"><?= can('activity_logs.view') ? 'Recent Activity' : 'Your Recent Activity' ?></h2>
      <?php if (can('activity_logs.view')): ?><a class="small" href="<?= e(url('admin/activity-logs.php')) ?>">View all →</a><?php endif; ?></div>
      <?php if (!$activity): echo empty_state('fa-clock-rotate-left', 'No activity yet');
      else: ?>
      <ul class="timeline">
        <?php foreach ($activity as $a): ?>
          <li><span class="tl-icon"><i class="fa-solid <?= e($actionIcons[$a['action']] ?? 'fa-circle-dot') ?>"></i></span>
            <div><div class="small"><strong><?= e($a['user_name'] ?: 'System') ?></strong> — <?= e($a['description']) ?></div><div class="tiny text-muted"><?= e(time_ago($a['created_at'])) ?> · <?= e(ucfirst(str_replace('_', ' ', $a['module']))) ?></div></div></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
admin_footer($charts ? ['https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js'] : []);
