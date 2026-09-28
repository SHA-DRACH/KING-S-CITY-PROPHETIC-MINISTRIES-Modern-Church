<?php
require __DIR__ . '/partials/init.php';

// Opening a message marks it as read.
if (($_GET['action'] ?? '') === 'get' && can('messages.view')) {
    DB::update('contact_messages', ['is_read' => 1], 'id = ?', [query_int('id')]);
}

(new CrudController([
    'table'    => 'contact_messages',
    'title'    => 'Messages',
    'singular' => 'Message',
    'icon'     => 'fa-envelope',
    'nav'      => 'messages',
    'module'   => 'messages',
    'intro'    => 'Messages sent from the website contact form.',
    'perms'    => ['view' => 'messages.view', 'create' => false, 'edit' => 'messages.view', 'delete' => 'messages.delete'],
    'search'   => ['t.name', 't.email', 't.subject', 't.message'],
    'filters'  => ['is_read' => ['label' => 'Messages', 'options' => ['0' => 'Unread', '1' => 'Read']]],
    'order'    => 't.is_read ASC, t.created_at DESC',
    'columns'  => [
        ['key' => 'name', 'label' => 'From', 'type' => 'custom', 'render' => fn($r) => ($r['is_read'] ? '' : '<span class="unread-dot" title="Unread"></span>') . '<strong class="cell-title">' . e($r['name']) . '</strong><div class="cell-sub">' . e($r['email']) . '</div>'],
        ['key' => 'subject', 'label' => 'Subject', 'type' => 'excerpt', 'length' => 50],
        ['key' => 'message', 'label' => 'Message', 'type' => 'excerpt', 'length' => 70],
        ['key' => 'created_at', 'label' => 'Received', 'type' => 'custom', 'render' => fn($r) => e(time_ago($r['created_at']))],
    ],
    'fields'   => [
        'name'    => ['type' => 'static', 'label' => 'From', 'col' => 6, 'render' => fn($r) => e($r['name']) . '<div><a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a> ' . e($r['phone'] ?? '') . '</div>'],
        'subject' => ['type' => 'static', 'label' => 'Subject', 'col' => 6],
        'message' => ['type' => 'static', 'label' => 'Message', 'render' => fn($r) => '<div class="quote-box">' . nl2br_e($r['message']) . '</div>'],
        'is_read' => ['label' => 'Read', 'type' => 'checkbox', 'check_label' => 'Mark as read'],
    ],
]))->handle();
