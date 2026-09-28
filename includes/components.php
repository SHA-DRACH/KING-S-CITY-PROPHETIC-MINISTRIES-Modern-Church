<?php
/**
 * Reusable UI components (return HTML strings; every dynamic value is escaped).
 */

// ---------------------------------------------------------------------
//  Shared
// ---------------------------------------------------------------------
function status_badge(string $status): string
{
    $map = [
        'published' => 'success', 'active' => 'success', 'confirmed' => 'success', 'answered' => 'success', 'approved' => 'info',
        'draft' => 'secondary', 'inactive' => 'secondary', 'closed' => 'secondary', 'disabled' => 'danger',
        'pending' => 'warning', 'new' => 'warning', 'assigned' => 'info', 'in_prayer' => 'primary',
        'cancelled' => 'danger', 'rejected' => 'danger', 'failed' => 'danger', 'refunded' => 'dark',
        '1' => 'success', '0' => 'secondary',
    ];
    $labels = ['1' => 'Published', '0' => 'Hidden', 'in_prayer' => 'In Prayer'];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge status-badge text-bg-' . $color . '">' . e($labels[$status] ?? ucwords(str_replace('_', ' ', $status))) . '</span>';
}

function empty_state(string $icon, string $title, string $text = ''): string
{
    return '<div class="empty-state"><div class="empty-icon"><i class="fa-solid ' . e($icon) . '"></i></div><h3>' . e($title) . '</h3>'
        . ($text ? '<p>' . e($text) . '</p>' : '') . '</div>';
}

/** Bootstrap form control for admin forms (used by CrudController and custom pages). */
function form_field(string $name, array $f, mixed $value = null): string
{
    $type = $f['type'] ?? 'text';
    $label = $f['label'] ?? ucfirst(str_replace('_', ' ', $name));
    $col = $f['col'] ?? 12;
    $req = !empty($f['required']);
    $id = 'f_' . $name;
    $attrs = ' id="' . e($id) . '" name="' . e($name) . '"' . ($req ? ' required' : '') . (isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '')
        . (isset($f['placeholder']) ? ' placeholder="' . e($f['placeholder']) . '"' : '');
    $val = $value ?? ($f['default'] ?? '');
    $help = isset($f['help']) ? '<div class="form-text">' . e($f['help']) . '</div>' : '';
    $labelHtml = '<label class="form-label" for="' . e($id) . '">' . e($label) . ($req ? ' <span class="text-danger" aria-hidden="true">*</span>' : '') . '</label>';
    $feedback = '<div class="invalid-feedback" data-error-for="' . e($name) . '"></div>';

    $control = match ($type) {
        'textarea', 'html' => '<textarea class="form-control" rows="' . (int) ($f['rows'] ?? 4) . '"' . $attrs . '>' . e($val) . '</textarea>',
        'select' => (function () use ($f, $attrs, $val) {
            $h = '<select class="form-select"' . $attrs . '>';
            if (!empty($f['placeholder_option']) || empty($f['required'])) {
                $h .= '<option value="">' . e($f['placeholder_option'] ?? '— None —') . '</option>';
            }
            foreach ($f['options_resolved'] ?? [] as $k => $t) {
                $h .= '<option value="' . e($k) . '"' . ((string) $k === (string) $val ? ' selected' : '') . '>' . e($t) . '</option>';
            }
            return $h . '</select>';
        })(),
        'checkbox' => '<div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" value="1"' . $attrs . ($val ? ' checked' : '') . '>'
            . '<label class="form-check-label" for="' . e($id) . '">' . e($f['check_label'] ?? $label) . '</label></div>',
        'file' => '<div class="file-field" data-file-field="' . e($name) . '"><div class="file-preview" data-preview-for="' . e($name) . '"></div>'
            . '<input type="file" class="form-control" id="' . e($id) . '" name="' . e($name) . '" accept="' . e(Upload::accept($f['upload'])) . '">'
            . '<label class="form-check small mt-1 d-none" data-remove-for="' . e($name) . '"><input type="checkbox" class="form-check-input" name="remove_' . e($name) . '" value="1"> Remove current file</label>'
            . '<div class="form-text">Max ' . ($f['upload'] === 'video' ? (int) setting('max_video_upload_mb', '200') : UPLOAD_RULES[$f['upload']]['max_mb']) . ' MB · ' . e(strtoupper(implode(', ', array_keys(UPLOAD_RULES[$f['upload']]['types'])))) . '</div></div>',
        'static' => '<div class="form-control-plaintext static-field" data-static="' . e($name) . '"></div>',
        default => '<input type="' . e($type) . '" class="form-control" value="' . e($val) . '"' . $attrs . ($type === 'number' ? ' step="' . e($f['step'] ?? 'any') . '"' : '') . '>',
    };

    if ($type === 'checkbox') {
        return '<div class="col-md-' . (int) $col . '">' . $control . $help . $feedback . '</div>';
    }
    if (!empty($f['section'])) {
        $labelHtml = '<div class="form-section">' . e($f['section']) . '</div>' . $labelHtml;
    }
    return '<div class="col-md-' . (int) $col . '">' . $labelHtml . $control . $help . $feedback . '</div>';
}

// ---------------------------------------------------------------------
//  Public site
// ---------------------------------------------------------------------
function section_heading(string $eyebrow, string $title, string $sub = '', string $align = 'center', bool $light = false): string
{
    return '<div class="section-heading text-' . e($align) . ($light ? ' is-light' : '') . '" data-aos="fade-down">'
        . ($eyebrow ? '<span class="eyebrow">' . e($eyebrow) . '</span>' : '')
        . '<h2>' . e($title) . '</h2>' . ($sub ? '<p>' . e($sub) . '</p>' : '') . '</div>';
}

function page_banner(string $title, string $subtitle = '', string $image = 'assets/images/placeholders/hero-poster.svg', array $crumbs = []): string
{
    $crumbHtml = '<nav aria-label="Breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="' . e(url()) . '">Home</a></li>';
    foreach ($crumbs as $label => $href) {
        $crumbHtml .= $href ? '<li class="breadcrumb-item"><a href="' . e(url($href)) . '">' . e($label) . '</a></li>' : '';
    }
    $crumbHtml .= '<li class="breadcrumb-item active" aria-current="page">' . e($title) . '</li></ol></nav>';
    return '<section class="page-banner" style="--banner:url(\'' . e(media_url($image)) . '\')"><div class="container">'
        . '<h1 data-aos="fade-up">' . e($title) . '</h1>' . ($subtitle ? '<p data-aos="fade-up" data-aos-delay="100">' . e($subtitle) . '</p>' : '')
        . '<div data-aos="fade-up" data-aos-delay="150">' . $crumbHtml . '</div></div></section>';
}

function date_chip(string $date): string
{
    return '<div class="date-chip" aria-hidden="true"><span>' . e(strtoupper(date('M', strtotime($date)))) . '</span><strong>' . e(date('d', strtotime($date))) . '</strong></div>';
}

function event_card(array $ev, int $delay = 0): string
{
    $link = url('event-details.php?slug=' . rawurlencode($ev['slug']));
    $cancelled = $ev['status'] === 'cancelled' ? '<span class="badge text-bg-danger card-flag">Cancelled</span>' : '';
    return '<article class="event-card h-100" data-aos="fade-up" data-aos-delay="' . $delay . '">'
        . '<a class="event-media" href="' . e($link) . '" tabindex="-1" aria-hidden="true"><img src="' . e(media_url($ev['image'])) . '" alt="" loading="lazy" width="600" height="380">' . date_chip($ev['event_date']) . $cancelled . '</a>'
        . '<div class="event-body">' . (!empty($ev['category_name']) ? '<span class="tag">' . e($ev['category_name']) . '</span>' : '')
        . '<h3><a href="' . e($link) . '">' . e($ev['title']) . '</a></h3>'
        . '<ul class="meta"><li><i class="fa-regular fa-clock" aria-hidden="true"></i> ' . e(format_date($ev['event_date'], 'D, M j') . ($ev['start_time'] ? ' · ' . time_range($ev['start_time'], $ev['end_time']) : '')) . '</li>'
        . ($ev['location'] ? '<li><i class="fa-solid fa-location-dot" aria-hidden="true"></i> ' . e($ev['location']) . '</li>' : '') . '</ul>'
        . '<p>' . e(excerpt($ev['description'], 95)) . '</p>'
        . '<a class="link-arrow" href="' . e($link) . '">Details <span class="visually-hidden">about ' . e($ev['title']) . '</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div></article>';
}

function sermon_card(array $s, int $delay = 0): string
{
    $link = url('sermon-details.php?slug=' . rawurlencode($s['slug']));
    $icon = $s['media_type'] === 'audio' ? 'fa-headphones' : 'fa-play';
    return '<article class="sermon-card h-100" data-aos="fade-up" data-aos-delay="' . $delay . '">'
        . '<a class="sermon-media" href="' . e($link) . '" aria-label="' . e(($s['media_type'] === 'audio' ? 'Listen to ' : 'Watch ') . $s['title']) . '"><img src="' . e(Sermon::thumb($s)) . '" alt="" loading="lazy" width="600" height="340"><span class="play-btn"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i></span>'
        . '<span class="media-type">' . e(ucfirst($s['media_type'])) . '</span></a>'
        . '<div class="sermon-body">' . (!empty($s['category_name']) ? '<span class="tag">' . e($s['category_name']) . '</span>' : '')
        . '<h3><a href="' . e($link) . '">' . e($s['title']) . '</a></h3>'
        . '<p class="meta">' . e($s['speaker']) . ' · ' . e(format_date($s['sermon_date'])) . '</p>'
        . ($s['scripture'] ? '<p class="scripture"><i class="fa-solid fa-book-bible" aria-hidden="true"></i> ' . e($s['scripture']) . '</p>' : '')
        . '</div></article>';
}

function social_icons(string $class = 'social-icons'): string
{
    $html = '<ul class="' . e($class) . '">';
    foreach (social_links() as $s) {
        $html .= '<li><a href="' . e($s['url']) . '" target="_blank" rel="noopener" aria-label="' . e($s['platform']) . '"><i class="fa-brands ' . e($s['icon']) . '" aria-hidden="true"></i></a></li>';
    }
    return $html . '</ul>';
}

function flash_toasts(): string
{
    $html = '';
    foreach (take_flashes() as $f) {
        $html .= '<div class="js-flash" data-type="' . e($f['type']) . '" hidden>' . e($f['message']) . '</div>';
    }
    return $html;
}
