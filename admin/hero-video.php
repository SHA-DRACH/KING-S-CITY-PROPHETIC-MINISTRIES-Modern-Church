<?php
/**
 * Homepage hero video library. Many videos may be stored; exactly one is active.
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
                $title = post('title');
                if ($title === '') {
                    json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => ['title' => 'Title is required.']], 422);
                }
                $data = ['title' => mb_substr($title, 0, 160), 'description' => mb_substr(post('description'), 0, 300) ?: null];
                $replaced = [];
                foreach (['video_mp4' => 'video', 'video_webm' => 'video', 'video_mobile' => 'video', 'poster' => 'image'] as $field => $type) {
                    if ($path = Upload::fromField($field, $type, 'videos', 'hero_video')) {
                        $data[$field] = $path;
                        $replaced[] = $existing[$field] ?? null;
                    }
                }
                // MP4/WebM uploads must match their slot
                foreach (['video_mp4' => 'mp4', 'video_webm' => 'webm'] as $field => $ext) {
                    if (isset($data[$field]) && !str_ends_with($data[$field], ".$ext")) {
                        Upload::delete($data[$field]);
                        json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => [$field => 'Please upload a .' . $ext . ' file here.']], 422);
                    }
                }
                if (!$existing && empty($data['video_mp4']) && empty($data['video_webm'])) {
                    json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => ['video_mp4' => 'Upload an MP4 (recommended) or WebM video.']], 422);
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
                log_activity($existing ? 'update' : 'upload', 'hero_video', ($existing ? 'Updated' : 'Uploaded') . " hero video “{$data['title']}”" . (!empty($_POST['activate']) ? ' and set it active' : ''));
                json_response(['ok' => true, 'message' => 'Hero video saved.', 'reload' => true]);

            case 'activate':
                require_permission('hero_video.upload');
                $v = HeroVideo::find((int) post('id')) ?? abort(404);
                HeroVideo::activate((int) $v['id']);
                log_activity('activate', 'hero_video', "Changed homepage video to “{$v['title']}”");
                json_response(['ok' => true, 'message' => '“' . $v['title'] . '” is now the homepage video.', 'reload' => true]);

            case 'deactivate':
                require_permission('hero_video.upload');
                DB::update('hero_videos', ['is_active' => 0], 'id = ?', [(int) post('id')]);
                log_activity('deactivate', 'hero_video', 'Deactivated hero video #' . (int) post('id'));
                json_response(['ok' => true, 'message' => 'Video deactivated. The homepage will show the poster image.', 'reload' => true]);

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

$videos = DB::all('SELECT h.*, CONCAT(u.first_name, " ", u.last_name) AS uploader FROM hero_videos h LEFT JOIN users u ON u.id = h.uploaded_by ORDER BY h.is_active DESC, h.created_at DESC');
$active = HeroVideo::active();
$maxMb = (int) setting('max_video_upload_mb', '200');

admin_header('Hero Video', 'hero_video');
?>
<div class="page-head"><div><h1 class="page-title"><i class="fa-solid fa-film"></i> Homepage Hero Video</h1>
<p class="page-sub">Short, silent, looping clips (10–30 s, 1080p, under ~15 MB) work best. Videos autoplay muted and loop with a dark overlay.</p></div></div>

<div class="row g-4">
  <div class="col-xl-7">
    <div class="panel">
      <h2 class="panel-title">Current Hero Video</h2>
      <?php if ($active): ?>
        <div class="hero-preview">
          <?php if ($active['video_mp4'] || $active['video_webm']): ?>
            <video autoplay muted loop playsinline controls poster="<?= e(media_url($active['poster'], 'assets/images/placeholders/hero-poster.svg')) ?>">
              <?php if ($active['video_webm']): ?><source src="<?= e(media_url($active['video_webm'])) ?>" type="video/webm"><?php endif; ?>
              <?php if ($active['video_mp4']): ?><source src="<?= e(media_url($active['video_mp4'])) ?>" type="video/mp4"><?php endif; ?>
            </video>
          <?php else: ?>
            <img src="<?= e(media_url($active['poster'], 'assets/images/placeholders/hero-poster.svg')) ?>" alt="Poster image currently shown on the homepage">
            <div class="hero-preview-note"><i class="fa-solid fa-circle-info"></i> No video file yet — the homepage shows this poster image.</div>
          <?php endif; ?>
        </div>
        <h3 class="h6 mt-3 mb-0"><?= e($active['title']) ?></h3><p class="small text-muted"><?= e($active['description']) ?></p>
      <?php else: ?>
        <?= empty_state('fa-film', 'No active hero video', 'The homepage is showing the default poster image.') ?>
      <?php endif; ?>
    </div>

    <div class="panel mt-4">
      <h2 class="panel-title">Video Library</h2>
      <?php if (!$videos): echo empty_state('fa-photo-film', 'Library is empty');
      else: ?>
      <div class="table-responsive"><table class="table admin-table align-middle">
        <thead><tr><th></th><th>Title</th><th>Files</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
        <?php foreach ($videos as $v): ?>
          <tr><td><img class="thumb" src="<?= e(media_url($v['poster'], 'assets/images/placeholders/hero-poster.svg')) ?>" alt=""></td>
            <td><strong class="cell-title"><?= e($v['title']) ?></strong><div class="cell-sub"><?= e($v['uploader'] ?: '—') ?> · <?= e(format_date($v['created_at'])) ?></div></td>
            <td class="small"><?= $v['video_mp4'] ? '<span class="badge text-bg-light">MP4</span> ' : '' ?><?= $v['video_webm'] ? '<span class="badge text-bg-light">WebM</span> ' : '' ?><?= $v['video_mobile'] ? '<span class="badge text-bg-light">Mobile</span>' : '' ?><?= !$v['video_mp4'] && !$v['video_webm'] ? '<span class="text-muted">Poster only</span>' : '' ?></td>
            <td><?= $v['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
            <td class="text-end text-nowrap">
              <?php if (can('hero_video.upload')): ?>
                <?php if ($v['is_active']): ?><button class="btn btn-sm btn-light" data-post-action="deactivate" data-id="<?= (int) $v['id'] ?>">Deactivate</button>
                <?php else: ?><button class="btn btn-sm btn-navy" data-post-action="activate" data-id="<?= (int) $v['id'] ?>">Activate</button><?php endif; ?>
              <?php endif; ?>
              <?php if (can('hero_video.delete')): ?><button class="btn-icon danger" data-post-action="delete" data-id="<?= (int) $v['id'] ?>" data-confirm="Delete “<?= e($v['title']) ?>” and its files?" aria-label="Delete"><i class="fa-solid fa-trash"></i></button><?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-xl-5">
    <?php if (can('hero_video.upload')): ?>
    <form class="panel" data-ajax method="post" enctype="multipart/form-data" novalidate>
      <h2 class="panel-title">Upload New Video</h2>
      <?= csrf_field() ?><input type="hidden" name="action" value="save">
      <div class="row g-3">
        <?= form_field('title', ['label' => 'Title', 'required' => true, 'max' => 160, 'placeholder' => 'e.g. Sunday Worship 2026']) ?>
        <?= form_field('description', ['label' => 'Description', 'max' => 300]) ?>
        <?= form_field('video_mp4', ['label' => 'Video (MP4) — recommended', 'type' => 'file', 'upload' => 'video']) ?>
        <?= form_field('video_webm', ['label' => 'Video (WebM) — optional, smaller', 'type' => 'file', 'upload' => 'video']) ?>
        <?= form_field('video_mobile', ['label' => 'Mobile Version (MP4, 720p) — optional', 'type' => 'file', 'upload' => 'video']) ?>
        <?= form_field('poster', ['label' => 'Poster / Fallback Image', 'type' => 'file', 'upload' => 'image', 'help' => 'Shown while the video loads and when motion is reduced.']) ?>
        <?= form_field('activate', ['label' => 'Activate', 'type' => 'checkbox', 'check_label' => 'Make this the homepage video now', 'default' => 1]) ?>
      </div>
      <div class="upload-progress mt-3 d-none" data-upload-progress><div class="progress" role="progressbar" aria-label="Upload progress"><div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" style="width:0%"></div></div><div class="small text-muted mt-1" data-progress-text>Uploading…</div></div>
      <div class="mt-4 text-end"><button class="btn btn-gold" data-loading-text="Uploading…"><i class="fa-solid fa-cloud-arrow-up"></i> Save Video</button></div>
      <p class="small text-muted mt-3 mb-0"><i class="fa-solid fa-circle-info"></i> Max <?= $maxMb ?> MB per file. On mobile the lighter version is used when provided; visitors with data-saver or reduced motion see only the poster.</p>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php
admin_footer();
