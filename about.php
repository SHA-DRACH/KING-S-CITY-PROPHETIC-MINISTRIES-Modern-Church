<?php
require __DIR__ . '/includes/bootstrap.php';

$blocks = Page::many(['our-story', 'vision', 'mission', 'statement-of-faith', 'values']);
$leaders = DB::all('SELECT * FROM leaders WHERE is_active = 1 ORDER BY sort_order, name');
$mapQuery = rawurlencode(setting('map_query', church_address_line()));

$page = ['title' => 'About Us', 'nav' => 'about', 'description' => $blocks['our-story']['meta_description'] ?? null];
require __DIR__ . '/includes/header.php';
echo page_banner('About Us', setting('church_tagline') . ' · ' . setting('city'), 'assets/images/placeholders/hero-poster.svg', [], 'about');
?>

<section class="section">
  <div class="container">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6" data-aos="fade-right">
        <span class="eyebrow">Our Story</span>
        <h2 class="display-title"><?= e($blocks['our-story']['title'] ?? 'Our Story') ?></h2>
        <div class="rich-text"><?= sanitize_html($blocks['our-story']['content'] ?? '') ?></div>
      </div>
      <div class="col-lg-6" data-aos="zoom-in">
        <div class="stacked-images">
          <img src="<?= e(media_url(setting('welcome_image'), 'assets/images/placeholders/worship.svg')) ?>" alt="Worship service at <?= e(setting('church_name')) ?>" loading="lazy" width="560" height="420">
          <img src="<?= e(url('assets/images/placeholders/prayer.svg')) ?>" alt="" class="small-img" loading="lazy" width="300" height="220">
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section bg-navy">
  <div class="container">
    <div class="row g-4">
      <?php foreach ([['vision', 'fa-eye', 'Our Vision'], ['mission', 'fa-bullseye', 'Our Mission']] as $i => [$slug, $icon, $label]): ?>
        <div class="col-md-6" data-aos="<?= $i ? 'fade-left' : 'fade-right' ?>">
          <div class="vm-card h-100"><span class="vm-icon"><i class="fa-solid <?= $icon ?>" aria-hidden="true"></i></span>
            <h2><?= e($label) ?></h2><div class="rich-text"><?= sanitize_html($blocks[$slug]['content'] ?? '') ?></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="faith">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-6" data-aos="fade-up">
        <span class="eyebrow">What We Believe</span>
        <h2 class="display-title"><?= e($blocks['statement-of-faith']['title'] ?? 'Statement of Faith') ?></h2>
        <div class="rich-text check-list"><?= sanitize_html($blocks['statement-of-faith']['content'] ?? '') ?></div>
      </div>
      <div class="col-lg-6" data-aos="fade-up" data-aos-delay="150">
        <span class="eyebrow">How We Live</span>
        <h2 class="display-title"><?= e($blocks['values']['title'] ?? 'Our Values') ?></h2>
        <div class="rich-text values-list"><?= sanitize_html($blocks['values']['content'] ?? '') ?></div>
      </div>
    </div>
  </div>
</section>

<?php if ($leaders): ?>
<section class="section bg-soft">
  <div class="container">
    <?= section_heading('Leadership', 'Those Who Serve Us', 'Faithful men and women leading the ministry.') ?>
    <div class="row g-4 justify-content-center">
      <?php foreach ($leaders as $i => $l): ?>
        <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
          <div class="leader-card">
            <?php if ($l['photo']): ?><img src="<?= e(media_url($l['photo'])) ?>" alt="Portrait of <?= e($l['name']) ?>" loading="lazy" width="300" height="300">
            <?php else: ?><div class="leader-initials" aria-hidden="true"><?= e(initials($l['name'])) ?></div><?php endif; ?>
            <h3><?= e($l['name']) ?></h3><p class="role"><?= e($l['position']) ?></p>
            <?php if ($l['bio']): ?><p class="small"><?= e($l['bio']) ?></p><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="row g-4 align-items-center">
      <div class="col-lg-5" data-aos="fade-right">
        <span class="eyebrow">Church Location</span>
        <h2 class="display-title">Find Us</h2>
        <p class="lead-text"><?= e(church_address_line()) ?></p>
        <a class="btn btn-gold" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= e($mapQuery) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-diamond-turn-right" aria-hidden="true"></i> Get Directions</a>
      </div>
      <div class="col-lg-7" data-aos="fade-left"><div class="map-frame"><iframe title="Church location map" src="https://www.google.com/maps?q=<?= e($mapQuery) ?>&amp;output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div></div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
