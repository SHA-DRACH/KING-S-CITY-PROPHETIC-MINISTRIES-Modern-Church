<?php
require __DIR__ . '/partials/init.php';

$platforms = [
    'fa-facebook-f' => 'Facebook', 'fa-youtube' => 'YouTube', 'fa-tiktok' => 'TikTok', 'fa-instagram' => 'Instagram',
    'fa-whatsapp' => 'WhatsApp', 'fa-x-twitter' => 'X (Twitter)', 'fa-telegram' => 'Telegram', 'fa-spotify' => 'Spotify',
];

(new CrudController([
    'table'    => 'social_links',
    'title'    => 'Social Media',
    'singular' => 'Social Link',
    'icon'     => 'fa-share-nodes',
    'nav'      => 'social',
    'module'   => 'settings',
    'intro'    => 'Links shown in the website header, footer and contact page.',
    'perms'    => ['view' => 'website_settings.view', 'create' => 'website_settings.edit', 'edit' => 'website_settings.edit', 'delete' => 'website_settings.edit', 'publish' => 'website_settings.edit'],
    'order'    => 't.sort_order, t.id',
    'toggle'   => ['is_active', '1', '0'],
    'modal_size' => '',
    'columns'  => [
        ['key' => 'icon', 'label' => '', 'type' => 'custom', 'render' => fn($r) => '<span class="icon-chip"><i class="fa-brands ' . e($r['icon']) . '"></i></span>'],
        ['key' => 'platform', 'label' => 'Platform', 'sub' => 'url'],
        ['key' => 'sort_order', 'label' => 'Order', 'type' => 'excerpt'],
        ['key' => 'is_active', 'label' => 'Status', 'type' => 'badge'],
    ],
    'fields'   => [
        'icon'       => ['label' => 'Platform', 'type' => 'select', 'required' => true, 'options' => $platforms],
        'url'        => ['label' => 'URL', 'type' => 'url', 'required' => true, 'max' => 255, 'placeholder' => 'https://'],
        'sort_order' => ['label' => 'Sort Order', 'type' => 'number', 'col' => 6, 'default' => 0, 'empty' => 0],
        'is_active'  => ['label' => 'Active', 'type' => 'checkbox', 'check_label' => 'Show on website', 'col' => 6, 'default' => 1],
    ],
    'before_save' => function (array $data) use ($platforms) {
        $data['platform'] = $platforms[$data['icon']] ?? 'Link';
        return $data;
    },
]))->handle();
