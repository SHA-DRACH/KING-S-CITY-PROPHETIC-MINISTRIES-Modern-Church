<?php
/** Shared layout for sign-in / password pages: full-screen church backdrop + centred card. */
function auth_layout_start(string $title, string $heading, string $sub = ''): void
{
    $bg = media_url(setting('welcome_image'), 'assets/images/placeholders/hero-poster.svg');
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#071B35">
<title><?= e($title) ?> · <?= e(setting('church_name', "King's City Prophetic Ministries")) ?></title>
<link rel="icon" href="<?= e(favicon_url()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@700;800;900&family=Cormorant+Garamond:ital,wght@1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="auth-body" style="--auth-bg:url('<?= e($bg) ?>')">
<div class="auth-glow" aria-hidden="true"></div>
<header class="auth-top">
  <a href="<?= e(url()) ?>" class="auth-back"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to website</a>
</header>
<main class="auth-center">
  <div class="auth-card">
    <div class="auth-logo"><?= logo_img(104, '', setting('church_name') . ' logo') ?></div>
    <p class="auth-church"><?= e(setting('church_name')) ?></p>
    <p class="auth-tagline"><?= e(setting('church_tagline')) ?></p>
    <h1 class="auth-heading"><?= e($heading) ?></h1>
    <?php if ($sub): ?><p class="auth-sub"><?= e($sub) ?></p><?php endif; ?>
    <?php foreach (take_flashes() as $f): ?>
      <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : ($f['type'] === 'success' ? 'success' : 'info') ?> auth-alert" role="alert">
        <i class="fa-solid <?= $f['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check' ?>" aria-hidden="true"></i> <?= e($f['message']) ?></div>
    <?php endforeach; ?>
<?php
}

function auth_layout_end(): void
{
    ?>
  </div>
  <p class="auth-foot"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Secure staff area · &copy; <?= date('Y') ?> <?= e(setting('church_short_name', "King's City")) ?></p>
</main>
<script>
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = document.getElementById(btn.dataset.togglePassword);
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
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

/** Input with a leading icon (and an eye toggle for passwords). */
function auth_input(string $name, string $label, string $icon, string $type = 'text', string $autocomplete = '', string $value = '', bool $autofocus = false): string
{
    $toggle = $type === 'password'
        ? '<button class="auth-eye" type="button" data-toggle-password="' . e($name) . '" aria-label="Show password" aria-pressed="false"><i class="fa-regular fa-eye"></i></button>'
        : '';
    return '<div class="auth-field"><label for="' . e($name) . '">' . e($label) . '</label><div class="auth-input">'
        . '<i class="fa-solid ' . e($icon) . '" aria-hidden="true"></i>'
        . '<input type="' . e($type) . '" id="' . e($name) . '" name="' . e($name) . '" value="' . e($value) . '" required'
        . ($autocomplete ? ' autocomplete="' . e($autocomplete) . '"' : '') . ($autofocus ? ' autofocus' : '') . '>'
        . $toggle . '</div></div>';
}

function password_input(string $name, string $label, string $autocomplete = 'current-password'): string
{
    return auth_input($name, $label, 'fa-lock', 'password', $autocomplete);
}
