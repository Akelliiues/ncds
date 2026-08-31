NCDs Red Alert Station V3

- รักษาระบบแจ้งเตือนและหน้าต่างรับสัญญาณจากรุ่นเดิม
- ทดสอบ JHCIS จากเครื่องที่รัน Station โดยตรง ไม่ส่ง localhost ไปให้เว็บเซิร์ฟเวอร์
- รองรับ Host เป็น localhost, 127.0.0.1 หรือหมายเลข IP ของเครื่อง JHCIS
- เปิด Local Bridge ที่ 127.0.0.1:18765 สำหรับหน้า admin/jhcis_sync.php
- Local Bridge รับเฉพาะ Origin ของ Server URL ที่ตั้งไว้ใน Station และรับเฉพาะชุดคำสั่งที่สร้างโดย NCDs Portal
- หลัง MySQL ทำงานสำเร็จ หน้าเว็บจึงยืนยันสถานะซิงค์กลับไปยังโฮสต์
- แยก config, log, shortcut และ Auto Start จากรุ่นเดิม
- ใช้ไฟล์ red_alert_config_v3.json

รุ่นเดิมยังเก็บอยู่ใน tools\red_alert_station และสามารถนำกลับมาใช้ได้
