<?php
require __DIR__ . '/includes/bootstrap.php';

$mapQuery = rawurlencode(setting('map_query', church_address_line()));
$subject = mb_substr((string) ($_GET['subject'] ?? ''), 0, 200);

$page = ['title' => 'Contact Us', 'nav' => 'contact', 'description' => 'Contact ' . setting('church_name') . ' — ' . church_address_line() . '. Phone ' . setting('phone') . '.'];
require __DIR__ . '/includes/header.php';
echo page_banner('Contact Us', 'We would love to hear from you.');
?>
<section class="section">
  <div class="container">
    <div class="contact-cards">
      <?php
      $cards = [
          ['fa-location-dot', 'Visit Us', e(setting('address')) . '<br>' . e(setting('city')) . ', ' . e(setting('country'))],
          ['fa-phone', 'Call Us', '<a href="tel:' . e(preg_replace('/[^\d+]/', '', setting('phone'))) . '">' . e(setting('phone')) . '</a>'],
          ['fa-envelope', 'Email Us', '<a href="mailto:' . e(setting('email')) . '">' . e(setting('email')) . '</a>'],
      ];
      if (setting('whatsapp')) {
          $cards[] = ['fa-whatsapp', 'WhatsApp', '<a href="https://wa.me/' . e(preg_replace('/\D/', '', setting('whatsapp'))) . '" target="_blank" rel="noopener">Chat with us</a>'];
      }
      foreach ($cards as $i => [$icon, $title, $html]): ?>
        <div class="contact-card" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>"><i class="fa-<?= $icon === 'fa-whatsapp' ? 'brands' : 'solid' ?> <?= $icon ?>" aria-hidden="true"></i><h2><?= e($title) ?></h2><p><?= $html ?></p></div>
      <?php endforeach; ?>
    </div>

    <div class="row g-5 mt-2">
      <div class="col-lg-7">
        <form class="form-card" method="post" action="<?= e(url('api/contact.php')) ?>" data-public-form novalidate data-aos="fade-right">
          <h2>Send Us a Message</h2>
          <?= csrf_field() ?><?= honeypot_field() ?>
          <div class="row g-3">
            <div class="col-sm-6"><label class="form-label" for="name">Name <span class="text-danger" aria-hidden="true">*</span></label><input class="form-control" id="name" name="name" maxlength="120" required autocomplete="name"><div class="invalid-feedback" data-error-for="name"></div></div>
            <div class="col-sm-6"><label class="form-label" for="email">Email <span class="text-danger" aria-hidden="true">*</span></label><input type="email" class="form-control" id="email" name="email" maxlength="190" required autocomplete="email"><div class="invalid-feedback" data-error-for="email"></div></div>
            <div class="col-sm-6"><label class="form-label" for="phone">Phone</label><input type="tel" class="form-control" id="phone" name="phone" maxlength="40" autocomplete="tel"></div>
            <div class="col-sm-6"><label class="form-label" for="subject">Subject</label><input class="form-control" id="subject" name="subject" maxlength="200" value="<?= e($subject) ?>"></div>
            <div class="col-12"><label class="form-label" for="message">Message <span class="text-danger" aria-hidden="true">*</span></label><textarea class="form-control" id="message" name="message" rows="6" maxlength="5000" required></textarea><div class="invalid-feedback" data-error-for="message"></div></div>
          </div>
          <button class="btn btn-gold btn-lg mt-4" type="submit" data-loading-text="Sending…"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send Message</button>
          <div class="form-success" hidden><i class="fa-solid fa-circle-check" aria-hidden="true"></i><p data-success-text></p></div>
        </form>
      </div>
      <div class="col-lg-5" data-aos="fade-left">
        <div class="side-card navy mb-4"><h2 class="h5"><i class="fa-regular fa-clock" aria-hidden="true"></i> Service Times</h2>
          <ul class="service-list"><?php foreach (service_times() as $s): ?><li><span><?= e($s['name']) ?> <small>(<?= e($s['day_of_week']) ?>)</small></span><span><?= e(time_range($s['start_time'], $s['end_time'])) ?></span></li><?php endforeach; ?></ul></div>
        <div class="map-frame sm"><iframe title="Map to <?= e(setting('church_name')) ?>" src="https://www.google.com/maps?q=<?= e($mapQuery) ?>&amp;output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>
        <div class="mt-3"><?= social_icons('social-icons dark') ?></div>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
