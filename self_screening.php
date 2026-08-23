<?php
// self_screening.php - แบบประเมินสุขภาพและพฤติกรรมเสี่ยง NCDs ด้วยตนเองสำหรับประชาชน (Claymorphism & Overlay Navigation)
require_once __DIR__ . '/config/db.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ประเมินสุขภาพตนเอง">
    <meta name="theme-color" content="#0d2c54">
    <title>ตรวจเช็คสุขภาพเบื้องต้น - อำเภอ<?= DISTRICT_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="apple-touch-icon" href="assets/icon.png">
    <link rel="manifest" href="manifest.json">
    <script src="assets/js/clinical_guidance.js"></script>

    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            position: relative;
            overflow-x: hidden;
        }

        .screen-container {
            max-width: 520px;
            margin: 0 auto;
            width: 100%;
            padding: 12px 16px 40px 16px;
            box-sizing: border-box;
            position: relative;
        }

        /* Slide Transition */
        .question-slide {
            display: none;
            animation: slideInRight 0.28s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .question-slide.active {
            display: block;
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(24px) scale(0.98); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }

        /* Progress Bar */
        .top-nav-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .progress-pill {
            background: rgba(59, 130, 246, 0.12);
            color: #2563eb;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        [data-theme="dark"] .progress-pill {
            background: rgba(56, 189, 248, 0.18);
            color: #38bdf8;
        }

        .progress-container {
            width: 100%;
            height: 7px;
            background: var(--bg-darker);
            border-radius: 9999px;
            margin-bottom: 16px;
            overflow: hidden;
            box-shadow: var(--neumorph-inset);
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #10b981);
            width: 10%;
            transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 9999px;
        }

        /* Big Question Header */
        .q-header {
            text-align: center;
            margin-bottom: 16px;
        }
        .q-title {
            font-size: 20px;
            font-weight: 900;
            color: var(--text-primary);
            margin: 0 0 4px 0;
            line-height: 1.35;
        }
        .q-subtitle {
            font-size: 13.5px;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Large Claymorphism Option Cards */
        .clay-options-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .clay-opt-card {
            position: relative;
            background: var(--bg-card);
            border-radius: 22px;
            padding: 14px 18px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05), inset 1.5px 1.5px 3px rgba(255, 255, 255, 0.8), inset -1.5px -1.5px 3px rgba(0, 0, 0, 0.03);
            cursor: pointer;
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 2.5px solid transparent;
            display: flex;
            align-items: center;
            gap: 16px;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        .clay-opt-card:active {
            transform: scale(0.97);
        }
        .clay-opt-card input[type="radio"] {
            display: none;
        }
        .clay-opt-card.selected {
            background: rgba(59, 130, 246, 0.08);
            border-color: #3b82f6;
            box-shadow: 0 8px 22px rgba(59, 130, 246, 0.22), inset 2px 2px 4px rgba(59, 130, 246, 0.1);
            transform: scale(1.02);
        }
        [data-theme="dark"] .clay-opt-card.selected {
            background: rgba(56, 189, 248, 0.15);
            border-color: #38bdf8;
        }

        /* Prominent Clay Icon Container */
        .clay-icon-large {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1), inset 1px 1px 2px rgba(255, 255, 255, 0.6);
            background: #e2e8f0;
            border: 2px solid rgba(255, 255, 255, 0.7);
        }
        .clay-icon-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .clay-opt-card:hover .clay-icon-large img,
        .clay-opt-card.selected .clay-icon-large img {
            transform: scale(1.12);
        }

        /* Large Bold Typography */
        .opt-content {
            flex: 1;
            text-align: left;
        }
        .opt-content h4 {
            margin: 0 0 3px 0;
            font-size: 17.5px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.25;
        }
        .opt-content p {
            margin: 0;
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.35;
        }

        .opt-check-badge {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            border: 2px solid var(--border-color, #cbd5e1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: transparent;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        .clay-opt-card.selected .opt-check-badge {
            background: #3b82f6;
            border-color: #3b82f6;
            color: white;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4);
        }

        /* Side Overlay Navigation Buttons */
        .side-overlay-btn {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--bg-card);
            border: 2px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15), inset 2px 2px 4px rgba(255, 255, 255, 0.9);
            color: var(--text-primary);
            font-size: 22px;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 99;
            transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        .side-overlay-btn:hover {
            transform: translateY(-50%) scale(1.12);
            color: #3b82f6;
        }
        .side-overlay-btn:active {
            transform: translateY(-50%) scale(0.92);
        }
        .side-btn-prev {
            left: 8px;
        }
        .side-btn-next {
            right: 8px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.35);
        }
        .side-btn-next:hover {
            color: white;
        }

        /* Hide side button when inactive */
        .side-overlay-btn.disabled {
            opacity: 0;
            pointer-events: none;
            transform: translateY(-50%) scale(0.6);
        }

        /* Results & Solution Box */
        .solution-box {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 16px;
            margin-bottom: 14px;
            box-shadow: var(--neumorph-flat);
            border-left: 6px solid #3b82f6;
            text-align: left;
        }
        .solution-box.bp-box {
            border-left-color: #3b82f6;
        }
        .solution-box.sugar-box {
            border-left-color: #f59e0b;
        }
        .solution-title {
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .solution-list {
            margin: 0;
            padding-left: 18px;
            font-size: 14px;
            line-height: 1.6;
            color: var(--text-primary);
        }
        .solution-list li {
            margin-bottom: 5px;
        }

        /* Voice Coach Trigger */
        .voice-coach-bar {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(16, 185, 129, 0.12));
            border: 1.5px solid rgba(59, 130, 246, 0.3);
            border-radius: 16px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body class="vhv-accessibility">

    <!-- Floating Side Overlay Buttons -->
    <button type="button" id="btn-side-prev" class="side-overlay-btn side-btn-prev disabled" onclick="prevQuestion()" title="ย้อนกลับ">
        ‹
    </button>
    <button type="button" id="btn-side-next" class="side-overlay-btn side-btn-next" onclick="nextQuestion()" title="ถัดไป">
        ›
    </button>

    <div class="screen-container">
        
        <!-- Top Nav & Progress Bar -->
        <div id="top-nav-section">
            <div class="top-nav-bar">
                <a href="index.php" style="color: var(--text-secondary); text-decoration: none; font-size: 13.5px; font-weight: 700; display: flex; align-items: center; gap: 4px;">
                    ✕ ออก
                </a>
                <span id="step-badge" class="progress-pill">ข้อ 1 จาก 10</span>
                <span style="font-size: 12.5px; color: var(--color-accent); font-weight: 800;">
                    NCDs ตาลสุม
                </span>
            </div>
            <div class="progress-container">
                <div id="self-progress" class="progress-fill"></div>
            </div>
        </div>

        <form id="self-screening-form" onsubmit="return false;">
            
            <!-- QUESTION 1: เพศ -->
            <div id="q-1" class="question-slide active">
                <div class="q-header">
                    <h2 class="q-title">1. เพศของท่าน</h2>
                    <p class="q-subtitle">แตะเลือกเพศเพื่อเริ่มตรวจเช็ค</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'gender', 1)">
                        <input type="radio" name="gender" value="male" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/male.jpg" alt="เพศชาย">
                        </div>
                        <div class="opt-content">
                            <h4>ชาย</h4>
                            <p>สุภาพบุรุษ</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'gender', 1)">
                        <input type="radio" name="gender" value="female">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/female.jpg" alt="เพศหญิง">
                        </div>
                        <div class="opt-content">
                            <h4>หญิง</h4>
                            <p>สุภาพสตรี</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
            </div>


            <!-- QUESTION 2: อายุ -->
            <div id="q-2" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">2. ท่านอายุเท่าไหร่?</h2>
                    <p class="q-subtitle">ช่วงวัยที่ตรงกับท่านในปัจจุบัน</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'age_group', 2)">
                        <input type="radio" name="age_group" value="young" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/exercise.jpg" alt="น้อยกว่า 35 ปี">
                        </div>
                        <div class="opt-content">
                            <h4>น้อยกว่า 35 ปี</h4>
                            <p>วัยหนุ่มสาว / วัยเริ่มทำงาน</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'age_group', 2)">
                        <input type="radio" name="age_group" value="middle">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/shield.jpg" alt="อายุ 35-59 ปี">
                        </div>
                        <div class="opt-content">
                            <h4>อายุ 35 - 59 ปี</h4>
                            <p>วัยทำงาน (ควรตรวจสุขภาพประจำปี)</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'age_group', 2)">
                        <input type="radio" name="age_group" value="senior">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/sleep.jpg" alt="อายุ 60 ปีขึ้นไป">
                        </div>
                        <div class="opt-content">
                            <h4>อายุ 60 ปีขึ้นไป</h4>
                            <p>วัยผู้สูงอายุ</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
            </div>


            <!-- QUESTION 3: รูปร่าง & รอบเอว -->
            <div id="q-3" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">3. รูปร่างและรอบเอว</h2>
                    <p class="q-subtitle">สัดส่วนหน้าท้องและรูปร่างของท่าน</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'body_shape', 3)">
                        <input type="radio" name="body_shape" value="slim" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/waist.jpg" alt="สมส่วน พอดีตัว">
                        </div>
                        <div class="opt-content">
                            <h4>สมส่วน พอดีตัว</h4>
                            <p>ไม่อึดอัด พุงไม่ยื่น</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'body_shape', 3)">
                        <input type="radio" name="body_shape" value="chubby">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/sweet.jpg" alt="เริ่มมีพุง">
                        </div>
                        <div class="opt-content">
                            <h4>เริ่มมีพุง / ท้วม</h4>
                            <p>กางเกงเริ่มแน่น มีพุงเล็กน้อย</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'body_shape', 3)">
                        <input type="radio" name="body_shape" value="obese">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/fried.jpg" alt="อ้วนลงพุงชัดเจน">
                        </div>
                        <div class="opt-content">
                            <h4>อ้วนลงพุงชัดเจน</h4>
                            <p>พุงยื่นเยอะ เหนื่อยง่าย</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
            </div>


            <!-- QUESTION 4: ของหวาน & น้ำหวาน -->
            <div id="q-4" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">4. ดื่มน้ำหวานหรือขนมหวานบ่อยไหม?</h2>
                    <p class="q-subtitle">ชา ชานม กาแฟใส่นมข้น น้ำอัดลม</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'sweet_habit', 4)">
                        <input type="radio" name="sweet_habit" value="low" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/water.jpg" alt="ดื่มน้ำเปล่าเป็นหลัก">
                        </div>
                        <div class="opt-content">
                            <h4>ดื่มน้ำเปล่าเป็นหลัก</h4>
                            <p>แทบไม่แตะน้ำอัดลม ชาหวาน</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'sweet_habit', 4)">
                        <input type="radio" name="sweet_habit" value="med">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/sweet.jpg" alt="ดื่มบ้างบางวัน">
                        </div>
                        <div class="opt-content">
                            <h4>ดื่มบ้างบางวัน</h4>
                            <p>สัปดาห์ละ 1-3 ครั้ง เวลาเหนื่อย</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'sweet_habit', 4)">
                        <input type="radio" name="sweet_habit" value="high">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/sweet.jpg" alt="กินเกือบทุกวัน">
                        </div>
                        <div class="opt-content">
                            <h4>กินเกือบทุกวัน / ติดหวาน</h4>
                            <p>ต้องมีน้ำหวาน กาแฟ หรือขนมทุกวัน</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
            </div>


            <!-- QUESTION 5: ของเค็ม & ของทอด -->
            <div id="q-5" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">5. ชอบกินเค็ม ผงชูรส หรือของทอดไหม?</h2>
                    <p class="q-subtitle">น้ำปลา ซอสปรุงรส ปลาร้าเข้มข้น</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'salt_habit', 5)">
                        <input type="radio" name="salt_habit" value="low" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/veggie.jpg" alt="กินรสจืดๆ">
                        </div>
                        <div class="opt-content">
                            <h4>กินรสจืดๆ ไม่ปรุงเพิ่ม</h4>
                            <p>เลี่ยงของทอด ซดน้ำแกงแต่น้อย</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'salt_habit', 5)">
                        <input type="radio" name="salt_habit" value="med">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/fried.jpg" alt="กินรสจัดบ้าง">
                        </div>
                        <div class="opt-content">
                            <h4>กินรสจัดบ้างบางมื้อ</h4>
                            <p>มีส้มตำ ปลาร้า ของทอด สัปดาห์ละ 2-3 ครั้ง</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'salt_habit', 5)">
                        <input type="radio" name="salt_habit" value="high">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/fried.jpg" alt="ชอบรสเค็มจัด">
                        </div>
                        <div class="opt-content">
                            <h4>ชอบเค็มจัด / ปลาร้าเข้ม / ของทอดประจำ</h4>
                            <p>ชอบเติมน้ำปลา ผงชูรส ซดน้ำแกงหมด</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
            </div>


            <!-- QUESTION 6: การกินผัก -->
            <div id="q-6" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">6. กินผักในมื้ออาหารบ่อยแค่ไหน?</h2>
                    <p class="q-subtitle">ผักสด ผักลวก ผักต้มในแต่ละมื้อ</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'veggie_habit', 6)">
                        <input type="radio" name="veggie_habit" value="good" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/veggie.jpg" alt="กินผักทุกมื้อ">
                        </div>
                        <div class="opt-content">
                            <h4>กินผักทุกมื้อ หรือเกือบทุกมื้อ</h4>
                            <p>มีผักสด ผักลวก ผักต้มในจานเสมอ</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'veggie_habit', 6)">
                        <input type="radio" name="veggie_habit" value="poor">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/fried.jpg" alt="ไม่ค่อยกินผัก">
                        </div>
                        <div class="opt-content">
                            <h4>ไม่ค่อยกินผัก / กินน้อยมาก</h4>
                            <p>เน้นเนื้อสัตว์ ข้าว และของทอด</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
            </div>


            <!-- QUESTION 7: การออกกำลังกาย -->
            <div id="q-7" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">7. ได้ออกกำลังกายหรือออกแรงไหม?</h2>
                    <p class="q-subtitle">กิจกรรมขยับตัวต่อเนื่อง 20-30 นาที</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'exercise_habit', 7)">
                        <input type="radio" name="exercise_habit" value="regular" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/exercise.jpg" alt="ทำเป็นประจำ">
                        </div>
                        <div class="opt-content">
                            <h4>ทำเป็นประจำ (3-5 วัน/สัปดาห์)</h4>
                            <p>เดินเร็ว วิ่ง ปั่นจักรยาน ทำสวนเหงื่อออก</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'exercise_habit', 7)">
                        <input type="radio" name="exercise_habit" value="some">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/waist.jpg" alt="มีเดินขยับตัวบ้าง">
                        </div>
                        <div class="opt-content">
                            <h4>มีเดินขยับตัวบ้าง (1-2 วัน/สัปดาห์)</h4>
                            <p>ทำงานบ้าน กวาดใบไม้ เดินไปมา</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'exercise_habit', 7)">
                        <input type="radio" name="exercise_habit" value="sedentary">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/sleep.jpg" alt="แทบไม่ได้ออก">
                        </div>
                        <div class="opt-content">
                            <h4>แทบไม่ได้ออก / นั่งนานทั้งวัน</h4>
                            <p>นั่งทำงานหรือนอนเล่นมือถือนาน</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
            </div>


            <!-- QUESTION 8: การนอนหลับ -->
            <div id="q-8" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">8. ท่านนอนหลับดีไหม?</h2>
                    <p class="q-subtitle">คุณภาพการนอนหลับและการพักผ่อน</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'sleep_habit', 8)">
                        <input type="radio" name="sleep_habit" value="good" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/sleep.jpg" alt="หลับสนิทดี">
                        </div>
                        <div class="opt-content">
                            <h4>หลับสนิทดี ตื่นมาสดชื่น</h4>
                            <p>นอน 6-8 ชั่วโมง ไม่ค่อยตื่นกลางดึก</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'sleep_habit', 8)">
                        <input type="radio" name="sleep_habit" value="poor">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/sweet.jpg" alt="หลับยาก">
                        </div>
                        <div class="opt-content">
                            <h4>หลับๆ ตื่นๆ / หลับยาก</h4>
                            <p>ตื่นกลางดึกบ่อย พักผ่อนไม่ค่อยพอ</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
            </div>


            <!-- QUESTION 9: บุหรี่ & สุรา -->
            <div id="q-9" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">9. สูบบุหรี่หรือดื่มเหล้าไหม?</h2>
                    <p class="q-subtitle">ความถี่ในการสูบหรือดื่มในปัจจุบัน</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'substance_habit', 9)">
                        <input type="radio" name="substance_habit" value="none" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/shield.jpg" alt="ไม่สูบและไม่ดื่ม">
                        </div>
                        <div class="opt-content">
                            <h4>ไม่สูบ และ ไม่ดื่ม</h4>
                            <p>หรือไม่แตะเลย</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'substance_habit', 9)">
                        <input type="radio" name="substance_habit" value="some">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/water.jpg" alt="ดื่มเฉพาะงาน">
                        </div>
                        <div class="opt-content">
                            <h4>ดื่มเฉพาะงานสังสรรค์</h4>
                            <p>สูบบ้างบางครั้ง</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'substance_habit', 9)">
                        <input type="radio" name="substance_habit" value="regular">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/fried.jpg" alt="สูบหรือดื่มประจำ">
                        </div>
                        <div class="opt-content">
                            <h4>สูบหรือดื่มเป็นประจำ</h4>
                            <p>ทำต่อเนื่องเป็นประจำ</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
            </div>


            <!-- QUESTION 10: ประวัติครอบครัว -->
            <div id="q-10" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">10. พ่อแม่หรือพี่น้อง มีใครเป็นเบาหวาน-ความดันไหม?</h2>
                    <p class="q-subtitle">ประวัติพันธุกรรมสายตรง</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'family_history', 10, true)">
                        <input type="radio" name="family_history" value="no" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/shield.jpg" alt="ไม่มีใครเป็น">
                        </div>
                        <div class="opt-content">
                            <h4>ไม่มีใครเป็น</h4>
                            <p>ไม่มีประวัติในครอบครัวสายตรง</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'family_history', 10, true)">
                        <input type="radio" name="family_history" value="yes">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/waist.jpg" alt="มีคนเป็น">
                        </div>
                        <div class="opt-content">
                            <h4>มีคนเป็นเบาหวานหรือความดัน</h4>
                            <p>พ่อ แม่ หรือพี่น้องเคยเป็น</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
            </div>


            <!-- STEP 11: หน้าสรุปผลตรวจสุขภาพ & คำแนะนำลดความดัน-ลดน้ำตาล -->
            <div id="q-result" class="question-slide">
                
                <!-- Overall Risk Banner -->
                <div id="result-risk-banner" style="background: var(--bg-card); border-radius: 24px; padding: 20px; margin-bottom: 16px; box-shadow: var(--neumorph-flat); text-align: center; border: 2.5px solid #10b981;">
                    <div id="result-risk-icon" style="font-size: 44px; margin-bottom: 4px;">🟢</div>
                    <h3 id="result-risk-title" style="margin: 0 0 4px 0; font-size: 19px; font-weight: 800; color: #10b981;">สุขภาพดีมาก (ความเสี่ยงต่ำ)</h3>
                    <p id="result-risk-desc" style="margin: 0; font-size: 13.5px; color: var(--text-secondary); line-height: 1.45;">ดูแลตัวเองได้ดีมาก ทั้งเรื่องกิน ออกกำลังกาย และการนอน ทำต่อเนื่องไปเรื่อยๆ นะครับ</p>
                </div>

                <!-- Voice Coach Player Bar -->
                <div class="voice-coach-bar">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">🎙️</span>
                        <div>
                            <div style="font-weight: 800; font-size: 14px; color: var(--text-primary);">คำแนะนำเสียงพูด (Voice Coach)</div>
                            <div style="font-size: 12px; color: var(--text-secondary);">ฟังเสียงสรุปคำแนะนำเพื่อสุขภาพ</div>
                        </div>
                    </div>
                    <button type="button" id="btn-self-voice" onclick="playSelfVoiceCoach()" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; border: none; padding: 8px 14px; border-radius: 12px; font-size: 13px; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 10px rgba(59,130,246,0.3);">
                        <span>🔊</span> <span id="voice-btn-text">เปิดเสียงพูด</span>
                    </button>
                </div>

                <!-- Solution Card 1: อยากลดความดัน ต้องทำอย่างไร? -->
                <div class="solution-box bp-box">
                    <div class="solution-title" style="color: #2563eb;">
                        <span>🩺</span> <span>ถ้าอยากลดความดัน ต้องทำอย่างไร?</span>
                    </div>
                    <ul class="solution-list" id="bp-advice-list">
                        <!-- Populated by JS -->
                    </ul>
                </div>

                <!-- Solution Card 2: อยากลดค่าน้ำตาล / เบาหวาน ต้องทำอย่างไร? -->
                <div class="solution-box sugar-box">
                    <div class="solution-title" style="color: #d97706;">
                        <span>🩸</span> <span>ถ้าอยากลดค่าน้ำตาล ต้องทำอย่างไร?</span>
                    </div>
                    <ul class="solution-list" id="sugar-advice-list">
                        <!-- Populated by JS -->
                    </ul>
                </div>

                <!-- Community Referral Card -->
                <div style="background: linear-gradient(135deg, #0d2c54, #1e3a8a); color: white; border-radius: 20px; padding: 18px; margin-bottom: 20px; box-shadow: 0 8px 20px rgba(13, 44, 84, 0.25); text-align: left;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                        <div style="background: rgba(255,255,255,0.15); border-radius: 50%; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; font-size: 18px;">🏥</div>
                        <div>
                            <h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #38bdf8;">อยากตรวจวัดค่าความดันและค่าน้ำตาลจริง?</h4>
                            <p style="margin: 2px 0 0 0; font-size: 12px; color: #cbd5e1;">ระบบสาธารณสุขอำเภอ<?= DISTRICT_NAME ?> พร้อมดูแลท่านฟรี</p>
                        </div>
                    </div>
                    <p style="font-size: 13px; line-height: 1.45; margin: 8px 0; color: #f1f5f9;">
                        ติดต่อ <strong>อสม. ประจำคุ้มบ้านของท่าน</strong> เพื่อตรวจวัดความดันโลหิตและเจาะน้ำตาลปลายนิ้ว หรือตรวจสุขภาพประจำปีได้ฟรีที่ <strong>รพ.สต. ใกล้บ้าน</strong> ได้เลยครับ
                    </p>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button type="button" onclick="shareResultCard()" class="btn-giant btn-giant-primary" style="margin: 0; padding: 13px; font-size: 15px; background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                        📤 แชร์หรือบันทึกผลให้ อสม. ดู
                    </button>
                    <button type="button" onclick="restartScreening()" class="btn-giant btn-giant-secondary" style="margin: 0; padding: 11px; font-size: 14px;">
                        🔄 ประเมินใหม่อีกครั้ง
                    </button>
                    <a href="index.php" style="text-align: center; color: var(--color-accent); text-decoration: none; font-size: 14px; font-weight: 700; margin-top: 4px; display: block;">
                        ← กลับหน้าหลัก
                    </a>
                </div>

            </div>

        </form>

    </div>

    <script>
        let currentQ = 1;
        const totalQ = 10;
        let calculatedRiskLevel = 'green';
        let currentVoiceText = '';
        let isNavigating = false;

        function updateUIState() {
            // Update question visibility
            document.querySelectorAll('.question-slide').forEach(q => q.classList.remove('active'));
            
            const prevBtn = document.getElementById('btn-side-prev');
            const nextBtn = document.getElementById('btn-side-next');
            const topNav = document.getElementById('top-nav-section');

            if (currentQ <= totalQ) {
                const targetQ = document.getElementById('q-' + currentQ);
                if (targetQ) targetQ.classList.add('active');

                // Update Progress Pill & Bar
                document.getElementById('step-badge').innerText = `ข้อ ${currentQ} จาก ${totalQ}`;
                document.getElementById('self-progress').style.width = (currentQ * 10) + '%';
                topNav.style.display = 'block';

                // Side Buttons
                if (currentQ === 1) {
                    prevBtn.classList.add('disabled');
                } else {
                    prevBtn.classList.remove('disabled');
                }
                nextBtn.classList.remove('disabled');
                nextBtn.innerHTML = '›';
            } else {
                // Result Screen
                const resultScreen = document.getElementById('q-result');
                if (resultScreen) resultScreen.classList.add('active');
                
                prevBtn.classList.add('disabled');
                nextBtn.classList.add('disabled');
                topNav.style.display = 'none';
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function pickOption(cardEl, inputName, qNum, isLast = false) {
            const container = cardEl.parentElement;
            container.querySelectorAll('.clay-opt-card').forEach(c => c.classList.remove('selected'));
            
            cardEl.classList.add('selected');
            const radio = cardEl.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;

            // Auto-advance to next question after 220ms
            if (!isNavigating) {
                isNavigating = true;
                setTimeout(() => {
                    if (isLast || qNum >= totalQ) {
                        calculateSelfResults();
                    } else {
                        currentQ = qNum + 1;
                        updateUIState();
                    }
                    isNavigating = false;
                }, 220);
            }
        }

        function nextQuestion() {
            if (currentQ < totalQ) {
                currentQ++;
                updateUIState();
            } else if (currentQ === totalQ) {
                calculateSelfResults();
            }
        }

        function prevQuestion() {
            if (currentQ > 1) {
                currentQ--;
                updateUIState();
            }
        }

        function restartScreening() {
            currentQ = 1;
            updateUIState();
        }

        function calculateSelfResults() {
            const form = document.getElementById('self-screening-form');
            const data = new FormData(form);

            const age = data.get('age_group') || 'young';
            const shape = data.get('body_shape') || 'slim';
            const sweet = data.get('sweet_habit') || 'low';
            const salt = data.get('salt_habit') || 'low';
            const veggie = data.get('veggie_habit') || 'good';
            const exercise = data.get('exercise_habit') || 'regular';
            const sleep = data.get('sleep_habit') || 'good';
            const substance = data.get('substance_habit') || 'none';
            const family = data.get('family_history') || 'no';

            let riskPoints = 0;
            if (age === 'middle') riskPoints += 1;
            if (age === 'senior') riskPoints += 2;
            if (shape === 'chubby') riskPoints += 1;
            if (shape === 'obese') riskPoints += 3;
            if (sweet === 'med') riskPoints += 1;
            if (sweet === 'high') riskPoints += 3;
            if (salt === 'med') riskPoints += 1;
            if (salt === 'high') riskPoints += 3;
            if (veggie === 'poor') riskPoints += 1;
            if (exercise === 'some') riskPoints += 1;
            if (exercise === 'sedentary') riskPoints += 3;
            if (sleep === 'poor') riskPoints += 1;
            if (substance === 'some') riskPoints += 1;
            if (substance === 'regular') riskPoints += 3;
            if (family === 'yes') riskPoints += 2;

            // BP Advice
            const bpList = document.getElementById('bp-advice-list');
            bpList.innerHTML = '';
            const bpAdvices = [];
            if (salt === 'high' || salt === 'med') {
                bpAdvices.push('<strong>ลดเค็ม:</strong> ตักน้ำปลา/ผงชูรสน้อยลงครึ่งหนึ่ง เลี่ยงซดน้ำส้มตำหรือน้ำแกงจนหมด');
            } else {
                bpAdvices.push('<strong>กินจืดต่อเนื่อง:</strong> ปรุงรสน้อย เลี่ยงของหมักดองและไส้กรอก กุนเชียง');
            }

            if (exercise === 'sedentary' || exercise === 'some') {
                bpAdvices.push('<strong>ขยับตัวบ่อยขึ้น:</strong> เดินเร็ว แกว่งแขน หรือปั่นจักรยานวันละ 20-30 นาที ช่วยให้หลอดเลือดยืดหยุ่นดี');
            } else {
                bpAdvices.push('<strong>ออกกำลังกายสม่ำเสมอ:</strong> ทำต่อเนื่อง 3-5 วันต่อสัปดาห์ หัวใจจะแข็งแรง');
            }

            if (sleep === 'poor') {
                bpAdvices.push('<strong>นอนหลับให้พอ:</strong> เข้านอนเร็วขึ้น งดเล่นมือถือก่อนนอน ให้ร่างกายได้พักผ่อนเต็มที่');
            }

            if (substance === 'regular' || substance === 'some') {
                bpAdvices.push('<strong>ลดหรือเลิกบุหรี่/สุรา:</strong> ช่วยให้หลอดเลือดคลายตัว ความดันจะลดลงสู่เกณฑ์ปกติได้เร็ว');
            }
            bpAdvices.push('<strong>ดื่มน้ำเปล่า 6-8 แก้วต่อวัน:</strong> ช่วยให้เลือดไหลเวียนดี ไม่ข้นหนืด');

            bpAdvices.forEach(adv => {
                const li = document.createElement('li');
                li.innerHTML = adv;
                bpList.appendChild(li);
            });

            // Blood Sugar Advice
            const sugarList = document.getElementById('sugar-advice-list');
            sugarList.innerHTML = '';
            const sugarAdvices = [];
            if (sweet === 'high' || sweet === 'med') {
                sugarAdvices.push('<strong>สั่งหวานน้อย:</strong> สั่งเครื่องดื่มหวานน้อย หรือเปลี่ยนมาดื่มน้ำเปล่า งดน้ำอัดลมและชานม');
            } else {
                sugarAdvices.push('<strong>ดื่มน้ำเปล่าเป็นหลัก:</strong> เลี่ยงน้ำหวานแฝง เช่น น้ำผลไม้กล่อง นมเปรี้ยวรสหวาน');
            }

            if (shape === 'obese' || shape === 'chubby') {
                sugarAdvices.push('<strong>ลดแป้งและข้าวเหนียว:</strong> ลดข้าวเหนียว/ข้าวขาวลง 1 ใน 3 เพื่อลดไขมันสะสมที่พุง');
            }

            if (veggie === 'poor') {
                sugarAdvices.push('<strong>กินผักเพิ่มขึ้น:</strong> เพิ่มผักใบเขียว (ผักบุ้ง ผักกาด กะหล่ำ) ครึ่งจานทุกมื้อ ช่วยชะลอน้ำตาลเข้าเลือด');
            } else {
                sugarAdvices.push('<strong>เลือกผลไม้ไม่หวานจัด:</strong> เช่น ฝรั่ง แอปเปิ้ลเขียว ส้มโอ เลี่ยงทุเรียน ลำไย มะม่วงสุก');
            }
            sugarAdvices.push('<strong>ไม่กินจุบจิบระหว่างมื้อ:</strong> ให้ร่างกายได้ดึงน้ำตาลสะสมไปเผาผลาญ');

            sugarAdvices.forEach(adv => {
                const li = document.createElement('li');
                li.innerHTML = adv;
                sugarList.appendChild(li);
            });

            // Overall Risk Banner
            const banner = document.getElementById('result-risk-banner');
            const icon = document.getElementById('result-risk-icon');
            const title = document.getElementById('result-risk-title');
            const desc = document.getElementById('result-risk-desc');

            if (riskPoints <= 4) {
                calculatedRiskLevel = 'green';
                banner.style.borderColor = '#10b981';
                icon.innerText = '🟢';
                title.style.color = '#10b981';
                title.innerText = 'สุขภาพดีมาก (ความเสี่ยงต่ำ)';
                desc.innerText = 'ดูแลตัวเองได้ดีมาก ทั้งเรื่องกิน ออกกำลังกาย และการนอน ทำต่อเนื่องไปเรื่อยๆ นะครับ';
                currentVoiceText = 'ผลตรวจเช็คสุขภาพโดยรวม ดีมากเลยค่ะ ดูแลสุขภาพได้ดีมาก ทำต่อเนื่องไปนะคะ';
            } else if (riskPoints <= 9) {
                calculatedRiskLevel = 'yellow';
                banner.style.borderColor = '#f59e0b';
                icon.innerText = '🟡';
                title.style.color = '#f59e0b';
                title.innerText = 'เริ่มมีสัญญาณเสี่ยง (ควรปรับตัว)';
                desc.innerText = 'เริ่มมีบางเรื่องที่สะสมความเสี่ยง ลองปรับตามคำแนะนำด้านล่าง สุขภาพจะดีขึ้นได้เร็วแน่นอนครับ';
                currentVoiceText = 'ผลตรวจรอบนี้ เริ่มมีสัญญาณเสี่ยงนิดหน่อยนะคะ ไม่เป็นไรค่ะ ชวนลดหวาน ลดเค็ม แล้วดื่มน้ำเปล่าเพิ่มขึ้นนะคะ';
            } else {
                calculatedRiskLevel = 'red';
                banner.style.borderColor = '#ef4444';
                icon.innerText = '🔴';
                title.style.color = '#ef4444';
                title.innerText = 'มีความเสี่ยงสูง (ควรตรวจเช็คละเอียด)';
                desc.innerText = 'มีสัญญาณเสี่ยงหลายด้าน แนะนำให้เริ่มปรับการกินอยู่ทันที และให้ อสม. หรือ รพ.สต. ช่วยตรวจวัดค่าความดันและค่าน้ำตาลจริงครับ';
                currentVoiceText = 'ผลตรวจรอบนี้ ต้องดูแลเป็นพิเศษค่ะ เดี๋ยวให้ ออ สอ มอ ช่วยตรวจเช็คความดันและค่าน้ำตาลให้นะคะ';
            }

            currentQ = totalQ + 1;
            updateUIState();
        }

        function playSelfVoiceCoach() {
            const btn = document.getElementById('btn-self-voice');
            let audioKey = 'normal';
            if (calculatedRiskLevel === 'yellow') audioKey = 'warning';
            if (calculatedRiskLevel === 'red') audioKey = 'critical';

            if (typeof ClinicalGuidance !== 'undefined' && ClinicalGuidance.speak) {
                ClinicalGuidance.speak(audioKey, currentVoiceText, btn);
            } else {
                if ('speechSynthesis' in window) {
                    window.speechSynthesis.cancel();
                    const utter = new SpeechSynthesisUtterance(currentVoiceText);
                    utter.lang = 'th-TH';
                    window.speechSynthesis.speak(utter);
                } else {
                    alert(currentVoiceText);
                }
            }
        }

        function shareResultCard() {
            if (navigator.share) {
                navigator.share({
                    title: 'ผลตรวจสุขภาพ NCDs ด้วยตนเอง - อำเภอ<?= DISTRICT_NAME ?>',
                    text: 'ฉันได้ทำแบบตรวจเช็คความเสี่ยงโรคความดันและเบาหวานเบื้องต้นผ่านระบบ NCDs Portal อำเภอ<?= DISTRICT_NAME ?> แล้ว!',
                    url: window.location.href
                }).catch(e => console.log('Share canceled', e));
            } else {
                alert('💡 ท่านสามารถแคปภาพหน้าจอนี้ (Screenshot) แล้วส่งไลน์ให้ อสม. หรือลูกหลานช่วยดูผลตรวจได้เลยครับ!');
            }
        }
    </script>
</body>
</html>
