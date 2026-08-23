<?php
// self_screening.php - แบบประเมินสุขภาพและพฤติกรรมเสี่ยง NCDs ด้วยตนเองสำหรับประชาชน
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
    <title>แบบประเมินสุขภาพและพฤติกรรม NCDs ด้วยตนเอง - อำเภอ<?= DISTRICT_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="apple-touch-icon" href="assets/icon.png">
    <link rel="manifest" href="manifest.json">
    <script src="assets/js/clinical_guidance.js"></script>

    <style>
        .self-step-card {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        .self-step-card.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Question Option Cards */
        .option-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }
        @media (min-width: 480px) {
            .option-grid-2 {
                grid-template-columns: 1fr 1fr;
            }
            .option-grid-3 {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }

        .option-card {
            position: relative;
            background: var(--bg-card);
            border-radius: 18px;
            padding: 16px;
            box-shadow: var(--neumorph-flat);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            display: flex;
            align-items: center;
            gap: 14px;
            user-select: none;
        }
        .option-card:hover {
            transform: translateY(-2px);
        }
        .option-card input[type="radio"] {
            display: none;
        }
        .option-card.selected {
            background: rgba(59, 130, 246, 0.08);
            border-color: #3b82f6;
            box-shadow: var(--neumorph-inset);
        }
        [data-theme="dark"] .option-card.selected {
            background: rgba(56, 189, 248, 0.12);
            border-color: #38bdf8;
        }
        .option-icon {
            font-size: 30px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: var(--bg-darker);
            flex-shrink: 0;
        }
        .option-text h4 {
            margin: 0 0 3px 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--text-primary);
        }
        .option-text p {
            margin: 0;
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.35;
        }

        /* Progress Bar */
        .progress-container {
            width: 100%;
            height: 8px;
            background: var(--bg-darker);
            border-radius: 9999px;
            margin-bottom: 24px;
            overflow: hidden;
            box-shadow: var(--neumorph-inset);
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #10b981);
            width: 25%;
            transition: width 0.3s ease;
            border-radius: 9999px;
        }

        /* Solution Action Box */
        .solution-box {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 18px;
            margin-bottom: 16px;
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
            padding-left: 20px;
            font-size: 14px;
            line-height: 1.65;
            color: var(--text-primary);
        }
        .solution-list li {
            margin-bottom: 6px;
        }

        /* Voice Coach Trigger */
        .voice-coach-bar {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(16, 185, 129, 0.12));
            border: 1.5px solid rgba(59, 130, 246, 0.3);
            border-radius: 16px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="vhv-accessibility">
    <div class="mobile-wrapper" style="padding-bottom: 80px;">
        
        <!-- Header Brand -->
        <div style="text-align: center; margin-bottom: 20px; padding-top: 10px;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 8px;">
                <img src="assets/icon.png" alt="Logo" style="width: 44px; height: 44px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                <span style="font-weight: 800; font-size: 16px; color: var(--color-accent);">NCDs Portal อำเภอ<?= DISTRICT_NAME ?></span>
            </div>
            <h2 style="font-size: 22px; font-weight: 800; margin: 4px 0; color: var(--text-primary);">ประเมินความเสี่ยงสุขภาพด้วยตนเอง</h2>
            <p style="font-size: 13.5px; color: var(--text-secondary); margin: 0;">เช็คพฤติกรรมง่ายๆ ไม่ต้องใช้ตัวเลข พร้อมคำแนะนำวิธีลดความดันและค่าน้ำตาล</p>
        </div>

        <!-- Progress Indicator -->
        <div class="progress-container">
            <div id="self-progress" class="progress-fill"></div>
        </div>

        <form id="self-screening-form" onsubmit="return false;">
            
            <!-- STEP 1: ข้อมูลพื้นฐานและรูปร่าง -->
            <div id="step-1" class="self-step-card active">
                <div style="margin-bottom: 18px;">
                    <span style="font-size: 13px; font-weight: 800; color: #3b82f6; text-transform: uppercase;">ขั้นตอนที่ 1 จาก 4</span>
                    <h3 style="font-size: 18px; font-weight: 800; margin: 4px 0; color: var(--text-primary);">👤 ข้อมูลเบื้องต้นและรูปร่าง</h3>
                </div>

                <!-- Gender -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 14.5px; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px;">1. เพศ</label>
                    <div class="option-grid option-grid-2">
                        <label class="option-card selected" onclick="selectOption(this, 'gender')">
                            <input type="radio" name="gender" value="male" checked>
                            <div class="option-icon">👨</div>
                            <div class="option-text">
                                <h4>ชาย</h4>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'gender')">
                            <input type="radio" name="gender" value="female">
                            <div class="option-icon">👩</div>
                            <div class="option-text">
                                <h4>หญิง</h4>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Age Group -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 14.5px; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px;">2. ช่วงอายุ</label>
                    <div class="option-grid">
                        <label class="option-card selected" onclick="selectOption(this, 'age_group')">
                            <input type="radio" name="age_group" value="young" checked>
                            <div class="option-icon">🌱</div>
                            <div class="option-text">
                                <h4>อายุน้อยกว่า 35 ปี</h4>
                                <p>วัยรุ่น / วัยทำงานตอนต้น</p>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'age_group')">
                            <input type="radio" name="age_group" value="middle">
                            <div class="option-icon">💼</div>
                            <div class="option-text">
                                <h4>อายุ 35 - 59 ปี</h4>
                                <p>วัยทำงาน (ควรเริ่มตรวจคัดกรองประจำปี)</p>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'age_group')">
                            <input type="radio" name="age_group" value="senior">
                            <div class="option-icon">🧓</div>
                            <div class="option-text">
                                <h4>อายุ 60 ปีขึ้นไป</h4>
                                <p>วัยผู้สูงอายุ (ควรดูแลสุขภาพอย่างสม่ำเสมอ)</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Body / Waist Shape -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 14.5px; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px;">3. สัดส่วนรูปร่างและรอบเอว</label>
                    <div class="option-grid">
                        <label class="option-card selected" onclick="selectOption(this, 'body_shape')">
                            <input type="radio" name="body_shape" value="slim" checked>
                            <div class="option-icon">✨</div>
                            <div class="option-text">
                                <h4>รูปร่างสมส่วน / พอดีตัว</h4>
                                <p>ไม่อึดอัด รอบเอวไม่เกินเกณฑ์ (ชาย < 36 นิ้ว, หญิง < 32 นิ้ว)</p>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'body_shape')">
                            <input type="radio" name="body_shape" value="chubby">
                            <div class="option-icon">👖</div>
                            <div class="option-text">
                                <h4>ท้วม / เริ่มมีพุงยื่น</h4>
                                <p>กางเกงเริ่มแน่น มีไขมันสะสมรอบเอวเล็กน้อย</p>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'body_shape')">
                            <input type="radio" name="body_shape" value="obese">
                            <div class="option-icon">⚠️</div>
                            <div class="option-text">
                                <h4>อ้วนลงพุงชัดเจน / น้ำหนักเกินมาก</h4>
                                <p>รอบเอวเกินเกณฑ์ เหนื่อยง่าย เคลื่อนไหวช้าลง</p>
                            </div>
                        </label>
                    </div>
                </div>

                <button type="button" onclick="goToStep(2)" class="btn-giant btn-giant-primary" style="width: 100%; margin: 0; padding: 14px; font-size: 16px;">
                    ถัดไป: พฤติกรรมการกินอาหาร →
                </button>
            </div>


            <!-- STEP 2: พฤติกรรมการกินอาหาร (หวาน มัน เค็ม ผัก) -->
            <div id="step-2" class="self-step-card">
                <div style="margin-bottom: 18px;">
                    <span style="font-size: 13px; font-weight: 800; color: #3b82f6; text-transform: uppercase;">ขั้นตอนที่ 2 จาก 4</span>
                    <h3 style="font-size: 18px; font-weight: 800; margin: 4px 0; color: var(--text-primary);">🥗 พฤติกรรมการกินอาหาร</h3>
                </div>

                <!-- Sweetness / Sugar -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 14.5px; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px;">4. การดื่มน้ำหวาน ชงหวาน ขนมหวาน</label>
                    <div class="option-grid">
                        <label class="option-card selected" onclick="selectOption(this, 'sweet_habit')">
                            <input type="radio" name="sweet_habit" value="low" checked>
                            <div class="option-icon">🥛</div>
                            <div class="option-text">
                                <h4>ดื่มน้ำเปล่าเป็นหลัก / หวานน้อย</h4>
                                <p>แทบไม่แตะน้ำอัดลม ชาหวาน กาแฟใส่นมข้น</p>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'sweet_habit')">
                            <input type="radio" name="sweet_habit" value="med">
                            <div class="option-icon">🧋</div>
                            <div class="option-text">
                                <h4>ดื่มบ้าง 1-3 วัน/สัปดาห์</h4>
                                <p>เฉพาะเวลาเหนื่อย หรือมีสังสรรค์</p>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'sweet_habit')">
                            <input type="radio" name="sweet_habit" value="high">
                            <div class="option-icon">🥤</div>
                            <div class="option-text">
                                <h4>ดื่มเกือบทุกวัน / ติดรสหวาน</h4>
                                <p>ขาดน้ำหวานหรือขนมหวานไม่ได้ ต้องมีทุกวัน</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Salt / Sodium / Fried -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 14.5px; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px;">5. การกินรสเค็ม ปลาร้า ซอสปรุงรส ของทอดมัน</label>
                    <div class="option-grid">
                        <label class="option-card selected" onclick="selectOption(this, 'salt_habit')">
                            <input type="radio" name="salt_habit" value="low" checked>
                            <div class="option-icon">🥣</div>
                            <div class="option-text">
                                <h4>กินรสกลางๆ / ไม่ปรุงเพิ่ม</h4>
                                <p>เลี่ยงของทอดมัน ซดน้ำแกงแต่น้อย</p>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'salt_habit')">
                            <input type="radio" name="salt_habit" value="med">
                            <div class="option-icon">🍲</div>
                            <div class="option-text">
                                <h4>กินรสจัดบ้างบางมื้อ</h4>
                                <p>มีส้มตำ ปลาร้า ของทอด สัปดาห์ละ 2-3 ครั้ง</p>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'salt_habit')">
                            <input type="radio" name="salt_habit" value="high">
                            <div class="option-icon">🧂</div>
                            <div class="option-text">
                                <h4>ชอบเค็มจัด เติมน้ำปลา/ผงชูรส/ชอบของทอด</h4>
                                <p>ซดน้ำแกงจนหมด ปลาร้าเข้มข้นทุกวัน ของทอดเป็นประจำ</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Vegetables -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 14.5px; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px;">6. ปริมาณการทานผักใบเขียวและผลไม้</label>
                    <div class="option-grid">
                        <label class="option-card selected" onclick="selectOption(this, 'veggie_habit')">
                            <input type="radio" name="veggie_habit" value="good" checked>
                            <div class="option-icon">🥦</div>
                            <div class="option-text">
                                <h4>ทานผักทุกมื้อ หรือเกือบทุกมื้อ</h4>
                                <p>มีผักสด ผักลวก ผักต้มในจานเสมอ</p>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'veggie_habit')">
                            <input type="radio" name="veggie_habit" value="poor">
                            <div class="option-icon">🥩</div>
                            <div class="option-text">
                                <h4>ไม่ค่อยทานผัก / ทานน้อยมาก</h4>
                                <p>เน้นเนื้อสัตว์ ข้าว แป้ง และของทอดเป็นหลัก</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="button" onclick="goToStep(1)" class="btn-giant btn-giant-secondary" style="flex: 1; margin: 0; padding: 14px;">
                        ← ย้อนกลับ
                    </button>
                    <button type="button" onclick="goToStep(3)" class="btn-giant btn-giant-primary" style="flex: 1.5; margin: 0; padding: 14px;">
                        ถัดไป: การขยับร่างกาย →
                    </button>
                </div>
            </div>


            <!-- STEP 3: การออกกำลังกาย & การเคลื่อนไหวร่างกาย -->
            <div id="step-3" class="self-step-card">
                <div style="margin-bottom: 18px;">
                    <span style="font-size: 13px; font-weight: 800; color: #3b82f6; text-transform: uppercase;">ขั้นตอนที่ 3 จาก 4</span>
                    <h3 style="font-size: 18px; font-weight: 800; margin: 4px 0; color: var(--text-primary);">🏃 การออกกำลังกายและขยับร่างกาย</h3>
                </div>

                <!-- Physical Activity -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 14.5px; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px;">7. กิจกรรมออกแรง / ออกกำลังกายในแต่ละสัปดาห์</label>
                    <div class="option-grid">
                        <label class="option-card selected" onclick="selectOption(this, 'exercise_habit')">
                            <input type="radio" name="exercise_habit" value="regular" checked>
                            <div class="option-icon">🏃‍♂️</div>
                            <div class="option-text">
                                <h4>ออกกำลังกายเป็นประจำ (3-5 วัน/สัปดาห์)</h4>
                                <p>เดินเร็ว วิ่ง ปั่นจักรยาน ทำงานสวนเหงื่อออก 30 นาทีขึ้นไป</p>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'exercise_habit')">
                            <input type="radio" name="exercise_habit" value="some">
                            <div class="option-icon">🚶</div>
                            <div class="option-text">
                                <h4>ขยับร่างกายบ้าง (1-2 วัน/สัปดาห์)</h4>
                                <p>เดินทำงานบ้าน กวาดใบไม้ ขยับตัวเป็นระยะ</p>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'exercise_habit')">
                            <input type="radio" name="exercise_habit" value="sedentary">
                            <div class="option-icon">🛋️</div>
                            <div class="option-text">
                                <h4>แทบไม่ได้ออกกำลังกาย / นั่งนานทั้งวัน</h4>
                                <p>นั่งทำงานหรือนอนดูมือถือ/ทีวี ขยับร่างกายน้อยมาก</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="button" onclick="goToStep(2)" class="btn-giant btn-giant-secondary" style="flex: 1; margin: 0; padding: 14px;">
                        ← ย้อนกลับ
                    </button>
                    <button type="button" onclick="goToStep(4)" class="btn-giant btn-giant-primary" style="flex: 1.5; margin: 0; padding: 14px;">
                        ถัดไป: การนอนและความเครียด →
                    </button>
                </div>
            </div>


            <!-- STEP 4: การนอน ความเครียด บุหรี่ สุรา -->
            <div id="step-4" class="self-step-card">
                <div style="margin-bottom: 18px;">
                    <span style="font-size: 13px; font-weight: 800; color: #3b82f6; text-transform: uppercase;">ขั้นตอนที่ 4 จาก 4</span>
                    <h3 style="font-size: 18px; font-weight: 800; margin: 4px 0; color: var(--text-primary);">😴 การนอนหลับ ความเครียด และสารเสพติด</h3>
                </div>

                <!-- Sleep (1น.) -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 14.5px; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px;">8. คุณภาพการนอนหลับ</label>
                    <div class="option-grid">
                        <label class="option-card selected" onclick="selectOption(this, 'sleep_habit')">
                            <input type="radio" name="sleep_habit" value="good" checked>
                            <div class="option-icon">🌙</div>
                            <div class="option-text">
                                <h4>หลับสนิทดี ตื่นมาสดชื่น</h4>
                                <p>นอนหลับ 6-8 ชั่วโมงต่อวัน ไม่ค่อยตื่นกลางดึก</p>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'sleep_habit')">
                            <input type="radio" name="sleep_habit" value="poor">
                            <div class="option-icon">🥱</div>
                            <div class="option-text">
                                <h4>หลับๆ ตื่นๆ / หลับยาก ตื่นไม่สดชื่น</h4>
                                <p>นอนดึก ตื่นกลางดึกบ่อย พักผ่อนไม่เพียงพอ</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Smoking & Alcohol -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 14.5px; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px;">9. การสูบบุหรี่และดื่มสุรา</label>
                    <div class="option-grid">
                        <label class="option-card selected" onclick="selectOption(this, 'substance_habit')">
                            <input type="radio" name="substance_habit" value="none" checked>
                            <div class="option-icon">🌿</div>
                            <div class="option-text">
                                <h4>ไม่สูบบุหรี่ และ ไม่ดื่มสุรา</h4>
                                <p>หรือเลิกเด็ดขาดแล้ว</p>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'substance_habit')">
                            <input type="radio" name="substance_habit" value="some">
                            <div class="option-icon">🥂</div>
                            <div class="option-text">
                                <h4>ดื่มเฉพาะงานสังสรรค์ / สูบบ้างบางครั้ง</h4>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'substance_habit')">
                            <input type="radio" name="substance_habit" value="regular">
                            <div class="option-icon">🚬</div>
                            <div class="option-text">
                                <h4>สูบบุหรี่ประจำ หรือ ดื่มสุราเป็นประจำ</h4>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Family History -->
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 14.5px; font-weight: 700; color: var(--text-secondary); margin-bottom: 8px;">10. ประวัติคนในครอบครัวสายตรง (พ่อ แม่ พี่ น้อง)</label>
                    <div class="option-grid">
                        <label class="option-card selected" onclick="selectOption(this, 'family_history')">
                            <input type="radio" name="family_history" value="no" checked>
                            <div class="option-icon">🛡️</div>
                            <div class="option-text">
                                <h4>ไม่มีประวัติเป็นโรคความดันหรือเบาหวาน</h4>
                            </div>
                        </label>
                        <label class="option-card" onclick="selectOption(this, 'family_history')">
                            <input type="radio" name="family_history" value="yes">
                            <div class="option-icon">🧬</div>
                            <div class="option-text">
                                <h4>มีพ่อ แม่ หรือพี่น้องเป็นความดันหรือเบาหวาน</h4>
                                <p>มีพันธุกรรมเสี่ยง ควรเฝ้าระวังพฤติกรรม</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="button" onclick="goToStep(3)" class="btn-giant btn-giant-secondary" style="flex: 1; margin: 0; padding: 14px;">
                        ← ย้อนกลับ
                    </button>
                    <button type="button" onclick="calculateSelfResults()" class="btn-giant btn-giant-success" style="flex: 1.5; margin: 0; padding: 14px; background: linear-gradient(135deg, #10b981, #059669); color: white;">
                        🎯 ดูผลวิเคราะห์สุขภาพ ✨
                    </button>
                </div>
            </div>


            <!-- STEP 5: หน้าสรุปผลสุขภาพ & คำแนะนำลดความดัน-ลดน้ำตาล -->
            <div id="step-5" class="self-step-card">
                
                <!-- Overall Risk Banner -->
                <div id="result-risk-banner" style="background: var(--bg-card); border-radius: 24px; padding: 22px; margin-bottom: 20px; box-shadow: var(--neumorph-flat); text-align: center; border: 2px solid #10b981;">
                    <div id="result-risk-icon" style="font-size: 48px; margin-bottom: 6px;">🟢</div>
                    <h3 id="result-risk-title" style="margin: 0 0 6px 0; font-size: 20px; font-weight: 800; color: #10b981;">สุขภาพโดยรวมอยู่ในเกณฑ์ดีเยี่ยม</h3>
                    <p id="result-risk-desc" style="margin: 0; font-size: 14px; color: var(--text-secondary); line-height: 1.5;">พฤติกรรมการดูแลตนเองดีมาก รักษาความสดชื่นและสุขภาพแบบนี้ต่อเนื่องไปนะครับ</p>
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
                        <span>🩺</span> <span>ถ้าอยากลดความดัน / ป้องกันความดันสูง ต้องทำอย่างไร?</span>
                    </div>
                    <ul class="solution-list" id="bp-advice-list">
                        <!-- Populated by JS -->
                    </ul>
                </div>

                <!-- Solution Card 2: อยากลดค่าน้ำตาล / เบาหวาน ต้องทำอย่างไร? -->
                <div class="solution-box sugar-box">
                    <div class="solution-title" style="color: #d97706;">
                        <span>🩸</span> <span>ถ้าอยากลดค่าน้ำตาล / ป้องกันโรคเบาหวาน ต้องทำอย่างไร?</span>
                    </div>
                    <ul class="solution-list" id="sugar-advice-list">
                        <!-- Populated by JS -->
                    </ul>
                </div>

                <!-- Community Referral Card -->
                <div style="background: linear-gradient(135deg, #0d2c54, #1e3a8a); color: white; border-radius: 20px; padding: 20px; margin-bottom: 24px; box-shadow: 0 10px 25px rgba(13, 44, 84, 0.3); text-align: left;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                        <div style="background: rgba(255,255,255,0.15); border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; font-size: 20px;">🏥</div>
                        <div>
                            <h4 style="margin: 0; font-size: 16px; font-weight: 800; color: #38bdf8;">อยากทราบค่าความดันและค่าน้ำตาลที่แท้จริง?</h4>
                            <p style="margin: 2px 0 0 0; font-size: 12.5px; color: #cbd5e1;">ระบบสาธารณสุขอำเภอ<?= DISTRICT_NAME ?> พร้อมดูแลท่านฟรี</p>
                        </div>
                    </div>
                    <p style="font-size: 13.5px; line-height: 1.5; margin: 10px 0; color: #f1f5f9;">
                        ผลประเมินนี้เป็นการวิเคราะห์จากพฤติกรรมเบื้องต้น แนะนำให้ท่านติดต่อ <strong>อสม. ประจำคุ้มบ้านของท่าน</strong> เพื่อตรวจวัดความดันโลหิตและเจาะน้ำตาลปลายนิ้ว (DTX) หรือเข้ารับบริการตรวจสุขภาพประจำปีได้ฟรีที่ <strong>รพ.สต. ใกล้บ้าน</strong> ได้เลยครับ
                    </p>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button type="button" onclick="shareResultCard()" class="btn-giant btn-giant-primary" style="margin: 0; padding: 14px; font-size: 15px; background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                        📤 แชร์หรือบันทึกผลการประเมินให้ อสม. ดู
                    </button>
                    <button type="button" onclick="goToStep(1)" class="btn-giant btn-giant-secondary" style="margin: 0; padding: 12px; font-size: 14px;">
                        🔄 ประเมินใหม่อีกครั้ง
                    </button>
                    <a href="index.php" style="text-align: center; color: var(--color-accent); text-decoration: none; font-size: 14px; font-weight: 700; margin-top: 8px; display: block;">
                        ← กลับหน้าหลัก
                    </a>
                </div>

            </div>

        </form>

    </div>

    <script>
        let currentStep = 1;
        let calculatedRiskLevel = 'green'; // green, yellow, red
        let currentVoiceText = '';

        function selectOption(el, inputName) {
            const container = el.parentElement;
            const allCards = container.querySelectorAll('.option-card');
            allCards.forEach(c => c.classList.remove('selected'));
            
            el.classList.add('selected');
            const radio = el.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        }

        function goToStep(stepNumber) {
            document.querySelectorAll('.self-step-card').forEach(card => card.classList.remove('active'));
            
            const targetCard = document.getElementById('step-' + stepNumber);
            if (targetCard) {
                targetCard.classList.add('active');
                currentStep = stepNumber;
            }

            // Update Progress bar
            const progress = document.getElementById('self-progress');
            if (progress) {
                progress.style.width = (stepNumber * 25) + '%';
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
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

            // Risk Scoring Calculation
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

            // Generate Tailored BP Advice
            const bpList = document.getElementById('bp-advice-list');
            bpList.innerHTML = '';

            const bpAdvices = [];
            if (salt === 'high' || salt === 'med') {
                bpAdvices.push('<strong>ลดเค็ม ลดโซเดียม:</strong> ลดการปรุงน้ำปลา ซีอิ๊ว และผงชูรสลงครึ่งหนึ่ง เลี่ยงการซดน้ำแกงหรือน้ำส้มตำจนหมดถ้วย');
            } else {
                bpAdvices.push('<strong>รักษาการกินจืด:</strong> ปรุงอาหารรสธรรมชาติ เลี่ยงของหมักดองและอาหารแปรรูป (ไส้กรอก กุนเชียง)');
            }

            if (exercise === 'sedentary' || exercise === 'some') {
                bpAdvices.push('<strong>เพิ่มการขยับร่างกาย:</strong> เริ่มต้นง่ายๆ ด้วยการเดินเร็ว แกว่งแขน หรือปั่นจักรยานวันละ 20-30 นาที จะช่วยให้หลอดเลือดยืดหยุ่นและลดความดันตัวบนได้ 5-10 mmHg');
            } else {
                bpAdvices.push('<strong>ออกกำลังกายต่อเนื่อง:</strong> ออกกำลังกายแบบแอโรบิกสม่ำเสมอสัปดาห์ละ 150 นาที เพื่อให้หัวใจแข็งแรง');
            }

            if (sleep === 'poor') {
                bpAdvices.push('<strong>จัดเวลานอนให้มีคุณภาพ:</strong> เข้านอนก่อน 4 ทุ่ม และงดเล่นมือถือ 30 นาทีก่อนนอน เพื่อให้ฮอร์โมนความดันได้พักผ่อนเต็มที่');
            }

            if (substance === 'regular' || substance === 'some') {
                bpAdvices.push('<strong>งดหรือลดบุหรี่/สุรา:</strong> สารนิโคตินและแอลกอฮอล์ทำให้หลอดเลือดหดเกร็งทันที การลดลงจะช่วยให้ความดันกลับสู่ระดับปกติได้เร็ว');
            }

            bpAdvices.push('<strong>ดื่มน้ำเปล่าสะอาด 6-8 แก้วต่อวัน:</strong> ช่วยให้เลือดไหลเวียนสะดวก ไม่ข้นหนืด');

            bpAdvices.forEach(adv => {
                const li = document.createElement('li');
                li.innerHTML = adv;
                bpList.appendChild(li);
            });


            // Generate Tailored Blood Sugar Advice
            const sugarList = document.getElementById('sugar-advice-list');
            sugarList.innerHTML = '';

            const sugarAdvices = [];
            if (sweet === 'high' || sweet === 'med') {
                sugarAdvices.push('<strong>ลดหวานแบบสั่งได้:</strong> สั่งเครื่องดื่ม "หวานน้อย" (ไม่เกิน 25%) หรืองดน้ำอัดลม ชาหวาน กาแฟใส่นมข้น เปลี่ยนมาดื่มน้ำเปล่าหรือชาชงไม่ใส่น้ำตาล');
            } else {
                sugarAdvices.push('<strong>คงการดื่มน้ำเปล่าเป็นหลัก:</strong> หลีกเลี่ยงน้ำหวานแฝง เช่น น้ำผลไม้กล่อง หรือนมเปรี้ยวรสหวาน');
            }

            if (shape === 'obese' || shape === 'chubby') {
                sugarAdvices.push('<strong>คุมแป้งและลดรอบเอว:</strong> ลดปริมาณข้าวเหนียว ข้าวขาว เส้นก๋วยเตี๋ยวลง 1 ใน 3 และเปลี่ยนมาทานข้าวกล้องหรือธัญพืช ช่วยลดไขมันสะสมในตับ');
            }

            if (veggie === 'poor') {
                sugarAdvices.push('<strong>ทานผักนำมื้ออาหาร:</strong> เพิ่มผักใบเขียว (ผักบุ้ง ผักกาด กะหล่ำปลี ผักก้านจอง) ครึ่งจานในทุกมื้อ เส้นใยอาหารจะช่วยชะลอการดูดซึมน้ำตาลเข้ากระแสเลือด');
            } else {
                sugarAdvices.push('<strong>ทานผักและผลไม้รสไม่หวาน:</strong> เลือกผลไม้ดัชนีน้ำตาลต่ำ เช่น ฝรั่ง แอปเปิ้ลเขียว ส้มโอ หลีกเลี่ยงทุเรียน ลำไย มะม่วงสุก');
            }

            sugarAdvices.push('<strong>ไม่กินจุบจิบระหว่างมื้อ:</strong> เว้นช่วงให้ร่างกายได้ดึงอินซูลินและน้ำตาลสะสมไปเผาผลาญ');

            sugarAdvices.forEach(adv => {
                const li = document.createElement('li');
                li.innerHTML = adv;
                sugarList.appendChild(li);
            });


            // Render Overall Banner & Risk Level
            const banner = document.getElementById('result-risk-banner');
            const icon = document.getElementById('result-risk-icon');
            const title = document.getElementById('result-risk-title');
            const desc = document.getElementById('result-risk-desc');

            if (riskPoints <= 4) {
                calculatedRiskLevel = 'green';
                banner.style.borderColor = '#10b981';
                icon.innerText = '🟢';
                title.style.color = '#10b981';
                title.innerText = 'สุขภาพโดยรวมอยู่ในเกณฑ์ดีเยี่ยม (ความเสี่ยงต่ำ)';
                desc.innerText = 'พฤติกรรมสุขภาพของท่านดีมาก รักษาการกิน การนอน และการขยับร่างกายแบบนี้ต่อเนื่องไปนะครับ';
                currentVoiceText = 'ผลประเมินสุขภาพโดยรวมอยู่ในเกณฑ์ดีเยี่ยมค่ะ ดูแลสุขภาพได้ดีมาก ทำต่อเนื่องไปนะคะ';
            } else if (riskPoints <= 9) {
                calculatedRiskLevel = 'yellow';
                banner.style.borderColor = '#f59e0b';
                icon.innerText = '🟡';
                title.style.color = '#f59e0b';
                title.innerText = 'เริ่มมีสัญญาณเสี่ยงโรค NCDs (ระดับปานกลาง)';
                desc.innerText = 'มีพฤติกรรมบางอย่างที่เริ่มสะสมความเสี่ยงต่อความดันและเบาหวาน หากปรับตามคำแนะนำด้านล่าง สุขภาพจะกลับมาสดชื่นแข็งแรงได้เร็วแน่นอนครับ';
                currentVoiceText = 'ผลประเมินรอบนี้ เริ่มมีสัญญาณเสี่ยงนิดหน่อยนะคะ ไม่เป็นไรค่ะ ชวนลดหวาน ลดเค็ม แล้วดื่มน้ำเปล่าเพิ่มขึ้นนะคะ';
            } else {
                calculatedRiskLevel = 'red';
                banner.style.borderColor = '#ef4444';
                icon.innerText = '🔴';
                title.style.color = '#ef4444';
                title.innerText = 'พฤติกรรมมีความเสี่ยงสูงต่อโรคความดันและเบาหวาน';
                desc.innerText = 'พบพฤติกรรมเสี่ยงสะสมหลายด้าน แนะนำให้เริ่มปรับเปลี่ยนตามคำแนะนำทันที และประสาน อสม. หรือ รพ.สต. เพื่อตรวจวัดค่าเลือดจริงครับ';
                currentVoiceText = 'พฤติกรรมรอบนี้ ต้องดูแลเป็นพิเศษค่ะ เดี๋ยวให้ ออ สอ มอ ช่วยตรวจเช็คความดันและค่าน้ำตาลให้นะคะ';
            }

            goToStep(5);
        }

        function playSelfVoiceCoach() {
            const btn = document.getElementById('btn-self-voice');
            const btnText = document.getElementById('voice-btn-text');

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
                    title: 'ผลประเมินสุขภาพ NCDs ด้วยตนเอง - อำเภอ<?= DISTRICT_NAME ?>',
                    text: 'ฉันได้ทำแบบประเมินความเสี่ยงโรคความดันและเบาหวานเบื้องต้นผ่านระบบ NCDs Portal อำเภอ<?= DISTRICT_NAME ?> แล้ว!',
                    url: window.location.href
                }).catch(e => console.log('Share canceled', e));
            } else {
                alert('💡 ท่านสามารถแคปภาพหน้าจอนี้ (Screenshot) แล้วส่งไลน์ให้ อสม. หรือลูกหลานช่วยดูผลประเมินได้เลยครับ!');
            }
        }
    </script>
</body>
</html>
