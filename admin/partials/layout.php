<?php
/**
 * Admin layout: dark navy sidebar (permission-filtered), topbar, toast
 * container and global confirmation dialog.
 */

function admin_nav(): array
{
    return [
        '' => [
            ['dashboard', 'Dashboard', 'fa-gauge-high', 'admin/dashboard.php', 'dashboard.view'],
        ],
        'Content' => [
            ['pages', 'Pages', 'fa-file-lines', 'admin/pages.php', 'pages.view'],
            ['sermons', 'Sermons', 'fa-book-bible', 'admin/sermons.php', 'sermons.view'],
            ['events', 'Events', 'fa-calendar-days', 'admin/events.php', 'events.view'],
            ['announcements', 'Announcements', 'fa-bullhorn', 'admin/announcements.php', 'announcements.view'],
            ['gallery', 'Gallery', 'fa-images', 'admin/gallery.php', 'gallery.view'],
            ['media', 'Media', 'fa-photo-film', 'admin/media.php', 'media.view'],
            ['categories', 'Categories', 'fa-tags', 'admin/categories.php', ['sermons.edit', 'events.edit', 'gallery.edit']],
        ],
        'Ministry' => [
            ['departments', 'Departments', 'fa-sitemap', 'admin/departments.php', 'departments.view'],
            ['prayer_requests', 'Prayer Requests', 'fa-hands-praying', 'admin/prayer_requests.php', 'prayer_requests.view'],
            ['testimonies', 'Testimonies', 'fa-heart', 'admin/testimonies.php', 'testimonies.view'],
            ['pastor', 'Pastor', 'fa-user-tie', 'admin/pastor.php', 'pastor.view'],
            ['leaders', 'Leadership', 'fa-people-roof', 'admin/leaders.php', 'pages.view'],
            ['messages', 'Messages', 'fa-envelope', 'admin/messages.php', 'messages.view'],
        ],
        'Management' => [
            ['users', 'Users', 'fa-users', 'admin/users.php', 'users.view'],
            ['roles', 'Roles', 'fa-user-shield', 'admin/roles.php', 'roles.view'],
            ['permissions', 'Permissions', 'fa-key', 'admin/permissions.php', 'permissions.view'],
            ['giving', 'Giving', 'fa-hand-holding-dollar', 'admin/giving.php', 'giving.view'],
        ],
        'Website' => [
            ['homepage', 'Homepage', 'fa-house', 'admin/settings.php?tab=homepage', 'website_settings.view'],
            ['hero_video', 'Hero Video', 'fa-film', 'admin/hero-video.php', 'hero_video.view'],
            ['church', 'Church Information', 'fa-church', 'admin/settings.php?tab=church', 'website_settings.view'],
            ['social', 'Social Media', 'fa-share-nodes', 'admin/social-links.php', 'website_settings.view'],
            ['services', 'Service Times', 'fa-clock', 'admin/service-times.php', 'website_settings.view'],
            ['settings', 'Settings', 'fa-gear', 'admin/settings.php', 'website_settings.view'],
        ],
        'System' => [
            ['activity_logs', 'Activity Logs', 'fa-clock-rotate-left', 'admin/activity-logs.php', 'activity_logs.view'],
            ['profile', 'Profile', 'fa-id-badge', 'admin/profile.php', null],
        ],
    ];
}

function admin_header(string $title, string $active = '', array $opts = []): void
{
    $user = current_user();
    $newPrayers = can('prayer_requests.view') ? (int) DB::value("SELECT COUNT(*) FROM prayer_requests WHERE status = 'new'") : 0;
    $pending = can('testimonies.view') ? (int) DB::value("SELECT COUNT(*) FROM testimonies WHERE status = 'pending'") : 0;
    $unread = can('messages.view') ? (int) DB::value('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0') : 0;
    $badges = ['prayer_requests' => $newPrayers, 'testimonies' => $pending, 'messages' => $unread];
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title) ?> · Admin · <?= e(setting('church_name', "King's City")) ?></title>
<link rel="icon" href="<?= e(media_url(setting('favicon'), 'assets/images/favicon.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin-body">
<a class="skip-link" href="#main">Skip to content</a>
<aside class="sidebar" id="sidebar" aria-label="Admin navigation">
  <a class="sidebar-brand" href="<?= e(url('admin/dashboard.php')) ?>">
    <img src="<?= e(media_url(setting('logo'), 'assets/images/logo.svg')) ?>" alt="" width="40" height="40">
    <span><strong><?= e(setting('church_short_name', "King's City")) ?></strong><small>Admin Panel</small></span>
  </a>
  <nav class="sidebar-nav">
    <?php foreach (admin_nav() as $section => $items):
        $visible = array_filter($items, fn($i) => $i[4] === null || (is_array($i[4]) ? can_any($i[4]) : can($i[4])));
        if (!$visible) continue; ?>
      <?php if ($section): ?><div class="nav-section"><?= e($section) ?></div><?php endif; ?>
      <?php foreach ($visible as [$key, $label, $icon, $href]): ?>
        <a class="nav-link<?= $key === $active ? ' active' : '' ?>" href="<?= e(url($href)) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
          <i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i><span><?= e($label) ?></span>
          <?php if (!empty($badges[$key])): ?><span class="nav-badge"><?= (int) $badges[$key] ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('auth/logout.php')) ?>" class="mt-2"><?= csrf_field() ?>
      <button class="nav-link w-100 border-0 bg-transparent text-start"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Logout</span></button>
    </form>
  </nav>
</aside>
<div class="sidebar-backdrop" data-sidebar-close></div>

<div class="admin-main">
  <header class="topbar">
    <button class="btn-icon d-lg-none" type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
    <div class="topbar-title"><?= e($title) ?></div>
    <div class="topbar-actions">
      <a class="btn btn-sm btn-outline-navy d-none d-sm-inline-flex" href="<?= e(url()) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-globe"></i> View Website</a>
      <?php if ($newPrayers): ?>
        <a class="btn-icon position-relative" href="<?= e(url('admin/prayer_requests.php?status=new')) ?>" aria-label="<?= $newPrayers ?> new prayer requests"><i class="fa-solid fa-bell"></i><span class="dot"></span></a>
      <?php endif; ?>
      <div class="dropdown">
        <button class="user-chip" data-bs-toggle="dropdown" aria-expanded="false">
          <?php if ($user['avatar']): ?><img src="<?= e(media_url($user['avatar'])) ?>" alt="" width="34" height="34"><?php else: ?><span class="avatar"><?= e(initials($user['full_name'])) ?></span><?php endif; ?>
          <span class="d-none d-md-block text-start"><strong><?= e($user['full_name']) ?></strong><small><?= e($user['role_name']) ?></small></span>
          <i class="fa-solid fa-chevron-down small"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
          <li><a class="dropdown-item" href="<?= e(url('admin/profile.php')) ?>"><i class="fa-solid fa-id-badge"></i> My Profile</a></li>
          <li><a class="dropdown-item" href="<?= e(url('auth/change-password.php')) ?>"><i class="fa-solid fa-lock"></i> Change Password</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><form method="post" action="<?= e(url('auth/logout.php')) ?>"><?= csrf_field() ?><button class="dropdown-item text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</button></form></li>
        </ul>
      </div>
    </div>
  </header>
  <main id="main" class="admin-content" tabindex="-1">
    <?= flash_toasts() ?>
<?php
}

function admin_footer(array $scripts = []): void
{
    ?>
  </main>
  <footer class="admin-footer">&copy; <?= date('Y') ?> <?= e(setting('church_name')) ?> · Church Management System v<?= e(APP_VERSION) ?></footer>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastStack" aria-live="polite" aria-atomic="true"></div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content confirm-box">
    <div class="modal-body text-center p-4">
      <div class="confirm-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
      <h2 class="h5 mt-3" id="confirmTitle">Are you sure?</h2>
      <p class="text-muted small mb-0" data-confirm-text>Are you sure you want to delete this item?</p>
    </div>
    <div class="modal-footer justify-content-center border-0 pt-0 pb-4">
      <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
      <button type="button" class="btn btn-danger" data-confirm-ok>Delete</button>
    </div>
  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<?php foreach ($scripts as $src): ?><script src="<?= e($src) ?>" defer></script><?php endforeach; ?>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
<?php
}
