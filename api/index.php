<?php
/**
 * Vercel Serverless Function Router for Money Life PHP Application
 */

// Parse the requested URL path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$urlPath = parse_url($requestUri, PHP_URL_PATH);

// Normalize path: strip optional /money-life prefix and leading slash
$path = preg_replace('#^/money-life#', '', $urlPath);
$path = ltrim($path, '/');

// Set root project directory
$rootDir = dirname(__DIR__);
chdir($rootDir);

// If root is requested, load index.php
if ($path === '' || $path === 'index.php') {
    require $rootDir . '/index.php';
    exit;
}

$target = $rootDir . '/' . $path;

// 1. Direct file match (.php)
if (is_file($target)) {
    require $target;
    exit;
}

// 2. Directory with index.php (e.g. /teacher or /teacher/)
if (is_dir($target) && is_file($target . '/index.php')) {
    require $target . '/index.php';
    exit;
}

// 3. Extensionless PHP route (e.g. /game -> /game.php)
if (is_file($target . '.php')) {
    require $target . '.php';
    exit;
}

// Fallback to main index.php
if (is_file($rootDir . '/index.php')) {
    require $rootDir . '/index.php';
    exit;
}

http_response_code(404);
echo "404 Not Found";
