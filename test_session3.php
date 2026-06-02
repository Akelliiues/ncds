<?php
session_id('dummy3');
require_once __DIR__ . '/config/session.php';
$_SESSION['admin_logged_in'] = true;
