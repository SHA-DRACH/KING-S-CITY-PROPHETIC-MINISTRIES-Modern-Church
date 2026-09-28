<?php
require __DIR__ . '/includes/bootstrap.php';

$categories = array_filter(Gallery::categories(), fn($c) => $c['items'] > 0);
$slugs = array_column($categories, 'slug');
$cat = in_array($_GET['category'] ?? '', $slugs, true) ? $_GET['category'] : null;
$p = paginate(Gallery::countPublished($cat), 24);
$items = Gallery::published($cat, $p['per_page'], $p['offset']);

$page = ['title' => 'Gallery', 'nav' => 'gallery', 'libs' => ['glightbox'], 'description' => 'Photos and videos from worship services, conferences, Praise Fest, youth programs and outreach at ' . setting('church_name') . '.'];
require __DIR__ . '/includes/header.php';
echo page_banner('Gallery', 'Moments of worship, fellowship and outreach.');
?>
<section class="section">
  <div class="container">
    <div class="filter-pills justify-content-center mb-5" role="group" aria-label="Gallery categories" data-aos="fade-up">
      <a class="pill<?= !$cat ? ' active' : '' ?>" href="<?= e(url('gallery.php')) ?>">All</a>
      <?php foreach ($categories as $c): ?><a class="pill<?= $cat === $c['slug'] ? ' active' : '' ?>" href="?category=<?= e($c['slug']) ?>"><?= e($c['name']) ?> <small><?= (int) $c['items'] ?></small></a><?php endforeach; ?>
    </div>
    <?php if (!$items): echo empty_state('fa-images', 'No photos yet', 'Check back soon for photos from our services and events.');
    else: ?>
    <div class="gallery-grid">
      <?php foreach ($items as $i => $g): $isVideo = $g['media_type'] === 'video' && $g['video_url']; ?>
        <a class="gallery-item glightbox" href="<?= e($isVideo ? $g['video_url'] : media_url($g['file_path'])) ?>" data-gallery="gallery" data-title="<?= e($g['title']) ?>" data-description="<?= e($g['caption']) ?>" data-aos="zoom-in" data-aos-delay="<?= ($i % 4) * 60 ?>">
          <img src="<?= e(media_url($g['file_path'])) ?>" alt="<?= e($g['title']) ?>" loading="lazy" width="400" height="300">
          <span class="gm-overlay"><i class="fa-solid <?= $isVideo ? 'fa-play' : 'fa-expand' ?>" aria-hidden="true"></i><span><?= e($g['title']) ?></span></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?= pagination_links($p, 'mt-5 d-flex justify-content-center') ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
