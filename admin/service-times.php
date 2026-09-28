<?php
require __DIR__ . '/partials/init.php';

$days = array_combine($d = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Daily'], $d);

(new CrudController([
    'table'    => 'service_times',
    'title'    => 'Service Times',
    'singular' => 'Service',
    'icon'     => 'fa-clock',
    'nav'      => 'services',
    'module'   => 'settings',
    'intro'    => 'The weekly service schedule shown in the footer, homepage and contact page.',
    'perms'    => ['view' => 'website_settings.view', 'create' => 'website_settings.edit', 'edit' => 'website_settings.edit', 'delete' => 'website_settings.edit', 'publish' => 'website_settings.edit'],
    'order'    => 't.sort_order, t.id',
    'toggle'   => ['is_active', '1', '0'],
    'modal_size' => '',
    'columns'  => [
        ['key' => 'name', 'label' => 'Service', 'sub' => 'description'],
        ['key' => 'day_of_week', 'label' => 'Day', 'type' => 'excerpt'],
        ['key' => 'start_time', 'label' => 'Time', 'type' => 'custom', 'render' => fn($r) => e(time_range($r['start_time'], $r['end_time']))],
        ['key' => 'is_active', 'label' => 'Status', 'type' => 'badge'],
    ],
    'fields'   => [
        'name'        => ['label' => 'Service Name', 'required' => true, 'max' => 120],
        'day_of_week' => ['label' => 'Day', 'type' => 'select', 'required' => true, 'options' => $days, 'col' => 4],
        'start_time'  => ['label' => 'Start', 'type' => 'time', 'required' => true, 'col' => 4],
        'end_time'    => ['label' => 'End', 'type' => 'time', 'col' => 4],
        'description' => ['label' => 'Description', 'max' => 255],
        'sort_order'  => ['label' => 'Sort Order', 'type' => 'number', 'col' => 6, 'default' => 0, 'empty' => 0],
        'is_active'   => ['label' => 'Active', 'type' => 'checkbox', 'check_label' => 'Show on website', 'col' => 6, 'default' => 1],
    ],
]))->handle();
