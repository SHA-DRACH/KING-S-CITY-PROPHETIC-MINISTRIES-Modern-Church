<?php
require __DIR__ . '/includes/bootstrap.php';

$hero = HeroVideo::active();
$announcements = Announcement::active(6);
$events = Event::upcoming(3);
$sermon = Sermon::latest();
$pastor = Pastor::primary();
$ministries = Department::publicList();
$testimonies = Testimony::published(6);
$gallery = Gallery::published(null, 6);
$mission = Page::many(['mission'])['mission'] ?? null;

$shortName = setting('church_short_name', "King's City");
$restName = trim(str_ireplace($shortName, '', setting('church_name')));
$poster = media_url($hero['poster'] ?? null, 'assets/images/placeholders/hero-poster.svg');
$mapQuery = rawurlencode(setting('map_query', church_address_line()));

$page = ['nav' => 'home', 'hero' => true, 'libs' => ['swiper', 'glightbox'], 'image' => $hero['poster'] ?? null];
require __DIR__ . '/includes/header.php';
?>

<!-- ================= HERO (background video) ================= -->
<section class="hero" aria-labelledby="heroTitle">
  <div class="hero-media" style="background-image:url('<?= e($poster) ?>')">
    <?php if ($hero && ($hero['video_mp4'] || $hero['video_webm'])): ?>
      <video class="hero-video" autoplay muted loop playsinline preload="none" poster="<?= e($poster) ?>" aria-hidden="true" tabindex="-1"
             data-mobile-src="<?= e($hero['video_mobile'] ? media_url($hero['video_mobile']) : '') ?>">
        <?php if ($hero['video_webm']): ?><source data-src="<?= e(media_url($hero['video_webm'])) ?>" type="video/webm"><?php endif; ?>
        <?php if ($hero['video_mp4']): ?><source data-src="<?= e(media_url($hero['video_mp4'])) ?>" type="video/mp4"><?php endif; ?>
      </video>
    <?php endif; ?>
  </div>
  <div class="hero-overlay" aria-hidden="true"></div>
  <div class="hero-rays" aria-hidden="true"></div>

  <div class="container hero-content">
    <p class="hero-eyebrow" data-aos="fade-down"><?= e(setting('hero_eyebrow', 'Welcome to')) ?></p>
    <h1 id="heroTitle" data-aos="fade-up" data-aos-delay="100"><span class="line-1"><?= e($shortName) ?></span><span class="line-2"><?= e($restName) ?></span></h1>
    <p class="hero-tagline" data-aos="fade-up" data-aos-delay="200"><?= e(setting('church_tagline')) ?></p>
    <p class="hero-motto" data-aos="fade-up" data-aos-delay="250">
      <?php foreach (array_map('trim', explode('•', setting('church_motto'))) as $i => $phrase): ?><?= $i ? '<span class="sep" aria-hidden="true">•</span>' : '' ?><span><?= e($phrase) ?></span><?php endforeach; ?>
    </p>
    <?php if (setting('hero_scripture')): ?>
      <blockquote class="hero-scripture" data-aos="fade-up" data-aos-delay="300">“<?= e(setting('hero_scripture')) ?>” <cite><?= e(setting('hero_scripture_ref')) ?></cite></blockquote>
    <?php endif; ?>
    <div class="hero-actions" data-aos="fade-up" data-aos-delay="350">
      <a class="btn btn-gold btn-lg" href="<?= e($liveUrl) ?>"<?= setting('live_stream_url') ? ' target="_blank" rel="noopener"' : '' ?>><i class="fa-solid fa-circle-play" aria-hidden="true"></i> Watch Live</a>
      <a class="btn btn-outline-light btn-lg" href="#visit"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Plan Your Visit</a>
    </div>
  </div>

  <?php if ($hero && ($hero['video_mp4'] || $hero['video_webm'])): ?>
    <button class="hero-pause" type="button" aria-label="Pause background video" aria-pressed="false"><i class="fa-solid fa-pause" aria-hidden="true"></i></button>
  <?php endif; ?>
  <a class="scroll-indicator" href="#welcome"><span class="mouse" aria-hidden="true"><span></span></span><span class="label">Scroll to explore</span></a>
</section>

<!-- ================= WELCOME ================= -->
<section class="section welcome" id="welcome">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6" data-aos="zoom-in">
        <div class="welcome-media">
          <img src="<?= e(media_url(setting('welcome_image'), 'assets/images/placeholders/worship.svg')) ?>" alt="Congregation worshipping at <?= e(setting('church_name')) ?>" class="main" loading="lazy" width="640" height="480">
          <div class="service-card" data-aos="fade-up" data-aos-delay="200">
            <h3><i class="fa-regular fa-clock" aria-hidden="true"></i> Service Times</h3>
            <ul><?php foreach (array_slice(service_times(), 0, 3) as $s): ?><li><strong><?= e($s['name']) ?></strong><span><?= e($s['day_of_week']) ?> · <?= e(time_range($s['start_time'], $s['end_time'])) ?></span></li><?php endforeach; ?></ul>
          </div>
        </div>
      </div>
      <div class="col-lg-6" data-aos="fade-left">
        <span class="eyebrow">Welcome Home</span>
        <h2 class="display-title"><?= e(setting('welcome_title')) ?></h2>
        <p class="lead-text"><?= e(setting('welcome_text')) ?></p>
        <?php if ($mission): ?>
          <div class="mission-box"><i class="fa-solid fa-dove" aria-hidden="true"></i><div><strong>Our Mission</strong><?= sanitize_html($mission['content']) ?></div></div>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-3 mt-4">
          <a class="btn btn-navy" href="<?= e(url('about.php')) ?>">Learn More About Us</a>
          <a class="btn btn-link-gold" href="<?= e(url('about.php#faith')) ?>">What We Believe <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= QUICK ACTIONS ================= -->
<section class="quick-actions" aria-label="Get connected">
  <div class="container">
    <div class="quick-grid">
      <?php
      $actions = [
          ['Worship With Us', 'Join us this Sunday', 'fa-church', '#visit'],
          ['Grow in the Word', 'Sermons & teachings', 'fa-book-open', url('sermons.php')],
          ['Join Our Ministries', 'Find your place to serve', 'fa-people-group', url('ministries.php')],
          ['Send a Prayer Request', 'We will pray with you', 'fa-hands-praying', url('prayer.php')],
          ['Partner in Giving', 'Support the ministry', 'fa-heart', url('giving.php')],
      ];
      foreach ($actions as $i => [$title, $sub, $icon, $href]): ?>
        <a class="quick-card" href="<?= e($href) ?>" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
          <span class="qc-icon"><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i></span>
          <span class="qc-title"><?= e($title) ?></span><span class="qc-sub"><?= e($sub) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($announcements): ?>
<!-- ================= ANNOUNCEMENTS ================= -->
<section class="section-sm announcements" aria-labelledby="annTitle">
  <div class="container">
    <div class="d-flex align-items-end justify-content-between mb-4" data-aos="fade-down">
      <div><span class="eyebrow">Stay Informed</span><h2 id="annTitle" class="section-title-sm">Announcements</h2></div>
      <div class="swiper-nav"><button class="ann-prev" aria-label="Previous announcement"><i class="fa-solid fa-chevron-left"></i></button><button class="ann-next" aria-label="Next announcement"><i class="fa-solid fa-chevron-right"></i></button></div>
    </div>
    <div class="swiper ann-swiper" data-aos="fade-up">
      <div class="swiper-wrapper">
        <?php foreach ($announcements as $a): ?>
          <article class="swiper-slide announcement-card">
            <span class="ann-icon"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i></span>
            <div><?php if ($a['department_name']): ?><span class="tag"><?= e($a['department_name']) ?></span><?php endif; ?>
              <h3><?= e($a['title']) ?></h3><p><?= e(excerpt($a['description'], 150)) ?></p>
              <?php if ($a['link_url']): ?><a class="link-arrow" href="<?= e($a['link_url']) ?>" rel="noopener">Learn more <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a><?php endif; ?></div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ================= UPCOMING EVENTS ================= -->
<section class="section bg-soft" aria-labelledby="eventsTitle">
  <div class="container">
    <div class="section-head-row">
      <?= section_heading('Join Us', 'Upcoming Events', 'Gatherings, services and programs you will not want to miss.', 'start') ?>
      <a class="btn btn-outline-navy" href="<?= e(url('events.php')) ?>" data-aos="fade-left">View All Events <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    </div>
    <?php if ($events): ?>
      <div class="row g-4">
        <?php foreach ($events as $i => $ev): ?><div class="col-md-6 col-lg-4"><?= event_card($ev, $i * 100) ?></div><?php endforeach; ?>
      </div>
    <?php else: ?>
      <?= empty_state('fa-calendar', 'New events coming soon', 'Check back shortly or follow us on social media for updates.') ?>
    <?php endif; ?>
  </div>
</section>

<?php if ($sermon): $sLink = url('sermon-details.php?slug=' . rawurlencode($sermon['slug'])); ?>
<!-- ================= LATEST SERMON ================= -->
<section class="section latest-sermon" aria-labelledby="sermonTitle">
  <div class="container">
    <?= section_heading('The Word', 'Latest Sermon') ?>
    <div class="feature-sermon" data-aos="fade-up">
      <a class="fs-media" href="<?= e($sLink) ?>" aria-label="<?= e(($sermon['media_type'] === 'audio' ? 'Listen to ' : 'Watch ') . $sermon['title']) ?>">
        <img src="<?= e(Sermon::thumb($sermon)) ?>" alt="" loading="lazy" width="720" height="420">
        <span class="play-btn lg"><i class="fa-solid <?= $sermon['media_type'] === 'audio' ? 'fa-headphones' : 'fa-play' ?>" aria-hidden="true"></i></span>
      </a>
      <div class="fs-body">
        <?php if ($sermon['category_name']): ?><span class="tag tag-gold"><?= e($sermon['category_name']) ?></span><?php endif; ?>
        <h3 id="sermonTitle"><?= e($sermon['title']) ?></h3>
        <p class="meta"><i class="fa-solid fa-user" aria-hidden="true"></i> <?= e($sermon['speaker']) ?> <span aria-hidden="true">·</span> <i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= e(format_date($sermon['sermon_date'], 'F j, Y')) ?></p>
        <?php if ($sermon['scripture']): ?><p class="scripture"><i class="fa-solid fa-book-bible" aria-hidden="true"></i> <?= e($sermon['scripture']) ?></p><?php endif; ?>
        <p><?= e(excerpt($sermon['description'], 220)) ?></p>
        <div class="d-flex flex-wrap gap-3">
          <a class="btn btn-gold" href="<?= e($sLink) ?>"><i class="fa-solid fa-play" aria-hidden="true"></i> <?= $sermon['media_type'] === 'audio' ? 'Listen Now' : 'Watch Sermon' ?></a>
          <a class="btn btn-outline-light" href="<?= e(url('sermons.php')) ?>">Sermon Library</a>
        </div>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($pastor): ?>
<!-- ================= SENIOR PASTOR ================= -->
<section class="section pastor-section" aria-labelledby="pastorTitle">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-5" data-aos="zoom-in">
        <div class="pastor-frame"><img src="<?= e(media_url($pastor['photo'], 'assets/images/placeholders/pastor.svg')) ?>" alt="Portrait of <?= e($pastor['name']) ?>" loading="lazy" width="520" height="620"></div>
      </div>
      <div class="col-lg-7" data-aos="fade-left">
        <span class="eyebrow">Meet Our Senior Pastor</span>
        <h2 id="pastorTitle" class="display-title"><?= e($pastor['name']) ?></h2>
        <p class="pastor-role"><?= e($pastor['position']) ?> · <?= e(setting('church_name')) ?></p>
        <p class="lead-text"><?= e($pastor['short_bio']) ?></p>
        <?php if ($pastor['scripture']): ?><blockquote class="gold-quote">“<?= e($pastor['scripture']) ?>”<cite><?= e($pastor['scripture_ref']) ?></cite></blockquote><?php endif; ?>
        <a class="btn btn-navy" href="<?= e(url('pastor.php')) ?>">Read More <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($ministries): ?>
<!-- ================= MINISTRIES ================= -->
<section class="section bg-soft" aria-labelledby="minTitle">
  <div class="container">
    <div class="section-head-row">
      <div class="section-heading text-start" data-aos="fade-down"><span class="eyebrow">Get Involved</span><h2 id="minTitle">Our Ministries</h2><p>There is a place for everyone to grow, serve and belong.</p></div>
      <div class="swiper-nav" data-aos="fade-left"><button class="min-prev" aria-label="Previous ministries"><i class="fa-solid fa-chevron-left"></i></button><button class="min-next" aria-label="Next ministries"><i class="fa-solid fa-chevron-right"></i></button></div>
    </div>
    <div class="swiper min-swiper" data-aos="fade-up">
      <div class="swiper-wrapper">
        <?php foreach ($ministries as $m): ?>
          <div class="swiper-slide">
            <a class="ministry-card" href="<?= e(url('ministries.php#' . $m['slug'])) ?>">
              <img src="<?= e(media_url($m['image'])) ?>" alt="" loading="lazy" width="400" height="260">
              <div class="mc-body"><span class="mc-icon"><i class="fa-solid <?= e($m['icon'] ?: 'fa-church') ?>" aria-hidden="true"></i></span>
                <h3><?= e($m['name']) ?></h3><p><?= e(excerpt($m['description'], 90)) ?></p></div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ================= PRAYER CTA ================= -->
<section class="prayer-cta" aria-labelledby="prayTitle">
  <div class="container">
    <div class="prayer-cta-inner" data-aos="fade-up">
      <span class="pc-icon" aria-hidden="true"><i class="fa-solid fa-hands-praying"></i></span>
      <div class="flex-grow-1">
        <h2 id="prayTitle">How can we pray for you?</h2>
        <p>Share your request with our prayer team. Every request is kept private unless you choose otherwise — we will stand with you in faith.</p>
      </div>
      <a class="btn btn-gold btn-lg" href="<?= e(url('prayer.php')) ?>">Submit Prayer Request <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    </div>
  </div>
</section>

<?php if ($testimonies): ?>
<!-- ================= TESTIMONIES ================= -->
<section class="section testimonies-section" aria-labelledby="testTitle">
  <div class="container">
    <div class="section-heading text-center" data-aos="fade-down"><span class="eyebrow">God Is Faithful</span><h2 id="testTitle">Testimonies</h2><p>Real stories of what God is doing in our church family.</p></div>
    <div class="swiper testimony-swiper" data-aos="fade-up">
      <div class="swiper-wrapper">
        <?php foreach ($testimonies as $t): ?>
          <figure class="swiper-slide testimony-card">
            <i class="fa-solid fa-quote-left quote-mark" aria-hidden="true"></i>
            <?php if ($t['title']): ?><h3><?= e($t['title']) ?></h3><?php endif; ?>
            <blockquote><?= e(excerpt($t['testimony'], 260)) ?></blockquote>
            <figcaption><?php if ($t['photo']): ?><img src="<?= e(media_url($t['photo'])) ?>" alt="" width="44" height="44" loading="lazy"><?php else: ?><span class="avatar-initials"><?= e(initials($t['name'])) ?></span><?php endif; ?>
              <span><?= e($t['name']) ?></span></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
      <div class="swiper-pagination"></div>
    </div>
    <div class="text-center mt-4"><a class="btn btn-outline-navy" href="<?= e(url('testimonies.php')) ?>">Share Your Testimony</a></div>
  </div>
</section>
<?php endif; ?>

<!-- ================= GIVING CTA ================= -->
<section class="giving-cta" style="--bg:url('<?= e(media_url(setting('giving_image'), 'assets/images/placeholders/prayer.svg')) ?>')" aria-labelledby="giveTitle">
  <div class="container">
    <div class="giving-cta-inner" data-aos="fade-right">
      <span class="eyebrow light">Support the Ministry</span>
      <h2 id="giveTitle">Partner With Us in Building God's Kingdom</h2>
      <p><?= e(setting('giving_intro')) ?></p>
      <?php if (setting('giving_scripture')): ?><p class="scripture-sm">“<?= e(excerpt(setting('giving_scripture'), 120)) ?>” — <?= e(setting('giving_scripture_ref')) ?></p><?php endif; ?>
      <a class="btn btn-gold btn-lg" href="<?= e(url('giving.php')) ?>"><i class="fa-solid fa-heart" aria-hidden="true"></i> Give Now</a>
    </div>
  </div>
</section>

<?php if ($gallery): ?>
<!-- ================= GALLERY PREVIEW ================= -->
<section class="section" aria-labelledby="galTitle">
  <div class="container">
    <div class="section-head-row">
      <?= section_heading('Moments', 'Life at ' . $shortName, 'Glimpses of worship, fellowship and outreach.', 'start') ?>
      <a class="btn btn-outline-navy" href="<?= e(url('gallery.php')) ?>" data-aos="fade-left">Full Gallery <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    </div>
    <div class="gallery-mosaic">
      <?php foreach ($gallery as $i => $g):
          $isVideo = $g['media_type'] === 'video' && $g['video_url']; ?>
        <a class="gm-item gm-<?= $i ?> glightbox" href="<?= e($isVideo ? $g['video_url'] : media_url($g['file_path'])) ?>" data-gallery="home" data-title="<?= e($g['title']) ?>" data-description="<?= e($g['caption']) ?>" data-aos="zoom-in" data-aos-delay="<?= $i * 60 ?>">
          <img src="<?= e(media_url($g['thumb'])) ?>" alt="<?= e($g['title'] ?: 'Church photo') ?>" loading="lazy" width="500" height="400">
          <span class="gm-overlay"><i class="fa-solid <?= $isVideo ? 'fa-play' : 'fa-expand' ?>" aria-hidden="true"></i></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ================= LOCATION ================= -->
<section class="section location bg-soft" id="visit" aria-labelledby="visitTitle">
  <div class="container">
    <div class="row g-4 align-items-stretch">
      <div class="col-lg-5" data-aos="fade-right">
        <div class="visit-card h-100">
          <span class="eyebrow">Plan Your Visit</span>
          <h2 id="visitTitle">We'd Love to See You</h2>
          <ul class="visit-list">
            <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i><div><strong>Location</strong><?= e(setting('address')) ?><br><?= e(setting('city')) ?>, <?= e(setting('country')) ?></div></li>
            <li><i class="fa-solid fa-phone" aria-hidden="true"></i><div><strong>Call Us</strong><a href="tel:<?= e(preg_replace('/[^\d+]/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></div></li>
            <li><i class="fa-regular fa-clock" aria-hidden="true"></i><div><strong>Service Times</strong>
              <?php foreach (service_times() as $s): ?><span class="d-block"><?= e($s['name']) ?> — <?= e($s['day_of_week']) ?>, <?= e(time_range($s['start_time'], $s['end_time'])) ?></span><?php endforeach; ?></div></li>
          </ul>
          <a class="btn btn-gold" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= e($mapQuery) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-diamond-turn-right" aria-hidden="true"></i> Get Directions</a>
        </div>
      </div>
      <div class="col-lg-7" data-aos="fade-left">
        <div class="map-frame">
          <iframe title="Map showing <?= e(setting('church_name')) ?> in <?= e(setting('city')) ?>" src="https://www.google.com/maps?q=<?= e($mapQuery) ?>&amp;output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
