<?php
if (!defined('ZBP_PATH')) { if (defined('BASE_PATH')) define('ZBP_PATH', BASE_PATH); else define('ZBP_PATH', dirname(dirname(dirname(__DIR__))) . '/'); }
if (!isset($blogpath)) $blogpath = ZBP_PATH;
if (!isset($bloghost)) { $bloghost = 'http://localhost/'; }
