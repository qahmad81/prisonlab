<?php
// The development server skips auto_prepend_file when its router serves a virtual path.
require_once __DIR__ . '/mock-provider.php';
return require dirname(__DIR__) . '/router.php';
