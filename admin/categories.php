<?php
require __DIR__ . '/partials/init.php';

// One page manages the three category lists; each needs its module's edit permission.
$types = [
    'sermon'  => ['table' => 'sermon_categories',  'label' => 'Sermon',  'perm' => 'sermons.edit', 'used' => 'sermons'],
    'event'   => ['table' => 'event_categories',   'label' => 'Event',   'perm' => 'events.edit',  'used' => 'events'],
    'gallery' => ['table' => 'gallery_categories', 'label' => 'Gallery', 'perm' => 'gallery.edit', 'used' => 'gallery'],
];
$available = array_filter($types, fn($t) => can($t['perm']));
if (!$available) {
    abort(403, 'You do not have permission to manage categories.');
}
$type = $_GET['type'] ?? $_POST['type'] ?? array_key_first($available);
if (!isset($available[$type])) {
    abort(403, 'You do not have permission to manage these categories.');
}
$t = $available[$type];

// Tab links rendered via the intro area
$tabs = '';
foreach ($available as $k => $v) {
    $tabs .= '<a class="tab-link' . ($k === $type ? ' active' : '') . '" href="?type=' . $k . '">' . e($v['label']) . ' Categories</a>';
}
$_GET['type'] = $type;

ob_start();
(new CrudController([
    'table'    => $t['table'],
    'title'    => $t['label'] . ' Categories',
    'singular' => $t['label'] . ' Category',
    'icon'     => 'fa-tags',
    'nav'      => 'categories',
    'module'   => 'categories',
    'perms'    => ['view' => $t['perm'], 'create' => $t['perm'], 'edit' => $t['perm'], 'delete' => $t['perm']],
    'select'   => "SELECT t.*, (SELECT COUNT(*) FROM {$t['used']} x WHERE x.category_id = t.id) AS item_count FROM {$t['table']} t",
    'search'   => ['t.name'],
    'order'    => 't.sort_order, t.name',
    'slug_from' => 'name',
    'modal_size' => '',
    'columns'  => [
        ['key' => 'name', 'label' => 'Name', 'sub' => 'slug'],
        ['key' => 'item_count', 'label' => 'Items', 'type' => 'excerpt'],
        ['key' => 'sort_order', 'label' => 'Order', 'type' => 'excerpt'],
    ],
    'fields'   => [
        'name'       => ['label' => 'Name', 'required' => true, 'max' => 80],
        'sort_order' => ['label' => 'Sort Order', 'type' => 'number', 'default' => 0, 'empty' => 0],
    ],
]))->handle();
$html = ob_get_clean();

// Inject the tab bar under the page heading and keep ?type on form posts.
$html = preg_replace('/(<div class="page-head">)/', '<div class="tab-bar mb-3">' . $tabs . '</div>$1', $html, 1);
echo str_replace('<input type="hidden" name="action" value="save">', '<input type="hidden" name="action" value="save"><input type="hidden" name="type" value="' . e($type) . '">', $html);
