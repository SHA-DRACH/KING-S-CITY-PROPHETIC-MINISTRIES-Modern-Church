<?php
/** Dynamic XML sitemap, served at /sitemap.xml via .htaccess. */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
$urls = [];
foreach (['' => '1.0', 'about.php' => '0.8', 'ministries.php' => '0.8', 'sermons.php' => '0.9', 'events.php' => '0.9', 'pastor.php' => '0.7',
          'giving.php' => '0.7', 'gallery.php' => '0.6', 'prayer.php' => '0.7', 'testimonies.php' => '0.6', 'contact.php' => '0.7',
          'privacy.php' => '0.2', 'terms.php' => '0.2'] as $path => $priority) {
    $urls[] = [url($path), null, $priority];
}
foreach (DB::all("SELECT slug, updated_at FROM sermons WHERE status = 'published' ORDER BY sermon_date DESC LIMIT 500") as $s) {
    $urls[] = [url('sermon-details.php?slug=' . rawurlencode($s['slug'])), $s['updated_at'], '0.6'];
}
foreach (DB::all("SELECT slug, updated_at FROM events WHERE status = 'published' ORDER BY event_date DESC LIMIT 500") as $ev) {
    $urls[] = [url('event-details.php?slug=' . rawurlencode($ev['slug'])), $ev['updated_at'], '0.5'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $priority]) {
    echo '  <url><loc>' . e($loc) . '</loc>' . ($mod ? '<lastmod>' . date('Y-m-d', strtotime($mod)) . '</lastmod>' : '') . '<priority>' . $priority . "</priority></url>\n";
}
echo '</urlset>';
