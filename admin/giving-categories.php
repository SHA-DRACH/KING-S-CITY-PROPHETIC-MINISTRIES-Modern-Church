<?php
require __DIR__ . '/partials/init.php';

(new CrudController([
    'table'    => 'giving_categories',
    'title'    => 'Giving Types',
    'singular' => 'Giving Type',
    'icon'     => 'fa-tags',
    'nav'      => 'giving',
    'module'   => 'giving',
    'intro'    => 'Tithes, offerings, missions, building fund and special donations.',
    'tabs'     => require __DIR__ . '/partials/giving_tabs.php',
    'perms'    => ['view' => 'giving.manage', 'create' => 'giving.manage', 'edit' => 'giving.manage', 'delete' => 'giving.manage', 'publish' => 'giving.manage'],
    'order'    => 't.sort_order, t.name',
    'toggle'   => ['is_active', '1', '0'],
    'modal_size' => '',
    'columns'  => [
        ['key' => 'icon', 'label' => '', 'type' => 'icon'],
        ['key' => 'name', 'label' => 'Giving Type', 'sub' => 'description'],
        ['key' => 'is_active', 'label' => 'Status', 'type' => 'badge'],
    ],
    'fields'   => [
        'name'        => ['label' => 'Name', 'required' => true, 'max' => 80],
        'description' => ['label' => 'Description', 'max' => 255],
        'icon'        => ['label' => 'Icon', 'max' => 60, 'col' => 4, 'default' => 'fa-hand-holding-heart'],
        'sort_order'  => ['label' => 'Sort Order', 'type' => 'number', 'col' => 4, 'default' => 0, 'empty' => 0],
        'is_active'   => ['label' => 'Active', 'type' => 'checkbox', 'check_label' => 'Active', 'col' => 4, 'default' => 1],
    ],
]))->handle();
