<?php
require __DIR__ . '/partials/init.php';

$types = ['mobile_money' => 'Mobile Money', 'bank_transfer' => 'Bank Transfer', 'online_gateway' => 'Online Payment Gateway', 'cash' => 'In Person / Cash', 'other' => 'Other'];
$drivers = [];
foreach (PaymentGateway::drivers() as $key => $class) {
    $drivers[$key] = (new $class())->label();
}

(new CrudController([
    'table'    => 'giving_methods',
    'title'    => 'Payment Methods',
    'singular' => 'Payment Method',
    'icon'     => 'fa-wallet',
    'nav'      => 'giving',
    'module'   => 'giving',
    'intro'    => 'Everything shown on the public Give page comes from here — nothing is hard-coded.',
    'tabs'     => require __DIR__ . '/partials/giving_tabs.php',
    'perms'    => ['view' => 'giving.manage', 'create' => 'giving.manage', 'edit' => 'giving.manage', 'delete' => 'giving.manage', 'publish' => 'giving.manage'],
    'order'    => 't.sort_order, t.name',
    'toggle'   => ['is_active', '1', '0'],
    'columns'  => [
        ['key' => 'icon', 'label' => '', 'type' => 'icon'],
        ['key' => 'name', 'label' => 'Method', 'sub' => 'provider'],
        ['key' => 'type', 'label' => 'Type', 'type' => 'custom', 'render' => fn($r) => e($types[$r['type']] ?? $r['type'])],
        ['key' => 'account_number', 'label' => 'Account / Number', 'type' => 'excerpt'],
        ['key' => 'is_active', 'label' => 'Status', 'type' => 'badge'],
    ],
    'fields'   => [
        'name'           => ['label' => 'Display Name', 'required' => true, 'max' => 100, 'col' => 6],
        'type'           => ['label' => 'Type', 'type' => 'select', 'required' => true, 'options' => $types, 'col' => 6],
        'provider'       => ['label' => 'Provider / Bank', 'max' => 100, 'col' => 6, 'placeholder' => 'e.g. Orange Money, Ecobank Liberia'],
        'account_name'   => ['label' => 'Account Name', 'max' => 150, 'col' => 6],
        'account_number' => ['label' => 'Account / Phone Number', 'max' => 100, 'col' => 6],
        'gateway_driver' => ['label' => 'Gateway Driver (online only)', 'type' => 'select', 'options' => $drivers, 'col' => 6],
        'instructions'   => ['label' => 'Giving Instructions', 'type' => 'textarea', 'rows' => 3],
        'icon'           => ['label' => 'Icon', 'max' => 60, 'col' => 4, 'default' => 'fa-wallet'],
        'sort_order'     => ['label' => 'Sort Order', 'type' => 'number', 'col' => 4, 'default' => 0, 'empty' => 0],
        'is_active'      => ['label' => 'Active', 'type' => 'checkbox', 'check_label' => 'Show on Give page', 'col' => 4, 'default' => 1],
    ],
]))->handle();
