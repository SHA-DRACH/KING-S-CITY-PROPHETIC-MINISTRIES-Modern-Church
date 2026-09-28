<?php
define('ADMIN_AREA', true);
require __DIR__ . '/../includes/bootstrap.php';
redirect(current_user() ? 'admin/dashboard.php' : 'admin/login.php');
