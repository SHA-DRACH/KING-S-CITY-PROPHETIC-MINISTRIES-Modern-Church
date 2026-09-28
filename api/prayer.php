<?php
/** POST endpoint: prayer form submission (CSRF, honeypot, rate limit, validation). */
require __DIR__ . '/../includes/bootstrap.php';
PublicFormController::prayer();
