<?php
require __DIR__ . '/partials/init.php';

$categories = fn() => array_column(Sermon::categories(), 'name', 'id');

(new CrudController([
    'table'    => 'sermons',
    'title'    => 'Sermons',
    'singular' => 'Sermon',
    'icon'     => 'fa-book-bible',
    'intro'    => 'Upload video/audio sermons or link YouTube/Facebook recordings.',
    'perms'    => ['view' => 'sermons.view', 'create' => 'sermons.create', 'edit' => 'sermons.edit', 'delete' => 'sermons.delete', 'publish' => 'sermons.publish'],
    'select'   => 'SELECT t.*, c.name AS category_name FROM sermons t LEFT JOIN sermon_categories c ON c.id = t.category_id',
    'search'   => ['t.title', 't.speaker', 't.scripture'],
    'filters'  => [
        'status'      => ['label' => 'Statuses', 'options' => ['published' => 'Published', 'draft' => 'Draft']],
        'category_id' => ['label' => 'Categories', 'options' => $categories],
        'media_type'  => ['label' => 'Media Types', 'options' => ['video' => 'Video', 'audio' => 'Audio']],
    ],
    'order'    => 't.sermon_date DESC, t.id DESC',
    'slug_from' => 'title',
    'owner'    => 'created_by',
    'toggle'   => ['status', 'published', 'draft'],
    'view_url' => fn($r) => $r['status'] === 'published' ? url('sermon-details.php?slug=' . rawurlencode($r['slug'])) : null,
    'columns'  => [
        ['key' => 'thumbnail', 'label' => '', 'type' => 'image', 'fallback' => 'assets/images/placeholders/sermon.svg'],
        ['key' => 'title', 'label' => 'Sermon', 'sub' => 'speaker'],
        ['key' => 'category_name', 'label' => 'Category', 'type' => 'excerpt'],
        ['key' => 'media_type', 'label' => 'Type', 'type' => 'custom', 'render' => fn($r) => '<i class="fa-solid ' . ($r['media_type'] === 'audio' ? 'fa-headphones' : 'fa-video') . ' text-muted"></i> ' . e(ucfirst($r['media_type']))],
        ['key' => 'sermon_date', 'label' => 'Date', 'type' => 'date'],
        ['key' => 'views', 'label' => 'Views', 'type' => 'excerpt'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ],
    'fields'   => [
        'title'          => ['label' => 'Title', 'required' => true, 'max' => 200, 'col' => 8],
        'sermon_date'    => ['label' => 'Date', 'type' => 'date', 'required' => true, 'col' => 4, 'default' => date('Y-m-d')],
        'speaker'        => ['label' => 'Speaker', 'required' => true, 'max' => 120, 'col' => 6, 'default' => 'Prophet Mark Dorbor'],
        'category_id'    => ['label' => 'Category', 'type' => 'select', 'options' => $categories, 'col' => 6],
        'scripture'      => ['label' => 'Scripture Reference', 'max' => 160, 'col' => 6, 'placeholder' => 'e.g. John 3:16'],
        'media_type'     => ['label' => 'Media Type', 'type' => 'select', 'required' => true, 'options' => ['video' => 'Video', 'audio' => 'Audio'], 'col' => 6, 'default' => 'video'],
        'description'    => ['label' => 'Description', 'type' => 'textarea', 'rows' => 4],
        'video_url'      => ['label' => 'YouTube / Facebook URL', 'type' => 'url', 'max' => 255, 'section' => 'Media', 'help' => 'Paste a link — or upload a video file below.'],
        'video_file'     => ['label' => 'Video File (MP4/WebM)', 'type' => 'file', 'upload' => 'video', 'dir' => 'sermons', 'col' => 6],
        'audio_file'     => ['label' => 'Audio File (MP3/M4A)', 'type' => 'file', 'upload' => 'audio', 'dir' => 'sermons', 'col' => 6],
        'thumbnail'      => ['label' => 'Thumbnail Image', 'type' => 'file', 'upload' => 'image', 'dir' => 'sermons', 'col' => 6],
        'is_featured'    => ['label' => 'Featured', 'type' => 'checkbox', 'check_label' => 'Feature this sermon', 'col' => 3],
        'allow_download' => ['label' => 'Download', 'type' => 'checkbox', 'check_label' => 'Allow download', 'col' => 3],
        'status'         => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['draft' => 'Draft', 'published' => 'Published'], 'publish' => true, 'unpublished' => 'draft', 'col' => 6, 'default' => 'draft'],
    ],
]))->handle();
