<?php
require_once __DIR__ . "/../src/Middleware/cors.php";

$router = require __DIR__ . "/../src/routes.php";

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) ?? "/";
$base = "/sports-tickets-backend/public";

if (str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}

$router->dispatch($_SERVER["REQUEST_METHOD"], $path);
exit;