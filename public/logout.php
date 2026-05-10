<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_post()) {
    csrf_verify();
    logout();
}
redirect('/public/index.php');
