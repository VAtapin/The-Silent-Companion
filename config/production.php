<?php

return [
    'asset_disk' => env('ASSET_DISK', 'local'),
    'max_upload_kb' => (int) env('MAX_UPLOAD_KB', 512000),
    'allowed_mimes' => [
        'jpg', 'jpeg', 'png', 'webp',
        'mp4', 'mov', 'webm',
        'mp3', 'wav', 'm4a', 'ogg',
        'pdf', 'docx', 'md', 'txt',
    ],
    'approved_asset_statuses' => ['Утверждено', 'Используется в фильме', 'Финальная версия'],
];
