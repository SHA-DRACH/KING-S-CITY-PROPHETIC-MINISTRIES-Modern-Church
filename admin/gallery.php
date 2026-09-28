<?php
/**
 * Gallery: albums of photos dated by day or month.
 *  - Bulk upload: select many photos at once, no captions needed.
 *  - "Photo Downloads" albums: members find and download their own photos.
 *  - "Event Gallery" albums: the reference gallery of church life.
 */
require __DIR__ . '/partials/init.php';
require_permission('gallery.view');

$categories = array_column(Gallery::categories(), 'name', 'id');
$departments = array_column(Department::options(), 'name', 'id');

function load_album(int $id): array
{
    $a = Album::findWithStats($id) ?? abort(404, 'Album not found.');
    if (!Album::canManage($a)) {
        abort(403, 'This album belongs to another department.');
    }
    return $a;
}

/** Validate album fields from POST. */
function album_input(array $categories, array $departments): array
{
    $errors = [];
    $precision = post('date_precision') === 'month' ? 'month' : 'day';
    $date = $precision === 'month' ? post('album_month') . '-01' : post('album_date');
    if (!DateTime::createFromFormat('Y-m-d', $date)) {
        $errors[$precision === 'month' ? 'album_month' : 'album_date'] = 'Choose the ' . ($precision === 'month' ? 'month' : 'date') . ' of these photos.';
    }
    $type = isset(Album::TYPES[post('type')]) ? post('type') : 'downloads';
    $cat = (int) post('category_id');
    $dept = (int) post('department_id');
    if (!has_all_department_access() && !isset($departments[$dept])) {
        $errors['department_id'] = 'Choose your department.';
    }
    if (mb_strlen(post('title')) > 200) {
        $errors['title'] = 'Title is too long.';
    }
    if ($errors) {
        json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
    }
    return [
        'title'          => post('title') ?: null,
        'album_date'     => $date,
        'date_precision' => $precision,
        'type'           => $type,
        'category_id'    => isset($categories[$cat]) ? $cat : null,
        'department_id'  => isset($departments[$dept]) ? $dept : null,
        'description'    => mb_substr(post('description'), 0, 300) ?: null,
        'allow_download' => !empty($_POST['allow_download']) ? 1 : 0,
        'is_published'   => !empty($_POST['is_published']) ? 1 : 0,
    ];
}

// ---------------------------------------------------------------------
//  Actions
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post('action')) {
        case 'create_album':
            require_permission('gallery.upload');
            $data = album_input($categories, $departments) + ['created_by' => user_id()];
            $id = Album::create($data);
            log_activity('create', 'gallery', 'Created album “' . Album::label($data) . '”');
            json_response(['ok' => true, 'message' => 'Album created.', 'album_id' => $id]);

        case 'update_album':
            require_permission('gallery.edit');
            $album = load_album((int) post('id'));
            $data = album_input($categories, $departments);
            Album::updateById((int) $album['id'], $data);
            DB::update('gallery', ['category_id' => $data['category_id'], 'department_id' => $data['department_id']], 'album_id = ?', [$album['id']]);
            log_activity('update', 'gallery', 'Updated album “' . Album::label($data) . '”');
            json_response(['ok' => true, 'message' => 'Album saved.', 'reload' => true]);

        case 'toggle_album':
            require_permission('gallery.edit');
            $album = load_album((int) post('id'));
            DB::update('photo_albums', ['is_published' => $album['is_published'] ? 0 : 1], 'id = ?', [$album['id']]);
            log_activity($album['is_published'] ? 'unpublish' : 'publish', 'gallery', ($album['is_published'] ? 'Hid' : 'Published') . ' album “' . Album::label($album) . '”');
            json_response(['ok' => true, 'message' => $album['is_published'] ? 'Album hidden from the website.' : 'Album published.']);

        case 'delete_album':
            require_permission('gallery.delete');
            $album = load_album((int) post('id'));
            foreach (DB::all('SELECT file_path, thumb_path FROM gallery WHERE album_id = ?', [$album['id']]) as $p) {
                Upload::delete($p['file_path']);
                Upload::delete($p['thumb_path']);
            }
            Album::deleteById((int) $album['id']); // photos cascade
            log_activity('delete', 'gallery', 'Deleted album “' . Album::label($album) . '” with ' . $album['photo_count'] . ' photos');
            json_response(['ok' => true, 'message' => 'Album and its photos deleted.', 'redirect' => url('admin/gallery.php')]);

        case 'upload_photo':
            // One photo per request, so any number of photos can be sent without hitting server limits.
            require_permission('gallery.upload');
            $album = load_album((int) post('album_id'));
            try {
                $path = Upload::fromField('photo', 'image', 'gallery/' . date('Y-m', strtotime($album['album_date'])), 'gallery');
            } catch (UploadException $e) {
                json_response(['ok' => false, 'message' => $e->getMessage()], 422);
            }
            if (!$path) {
                json_response(['ok' => false, 'message' => 'No photo received.'], 422);
            }
            $t = Image::thumbnail($path);
            $pid = DB::insert('gallery', [
                'album_id' => $album['id'], 'title' => null, 'category_id' => $album['category_id'], 'department_id' => $album['department_id'],
                'media_type' => 'image', 'file_path' => $path, 'thumb_path' => $t['thumb'], 'width' => $t['width'], 'height' => $t['height'],
                'is_published' => 1, 'uploaded_by' => user_id(),
            ]);
            if (!$album['cover_id']) {
                DB::update('photo_albums', ['cover_id' => $pid], 'id = ? AND cover_id IS NULL', [$album['id']]);
            }
            json_response(['ok' => true, 'id' => $pid, 'thumb' => media_url($t['thumb'] ?: $path)]);

        case 'upload_done':
            require_permission('gallery.upload');
            $album = load_album((int) post('album_id'));
            log_activity('upload', 'gallery', 'Uploaded ' . (int) post('count') . ' photos to “' . Album::label($album) . '”');
            json_response(['ok' => true]);

        case 'delete_photos':
            require_permission('gallery.delete');
            $album = load_album((int) post('album_id'));
            $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
            $n = 0;
            foreach ($ids as $pid) {
                $p = DB::one('SELECT * FROM gallery WHERE id = ? AND album_id = ?', [$pid, $album['id']]);
                if ($p) {
                    Upload::delete($p['file_path']);
                    Upload::delete($p['thumb_path']);
                    DB::delete('gallery', 'id = ?', [$pid]);
                    $n++;
                }
            }
            if ($album['cover_id'] && in_array((int) $album['cover_id'], $ids, true)) {
                DB::update('photo_albums', ['cover_id' => null], 'id = ?', [$album['id']]);
            }
            log_activity('delete', 'gallery', "Deleted $n photos from “" . Album::label($album) . '”');
            json_response(['ok' => true, 'message' => "$n photo" . ($n === 1 ? '' : 's') . ' deleted.', 'reload' => true]);

        case 'set_cover':
            require_permission('gallery.edit');
            $album = load_album((int) post('album_id'));
            $pid = (int) post('photo_id');
            if (DB::value('SELECT COUNT(*) FROM gallery WHERE id = ? AND album_id = ?', [$pid, $album['id']])) {
                DB::update('photo_albums', ['cover_id' => $pid], 'id = ?', [$album['id']]);
            }
            json_response(['ok' => true, 'message' => 'Album cover updated.', 'reload' => true]);
    }
    abort(404);
}

// ---------------------------------------------------------------------
//  Shared: the upload + album form
// ---------------------------------------------------------------------
function uploader_panel(?array $album, array $categories, array $departments): void
{
    $albums = $album ? [] : Album::adminList('', 100);
    ?>
    <div class="panel bulk-uploader" id="bulkUploader" data-endpoint="<?= e(url('admin/gallery.php')) ?>" <?= $album ? 'data-album-id="' . (int) $album['id'] . '" data-album-url="' . e(url('admin/gallery.php?album=' . $album['id'])) . '"' : '' ?>>
      <h2 class="panel-title"><i class="fa-solid fa-cloud-arrow-up text-gold"></i> <?= $album ? 'Add more photos to this album' : 'Upload Photos' ?></h2>
      <form data-album-form novalidate>
        <?php if (!$album): ?>
          <div class="seg-choice mb-3" role="radiogroup" aria-label="Album">
            <label><input type="radio" name="album_mode" value="new" checked> New date / album</label>
            <label><input type="radio" name="album_mode" value="existing" <?= $albums ? '' : 'disabled' ?>> Add to existing album</label>
          </div>
          <div data-mode="existing" hidden>
            <label class="form-label" for="existingAlbum">Album</label>
            <select class="form-select" id="existingAlbum" name="existing_album">
              <?php foreach ($albums as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e(Album::dateLabel($a, 'M j, Y') . ($a['title'] ? ' — ' . $a['title'] : '') . ' (' . Album::TYPES[$a['type']] . ')') ?></option><?php endforeach; ?>
            </select>
          </div>
          <div data-mode="new" class="row g-3">
            <?php album_fields(null, $categories, $departments); ?>
          </div>
        <?php endif; ?>
      </form>

      <label class="dropzone mt-3" data-dropzone>
        <input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden data-photo-input>
        <i class="fa-regular fa-images"></i>
        <strong>Click to choose photos, or drag &amp; drop them here</strong>
        <span>Select as many as you like · JPG, PNG, WebP up to <?= Upload::maxMb('image') ?> MB each · no captions needed</span>
      </label>
      <div class="upload-queue" data-queue hidden>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <strong data-queue-count>0 photos selected</strong>
          <button type="button" class="btn btn-sm btn-light" data-clear-queue>Clear</button>
        </div>
        <div class="queue-grid" data-queue-grid></div>
        <div class="progress mt-3" role="progressbar" aria-label="Upload progress" style="height:10px" hidden data-overall><div class="progress-bar bg-warning" style="width:0%"></div></div>
        <p class="small text-muted mt-1 mb-0" data-overall-text></p>
        <button type="button" class="btn btn-gold btn-lg w-100 mt-3" data-start-upload><i class="fa-solid fa-cloud-arrow-up"></i> Upload photos</button>
      </div>
    </div>
    <?php
}

function album_fields(?array $a, array $categories, array $departments): void
{
    $precision = $a['date_precision'] ?? 'day';
    ?>
    <div class="col-12"><span class="form-label d-block">Photos are from</span>
      <div class="seg-choice" role="radiogroup" aria-label="Date type">
        <label><input type="radio" name="date_precision" value="day" <?= $precision === 'day' ? 'checked' : '' ?>> A specific day</label>
        <label><input type="radio" name="date_precision" value="month" <?= $precision === 'month' ? 'checked' : '' ?>> A whole month</label>
      </div></div>
    <div class="col-md-6" data-precision="day" <?= $precision === 'month' ? 'hidden' : '' ?>><label class="form-label" for="album_date">Date <span class="text-danger">*</span></label>
      <input type="date" class="form-control" id="album_date" name="album_date" value="<?= e($a && $precision === 'day' ? $a['album_date'] : date('Y-m-d')) ?>"><div class="invalid-feedback" data-error-for="album_date"></div></div>
    <div class="col-md-6" data-precision="month" <?= $precision === 'day' ? 'hidden' : '' ?>><label class="form-label" for="album_month">Month <span class="text-danger">*</span></label>
      <input type="month" class="form-control" id="album_month" name="album_month" value="<?= e($a ? substr($a['album_date'], 0, 7) : date('Y-m')) ?>"><div class="invalid-feedback" data-error-for="album_month"></div></div>
    <div class="col-md-6"><label class="form-label" for="album_type">Show in</label>
      <select class="form-select" id="album_type" name="type"><?php foreach (Album::TYPES as $k => $label): ?><option value="<?= $k ?>" <?= ($a['type'] ?? 'downloads') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      <div class="form-text">Photo Downloads = members find &amp; download their photos. Event Gallery = reference photos.</div></div>
    <div class="col-md-6"><label class="form-label" for="album_title">Title <span class="text-muted">(optional)</span></label>
      <input class="form-control" id="album_title" name="title" maxlength="200" value="<?= e($a['title'] ?? '') ?>" placeholder="e.g. Sunday Service"><div class="invalid-feedback" data-error-for="title"></div></div>
    <div class="col-md-6"><label class="form-label" for="album_cat">Category <span class="text-muted">(optional)</span></label>
      <select class="form-select" id="album_cat" name="category_id"><option value="">— None —</option><?php foreach ($categories as $id => $name): ?><option value="<?= (int) $id ?>" <?= (int) ($a['category_id'] ?? 0) === (int) $id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label" for="album_dept">Department <?= has_all_department_access() ? '<span class="text-muted">(optional)</span>' : '<span class="text-danger">*</span>' ?></label>
      <select class="form-select" id="album_dept" name="department_id"><?php if (has_all_department_access()): ?><option value="">— Whole church —</option><?php endif; ?><?php foreach ($departments as $id => $name): ?><option value="<?= (int) $id ?>" <?= (int) ($a['department_id'] ?? 0) === (int) $id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select><div class="invalid-feedback" data-error-for="department_id"></div></div>
    <div class="col-12 d-flex flex-wrap gap-4">
      <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="allow_download" name="allow_download" value="1" <?= ($a['allow_download'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="allow_download">Visitors can download</label></div>
      <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="is_published" name="is_published" value="1" <?= ($a['is_published'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="is_published">Show on website</label></div>
    </div>
    <?php
}

// ---------------------------------------------------------------------
//  Album detail
// ---------------------------------------------------------------------
if ($albumId = query_int('album')) {
    $album = load_album($albumId);
    $p = paginate(Album::countPhotos($albumId, false), 120);
    $photos = Album::photos($albumId, false, $p['per_page'], $p['offset']);
    admin_header('Gallery', 'gallery');
    ?>
    <div class="page-head">
      <div><h1 class="page-title"><i class="fa-solid fa-images"></i> <?= e(Album::label($album)) ?></h1>
        <p class="page-sub"><?= e(Album::dateLabel($album)) ?> · <?= e(Album::TYPES[$album['type']]) ?> · <?= (int) $p['total'] ?> photos · <?= $album['is_published'] ? '<span class="text-success">Published</span>' : '<span class="text-danger">Hidden</span>' ?></p></div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-light" href="<?= e(url('admin/gallery.php')) ?>"><i class="fa-solid fa-arrow-left"></i> All albums</a>
        <?php if ($album['is_published']): ?><a class="btn btn-light" href="<?= e(url('gallery.php?album=' . $album['id'])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-globe"></i> View on website</a><?php endif; ?>
        <?php if (can('gallery.edit')): ?><button class="btn btn-light" data-post-action="toggle_album" data-id="<?= (int) $album['id'] ?>"><i class="fa-solid <?= $album['is_published'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i> <?= $album['is_published'] ? 'Hide' : 'Publish' ?></button><?php endif; ?>
        <?php if (can('gallery.delete')): ?><button class="btn btn-outline-danger" data-post-action="delete_album" data-id="<?= (int) $album['id'] ?>" data-confirm="Delete this album and all <?= (int) $p['total'] ?> photos? This cannot be undone."><i class="fa-solid fa-trash"></i> Delete album</button><?php endif; ?>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-xl-8">
        <div class="panel">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h2 class="panel-title mb-0">Photos</h2>
            <?php if (can('gallery.delete') && $photos): ?>
              <div class="d-flex gap-2 align-items-center"><label class="small"><input type="checkbox" data-select-all> Select all</label>
                <button class="btn btn-sm btn-outline-danger" data-delete-selected data-album="<?= (int) $album['id'] ?>" disabled><i class="fa-solid fa-trash"></i> Delete selected</button></div>
            <?php endif; ?>
          </div>
          <?php if (!$photos): echo empty_state('fa-images', 'No photos yet', 'Use the uploader to add photos to this album.');
          else: ?>
            <div class="photo-grid">
              <?php foreach ($photos as $ph): ?>
                <div class="photo-tile<?= (int) $album['cover_id'] === (int) $ph['id'] ? ' is-cover' : '' ?>">
                  <img src="<?= e(media_url($ph['thumb_path'] ?: $ph['file_path'])) ?>" alt="" loading="lazy">
                  <?php if (can('gallery.delete')): ?><label class="pt-check"><input type="checkbox" value="<?= (int) $ph['id'] ?>" data-photo-select aria-label="Select photo"></label><?php endif; ?>
                  <div class="pt-actions">
                    <?php if (can('gallery.edit')): ?><button type="button" class="btn-icon sm" data-set-cover="<?= (int) $ph['id'] ?>" data-album="<?= (int) $album['id'] ?>" title="Use as album cover" aria-label="Use as album cover"><i class="fa-solid fa-star"></i></button><?php endif; ?>
                    <a class="btn-icon sm" href="<?= e(media_url($ph['file_path'])) ?>" target="_blank" rel="noopener" title="Open original" aria-label="Open original"><i class="fa-solid fa-expand"></i></a>
                  </div>
                  <span class="pt-dl" title="Downloads"><i class="fa-solid fa-download"></i> <?= (int) $ph['download_count'] ?></span>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="mt-3"><?= pagination_links($p) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-xl-4">
        <?php if (can('gallery.upload')) uploader_panel($album, $categories, $departments); ?>
        <?php if (can('gallery.edit')): ?>
          <form class="panel mt-4" data-ajax method="post" novalidate>
            <h2 class="panel-title">Album details</h2>
            <?= csrf_field() ?><input type="hidden" name="action" value="update_album"><input type="hidden" name="id" value="<?= (int) $album['id'] ?>">
            <div class="row g-3" data-precision-scope><?php album_fields($album, $categories, $departments); ?>
              <div class="col-12"><label class="form-label" for="album_desc">Note (optional)</label><input class="form-control" id="album_desc" name="description" maxlength="300" value="<?= e($album['description']) ?>"></div></div>
            <button class="btn btn-gold w-100 mt-3" data-loading-text="Saving…"><i class="fa-solid fa-check"></i> Save details</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php
    admin_footer();
    exit;
}

// ---------------------------------------------------------------------
//  Album list
// ---------------------------------------------------------------------
$type = isset(Album::TYPES[$_GET['type'] ?? '']) ? $_GET['type'] : '';
$p = paginate(Album::countAdmin($type), 24);
$albums = Album::adminList($type, $p['per_page'], $p['offset']);

admin_header('Gallery', 'gallery');
?>
<div class="page-head"><div><h1 class="page-title"><i class="fa-solid fa-images"></i> Gallery</h1>
<p class="page-sub">Upload many photos at once, grouped by date or month. Visitors can view and download them.</p></div>
<a class="btn btn-light" href="<?= e(url('gallery.php')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-globe"></i> View gallery</a></div>

<div class="row g-4">
  <div class="col-xl-5 order-xl-2"><?php if (can('gallery.upload')) uploader_panel(null, $categories, $departments); ?></div>
  <div class="col-xl-7 order-xl-1">
    <div class="tab-bar mb-3">
      <a class="tab-link<?= $type === '' ? ' active' : '' ?>" href="?">All albums</a>
      <?php foreach (Album::TYPES as $k => $label): ?><a class="tab-link<?= $type === $k ? ' active' : '' ?>" href="?type=<?= $k ?>"><?= e($label) ?></a><?php endforeach; ?>
    </div>
    <div id="crud-table">
    <?php if (!$albums): echo empty_state('fa-images', 'No albums yet', 'Choose a date and upload your first photos.');
    else: ?>
      <div class="album-grid">
        <?php foreach ($albums as $a): ?>
          <a class="album-card" href="<?= e(url('admin/gallery.php?album=' . $a['id'])) ?>">
            <span class="ac-cover" style="background-image:url('<?= e(media_url($a['cover'], 'assets/images/placeholders/worship.svg')) ?>')">
              <span class="ac-count"><i class="fa-regular fa-images"></i> <?= (int) $a['photo_count'] ?></span>
              <?php if (!$a['is_published']): ?><span class="ac-hidden">Hidden</span><?php endif; ?>
            </span>
            <span class="ac-body"><strong><?= e(Album::label($a)) ?></strong>
              <small><?= e($a['title'] ? Album::dateLabel($a, 'M j, Y') . ' · ' : '') ?><?= e(Album::TYPES[$a['type']]) ?></small></span>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="mt-3"><?= pagination_links($p) ?></div>
    <?php endif; ?>
    </div>
  </div>
</div>
<?php
admin_footer();
