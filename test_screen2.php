<?php
session_start();
$_SESSION['vhv_id'] = '1001';
$_GET['cid'] = 'TEST';
require 'd:\_Site\ssotansum\ncd\vhv\screening_form.php';
