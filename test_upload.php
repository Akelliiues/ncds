<?php
session_start();
$_SESSION['admin_logged_in'] = true;

// Create dummy CSV
file_put_contents('dummy.csv', "cid,name,lname,sex,birth,risk,result\n1234567890123,Test,User,1,1990-01-01,เสี่ยงสูง,150");

 = curl_init('http://localhost:8000/admin/hdc_import.php');
 = new CURLFile(realpath('dummy.csv'), 'text/csv', 'dummy.csv');
 = [
    'disease_type' => 'DM',
    'hdc_file' => 
];
curl_setopt(, CURLOPT_POST, true);
curl_setopt(, CURLOPT_POSTFIELDS, );
curl_setopt(, CURLOPT_RETURNTRANSFER, true);
curl_setopt(, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());
 = curl_exec();
 = curl_getinfo(, CURLINFO_HTTP_CODE);
curl_close();
echo "HTTP CODE: $httpcode\n";
echo "RESPONSE:\n";
echo substr(strip_tags($response), 0, 500);
