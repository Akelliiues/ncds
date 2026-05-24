<?php
session_start();
$_SESSION['admin_logged_in'] = true;
 = curl_init('http://localhost:8000/admin/hdc_import.php');
curl_setopt(, CURLOPT_RETURNTRANSFER, true);
curl_setopt(, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());
 = curl_exec();
 = curl_getinfo(, CURLINFO_HTTP_CODE);
curl_close();
echo "HTTP CODE: \n";
