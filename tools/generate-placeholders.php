<?php
/**
 * Generates the branded SVG placeholder artwork in assets/images/placeholders.
 * These are stand-ins until real church photography is uploaded through the
 * admin dashboard.  Run:  php tools/generate-placeholders.php
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
$out = dirname(__DIR__) . '/assets/images/placeholders';
@mkdir($out, 0755, true);

function bg(int $w, int $h, string $top, string $bottom, string $glow, float $gx = .5, float $gy = .35): string
{
    return <<<SVG
<defs>
  <linearGradient id="bg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="$top"/><stop offset="1" stop-color="$bottom"/></linearGradient>
  <radialGradient id="glow" cx="$gx" cy="$gy" r=".6"><stop offset="0" stop-color="$glow" stop-opacity=".95"/><stop offset=".35" stop-color="$glow" stop-opacity=".35"/><stop offset="1" stop-color="$glow" stop-opacity="0"/></radialGradient>
  <linearGradient id="ray" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFE9A8" stop-opacity=".55"/><stop offset="1" stop-color="#FFE9A8" stop-opacity="0"/></linearGradient>
  <linearGradient id="gold" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFE28A"/><stop offset="1" stop-color="#E0A92A"/></linearGradient>
</defs>
<rect width="$w" height="$h" fill="url(#bg)"/>
<rect width="$w" height="$h" fill="url(#glow)"/>
SVG;
}

function rays(int $w, int $h, float $cx, float $cy, int $n = 9, int $seed = 1): string
{
    mt_srand($seed);
    $s = '<g opacity=".5">';
    for ($i = 0; $i < $n; $i++) {
        $a = deg2rad(60 + $i * (60 / max(1, $n - 1)) + mt_rand(-4, 4));
        $len = $h * 1.3;
        $spread = deg2rad(2.2);
        $x1 = $cx + cos($a - $spread) * $len; $y1 = $cy + sin($a - $spread) * $len;
        $x2 = $cx + cos($a + $spread) * $len; $y2 = $cy + sin($a + $spread) * $len;
        $s .= sprintf('<polygon points="%.1f,%.1f %.1f,%.1f %.1f,%.1f" fill="url(#ray)"/>', $cx, $cy, $x1, $y1, $x2, $y2);
    }
    return $s . '</g>';
}

function bokeh(int $w, int $h, int $n, int $seed, string $color = '#FFD966'): string
{
    mt_srand($seed);
    $s = '<g>';
    for ($i = 0; $i < $n; $i++) {
        $s .= sprintf('<circle cx="%d" cy="%d" r="%d" fill="%s" opacity="%.2f"/>', mt_rand(0, $w), mt_rand(0, (int) ($h * .7)), mt_rand(3, 22), $color, mt_rand(8, 35) / 100);
    }
    return $s . '</g>';
}

/** A crowd of silhouettes; $hands = share of people with raised hands. */
function crowd(int $w, int $h, int $rows, float $hands, int $seed, string $fill = '#040C1A', float $scale = 1.0): string
{
    mt_srand($seed);
    $s = '';
    for ($r = 0; $r < $rows; $r++) {
        $base = $h - ($rows - 1 - $r) * 38 * $scale;
        $size = (26 + $r * 7) * $scale;
        $op = 0.55 + $r * (0.45 / max(1, $rows - 1));
        $s .= '<g fill="' . $fill . '" opacity="' . round($op, 2) . '">';
        for ($x = -20 + mt_rand(0, 30); $x < $w + 40; $x += $size * (1.5 + mt_rand(0, 60) / 100)) {
            $hy = $base - $size * 2.1 - mt_rand(0, (int) ($size * .5));
            $s .= sprintf('<ellipse cx="%.1f" cy="%.1f" rx="%.1f" ry="%.1f"/>', $x, $hy, $size * .42, $size * .5);
            $s .= sprintf('<path d="M%.1f %.1f Q%.1f %.1f %.1f %.1f L%.1f %.1f Q%.1f %.1f %.1f %.1f Z"/>',
                $x - $size * .95, $h + 10, $x - $size * .9, $hy + $size * .55, $x, $hy + $size * .5,
                $x, $hy + $size * .5, $x + $size * .9, $hy + $size * .55, $x + $size * .95, $h + 10);
            if (mt_rand(0, 100) / 100 < $hands) {
                foreach ([-1, 1] as $side) {
                    if (mt_rand(0, 100) < 35 && $side === -1) {
                        continue;
                    }
                    $sx = $x + $side * $size * .55; $sy = $hy + $size * .9;
                    $ex = $sx + $side * $size * (0.25 + mt_rand(0, 40) / 100); $ey = $hy - $size * (1.2 + mt_rand(0, 60) / 100);
                    $s .= sprintf('<path d="M%.1f %.1f L%.1f %.1f" stroke="%s" stroke-width="%.1f" stroke-linecap="round" fill="none"/>', $sx, $sy, $ex, $ey, $fill, $size * .26);
                    $s .= sprintf('<ellipse cx="%.1f" cy="%.1f" rx="%.1f" ry="%.1f"/>', $ex, $ey - $size * .12, $size * .16, $size * .24);
                }
            }
        }
        $s .= '</g>';
    }
    return $s;
}

function cross(float $cx, float $cy, float $size, string $fill = 'url(#gold)', float $op = .9): string
{
    $w = $size * .16;
    return sprintf('<g fill="%s" opacity="%.2f"><rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="%.1f"/><rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="%.1f"/></g>',
        $fill, $op, $cx - $w / 2, $cy - $size / 2, $w, $size, $w * .2, $cx - $size * .34, $cy - $size * .25, $size * .68, $w, $w * .2);
}

function book(float $cx, float $cy, float $s): string
{
    return sprintf('<g transform="translate(%.1f %.1f) scale(%.2f)"><path d="M-150 20 Q-75 -20 0 20 Q75 -20 150 20 L150 90 Q75 50 0 90 Q-75 50 -150 90 Z" fill="#F7F1E1"/>'
        . '<path d="M0 20 L0 90" stroke="#C9A55A" stroke-width="3"/><g stroke="#C9B894" stroke-width="2.5" opacity=".7">'
        . '<path d="M-130 35 Q-70 5 -15 35"/><path d="M-130 50 Q-70 20 -15 50"/><path d="M-130 65 Q-70 35 -15 65"/>'
        . '<path d="M15 35 Q70 5 130 35"/><path d="M15 50 Q70 20 130 50"/><path d="M15 65 Q70 35 130 65"/></g>'
        . '<path d="M-150 90 Q-75 50 0 90 Q75 50 150 90 L150 100 Q75 60 0 100 Q-75 60 -150 100 Z" fill="#7A2E1D"/></g>', $cx, $cy, $s);
}

function portrait(float $cx, float $cy, float $s): string
{
    return sprintf('<g transform="translate(%.1f %.1f) scale(%.2f)">'
        . '<path d="M-230 330 Q-220 130 -60 100 L60 100 Q220 130 230 330 Z" fill="#0B1E3A"/>'
        . '<path d="M-60 100 L0 230 L60 100 Z" fill="#F4F6FA"/><path d="M-12 118 L0 240 L12 118 L0 105 Z" fill="#B8871E"/>'
        . '<path d="M-60 100 L-5 250 L-95 140 Z M60 100 L5 250 L95 140 Z" fill="#132D52"/>'
        . '<rect x="-38" y="40" width="76" height="70" rx="30" fill="#4A2E22"/>'
        . '<ellipse cx="0" cy="-10" rx="82" ry="100" fill="#5B3A2B"/>'
        . '<path d="M-82 -30 Q-80 -118 0 -115 Q80 -118 82 -30 Q70 -80 0 -82 Q-70 -80 -82 -30 Z" fill="#1A120E"/>'
        . '</g>', $cx, $cy, $s);
}

$scenes = [
    'hero-poster' => [1920, 1080, fn($w, $h) => bg($w, $h, '#081D3A', '#02070F', '#E9B949', .5, .2) . rays($w, $h, $w / 2, $h * .12, 13, 3) . bokeh($w, $h, 60, 4) . cross($w / 2, $h * .2, 150) . crowd($w, $h, 4, .7, 11, '#030914', 1.6)],
    'worship'     => [1200, 800, fn($w, $h) => bg($w, $h, '#0D315C', '#030A16', '#F4C542', .5, .25) . rays($w, $h, $w / 2, $h * .1, 9, 5) . bokeh($w, $h, 40, 6) . crowd($w, $h, 3, .75, 21, '#040C1A', 1.2)],
    'prayer'      => [1200, 800, fn($w, $h) => bg($w, $h, '#2A1A0C', '#070604', '#FFB547', .5, .45) . bokeh($w, $h, 70, 7, '#FFC96B') . crowd($w, $h, 2, .25, 31, '#0A0703', 1.4)],
    'bible'       => [1200, 800, fn($w, $h) => bg($w, $h, '#10294B', '#040B18', '#FFD98A', .5, .45) . rays($w, $h, $w / 2, $h * .45, 11, 8) . book($w / 2, $h * .5, 2.3)],
    'sermon'      => [1200, 800, fn($w, $h) => bg($w, $h, '#0D315C', '#050E1D', '#F4C542', .3, .3) . rays($w, $h, $w * .3, 0, 7, 9) . portrait($w * .32, $h * .62, 1.25) . book($w * .72, $h * .62, 1.3)],
    'choir'       => [1200, 800, fn($w, $h) => bg($w, $h, '#3B1A55', '#0A0714', '#F4C542', .5, .2) . rays($w, $h, $w / 2, 0, 9, 10) . bokeh($w, $h, 45, 11, '#E7B8FF') . crowd($w, $h, 3, .45, 41, '#08040F', 1.1)],
    'youth'       => [1200, 800, fn($w, $h) => bg($w, $h, '#123E86', '#060D1E', '#5FD0FF', .5, .25) . rays($w, $h, $w / 2, 0, 11, 12) . bokeh($w, $h, 55, 13, '#9BE3FF') . crowd($w, $h, 3, .85, 51, '#030A18', 1.15)],
    'women'       => [1200, 800, fn($w, $h) => bg($w, $h, '#5A1D3F', '#12050D', '#FFC2A8', .5, .3) . bokeh($w, $h, 50, 14, '#FFD1E0') . crowd($w, $h, 2, .5, 61, '#12040B', 1.35)],
    'men'         => [1200, 800, fn($w, $h) => bg($w, $h, '#14314F', '#040A12', '#F4C542', .5, .3) . rays($w, $h, $w / 2, 0, 7, 15) . crowd($w, $h, 2, .35, 71, '#03070D', 1.45)],
    'children'    => [1200, 800, fn($w, $h) => bg($w, $h, '#127A6B', '#062520', '#FFE27A', .5, .25) . bokeh($w, $h, 60, 16, '#FFF3B0') . crowd($w, $h, 2, .9, 81, '#04201B', .85)],
    'community'   => [1200, 800, fn($w, $h) => bg($w, $h, '#E08A3C', '#2A1405', '#FFE0A0', .5, .55)
        . '<g fill="#2A1405" opacity=".55"><path d="M0 560 L120 500 L240 560 L240 800 L0 800Z M260 540 L380 470 L500 540 L500 800 L260 800Z M760 530 L900 460 L1040 530 L1040 800 L760 800Z M1040 560 L1140 500 L1200 540 L1200 800 L1040 800Z"/></g>'
        . crowd($w, $h, 2, .4, 91, '#1A0B02', 1.2)],
    'conference'  => [1200, 800, fn($w, $h) => bg($w, $h, '#0A1F3F', '#02060D', '#6FA8FF', .5, .15) . rays($w, $h, $w * .25, 0, 5, 17) . rays($w, $h, $w * .75, 0, 5, 18) . '<rect x="330" y="300" width="540" height="18" rx="6" fill="#F4C542" opacity=".8"/>' . cross($w / 2, 230, 110) . crowd($w, $h, 4, .6, 101, '#02060D', 1.0)],
    'pastor'      => [900, 1080, fn($w, $h) => bg($w, $h, '#0D315C', '#040B18', '#F4C542', .5, .3) . rays($w, $h, $w / 2, 0, 9, 19) . portrait($w / 2, $h * .52, 1.55)],
];

foreach ($scenes as $name => [$w, $h, $draw]) {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" preserveAspectRatio="xMidYMid slice">' . $draw($w, $h) . '</svg>';
    file_put_contents("$out/$name.svg", $svg);
    echo "✓ $name.svg (" . round(strlen($svg) / 1024, 1) . " KB)\n";
}
