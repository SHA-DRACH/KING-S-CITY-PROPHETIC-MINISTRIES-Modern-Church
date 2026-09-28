<?php
require __DIR__ . '/partials/init.php';

(new CrudController([
    'table'    => 'testimonies',
    'title'    => 'Testimonies',
    'singular' => 'Testimony',
    'icon'     => 'fa-heart',
    'intro'    => 'Submitted testimonies must be approved and published before they appear on the website.',
    'perms'    => ['view' => 'testimonies.view', 'create' => false, 'edit' => ['testimonies.approve', 'testimonies.reject'], 'delete' => 'testimonies.delete'],
    'search'   => ['t.name', 't.title', 't.testimony'],
    'filters'  => ['status' => ['label' => 'Statuses', 'options' => TESTIMONY_STATUSES]],
    'order'    => "FIELD(t.status, 'pending', 'approved', 'published', 'rejected'), t.created_at DESC",
    'view_url' => fn($r) => $r['status'] === 'published' ? url('testimonies.php') : null,
    'columns'  => [
        ['key' => 'photo', 'label' => '', 'type' => 'image', 'fallback' => 'assets/images/placeholders/pastor.svg'],
        ['key' => 'name', 'label' => 'From', 'sub' => 'title'],
        ['key' => 'testimony', 'label' => 'Testimony', 'type' => 'excerpt', 'length' => 80],
        ['key' => 'permission_to_publish', 'label' => 'May Publish', 'type' => 'bool'],
        ['key' => 'created_at', 'label' => 'Submitted', 'type' => 'date'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ],
    'fields'   => [
        'name'      => ['type' => 'static', 'label' => 'From', 'col' => 6, 'render' => fn($r) => e($r['name']) . '<div class="small text-muted">' . e($r['email'] ?? '') . '</div>'],
        'permission_to_publish' => ['type' => 'static', 'label' => 'Permission to Publish', 'col' => 6, 'render' => fn($r) => $r['permission_to_publish'] ? '<span class="text-success"><i class="fa-solid fa-check"></i> Given</span>' : '<span class="text-danger"><i class="fa-solid fa-xmark"></i> Not given — cannot be published</span>'],
        'testimony' => ['type' => 'static', 'label' => 'Testimony', 'render' => fn($r) => '<div class="quote-box">' . nl2br_e($r['testimony']) . '</div>'],
        'title'     => ['label' => 'Headline', 'max' => 200, 'col' => 6],
        'status'    => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => TESTIMONY_STATUSES, 'col' => 6],
    ],
    'before_save' => function (array $data, ?array $existing) {
        $status = $data['status'] ?? $existing['status'];
        if (in_array($status, ['approved', 'published'], true) && !can('testimonies.approve')) {
            throw new ValidationError(['status' => 'You do not have permission to approve testimonies.']);
        }
        if ($status === 'rejected' && !can('testimonies.reject')) {
            throw new ValidationError(['status' => 'You do not have permission to reject testimonies.']);
        }
        if ($status === 'published' && !(int) $existing['permission_to_publish']) {
            throw new ValidationError(['status' => 'The sender did not give permission to publish this testimony.']);
        }
        if ($status !== $existing['status']) {
            $data['reviewed_by'] = user_id();
            $data['reviewed_at'] = date('Y-m-d H:i:s');
        }
        return $data;
    },
]))->handle();
