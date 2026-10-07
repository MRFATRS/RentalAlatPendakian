<?php
require_once __DIR__ . '/includes/functions.php';
unset($_SESSION['user_id'], $_SESSION['user_username'], $_SESSION['user_role'], $_SESSION['user_redirect']);
header('Location: index.php');
exit;
