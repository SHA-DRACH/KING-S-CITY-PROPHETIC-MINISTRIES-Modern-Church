<?php
require __DIR__ . '/partials/init.php';

$categories = fn() => array_column(Gallery::categories(), 'name', 'id');
$departments = fn() => array_column(Department::options(), 'name', 'id');

(new CrudController([
    'table'    => 'gallery',
    'title'    => 'Gallery',
    'singular' => 'Gallery Item',
    'icon'     => 'fa-images',
    'intro'    => 'Photos and videos shown in the public gallery lightbox.',
    'perms'    => ['view' => 'gallery.view', 'create' => 'gallery.upload', 'edit' => 'gallery.edit', 'delete' => 'gallery.delete', 'publish' => ['gallery.edit', 'gallery.upload']],
    'select'   => 'SELECT t.*, c.name AS category_name FROM gallery t LEFT JOIN gallery_categories c ON c.id = t.category_id',
    'search'   => ['t.title', 't.caption'],
    'filters'  => [
        'category_id'  => ['label' => 'Categories', 'options' => $categories],
        'is_published' => ['label' => 'Visibility', 'options' => ['1' => 'Published', '0' => 'Hidden']],
    ],
    'order'    => 't.created_at DESC, t.id DESC',
    'owner'    => 'uploaded_by',
    'scope'    => has_all_department_access() ? null : 'department_id',
    'toggle'   => ['is_published', '1', '0'],
    'columns'  => [
        ['key' => 'file_path', 'label' => '', 'type' => 'image'],
        ['key' => 'title', 'label' => 'Title', 'sub' => 'caption'],
        ['key' => 'category_name', 'label' => 'Category', 'type' => 'excerpt'],
        ['key' => 'media_type', 'label' => 'Type', 'type' => 'excerpt'],
        ['key' => 'is_published', 'label' => 'Status', 'type' => 'badge'],
    ],
    'fields'   => [
        'title'         => ['label' => 'Title', 'required' => true, 'max' => 200, 'col' => 8],
        'media_type'    => ['label' => 'Type', 'type' => 'select', 'required' => true, 'options' => ['image' => 'Photo', 'video' => 'Video'], 'col' => 4, 'default' => 'image'],
        'category_id'   => ['label' => 'Category', 'type' => 'select', 'options' => $categories, 'col' => 6],
        'department_id' => ['label' => 'Department', 'type' => 'select', 'options' => $departments, 'col' => 6],
        'file_path'     => ['label' => 'Photo', 'type' => 'file', 'upload' => 'image', 'dir' => 'gallery', 'col' => 6, 'help' => 'For videos this is used as the cover image.'],
        'video_url'     => ['label' => 'Video URL (YouTube)', 'type' => 'url', 'max' => 255, 'col' => 6],
        'caption'       => ['label' => 'Caption', 'max' => 300],
        'is_published'  => ['label' => 'Published', 'type' => 'checkbox', 'check_label' => 'Show on website', 'default' => 1],
    ],
    'before_save' => function (array $data, ?array $existing) {
        if (($data['media_type'] ?? '') === 'image' && empty($data['file_path']) && empty($existing['file_path'])) {
            throw new ValidationError(['file_path' => 'Please choose a photo to upload.']);
        }
        if (($data['media_type'] ?? '') === 'video' && empty($data['video_url'])) {
            throw new ValidationError(['video_url' => 'Add the YouTube link for this video.']);
        }
        return $data;
    },
]))->handle();
