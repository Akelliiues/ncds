<?php
session_id('dummy');
require_once __DIR__ . '/config/session.php';
$_SESSION['admin_logged_in'] = true;
