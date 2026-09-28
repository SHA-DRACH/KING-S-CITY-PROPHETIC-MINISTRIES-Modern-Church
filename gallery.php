<?php
require __DIR__ . '/includes/bootstrap.php';

$tab = ($_GET['tab'] ?? 'downloads') === 'event' ? 'event' : 'downloads';
$albumId = query_int('album');

// ---------------------------------------------------------------------
//  Single album: medium thumbnails, each downloadable
// ---------------------------------------------------------------------
if ($albumId) {
    $album = Album::findPublic($albumId) ?? abort(404, 'This album could not be found.');
    $tab = $album['type'];
    $p = paginate(Album::countPhotos($albumId), 60);
    $photos = Album::photos($albumId, true, $p['per_page'], $p['offset']);
    $label = Album::label($album);

    $page = ['title' => $label . ' — Photos', 'nav' => 'gallery', 'libs' => ['glightbox'], 'image' => $album['cover'],
             'description' => 'Photos from ' . Album::dateLabel($album) . ' at ' . setting('church_name') . '. View and download your pictures.'];
    require __DIR__ . '/includes/header.php';
    echo page_banner($label, Album::dateLabel($album) . ' · ' . $p['total'] . ' photos', $album['cover'] ?: 'assets/images/placeholders/worship.svg',
        ['Gallery' => 'gallery.php', Album::TYPES[$album['type']] => 'gallery.php?tab=' . $album['type']], 'gallery');
    ?>
    <section class="section-sm">
      <div class="container">
        <div class="album-bar" data-aos="fade-up">
          <a class="btn btn-light" href="<?= e(url('gallery.php?tab=' . $album['type'])) ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All albums</a>
          <p class="mb-0 text-muted"><?php if ($album['allow_download']): ?><i class="fa-solid fa-circle-info text-gold" aria-hidden="true"></i> Tap <i class="fa-solid fa-download" aria-hidden="true"></i> on any photo to save it in full quality.<?php endif; ?><?= $album['description'] ? ' ' . e($album['description']) : '' ?></p>
          <?php if ($album['allow_download'] && $p['total'] > 1 && class_exists('ZipArchive')): ?>
            <a class="btn btn-gold" href="<?= e(url('download.php?album=' . $album['id'])) ?>" rel="nofollow"><i class="fa-solid fa-file-zipper" aria-hidden="true"></i> Download all (<?= (int) $p['total'] ?>)</a>
          <?php endif; ?>
        </div>

        <?php if (!$photos): echo empty_state('fa-images', 'No photos yet', 'Photos will appear here soon.');
        else: ?>
          <div class="photo-wall">
            <?php foreach ($photos as $i => $ph):
                $isVideo = $ph['media_type'] === 'video' && $ph['video_url'];
                $dl = url('download.php?photo=' . $ph['id']);
                $desc = $album['allow_download'] && !$isVideo ? '<a class="gl-download" href="' . e($dl) . '" rel="nofollow">⬇ Download this photo</a>' : ''; ?>
              <figure class="photo-card">
                <a class="glightbox" href="<?= e($isVideo ? $ph['video_url'] : media_url($ph['file_path'])) ?>" data-gallery="album" data-description="<?= e($desc) ?>" aria-label="View photo <?= $p['offset'] + $i + 1 ?>">
                  <img src="<?= e(media_url($ph['thumb_path'] ?: $ph['file_path'])) ?>" alt="Photo <?= $p['offset'] + $i + 1 ?> from <?= e($label) ?>" loading="lazy" width="360" height="360">
                  <span class="pc-zoom" aria-hidden="true"><i class="fa-solid <?= $isVideo ? 'fa-play' : 'fa-expand' ?>"></i></span>
                </a>
                <?php if ($album['allow_download'] && !$isVideo): ?>
                  <a class="pc-download" href="<?= e($dl) ?>" rel="nofollow" aria-label="Download photo <?= $p['offset'] + $i + 1 ?>" title="Download"><i class="fa-solid fa-download" aria-hidden="true"></i></a>
                <?php endif; ?>
              </figure>
            <?php endforeach; ?>
          </div>
          <?= pagination_links($p, 'mt-5 d-flex justify-content-center') ?>
        <?php endif; ?>
      </div>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// ---------------------------------------------------------------------
//  Album lists: Photo Downloads (by month) and Event Gallery (by category)
// ---------------------------------------------------------------------
$months = Album::months('downloads');
$month = isset($months[$_GET['month'] ?? '']) ? $_GET['month'] : null;
$categories = array_filter(Gallery::categories(), fn($c) => $c['items'] > 0);
$cat = in_array($_GET['category'] ?? '', array_column($categories, 'slug'), true) ? $_GET['category'] : null;

$p = paginate(Album::countPublic($tab, $tab === 'downloads' ? $month : null, $tab === 'event' ? $cat : null), 18);
$albums = Album::publicList($tab, $tab === 'downloads' ? $month : null, $tab === 'event' ? $cat : null, $p['per_page'], $p['offset']);

$page = ['title' => 'Gallery & Photo Downloads', 'nav' => 'gallery', 'description' => 'Find and download your photos from services and events at ' . setting('church_name') . ', and browse moments of worship, fellowship and outreach.'];
require __DIR__ . '/includes/header.php';
echo page_banner('Gallery', 'Find your photos and relive every moment.', 'assets/images/placeholders/worship.svg', [], 'gallery');
?>
<section class="section">
  <div class="container">
    <div class="gallery-tabs" role="tablist" aria-label="Gallery sections" data-aos="fade-up">
      <a role="tab" class="<?= $tab === 'downloads' ? 'active' : '' ?>" aria-selected="<?= $tab === 'downloads' ? 'true' : 'false' ?>" href="?tab=downloads">
        <i class="fa-solid fa-download" aria-hidden="true"></i><span><strong>Download Your Photos</strong><small>Find the service you attended</small></span></a>
      <a role="tab" class="<?= $tab === 'event' ? 'active' : '' ?>" aria-selected="<?= $tab === 'event' ? 'true' : 'false' ?>" href="?tab=event">
        <i class="fa-solid fa-images" aria-hidden="true"></i><span><strong>Event Gallery</strong><small>Moments from church life</small></span></a>
    </div>

    <?php if ($tab === 'downloads'): ?>
      <?php if ($months): ?>
        <div class="filter-pills month-pills mb-4" role="group" aria-label="Filter by month" data-aos="fade-up">
          <a class="pill<?= !$month ? ' active' : '' ?>" href="?tab=downloads">All dates</a>
          <?php foreach ($months as $ym => $label): ?><a class="pill<?= $month === $ym ? ' active' : '' ?>" href="?tab=downloads&amp;month=<?= e($ym) ?>"><?= e($label) ?></a><?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php elseif ($categories): ?>
      <div class="filter-pills mb-4" role="group" aria-label="Filter by category" data-aos="fade-up">
        <a class="pill<?= !$cat ? ' active' : '' ?>" href="?tab=event">All</a>
        <?php foreach ($categories as $c): ?><a class="pill<?= $cat === $c['slug'] ? ' active' : '' ?>" href="?tab=event&amp;category=<?= e($c['slug']) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$albums): echo empty_state('fa-images', $tab === 'downloads' ? 'No photos to download yet' : 'No albums yet', 'Photos from our services and events will appear here.');
    else: ?>
      <div class="album-list">
        <?php foreach ($albums as $i => $a): ?>
          <a class="public-album" href="<?= e(url('gallery.php?album=' . $a['id'])) ?>" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 70 ?>">
            <span class="pa-cover"><img src="<?= e(media_url($a['cover'], 'assets/images/placeholders/worship.svg')) ?>" alt="" loading="lazy" width="480" height="340">
              <span class="pa-count"><i class="fa-regular fa-images" aria-hidden="true"></i> <?= (int) $a['photo_count'] ?></span>
              <?php if ($a['date_precision'] === 'day'): ?><span class="pa-date"><?= date_chip($a['album_date']) ?></span><?php endif; ?></span>
            <span class="pa-body">
              <strong><?= e(Album::label($a)) ?></strong>
              <small><?= e($a['title'] ? Album::dateLabel($a) : ($a['category_name'] ?: Album::TYPES[$a['type']])) ?></small>
              <span class="pa-cta"><?= $a['allow_download'] ? '<i class="fa-solid fa-download" aria-hidden="true"></i> View &amp; download' : '<i class="fa-solid fa-eye" aria-hidden="true"></i> View photos' ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
      <?= pagination_links($p, 'mt-5 d-flex justify-content-center') ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
