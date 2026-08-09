<?php
require_once __DIR__ . '/../includes/bootstrap.php';
do_logout();
header('Location: login.php');
exit;
