<?php
/**
 * Central church / website settings. Nothing on the public site is hard-coded:
 * name, contact details, homepage copy, giving copy, SEO and system options
 * are all stored in church_settings.
 */
require __DIR__ . '/partials/init.php';
require_permission('website_settings.view');

$tabs = [
    'church'   => ['Church Information', 'fa-church', 'website_settings.edit', [
        'church_name'       => ['label' => 'Church Name', 'required' => true, 'max' => 150],
        'church_short_name' => ['label' => 'Short Name', 'max' => 60, 'col' => 6],
        'church_tagline'    => ['label' => 'Tagline', 'max' => 120, 'col' => 6],
        'church_motto'      => ['label' => 'Motto', 'max' => 200, 'help' => 'Separate phrases with •'],
        'footer_text'       => ['label' => 'Footer About Text', 'type' => 'textarea', 'rows' => 3],
        'logo'              => ['label' => 'Logo', 'type' => 'file', 'upload' => 'image', 'col' => 6],
        'favicon'           => ['label' => 'Favicon (square PNG)', 'type' => 'file', 'upload' => 'image', 'col' => 6],
    ]],
    'contact'  => ['Contact & Location', 'fa-location-dot', 'website_settings.edit', [
        'phone'     => ['label' => 'Phone', 'type' => 'tel', 'max' => 40, 'col' => 6],
        'email'     => ['label' => 'Email', 'type' => 'email', 'max' => 190, 'col' => 6],
        'whatsapp'  => ['label' => 'WhatsApp Number (digits, with country code)', 'max' => 20, 'col' => 6, 'placeholder' => '231888951997'],
        'address'   => ['label' => 'Street Address', 'max' => 200, 'col' => 6],
        'city'      => ['label' => 'City', 'max' => 100, 'col' => 6],
        'country'   => ['label' => 'Country', 'max' => 100, 'col' => 6],
        'map_query' => ['label' => 'Google Maps Location', 'max' => 255, 'help' => 'An address or "latitude,longitude" used for the embedded map.'],
    ]],
    'homepage' => ['Homepage', 'fa-house', 'website_settings.edit', [
        'hero_eyebrow'       => ['label' => 'Hero Eyebrow Text', 'max' => 60, 'col' => 6],
        'hero_scripture_ref' => ['label' => 'Hero Scripture Reference', 'max' => 60, 'col' => 6],
        'hero_scripture'     => ['label' => 'Hero Scripture', 'max' => 200],
        'welcome_title'      => ['label' => 'Welcome Section Title', 'max' => 150],
        'welcome_text'       => ['label' => 'Welcome Section Text', 'type' => 'textarea', 'rows' => 5],
        'live_stream_url'    => ['label' => 'Live Stream URL (YouTube / Facebook)', 'type' => 'url', 'max' => 255, 'col' => 8],
        'is_live'            => ['label' => 'Live Now', 'type' => 'checkbox', 'check_label' => 'We are LIVE now (shows a live badge)', 'col' => 4],
        'welcome_image'      => ['label' => 'Welcome Section Image', 'type' => 'file', 'upload' => 'image', 'col' => 6],
        'giving_image'       => ['label' => 'Giving Banner Background', 'type' => 'file', 'upload' => 'image', 'col' => 6],
    ]],
    'giving'   => ['Giving Page', 'fa-hand-holding-heart', 'website_settings.edit', [
        'giving_intro'         => ['label' => 'Giving Introduction', 'type' => 'textarea', 'rows' => 2],
        'giving_scripture'     => ['label' => 'Giving Scripture', 'type' => 'textarea', 'rows' => 2],
        'giving_scripture_ref' => ['label' => 'Scripture Reference', 'max' => 60, 'col' => 6],
        'giving_currencies'    => ['label' => 'Accepted Currencies', 'max' => 40, 'col' => 6, 'help' => 'Comma separated ISO codes, e.g. USD,LRD'],
    ]],
    'seo'      => ['SEO', 'fa-magnifying-glass-chart', 'website_settings.edit', [
        'seo_description' => ['label' => 'Default Meta Description', 'type' => 'textarea', 'rows' => 3, 'max' => 300],
        'seo_keywords'    => ['label' => 'Keywords', 'max' => 300],
        'og_image'        => ['label' => 'Social Share Image (1200×630)', 'type' => 'file', 'upload' => 'image'],
    ]],
    'system'   => ['System & Security', 'fa-shield-halved', 'system_settings.edit', [
        'maintenance_mode'        => ['label' => 'Maintenance', 'type' => 'checkbox', 'check_label' => 'Maintenance mode (hide public website from visitors)'],
        'prayer_wall_enabled'     => ['label' => 'Prayer Wall', 'type' => 'checkbox', 'check_label' => 'Show public prayer wall (only requests visitors marked public)'],
        'session_timeout_minutes' => ['label' => 'Session Timeout (minutes)', 'type' => 'number', 'col' => 4],
        'max_login_attempts'      => ['label' => 'Max Failed Logins (per 15 min)', 'type' => 'number', 'col' => 4],
        'max_video_upload_mb'     => ['label' => 'Max Video Upload (MB)', 'type' => 'number', 'col' => 4, 'help' => 'Also raise upload_max_filesize & post_max_size in php.ini.'],
    ]],
];
$tab = $_GET['tab'] ?? $_POST['tab'] ?? 'church';
if (!isset($tabs[$tab])) {
    $tab = 'church';
}
[$tabLabel, , $editPerm, $fields] = $tabs[$tab];
$canEdit = can($editPerm);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_permission($editPerm);
    $values = [];
    $errors = [];
    foreach ($fields as $key => $f) {
        $type = $f['type'] ?? 'text';
        if ($type === 'file') {
            try {
                if ($path = Upload::fromField($key, 'image', 'images', 'settings')) {
                    $old = setting($key);
                    $values[$key] = $path;
                    if (str_starts_with($old, 'uploads/')) {
                        Upload::delete($old);
                    }
                }
            } catch (UploadException $e) {
                $errors[$key] = $e->getMessage();
            }
            continue;
        }
        if ($type === 'checkbox') {
            $values[$key] = !empty($_POST[$key]) ? '1' : '0';
            continue;
        }
        $v = post($key);
        if (!empty($f['required']) && $v === '') {
            $errors[$key] = $f['label'] . ' is required.';
        } elseif ($v !== '' && $type === 'email' && !valid_email($v)) {
            $errors[$key] = 'Enter a valid email.';
        } elseif ($v !== '' && $type === 'url' && !valid_url($v)) {
            $errors[$key] = 'Enter a valid URL.';
        } elseif ($type === 'number' && ($v === '' || !ctype_digit($v) || (int) $v < 1)) {
            $errors[$key] = 'Enter a positive whole number.';
        } elseif (isset($f['max']) && mb_strlen($v) > $f['max']) {
            $errors[$key] = "Maximum {$f['max']} characters.";
        }
        $values[$key] = $v;
    }
    if ($errors) {
        json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
    }
    Setting::saveMany($values, $tab);
    log_activity('update', 'settings', "Updated $tabLabel settings");
    json_response(['ok' => true, 'message' => $tabLabel . ' saved successfully.', 'reload' => (bool) array_filter(array_keys($values), fn($k) => in_array($k, ['logo', 'favicon', 'og_image'], true))]);
}

$nav = ['church' => 'church', 'homepage' => 'homepage'][$tab] ?? 'settings';
admin_header('Settings', $nav);
?>
<div class="page-head"><div><h1 class="page-title"><i class="fa-solid fa-gear"></i> Website Settings</h1><p class="page-sub">All church information shown on the website is managed here.</p></div>
<a class="btn btn-light" href="<?= e(url()) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-globe"></i> Preview Website</a></div>

<div class="row g-4">
  <div class="col-lg-3">
    <nav class="settings-nav" aria-label="Settings sections">
      <?php foreach ($tabs as $key => [$label, $icon, $perm]): ?>
        <a href="?tab=<?= e($key) ?>" class="<?= $key === $tab ? 'active' : '' ?>"<?= $key === $tab ? ' aria-current="page"' : '' ?>><i class="fa-solid <?= e($icon) ?>"></i> <?= e($label) ?><?= !can($perm) ? ' <i class="fa-solid fa-lock ms-auto small"></i>' : '' ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('admin/service-times.php')) ?>"><i class="fa-solid fa-clock"></i> Service Times</a>
      <a href="<?= e(url('admin/social-links.php')) ?>"><i class="fa-solid fa-share-nodes"></i> Social Media</a>
      <a href="<?= e(url('admin/hero-video.php')) ?>"><i class="fa-solid fa-film"></i> Hero Video</a>
    </nav>
  </div>
  <div class="col-lg-9">
    <form class="panel" data-ajax method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>">
      <h2 class="panel-title"><?= e($tabLabel) ?></h2>
      <?php if (!$canEdit): ?><div class="alert alert-light border small"><i class="fa-solid fa-lock"></i> You can view these settings but not change them.</div><?php endif; ?>
      <fieldset <?= $canEdit ? '' : 'disabled' ?>><div class="row g-3">
        <?php foreach ($fields as $key => $f):
            if (($f['type'] ?? '') === 'file'): ?>
              <div class="col-md-<?= (int) ($f['col'] ?? 12) ?>"><label class="form-label" for="f_<?= e($key) ?>"><?= e($f['label']) ?></label>
                <div class="d-flex align-items-center gap-3"><img src="<?= e(media_url(setting($key), 'assets/images/logo.svg')) ?>" alt="Current <?= e(strtolower($f['label'])) ?>" class="setting-preview">
                <input type="file" class="form-control" id="f_<?= e($key) ?>" name="<?= e($key) ?>" accept="<?= e(Upload::accept('image')) ?>"></div>
                <div class="invalid-feedback" data-error-for="<?= e($key) ?>"></div></div>
            <?php else:
                echo form_field($key, $f, setting($key));
            endif;
        endforeach; ?>
      </div></fieldset>
      <?php if ($canEdit): ?><div class="mt-4 text-end"><button class="btn btn-gold" data-loading-text="Saving…"><i class="fa-solid fa-check"></i> Save Changes</button></div><?php endif; ?>
    </form>
  </div>
</div>
<?php
admin_footer();
