<?php

require_once 'config.php';

$_SESSION = [];

session_destroy();

header("Location: " . url('index.php'));
exit;
?>