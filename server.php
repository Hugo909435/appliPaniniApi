<?php

$publicPath = __DIR__ . '/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// If the file exists in /public, serve it manually so CORS headers are included
if ($uri !== '/' && file_exists($publicPath . $uri)) {
    $filePath = $publicPath . $uri;
    $ext      = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    $mimeTypes = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
    ];

    $mimeType = $mimeTypes[$ext] ?? mime_content_type($filePath) ?? 'application/octet-stream';

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit();
}

require_once $publicPath . '/index.php';
