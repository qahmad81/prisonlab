<?php
// Virtual paths are executed by the router without PHP's normal auto-prepend stage.
require_once __DIR__.'/mock-provider.php';
return require dirname(__DIR__).'/router.php';
