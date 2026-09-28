<?php
/** Shared entry for every protected admin page: bootstrap + authentication guard. */
define('ADMIN_AREA', true);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();
