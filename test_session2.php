<?php
session_id('dummy2');
require_once __DIR__ . '/config/session.php';
$_SESSION['admin_logged_in'] = true;
