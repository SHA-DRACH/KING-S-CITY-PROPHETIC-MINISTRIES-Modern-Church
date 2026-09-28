<?php
/** Public footer + scripts. */
$footerMinistries = DB::all("SELECT name, slug FROM departments WHERE status = 'active' AND is_public = 1 ORDER BY sort_order LIMIT 6");
?>
</main>
<footer class="site-footer">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-4">
        <a class="brand mb-3" href="<?= e(url()) ?>"><?= logo_img(64) ?>
          <span class="brand-text"><strong><?= e(setting('church_short_name')) ?></strong><small><?= e(trim(str_ireplace(setting('church_short_name'), '', setting('church_name')))) ?></small></span></a>
        <p class="footer-about"><?= e(setting('footer_text')) ?></p>
        <p class="footer-motto"><?= e(setting('church_motto')) ?></p>
        <?= social_icons() ?>
      </div>
      <div class="col-6 col-lg-2">
        <h2 class="footer-title">Explore</h2>
        <ul class="footer-links">
          <li><a href="<?= e(url('about.php')) ?>">About Us</a></li>
          <li><a href="<?= e(url('sermons.php')) ?>">Sermons</a></li>
          <li><a href="<?= e(url('events.php')) ?>">Events</a></li>
          <li><a href="<?= e(url('pastor.php')) ?>">Our Pastor</a></li>
          <li><a href="<?= e(url('gallery.php')) ?>">Gallery</a></li>
          <li><a href="<?= e(url('testimonies.php')) ?>">Testimonies</a></li>
          <li><a href="<?= e(url('giving.php')) ?>">Give</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h2 class="footer-title">Ministries</h2>
        <ul class="footer-links">
          <?php foreach ($footerMinistries as $m): ?><li><a href="<?= e(url('ministries.php#' . $m['slug'])) ?>"><?= e($m['name']) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
      <div class="col-lg-4">
        <h2 class="footer-title">Visit Us</h2>
        <ul class="footer-contact">
          <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span><?= e(setting('address')) ?><br><?= e(setting('city')) ?>, <?= e(setting('country')) ?></span></li>
          <li><i class="fa-solid fa-phone" aria-hidden="true"></i><a href="tel:<?= e(preg_replace('/[^\d+]/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></li>
          <?php if (setting('email')): ?><li><i class="fa-solid fa-envelope" aria-hidden="true"></i><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li><?php endif; ?>
        </ul>
        <h3 class="footer-subtitle">Service Times</h3>
        <ul class="service-list">
          <?php foreach (service_times() as $s): ?>
            <li><span><?= e($s['name']) ?> <small>(<?= e($s['day_of_week']) ?>)</small></span><span><?= e(time_range($s['start_time'], $s['end_time'])) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container d-flex flex-column flex-md-row justify-content-between gap-2">
      <p class="mb-0">&copy; <?= date('Y') ?> <?= e(setting('church_name')) ?>. All rights reserved.</p>
      <ul class="legal-links">
        <li><a href="<?= e(url('privacy.php')) ?>">Privacy Policy</a></li>
        <li><a href="<?= e(url('terms.php')) ?>">Terms</a></li>
        <li><a href="<?= e(url('admin/login.php')) ?>" rel="nofollow">Staff Login</a></li>
      </ul>
    </div>
  </div>
</footer>

<?php if (setting('whatsapp')): ?>
<a class="whatsapp-float" href="https://wa.me/<?= e(preg_replace('/\D/', '', setting('whatsapp'))) ?>" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></a>
<?php endif; ?>
<button class="back-to-top" type="button" aria-label="Back to top"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastStack" aria-live="polite" aria-atomic="true"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js" defer></script>
<?php if (in_array('swiper', $page['libs'], true)): ?><script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script><?php endif; ?>
<?php if (in_array('glightbox', $page['libs'], true)): ?><script src="https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/js/glightbox.min.js" defer></script><?php endif; ?>
<script src="<?= e(asset('js/main.js')) ?>" defer></script>
</body>
</html>
