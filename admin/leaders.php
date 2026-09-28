<?php
require __DIR__ . '/partials/init.php';

(new CrudController([
    'table'    => 'leaders',
    'title'    => 'Leadership',
    'singular' => 'Leader',
    'icon'     => 'fa-people-roof',
    'intro'    => 'Leaders shown in the Leadership section of the About page.',
    'perms'    => ['view' => 'pages.view', 'create' => 'pages.edit', 'edit' => 'pages.edit', 'delete' => 'pages.edit', 'publish' => 'pages.edit'],
    'search'   => ['t.name', 't.position'],
    'order'    => 't.sort_order, t.name',
    'toggle'   => ['is_active', '1', '0'],
    'columns'  => [
        ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'assets/images/placeholders/pastor.svg'],
        ['key' => 'name', 'label' => 'Name', 'sub' => 'position'],
        ['key' => 'sort_order', 'label' => 'Order', 'type' => 'excerpt'],
        ['key' => 'is_active', 'label' => 'Status', 'type' => 'badge'],
    ],
    'fields'   => [
        'name'       => ['label' => 'Name', 'required' => true, 'max' => 120, 'col' => 6],
        'position'   => ['label' => 'Position', 'required' => true, 'max' => 120, 'col' => 6],
        'bio'        => ['label' => 'Short Bio', 'type' => 'textarea', 'rows' => 3],
        'photo'      => ['label' => 'Photo', 'type' => 'file', 'upload' => 'image', 'dir' => 'profiles', 'col' => 6],
        'sort_order' => ['label' => 'Sort Order', 'type' => 'number', 'col' => 3, 'default' => 0, 'empty' => 0],
        'is_active'  => ['label' => 'Visible', 'type' => 'checkbox', 'check_label' => 'Show on website', 'col' => 3, 'default' => 1],
    ],
]))->handle();
