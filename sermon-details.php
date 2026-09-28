<?php
require __DIR__ . '/includes/bootstrap.php';

$sermon = Sermon::bySlug((string) ($_GET['slug'] ?? '')) ?? abort(404, 'This sermon could not be found.');
Sermon::incrementViews((int) $sermon['id']);
$related = Sermon::related($sermon);
$embed = video_embed_url($sermon['video_url']);
$thumb = Sermon::thumb($sermon);

$schema = [
    '@context' => 'https://schema.org',
    '@type' => $sermon['media_type'] === 'audio' ? 'AudioObject' : 'VideoObject',
    'name' => $sermon['title'],
    'description' => excerpt($sermon['description'], 300) ?: $sermon['title'],
    'thumbnailUrl' => $thumb,
    'uploadDate' => $sermon['sermon_date'],
    'author' => ['@type' => 'Person', 'name' => $sermon['speaker']],
    'publisher' => ['@type' => 'Church', 'name' => setting('church_name')],
];
if ($embed) {
    $schema['embedUrl'] = $embed;
}

$page = ['title' => $sermon['title'], 'nav' => 'sermons', 'type' => 'article', 'description' => $sermon['description'], 'image' => $sermon['thumbnail'], 'schema' => [$schema]];
require __DIR__ . '/includes/header.php';
echo page_banner($sermon['title'], $sermon['speaker'] . ' · ' . format_date($sermon['sermon_date'], 'F j, Y'), $sermon['thumbnail'] ?: 'assets/images/placeholders/sermon.svg', ['Sermons' => 'sermons.php']);
?>
<section class="section">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-8">
        <div class="player-card" data-aos="fade-up">
          <?php if ($embed): ?>
            <div class="ratio ratio-16x9"><iframe src="<?= e($embed) ?>" title="<?= e($sermon['title']) ?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>
          <?php elseif ($sermon['video_file']): ?>
            <video controls preload="metadata" poster="<?= e($thumb) ?>" class="w-100"><source src="<?= e(media_url($sermon['video_file'])) ?>" type="video/<?= str_ends_with($sermon['video_file'], '.webm') ? 'webm' : 'mp4' ?>">Your browser does not support video playback.</video>
          <?php else: ?>
            <div class="audio-cover" style="--cover:url('<?= e($thumb) ?>')"><div><i class="fa-solid <?= $sermon['audio_file'] ? 'fa-headphones' : 'fa-hourglass-half' ?>" aria-hidden="true"></i><p><?= $sermon['audio_file'] ? 'Audio Sermon' : 'Media will be available soon' ?></p></div></div>
          <?php endif; ?>
          <?php if ($sermon['audio_file']): ?>
            <div class="audio-player"><audio controls preload="none" class="w-100"><source src="<?= e(media_url($sermon['audio_file'])) ?>" type="<?= str_ends_with($sermon['audio_file'], '.m4a') ? 'audio/mp4' : 'audio/mpeg' ?>">Your browser does not support audio playback.</audio></div>
          <?php endif; ?>
        </div>

        <article class="sermon-article" data-aos="fade-up">
          <div class="d-flex flex-wrap gap-2 mb-3">
            <?php if ($sermon['category_name']): ?><a class="tag tag-gold" href="<?= e(url('sermons.php?filter=' . rawurlencode($sermon['category_slug']))) ?>"><?= e($sermon['category_name']) ?></a><?php endif; ?>
            <span class="tag"><i class="fa-solid <?= $sermon['media_type'] === 'audio' ? 'fa-headphones' : 'fa-video' ?>" aria-hidden="true"></i> <?= e(ucfirst($sermon['media_type'])) ?></span>
          </div>
          <h2><?= e($sermon['title']) ?></h2>
          <p class="meta"><i class="fa-solid fa-user" aria-hidden="true"></i> <?= e($sermon['speaker']) ?> · <i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= e(format_date($sermon['sermon_date'], 'l, F j, Y')) ?></p>
          <?php if ($sermon['scripture']): ?><div class="scripture-box"><i class="fa-solid fa-book-bible" aria-hidden="true"></i><div><strong>Scripture</strong><?= e($sermon['scripture']) ?></div></div><?php endif; ?>
          <div class="rich-text"><?= paragraphs($sermon['description']) ?></div>
          <div class="d-flex flex-wrap gap-3 mt-4">
            <?php if ($sermon['allow_download'] && $sermon['audio_file']): ?><a class="btn btn-gold" href="<?= e(media_url($sermon['audio_file'])) ?>" download><i class="fa-solid fa-download" aria-hidden="true"></i> Download Audio</a><?php endif; ?>
            <?php if ($sermon['allow_download'] && $sermon['video_file']): ?><a class="btn btn-outline-navy" href="<?= e(media_url($sermon['video_file'])) ?>" download><i class="fa-solid fa-download" aria-hidden="true"></i> Download Video</a><?php endif; ?>
            <button class="btn btn-light" type="button" data-share data-title="<?= e($sermon['title']) ?>"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Share</button>
          </div>
        </article>
      </div>

      <aside class="col-lg-4">
        <div class="side-card" data-aos="fade-left">
          <h2 class="h5">Related Sermons</h2>
          <?php foreach ($related as $r): ?>
            <a class="related-item" href="<?= e(url('sermon-details.php?slug=' . rawurlencode($r['slug']))) ?>">
              <img src="<?= e(Sermon::thumb($r)) ?>" alt="" loading="lazy" width="96" height="64">
              <span><strong><?= e($r['title']) ?></strong><small><?= e($r['speaker']) ?> · <?= e(format_date($r['sermon_date'])) ?></small></span>
            </a>
          <?php endforeach; ?>
          <a class="btn btn-outline-navy w-100 mt-3" href="<?= e(url('sermons.php')) ?>">Browse All Sermons</a>
        </div>
        <div class="side-card navy mt-4" data-aos="fade-left" data-aos-delay="100">
          <h2 class="h5">Need Prayer?</h2><p>Our prayer team would love to stand with you.</p>
          <a class="btn btn-gold w-100" href="<?= e(url('prayer.php')) ?>">Send a Prayer Request</a>
        </div>
      </aside>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
