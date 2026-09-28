<?php
/** robots.txt (served at /robots.txt via .htaccess) with an absolute sitemap URL. */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
foreach (['/admin/', '/auth/', '/api/', '/config/', '/controllers/', '/models/', '/includes/', '/tools/'] as $path) {
    echo 'Disallow: ' . rtrim(parse_url(url($path), PHP_URL_PATH), '/') . "/\n";
}
echo "\nSitemap: " . url('sitemap.xml') . "\n";
