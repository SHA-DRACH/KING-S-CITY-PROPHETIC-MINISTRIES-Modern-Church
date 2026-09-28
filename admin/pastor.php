<?php
/** Senior Pastor profile (public Pastor page + homepage section). */
require __DIR__ . '/partials/init.php';
require_permission('pastor.view');

$pastor = Pastor::primary();
$fields = [
    'name'             => ['label' => 'Name', 'required' => true, 'max' => 120, 'col' => 6],
    'position'         => ['label' => 'Position', 'required' => true, 'max' => 120, 'col' => 6],
    'short_bio'        => ['label' => 'Short Biography (homepage)', 'type' => 'textarea', 'rows' => 3, 'required' => true],
    'biography'        => ['label' => 'Full Biography', 'type' => 'textarea', 'rows' => 6, 'help' => 'Leave a blank line between paragraphs.'],
    'ministry_journey' => ['label' => 'Ministry Journey', 'type' => 'textarea', 'rows' => 5],
    'vision'           => ['label' => 'Vision', 'type' => 'textarea', 'rows' => 2],
    'message'          => ['label' => 'Pastor\'s Message', 'type' => 'textarea', 'rows' => 5],
    'scripture'        => ['label' => 'Favourite Scripture', 'type' => 'textarea', 'rows' => 2, 'col' => 8],
    'scripture_ref'    => ['label' => 'Reference', 'max' => 80, 'col' => 4],
    'email'            => ['label' => 'Contact Email', 'type' => 'email', 'max' => 190, 'col' => 6],
    'phone'            => ['label' => 'Contact Phone', 'type' => 'tel', 'max' => 40, 'col' => 6],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_permission('pastor.edit');
    $data = [];
    $errors = [];
    foreach ($fields as $k => $f) {
        $v = post($k);
        if (!empty($f['required']) && $v === '') {
            $errors[$k] = $f['label'] . ' is required.';
        } elseif (($f['type'] ?? '') === 'email' && $v !== '' && !valid_email($v)) {
            $errors[$k] = 'Enter a valid email.';
        } elseif (isset($f['max']) && mb_strlen($v) > $f['max']) {
            $errors[$k] = "Maximum {$f['max']} characters.";
        }
        $data[$k] = $v === '' ? null : $v;
    }
    try {
        if ($photo = Upload::fromField('photo', 'image', 'profiles', 'pastor')) {
            $data['photo'] = $photo;
        }
    } catch (UploadException $e) {
        $errors['photo'] = $e->getMessage();
    }
    if ($errors) {
        json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
    }
    if ($pastor) {
        Pastor::updateById((int) $pastor['id'], $data);
        if (isset($data['photo'])) {
            Upload::delete($pastor['photo']);
        }
    } else {
        Pastor::create($data + ['is_primary' => 1]);
    }
    log_activity('update', 'pastor', 'Updated the Senior Pastor profile');
    json_response(['ok' => true, 'message' => 'Pastor profile saved.', 'reload' => isset($data['photo'])]);
}

$canEdit = can('pastor.edit');
admin_header('Pastor', 'pastor');
?>
<div class="page-head"><div><h1 class="page-title"><i class="fa-solid fa-user-tie"></i> Senior Pastor Profile</h1><p class="page-sub">Shown on the Pastor page and the homepage “Meet Our Senior Pastor” section.</p></div>
<a class="btn btn-light" href="<?= e(url('pastor.php')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> View Pastor Page</a></div>

<form class="row g-4" data-ajax method="post" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <div class="col-lg-4">
    <div class="panel text-center">
      <img src="<?= e(media_url($pastor['photo'] ?? null, 'assets/images/placeholders/pastor.svg')) ?>" alt="Portrait of <?= e($pastor['name'] ?? 'the pastor') ?>" class="pastor-portrait">
      <h2 class="h5 mt-3 mb-0"><?= e($pastor['name'] ?? '') ?></h2><p class="text-muted small"><?= e($pastor['position'] ?? '') ?></p>
      <?php if ($canEdit): ?><div class="text-start"><?= form_field('photo', ['label' => 'Professional Portrait', 'type' => 'file', 'upload' => 'image']) ?></div><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="panel"><fieldset <?= $canEdit ? '' : 'disabled' ?>><div class="row g-3">
      <?php foreach ($fields as $k => $f) echo form_field($k, $f, $pastor[$k] ?? ''); ?>
    </div></fieldset>
    <?php if ($canEdit): ?><div class="mt-4 text-end"><button class="btn btn-gold" data-loading-text="Saving…"><i class="fa-solid fa-check"></i> Save Profile</button></div><?php endif; ?>
    </div>
  </div>
</form>
<?php
admin_footer();
