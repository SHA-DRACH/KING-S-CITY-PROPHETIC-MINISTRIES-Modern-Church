<?php
require __DIR__ . '/partials/init.php';

$staff = fn() => array_column(User::staffOptions(), 'name', 'id');

(new CrudController([
    'table'    => 'prayer_requests',
    'title'    => 'Prayer Requests',
    'singular' => 'Prayer Request',
    'icon'     => 'fa-hands-praying',
    'intro'    => 'Private by default. Only requests the visitor marked "public" ever appear on the website prayer wall.',
    'perms'    => ['view' => 'prayer_requests.view', 'create' => false, 'edit' => ['prayer_requests.update', 'prayer_requests.assign'], 'delete' => 'prayer_requests.delete'],
    'select'   => 'SELECT t.*, CONCAT(u.first_name, " ", u.last_name) AS assignee FROM prayer_requests t LEFT JOIN users u ON u.id = t.assigned_to',
    'search'   => ['t.name', 't.email', 't.request'],
    'filters'  => [
        'status'      => ['label' => 'Statuses', 'options' => PRAYER_STATUSES],
        'is_public'   => ['label' => 'Visibility', 'options' => ['0' => 'Private', '1' => 'Public']],
        'assigned_to' => ['label' => 'Assignees', 'options' => $staff],
    ],
    'order'    => "FIELD(t.status, 'new', 'assigned', 'in_prayer', 'answered', 'closed'), t.created_at DESC",
    'columns'  => [
        ['key' => 'name', 'label' => 'From', 'type' => 'custom', 'render' => fn($r) => '<strong class="cell-title">' . e($r['name']) . '</strong><div class="cell-sub">' . e(trim(($r['email'] ?? '') . ' ' . ($r['phone'] ?? ''))) . '</div>'],
        ['key' => 'request', 'label' => 'Request', 'type' => 'excerpt', 'length' => 80],
        ['key' => 'is_public', 'label' => 'Visibility', 'type' => 'custom', 'render' => fn($r) => $r['is_public'] ? '<span class="badge text-bg-info"><i class="fa-solid fa-globe"></i> Public</span>' : '<span class="badge text-bg-light"><i class="fa-solid fa-lock"></i> Private</span>'],
        ['key' => 'assignee', 'label' => 'Assigned To', 'type' => 'excerpt'],
        ['key' => 'created_at', 'label' => 'Received', 'type' => 'custom', 'render' => fn($r) => e(time_ago($r['created_at']))],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ],
    'fields'   => [
        'name'        => ['type' => 'static', 'label' => 'From', 'col' => 6, 'render' => fn($r) => e($r['name']) . ($r['is_public'] ? ' <span class="badge text-bg-info">Public</span>' : ' <span class="badge text-bg-light">Private</span>')],
        'email'       => ['type' => 'static', 'label' => 'Contact', 'col' => 6, 'render' => fn($r) => e(trim(($r['email'] ?? '') . '  ' . ($r['phone'] ?? ''))) ?: '—'],
        'request'     => ['type' => 'static', 'label' => 'Prayer Request', 'render' => fn($r) => '<div class="quote-box">' . nl2br_e($r['request']) . '</div>'],
        'status'      => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => PRAYER_STATUSES, 'col' => 6, 'perm' => 'prayer_requests.update'],
        'assigned_to' => ['label' => 'Assign To', 'type' => 'select', 'options' => $staff, 'col' => 6, 'perm' => 'prayer_requests.assign'],
        'notes'       => ['label' => 'Internal Notes (never public)', 'type' => 'textarea', 'rows' => 3, 'perm' => 'prayer_requests.update'],
    ],
    'before_save' => function (array $data, ?array $existing) {
        // Assigning someone moves a new request to "assigned" automatically.
        if (!empty($data['assigned_to']) && ($data['status'] ?? $existing['status']) === 'new') {
            $data['status'] = 'assigned';
        }
        return $data;
    },
    'after_save' => function (int $id, array $data) {
        if (!empty($data['assigned_to'])) {
            log_activity('assign', 'prayer_requests', "Assigned prayer request #$id to user #" . (int) $data['assigned_to']);
        }
    },
]))->handle();
