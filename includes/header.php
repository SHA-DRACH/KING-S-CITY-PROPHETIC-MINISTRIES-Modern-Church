<?php
/**
 * Public <head> + navigation. Pages set $page before including:
 *   $page = ['title' => '…', 'description' => '…', 'image' => 'path', 'type' => 'website|article',
 *            'nav' => 'sermons', 'schema' => [...extra JSON-LD...], 'hero' => true (transparent nav),
 *            'libs' => ['swiper', 'glightbox']];
 */
$page = ($page ?? []) + ['title' => null, 'description' => null, 'image' => null, 'type' => 'website', 'nav' => '', 'schema' => [], 'hero' => false, 'libs' => []];
$siteName = setting('church_name', "King's City Prophetic Ministries");
$fullTitle = $page['title'] ? $page['title'] . ' | ' . $siteName : $siteName . ' | ' . setting('church_tagline');
$description = excerpt($page['description'] ?: setting('seo_description'), 160);
$ogImage = media_url($page['image'] ?: setting('og_image'), 'assets/images/placeholders/hero-poster.svg');
$canonical = canonical_url();

// Schema.org: the church as an organisation (on every page) + page-specific items
$churchSchema = [
    '@context'  => 'https://schema.org',
    '@type'     => 'Church',
    'name'      => $siteName,
    'alternateName' => setting('church_short_name'),
    'description' => setting('seo_description'),
    'url'       => url(),
    'logo'      => media_url(setting('logo'), 'assets/images/logo.svg'),
    'image'     => $ogImage,
    'telephone' => setting('phone'),
    'email'     => setting('email'),
    'address'   => [
        '@type' => 'PostalAddress',
        'streetAddress'   => setting('address'),
        'addressLocality' => 'Paynesville',
        'addressRegion'   => 'Montserrado',
        'addressCountry'  => 'LR',
    ],
    'sameAs'    => array_values(array_filter(array_column(social_links(), 'url'), fn($u) => !str_contains($u, 'wa.me'))),
];
if ($pastorName = DB::value('SELECT name FROM pastor_profiles ORDER BY is_primary DESC LIMIT 1')) {
    $churchSchema['founder'] = ['@type' => 'Person', 'name' => $pastorName, 'jobTitle' => 'Senior Pastor'];
}
$schemas = array_merge([$churchSchema], $page['schema']);
$liveUrl = setting('live_stream_url') ?: url('sermons.php');
$isLive = setting('is_live') === '1';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="keywords" content="<?= e(setting('seo_keywords')) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta name="theme-color" content="#071B35">
<link rel="icon" href="<?= e(media_url(setting('favicon'), 'assets/images/favicon.svg')) ?>">
<!-- Open Graph / Twitter -->
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:type" content="<?= e($page['type']) ?>">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:locale" content="en_LR">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($fullTitle) ?>">
<meta name="twitter:description" content="<?= e($description) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">
<?php foreach ($schemas as $schema): ?>
<script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endforeach; ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800;900&family=Cormorant+Garamond:ital,wght@0,600;1,500;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
<?php if (in_array('swiper', $page['libs'], true)): ?><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"><?php endif; ?>
<?php if (in_array('glightbox', $page['libs'], true)): ?><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/css/glightbox.min.css"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="<?= $page['hero'] ? 'has-hero' : 'no-hero' ?>">
<a class="skip-link" href="#main">Skip to main content</a>
<?php require __DIR__ . '/navbar.php'; ?>
<main id="main" tabindex="-1">
<?= flash_toasts() ?>
