<?php
/**
 * Production build: writes style.min.css / admin.min.css next to the sources.
 * asset() automatically serves the .min file when app.debug is false.
 * Run:  php tools/minify.php
 *
 * (JavaScript is already small and loaded with `defer`; for extra savings run
 *  `npx terser assets/js/main.js -c -m -o assets/js/main.min.js` if Node is available.)
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
$dir = dirname(__DIR__) . '/assets/css';
foreach (['style', 'admin'] as $name) {
    $css = file_get_contents("$dir/$name.css");
    $css = preg_replace('!/\*.*?\*/!s', '', $css);          // comments
    $css = preg_replace('/\s+/', ' ', $css);                 // whitespace
    $css = preg_replace('/\s*([{};:,>])\s*/', '$1', $css);   // around punctuation
    $css = str_replace(';}', '}', $css);
    file_put_contents("$dir/$name.min.css", trim($css));
    printf("✓ %s.min.css  %.1f KB → %.1f KB\n", $name, filesize("$dir/$name.css") / 1024, filesize("$dir/$name.min.css") / 1024);
}
