<?php
/** Public navigation: desktop bar + animated off-canvas drawer on mobile/tablet. */
$navItems = [
    'home'       => ['Home', 'index.php', 'fa-house'],
    'about'      => ['About', 'about.php', 'fa-circle-info'],
    'ministries' => ['Ministries', 'ministries.php', 'fa-people-group'],
    'sermons'    => ['Sermons', 'sermons.php', 'fa-book-bible'],
    'events'     => ['Events', 'events.php', 'fa-calendar-days'],
    'pastor'     => ['Our Pastor', 'pastor.php', 'fa-user-tie'],
    'give'       => ['Give', 'giving.php', 'fa-hand-holding-heart'],
    'gallery'    => ['Gallery', 'gallery.php', 'fa-images'],
    'prayer'     => ['Prayer', 'prayer.php', 'fa-hands-praying'],
    'contact'    => ['Contact', 'contact.php', 'fa-envelope'],
];
$shortName = setting('church_short_name', "King's City");
$restName = trim(str_ireplace($shortName, '', setting('church_name')));
?>
<header class="site-header<?= $page['hero'] ? ' is-transparent' : '' ?>" id="siteHeader">
  <div class="topbar-public d-none d-lg-block">
    <div class="container d-flex justify-content-between align-items-center">
      <div class="topbar-info">
        <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= e(church_address_line()) ?></span>
        <a href="tel:<?= e(preg_replace('/[^\d+]/', '', setting('phone'))) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i> <?= e(setting('phone')) ?></a>
      </div>
      <?= social_icons('social-icons social-sm') ?>
    </div>
  </div>
  <nav class="main-nav" aria-label="Main navigation">
    <div class="container d-flex align-items-center justify-content-between">
      <a class="brand" href="<?= e(url()) ?>" aria-label="<?= e(setting('church_name')) ?> — Home">
        <?= logo_img(56) ?>
        <span class="brand-text"><strong><?= e($shortName) ?></strong><small><?= e($restName) ?></small></span>
      </a>
      <ul class="nav-links d-none d-xl-flex">
        <?php foreach ($navItems as $key => [$label, $href]): if ($key === 'prayer') continue; ?>
          <li><a href="<?= e(url($href)) ?>" class="<?= $page['nav'] === $key ? 'active' : '' ?>"<?= $page['nav'] === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="d-flex align-items-center gap-2">
        <a class="btn btn-live d-none d-sm-inline-flex" href="<?= e($liveUrl) ?>"<?= setting('live_stream_url') ? ' target="_blank" rel="noopener"' : '' ?>>
          <span class="live-dot<?= $isLive ? ' on' : '' ?>" aria-hidden="true"></span> <?= $isLive ? 'Live Now' : 'Watch Live' ?>
        </a>
        <?php $staff = current_user(); ?>
        <a class="btn btn-login" href="<?= e(url($staff ? 'admin/dashboard.php' : 'admin/login.php')) ?>" rel="nofollow">
          <i class="fa-solid <?= $staff ? 'fa-gauge-high' : 'fa-user-lock' ?>" aria-hidden="true"></i><span class="d-none d-md-inline"><?= $staff ? 'Dashboard' : 'Login' ?></span><span class="visually-hidden d-md-none"><?= $staff ? 'Dashboard' : 'Staff login' ?></span>
        </a>
        <button class="menu-toggle d-xl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="Open menu">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </nav>
</header>

<div class="offcanvas offcanvas-end mobile-drawer" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
  <div class="offcanvas-header">
    <a class="brand" href="<?= e(url()) ?>"><?= logo_img(46) ?><span class="brand-text"><strong id="mobileNavLabel"><?= e($shortName) ?></strong><small><?= e($restName) ?></small></span></a>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close menu"></button>
  </div>
  <div class="offcanvas-body">
    <ul class="drawer-links">
      <?php $i = 0; foreach ($navItems as $key => [$label, $href, $icon]): ?>
        <li style="--i:<?= $i++ ?>"><a href="<?= e(url($href)) ?>" class="<?= $page['nav'] === $key ? 'active' : '' ?>"<?= $page['nav'] === $key ? ' aria-current="page"' : '' ?>>
          <i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i><span><?= e($key === 'prayer' ? 'Prayer Request' : $label) ?></span><i class="fa-solid fa-chevron-right chev" aria-hidden="true"></i></a></li>
      <?php endforeach; ?>
    </ul>
    <a class="btn btn-live w-100 justify-content-center mt-3" href="<?= e($liveUrl) ?>"<?= setting('live_stream_url') ? ' target="_blank" rel="noopener"' : '' ?>><span class="live-dot<?= $isLive ? ' on' : '' ?>" aria-hidden="true"></span> <?= $isLive ? 'Live Now' : 'Watch Live' ?></a>
    <a class="btn btn-login w-100 justify-content-center mt-2" href="<?= e(url($staff ? 'admin/dashboard.php' : 'admin/login.php')) ?>" rel="nofollow"><i class="fa-solid <?= $staff ? 'fa-gauge-high' : 'fa-user-lock' ?>" aria-hidden="true"></i> <?= $staff ? 'Go to Dashboard' : 'Staff Login' ?></a>
    <div class="drawer-contact">
      <p><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= e(church_address_line()) ?></p>
      <p><i class="fa-solid fa-phone" aria-hidden="true"></i> <a href="tel:<?= e(preg_replace('/[^\d+]/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></p>
      <?= social_icons() ?>
    </div>
  </div>
</div>
