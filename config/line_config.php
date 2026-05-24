<?php
// config/line_config.php

// LINE Messaging API Configuration
// Replace these placeholders with actual values from the LINE Developers Console
define('LINE_CHANNEL_ACCESS_TOKEN', 'YOUR_CHANNEL_ACCESS_TOKEN');
define('LINE_CHANNEL_SECRET', 'YOUR_CHANNEL_SECRET');

// District-specific settings: Tal Sum District (อำเภอตาลสุม)
define('DISTRICT_NAME', 'ตาลสุม');
define('PROVINCE_NAME', 'อุบลราชธานี');

// High-sodium warning foods specific to Tal Sum local culture
define('SODIUM_WARNING_FOODS', [
    'ปลาร้า' => 'น้ำปลาร้าต้มสุก/น้ำส้มตำปูปลาร้า (โซเดียมเฉลี่ย 1,500-2,000 มก. ต่อช้อนแกง)',
    'แจ่ว' => 'แจ่วบอง/น้ำพริกท้องถิ่น (โซเดียมเฉลี่ย 1,200 มก. ต่อช้อนแกง)',
    'น้ำซุป' => 'น้ำซุปแกงหน่อไม้/แกงอ่อม (มักปรุงด้วยปลาร้าเข้มข้น)',
    'อาหารแปรรูป' => 'แหนม/กุนเชียง/หมูยอท้องถิ่น (โซเดียมสูงมากจากผงชูรสและเกลือแกง)'
]);
