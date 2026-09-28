<?php
/**
 * Background (hero) videos per public page. A page may have many videos in its
 * library but exactly one active. Large files arrive through chunked upload.
 */
require __DIR__ . '/partials/init.php';
require_permission('hero_video.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    try {
        switch ($action) {
            case 'save':
                require_permission('hero_video.upload');
                $id = (int) post('id');
                $existing = $id ? (HeroVideo::find($id) ?? abort(404)) : null;
                $pageKey = post('page');
                $errors = [];
                if (!isset(HERO_PAGES[$pageKey])) {
                    $errors['page'] = 'Choose the page this video belongs to.';
                }
                if (post('title') === '') {
                    $errors['title'] = 'Title is required.';
                }
                if ($errors) {
                    json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
                }
                $data = ['page' => $pageKey, 'title' => mb_substr(post('title'), 0, 160), 'description' => mb_substr(post('description'), 0, 300) ?: null];
                $replaced = [];
                foreach (['video_mp4' => 'video', 'video_webm' => 'video', 'video_mobile' => 'video', 'poster' => 'image'] as $field => $type) {
                    if ($path = Upload::fromField($field, $type, 'videos', 'hero_video')) {
                        $data[$field] = $path;
                        $replaced[] = $existing[$field] ?? null;
                    }
                }
                if (isset($data['video_webm']) && !str_ends_with($data['video_webm'], '.webm')) {
                    Upload::delete($data['video_webm']);
                    json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => ['video_webm' => 'Please upload a .webm file here (or use the MP4 field).']], 422);
                }
                if (!$existing && empty($data['video_mp4']) && empty($data['video_webm'])) {
                    json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => ['video_mp4' => 'Choose an MP4 (recommended) or WebM video.']], 422);
                }
                if ($existing) {
                    HeroVideo::updateById($id, $data);
                    foreach (array_filter($replaced) as $old) {
                        Upload::delete($old);
                    }
                } else {
                    $data['uploaded_by'] = user_id();
                    $id = HeroVideo::create($data);
                }
                if (!empty($_POST['activate'])) {
                    HeroVideo::activate($id);
                }
                log_activity($existing ? 'update' : 'upload', 'hero_video', ($existing ? 'Updated' : 'Uploaded') . " hero video “{$data['title']}” for the " . HERO_PAGES[$pageKey] . ' page' . (!empty($_POST['activate']) ? ' (active)' : ''));
                json_response(['ok' => true, 'message' => 'Video saved for the ' . HERO_PAGES[$pageKey] . ' page.', 'reload' => true]);

            case 'activate':
                require_permission('hero_video.upload');
                $v = HeroVideo::find((int) post('id')) ?? abort(404);
                HeroVideo::activate((int) $v['id']);
                log_activity('activate', 'hero_video', 'Set “' . $v['title'] . '” as the ' . (HERO_PAGES[$v['page']] ?? $v['page']) . ' page video');
                json_response(['ok' => true, 'message' => '“' . $v['title'] . '” now plays on the ' . (HERO_PAGES[$v['page']] ?? $v['page']) . ' page.', 'reload' => true]);

            case 'deactivate':
                require_permission('hero_video.upload');
                $v = HeroVideo::find((int) post('id')) ?? abort(404);
                DB::update('hero_videos', ['is_active' => 0], 'id = ?', [$v['id']]);
                log_activity('deactivate', 'hero_video', 'Removed the background video from the ' . (HERO_PAGES[$v['page']] ?? $v['page']) . ' page');
                json_response(['ok' => true, 'message' => 'Video turned off. That page now shows its image.', 'reload' => true]);

            case 'delete':
                require_permission('hero_video.delete');
                $v = HeroVideo::find((int) post('id')) ?? abort(404);
                HeroVideo::deleteById((int) $v['id']);
                foreach (['video_mp4', 'video_webm', 'video_mobile', 'poster'] as $f) {
                    Upload::delete($v[$f]);
                }
                log_activity('delete', 'hero_video', "Deleted hero video “{$v['title']}”");
                json_response(['ok' => true, 'message' => 'Video deleted.', 'reload' => true]);
        }
    } catch (UploadException $e) {
        json_response(['ok' => false, 'message' => $e->getMessage()], 422);
    }
    abort(404);
}

$videos = DB::all('SELECT h.*, CONCAT(u.first_name, " ", u.last_name) AS uploader FROM hero_videos h LEFT JOIN users u ON u.id = h.uploaded_by ORDER BY h.page, h.is_active DESC, h.created_at DESC');
$byPage = [];
foreach ($videos as $v) {
    $byPage[$v['page']][] = $v;
}
$selected = isset(HERO_PAGES[$_GET['page'] ?? '']) ? $_GET['page'] : 'home';
$pageUrls = ['home' => '', 'about' => 'about.php', 'ministries' => 'ministries.php', 'sermons' => 'sermons.php', 'events' => 'events.php', 'pastor' => 'pastor.php',
             'giving' => 'giving.php', 'gallery' => 'gallery.php', 'prayer' => 'prayer.php', 'testimonies' => 'testimonies.php', 'contact' => 'contact.php'];

admin_header('Hero Videos', 'hero_video');
?>
<div class="page-head"><div><h1 class="page-title"><i class="fa-solid fa-film"></i> Page Background Videos</h1>
<p class="page-sub">Each page can have its own looping, silent background video. Videos of 30 seconds or longer are fine. Large files upload in pieces with a progress bar.</p></div></div>

<div class="hero-pages mb-4">
  <?php foreach (HERO_PAGES as $key => $label):
      $active = null;
      foreach ($byPage[$key] ?? [] as $v) { if ($v['is_active'] && HeroVideo::hasVideo($v)) { $active = $v; break; } } ?>
    <a class="hero-page-tile<?= $key === $selected ? ' selected' : '' ?>" href="?page=<?= e($key) ?>">
      <span class="hp-thumb" style="background-image:url('<?= e(media_url($active['poster'] ?? null, 'assets/images/placeholders/hero-poster.svg')) ?>')"><?= $active ? '<i class="fa-solid fa-play"></i>' : '' ?></span>
      <span class="hp-name"><?= e($label) ?></span>
      <span class="hp-status <?= $active ? 'on' : '' ?>"><?= $active ? 'Video playing' : 'Image only' ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <div class="col-xl-7">
    <div class="panel">
      <div class="panel-head"><h2 class="panel-title"><?= e(HERO_PAGES[$selected]) ?> page videos</h2>
        <a class="small" href="<?= e(url($pageUrls[$selected])) ?>" target="_blank" rel="noopener">View page <i class="fa-solid fa-arrow-up-right-from-square"></i></a></div>
      <?php if (empty($byPage[$selected])): echo empty_state('fa-film', 'No video for this page yet', 'Upload one on the right; it will play behind the page title.');
      else: foreach ($byPage[$selected] as $v): ?>
        <div class="video-row<?= $v['is_active'] ? ' is-active' : '' ?>">
          <div class="hero-preview sm">
            <?php if (HeroVideo::hasVideo($v)): ?>
              <video muted loop playsinline controls preload="metadata" poster="<?= e(media_url($v['poster'], 'assets/images/placeholders/hero-poster.svg')) ?>">
                <?php if ($v['video_mp4']): ?><source src="<?= e(media_url($v['video_mp4'])) ?>" type="video/mp4"><?php endif; ?>
                <?php if ($v['video_webm']): ?><source src="<?= e(media_url($v['video_webm'])) ?>" type="video/webm"><?php endif; ?>
              </video>
            <?php else: ?><img src="<?= e(media_url($v['poster'], 'assets/images/placeholders/hero-poster.svg')) ?>" alt=""><?php endif; ?>
          </div>
          <div class="flex-grow-1">
            <strong><?= e($v['title']) ?></strong> <?= $v['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?>
            <div class="small text-muted"><?= e($v['description']) ?></div>
            <div class="tiny text-muted mt-1"><?= $v['video_mp4'] ? 'MP4 ' : '' ?><?= $v['video_webm'] ? '· WebM ' : '' ?><?= $v['video_mobile'] ? '· Mobile ' : '' ?><?= !HeroVideo::hasVideo($v) ? 'Poster only' : '' ?> · <?= e($v['uploader'] ?: '—') ?> · <?= e(format_date($v['created_at'])) ?></div>
            <div class="d-flex gap-2 mt-2">
              <?php if (can('hero_video.upload')): ?>
                <?php if ($v['is_active']): ?><button class="btn btn-sm btn-light" data-post-action="deactivate" data-id="<?= (int) $v['id'] ?>">Turn off</button>
                <?php else: ?><button class="btn btn-sm btn-navy" data-post-action="activate" data-id="<?= (int) $v['id'] ?>"><i class="fa-solid fa-play"></i> Use on this page</button><?php endif; ?>
              <?php endif; ?>
              <?php if (can('hero_video.delete')): ?><button class="btn btn-sm btn-outline-danger" data-post-action="delete" data-id="<?= (int) $v['id'] ?>" data-confirm="Delete “<?= e($v['title']) ?>” and its files?"><i class="fa-solid fa-trash"></i></button><?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="col-xl-5">
    <?php if (can('hero_video.upload')): ?>
    <form class="panel" data-ajax method="post" enctype="multipart/form-data" novalidate>
      <h2 class="panel-title">Upload a Video</h2>
      <?= csrf_field() ?><input type="hidden" name="action" value="save">
      <div class="row g-3">
        <?= form_field('page', ['label' => 'Show on page', 'type' => 'select', 'required' => true, 'options_resolved' => HERO_PAGES], $selected) ?>
        <?= form_field('title', ['label' => 'Title', 'required' => true, 'max' => 160, 'placeholder' => 'e.g. Sunday Worship 2026']) ?>
        <?= form_field('description', ['label' => 'Note (optional)', 'max' => 300]) ?>
        <?= form_field('video_mp4', ['label' => 'Video (MP4)', 'type' => 'file', 'upload' => 'video', 'dir' => 'videos']) ?>
        <?= form_field('poster', ['label' => 'Poster / Fallback Image', 'type' => 'file', 'upload' => 'image', 'help' => 'Shown while the video loads and to visitors on data-saver.']) ?>
        <div class="col-12"><details><summary class="small fw-semibold">Optional extra versions</summary><div class="row g-3 mt-1">
          <?= form_field('video_webm', ['label' => 'WebM version (smaller)', 'type' => 'file', 'upload' => 'video', 'dir' => 'videos']) ?>
          <?= form_field('video_mobile', ['label' => 'Mobile version (MP4, 720p)', 'type' => 'file', 'upload' => 'video', 'dir' => 'videos']) ?>
        </div></details></div>
        <?= form_field('activate', ['label' => 'Activate', 'type' => 'checkbox', 'check_label' => 'Play this video on the page now', 'default' => 1]) ?>
      </div>
      <div class="upload-progress mt-3 d-none" data-upload-progress><div class="progress" role="progressbar" aria-label="Upload progress" style="height:10px"><div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" style="width:0%"></div></div><div class="small text-muted mt-1" data-progress-text>Uploading…</div></div>
      <div class="mt-4 text-end"><button class="btn btn-gold" data-loading-text="Uploading…"><i class="fa-solid fa-cloud-arrow-up"></i> Save Video</button></div>
      <p class="small text-muted mt-3 mb-0"><i class="fa-solid fa-lightbulb"></i> Tip: for fast loading, export at 1080p (or 720p) around 5–8 Mbps. Up to <?= Upload::maxMb('video') ?> MB is accepted. Videos play muted on a loop.</p>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php
admin_footer();
