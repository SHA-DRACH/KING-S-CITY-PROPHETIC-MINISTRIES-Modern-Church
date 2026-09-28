<?php
require __DIR__ . '/includes/bootstrap.php';

$wall = setting('prayer_wall_enabled', '1') === '1' ? PrayerRequest::publicWall(6) : [];

$page = ['title' => 'Prayer Request', 'nav' => 'prayer', 'description' => 'Send a prayer request to the prayer team of ' . setting('church_name') . '. Your request stays private unless you choose to share it.'];
require __DIR__ . '/includes/header.php';
echo page_banner('Prayer Request', 'How can we pray for you?');
?>
<section class="section">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-5" data-aos="fade-right">
        <span class="eyebrow">We Stand With You</span>
        <h2 class="display-title">You Don't Have to Carry It Alone</h2>
        <p class="lead-text">Whatever you are facing — sickness, family, finances, direction or spiritual battles — our intercessors will lift your request before the Lord.</p>
        <blockquote class="gold-quote">“The effective, fervent prayer of a righteous man avails much.”<cite>James 5:16</cite></blockquote>
        <ul class="assurance-list">
          <li><i class="fa-solid fa-lock" aria-hidden="true"></i> Requests are <strong>private by default</strong> and seen only by authorised prayer staff.</li>
          <li><i class="fa-solid fa-globe" aria-hidden="true"></i> Only if you choose “share publicly” will your first name and request appear on the prayer wall.</li>
          <li><i class="fa-solid fa-phone" aria-hidden="true"></i> Urgent? Call <a href="tel:<?= e(preg_replace('/[^\d+]/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a>.</li>
        </ul>
      </div>
      <div class="col-lg-7">
        <form class="form-card" method="post" action="<?= e(url('api/prayer.php')) ?>" data-public-form novalidate data-aos="fade-left">
          <h2>Submit Your Prayer Request</h2>
          <?= csrf_field() ?><?= honeypot_field() ?>
          <div class="row g-3">
            <div class="col-12"><label class="form-label" for="name">Your Name <span class="text-danger" aria-hidden="true">*</span></label><input class="form-control" id="name" name="name" maxlength="120" required autocomplete="name" value="<?= e(old('name')) ?>"><div class="invalid-feedback" data-error-for="name"></div></div>
            <div class="col-sm-6"><label class="form-label" for="email">Email</label><input type="email" class="form-control" id="email" name="email" maxlength="190" autocomplete="email" value="<?= e(old('email')) ?>"><div class="invalid-feedback" data-error-for="email"></div></div>
            <div class="col-sm-6"><label class="form-label" for="phone">Phone</label><input type="tel" class="form-control" id="phone" name="phone" maxlength="40" autocomplete="tel" value="<?= e(old('phone')) ?>"></div>
            <div class="col-12"><label class="form-label" for="request">Prayer Request <span class="text-danger" aria-hidden="true">*</span></label><textarea class="form-control" id="request" name="request" rows="6" maxlength="3000" required><?= e(old('request')) ?></textarea><div class="invalid-feedback" data-error-for="request"></div></div>
            <div class="col-12"><fieldset><legend class="form-label">Visibility</legend>
              <div class="choice-grid two">
                <label class="choice"><input type="radio" name="visibility" value="private" checked><span><i class="fa-solid fa-lock" aria-hidden="true"></i> Keep private <small>Prayer team only</small></span></label>
                <label class="choice"><input type="radio" name="visibility" value="public"><span><i class="fa-solid fa-globe" aria-hidden="true"></i> Share publicly <small>First name on prayer wall</small></span></label>
              </div></fieldset></div>
          </div>
          <button class="btn btn-gold btn-lg w-100 mt-4" type="submit" data-loading-text="Sending…"><i class="fa-solid fa-hands-praying" aria-hidden="true"></i> Submit Prayer Request</button>
          <div class="form-success" hidden><i class="fa-solid fa-circle-check" aria-hidden="true"></i><p data-success-text></p></div>
        </form>
      </div>
    </div>
  </div>
</section>

<?php if ($wall): ?>
<section class="section bg-soft">
  <div class="container">
    <?= section_heading('Prayer Wall', 'Pray With Us', 'Requests shared publicly by members of our community.') ?>
    <div class="row g-4">
      <?php foreach ($wall as $i => $w): ?>
        <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
          <div class="wall-card h-100"><div class="d-flex justify-content-between"><strong><?= e($w['name']) ?></strong><?php if ($w['status'] === 'answered'): ?><span class="badge text-bg-success">Answered 🙌</span><?php endif; ?></div>
            <p><?= e(excerpt($w['request'], 220)) ?></p><small class="text-muted"><?= e(time_ago($w['created_at'])) ?></small></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
