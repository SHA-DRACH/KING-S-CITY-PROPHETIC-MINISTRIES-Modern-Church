<?php
require __DIR__ . '/includes/bootstrap.php';

$doc = Page::findBy('slug', 'privacy-policy') ?? abort(404);
$page = ['title' => $doc['title'], 'description' => $doc['meta_description']];
require __DIR__ . '/includes/header.php';
echo page_banner($doc['title'], 'Last updated ' . format_date($doc['updated_at'], 'F j, Y'));
?>
<section class="section">
  <div class="container"><div class="row justify-content-center"><div class="col-lg-8">
    <div class="rich-text legal" data-aos="fade-up"><?= sanitize_html($doc['content']) ?></div>
  </div></div></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
