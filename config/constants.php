<?php
/**
 * Filesystem paths and fixed system constants.
 */
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('APP_VERSION', '1.0.0');

// Upload rules: extension => allowed MIME types, grouped by file type.
const UPLOAD_RULES = [
    'image' => [
        'max_mb' => 20,
        'types'  => [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
        ],
    ],
    'video' => [
        'max_mb' => 1024, // overridden by the max_video_upload_mb system setting
        'types'  => [
            // Phone/camera MP4s are often reported as QuickTime or M4V containers
            'mp4'  => ['video/mp4', 'application/mp4', 'video/x-m4v', 'video/quicktime'],
            'm4v'  => ['video/mp4', 'video/x-m4v'],
            'webm' => ['video/webm', 'audio/webm'],
        ],
    ],
    'audio' => [
        'max_mb' => 60,
        'types'  => [
            'mp3' => ['audio/mpeg', 'audio/mp3'],
            'm4a' => ['audio/mp4', 'audio/x-m4a', 'audio/m4a'],
        ],
    ],
    'document' => [
        'max_mb' => 15,
        'types'  => [
            'pdf' => ['application/pdf'],
        ],
    ],
];

// Public pages that can have their own background (hero) video
const HERO_PAGES = [
    'home'        => 'Homepage',
    'about'       => 'About',
    'ministries'  => 'Ministries',
    'sermons'     => 'Sermons',
    'events'      => 'Events',
    'pastor'      => 'Our Pastor',
    'giving'      => 'Give',
    'gallery'     => 'Gallery',
    'prayer'      => 'Prayer Request',
    'testimonies' => 'Testimonies',
    'contact'     => 'Contact',
];

const PRAYER_STATUSES = [
    'new'       => 'New',
    'assigned'  => 'Assigned',
    'in_prayer' => 'In Prayer',
    'answered'  => 'Answered',
    'closed'    => 'Closed',
];

const TESTIMONY_STATUSES = [
    'pending'   => 'Pending',
    'approved'  => 'Approved',
    'rejected'  => 'Rejected',
    'published' => 'Published',
];

const GIVING_STATUSES = [
    'pending'   => 'Pending',
    'confirmed' => 'Confirmed',
    'failed'    => 'Failed',
    'refunded'  => 'Refunded',
];
