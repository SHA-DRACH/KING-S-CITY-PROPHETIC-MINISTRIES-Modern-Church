<?php
require __DIR__ . '/partials/init.php';

$categories = fn() => array_column(Event::categories(), 'name', 'id');
$departments = fn() => array_column(Department::options(), 'name', 'id');

(new CrudController([
    'table'    => 'events',
    'title'    => 'Events',
    'singular' => 'Event',
    'icon'     => 'fa-calendar-days',
    'intro'    => 'Services, conferences, crusades and department programs.',
    'perms'    => ['view' => 'events.view', 'create' => 'events.create', 'edit' => 'events.edit', 'delete' => 'events.delete', 'publish' => 'events.publish'],
    'select'   => 'SELECT t.*, c.name AS category_name, d.name AS department_name FROM events t
                   LEFT JOIN event_categories c ON c.id = t.category_id LEFT JOIN departments d ON d.id = t.department_id',
    'search'   => ['t.title', 't.location', 't.organizer'],
    'filters'  => [
        'status'        => ['label' => 'Statuses', 'options' => ['published' => 'Published', 'draft' => 'Draft', 'cancelled' => 'Cancelled']],
        'category_id'   => ['label' => 'Categories', 'options' => $categories],
        'department_id' => ['label' => 'Departments', 'options' => $departments],
    ],
    'order'    => 't.event_date DESC',
    'scope'    => 'department_id',
    'slug_from' => 'title',
    'owner'    => 'created_by',
    'toggle'   => ['status', 'published', 'draft'],
    'view_url' => fn($r) => $r['status'] !== 'draft' ? url('event-details.php?slug=' . rawurlencode($r['slug'])) : null,
    'columns'  => [
        ['key' => 'image', 'label' => '', 'type' => 'image'],
        ['key' => 'title', 'label' => 'Event', 'sub' => 'location'],
        ['key' => 'event_date', 'label' => 'Date', 'type' => 'custom', 'render' => fn($r) => e(format_date($r['event_date'])) . '<div class="cell-sub">' . e(time_range($r['start_time'], $r['end_time'])) . '</div>'],
        ['key' => 'category_name', 'label' => 'Category', 'type' => 'excerpt'],
        ['key' => 'department_name', 'label' => 'Department', 'type' => 'excerpt'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ],
    'fields'   => [
        'title'         => ['label' => 'Title', 'required' => true, 'max' => 200],
        'category_id'   => ['label' => 'Category', 'type' => 'select', 'options' => $categories, 'col' => 6],
        'department_id' => ['label' => 'Department', 'type' => 'select', 'options' => $departments, 'col' => 6, 'help' => 'Department heads can only file events under their own department.'],
        'event_date'    => ['label' => 'Date', 'type' => 'date', 'required' => true, 'col' => 4],
        'start_time'    => ['label' => 'Start Time', 'type' => 'time', 'col' => 4],
        'end_time'      => ['label' => 'End Time', 'type' => 'time', 'col' => 4],
        'end_date'      => ['label' => 'End Date (multi-day)', 'type' => 'date', 'col' => 4],
        'location'      => ['label' => 'Location', 'max' => 200, 'col' => 8, 'default' => 'Omega Community, Paynesville'],
        'description'   => ['label' => 'Description', 'type' => 'textarea', 'rows' => 5],
        'organizer'     => ['label' => 'Organizer', 'max' => 120, 'col' => 6],
        'image'         => ['label' => 'Event Image', 'type' => 'file', 'upload' => 'image', 'dir' => 'events', 'col' => 6],
        'requires_registration' => ['label' => 'Registration', 'type' => 'checkbox', 'check_label' => 'Requires registration', 'col' => 6],
        'registration_url' => ['label' => 'Registration Link', 'type' => 'url', 'max' => 255, 'col' => 6],
        'is_featured'   => ['label' => 'Featured', 'type' => 'checkbox', 'check_label' => 'Feature on homepage', 'col' => 6],
        'status'        => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['draft' => 'Draft', 'published' => 'Published', 'cancelled' => 'Cancelled'], 'publish' => true, 'col' => 6, 'default' => 'draft'],
    ],
    'before_save' => function (array $data) {
        if (!empty($data['end_date']) && $data['end_date'] < ($data['event_date'] ?? '')) {
            throw new ValidationError(['end_date' => 'End date cannot be before the start date.']);
        }
        if (!empty($data['requires_registration']) && empty($data['registration_url'])) {
            throw new ValidationError(['registration_url' => 'Add a registration link or untick "Requires registration".']);
        }
        return $data;
    },
]))->handle();
