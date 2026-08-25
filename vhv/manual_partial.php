<?php
// vhv/manual_partial.php - Reusable Manual Component (Embeddable Tab & Standalone)
$path_prefix = isset($path_prefix) ? $path_prefix : '../';
$district = defined('DISTRICT_NAME') ? DISTRICT_NAME : 'ตาลสุม';
$province = defined('PROVINCE_NAME') ? PROVINCE_NAME : 'อุบลราชธานี';
?>
<style>
/* Top Bar */
        
        
        

        /* Hero Banner */
        .manual-hero {
            text-align: center;
            padding: 22px 18px;
            border-radius: 24px;
            background: var(--bg-card);
            box-shadow: var(--neumorph-flat);
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.6);
        }
        [data-theme="dark"] .manual-hero {
            border-color: rgba(255,255,255,0.05);
        }
        .manual-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #3b82f6, #10b981, #f59e0b);
        }
        .manual-hero-logo {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
            margin-bottom: 10px;
        }
        .manual-hero h1 {
            font-size: 21px;
            font-weight: 900;
            margin: 0 0 4px 0;
            color: var(--text-primary);
        }
        .manual-hero p {
            color: var(--text-secondary);
            font-size: 13px;
            margin: 0;
            line-height: 1.45;
        }

        /* Search Box */
        .search-container {
            margin-bottom: 16px;
            position: relative;
        }
        .search-input {
            width: 100%;
            padding: 12px 16px 12px 42px;
            border-radius: 50px;
            border: none;
            background: var(--bg-card);
            box-shadow: var(--neumorph-inset);
            font-size: 14px;
            color: var(--text-primary);
            box-sizing: border-box;
            outline: none;
            font-family: inherit;
        }
        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: var(--text-muted);
        }

        /* Filter Pills */
        .filter-scroll {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 8px;
            margin-bottom: 16px;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }
        .filter-scroll::-webkit-scrollbar {
            display: none;
        }
        .filter-pill {
            background: var(--bg-card);
            border: none;
            color: var(--text-secondary);
            padding: 7px 14px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s;
        }
        .filter-pill.active {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        }

        /* Accordion Items */
        .manual-accordion {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .accordion-item {
            background-color: var(--bg-card);
            border-radius: 20px;
            box-shadow: var(--neumorph-flat);
            overflow: hidden;
            transition: all 0.25s ease;
            border: 1.5px solid transparent;
        }
        .accordion-item.open {
            border-color: rgba(59, 130, 246, 0.3);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }
        .accordion-header {
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        .accordion-header:active {
            background: var(--bg-darker);
        }
        .accordion-title-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
        }
        .accordion-icon-badge {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: var(--neumorph-flat);
        }
        [data-theme="dark"] .accordion-icon-badge {
            background: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
        }
        .accordion-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0;
            line-height: 1.3;
        }
        .accordion-tag {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
        }
        .accordion-arrow {
            font-size: 13px;
            color: var(--text-muted);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
            margin-left: 8px;
        }
        .accordion-item.open .accordion-arrow {
            transform: rotate(180deg);
            color: #2563eb;
        }

        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .accordion-
        [data-theme="dark"] .accordion-

        /* Step List */
        .step-list {
            margin: 12px 0 0 0;
            padding-left: 0;
            list-style: none;
        }
        .step-item {
            position: relative;
            padding-left: 36px;
            margin-bottom: 16px;
        }
        .step-item::before {
            content: '';
            position: absolute;
            left: 13px;
            top: 26px;
            bottom: -12px;
            width: 2px;
            background-color: var(--bg-darker);
        }
        .step-item:last-child::before {
            display: none;
        }
        .step-number {
            position: absolute;
            left: 0;
            top: 0;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background-color: #3b82f6;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 12px;
            box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
        }
        .step-content h4 {
            margin: 0 0 2px 0;
            font-size: 14px;
            font-weight: 800;
            color: var(--text-primary);
        }
        .step-content p {
            margin: 0;
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* Alert Boxes */
        .alert-box {
            padding: 12px 14px;
            border-radius: 14px;
            margin: 12px 0;
            display: flex;
            gap: 10px;
            align-items: flex-start;
            text-align: left;
        }
        .alert-box-info {
            background-color: rgba(59, 130, 246, 0.08);
            border-left: 4px solid #3b82f6;
        }
        .alert-box-success {
            background-color: rgba(16, 185, 129, 0.08);
            border-left: 4px solid #10b981;
        }
        .alert-box-warning {
            background-color: rgba(245, 158, 11, 0.08);
            border-left: 4px solid #f59e0b;
        }
        .alert-box-danger {
            background-color: rgba(239, 68, 68, 0.08);
            border-left: 4px solid #ef4444;
        }
        .alert-title {
            font-weight: 800;
            font-size: 13px;
            margin-bottom: 2px;
            color: var(--text-primary);
        }
        .alert-desc {
            font-size: 12.5px;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.45;
        }

        /* Clay Visual Cards */
        .clay-feature-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.8), rgba(241, 245, 249, 0.9));
            border-radius: 16px;
            padding: 12px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        [data-theme="dark"] .clay-feature-card {
            background: rgba(30, 41, 59, 0.7);
            border-color: rgba(51, 65, 85, 0.8);
        }
        .clay-feature-img {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }
        .clay-feature-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .clay-feature-text {
            font-size: 12.5px;
            line-height: 1.4;
            color: var(--text-secondary);
        }
        .clay-feature-text strong {
            color: var(--text-primary);
            display: block;
            font-size: 13.5px;
            margin-bottom: 2px;
        }

        .hl-code {
            background-color: rgba(59, 130, 246, 0.12);
            color: #2563eb;
            padding: 2px 6px;
            border-radius: 6px;
            font-weight: 800;
            font-family: monospace;
        }
        [data-theme="dark"] .hl-code {
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.15);
        }
</style>

<div class="manual-tab-container" style="margin-top: 10px;">
<!-- Hero Section -->
        <div class="manual-hero">
            <img src="<?= $path_prefix ?>assets/icon.png" alt="NCDs Portal Logo" class="manual-hero-logo">
            <h1>📖 คู่มือการใช้งานระบบ NCDs</h1>
            <p>ระบบบันทึกคัดกรอง ดูแลสุขภาพ และประเมินความเสี่ยงโรคเรื้อรัง<br>อำเภอ<?= htmlspecialchars($district) ?> จังหวัด<?= htmlspecialchars($province) ?></p>
        </div>

        <!-- Search Bar -->
        <div class="search-container">
            <span class="search-icon">🔍</span>
            <input type="text" id="manual-search" class="search-input" placeholder="พิมพ์ค้นหาหัวข้อ เช่น คัดกรอง, DPAC, เสียงโค้ช, รหัสผ่าน..." oninput="filterManual()">
        </div>

        <!-- Category Filter Pills -->
        <div class="filter-scroll">
            <button type="button" class="filter-pill active" onclick="setCategory('all', this)">📌 ทั้งหมด</button>
            <button type="button" class="filter-pill" onclick="setCategory('emergency-alert', this)">🚨 แจ้งเหตุวิกฤต</button>
            <button type="button" class="filter-pill" onclick="setCategory('vhv-screen', this)">🩺 คัดกรอง อสม.</button>
            <button type="button" class="filter-pill" onclick="setCategory('citizen-screen', this)">🌱 ประเมินตนเอง</button>
            <button type="button" class="filter-pill" onclick="setCategory('voice-coach', this)">🎙️ โค้ชเสียงพูด</button>
            <button type="button" class="filter-pill" onclick="setCategory('dpac', this)">❤️ งาน DPAC</button>
            <button type="button" class="filter-pill" onclick="setCategory('pwa-install', this)">📲 ติดตั้งแอป</button>
            <button type="button" class="filter-pill" onclick="setCategory('leader', this)">👑 สิทธิ์ประธาน</button>
        </div>

        <!-- Accordion Guide List -->
        <div class="manual-accordion" id="manual-list">

            <!-- 1. การติดตั้งแอปพลิเคชัน NCDs Portal ลงมือถือ -->
            <div class="accordion-item" data-category="pwa-install">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">📲</div>
                        <div>
                            <h3 class="accordion-title">1. การติดตั้งแอป "NCDs Portal" ลงมือถือ</h3>
                            <span class="accordion-tag">ไม่ต้องโหลดผ่าน App Store / Play Store</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>แอปพลิเคชัน NCDs Portal เป็นระบบ Progressive Web App (PWA) ติดตั้งได้ฟรี ทันที ไม่มีไฟล์หนัก และเปิดใช้งานได้เต็มหน้าจอเหมือนแอปพลิเคชันมือถือ:</p>

                        <div class="clay-feature-card">
                            <div class="clay-feature-img">
                                <img src="<?= $path_prefix ?>assets/icon.png" alt="โลโก้แอป">
                            </div>
                            <div class="clay-feature-text">
                                <strong>ติดตั้งง่ายเพียงแตะที่โลโก้</strong>
                                ในหน้าหลัก อสม. ให้แตะที่รูปโลโก้ที่มีไอคอน 📲 ระบบจะเปิดหน้าต่างติดตั้งขึ้นมาทันที
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">A</span>
                                <div class="step-content">
                                    <h4>สำหรับมือถือ Android (Google Chrome):</h4>
                                    <p>แตะที่โลโก้ในหน้าหลัก หรือกดปุ่ม 3 จุดมุมขวาบนของ Chrome แล้วเลือก <strong>"ติดตั้งแอป" (Install App)</strong> หรือ <strong>"เพิ่มลงในหน้าจอหลัก"</strong></p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">B</span>
                                <div class="step-content">
                                    <h4>สำหรับ iPhone / iPad (Safari):</h4>
                                    <p>กดปุ่ม <strong>แชร์ (Share ⎋)</strong> ด้านล่างจอ แล้วเลื่อนลงมาเลือก <strong>"เพิ่มไปยังหน้าจอโฮม" (Add to Home Screen ⊞)</strong></p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 2. การประเมินสุขภาพตนเองสำหรับประชาชน (Claymorphism Self-Screening) -->
            <div class="accordion-item" data-category="citizen-screen">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">🌱</div>
                        <div>
                            <h3 class="accordion-title">2. แบบประเมินสุขภาพตนเอง (สำหรับประชาชน)</h3>
                            <span class="accordion-tag">เข้าทำได้ฟรี ไม่ต้องล็อกอิน • 10 ข้อจบใน 30 วิ</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>ประชาชนทั่วไปในอำเภอ<?= htmlspecialchars($district) ?> สามารถเข้าตรวจเช็คพฤติกรรมเสี่ยงความดันโลหิตสูงและเบาหวานได้ด้วยตนเองผ่านเมนูหน้าแรก:</p>

                        <div class="clay-feature-card">
                            <div class="clay-feature-img">
                                <img src="<?= $path_prefix ?>assets/img/clay/sprout.jpg" alt="ประเมินตนเอง">
                            </div>
                            <div class="clay-feature-text">
                                <strong>ดีไซน์ 3D ดินน้ำมัน (Claymorphism)</strong>
                                สวยงาม ตัวหนังสือใหญ่เด่นชัด 1 คำถามต่อ 1 สไลด์ พอดีหน้าจอมือถือโดยไม่ต้องเลื่อนจอ
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>แตะปุ๊บ ไปต่อปั๊บ (Auto-Advance):</h4>
                                    <p>เมื่อแตะเลือกคำตอบ ระบบจะบันทึกและสไลด์ไปคำถามถัดไปให้อัตโนมัติทันที</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>ปุ่มควบคุมลอยชิดขอบล่าง (‹ และ ›):</h4>
                                    <p>สามารถกดย้อนกลับหรือข้ามคำถามได้สะดวกด้วยนิ้วโป้ง โดยปุ่มอยู่ตำแหน่งที่ปลอดภัย ไม่บังคำตอบ</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">3</span>
                                <div class="step-content">
                                    <h4>สรุปผลและแนวทางปฏิบัติเฉพาะบุคคล:</h4>
                                    <p>บอกชัดเจนว่า <strong>"ถ้าอยากลดความดันต้องทำอย่างไร?"</strong> และ <strong>"ถ้าอยากลดค่าน้ำตาลต้องทำอย่างไร?"</strong> พร้อมปุ่มส่งต่อให้ อสม. หรือ รพ.สต. ตรวจวัดค่าจริง</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 3. ระบบเสียงโค้ชสุขภาพอัจฉริยะ (Clinical Voice Guidance Coach) -->
            <div class="accordion-item" data-category="voice-coach">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">🎙️</div>
                        <div>
                            <h3 class="accordion-title">3. ระบบเสียงโค้ชสุขภาพ (Voice Coach)</h3>
                            <span class="accordion-tag">สรุปผลวิเคราะห์สุขภาพด้วยเสียงภาษาไทยเป็นธรรมชาติ</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>ระบบช่วยอ่านสรุปผลการคัดกรองและให้คำแนะนำสุขภาพแก่ผู้รับบริการ ด้วยเสียงภาษาไทยที่เป็นมิตรและเข้าใจง่าย:</p>

                        <div class="alert-box alert-box-success">
                            <div>
                                <div class="alert-title">🔊 สำเนียงธรรมชาติ & ออกเสียงคำย่อถูกต้อง</div>
                                <p class="alert-desc">ระบบออกเสียง <strong>"ออ-สอ-มอ"</strong> สำหรับคำว่า อสม. และ <strong>"รอ-พอ-สอ-ตอ"</strong> สำหรับ รพ.สต. ด้วยจังหวะที่นุ่มนวล ไม่เร็วเกินไป</p>
                            </div>
                        </div>

                        <p><strong>วิธีเปิดฟังเสียง:</strong></p>
                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>ในฟอร์มคัดกรอง อสม. & ติดตาม DPAC:</h4>
                                    <p>เมื่อกรอกค่าความดันหรือน้ำตาล จะมีแถบวิเคราะห์ผลสุขภาพสีเขียว/เหลือง/แดง พร้อมปุ่ม <strong>"🔊 เปิดเสียงคำแนะนำ"</strong> แตะเพื่อให้ระบบพูดให้ผู้รับบริการฟังได้ทันที</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>ในหน้าประเมินสุขภาพตนเอง:</h4>
                                    <p>หน้าสรุปผลจะมีปุ่ม <strong>"🔊 เปิดเสียงพูด"</strong> สำหรับฟังคำแนะนำสรุปพฤติกรรมการกิน การนอน และการออกกำลังกาย</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 4. ขั้นตอนการลงพื้นที่คัดกรองของ อสม. & กฎความถูกต้องของข้อมูล -->
            <div class="accordion-item" data-category="vhv-screen">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">🩺</div>
                        <div>
                            <h3 class="accordion-title">4. การคัดกรอง อสม. & กฎความถูกต้อง</h3>
                            <span class="accordion-tag">บังคับเลือกคำแนะนำอย่างน้อย 1 ข้อ และกรอกข้อมูลครบถ้วน</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>เพื่อรักษามาตรฐานข้อมูลสุขภาพระดับอำเภอ ระบบคัดกรองมีข้อกำหนดความสมบูรณ์ของฟอร์มดังนี้:</p>

                        <div class="alert-box alert-box-warning">
                            <div>
                                <div class="alert-title">⚠️ กฎการตรวจสอบก่อนกดส่งงาน (Strict Validation)</div>
                                <p class="alert-desc">
                                    1. ต้องกรอก <strong>น้ำหนัก และ ส่วนสูง</strong> (เพื่อคำนวณ BMI)<br>
                                    2. ต้องกรอกค่า <strong>ความดันโลหิต (SYS/DIA) หรือ ค่าน้ำตาล (DTX)</strong><br>
                                    3. <strong>ต้องแตะเลือกคำแนะนำสุขภาพอย่างน้อย 1 รายการ</strong> (แตะที่รูปไอคอนคำแนะนำ 9 แบบ) ระบบจะไม่ยอมให้กดส่งงานหากไม่มีการให้คำแนะนำสุขภาพ
                                </p>
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>สแกน QR Code หรือเลือกรายชื่อ:</h4>
                                    <p>แตะปุ่มสแกนตรงกลางเมนูล่าง หรือแตะการ์ดรายชื่อในแท็บ <strong>"งานค้าง"</strong></p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>กรอกค่าวัดทางกายภาพ:</h4>
                                    <p>ชั่งน้ำหนัก วัดส่วนสูง วัดรอบเอว วัดความดัน และเจาะน้ำตาลปลายนิ้ว</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">3</span>
                                <div class="step-content">
                                    <h4>แตะเลือกไอคอนคำแนะนำสุขภาพ:</h4>
                                    <p>แตะเลือกคำแนะนำที่ตรงกับพฤติกรรม เช่น <em>ลดเค็ม, ดื่มน้ำเปล่า, เดินเร็ววันละ 30 นาที, นอนก่อน 4 ทุ่ม</em></p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">4</span>
                                <div class="step-content">
                                    <h4>กดบันทึกส่งงาน:</h4>
                                    <p>ระบบจะบันทึกผลงาน พร้อมเก็บพิกัด GPS บ้านเข้าสู่ระบบแผนที่ GIS ของ รพ.สต. ทันที</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 5. การติดตามกลุ่มเสี่ยง DPAC -->
            <div class="accordion-item" data-category="dpac">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">❤️</div>
                        <div>
                            <h3 class="accordion-title">5. การติดตามกลุ่มเสี่ยงโครงการ DPAC</h3>
                            <span class="accordion-tag">ติดตามพฤติกรรม 3อ. 2ส. 1น. ต่อเนื่อง</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>ผู้รับบริการที่ตรวจพบว่ามีความเสี่ยงโรคความดันหรือเบาหวาน จะถูกจัดเข้ากลุ่มติดตามพฤติกรรม DPAC โดย รพ.สต.:</p>

                        <div class="clay-feature-card">
                            <div class="clay-feature-img">
                                <img src="<?= $path_prefix ?>assets/img/clay/exercise.jpg" alt="DPAC">
                            </div>
                            <div class="clay-feature-text">
                                <strong>แท็บงาน DPAC สีแดง</strong>
                                อยู่ในแดชบอร์ดหน้าหลัก อสม. แสดงรายชื่อกลุ่มเสี่ยงที่ต้องลงไปติดตามสุขภาพรอบ 1, 2, 3
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>บันทึกการปรับพฤติกรรม:</h4>
                                    <p>ประเมินเรื่องการลดหวาน ลดเค็ม การออกกำลังกาย และคุณภาพการนอนหลับ</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>วัดผลความเปลี่ยนแปลง:</h4>
                                    <p>ชั่งน้ำหนักและวัดความดันซ้ำเพื่อดูแนวโน้มว่าสุขภาพดีขึ้นหรือไม่</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 6. สิทธิ์ผู้นำ อสม. (รีเซ็ตรหัสผ่านสมาชิกในทีม) -->
            <div class="accordion-item" data-category="leader">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">👑</div>
                        <div>
                            <h3 class="accordion-title">6. สิทธิ์ผู้นำ อสม. (กู้รหัสผ่านสมาชิก)</h3>
                            <span class="accordion-tag">ประธานหมู่บ้าน • ประธานตำบล • ประธานอำเภอ</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>เพื่อความสะดวกรวดเร็วในการช่วยเหลือ อสม. ที่ลืมรหัสผ่าน ประธาน อสม. จะมีกล่องเครื่องมือพิเศษบนแดชบอร์ด:</p>

                        <div class="alert-box alert-box-info">
                            <div>
                                <div class="alert-title">🔑 วิธีรีเซ็ตรหัสผ่านเป็น "1234"</div>
                                <p class="alert-desc">
                                    1. เลือกรายชื่อ อสม. ที่ลืมรหัสผ่านจากกล่องเมนู<br>
                                    2. กดปุ่ม <strong>"รีเซ็ต 1234"</strong><br>
                                    3. สมาชิกจะสามารถใช้รหัสผ่าน <span class="hl-code">1234</span> เข้าสู่ระบบได้ทันที
                                </p>
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">•</span>
                                <div class="step-content">
                                    <h4>ประธาน อสม. หมู่บ้าน:</h4>
                                    <p>สามารถช่วยรีเซ็ตรหัสผ่านให้อาสาสมัครทุกคนในหมู่บ้านของตนเองได้</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">•</span>
                                <div class="step-content">
                                    <h4>ประธาน อสม. ตำบล:</h4>
                                    <p>สามารถช่วยรีเซ็ตรหัสผ่านให้อาสาสมัครทุกหมู่บ้านในตำบลได้</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 7. การทำงานแบบออฟไลน์ (เมื่อไม่มีสัญญาณเน็ต) -->
            <div class="accordion-item" data-category="vhv-screen">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">📡</div>
                        <div>
                            <h3 class="accordion-title">7. การทำงานแบบออฟไลน์ (ไม่มีเน็ต)</h3>
                            <span class="accordion-tag">เซฟงานลงเครื่องอัตโนมัติ ซิงค์ทันทีเมื่อมีเน็ต</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>หมดกังวลเรื่องการลงพื้นที่จุดอับสัญญาณ ระบบมีระบบ <strong>Offline Storage</strong> บันทึกข้อมูลปลอดภัย:</p>

                        <div class="alert-box alert-box-success">
                            <div>
                                <div class="alert-title">⚡ ซิงค์ข้อมูลอัตโนมัติ (Auto-Sync)</div>
                                <p class="alert-desc">เมื่อไม่มีเน็ต อสม. ยังสามารถคัดกรองได้ปกติ ระบบจะบันทึกงานไว้ในมือถือ และเมื่อกลับมาจับสัญญาณเน็ตได้ ข้อมูลจะถูกอัปโหลดขึ้นเซิร์ฟเวอร์ให้อัตโนมัติโดยไม่สูญหาย</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 8. กระดานคะแนนผลงาน (Leaderboard) -->
            <div class="accordion-item" data-category="vhv-screen">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">🏆</div>
                        <div>
                            <h3 class="accordion-title">8. กระดานคะแนนผลงาน (Leaderboard)</h3>
                            <span class="accordion-tag">จัดอันดับผลงานสะสมระดับหมู่บ้าน ตำบล และอำเภอ</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>อสม. สามารถเปิดดูเมนู <strong>"กระดานคะแนน"</strong> จากแถบเมนูด้านล่าง เพื่อดูอันดับการบันทึกคัดกรองสะสม การลงพื้นที่เชิงรุก และรับเหรียญรางวัลเกียรติยศประจำปีงบประมาณครับ</p>
                    </div>
                </div>
            </div>

            <!-- 9. ระบบแจ้งเหตุวิกฤต Fast-Track รพ.สต. และส่งต่อโรงพยาบาล -->
            <div class="accordion-item" data-category="emergency-alert">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">🚨</div>
                        <div>
                            <h3 class="accordion-title">9. ระบบแจ้งเหตุวิกฤต Fast-Track รพ.สต. & ส่งต่อ รพ.</h3>
                            <span class="accordion-tag">ยิงสัญญาณไซเรนด่วน • ติดตามสถานะสด 3 สเต็ป • สั่นเตือนมือถือ</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>เมื่อ อสม. ลงพื้นที่คัดกรองแล้วพบชาวบ้านที่มี <strong>สัญญาณชีพวิกฤต</strong> ระบบจะเปิดระบบส่งสัญญาณด่วนไปยัง รพ.สต. โดยอัตโนมัติ:</p>

                        <div class="alert-box alert-box-danger">
                            <div>
                                <div class="alert-title">🚨 เกณฑ์สัญญาณชีพวิกฤต (Red Flag Alert)</div>
                                <p class="alert-desc">
                                    • ความดันโลหิตสูงวิกฤต: <strong>SYS ≥ 180 mmHg</strong> หรือ <strong>DIA ≥ 110 mmHg</strong><br>
                                    • น้ำตาลในเลือดสูงวิกฤต: <strong>DTX ≥ 300 mg/dL</strong> หรือน้ำตาลต่ำวิกฤต <strong>DTX &lt; 70 mg/dL</strong>
                                </p>
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>กดยิงสัญญาณฉุกเฉินด่วน:</h4>
                                    <p>ในหน้าต่างสรุปผล ให้แตะปุ่มสีแดง <strong>"🆘 ส่งสัญญาณฉุกเฉินแจ้งไปยัง รพ.สต. ทันที"</strong> ระบบจะส่งสัญญาณไซเรนเด้งขึ้นหน้าจอคอมพิวเตอร์โต๊ะพยาบาล รพ.สต. พร้อมเสียงเตือนทันที</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>ติดตามสถานะสด 3 ขั้นตอน (Live Tracking):</h4>
                                    <p>
                                        • <strong>สเต็ป 1 (ส่งสัญญาณ):</strong> แสดงป้ายสีเขียวว่าส่งถึง รพ.สต. สำเร็จ<br>
                                        • <strong>สเต็ป 2 (รพ.สต. รับเรื่อง):</strong> เมื่อเจ้าหน้าที่ รพ.สต. เปิดดูเคส มือถือ อสม. จะ <strong>สั่นเตือน</strong> และขึ้นป้ายสีเขียวพร้อมแสดงชื่อเจ้าหน้าที่ผู้รับเรื่องทันที<br>
                                        • <strong>สเต็ป 3 (พร้อมส่งต่อ):</strong> เมื่อเจ้าหน้าที่สั่งส่งต่อ มือถือ อสม. จะแสดง <strong>เลขที่ใบส่งต่อ (Refer No.)</strong> ปลายทาง <strong>โรงพยาบาลตาลสุม (10957)</strong> อย่างชัดเจน
                                    </p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">3</span>
                                <div class="step-content">
                                    <h4>ปุ่มโทรฉุกเฉินด่วน:</h4>
                                    <p>มีปุ่ม <strong>"📞 โทร 1669 ด่วน"</strong> และปุ่ม <strong>"🏥 โทร รพ.สต."</strong> เพื่อประสานงานทางโทรศัพท์ควบคู่ได้ทันที</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 10. หน้าต่างสรุปผลตรวจแบบ Soft Neumorphism & เสียงพูดคุณหมอ -->
            <div class="accordion-item" data-category="vhv-screen">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">✨</div>
                        <div>
                            <h3 class="accordion-title">10. หน้าสรุปผลตรวจสุขภาพ & เสียงคุณหมอ</h3>
                            <span class="accordion-tag">ดีไซน์ Soft Neumorphism • สรุปผล 4 ด้าน • ลูกศรหนาบอกแนวโน้ม</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>หลังบันทึกคัดกรองเสร็จสิ้น ระบบจะแสดงหน้าต่างสรุปผลสุขภาพสไตล์ <strong>Soft Neumorphism</strong> ที่สวยงามและเข้าใจง่าย:</p>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>ผลตรวจสุขภาพ 4 ด้าน (Raised Cards):</h4>
                                    <p>แสดงการ์ดนูนลอย 4 ใบ ได้แก่ ความดันโลหิต, น้ำตาลในเลือด, รูปร่าง/BMI, และสัดส่วนรอบเอว พร้อมระดับสีชัดเจน</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>ปุ่มเปิดเสียงคุณหมอสรุปผล (Voice Button):</h4>
                                    <p>มีปุ่มสีเขียวมรกตขนาดใหญ่พร้อมไอคอนคนพูดออกเสียงทรงกลม <strong>"เปิดเสียงคุณหมอสรุปผล"</strong> แตะเพื่อให้ระบบอ่านคำพูดสรุปผลตรวจและคำแนะนำการดูแลสุขภาพให้ชาวบ้านฟัง</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">3</span>
                                <div class="step-content">
                                    <h4>ลายน้ำลูกศรหนาบอกแนวโน้มสุขภาพ:</h4>
                                    <p>
                                        • <strong>ลูกศรสีแดงชี้ขึ้น (↗):</strong> เมื่อค่าตรวจรอบนี้สูงขึ้นกว่ารอบก่อน (ต้องเฝ้าระวัง)<br>
                                        • <strong>ลูกศรสีเขียวชี้ลง (↘):</strong> เมื่อสุขภาพดีขึ้นหรือค่าตรวจลดลงสู่เกณฑ์มาตรฐาน
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
</div>

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
    <meta name="apple-mobile-web-app-title" content="NCDs Portal">
    <meta name="application-name" content="NCDs Portal">
    <meta name="theme-color" content="#0d2c54">
    <title>คู่มือการใช้งานระบบ NCDs Portal - อำเภอ<?= htmlspecialchars($district) ?></title>
    <link rel="stylesheet" href="<?= $path_prefix ?>assets/css/style.css">
    <link rel="apple-touch-icon" href="<?= $path_prefix ?>assets/icon.png">
    <link rel="manifest" href="<?= $path_prefix ?>manifest.json">
    <script src="<?= $path_prefix ?>assets/js/app.js"></script>

    <style>
        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: var(--font-base);
            min-height: 100vh;
            padding-bottom: 40px;
        }

        .mobile-wrapper {
            max-width: 540px;
            margin: 0 auto;
            padding: 14px 16px 40px 16px;
        }

        /* Top Bar */
        .manual-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .btn-back-pill {
            color: var(--color-accent);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--bg-card);
            padding: 8px 16px;
            border-radius: 50px;
            box-shadow: var(--neumorph-flat);
            transition: transform 0.2s;
        }
        .btn-back-pill:active {
            transform: scale(0.95);
            box-shadow: var(--neumorph-inset);
        }

        /* Hero Banner */
        .manual-hero {
            text-align: center;
            padding: 22px 18px;
            border-radius: 24px;
            background: var(--bg-card);
            box-shadow: var(--neumorph-flat);
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.6);
        }
        [data-theme="dark"] .manual-hero {
            border-color: rgba(255,255,255,0.05);
        }
        .manual-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #3b82f6, #10b981, #f59e0b);
        }
        .manual-hero-logo {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
            margin-bottom: 10px;
        }
        .manual-hero h1 {
            font-size: 21px;
            font-weight: 900;
            margin: 0 0 4px 0;
            color: var(--text-primary);
        }
        .manual-hero p {
            color: var(--text-secondary);
            font-size: 13px;
            margin: 0;
            line-height: 1.45;
        }

        /* Search Box */
        .search-container {
            margin-bottom: 16px;
            position: relative;
        }
        .search-input {
            width: 100%;
            padding: 12px 16px 12px 42px;
            border-radius: 50px;
            border: none;
            background: var(--bg-card);
            box-shadow: var(--neumorph-inset);
            font-size: 14px;
            color: var(--text-primary);
            box-sizing: border-box;
            outline: none;
            font-family: inherit;
        }
        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: var(--text-muted);
        }

        /* Filter Pills */
        .filter-scroll {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 8px;
            margin-bottom: 16px;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }
        .filter-scroll::-webkit-scrollbar {
            display: none;
        }
        .filter-pill {
            background: var(--bg-card);
            border: none;
            color: var(--text-secondary);
            padding: 7px 14px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s;
        }
        .filter-pill.active {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        }

        /* Accordion Items */
        .manual-accordion {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .accordion-item {
            background-color: var(--bg-card);
            border-radius: 20px;
            box-shadow: var(--neumorph-flat);
            overflow: hidden;
            transition: all 0.25s ease;
            border: 1.5px solid transparent;
        }
        .accordion-item.open {
            border-color: rgba(59, 130, 246, 0.3);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }
        .accordion-header {
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        .accordion-header:active {
            background: var(--bg-darker);
        }
        .accordion-title-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
        }
        .accordion-icon-badge {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: var(--neumorph-flat);
        }
        [data-theme="dark"] .accordion-icon-badge {
            background: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
        }
        .accordion-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0;
            line-height: 1.3;
        }
        .accordion-tag {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
        }
        .accordion-arrow {
            font-size: 13px;
            color: var(--text-muted);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
            margin-left: 8px;
        }
        .accordion-item.open .accordion-arrow {
            transform: rotate(180deg);
            color: #2563eb;
        }

        .accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .accordion-body {
            padding: 0 18px 18px 18px;
            font-size: 13.5px;
            line-height: 1.6;
            color: var(--text-secondary);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            padding-top: 14px;
        }
        [data-theme="dark"] .accordion-body {
            border-top-color: rgba(255, 255, 255, 0.05);
        }

        /* Step List */
        .step-list {
            margin: 12px 0 0 0;
            padding-left: 0;
            list-style: none;
        }
        .step-item {
            position: relative;
            padding-left: 36px;
            margin-bottom: 16px;
        }
        .step-item::before {
            content: '';
            position: absolute;
            left: 13px;
            top: 26px;
            bottom: -12px;
            width: 2px;
            background-color: var(--bg-darker);
        }
        .step-item:last-child::before {
            display: none;
        }
        .step-number {
            position: absolute;
            left: 0;
            top: 0;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background-color: #3b82f6;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 12px;
            box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
        }
        .step-content h4 {
            margin: 0 0 2px 0;
            font-size: 14px;
            font-weight: 800;
            color: var(--text-primary);
        }
        .step-content p {
            margin: 0;
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* Alert Boxes */
        .alert-box {
            padding: 12px 14px;
            border-radius: 14px;
            margin: 12px 0;
            display: flex;
            gap: 10px;
            align-items: flex-start;
            text-align: left;
        }
        .alert-box-info {
            background-color: rgba(59, 130, 246, 0.08);
            border-left: 4px solid #3b82f6;
        }
        .alert-box-success {
            background-color: rgba(16, 185, 129, 0.08);
            border-left: 4px solid #10b981;
        }
        .alert-box-warning {
            background-color: rgba(245, 158, 11, 0.08);
            border-left: 4px solid #f59e0b;
        }
        .alert-box-danger {
            background-color: rgba(239, 68, 68, 0.08);
            border-left: 4px solid #ef4444;
        }
        .alert-title {
            font-weight: 800;
            font-size: 13px;
            margin-bottom: 2px;
            color: var(--text-primary);
        }
        .alert-desc {
            font-size: 12.5px;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.45;
        }

        /* Clay Visual Cards */
        .clay-feature-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.8), rgba(241, 245, 249, 0.9));
            border-radius: 16px;
            padding: 12px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        [data-theme="dark"] .clay-feature-card {
            background: rgba(30, 41, 59, 0.7);
            border-color: rgba(51, 65, 85, 0.8);
        }
        .clay-feature-img {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }
        .clay-feature-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .clay-feature-text {
            font-size: 12.5px;
            line-height: 1.4;
            color: var(--text-secondary);
        }
        .clay-feature-text strong {
            color: var(--text-primary);
            display: block;
            font-size: 13.5px;
            margin-bottom: 2px;
        }

        .hl-code {
            background-color: rgba(59, 130, 246, 0.12);
            color: #2563eb;
            padding: 2px 6px;
            border-radius: 6px;
            font-weight: 800;
            font-family: monospace;
        }
        [data-theme="dark"] .hl-code {
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.15);
        }
    </style>
</head>
<body class="vhv-accessibility">
    <div class="mobile-wrapper">

        <!-- Top Navigation -->
        <div class="manual-top-bar">
            <a href="<?= htmlspecialchars($back_url) ?>" class="btn-back-pill">
                ← ย้อนกลับ
            </a>
            <span style="font-weight: 800; color: var(--color-accent); font-size: 13.5px;">
                NCDs Portal
            </span>
        </div>

        <!-- Hero Section -->
        <div class="manual-hero">
            <img src="<?= $path_prefix ?>assets/icon.png" alt="NCDs Portal Logo" class="manual-hero-logo">
            <h1>📖 คู่มือการใช้งานระบบ NCDs</h1>
            <p>ระบบบันทึกคัดกรอง ดูแลสุขภาพ และประเมินความเสี่ยงโรคเรื้อรัง<br>อำเภอ<?= htmlspecialchars($district) ?> จังหวัด<?= htmlspecialchars($province) ?></p>
        </div>

        <!-- Search Bar -->
        <div class="search-container">
            <span class="search-icon">🔍</span>
            <input type="text" id="manual-search" class="search-input" placeholder="พิมพ์ค้นหาหัวข้อ เช่น คัดกรอง, DPAC, เสียงโค้ช, รหัสผ่าน..." oninput="filterManual()">
        </div>

        <!-- Category Filter Pills -->
        <div class="filter-scroll">
            <button type="button" class="filter-pill active" onclick="setCategory('all', this)">📌 ทั้งหมด</button>
            <button type="button" class="filter-pill" onclick="setCategory('emergency-alert', this)">🚨 แจ้งเหตุวิกฤต</button>
            <button type="button" class="filter-pill" onclick="setCategory('vhv-screen', this)">🩺 คัดกรอง อสม.</button>
            <button type="button" class="filter-pill" onclick="setCategory('citizen-screen', this)">🌱 ประเมินตนเอง</button>
            <button type="button" class="filter-pill" onclick="setCategory('voice-coach', this)">🎙️ โค้ชเสียงพูด</button>
            <button type="button" class="filter-pill" onclick="setCategory('dpac', this)">❤️ งาน DPAC</button>
            <button type="button" class="filter-pill" onclick="setCategory('pwa-install', this)">📲 ติดตั้งแอป</button>
            <button type="button" class="filter-pill" onclick="setCategory('leader', this)">👑 สิทธิ์ประธาน</button>
        </div>

        <!-- Accordion Guide List -->
        <div class="manual-accordion" id="manual-list">

            <!-- 1. การติดตั้งแอปพลิเคชัน NCDs Portal ลงมือถือ -->
            <div class="accordion-item" data-category="pwa-install">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">📲</div>
                        <div>
                            <h3 class="accordion-title">1. การติดตั้งแอป "NCDs Portal" ลงมือถือ</h3>
                            <span class="accordion-tag">ไม่ต้องโหลดผ่าน App Store / Play Store</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>แอปพลิเคชัน NCDs Portal เป็นระบบ Progressive Web App (PWA) ติดตั้งได้ฟรี ทันที ไม่มีไฟล์หนัก และเปิดใช้งานได้เต็มหน้าจอเหมือนแอปพลิเคชันมือถือ:</p>

                        <div class="clay-feature-card">
                            <div class="clay-feature-img">
                                <img src="<?= $path_prefix ?>assets/icon.png" alt="โลโก้แอป">
                            </div>
                            <div class="clay-feature-text">
                                <strong>ติดตั้งง่ายเพียงแตะที่โลโก้</strong>
                                ในหน้าหลัก อสม. ให้แตะที่รูปโลโก้ที่มีไอคอน 📲 ระบบจะเปิดหน้าต่างติดตั้งขึ้นมาทันที
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">A</span>
                                <div class="step-content">
                                    <h4>สำหรับมือถือ Android (Google Chrome):</h4>
                                    <p>แตะที่โลโก้ในหน้าหลัก หรือกดปุ่ม 3 จุดมุมขวาบนของ Chrome แล้วเลือก <strong>"ติดตั้งแอป" (Install App)</strong> หรือ <strong>"เพิ่มลงในหน้าจอหลัก"</strong></p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">B</span>
                                <div class="step-content">
                                    <h4>สำหรับ iPhone / iPad (Safari):</h4>
                                    <p>กดปุ่ม <strong>แชร์ (Share ⎋)</strong> ด้านล่างจอ แล้วเลื่อนลงมาเลือก <strong>"เพิ่มไปยังหน้าจอโฮม" (Add to Home Screen ⊞)</strong></p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 2. การประเมินสุขภาพตนเองสำหรับประชาชน (Claymorphism Self-Screening) -->
            <div class="accordion-item" data-category="citizen-screen">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">🌱</div>
                        <div>
                            <h3 class="accordion-title">2. แบบประเมินสุขภาพตนเอง (สำหรับประชาชน)</h3>
                            <span class="accordion-tag">เข้าทำได้ฟรี ไม่ต้องล็อกอิน • 10 ข้อจบใน 30 วิ</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>ประชาชนทั่วไปในอำเภอ<?= htmlspecialchars($district) ?> สามารถเข้าตรวจเช็คพฤติกรรมเสี่ยงความดันโลหิตสูงและเบาหวานได้ด้วยตนเองผ่านเมนูหน้าแรก:</p>

                        <div class="clay-feature-card">
                            <div class="clay-feature-img">
                                <img src="<?= $path_prefix ?>assets/img/clay/sprout.jpg" alt="ประเมินตนเอง">
                            </div>
                            <div class="clay-feature-text">
                                <strong>ดีไซน์ 3D ดินน้ำมัน (Claymorphism)</strong>
                                สวยงาม ตัวหนังสือใหญ่เด่นชัด 1 คำถามต่อ 1 สไลด์ พอดีหน้าจอมือถือโดยไม่ต้องเลื่อนจอ
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>แตะปุ๊บ ไปต่อปั๊บ (Auto-Advance):</h4>
                                    <p>เมื่อแตะเลือกคำตอบ ระบบจะบันทึกและสไลด์ไปคำถามถัดไปให้อัตโนมัติทันที</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>ปุ่มควบคุมลอยชิดขอบล่าง (‹ และ ›):</h4>
                                    <p>สามารถกดย้อนกลับหรือข้ามคำถามได้สะดวกด้วยนิ้วโป้ง โดยปุ่มอยู่ตำแหน่งที่ปลอดภัย ไม่บังคำตอบ</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">3</span>
                                <div class="step-content">
                                    <h4>สรุปผลและแนวทางปฏิบัติเฉพาะบุคคล:</h4>
                                    <p>บอกชัดเจนว่า <strong>"ถ้าอยากลดความดันต้องทำอย่างไร?"</strong> และ <strong>"ถ้าอยากลดค่าน้ำตาลต้องทำอย่างไร?"</strong> พร้อมปุ่มส่งต่อให้ อสม. หรือ รพ.สต. ตรวจวัดค่าจริง</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 3. ระบบเสียงโค้ชสุขภาพอัจฉริยะ (Clinical Voice Guidance Coach) -->
            <div class="accordion-item" data-category="voice-coach">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">🎙️</div>
                        <div>
                            <h3 class="accordion-title">3. ระบบเสียงโค้ชสุขภาพ (Voice Coach)</h3>
                            <span class="accordion-tag">สรุปผลวิเคราะห์สุขภาพด้วยเสียงภาษาไทยเป็นธรรมชาติ</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>ระบบช่วยอ่านสรุปผลการคัดกรองและให้คำแนะนำสุขภาพแก่ผู้รับบริการ ด้วยเสียงภาษาไทยที่เป็นมิตรและเข้าใจง่าย:</p>

                        <div class="alert-box alert-box-success">
                            <div>
                                <div class="alert-title">🔊 สำเนียงธรรมชาติ & ออกเสียงคำย่อถูกต้อง</div>
                                <p class="alert-desc">ระบบออกเสียง <strong>"ออ-สอ-มอ"</strong> สำหรับคำว่า อสม. และ <strong>"รอ-พอ-สอ-ตอ"</strong> สำหรับ รพ.สต. ด้วยจังหวะที่นุ่มนวล ไม่เร็วเกินไป</p>
                            </div>
                        </div>

                        <p><strong>วิธีเปิดฟังเสียง:</strong></p>
                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>ในฟอร์มคัดกรอง อสม. & ติดตาม DPAC:</h4>
                                    <p>เมื่อกรอกค่าความดันหรือน้ำตาล จะมีแถบวิเคราะห์ผลสุขภาพสีเขียว/เหลือง/แดง พร้อมปุ่ม <strong>"🔊 เปิดเสียงคำแนะนำ"</strong> แตะเพื่อให้ระบบพูดให้ผู้รับบริการฟังได้ทันที</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>ในหน้าประเมินสุขภาพตนเอง:</h4>
                                    <p>หน้าสรุปผลจะมีปุ่ม <strong>"🔊 เปิดเสียงพูด"</strong> สำหรับฟังคำแนะนำสรุปพฤติกรรมการกิน การนอน และการออกกำลังกาย</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 4. ขั้นตอนการลงพื้นที่คัดกรองของ อสม. & กฎความถูกต้องของข้อมูล -->
            <div class="accordion-item" data-category="vhv-screen">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">🩺</div>
                        <div>
                            <h3 class="accordion-title">4. การคัดกรอง อสม. & กฎความถูกต้อง</h3>
                            <span class="accordion-tag">บังคับเลือกคำแนะนำอย่างน้อย 1 ข้อ และกรอกข้อมูลครบถ้วน</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>เพื่อรักษามาตรฐานข้อมูลสุขภาพระดับอำเภอ ระบบคัดกรองมีข้อกำหนดความสมบูรณ์ของฟอร์มดังนี้:</p>

                        <div class="alert-box alert-box-warning">
                            <div>
                                <div class="alert-title">⚠️ กฎการตรวจสอบก่อนกดส่งงาน (Strict Validation)</div>
                                <p class="alert-desc">
                                    1. ต้องกรอก <strong>น้ำหนัก และ ส่วนสูง</strong> (เพื่อคำนวณ BMI)<br>
                                    2. ต้องกรอกค่า <strong>ความดันโลหิต (SYS/DIA) หรือ ค่าน้ำตาล (DTX)</strong><br>
                                    3. <strong>ต้องแตะเลือกคำแนะนำสุขภาพอย่างน้อย 1 รายการ</strong> (แตะที่รูปไอคอนคำแนะนำ 9 แบบ) ระบบจะไม่ยอมให้กดส่งงานหากไม่มีการให้คำแนะนำสุขภาพ
                                </p>
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>สแกน QR Code หรือเลือกรายชื่อ:</h4>
                                    <p>แตะปุ่มสแกนตรงกลางเมนูล่าง หรือแตะการ์ดรายชื่อในแท็บ <strong>"งานค้าง"</strong></p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>กรอกค่าวัดทางกายภาพ:</h4>
                                    <p>ชั่งน้ำหนัก วัดส่วนสูง วัดรอบเอว วัดความดัน และเจาะน้ำตาลปลายนิ้ว</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">3</span>
                                <div class="step-content">
                                    <h4>แตะเลือกไอคอนคำแนะนำสุขภาพ:</h4>
                                    <p>แตะเลือกคำแนะนำที่ตรงกับพฤติกรรม เช่น <em>ลดเค็ม, ดื่มน้ำเปล่า, เดินเร็ววันละ 30 นาที, นอนก่อน 4 ทุ่ม</em></p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">4</span>
                                <div class="step-content">
                                    <h4>กดบันทึกส่งงาน:</h4>
                                    <p>ระบบจะบันทึกผลงาน พร้อมเก็บพิกัด GPS บ้านเข้าสู่ระบบแผนที่ GIS ของ รพ.สต. ทันที</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 5. การติดตามกลุ่มเสี่ยง DPAC -->
            <div class="accordion-item" data-category="dpac">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">❤️</div>
                        <div>
                            <h3 class="accordion-title">5. การติดตามกลุ่มเสี่ยงโครงการ DPAC</h3>
                            <span class="accordion-tag">ติดตามพฤติกรรม 3อ. 2ส. 1น. ต่อเนื่อง</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>ผู้รับบริการที่ตรวจพบว่ามีความเสี่ยงโรคความดันหรือเบาหวาน จะถูกจัดเข้ากลุ่มติดตามพฤติกรรม DPAC โดย รพ.สต.:</p>

                        <div class="clay-feature-card">
                            <div class="clay-feature-img">
                                <img src="<?= $path_prefix ?>assets/img/clay/exercise.jpg" alt="DPAC">
                            </div>
                            <div class="clay-feature-text">
                                <strong>แท็บงาน DPAC สีแดง</strong>
                                อยู่ในแดชบอร์ดหน้าหลัก อสม. แสดงรายชื่อกลุ่มเสี่ยงที่ต้องลงไปติดตามสุขภาพรอบ 1, 2, 3
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>บันทึกการปรับพฤติกรรม:</h4>
                                    <p>ประเมินเรื่องการลดหวาน ลดเค็ม การออกกำลังกาย และคุณภาพการนอนหลับ</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>วัดผลความเปลี่ยนแปลง:</h4>
                                    <p>ชั่งน้ำหนักและวัดความดันซ้ำเพื่อดูแนวโน้มว่าสุขภาพดีขึ้นหรือไม่</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 6. สิทธิ์ผู้นำ อสม. (รีเซ็ตรหัสผ่านสมาชิกในทีม) -->
            <div class="accordion-item" data-category="leader">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">👑</div>
                        <div>
                            <h3 class="accordion-title">6. สิทธิ์ผู้นำ อสม. (กู้รหัสผ่านสมาชิก)</h3>
                            <span class="accordion-tag">ประธานหมู่บ้าน • ประธานตำบล • ประธานอำเภอ</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>เพื่อความสะดวกรวดเร็วในการช่วยเหลือ อสม. ที่ลืมรหัสผ่าน ประธาน อสม. จะมีกล่องเครื่องมือพิเศษบนแดชบอร์ด:</p>

                        <div class="alert-box alert-box-info">
                            <div>
                                <div class="alert-title">🔑 วิธีรีเซ็ตรหัสผ่านเป็น "1234"</div>
                                <p class="alert-desc">
                                    1. เลือกรายชื่อ อสม. ที่ลืมรหัสผ่านจากกล่องเมนู<br>
                                    2. กดปุ่ม <strong>"รีเซ็ต 1234"</strong><br>
                                    3. สมาชิกจะสามารถใช้รหัสผ่าน <span class="hl-code">1234</span> เข้าสู่ระบบได้ทันที
                                </p>
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">•</span>
                                <div class="step-content">
                                    <h4>ประธาน อสม. หมู่บ้าน:</h4>
                                    <p>สามารถช่วยรีเซ็ตรหัสผ่านให้อาสาสมัครทุกคนในหมู่บ้านของตนเองได้</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">•</span>
                                <div class="step-content">
                                    <h4>ประธาน อสม. ตำบล:</h4>
                                    <p>สามารถช่วยรีเซ็ตรหัสผ่านให้อาสาสมัครทุกหมู่บ้านในตำบลได้</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 7. การทำงานแบบออฟไลน์ (เมื่อไม่มีสัญญาณเน็ต) -->
            <div class="accordion-item" data-category="vhv-screen">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">📡</div>
                        <div>
                            <h3 class="accordion-title">7. การทำงานแบบออฟไลน์ (ไม่มีเน็ต)</h3>
                            <span class="accordion-tag">เซฟงานลงเครื่องอัตโนมัติ ซิงค์ทันทีเมื่อมีเน็ต</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>หมดกังวลเรื่องการลงพื้นที่จุดอับสัญญาณ ระบบมีระบบ <strong>Offline Storage</strong> บันทึกข้อมูลปลอดภัย:</p>

                        <div class="alert-box alert-box-success">
                            <div>
                                <div class="alert-title">⚡ ซิงค์ข้อมูลอัตโนมัติ (Auto-Sync)</div>
                                <p class="alert-desc">เมื่อไม่มีเน็ต อสม. ยังสามารถคัดกรองได้ปกติ ระบบจะบันทึกงานไว้ในมือถือ และเมื่อกลับมาจับสัญญาณเน็ตได้ ข้อมูลจะถูกอัปโหลดขึ้นเซิร์ฟเวอร์ให้อัตโนมัติโดยไม่สูญหาย</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 8. กระดานคะแนนผลงาน (Leaderboard) -->
            <div class="accordion-item" data-category="vhv-screen">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">🏆</div>
                        <div>
                            <h3 class="accordion-title">8. กระดานคะแนนผลงาน (Leaderboard)</h3>
                            <span class="accordion-tag">จัดอันดับผลงานสะสมระดับหมู่บ้าน ตำบล และอำเภอ</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>อสม. สามารถเปิดดูเมนู <strong>"กระดานคะแนน"</strong> จากแถบเมนูด้านล่าง เพื่อดูอันดับการบันทึกคัดกรองสะสม การลงพื้นที่เชิงรุก และรับเหรียญรางวัลเกียรติยศประจำปีงบประมาณครับ</p>
                    </div>
                </div>
            </div>

            <!-- 9. ระบบแจ้งเหตุวิกฤต Fast-Track รพ.สต. และส่งต่อโรงพยาบาล -->
            <div class="accordion-item" data-category="emergency-alert">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">🚨</div>
                        <div>
                            <h3 class="accordion-title">9. ระบบแจ้งเหตุวิกฤต Fast-Track รพ.สต. & ส่งต่อ รพ.</h3>
                            <span class="accordion-tag">ยิงสัญญาณไซเรนด่วน • ติดตามสถานะสด 3 สเต็ป • สั่นเตือนมือถือ</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>เมื่อ อสม. ลงพื้นที่คัดกรองแล้วพบชาวบ้านที่มี <strong>สัญญาณชีพวิกฤต</strong> ระบบจะเปิดระบบส่งสัญญาณด่วนไปยัง รพ.สต. โดยอัตโนมัติ:</p>

                        <div class="alert-box alert-box-danger">
                            <div>
                                <div class="alert-title">🚨 เกณฑ์สัญญาณชีพวิกฤต (Red Flag Alert)</div>
                                <p class="alert-desc">
                                    • ความดันโลหิตสูงวิกฤต: <strong>SYS ≥ 180 mmHg</strong> หรือ <strong>DIA ≥ 110 mmHg</strong><br>
                                    • น้ำตาลในเลือดสูงวิกฤต: <strong>DTX ≥ 300 mg/dL</strong> หรือน้ำตาลต่ำวิกฤต <strong>DTX &lt; 70 mg/dL</strong>
                                </p>
                            </div>
                        </div>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>กดยิงสัญญาณฉุกเฉินด่วน:</h4>
                                    <p>ในหน้าต่างสรุปผล ให้แตะปุ่มสีแดง <strong>"🆘 ส่งสัญญาณฉุกเฉินแจ้งไปยัง รพ.สต. ทันที"</strong> ระบบจะส่งสัญญาณไซเรนเด้งขึ้นหน้าจอคอมพิวเตอร์โต๊ะพยาบาล รพ.สต. พร้อมเสียงเตือนทันที</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>ติดตามสถานะสด 3 ขั้นตอน (Live Tracking):</h4>
                                    <p>
                                        • <strong>สเต็ป 1 (ส่งสัญญาณ):</strong> แสดงป้ายสีเขียวว่าส่งถึง รพ.สต. สำเร็จ<br>
                                        • <strong>สเต็ป 2 (รพ.สต. รับเรื่อง):</strong> เมื่อเจ้าหน้าที่ รพ.สต. เปิดดูเคส มือถือ อสม. จะ <strong>สั่นเตือน</strong> และขึ้นป้ายสีเขียวพร้อมแสดงชื่อเจ้าหน้าที่ผู้รับเรื่องทันที<br>
                                        • <strong>สเต็ป 3 (พร้อมส่งต่อ):</strong> เมื่อเจ้าหน้าที่สั่งส่งต่อ มือถือ อสม. จะแสดง <strong>เลขที่ใบส่งต่อ (Refer No.)</strong> ปลายทาง <strong>โรงพยาบาลตาลสุม (10957)</strong> อย่างชัดเจน
                                    </p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">3</span>
                                <div class="step-content">
                                    <h4>ปุ่มโทรฉุกเฉินด่วน:</h4>
                                    <p>มีปุ่ม <strong>"📞 โทร 1669 ด่วน"</strong> และปุ่ม <strong>"🏥 โทร รพ.สต."</strong> เพื่อประสานงานทางโทรศัพท์ควบคู่ได้ทันที</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 10. หน้าต่างสรุปผลตรวจแบบ Soft Neumorphism & เสียงพูดคุณหมอ -->
            <div class="accordion-item" data-category="vhv-screen">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <div class="accordion-title-wrap">
                        <div class="accordion-icon-badge">✨</div>
                        <div>
                            <h3 class="accordion-title">10. หน้าสรุปผลตรวจสุขภาพ & เสียงคุณหมอ</h3>
                            <span class="accordion-tag">ดีไซน์ Soft Neumorphism • สรุปผล 4 ด้าน • ลูกศรหนาบอกแนวโน้ม</span>
                        </div>
                    </div>
                    <span class="accordion-arrow">▼</span>
                </div>
                <div class="accordion-content">
                    <div class="accordion-body">
                        <p>หลังบันทึกคัดกรองเสร็จสิ้น ระบบจะแสดงหน้าต่างสรุปผลสุขภาพสไตล์ <strong>Soft Neumorphism</strong> ที่สวยงามและเข้าใจง่าย:</p>

                        <ul class="step-list">
                            <li class="step-item">
                                <span class="step-number">1</span>
                                <div class="step-content">
                                    <h4>ผลตรวจสุขภาพ 4 ด้าน (Raised Cards):</h4>
                                    <p>แสดงการ์ดนูนลอย 4 ใบ ได้แก่ ความดันโลหิต, น้ำตาลในเลือด, รูปร่าง/BMI, และสัดส่วนรอบเอว พร้อมระดับสีชัดเจน</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">2</span>
                                <div class="step-content">
                                    <h4>ปุ่มเปิดเสียงคุณหมอสรุปผล (Voice Button):</h4>
                                    <p>มีปุ่มสีเขียวมรกตขนาดใหญ่พร้อมไอคอนคนพูดออกเสียงทรงกลม <strong>"เปิดเสียงคุณหมอสรุปผล"</strong> แตะเพื่อให้ระบบอ่านคำพูดสรุปผลตรวจและคำแนะนำการดูแลสุขภาพให้ชาวบ้านฟัง</p>
                                </div>
                            </li>
                            <li class="step-item">
                                <span class="step-number">3</span>
                                <div class="step-content">
                                    <h4>ลายน้ำลูกศรหนาบอกแนวโน้มสุขภาพ:</h4>
                                    <p>
                                        • <strong>ลูกศรสีแดงชี้ขึ้น (↗):</strong> เมื่อค่าตรวจรอบนี้สูงขึ้นกว่ารอบก่อน (ต้องเฝ้าระวัง)<br>
                                        • <strong>ลูกศรสีเขียวชี้ลง (↘):</strong> เมื่อสุขภาพดีขึ้นหรือค่าตรวจลดลงสู่เกณฑ์มาตรฐาน
                                    </p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- Interactive Script -->
    <script>
        function toggleAccordion(header) {
            const item = header.parentElement;
            const content = item.querySelector('.accordion-content');
            
            const isOpen = item.classList.contains('open');

            // Close all
            document.querySelectorAll('.accordion-item').forEach(i => {
                i.classList.remove('open');
                const c = i.querySelector('.accordion-content');
                if (c) c.style.maxHeight = null;
            });

            // Toggle selected
            if (!isOpen) {
                item.classList.add('open');
                content.style.maxHeight = content.scrollHeight + "px";
            }
        }

        function setCategory(cat, btn) {
            document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');

            const items = document.querySelectorAll('.accordion-item');
            items.forEach(item => {
                if (cat === 'all' || item.getAttribute('data-category') === cat) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function filterManual() {
            const q = document.getElementById('manual-search').value.toLowerCase().trim();
            const items = document.querySelectorAll('.accordion-item');
            
            items.forEach(item => {
                const text = item.innerText.toLowerCase();
                if (text.includes(q)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }
</script>
