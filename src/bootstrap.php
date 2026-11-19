<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/layout.php';

$storage = new Storage($config);
$auth = new Auth($storage, $config);
