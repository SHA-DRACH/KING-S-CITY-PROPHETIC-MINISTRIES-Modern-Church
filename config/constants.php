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
        'max_mb' => 8,
        'types'  => [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
        ],
    ],
    'video' => [
        'max_mb' => 200, // overridden by the max_video_upload_mb system setting
        'types'  => [
            'mp4'  => ['video/mp4'],
            'webm' => ['video/webm'],
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
