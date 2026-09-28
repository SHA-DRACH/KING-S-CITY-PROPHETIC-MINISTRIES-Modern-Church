<?php
require __DIR__ . '/partials/init.php';

$departments = fn() => array_column(Department::options(), 'name', 'id');

(new CrudController([
    'table'    => 'announcements',
    'title'    => 'Announcements',
    'singular' => 'Announcement',
    'icon'     => 'fa-bullhorn',
    'intro'    => 'Active announcements (within their date range) appear on the homepage.',
    'perms'    => ['view' => 'announcements.view', 'create' => 'announcements.create', 'edit' => 'announcements.edit', 'delete' => 'announcements.delete', 'publish' => 'announcements.publish'],
    'select'   => 'SELECT t.*, d.name AS department_name, CONCAT(u.first_name, " ", u.last_name) AS author
                   FROM announcements t LEFT JOIN departments d ON d.id = t.department_id LEFT JOIN users u ON u.id = t.created_by',
    'search'   => ['t.title', 't.description'],
    'filters'  => [
        'status'        => ['label' => 'Statuses', 'options' => ['published' => 'Published', 'draft' => 'Draft']],
        'department_id' => ['label' => 'Departments', 'options' => $departments],
    ],
    'order'    => 't.created_at DESC',
    'scope'    => 'department_id',
    'owner'    => 'created_by',
    'toggle'   => ['status', 'published', 'draft'],
    'columns'  => [
        ['key' => 'title', 'label' => 'Announcement', 'sub' => 'author'],
        ['key' => 'department_name', 'label' => 'Department', 'type' => 'excerpt'],
        ['key' => 'start_date', 'label' => 'Runs', 'type' => 'custom', 'render' => fn($r) => e(($r['start_date'] ? format_date($r['start_date']) : 'Now') . ' → ' . ($r['end_date'] ? format_date($r['end_date']) : 'No end'))],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ],
    'fields'   => [
        'title'         => ['label' => 'Title', 'required' => true, 'max' => 200],
        'description'   => ['label' => 'Description', 'type' => 'textarea', 'rows' => 4, 'required' => true],
        'department_id' => ['label' => 'Department', 'type' => 'select', 'options' => $departments, 'col' => 6],
        'link_url'      => ['label' => 'Link (optional)', 'type' => 'url', 'max' => 255, 'col' => 6],
        'start_date'    => ['label' => 'Start Date', 'type' => 'date', 'col' => 6, 'default' => date('Y-m-d')],
        'end_date'      => ['label' => 'End Date', 'type' => 'date', 'col' => 6],
        'image'         => ['label' => 'Image', 'type' => 'file', 'upload' => 'image', 'dir' => 'images', 'col' => 6],
        'status'        => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['draft' => 'Draft', 'published' => 'Published'], 'publish' => true, 'col' => 6, 'default' => 'draft'],
    ],
]))->handle();
