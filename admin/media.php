<?php
/** Media library: every file uploaded through the system, plus direct uploads. */
require __DIR__ . '/partials/init.php';
require_permission('media.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'upload') {
        require_permission('media.upload');
        $type = in_array(post('file_type'), ['image', 'document', 'audio', 'video'], true) ? post('file_type') : 'image';
        try {
            $path = Upload::fromField('file', $type, $type === 'image' ? 'images' : ($type === 'document' ? 'documents' : ($type === 'audio' ? 'sermons' : 'videos')), 'media');
        } catch (UploadException $e) {
            json_response(['ok' => false, 'message' => $e->getMessage(), 'errors' => ['file' => $e->getMessage()]], 422);
        }
        if (!$path) {
            json_response(['ok' => false, 'message' => 'Choose a file to upload.', 'errors' => ['file' => 'Choose a file.']], 422);
        }
        log_activity('upload', 'media', 'Uploaded ' . basename($path));
        json_response(['ok' => true, 'message' => 'File uploaded.', 'reload' => true]);
    }
    if ($action === 'delete') {
        require_permission('media.delete');
        $m = DB::one('SELECT * FROM media WHERE id = ?', [(int) post('id')]) ?? abort(404);
        Upload::delete($m['file_path']);
        DB::delete('media', 'id = ?', [$m['id']]);
        log_activity('delete', 'media', 'Deleted media ' . ($m['original_name'] ?: basename($m['file_path'])));
        json_response(['ok' => true, 'message' => 'File deleted.', 'reload' => true]);
    }
    abort(404);
}

$type = in_array($_GET['type'] ?? '', ['image', 'video', 'audio', 'document'], true) ? $_GET['type'] : '';
$p = paginate((int) DB::value('SELECT COUNT(*) FROM media' . ($type ? ' WHERE file_type = ?' : ''), $type ? [$type] : []), 24);
$items = DB::all('SELECT m.*, CONCAT(u.first_name, " ", u.last_name) AS uploader FROM media m LEFT JOIN users u ON u.id = m.uploaded_by'
    . ($type ? ' WHERE m.file_type = ?' : '') . " ORDER BY m.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}", $type ? [$type] : []);
$icons = ['video' => 'fa-file-video', 'audio' => 'fa-file-audio', 'document' => 'fa-file-pdf'];

admin_header('Media', 'media');
?>
<div class="page-head"><div><h1 class="page-title"><i class="fa-solid fa-photo-film"></i> Media Library</h1><p class="page-sub">All uploaded files, stored with safe random filenames.</p></div></div>
<div class="row g-4">
  <div class="col-xl-9">
    <div class="tab-bar mb-3">
      <?php foreach (['' => 'All', 'image' => 'Images', 'video' => 'Videos', 'audio' => 'Audio', 'document' => 'Documents'] as $k => $label): ?>
        <a class="tab-link<?= $type === $k ? ' active' : '' ?>" href="?type=<?= e($k) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <?php if (!$items): echo empty_state('fa-photo-film', 'No files yet', 'Files uploaded anywhere in the dashboard appear here.');
    else: ?>
    <div class="media-grid">
      <?php foreach ($items as $m): $u = media_url($m['file_path']); ?>
        <div class="media-item">
          <?php if ($m['file_type'] === 'image'): ?><img src="<?= e($u) ?>" alt="<?= e($m['original_name']) ?>" loading="lazy">
          <?php else: ?><div class="media-file"><i class="fa-solid <?= e($icons[$m['file_type']] ?? 'fa-file') ?>"></i></div><?php endif; ?>
          <div class="media-meta"><div class="text-truncate small fw-semibold" title="<?= e($m['original_name']) ?>"><?= e($m['original_name'] ?: basename($m['file_path'])) ?></div>
            <div class="tiny text-muted"><?= e(Upload::humanSize((int) $m['size_bytes'])) ?> · <?= e($m['module']) ?></div>
            <div class="d-flex gap-1 mt-1">
              <button class="btn-icon sm" data-copy="<?= e($u) ?>" title="Copy URL" aria-label="Copy URL"><i class="fa-regular fa-copy"></i></button>
              <a class="btn-icon sm" href="<?= e($u) ?>" target="_blank" rel="noopener" title="Open" aria-label="Open"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
              <?php if (can('media.delete')): ?><button class="btn-icon sm danger" data-post-action="delete" data-id="<?= (int) $m['id'] ?>" data-confirm="Delete this file? Any page using it will lose the image." aria-label="Delete"><i class="fa-solid fa-trash"></i></button><?php endif; ?>
            </div></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-3"><?= pagination_links($p) ?></div>
    <?php endif; ?>
  </div>
  <div class="col-xl-3">
    <?php if (can('media.upload')): ?>
    <form class="panel" data-ajax method="post" enctype="multipart/form-data" novalidate>
      <h2 class="panel-title">Upload File</h2><?= csrf_field() ?><input type="hidden" name="action" value="upload">
      <div class="row g-3">
        <?= form_field('file_type', ['label' => 'Type', 'type' => 'select', 'required' => true, 'options_resolved' => ['image' => 'Image (JPG, PNG, WebP)', 'document' => 'Document (PDF)', 'audio' => 'Audio (MP3, M4A)', 'video' => 'Video (MP4, WebM)']], 'image') ?>
        <div class="col-12"><label class="form-label" for="f_file">File</label><input class="form-control" type="file" id="f_file" name="file" required><div class="invalid-feedback" data-error-for="file"></div></div>
      </div>
      <div class="upload-progress mt-3 d-none" data-upload-progress><div class="progress" role="progressbar" aria-label="Upload progress"><div class="progress-bar bg-warning" style="width:0%"></div></div></div>
      <button class="btn btn-gold w-100 mt-3" data-loading-text="Uploading…"><i class="fa-solid fa-cloud-arrow-up"></i> Upload</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php
admin_footer();
