<?php
/** POST endpoint: contact form submission (CSRF, honeypot, rate limit, validation). */
require __DIR__ . '/../includes/bootstrap.php';
PublicFormController::contact();
