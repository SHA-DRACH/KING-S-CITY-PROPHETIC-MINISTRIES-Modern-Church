<?php
require __DIR__ . '/includes/bootstrap.php';

$pastor = Pastor::primary() ?? abort(404, 'Pastor information is not available yet.');
$photos = Gallery::published('pastors-ministry', 6);
$sermons = DB::all("SELECT s.*, c.name AS category_name FROM sermons s LEFT JOIN sermon_categories c ON c.id = s.category_id
    WHERE s.status = 'published' AND s.speaker = ? ORDER BY s.sermon_date DESC LIMIT 3", [$pastor['name']]);

$schema = [[
    '@context' => 'https://schema.org', '@type' => 'Person', 'name' => $pastor['name'], 'jobTitle' => $pastor['position'],
    'image' => media_url($pastor['photo'], 'assets/images/placeholders/pastor.svg'), 'description' => $pastor['short_bio'],
    'worksFor' => ['@type' => 'Church', 'name' => setting('church_name')],
]];
$page = ['title' => $pastor['name'], 'nav' => 'pastor', 'description' => $pastor['short_bio'], 'image' => $pastor['photo'], 'schema' => $schema, 'libs' => ['glightbox']];
require __DIR__ . '/includes/header.php';
?>
<?php $pastorHero = HeroVideo::active('pastor'); ?>
<section class="pastor-hero<?= HeroVideo::hasVideo($pastorHero) ? ' has-video' : '' ?>"><?= bg_video_tag($pastorHero) ?>
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-5" data-aos="zoom-in"><div class="pastor-frame large"><img src="<?= e(media_url($pastor['photo'], 'assets/images/placeholders/pastor.svg')) ?>" alt="Portrait of <?= e($pastor['name']) ?>" width="520" height="620"></div></div>
      <div class="col-lg-7" data-aos="fade-left">
        <nav aria-label="Breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url()) ?>">Home</a></li><li class="breadcrumb-item active" aria-current="page">Our Pastor</li></ol></nav>
        <span class="eyebrow light"><?= e($pastor['position']) ?></span>
        <h1><?= e($pastor['name']) ?></h1>
        <p class="church"><?= e(setting('church_name')) ?></p>
        <?php if ($pastor['vision']): ?><blockquote class="pastor-quote">“<?= e($pastor['vision']) ?>”</blockquote><?php endif; ?>
        <div class="d-flex flex-wrap gap-3">
          <a class="btn btn-gold" href="#contact-pastor"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Contact Pastor</a>
          <a class="btn btn-outline-light" href="<?= e(url('sermons.php')) ?>">Sermons</a>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-8">
        <div class="content-block" data-aos="fade-up"><span class="eyebrow">Biography</span><h2>About <?= e($pastor['name']) ?></h2><div class="rich-text"><?= paragraphs($pastor['biography'] ?: $pastor['short_bio']) ?></div></div>
        <?php if ($pastor['ministry_journey']): ?><div class="content-block" data-aos="fade-up"><span class="eyebrow">Ministry Journey</span><h2>Called to Serve</h2><div class="rich-text"><?= paragraphs($pastor['ministry_journey']) ?></div></div><?php endif; ?>
        <?php if ($pastor['message']): ?>
          <div class="message-card" data-aos="fade-up"><i class="fa-solid fa-feather-pointed" aria-hidden="true"></i><h2>A Message from the Pastor</h2><div class="rich-text"><?= paragraphs($pastor['message']) ?></div><p class="signature">— <?= e($pastor['name']) ?></p></div>
        <?php endif; ?>
      </div>
      <aside class="col-lg-4">
        <?php if ($pastor['scripture']): ?><div class="side-card navy scripture-card" data-aos="fade-left"><i class="fa-solid fa-book-bible" aria-hidden="true"></i><p>“<?= e($pastor['scripture']) ?>”</p><cite><?= e($pastor['scripture_ref']) ?></cite></div><?php endif; ?>
        <?php if ($pastor['vision']): ?><div class="side-card mt-4" data-aos="fade-left"><h2 class="h5"><i class="fa-solid fa-eye text-gold" aria-hidden="true"></i> Vision</h2><p class="mb-0"><?= e($pastor['vision']) ?></p></div><?php endif; ?>
        <div class="side-card mt-4" id="contact-pastor" data-aos="fade-left">
          <h2 class="h5">Contact the Pastor's Office</h2>
          <ul class="contact-mini">
            <?php if ($pastor['phone']): ?><li><i class="fa-solid fa-phone" aria-hidden="true"></i><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $pastor['phone'])) ?>"><?= e($pastor['phone']) ?></a></li><?php endif; ?>
            <?php if ($pastor['email']): ?><li><i class="fa-solid fa-envelope" aria-hidden="true"></i><a href="mailto:<?= e($pastor['email']) ?>"><?= e($pastor['email']) ?></a></li><?php endif; ?>
            <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span><?= e(church_address_line()) ?></span></li>
          </ul>
          <a class="btn btn-navy w-100" href="<?= e(url('contact.php?subject=' . rawurlencode('Message for ' . $pastor['name']))) ?>">Send a Message</a>
        </div>
      </aside>
    </div>
  </div>
</section>

<?php if ($sermons): ?>
<section class="section bg-soft">
  <div class="container">
    <?= section_heading('Recent Messages', 'Sermons by ' . $pastor['name']) ?>
    <div class="row g-4"><?php foreach ($sermons as $i => $s): ?><div class="col-md-6 col-lg-4"><?= sermon_card($s, $i * 100) ?></div><?php endforeach; ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($photos): ?>
<section class="section">
  <div class="container">
    <?= section_heading('Gallery', 'In Ministry') ?>
    <div class="gallery-grid">
      <?php foreach ($photos as $i => $g): ?>
        <a class="gallery-item glightbox" href="<?= e(media_url($g['file_path'])) ?>" data-gallery="pastor" data-title="<?= e($g['title']) ?>" data-aos="zoom-in" data-aos-delay="<?= $i * 60 ?>"><img src="<?= e(media_url($g['thumb'])) ?>" alt="<?= e($g['title'] ?: 'Ministry photo') ?>" loading="lazy" width="400" height="300"><span class="gm-overlay"><i class="fa-solid fa-expand" aria-hidden="true"></i></span></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
