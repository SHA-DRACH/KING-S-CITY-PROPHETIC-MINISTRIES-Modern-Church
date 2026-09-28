<?php
require __DIR__ . '/partials/init.php';

$staff = fn() => array_column(User::staffOptions(), 'name', 'id');

(new CrudController([
    'table'    => 'departments',
    'title'    => 'Departments',
    'singular' => 'Department',
    'icon'     => 'fa-sitemap',
    'intro'    => 'Ministry departments. Public ones appear on the Ministries page; heads manage their department\'s content.',
    'perms'    => ['view' => 'departments.view', 'create' => 'departments.create', 'edit' => 'departments.edit', 'delete' => 'departments.delete', 'publish' => 'departments.edit'],
    'select'   => 'SELECT t.*, CONCAT(u.first_name, " ", u.last_name) AS head_user_name,
                   (SELECT COUNT(*) FROM department_users du WHERE du.department_id = t.id) AS member_count
                   FROM departments t LEFT JOIN users u ON u.id = t.head_user_id',
    'search'   => ['t.name', 't.description'],
    'filters'  => ['status' => ['label' => 'Statuses', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']]],
    'order'    => 't.sort_order, t.name',
    'scope'    => 'id',
    'slug_from' => 'name',
    'toggle'   => ['status', 'active', 'inactive'],
    'view_url' => fn($r) => url('ministries.php#' . $r['slug']),
    'columns'  => [
        ['key' => 'icon', 'label' => '', 'type' => 'icon'],
        ['key' => 'name', 'label' => 'Department', 'sub' => 'meeting_schedule'],
        ['key' => 'head_user_name', 'label' => 'Head', 'type' => 'custom', 'render' => fn($r) => e($r['head_user_name'] ?: ($r['head_name'] ?: '—')) . ($r['head_user_name'] ? ' <i class="fa-solid fa-circle-check text-success" title="Has an account"></i>' : '')],
        ['key' => 'member_count', 'label' => 'Members', 'type' => 'excerpt'],
        ['key' => 'is_public', 'label' => 'Public', 'type' => 'bool'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ],
    'fields'   => [
        'name'             => ['label' => 'Name', 'required' => true, 'max' => 120, 'col' => 8],
        'icon'             => ['label' => 'Icon (Font Awesome)', 'max' => 60, 'col' => 4, 'default' => 'fa-church', 'placeholder' => 'fa-music'],
        'description'      => ['label' => 'Description', 'type' => 'textarea', 'rows' => 3],
        'head_user_id'     => ['label' => 'Department Head (user account)', 'type' => 'select', 'options' => $staff, 'col' => 6, 'perm' => 'departments.assign'],
        'head_name'        => ['label' => 'Head Display Name', 'max' => 120, 'col' => 6, 'help' => 'Shown publicly if the head has no account.'],
        'contact_email'    => ['label' => 'Contact Email', 'type' => 'email', 'max' => 190, 'col' => 6],
        'contact_phone'    => ['label' => 'Contact Phone', 'type' => 'tel', 'max' => 40, 'col' => 6],
        'meeting_schedule' => ['label' => 'Meeting Schedule', 'max' => 255, 'placeholder' => 'e.g. Saturdays, 3:00 PM – 5:00 PM'],
        'image'            => ['label' => 'Image / Logo', 'type' => 'file', 'upload' => 'image', 'dir' => 'images', 'col' => 6],
        'sort_order'       => ['label' => 'Sort Order', 'type' => 'number', 'col' => 3, 'default' => 0, 'empty' => 0],
        'status'           => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'col' => 3, 'default' => 'active'],
        'is_public'        => ['label' => 'Public', 'type' => 'checkbox', 'check_label' => 'Show on the public Ministries page', 'default' => 1],
    ],
    'after_save' => function (int $id, array $data) {
        // Keep the membership table in sync with the chosen head.
        if (!empty($data['head_user_id'])) {
            DB::query('INSERT INTO department_users (department_id, user_id, is_head) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE is_head = 1', [$id, $data['head_user_id']]);
        }
    },
]))->handle();
