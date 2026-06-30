<?php
// vhv/manual.php (Mobile-Optimized VHV Manual)
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['vhv_id']) && !defined('ALLOW_GUEST_MANUAL')) {
    header("Location: ../index.php");
    exit();
}

$is_vhv = isset($_SESSION['vhv_id']);
$vhvName = $is_vhv ? $_SESSION['vhv_name'] : '';

$path_prefix = defined('ALLOW_GUEST_MANUAL') ? '' : '../';
$back_url = defined('ALLOW_GUEST_MANUAL') ? ($is_vhv ? 'vhv/index.php' : 'index.php') : 'index.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <script>
        // Immediately apply theme before rendering
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คู่มือ อสม. - NCDs Portal</title>
    <link rel="stylesheet" href="<?= $path_prefix ?>assets/css/style.css">
    <style>
        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: var(--font-base);
        }

        .header-section {
            text-align: center;
            margin-bottom: 25px;
            padding: 20px 15px;
            border-radius: var(--border-radius);
            background: var(--bg-card);
            box-shadow: var(--neumorph-flat);
        }

        .header-section img {
            width: 70px;
            height: auto;
            margin-bottom: 12px;
        }

        .header-section h1 {
            font-size: 22px;
            margin: 5px 0;
            font-weight: 800;
        }

        .header-section p {
            color: var(--text-secondary);
            font-size: 13.5px;
            margin: 4px 0 0 0;
        }

        /* Collapsible Accordion Styling */
        .manual-accordion {
            margin-bottom: 20px;
        }

        .accordion-item {
            background-color: var(--bg-card);
            border-radius: 20px;
            margin-bottom: 14px;
            box-shadow: var(--neumorph-flat);
            overflow: hidden;
            transition: all var(--transition-speed);
        }

        .accordion-header {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
            transition: background-color var(--transition-speed);
        }

        .accordion-header:active {
            background-color: var(--bg-darker);
        }

        .accordion-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--color-primary);
            display: flex;
            align-items: center;
            gap: 12px;
            padding-right: 10px;
            line-height: 1.4;
        }

        .accordion-title svg {
            width: 20px;
            height: 20px;
            stroke-width: 2.2;
            flex-shrink: 0;
        }

        .accordion-arrow {
            font-size: 12px;
            color: var(--text-muted);
            transition: transform var(--transition-speed);
        }

        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.35s ease-out;
        }

        .accordion-body {
            padding: 0 20px 20px 20px;
            font-size: 14.5px;
            line-height: 1.6;
            color: var(--text-secondary);
            border-top: 1px solid rgba(13, 44, 84, 0.05);
            padding-top: 16px;
        }

        /* Active/Open State */
        .accordion-item.open .accordion-arrow {
            transform: rotate(180deg);
        }

        .accordion-item.open {
            box-shadow: var(--neumorph-inset);
        }

        /* Inside content adjustments */
        .step-list {
            margin: 15px 0 0 0;
            padding-left: 0;
            list-style: none;
        }

        .step-item {
            position: relative;
            padding-left: 42px;
            margin-bottom: 20px;
        }

        .step-item::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 32px;
            bottom: -16px;
            width: 2px;
            background-color: var(--bg-darker);
        }

        .step-item:last-child::before {
            display: none;
        }

        .step-number {
            position: absolute;
            left: 0;
            top: 2px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: var(--bg-card);
            box-shadow: var(--neumorph-flat);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 13px;
            color: var(--color-primary);
            border: 2px solid var(--color-primary);
        }

        .step-content h4 {
            margin: 0 0 4px 0;
            font-size: 14.5px;
            font-weight: 800;
            color: var(--text-primary);
        }

        .step-content p {
            margin: 0;
            font-size: 13.5px;
            color: var(--text-secondary);
        }

        .alert-box {
            padding: 16px;
            border-radius: 16px;
            margin: 15px 0;
            display: flex;
            gap: 12px;
            box-shadow: var(--neumorph-flat);
            align-items: flex-start;
        }

        .alert-box-info {
            background-color: rgba(13, 44, 84, 0.03);
            border-left: 5px solid var(--color-primary);
        }

        .alert-box-success {
            background-color: rgba(16, 185, 129, 0.03);
            border-left: 5px solid var(--color-green);
        }

        .alert-box-warning {
            background-color: rgba(245, 158, 11, 0.03);
            border-left: 5px solid var(--color-yellow);
        }

        .alert-title {
            font-weight: 800;
            font-size: 14px;
            margin-bottom: 4px;
            color: var(--text-primary);
        }

        .alert-desc {
            font-size: 13px;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.5;
        }

        .alert-box svg {
            width: 22px;
            height: 22px;
            flex-shrink: 0;
        }

        table.manual-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.manual-table th,
        table.manual-table td {
            padding: 10px 12px;
            font-size: 13px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            text-align: left;
        }

        table.manual-table th {
            background-color: var(--bg-darker);
            color: var(--color-primary);
            font-weight: 800;
            border-radius: 8px;
        }

        .hl-text {
            background-color: rgba(13, 44, 84, 0.06);
            color: var(--color-primary);
            padding: 2px 5px;
            border-radius: 4px;
            font-weight: bold;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="mobile-wrapper">
        <!-- Navigation Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
            <a href="<?= htmlspecialchars($back_url) ?>" style="color: var(--color-accent); text-decoration: none; font-size: 14px; font-weight: 800; display: inline-flex; align-items: center; gap: 6px; background: rgba(13, 44, 84, 0.08); padding: 8px 16px; border-radius: 50px;">
                ⬅️ ย้อนกลับ
            </a>
            <span style="font-weight: 800; color: var(--text-primary); font-size: 14px;">คู่มือการใช้งาน อสม.</span>
        </div>

        <!-- Header Section -->
        <div class="header-section">
            <img src="<?= $path_prefix ?>assets/icon.png" alt="NCDs Prevention Logo">
            <h1>📖 คู่มือการใช้งานระบบ</h1>
            <p>สำหรับ อสม. ในการลงพื้นที่คัดกรองเชิงรุก</p>
        </div>

        <!-- Accordion Container -->
        <div class="manual-accordion">

            <!-- Item 1 -->
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span class="accordion-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                        1. การลงทะเบียน & เข้าสู่ระบบ
                    </span>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>การเข้าสู่ระบบ อสม. มีความปลอดภัยและตรวจสอบความเป็นบุคคลจริง เพื่อป้องกันข้อมูลสุขภาพที่ละเอียดอ่อนของผู้รับการคัดกรองในตำบลและหมู่บ้านต่างๆ</p>
                        
                        <div class="alert-box alert-box-info">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                            </svg>
                            <div>
                                <div class="alert-title">รหัสผ่านเริ่มต้น</div>
                                <p class="alert-desc">อสม. ทุกคนที่สมัครใหม่ จะได้รับรหัสผ่านเริ่มต้นคือ <span class="hl-text">1234</span> แนะนำให้เปลี่ยนทันทีหลังเข้าระบบครั้งแรก</p>
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>ลงทะเบียน อสม. ใหม่</h4>
                                    <p>กดปุ่ม "ลงทะเบียน อสม. ใหม่" ในหน้าแรก กรอกชื่อ นามสกุล และเบอร์โทรศัพท์ (ซึ่งใช้เป็นรหัสผู้ใช้)</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>ระบุเขตรับผิดชอบ</h4>
                                    <p>เลือก ตำบล, หมู่บ้าน และ หน่วยบริการ (รพ.สต.) ต้นสังกัดของท่านให้ถูกต้อง</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">3</span>
                                <div class="step-content">
                                    <h4>รอเจ้าหน้าที่อนุมัติ</h4>
                                    <p>เมื่อลงชื่อสำเร็จ บัญชีจะรอเจ้าหน้าที่ รพ.สต. กดยืนยันตัวตน จึงจะเข้าใช้งานระบบได้</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Item 2 -->
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span class="accordion-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        2. แดชบอร์ด & การแยกประเภทงาน
                    </span>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>เมื่อล็อกอินแล้ว จะพบกับแดชบอร์ดสรุปงาน ซึ่งแยกออกเป็น 3 แท็บงาน เพื่อป้องกันความสับสน:</p>
                        <table class="manual-table">
                            <thead>
                                <tr>
                                    <th>แท็บงาน</th>
                                    <th>คำอธิบาย</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong class="hl-text">งานค้าง</strong></td>
                                    <td>รายชื่อเป้าหมายในเขตของท่านที่ยังไม่ได้รับคัดกรองในปีงบประมาณปัจจุบัน</td>
                                </tr>
                                <tr>
                                    <td><strong class="hl-text" style="color: #b91c1c;">DPAC</strong></td>
                                    <td>รายชื่อกลุ่มเสี่ยงที่ถูกส่งเข้ามาติดตามพฤติกรรมสุขภาพรอบปัจจุบัน</td>
                                </tr>
                                <tr>
                                    <td><strong class="hl-text" style="color: #10b981;">เสร็จสิ้น</strong></td>
                                    <td>ประวัติที่บันทึกแล้ว ทั้งที่ส่งสำเร็จและผู้รับบริการที่ถูกข้ามเคส</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Item 3 -->
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span class="accordion-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                        </svg>
                        3. การสแกนคิวอาร์โค้ด (QR Scan)
                    </span>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>เพื่อการสแกนและบันทึกคัดกรองอย่างรวดเร็ว ระบบมีปุ่ม <strong>"สแกนบ้าน"</strong> เป็นรูปวงกลมสีน้ำเงินพร้อมไฟกะพริบอยู่ตรงกลางแถบเมนูด้านล่าง</p>
                        
                        <div class="alert-box alert-box-success">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <div class="alert-title">คลิกเดียวเพื่อเปิดฟอร์ม</div>
                                <p class="alert-desc">อสม. สามารถถือเครื่องไปสแกนคิวอาร์โค้ดที่ติดหน้าบ้าน หรือคิวอาร์โค้ดประจำตัวบุคคล ระบบจะนำทางเข้าสู่ฟอร์มบันทึกคัดกรองของบุคคลนั้นทันทีโดยไม่ต้องค้นหาชื่อ</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Item 4 -->
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span class="accordion-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        4. ขั้นตอนคัดกรอง 2 ขั้นตอน
                    </span>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>ฟอร์มคัดกรองเบาหวาน/ความดันโลหิต ออกแบบมาเน้นการใช้นิ้วแตะปุ่มเพื่อความรวดเร็วโดยมีขั้นตอนดังนี้:</p>
                        
                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>ขั้นตอนที่ 1: ตรวจสอบรายชื่อ</h4>
                                    <p>ยืนยันชื่อ-นามสกุล อายุ และบ้านเลขที่ของผู้รับการคัดกรองว่าถูกต้อง</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>ขั้นตอนที่ 2: บันทึกข้อมูลและพฤติกรรม</h4>
                                    <p>กรอก น้ำหนัก, ส่วนสูง, รอบเอว (ระบบจะคำนวณ BMI ให้อัตโนมัติ), ค่าความดันโลหิต (SYS/DIA), ค่าเจาะน้ำตาลปลายนิ้ว (DTX) และเลือกการประเมินพฤติกรรมเสี่ยง 5 อ.</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">3</span>
                                <div class="step-content">
                                    <h4>ขั้นตอนที่ 3: เลือกไอคอนแนะนำตามรูปภาพ</h4>
                                    <p>ระบบจะแปรผลสุขภาพอัตโนมัติ อสม. สามารถแตะเลือก <strong>รูปไอคอนคำแนะนำสุขภาพ 9 แบบ</strong> (เช่น ลดเค็ม, ออกกำลังกาย, เลี่ยงของทอด) ได้เลยโดยไม่ต้องเสียเวลาคีย์พิมพ์เอง</p>
                                </div>
                            </li>
                        </ul>

                        <div class="alert-box alert-box-success">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            </svg>
                            <div>
                                <div class="alert-title">📍 บันทึกพิกัด GPS อัตโนมัติ</div>
                                <p class="alert-desc">เมื่อกดบันทึกส่งงาน ระบบจะเก็บพิกัด GPS ของบ้านเพื่อนำไปวิเคราะห์ในแผนที่ความร้อน (Health Heatmap) ของ รพ.สต. โดยอัตโนมัติ</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Item 5 -->
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span class="accordion-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        5. การติดตามงาน DPAC
                    </span>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>สำหรับบุคคลที่ตรวจรอบแรกแล้วจัดเป็น "กลุ่มเสี่ยงโรคเรื้อรัง" เจ้าหน้าที่จะลงทะเบียนเข้าโครงการ DPAC และส่งงานมอบหมายให้ อสม. ลงไปติดตามการปรับเปลี่ยนพฤติกรรม</p>
                        <p>อสม. สามารถเข้าไปกดยืนยันและกรอกข้อมูลติดตามรอบ 1-3 ได้ที่แท็บงาน <strong style="color:#b91c1c;">DPAC</strong> โดยบันทึกการประเมินอาหาร การออกกำลังกาย อารมณ์ และชั่งน้ำหนัก/วัดความดันตามรอบครับ</p>
                    </div>
                </div>
            </div>

            <!-- Item 6 -->
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span class="accordion-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-3.536 4.978 4.978 0 011.414-3.536m0 0L5.636 5.636m3.536 9.9L6.343 18.364m0 0L3 21"></path>
                        </svg>
                        6. การใช้งานออฟไลน์ (ไม่มีเน็ต)
                    </span>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>เพื่อตอบสนองการลงพื้นที่จุดอับสัญญาณเน็ตในอำเภอตาลสุม ระบบติดตั้ง PWA ออฟไลน์โหมดอัตโนมัติ:</p>
                        <div class="alert-box alert-box-success">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <div class="alert-title">Offline Auto-Sync</div>
                                <p class="alert-desc">หากบริเวณบ้านที่คัดกรองไม่มีคลื่นเน็ต อสม. ยังสามารถคีย์บันทึกงานได้ตามปกติ ระบบจะเซฟงานไว้ในเครื่องชั่วคราว และเมื่อจับสัญญาณเน็ตได้อีกครั้ง งานจะถูกส่งขึ้นเซิร์ฟเวอร์หลังบ้านอัตโนมัติ</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Item 7 -->
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span class="accordion-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                        7. สิทธิ์ประธาน อสม. (กู้รหัสผ่าน)
                    </span>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>สำหรับ อสม. ที่เป็น <strong>"ประธาน อสม. ประจำหมู่บ้าน"</strong> จะมีเครื่องมือพิเศษที่ด้านบนของแดชบอร์ด เพื่อช่วยแก้ปัญหาเวลาสมาชิกในทีมลืมรหัสผ่าน:</p>
                        <div class="alert-box alert-box-warning">
                            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            <div>
                                <div class="alert-title">ปุ่มรีเซ็ตรหัสผ่านเป็น 1234</div>
                                <p class="alert-desc">ประธาน อสม. สามารถคลิกเลือกรายชื่อ อสม. ในหมู่บ้านที่รับผิดชอบ แล้วกด "รีเซ็ต 1234" ได้ทันที ไม่ต้องโทรแจ้ง รพ.สต. เพื่อช่วยประหยัดเวลา</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Item 8 -->
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span class="accordion-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5a2 2 0 10-2 2h2zm-2 4h4M7 14h10"></path>
                        </svg>
                        8. ระบบบอร์ดคะแนนผลงาน
                    </span>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>อสม. สามารถเปิดเข้าดูหน้า <strong>"กระดานคะแนน (Leaderboard)"</strong> เพื่อดูอันดับการบันทึกคัดกรองสะสมผลงานเปรียบเทียบในระดับตำบลและอำเภอ เพื่อเป็นเกียรติและสร้างแรงบันดาลใจในการทำงานเชิงรุกเพื่อชุมชนครับ</p>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- Accordion Script -->
    <script>
        function toggleAccordion(header) {
            const item = header.parentElement;
            const content = item.querySelector('.accordion-content');
            
            // Close other accordions
            const allItems = document.querySelectorAll('.accordion-item');
            allItems.forEach(i => {
                if (i !== item && i.classList.contains('open')) {
                    i.classList.remove('open');
                    i.querySelector('.accordion-content').style.maxHeight = '0';
                }
            });

            // Toggle active state
            if (item.classList.contains('open')) {
                item.classList.remove('open');
                content.style.maxHeight = '0';
            } else {
                item.classList.add('open');
                content.style.maxHeight = content.scrollHeight + "px";
            }
        }
    </script>
</body>
</html>
