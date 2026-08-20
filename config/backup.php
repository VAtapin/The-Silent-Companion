<?php

return [
    'path' => env('BACKUP_PATH', storage_path('app/backups')),
    'keep' => (int) env('BACKUP_KEEP', 14),
    'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
];
