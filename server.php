<?php
// This file allows running the application using PHP's built-in server
// Command: php -S localhost:8000 server.php

if (php_sapi_name() == 'cli-server') {
    $file = __DIR__ . $_SERVER['REQUEST_URI'];
    if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
        return false;
    }
}

require_once 'index.php';