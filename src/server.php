<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

$mime = match(pathinfo($uri, PATHINFO_EXTENSION)) {
    'js' => 'application/javascript',
    'css' => 'text/css',
    'svg' => 'image/svg+xml',
    default => null
};

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
if (($path = realpath($publicPath.$uri)) && is_file($path)) {
    header("Content-type: ". $mime ?: mime_content_type($publicPath.$uri));
    header('Access-Control-Allow-Origin: *');
    readfile($publicPath.$uri);
    return;
}

$formattedDateTime = date('D M j H:i:s Y');

$requestMethod = $_SERVER['REQUEST_METHOD'];
$remoteAddress = $_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'];

file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath.'/index.php';
