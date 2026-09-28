<?php
require __DIR__ . '/includes/bootstrap.php';
http_response_code(404);
$page = ['title' => 'Page Not Found'];
require __DIR__ . '/includes/header.php';
echo page_banner('Page Not Found', 'The page you are looking for may have moved.');
?>
<section class="section text-center">
  <div class="container">
    <p class="lead-text">“Seek, and you will find.” — Matthew 7:7</p>
    <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
      <a class="btn btn-gold" href="<?= e(url()) ?>">Go to Homepage</a>
      <a class="btn btn-outline-navy" href="<?= e(url('sermons.php')) ?>">Watch Sermons</a>
      <a class="btn btn-outline-navy" href="<?= e(url('contact.php')) ?>">Contact Us</a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
