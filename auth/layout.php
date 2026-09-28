<?php
/** Shared split-screen layout for sign-in / password pages. */
function auth_layout_start(string $title, string $heading, string $sub = ''): void
{
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e(setting('church_name', "King's City Prophetic Ministries")) ?></title>
<link rel="icon" href="<?= e(media_url(setting('favicon'), 'assets/images/favicon.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@700;800&family=Cormorant+Garamond:ital,wght@1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="auth-body">
<main class="auth-shell">
  <section class="auth-brand" aria-hidden="true">
    <img src="<?= e(media_url(setting('logo'), 'assets/images/logo.svg')) ?>" alt="" width="120" height="120">
    <h2><?= e(setting('church_short_name', "King's City")) ?><span><?= e(str_replace(setting('church_short_name', "King's City"), '', setting('church_name'))) ?></span></h2>
    <p class="tagline"><?= e(setting('church_tagline')) ?></p>
    <p class="motto"><?= e(setting('church_motto')) ?></p>
  </section>
  <section class="auth-panel">
    <div class="auth-card">
      <a href="<?= e(url()) ?>" class="small text-muted text-decoration-none"><i class="fa-solid fa-arrow-left"></i> Back to website</a>
      <h1 class="h3 mt-3 mb-1"><?= e($heading) ?></h1>
      <?php if ($sub): ?><p class="text-muted"><?= e($sub) ?></p><?php endif; ?>
      <?php foreach (take_flashes() as $f): ?>
        <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : ($f['type'] === 'success' ? 'success' : 'info') ?> small" role="alert"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
<?php
}

function auth_layout_end(): void
{
    ?>
    </div>
    <p class="text-center small text-muted mt-4">&copy; <?= date('Y') ?> <?= e(setting('church_name')) ?></p>
  </section>
</main>
<script>
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = document.getElementById(btn.dataset.togglePassword);
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.innerHTML = show ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
  });
});
document.querySelectorAll('form[data-once]').forEach(function (f) {
  f.addEventListener('submit', function () { var b = f.querySelector('button[type=submit]'); if (b) { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Please wait…'; } });
});
</script>
</body>
</html>
<?php
}

function password_input(string $name, string $label, string $autocomplete = 'current-password'): string
{
    return '<div class="mb-3"><label class="form-label" for="' . e($name) . '">' . e($label) . '</label><div class="input-group">'
        . '<input type="password" class="form-control form-control-lg" id="' . e($name) . '" name="' . e($name) . '" required autocomplete="' . e($autocomplete) . '">'
        . '<button class="btn btn-outline-secondary" type="button" data-toggle-password="' . e($name) . '" aria-label="Show password" aria-pressed="false"><i class="fa-regular fa-eye"></i></button></div></div>';
}
