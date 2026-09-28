<?php
require __DIR__ . '/partials/init.php';

$publicLinks = ['our-story' => 'about.php', 'vision' => 'about.php', 'mission' => 'about.php', 'statement-of-faith' => 'about.php#faith', 'values' => 'about.php', 'privacy-policy' => 'privacy.php', 'terms-of-use' => 'terms.php'];

(new CrudController([
    'table'    => 'pages',
    'title'    => 'Pages',
    'singular' => 'Page',
    'icon'     => 'fa-file-lines',
    'intro'    => 'Editable content blocks for the About page (story, vision, mission, faith, values) and legal pages.',
    'perms'    => ['view' => 'pages.view', 'create' => false, 'edit' => 'pages.edit', 'delete' => false],
    'search'   => ['t.title', 't.content'],
    'order'    => 't.id',
    'view_url' => fn($r) => isset($publicLinks[$r['slug']]) ? url($publicLinks[$r['slug']]) : null,
    'columns'  => [
        ['key' => 'title', 'label' => 'Page', 'sub' => 'slug'],
        ['key' => 'content', 'label' => 'Content', 'type' => 'excerpt', 'length' => 90],
        ['key' => 'updated_at', 'label' => 'Last Updated', 'type' => 'datetime'],
    ],
    'fields'   => [
        'title'            => ['label' => 'Title', 'required' => true, 'max' => 200],
        'content'          => ['label' => 'Content', 'type' => 'html', 'rows' => 12, 'help' => 'Basic HTML allowed: <p>, <strong>, <em>, <ul>/<ol>/<li>, <h3>, <blockquote>, <a href>. Scripts and styles are removed automatically.'],
        'meta_description' => ['label' => 'Meta Description (SEO)', 'max' => 300],
    ],
    'before_save' => function (array $data) {
        $data['updated_by'] = user_id();
        return $data;
    },
]))->handle();
