<?php
require __DIR__ . '/includes/bootstrap.php';

$p = paginate(Testimony::countPublished(), 9);
$testimonies = Testimony::published($p['per_page'], $p['offset']);

$page = ['title' => 'Testimonies', 'nav' => 'testimonies', 'description' => 'Testimonies of God\'s faithfulness from the ' . setting('church_name') . ' family.'];
require __DIR__ . '/includes/header.php';
echo page_banner('Testimonies', 'Declaring the goodness of God.');
?>
<section class="section">
  <div class="container">
    <?php if (!$testimonies): echo empty_state('fa-heart', 'Be the first to share', 'Your story of God\'s faithfulness can encourage someone today.');
    else: ?>
      <div class="masonry">
        <?php foreach ($testimonies as $i => $t): ?>
          <figure class="testimony-card" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
            <i class="fa-solid fa-quote-left quote-mark" aria-hidden="true"></i>
            <?php if ($t['title']): ?><h2 class="h5"><?= e($t['title']) ?></h2><?php endif; ?>
            <blockquote><?= nl2br_e($t['testimony']) ?></blockquote>
            <figcaption><?php if ($t['photo']): ?><img src="<?= e(media_url($t['photo'])) ?>" alt="" width="44" height="44" loading="lazy"><?php else: ?><span class="avatar-initials"><?= e(initials($t['name'])) ?></span><?php endif; ?><span><?= e($t['name']) ?><small><?= e(format_date($t['created_at'], 'F Y')) ?></small></span></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
      <?= pagination_links($p, 'mt-4 d-flex justify-content-center') ?>
    <?php endif; ?>
  </div>
</section>

<section class="section bg-soft" id="share">
  <div class="container">
    <div class="row justify-content-center"><div class="col-lg-8">
      <form class="form-card" method="post" action="<?= e(url('api/testimony.php')) ?>" enctype="multipart/form-data" data-public-form novalidate data-aos="fade-up">
        <h2>Share Your Testimony</h2>
        <p class="text-muted">Testimonies are reviewed by our team before they are published.</p>
        <?= csrf_field() ?><?= honeypot_field() ?>
        <div class="row g-3">
          <div class="col-sm-6"><label class="form-label" for="name">Your Name <span class="text-danger" aria-hidden="true">*</span></label><input class="form-control" id="name" name="name" maxlength="120" required autocomplete="name"><div class="invalid-feedback" data-error-for="name"></div></div>
          <div class="col-sm-6"><label class="form-label" for="email">Email</label><input type="email" class="form-control" id="email" name="email" maxlength="190" autocomplete="email"><div class="invalid-feedback" data-error-for="email"></div></div>
          <div class="col-12"><label class="form-label" for="title">Title</label><input class="form-control" id="title" name="title" maxlength="200" placeholder="e.g. Healed after prayer"></div>
          <div class="col-12"><label class="form-label" for="testimony">Your Testimony <span class="text-danger" aria-hidden="true">*</span></label><textarea class="form-control" id="testimony" name="testimony" rows="6" maxlength="5000" required></textarea><div class="invalid-feedback" data-error-for="testimony"></div></div>
          <div class="col-12"><label class="form-label" for="photo">Photo (optional)</label><input type="file" class="form-control" id="photo" name="photo" accept="<?= e(Upload::accept('image')) ?>"><div class="form-text">JPG, PNG or WebP, max <?= UPLOAD_RULES['image']['max_mb'] ?> MB.</div><div class="invalid-feedback" data-error-for="photo"></div></div>
          <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="permission_to_publish" name="permission_to_publish" value="1" checked><label class="form-check-label" for="permission_to_publish">I give permission for my testimony to be published on the church website.</label></div></div>
        </div>
        <button class="btn btn-gold btn-lg w-100 mt-4" type="submit" data-loading-text="Sending…"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Submit Testimony</button>
        <div class="form-success" hidden><i class="fa-solid fa-circle-check" aria-hidden="true"></i><p data-success-text></p></div>
      </form>
    </div></div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
