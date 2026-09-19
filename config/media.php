<?php

// Central config for the Phase 8 storage foundation. Category is always derived
// from the uploaded file's actual (finfo-detected) MIME type against this map —
// never trusted from client input. Sizes are in kilobytes (Laravel validation units).

return [

    'max_size_kb' => [
        'image' => 5 * 1024,
        'document' => 10 * 1024,
        'audio' => 20 * 1024,
        'video' => 50 * 1024,
        'other' => 5 * 1024,
    ],

    'allowed_mimes' => [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'document' => ['application/pdf', 'text/plain', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'audio' => ['audio/mpeg', 'audio/wav', 'audio/ogg'],
        'video' => ['video/mp4', 'video/webm'],
    ],

    // Extensions rejected regardless of detected MIME type — a defense-in-depth
    // check against dangerous uploads independent of MIME-spoofing resistance.
    'blocked_extensions' => [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'sh', 'bat', 'cmd',
        'com', 'msi', 'dll', 'js', 'jar', 'py', 'rb', 'pl', 'cgi', 'htaccess',
    ],
];
