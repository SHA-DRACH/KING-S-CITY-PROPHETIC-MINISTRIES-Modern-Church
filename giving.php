<?php
require __DIR__ . '/includes/bootstrap.php';

$categories = Giving::categories();
$methods = Giving::methods();
$currencies = array_map('trim', explode(',', setting('giving_currencies', 'USD')));
$typeLabels = ['mobile_money' => 'Mobile Money', 'bank_transfer' => 'Bank Transfer', 'online_gateway' => 'Online Payment', 'cash' => 'In Person', 'other' => 'Other'];

$page = ['title' => 'Give', 'nav' => 'give', 'description' => setting('giving_intro')];
require __DIR__ . '/includes/header.php';
echo page_banner('Give', setting('giving_intro', 'Your generosity changes lives.'), setting('giving_image') ?: 'assets/images/placeholders/prayer.svg');
?>
<section class="section">
  <div class="container">
    <?php if ($categories): ?>
    <div class="giving-types" data-aos="fade-up">
      <?php foreach ($categories as $c): ?>
        <div class="gt-card"><i class="fa-solid <?= e($c['icon'] ?: 'fa-hand-holding-heart') ?>" aria-hidden="true"></i><h2><?= e($c['name']) ?></h2><p><?= e($c['description']) ?></p></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="row g-5 mt-2">
      <div class="col-lg-5">
        <span class="eyebrow" data-aos="fade-down">Ways to Give</span>
        <h2 class="display-title" data-aos="fade-down">Choose How You Give</h2>
        <?php if (!$methods): echo empty_state('fa-wallet', 'Giving options coming soon', 'Please give in person during any of our services.');
        else: foreach ($methods as $i => $m): ?>
          <div class="method-card" data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
            <span class="mc-icon"><i class="fa-solid <?= e($m['icon'] ?: 'fa-wallet') ?>" aria-hidden="true"></i></span>
            <div>
              <h3><?= e($m['name']) ?> <small><?= e($typeLabels[$m['type']] ?? '') ?></small></h3>
              <?php if ($m['provider']): ?><p class="mb-1"><strong><?= e($m['provider']) ?></strong></p><?php endif; ?>
              <?php if ($m['account_name'] || $m['account_number']): ?>
                <dl class="acct"><?php if ($m['account_name']): ?><dt>Account Name</dt><dd><?= e($m['account_name']) ?></dd><?php endif; ?>
                  <?php if ($m['account_number']): ?><dt>Number</dt><dd><span class="copyable"><?= e($m['account_number']) ?></span> <button class="copy-btn" type="button" data-copy="<?= e($m['account_number']) ?>" aria-label="Copy account number"><i class="fa-regular fa-copy" aria-hidden="true"></i></button></dd><?php endif; ?></dl>
              <?php endif; ?>
              <?php if ($m['instructions']): ?><p class="small text-muted mb-0"><?= nl2br_e($m['instructions']) ?></p><?php endif; ?>
            </div>
          </div>
        <?php endforeach; endif; ?>

        <?php if (setting('giving_scripture')): ?>
          <div class="side-card navy scripture-card mt-4" data-aos="fade-up"><i class="fa-solid fa-book-bible" aria-hidden="true"></i><p>“<?= e(setting('giving_scripture')) ?>”</p><cite><?= e(setting('giving_scripture_ref')) ?></cite></div>
        <?php endif; ?>
      </div>

      <div class="col-lg-7">
        <form class="form-card" method="post" action="<?= e(url('api/giving.php')) ?>" data-public-form novalidate data-aos="fade-left">
          <h2>Record Your Gift</h2>
          <p class="text-muted">After sending your gift by mobile money or bank transfer, tell us about it here so we can confirm and thank you. You will receive a reference number.</p>
          <?= csrf_field() ?><?= honeypot_field() ?>
          <div class="row g-3">
            <div class="col-12"><span class="form-label d-block">Giving Type <span class="text-danger" aria-hidden="true">*</span></span>
              <div class="choice-grid" role="radiogroup" aria-label="Giving type">
                <?php foreach ($categories as $i => $c): ?><label class="choice"><input type="radio" name="category_id" value="<?= (int) $c['id'] ?>" <?= $i === 0 ? 'checked' : '' ?> required><span><i class="fa-solid <?= e($c['icon']) ?>" aria-hidden="true"></i> <?= e($c['name']) ?></span></label><?php endforeach; ?>
              </div><div class="invalid-feedback" data-error-for="category_id"></div></div>
            <div class="col-sm-8"><label class="form-label" for="amount">Amount <span class="text-danger" aria-hidden="true">*</span></label>
              <div class="input-group input-group-lg"><span class="input-group-text"><i class="fa-solid fa-coins" aria-hidden="true"></i></span><input type="number" min="1" step="0.01" inputmode="decimal" class="form-control" id="amount" name="amount" required></div>
              <div class="amount-chips mt-2" aria-label="Quick amounts"><?php foreach ([10, 25, 50, 100, 250] as $amt): ?><button type="button" class="chip" data-amount="<?= $amt ?>"><?= $amt ?></button><?php endforeach; ?></div>
              <div class="invalid-feedback" data-error-for="amount"></div></div>
            <div class="col-sm-4"><label class="form-label" for="currency">Currency</label><select class="form-select form-select-lg" id="currency" name="currency"><?php foreach ($currencies as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><label class="form-label" for="method_id">Payment Method <span class="text-danger" aria-hidden="true">*</span></label>
              <select class="form-select" id="method_id" name="method_id" required><?php foreach ($methods as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select><div class="invalid-feedback" data-error-for="method_id"></div></div>
            <div class="col-12"><label class="form-label" for="payment_reference">Transaction ID / Reference <small class="text-muted">(from your mobile money or bank receipt)</small></label><input class="form-control" id="payment_reference" name="payment_reference" maxlength="100"></div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="is_anonymous" name="is_anonymous" value="1" data-toggle-anon><label class="form-check-label" for="is_anonymous">Give anonymously</label></div></div>
            <div class="col-sm-6" data-anon-hide><label class="form-label" for="donor_name">Full Name <span class="text-danger" aria-hidden="true">*</span></label><input class="form-control" id="donor_name" name="donor_name" maxlength="120" autocomplete="name"><div class="invalid-feedback" data-error-for="donor_name"></div></div>
            <div class="col-sm-6"><label class="form-label" for="phone">Phone</label><input type="tel" class="form-control" id="phone" name="phone" maxlength="40" autocomplete="tel"></div>
            <div class="col-12"><label class="form-label" for="email">Email <small class="text-muted">(for your receipt)</small></label><input type="email" class="form-control" id="email" name="email" maxlength="190" autocomplete="email"><div class="invalid-feedback" data-error-for="email"></div></div>
          </div>
          <button class="btn btn-gold btn-lg w-100 mt-4" type="submit" data-loading-text="Submitting…"><i class="fa-solid fa-heart" aria-hidden="true"></i> Submit My Gift</button>
          <div class="form-success" hidden><i class="fa-solid fa-circle-check" aria-hidden="true"></i><p data-success-text></p></div>
          <p class="small text-muted mt-3 mb-0"><i class="fa-solid fa-lock" aria-hidden="true"></i> We never ask for card numbers or PINs on this page. Your details are visible only to authorised church staff.</p>
        </form>
      </div>
    </div>
  </div>
</section>

<section class="section-sm bg-soft">
  <div class="container text-center" data-aos="fade-up">
    <h2 class="section-title-sm">Why We Give</h2>
    <div class="why-grid">
      <?php foreach ([['fa-church', 'Support the local church'], ['fa-person-walking-arrow-right', 'Reach the lost'], ['fa-bowl-food', 'Feed the needy'], ['fa-crown', 'Build God\'s Kingdom']] as [$icon, $label]): ?>
        <div><i class="fa-solid <?= $icon ?>" aria-hidden="true"></i><span><?= e($label) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
