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
    <meta name="apple-mobile-web-app-title" content="NCDs Portal">
    <meta name="application-name" content="NCDs Portal">
    <meta name="theme-color" content="#0d2c54">
    <!-- Open Graph / LINE / Facebook Rich Link Previews -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="ตรวจเช็คสุขภาพตนเอง..ฟรี - อำเภอ<?= DISTRICT_NAME ?>">
    <meta property="og:description" content="ประเมินความเสี่ยงโรคความดันและเบาหวานเบื้องต้น 1 นาที พร้อมรับการ์ดเกียรติยศสุขภาพดิจิทัล">
    <meta property="og:image" content="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ncd.ssotansum.com') ?>/assets/img/clay/heart_red.png">
    <title>ตรวจเช็คสุขภาพเบื้องต้น - อำเภอ<?= DISTRICT_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="apple-touch-icon" href="assets/icon.png">
    <link rel="manifest" href="manifest.json">
    <script src="assets/js/clinical_guidance.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            position: relative;
            overflow-x: hidden;
            background: var(--bg-main);
        }

        .screen-container {
            max-width: 500px;
            margin: 0 auto;
            width: 100%;
            padding: 10px 16px 95px 16px;
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
            from { opacity: 0; transform: translateX(20px) scale(0.98); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }

        /* Top Progress Bar & Motivational Badge */
        .top-nav-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .progress-pill {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(16, 185, 129, 0.12));
            color: #2563eb;
            padding: 5px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.3px;
            border: 1px solid rgba(59, 130, 246, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        [data-theme="dark"] .progress-pill {
            background: rgba(56, 189, 248, 0.18);
            color: #38bdf8;
            border-color: rgba(56, 189, 248, 0.3);
        }

        .progress-container {
            width: 100%;
            height: 6px;
            background: var(--bg-darker);
            border-radius: 9999px;
            margin-bottom: 14px;
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
            margin-bottom: 14px;
        }
        .q-title {
            font-size: 20px;
            font-weight: 900;
            color: var(--text-primary);
            margin: 0 0 3px 0;
            line-height: 1.3;
        }
        .q-subtitle {
            font-size: 13px;
            color: var(--text-secondary);
            margin: 0;
        }

        /* Large Claymorphism Option Cards */
        .clay-options-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 14px;
        }

        .clay-opt-card {
            position: relative;
            background: var(--bg-card);
            border-radius: 20px;
            padding: 12px 16px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05), inset 1.5px 1.5px 3px rgba(255, 255, 255, 0.8), inset -1.5px -1.5px 3px rgba(0, 0, 0, 0.03);
            cursor: pointer;
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 2.5px solid transparent;
            display: flex;
            align-items: center;
            gap: 14px;
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
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.2), inset 2px 2px 4px rgba(59, 130, 246, 0.1);
            transform: scale(1.015);
        }
        [data-theme="dark"] .clay-opt-card.selected {
            background: rgba(56, 189, 248, 0.15);
            border-color: #38bdf8;
        }

        /* Prominent Clay Icon Container */
        .clay-icon-large {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08), inset 1px 1px 2px rgba(255, 255, 255, 0.6);
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
            margin: 0 0 2px 0;
            font-size: 17px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.25;
        }
        .opt-content p {
            margin: 0;
            font-size: 12.5px;
            color: var(--text-secondary);
            line-height: 1.35;
        }

        .opt-check-badge {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid var(--border-color, #cbd5e1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
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

        /* Bottom Clay Tip Banner */
        .clay-tip-banner {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.7), rgba(241, 245, 249, 0.8));
            border: 1.5px solid rgba(226, 232, 240, 0.8);
            border-radius: 18px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }
        [data-theme="dark"] .clay-tip-banner {
            background: rgba(30, 41, 59, 0.6);
            border-color: rgba(51, 65, 85, 0.8);
        }
        .clay-tip-thumb {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 3px 8px rgba(0,0,0,0.08);
            background: #e2e8f0;
        }
        .clay-tip-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .clay-tip-text {
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.35;
            text-align: left;
        }
        .clay-tip-text strong {
            color: var(--text-primary);
        }

        /* Floating Overlay Navigation Buttons (ชิดด้านล่าง แต่ไม่ล่างสุด ไม่บังคำตอบ) */
        .side-overlay-btn {
            position: fixed;
            bottom: 24px;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--bg-card);
            border: 2.5px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18), inset 2px 2px 4px rgba(255, 255, 255, 0.9);
            color: var(--text-primary);
            font-size: 24px;
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
            transform: scale(1.12);
            color: #3b82f6;
        }
        .side-overlay-btn:active {
            transform: scale(0.92);
        }
        .side-btn-prev {
            left: 20px;
        }
        .side-btn-next {
            right: 20px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.4);
        }
        .side-btn-next:hover {
            color: white;
        }

        .side-overlay-btn.disabled {
            opacity: 0;
            pointer-events: none;
            transform: scale(0.6);
        }

        /* Results Box */
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
            font-size: 13.5px;
            line-height: 1.6;
            color: var(--text-primary);
        }
        .solution-list li {
            margin-bottom: 5px;
        }

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

        /* Intro Step & Privacy Box */
        .intro-hero-card {
            background: var(--bg-card);
            border-radius: 24px;
            padding: 24px 20px;
            box-shadow: var(--neumorph-flat);
            text-align: center;
            border: 1.5px solid rgba(59, 130, 246, 0.18);
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
        }
        .intro-hero-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #3b82f6, #10b981, #f59e0b);
        }
        .privacy-guarantee-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1.5px solid rgba(16, 185, 129, 0.3);
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 14px;
        }
        [data-theme="dark"] .privacy-guarantee-badge {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border-color: rgba(52, 211, 153, 0.4);
        }
        .privacy-statement-box {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.06), rgba(16, 185, 129, 0.06));
            border: 1.5px dashed rgba(59, 130, 246, 0.35);
            border-radius: 18px;
            padding: 16px;
            margin: 16px 0;
            text-align: left;
        }
        .privacy-feature-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 13px;
            color: var(--text-primary);
            line-height: 1.45;
        }
        .privacy-feature-item:last-child {
            margin-bottom: 0;
        }
        /* ====================================================
           Health Trophy & Viral Share Card Styles (3D Soft UI)
           ==================================================== */
        .health-trophy-card {
            background: #ffffff;
            border-radius: 26px;
            padding: 22px 18px 18px 18px;
            margin-bottom: 18px;
            border: 2px solid rgba(59, 130, 246, 0.25);
            box-shadow: 0 16px 36px rgba(13, 44, 84, 0.12), 0 4px 12px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            text-align: center;
            box-sizing: border-box;
        }
        [data-theme="dark"] .health-trophy-card {
            background: #1e293b;
            border-color: rgba(56, 189, 248, 0.3);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.4);
        }
        .trophy-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px dashed rgba(148, 163, 184, 0.35);
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .trophy-org-title {
            font-size: 12px;
            font-weight: 800;
            color: var(--color-primary);
            display: flex;
            align-items: center;
            gap: 6px;
            text-align: left;
        }
        .trophy-verified-badge {
            font-size: 10.5px;
            font-weight: 800;
            color: #059669;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 3px 8px;
            border-radius: 9999px;
            white-space: nowrap;
        }
        .trophy-score-showcase {
            margin: 8px 0 14px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .trophy-disc-wrapper {
            margin-bottom: 8px;
            display: inline-flex;
            justify-content: center;
        }
        .trophy-score-value {
            font-size: 40px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -1px;
            color: #10b981;
            margin-bottom: 3px;
        }
        .trophy-score-label {
            font-size: 12.5px;
            color: var(--text-secondary);
            font-weight: 700;
            margin-bottom: 10px;
        }
        .trophy-rank-banner {
            display: inline-block;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 6px 16px;
            border-radius: 9999px;
            font-size: 13.5px;
            font-weight: 800;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
            margin-bottom: 14px;
            max-width: 92%;
        }
        .trophy-pillars-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 16px;
            text-align: left;
        }
        .trophy-pillar-box {
            background: rgba(59, 130, 246, 0.05);
            border: 1px solid rgba(59, 130, 246, 0.15);
            border-radius: 12px;
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        [data-theme="dark"] .trophy-pillar-box {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.1);
        }
        .trophy-pillar-info h5 {
            margin: 0;
            font-size: 11px;
            font-weight: 800;
            color: var(--text-primary);
        }
        .trophy-pillar-info span {
            font-size: 10.5px;
            color: var(--text-secondary);
            font-weight: 600;
        }
        .trophy-viral-footer {
            background: linear-gradient(135deg, rgba(13, 44, 84, 0.06), rgba(59, 130, 246, 0.08));
            border-radius: 16px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        [data-theme="dark"] .trophy-viral-footer {
            background: rgba(15, 23, 42, 0.6);
            border-color: rgba(56, 189, 248, 0.2);
        }
        .trophy-qr-frame {
            width: 62px;
            height: 62px;
            background: #ffffff;
            border-radius: 10px;
            padding: 3px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .trophy-qr-frame canvas, .trophy-qr-frame img {
            width: 100% !important;
            height: 100% !important;
            border-radius: 4px;
        }
        .trophy-invite-text h4 {
            margin: 0 0 2px 0;
            font-size: 12.5px;
            font-weight: 800;
            color: var(--color-primary);
        }
        .trophy-invite-text p {
            margin: 0;
            font-size: 11px;
            color: var(--text-secondary);
            line-height: 1.35;
        }
        .trophy-date-stamp {
            font-size: 9.5px;
            color: var(--text-muted);
            margin-top: 3px;
        }

        /* Viral Share Buttons */
        .viral-actions-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }
        .btn-viral-line {
            background: linear-gradient(135deg, #06C755, #00B900);
            color: #ffffff !important;
            border: none;
            border-radius: 16px;
            padding: 13px 18px;
            font-size: 15px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(6, 199, 85, 0.35);
            transition: all 0.2s ease;
            text-decoration: none;
            width: 100%;
            box-sizing: border-box;
        }
        .btn-viral-line:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(6, 199, 85, 0.45);
        }
        .btn-viral-save {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff !important;
            border: none;
            border-radius: 16px;
            padding: 13px 18px;
            font-size: 15px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
            transition: all 0.2s ease;
            width: 100%;
            box-sizing: border-box;
        }
        .btn-viral-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.45);
        }
        .btn-viral-copy {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1.5px solid var(--border-color);
            border-radius: 14px;
            padding: 10px 14px;
            font-size: 13.5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all 0.2s;
            width: 100%;
            box-sizing: border-box;
        }
        .btn-viral-copy:hover {
            background: var(--bg-darker);
        }

        /* Image Save Preview Modal */
        .image-modal-backdrop {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(6px);
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            box-sizing: border-box;
        }
        .image-modal-content {
            background: var(--bg-card);
            border-radius: 24px;
            max-width: 440px;
            width: 100%;
            padding: 20px;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.4);
            text-align: center;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border-color);
        }
        .image-preview-target {
            width: 100%;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            margin: 12px 0 16px 0;
            border: 1px solid var(--border-color);
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
                <span id="step-badge" class="progress-pill">🌱 เริ่มต้นง่ายๆ</span>
                <span style="font-size: 12.5px; color: var(--color-accent); font-weight: 800;">
                    NCDs Portal:ตาลสุม
                </span>
            </div>
            <div class="progress-container">
                <div id="self-progress" class="progress-fill"></div>
            </div>
        </div>

        <form id="self-screening-form" onsubmit="return false;">
            
            <!-- STEP 0: ข้อความต้อนรับ & แจ้งเตือนความเป็นส่วนตัว -->
            <div id="q-intro" class="question-slide active">
                <div class="intro-hero-card">
                    <div class="privacy-guarantee-badge">
                        <?= render_neu_icon('shield-check', 'xs', 'text-green') ?> ปลอดภัย ไม่เก็บข้อมูลส่วนบุคคล
                    </div>

                    <div style="margin: 6px auto 14px auto; display: flex; justify-content: center;">
                        <?= render_neu_icon('shield-check', 'xl', 'text-green') ?>
                    </div>

                    <h1 style="font-size: 22px; font-weight: 900; color: var(--text-primary); margin: 0 0 6px 0; line-height: 1.3;">
                        ตรวจเช็คสุขภาพตนเอง
                    </h1>
                    <p style="font-size: 14px; color: var(--text-secondary); margin: 0; font-weight: 600;">
                        ประเมินความเสี่ยงโรคความดันและเบาหวานเบื้องต้น
                    </p>

                    <!-- ข้อความชี้แจงความปรารถนาดีและไม่เก็บข้อมูลส่วนตัว -->
                    <div class="privacy-statement-box">
                        <div style="font-size: 14px; font-weight: 800; color: #2563eb; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <?= render_neu_icon('heart-pulse', 'xs', 'disc-red') ?>
                            <span>เราปรารถนาอยากให้ทุกคนมีสุขภาพแข็งแรง</span>
                        </div>
                        <div class="privacy-feature-item" style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px;">
                            <?= render_neu_icon('shield-check', 'sm', 'text-green') ?>
                            <div style="flex: 1; text-align: left;">
                                <strong>ไม่มีการขอข้อมูลส่วนตัวใดๆ:</strong> ไม่ต้องกรอกชื่อ-นามสกุล, ไม่ต้องระบุเลขบัตรประชาชน, ไม่ต้องกรอกเบอร์โทรหรือที่อยู่
                            </div>
                        </div>
                        <div class="privacy-feature-item" style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px;">
                            <?= render_neu_icon('nutrition', 'sm', 'text-blue') ?>
                            <div style="flex: 1; text-align: left;">
                                <strong>ดูแลตัวเองได้ทันท่วงที:</strong> ทราบผลวิเคราะห์พฤติกรรม 3อ. 2ส. 1น. พร้อมแนวทางลดเค็ม-ลดน้ำตาลเฉพาะบุคคลทันที
                            </div>
                        </div>
                        <div class="privacy-feature-item" style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px;">
                            <?= render_neu_icon('mobile-health', 'sm', 'text-purple') ?>
                            <div style="flex: 1; text-align: left;">
                                <strong>มีระบบเสียงพูด (Voice Coach):</strong> ฟังสรุปคำแนะนำสุขภาพด้วยเสียงเข้าใจง่าย เหมาะกับทุกเพศทุกวัย
                            </div>
                        </div>
                        <div class="privacy-feature-item" style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px;">
                            <?= render_neu_icon('heart-pulse', 'sm', 'text-yellow') ?>
                            <div style="flex: 1; text-align: left;">
                                <strong>ใช้เวลาเพียง 1 นาที:</strong> ตอบคำถามสบายๆ 10 ข้อ รู้ผลลัพธ์ทันทีฟรี ไม่มีค่าใช้จ่ายใดๆ
                            </div>
                        </div>
                    </div>

                    <button type="button" onclick="startScreening()" class="btn-giant btn-giant-primary" style="margin: 6px 0 0 0; padding: 15px; font-size: 16px; width: 100%; border-radius: 18px; box-shadow: 0 8px 24px rgba(37,99,235,0.35); background: linear-gradient(135deg, #2563eb, #10b981); display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <span>เริ่มตรวจเช็คสุขภาพตนเอง (ฟรี ไม่มีค่าใช้จ่าย)</span>
                    </button>
                    
                    <div style="margin-top: 14px; font-size: 12.5px; color: var(--text-secondary); display: flex; align-items: center; justify-content: center; gap: 6px;">
                        <?= render_neu_icon('medical-kit', 'xs', 'text-navy') ?>
                        <span>เพื่อพี่น้องประชาชนชาวอำเภอ<?= DISTRICT_NAME ?></span>
                    </div>
                </div>
            </div>

            <!-- QUESTION 1: เพศ -->
            <div id="q-1" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">เพศของท่าน</h2>
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
                <!-- Bottom Decorative Clay Tip -->
                <div class="clay-tip-banner">
                    <div class="clay-tip-thumb">
                        <img src="assets/img/clay/heart_red.png" alt="เกร็ดสุขภาพ">
                    </div>
                    <div class="clay-tip-text">
                        <strong>รู้หรือไม่:</strong> เพศและฮอร์โมนมีผลต่อการกระจายตัวของไขมันสะสมในร่างกาย
                    </div>
                </div>
            </div>


            <!-- QUESTION 2: ช่วงวัย -->
            <div id="q-2" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">ช่วงวัยในปัจจุบัน</h2>
                    <p class="q-subtitle">ช่วงอายุที่ตรงกับท่านมากที่สุด</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'age_group', 2)">
                        <input type="radio" name="age_group" value="young" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/sprout.jpg" alt="น้อยกว่า 35 ปี">
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
                            <img src="assets/img/clay/briefcase.jpg" alt="อายุ 35-59 ปี">
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
                            <img src="assets/img/clay/elder_glasses.jpg" alt="อายุ 60 ปีขึ้นไป">
                        </div>
                        <div class="opt-content">
                            <h4>อายุ 60 ปีขึ้นไป</h4>
                            <p>วัยผู้สูงอายุ</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
                <!-- Bottom Decorative Clay Tip -->
                <div class="clay-tip-banner">
                    <div class="clay-tip-thumb">
                        <img src="assets/img/clay/sun_yellow.png" alt="เกร็ดสุขภาพ">
                    </div>
                    <div class="clay-tip-text">
                        <strong>คำแนะนำ:</strong> วัย 35 ปีขึ้นไปควรตรวจคัดกรองความดันและเบาหวานปีละ 1 ครั้ง
                    </div>
                </div>
            </div>


            <!-- QUESTION 3: รูปร่าง & รอบเอว -->
            <div id="q-3" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">รูปร่างและรอบเอว</h2>
                    <p class="q-subtitle">สัดส่วนหน้าท้องและรูปร่างของท่าน</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card" onclick="pickOption(this, 'body_shape', 3)">
                        <input type="radio" name="body_shape" value="thin">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/leaf_green.png" alt="ผอม / น้ำหนักน้อย">
                        </div>
                        <div class="opt-content">
                            <h4>ผอม / น้ำหนักค่อนข้างน้อย</h4>
                            <p>ตัวเล็ก ผอมเพรียว ไม่มีพุง</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'body_shape', 3)">
                        <input type="radio" name="body_shape" value="slim" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/waist.jpg" alt="สมส่วน พอดีตัว">
                        </div>
                        <div class="opt-content">
                            <h4>สมส่วน พอดีตัว</h4>
                            <p>ไม่อึดอัด รูปร่างกำลังดี</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'body_shape', 3)">
                        <input type="radio" name="body_shape" value="chubby">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/donut_pink.png" alt="เริ่มมีพุง">
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
                            <img src="assets/img/clay/pizza.png" alt="อ้วนลงพุงชัดเจน">
                        </div>
                        <div class="opt-content">
                            <h4>อ้วนลงพุงชัดเจน</h4>
                            <p>พุงยื่นเยอะ เหนื่อยง่าย</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
                <!-- Bottom Decorative Clay Tip -->
                <div class="clay-tip-banner">
                    <div class="clay-tip-thumb">
                        <img src="assets/img/clay/waist.jpg" alt="เกร็ดสุขภาพ">
                    </div>
                    <div class="clay-tip-text">
                        <strong>เกณฑ์รอบเอวมาตรฐาน:</strong> ชายไม่เกิน 36 นิ้ว, หญิงไม่เกิน 32 นิ้ว
                    </div>
                </div>
            </div>


            <!-- QUESTION 4: ของหวาน & น้ำหวาน -->
            <div id="q-4" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">เรื่องเครื่องดื่ม & ของหวาน</h2>
                    <p class="q-subtitle">ชา ชานม กาแฟใส่นมข้น น้ำอัดลม ขนมหวาน</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'sweet_habit', 4)">
                        <input type="radio" name="sweet_habit" value="low" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/water_drop.png" alt="ดื่มน้ำเปล่าเป็นหลัก">
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
                            <img src="assets/img/clay/coffee_cup.png" alt="ดื่มบ้างบางวัน">
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
                <!-- Bottom Decorative Clay Tip -->
                <div class="clay-tip-banner">
                    <div class="clay-tip-thumb">
                        <img src="assets/img/clay/water.jpg" alt="เกร็ดสุขภาพ">
                    </div>
                    <div class="clay-tip-text">
                        <strong>เกร็ดลดน้ำตาล:</strong> การสั่ง "หวานน้อย" ช่วยลดพลังงานส่วนเกินได้ถึง 100 แคลอรีต่อแก้ว
                    </div>
                </div>
            </div>


            <!-- QUESTION 5: ของเค็ม & ของทอด -->
            <div id="q-5" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">เรื่องของเค็ม & ของทอด</h2>
                    <p class="q-subtitle">น้ำปลา ผงชูรส ปลาร้าเข้มข้น ไก่ทอด</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'salt_habit', 5)">
                        <input type="radio" name="salt_habit" value="low" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/avocado.png" alt="กินรสจืดๆ">
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
                            <img src="assets/img/clay/mushroom_purple.png" alt="กินรสจัดบ้าง">
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
                <!-- Bottom Decorative Clay Tip -->
                <div class="clay-tip-banner">
                    <div class="clay-tip-thumb">
                        <img src="assets/img/clay/bell_blue.png" alt="เกร็ดสุขภาพ">
                    </div>
                    <div class="clay-tip-text">
                        <strong>เกร็ดลดความดัน:</strong> ลดการซดน้ำแกงจนหมดถ้วย ช่วยลดโซเดียมลงได้มากกว่า 50%
                    </div>
                </div>
            </div>


            <!-- QUESTION 6: การกินผัก -->
            <div id="q-6" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">การกินผักในแต่ละมื้อ</h2>
                    <p class="q-subtitle">ผักสด ผักลวก ผักต้มในจานอาหาร</p>
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
                            <img src="assets/img/clay/donut_choco.png" alt="ไม่ค่อยกินผัก">
                        </div>
                        <div class="opt-content">
                            <h4>ไม่ค่อยกินผัก / กินน้อยมาก</h4>
                            <p>เน้นเนื้อสัตว์ ข้าว และของทอด</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
                <!-- Bottom Decorative Clay Tip -->
                <div class="clay-tip-banner">
                    <div class="clay-tip-thumb">
                        <img src="assets/img/clay/leaf_green.png" alt="เกร็ดสุขภาพ">
                    </div>
                    <div class="clay-tip-text">
                        <strong>ประโยชน์ของผัก:</strong> ใยอาหารช่วยดักจับไขมันและชะลอน้ำตาลไม่ให้พุ่งสูงหลังมื้ออาหาร
                    </div>
                </div>
            </div>


            <!-- QUESTION 7: การออกกำลังกาย -->
            <div id="q-7" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">การขยับร่างกาย & ออกกำลังกาย</h2>
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
                            <img src="assets/img/clay/sun_yellow.png" alt="มีเดินขยับตัวบ้าง">
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
                            <img src="assets/img/clay/tree_green.png" alt="แทบไม่ได้ออก">
                        </div>
                        <div class="opt-content">
                            <h4>แทบไม่ได้ออก / นั่งนานทั้งวัน</h4>
                            <p>นั่งทำงานหรือนอนเล่นมือถือนาน</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
                <!-- Bottom Decorative Clay Tip -->
                <div class="clay-tip-banner">
                    <div class="clay-tip-thumb">
                        <img src="assets/img/clay/exercise.jpg" alt="เกร็ดสุขภาพ">
                    </div>
                    <div class="clay-tip-text">
                        <strong>การขยับร่างกาย:</strong> แค่เดินเร็ววันละ 30 นาที ช่วยลดความดันตัวบนได้ทันที 5-10 mmHg
                    </div>
                </div>
            </div>


            <!-- QUESTION 8: การนอนหลับ -->
            <div id="q-8" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">คุณภาพการนอนหลับ</h2>
                    <p class="q-subtitle">การนอนหลับและการพักผ่อนของร่างกาย</p>
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
                            <img src="assets/img/clay/bell_blue.png" alt="หลับยาก">
                        </div>
                        <div class="opt-content">
                            <h4>หลับๆ ตื่นๆ / หลับยาก</h4>
                            <p>ตื่นกลางดึกบ่อย พักผ่อนไม่ค่อยพอ</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
                <!-- Bottom Decorative Clay Tip -->
                <div class="clay-tip-banner">
                    <div class="clay-tip-thumb">
                        <img src="assets/img/clay/sleep.jpg" alt="เกร็ดสุขภาพ">
                    </div>
                    <div class="clay-tip-text">
                        <strong>เกร็ดการนอน:</strong> เข้านอนก่อน 4 ทุ่ม ช่วยให้หลอดเลือดและหัวใจได้ฟื้นฟูเต็มประสิทธิภาพ
                    </div>
                </div>
            </div>


            <!-- QUESTION 9: บุหรี่ & สุรา -->
            <div id="q-9" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">เรื่องบุหรี่ & สุรา</h2>
                    <p class="q-subtitle">การสูบหรือดื่มในชีวิตประจำวัน</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'substance_habit', 9)">
                        <input type="radio" name="substance_habit" value="none" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/leaf_green.png" alt="ไม่สูบและไม่ดื่ม">
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
                            <img src="assets/img/clay/flower_green.png" alt="ดื่มเฉพาะงาน">
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
                            <img src="assets/img/clay/burger.png" alt="สูบหรือดื่มประจำ">
                        </div>
                        <div class="opt-content">
                            <h4>สูบหรือดื่มเป็นประจำ</h4>
                            <p>ทำต่อเนื่องเป็นประจำ</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
                <!-- Bottom Decorative Clay Tip -->
                <div class="clay-tip-banner">
                    <div class="clay-tip-thumb">
                        <img src="assets/img/clay/shield.jpg" alt="เกร็ดสุขภาพ">
                    </div>
                    <div class="clay-tip-text">
                        <strong>การปกป้องหลอดเลือด:</strong> เลี่ยงบุหรี่และสุรา ช่วยให้หลอดเลือดนุ่ม ยืดหยุ่น ไม่แข็งตัว
                    </div>
                </div>
            </div>


            <!-- QUESTION 10: ประวัติครอบครัว -->
            <div id="q-10" class="question-slide">
                <div class="q-header">
                    <h2 class="q-title">ประวัติสุขภาพในครอบครัว</h2>
                    <p class="q-subtitle">พ่อ แม่ หรือพี่น้องสายตรง</p>
                </div>
                <div class="clay-options-list">
                    <label class="clay-opt-card selected" onclick="pickOption(this, 'family_history', 10, true)">
                        <input type="radio" name="family_history" value="no" checked>
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/shield.jpg" alt="ไม่มีใครเป็น">
                        </div>
                        <div class="opt-content">
                            <h4>ไม่มีใครเป็น</h4>
                            <p>ไม่มีประวัติเบาหวานหรือความดัน</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                    <label class="clay-opt-card" onclick="pickOption(this, 'family_history', 10, true)">
                        <input type="radio" name="family_history" value="yes">
                        <div class="clay-icon-large">
                            <img src="assets/img/clay/heart_red.png" alt="มีคนเป็น">
                        </div>
                        <div class="opt-content">
                            <h4>มีคนเป็นเบาหวานหรือความดัน</h4>
                            <p>พ่อ แม่ หรือพี่น้องเคยเป็น</p>
                        </div>
                        <div class="opt-check-badge">✓</div>
                    </label>
                </div>
                <!-- Bottom Decorative Clay Tip -->
                <div class="clay-tip-banner">
                    <div class="clay-tip-thumb">
                        <img src="assets/img/clay/house.png" alt="เกร็ดสุขภาพ">
                    </div>
                    <div class="clay-tip-text">
                        <strong>ข้อคิดสุขภาพ:</strong> แม้มีพันธุกรรม แต่ถ้ากินอยู่ถูกต้อง ก็ชนะโรค NCDs ได้สบายๆ ครับ!
                    </div>
                </div>
            </div>


            <!-- STEP 11: หน้าสรุปผลตรวจสุขภาพ & การ์ดเกียรติยศสุขภาพ (Health Passport) -->
            <div id="q-result" class="question-slide">
                
                <!-- ============================================== -->
                <!-- 🏆 3D HEALTH TROPHY & PASSPORT CARD (FOR SHARE) -->
                <!-- ============================================== -->
                <div id="health-trophy-card" class="health-trophy-card">
                    <!-- Card Top Header -->
                    <div class="trophy-card-header">
                        <div class="trophy-org-title">
                            <?= render_neu_icon('shield-check', 'xs', 'text-green') ?>
                            <span>NCDs Health Passport • อำเภอ<?= DISTRICT_NAME ?></span>
                        </div>
                        <div class="trophy-verified-badge">
                            ✓ ประเมินด้วยตนเอง
                        </div>
                    </div>

                    <!-- Score Showcase -->
                    <div class="trophy-score-showcase">
                        <div class="trophy-disc-wrapper" id="trophy-disc-icon">
                            <?= render_neu_icon('heart-pulse', 'xl', 'disc-green') ?>
                        </div>
                        <div class="trophy-score-value" id="trophy-score-display">95</div>
                        <div class="trophy-score-label">คะแนนสุขภาพตนเอง (เต็ม 100)</div>
                        <div class="trophy-rank-banner" id="trophy-rank-title">
                            🌟 ยอดมนุษย์สายคลีน สุขภาพดีเด่นแห่งตาลสุม
                        </div>
                    </div>

                    <!-- 4 Pillars Matrix (3อ. 2ส. 1น.) -->
                    <div class="trophy-pillars-grid">
                        <div class="trophy-pillar-box">
                            <?= render_neu_icon('nutrition', 'xs', 'text-green') ?>
                            <div class="trophy-pillar-info">
                                <h5>ผักผลไม้ & โภชนาการ</h5>
                                <span id="pillar-nutrition-status">กินผักสม่ำเสมอ</span>
                            </div>
                        </div>
                        <div class="trophy-pillar-box">
                            <?= render_neu_icon('salt-sodium', 'xs', 'text-blue') ?>
                            <div class="trophy-pillar-info">
                                <h5>การควบคุมเค็ม & หวาน</h5>
                                <span id="pillar-taste-status">สั่งหวานน้อย คุมเค็ม</span>
                            </div>
                        </div>
                        <div class="trophy-pillar-box">
                            <?= render_neu_icon('exercise', 'xs', 'text-yellow') ?>
                            <div class="trophy-pillar-info">
                                <h5>กิจกรรมทางกาย</h5>
                                <span id="pillar-exercise-status">ขยับกายต่อเนื่อง</span>
                            </div>
                        </div>
                        <div class="trophy-pillar-box">
                            <?= render_neu_icon('sleep', 'xs', 'text-navy') ?>
                            <div class="trophy-pillar-info">
                                <h5>การนอนหลับพักผ่อน</h5>
                                <span id="pillar-sleep-status">หลับสนิท สดชื่น</span>
                            </div>
                        </div>
                    </div>

                    <!-- Viral QR Code & Invite Footer -->
                    <div class="trophy-viral-footer">
                        <div class="trophy-qr-frame">
                            <div id="trophy-qrcode"></div>
                        </div>
                        <div class="trophy-invite-text">
                            <h4>📲 ชวนคุณมาเช็คสุขภาพด้วยกัน!</h4>
                            <p>สแกนตรวจความดัน-เบาหวานฟรี 1 นาที ไม่เก็บข้อมูลส่วนตัว</p>
                            <div class="trophy-date-stamp" id="trophy-date-label">
                                ตรวจเมื่อ: <?= date('d/m/Y') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- 🚀 VIRAL SHARING & ACTION BUTTONS -->
                <!-- ============================================== -->
                <div class="viral-actions-container">
                    <!-- LINE Share Button -->
                    <button type="button" onclick="shareToLine()" class="btn-viral-line">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.499.254l2.46 3.33v-2.959c0-.345.282-.63.63-.63.345 0 .628.285.628.63v4.774zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>
                        <span>อวดคะแนนสุขภาพทาง LINE (ชวนเพื่อน)</span>
                    </button>

                    <!-- Save as PNG Button -->
                    <button type="button" onclick="saveTrophyImage()" class="btn-viral-save">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <span id="save-btn-label">บันทึกการ์ดเกียรติยศลงเครื่อง (Save Image)</span>
                    </button>

                    <!-- Copy Link Button -->
                    <button type="button" onclick="copyInviteLink()" class="btn-viral-copy">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        <span id="copy-btn-label">คัดลอกข้อความ & ลิงก์ชวนเพื่อน</span>
                    </button>
                </div>

                <!-- Voice Coach Player Bar -->
                <div class="voice-coach-bar">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <?= render_neu_icon('mobile-health', 'sm', 'text-purple') ?>
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
                    <div class="solution-title" style="color: #2563eb; display: flex; align-items: center; gap: 8px;">
                        <?= render_neu_icon('thermometer', 'xs', 'disc-blue') ?>
                        <span>ถ้าอยากลดความดัน ต้องทำอย่างไร?</span>
                    </div>
                    <ul class="solution-list" id="bp-advice-list">
                        <!-- Populated by JS -->
                    </ul>
                </div>

                <!-- Solution Card 2: อยากลดค่าน้ำตาล / เบาหวาน ต้องทำอย่างไร? -->
                <div class="solution-box sugar-box">
                    <div class="solution-title" style="color: #d97706; display: flex; align-items: center; gap: 8px;">
                        <?= render_neu_icon('syringe', 'xs', 'disc-yellow') ?>
                        <span>ถ้าอยากลดค่าน้ำตาล ต้องทำอย่างไร?</span>
                    </div>
                    <ul class="solution-list" id="sugar-advice-list">
                        <!-- Populated by JS -->
                    </ul>
                </div>

                <!-- Community Referral Card -->
                <div style="background: linear-gradient(135deg, #0d2c54, #1e3a8a); color: white; border-radius: 20px; padding: 18px; margin-bottom: 20px; box-shadow: 0 8px 20px rgba(13, 44, 84, 0.25); text-align: left;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                        <?= render_neu_icon('doctor', 'md', 'disc-blue') ?>
                        <div>
                            <h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #38bdf8;">อยากตรวจวัดค่าความดันและค่าน้ำตาลจริง?</h4>
                            <p style="margin: 2px 0 0 0; font-size: 12px; color: #cbd5e1;">ระบบสาธารณสุขอำเภอ<?= DISTRICT_NAME ?> พร้อมดูแลท่านฟรี</p>
                        </div>
                    </div>
                    <p style="font-size: 13px; line-height: 1.45; margin: 8px 0; color: #f1f5f9;">
                        ติดต่อ <strong>อสม. ประจำคุ้มบ้านของท่าน</strong> เพื่อตรวจวัดความดันโลหิตและเจาะน้ำตาลปลายนิ้ว หรือตรวจสุขภาพประจำปีได้ฟรีที่ <strong>รพ.สต. ใกล้บ้าน</strong> ได้เลยครับ
                    </p>
                </div>

                <!-- Secondary Bottom Actions -->
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button type="button" onclick="restartScreening()" class="btn-giant btn-giant-secondary" style="margin: 0; padding: 12px; font-size: 14px;">
                        🔄 ประเมินใหม่อีกครั้ง
                    </button>
                    <a href="index.php" style="text-align: center; color: var(--color-accent); text-decoration: none; font-size: 14px; font-weight: 700; margin-top: 4px; display: block;">
                        ← กลับหน้าหลัก
                    </a>
                </div>

            </div>

        </form>

    </div>

    <!-- Save Image Success Modal (Mobile Friendly) -->
    <div id="image-save-modal" class="image-modal-backdrop" onclick="closeImageModal(event)">
        <div class="image-modal-content" onclick="event.stopPropagation()">
            <h3 style="margin: 0 0 6px 0; font-size: 17px; color: var(--color-accent); font-weight: 800;">
                🎉 การ์ดสุขภาพของคุณพร้อมแล้ว!
            </h3>
            <p style="font-size: 12.5px; color: var(--text-secondary); margin: 0 0 10px 0;">
                แตะค้างที่รูปภาพด้านล่างเพื่อบันทึกรูป หรือกดปุ่มดาวน์โหลด
            </p>
            <img id="image-modal-preview" class="image-preview-target" alt="Health Trophy Card">
            <div style="display: flex; gap: 10px;">
                <a id="image-modal-download-link" href="#" download="ncd_health_passport.png" class="btn-giant btn-giant-primary" style="flex: 1; margin: 0; padding: 12px; font-size: 14px; text-decoration: none; display: flex; align-items: center; justify-content: center;">
                    📥 ดาวน์โหลด
                </a>
                <button type="button" onclick="closeImageModal()" class="btn-giant btn-giant-secondary" style="margin: 0; padding: 12px 18px; font-size: 14px;">
                    ปิด
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentQ = 0; // Starts at Step 0: Intro & Privacy Guarantee
        const totalQ = 10;
        let calculatedRiskLevel = 'green';
        let currentVoiceText = '';
        let isNavigating = false;
        let isStatsSaved = false;

        const trophyIcons = {
            green: `<?= str_replace(["\r", "\n"], '', render_neu_icon('heart-pulse', 'xl', 'disc-green')) ?>`,
            yellow: `<?= str_replace(["\r", "\n"], '', render_neu_icon('thermometer', 'xl', 'disc-yellow')) ?>`,
            red: `<?= str_replace(["\r", "\n"], '', render_neu_icon('warning-alert', 'xl', 'disc-red')) ?>`
        };

        const motivationalBadges = [
            '🌱 เริ่มต้นง่ายๆ',
            '✨ สบายๆ อีกนิดเดียว',
            '🏃‍♂️ ทำได้ดีมาก',
            '🥗 ไปต่อกันเลย',
            '🎉 ครึ่งทางแล้ว!',
            '🥦 สุขภาพสดใส',
            '💪 เกือบเสร็จแล้ว',
            '🌙 ใกล้ถึงผลตรวจแล้ว',
            '⚡ อีกคำถามเดียว',
            '🎯 ข้อสุดท้ายแล้ว!'
        ];

        function startScreening() {
            currentQ = 1;
            isStatsSaved = false;
            updateUIState();
        }

        function updateUIState() {
            document.querySelectorAll('.question-slide').forEach(q => q.classList.remove('active'));
            
            const prevBtn = document.getElementById('btn-side-prev');
            const nextBtn = document.getElementById('btn-side-next');
            const topNav = document.getElementById('top-nav-section');

            if (currentQ === 0) {
                // Step 0: Intro Screen
                const introQ = document.getElementById('q-intro');
                if (introQ) introQ.classList.add('active');

                document.getElementById('step-badge').innerText = '🛡️ ปลอดภัย ไม่เก็บข้อมูลส่วนตัว';
                document.getElementById('self-progress').style.width = '0%';
                topNav.style.display = 'block';

                prevBtn.classList.add('disabled');
                nextBtn.classList.add('disabled');
            } else if (currentQ >= 1 && currentQ <= totalQ) {
                // Questions 1 to 10
                const targetQ = document.getElementById('q-' + currentQ);
                if (targetQ) targetQ.classList.add('active');

                // Dynamic Motivational Badge & Smooth Progress
                const badgeText = (motivationalBadges[currentQ - 1] || `กำลังประเมิน`) + ` (${currentQ}/${totalQ})`;
                document.getElementById('step-badge').innerText = badgeText;
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
            if (currentQ === 0) {
                startScreening();
            } else if (currentQ < totalQ) {
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
            } else if (currentQ === 1) {
                currentQ = 0;
                updateUIState();
            }
        }

        function restartScreening() {
            currentQ = 0;
            isStatsSaved = false;
            updateUIState();
        }

        function calculateSelfResults() {
            const form = document.getElementById('self-screening-form');
            const data = new FormData(form);

            const gender = data.get('gender') || 'male';
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

            if (shape === 'thin') {
                sugarAdvices.push('<strong>กินอาหารครบ 5 หมู่:</strong> เสริมโปรตีนคุณภาพ (ไข่ ปลา เต้าหู้ ถั่ว) และข้าวกล้อง เพื่อสร้างกล้ามเนื้อและให้พลังงานเพียงพอ');
            } else if (shape === 'obese' || shape === 'chubby') {
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

            // Populate 3D Health Trophy Card (for sharing & saving)
            const healthScore = Math.max(25, Math.min(100, Math.round(100 - (riskPoints * 4.5))));
            const scoreDisplay = document.getElementById('trophy-score-display');
            const rankTitle = document.getElementById('trophy-rank-title');
            const cardElement = document.getElementById('health-trophy-card');

            if (scoreDisplay) scoreDisplay.innerText = healthScore;

            if (riskPoints <= 4) {
                calculatedRiskLevel = 'green';
                if (scoreDisplay) scoreDisplay.style.color = '#10b981';
                if (rankTitle) {
                    rankTitle.innerText = '🌟 ยอดมนุษย์สายคลีน สุขภาพดีเด่นแห่งตาลสุม';
                    rankTitle.style.background = 'linear-gradient(135deg, #10b981, #059669)';
                    rankTitle.style.boxShadow = '0 4px 14px rgba(16, 185, 129, 0.35)';
                }
                if (cardElement) cardElement.style.borderColor = 'rgba(16, 185, 129, 0.4)';
                currentVoiceText = 'ผลตรวจเช็คสุขภาพโดยรวม ดีมากเลยค่ะ คุณได้คะแนนสุขภาพ ' + healthScore + ' เต็มร้อย ดูแลสุขภาพได้ดีมาก ทำต่อเนื่องไปนะคะ';
            } else if (riskPoints <= 9) {
                calculatedRiskLevel = 'yellow';
                if (scoreDisplay) scoreDisplay.style.color = '#d97706';
                if (rankTitle) {
                    rankTitle.innerText = '⚡ นักสู้สายฟิต พร้อมปรับลดหวาน-ลดเค็ม';
                    rankTitle.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
                    rankTitle.style.boxShadow = '0 4px 14px rgba(245, 158, 11, 0.35)';
                }
                if (cardElement) cardElement.style.borderColor = 'rgba(245, 158, 11, 0.4)';
                currentVoiceText = 'ผลตรวจรอบนี้ คุณได้คะแนนสุขภาพ ' + healthScore + ' คะแนน เริ่มมีสัญญาณเสี่ยงนิดหน่อยนะคะ ไม่เป็นไรค่ะ ชวนลดหวาน ลดเค็ม แล้วดื่มน้ำเปล่าเพิ่มขึ้นนะคะ';
            } else {
                calculatedRiskLevel = 'red';
                if (scoreDisplay) scoreDisplay.style.color = '#dc2626';
                if (rankTitle) {
                    rankTitle.innerText = '🛡️ ฮีโร่ตระหนักรู้ พร้อมดูแลตัวเองเพื่อคนที่รัก';
                    rankTitle.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
                    rankTitle.style.boxShadow = '0 4px 14px rgba(239, 68, 68, 0.35)';
                }
                if (cardElement) cardElement.style.borderColor = 'rgba(239, 68, 68, 0.4)';
                currentVoiceText = 'ผลตรวจรอบนี้ คุณได้คะแนนสุขภาพ ' + healthScore + ' คะแนน ต้องดูแลเป็นพิเศษค่ะ เดี๋ยวให้ อสม. ช่วยตรวจเช็คความดันและค่าน้ำตาลให้นะคะ';
            }

            const trophyDiscWrapper = document.getElementById('trophy-disc-icon');
            if (trophyDiscWrapper && trophyIcons[calculatedRiskLevel]) {
                trophyDiscWrapper.innerHTML = trophyIcons[calculatedRiskLevel];
            }

            // Update 4 Pillars Status Badges
            const pNutrition = document.getElementById('pillar-nutrition-status');
            const pTaste = document.getElementById('pillar-taste-status');
            const pExercise = document.getElementById('pillar-exercise-status');
            const pSleep = document.getElementById('pillar-sleep-status');

            if (pNutrition) pNutrition.innerText = (veggie === 'good' ? 'กินผักสม่ำเสมอ ✓' : 'ควรเพิ่มผักในมื้อ');
            if (pTaste) pTaste.innerText = (sweet === 'low' && salt === 'low' ? 'คุมหวาน คุมเค็มดีเยี่ยม ✓' : (salt === 'high' ? 'ควรลดเค็ม/เลี่ยงของดอง' : 'ควรสั่งหวานน้อย'));
            if (pExercise) pExercise.innerText = (exercise === 'regular' ? 'ออกกำลังสม่ำเสมอ ✓' : (exercise === 'some' ? 'ขยับกายปานกลาง' : 'ควรขยับกายเพิ่มขึ้น'));
            if (pSleep) pSleep.innerText = (sleep === 'good' ? 'หลับสนิท สดชื่น ✓' : (sleep === 'poor' ? 'ควรนอนหลับให้พอ' : 'หลับๆ ตื่นๆ ควรผ่อนคลาย'));

            // Generate Dynamic QR Code inside the card
            const qrContainer = document.getElementById('trophy-qrcode');
            if (qrContainer && typeof QRCode !== 'undefined') {
                qrContainer.innerHTML = '';
                const shareUrl = window.location.origin + window.location.pathname;
                try {
                    new QRCode(qrContainer, {
                        text: shareUrl,
                        width: 56,
                        height: 56,
                        colorDark: '#0d2c54',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.M
                    });
                } catch (qrErr) {
                    console.warn('QR Code generation error:', qrErr);
                }
            }

            // Silent & Non-intrusive 100% Anonymous Stats Persistence
            if (!isStatsSaved) {
                isStatsSaved = true;
                let sessionToken = sessionStorage.getItem('ncd_anon_token');
                if (!sessionToken) {
                    sessionToken = 'anon_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
                    sessionStorage.setItem('ncd_anon_token', sessionToken);
                }

                const payload = {
                    session_hash: sessionToken,
                    gender: gender,
                    age_group: age,
                    body_shape: shape,
                    sweet_habit: sweet,
                    salt_habit: salt,
                    veggie_habit: veggie,
                    exercise_habit: exercise,
                    sleep_habit: sleep,
                    substance_habit: substance,
                    family_history: family,
                    risk_points: riskPoints,
                    risk_level: calculatedRiskLevel
                };

                fetch('api/save_citizen_screening.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(res => res.json())
                  .then(json => {
                      console.log('Anonymous assessment logged:', json.status);
                  })
                  .catch(err => {
                      // Silent fail to preserve user experience
                      console.warn('Silent stats log:', err);
                  });
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

        // ==============================================
        // 💬 VIRAL SHARING & IMAGE SAVING FUNCTIONS
        // ==============================================
        async function shareToLine() {
            const scoreEl = document.getElementById('trophy-score-display');
            const score = scoreEl ? scoreEl.innerText : '85';
            const rankEl = document.getElementById('trophy-rank-title');
            const rank = rankEl ? rankEl.innerText.replace(/^[^\w\s\u0E00-\u0E7F]+/, '').trim() : 'ยอดมนุษย์สายคลีน สุขภาพดีเด่น';
            const shareUrl = window.location.origin + window.location.pathname;
            
            const textMsg = `🌟 ฉันตรวจเช็คสุขภาพตนเองแล้วได้คะแนน ${score}/100 คะแนน!\n🏆 ฉายาสุขภาพ: "${rank}"\n\n🥗 มาตรวจเช็คความเสี่ยงความดัน-เบาหวานด้วยตัวเองกันเถอะ (ฟรี 1 นาที ไม่เก็บข้อมูลส่วนตัว 100%):\n👉 ${shareUrl}`;

            const card = document.getElementById('health-trophy-card');

            // 1. Mobile Native Share Sheet (Directly attaches the Image Card into LINE/Apps)
            if (typeof html2canvas !== 'undefined' && card) {
                try {
                    showToast('⏳ กำลังเตรียมการ์ดรูปภาพเพื่อแชร์...');
                    const canvas = await html2canvas(card, {
                        scale: 2.5,
                        useCORS: true,
                        allowTaint: true,
                        backgroundColor: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                        logging: false
                    });

                    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
                    const file = new File([blob], `Health_Passport_${Date.now()}.png`, { type: 'image/png' });

                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        await navigator.share({
                            files: [file],
                            title: 'การ์ดคะแนนสุขภาพ - อำเภอ<?= DISTRICT_NAME ?>',
                            text: textMsg
                        });
                        showToast('🎉 แชร์การ์ดรูปภาพสำเร็จแล้ว!');
                        return;
                    }
                } catch (e) {
                    console.log('Native file share fallback or user cancel:', e);
                }
            }

            // 2. Fallback: Direct LINE protocol link
            const lineUrl = `https://line.me/R/msg/text/?${encodeURIComponent(textMsg)}`;
            window.open(lineUrl, '_blank');
        }

        function saveTrophyImage() {
            const card = document.getElementById('health-trophy-card');
            const saveBtnLabel = document.getElementById('save-btn-label');
            const origText = saveBtnLabel ? saveBtnLabel.innerText : 'บันทึกการ์ดเกียรติยศลงเครื่อง (Save Image)';
            
            if (saveBtnLabel) saveBtnLabel.innerText = '⏳ กำลังสร้างรูปภาพความละเอียดสูง...';

            if (typeof html2canvas === 'undefined') {
                alert('💡 กำลังดาวน์โหลดโมดูลสร้างรูปภาพ กรุณาลองใหม่อีกครั้งใน 2-3 วินาที');
                if (saveBtnLabel) saveBtnLabel.innerText = origText;
                return;
            }

            // Ensure smooth canvas rendering
            html2canvas(card, {
                scale: 2.5, // Crisp HD Retina Resolution
                useCORS: true,
                allowTaint: true,
                backgroundColor: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                logging: false
            }).then(canvas => {
                if (saveBtnLabel) saveBtnLabel.innerText = origText;
                const imgData = canvas.toDataURL('image/png');
                
                // Populate Modal for mobile tap-and-hold saving
                const modal = document.getElementById('image-save-modal');
                const preview = document.getElementById('image-modal-preview');
                const dlLink = document.getElementById('image-modal-download-link');
                const fileName = `NCDs_Health_Passport_${Date.now()}.png`;

                if (preview) preview.src = imgData;
                if (dlLink) {
                    dlLink.href = imgData;
                    dlLink.download = fileName;
                }

                // Try direct automatic download
                try {
                    const tempLink = document.createElement('a');
                    tempLink.href = imgData;
                    tempLink.download = fileName;
                    document.body.appendChild(tempLink);
                    tempLink.click();
                    document.body.removeChild(tempLink);
                } catch (e) {
                    console.log('Direct download fallback to modal', e);
                }

                // Show modal on screen
                if (modal) modal.style.display = 'flex';
                showToast('🎉 บันทึกการ์ดสุขภาพสำเร็จแล้ว!');
            }).catch(err => {
                console.error('Error generating canvas image:', err);
                if (saveBtnLabel) saveBtnLabel.innerText = origText;
                alert('💡 ท่านสามารถแคปภาพหน้าจอนี้ (Screenshot) เพื่อบันทึกรูปหรือส่งไลน์ให้คนอื่นได้เลยครับ!');
            });
        }

        function closeImageModal(e) {
            const modal = document.getElementById('image-save-modal');
            if (modal) modal.style.display = 'none';
        }

        function copyInviteLink() {
            const scoreEl = document.getElementById('trophy-score-display');
            const score = scoreEl ? scoreEl.innerText : '85';
            const shareUrl = window.location.origin + window.location.pathname;
            const text = `🌟 ฉันตรวจเช็คสุขภาพตนเองแล้วได้คะแนน ${score}/100! ชวนทุกคนมาตรวจเช็คความเสี่ยงความดัน-เบาหวานฟรี 1 นาที ไม่เก็บข้อมูลส่วนตัวที่ 👉 ${shareUrl}`;
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast('📋 คัดลอกข้อความและลิงก์ชวนเพื่อนเรียบร้อยแล้ว!');
                }).catch(() => fallbackCopy(text));
            } else {
                fallbackCopy(text);
            }
        }

        function fallbackCopy(text) {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showToast('📋 คัดลอกข้อความและลิงก์ชวนเพื่อนเรียบร้อยแล้ว!');
        }

        function showToast(msg) {
            const existing = document.getElementById('app-floating-toast');
            if (existing) document.body.removeChild(existing);

            const toast = document.createElement('div');
            toast.id = 'app-floating-toast';
            toast.style.cssText = 'position:fixed; bottom:25px; left:50%; transform:translateX(-50%); background:#0d2c54; color:#ffffff; padding:12px 24px; border-radius:30px; font-size:13.5px; font-weight:800; z-index:9999999; box-shadow:0 10px 30px rgba(0,0,0,0.35); border:1px solid rgba(255,255,255,0.25); text-align:center; max-width:90%;';
            toast.innerText = msg;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.4s ease';
                setTimeout(() => {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 400);
            }, 3000);
        }

        // Initialize state on load
        document.addEventListener('DOMContentLoaded', () => {
            updateUIState();
        });
    </script>
</body>
</html>
