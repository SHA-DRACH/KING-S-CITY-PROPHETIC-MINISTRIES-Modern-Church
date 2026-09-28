<?php
define('ADMIN_AREA', true);
require __DIR__ . '/../includes/bootstrap.php';

// Logout must be a POST with a valid CSRF token (checked in bootstrap) so
// other sites cannot sign staff out with a simple link or image tag.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(current_user() ? 'admin/dashboard.php' : 'admin/login.php');
}
auth_logout();
flash('success', 'You have been signed out securely.');
redirect('admin/login.php');
