<?php
// about.php
session_start();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เกี่ยวกับระบบและผู้พัฒนา - NCDs Prevention Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: var(--font-base);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .about-container {
            width: 100%;
            max-width: 550px;
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .about-card {
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 35px 30px;
            box-shadow: var(--neumorph-flat);
            text-align: center;
            position: relative;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .about-logo-wrapper {
            margin-bottom: 24px;
            display: inline-block;
        }

        .about-logo {
            width: 150px;
            height: 150px;
            object-fit: contain;
            border-radius: 20px;
            cursor: pointer;
            filter: drop-shadow(0 10px 20px rgba(13, 44, 84, 0.15));
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .about-logo:hover {
            transform: scale(1.08) rotate(2deg);
            filter: drop-shadow(0 15px 25px rgba(13, 44, 84, 0.25));
        }

        .about-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 8px 0;
            line-height: 1.4;
        }

        .about-subtitle {
            color: var(--color-accent);
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 25px;
        }

        .info-grid {
            text-align: left;
            background-color: var(--bg-darker);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: var(--neumorph-inset);
        }

        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid rgba(13, 44, 84, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            width: 110px;
            font-weight: 800;
            color: var(--text-secondary);
            font-size: 14.5px;
            flex-shrink: 0;
        }

        .info-value {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 14.5px;
            line-height: 1.5;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            height: 48px;
            border: 1px solid var(--color-primary);
            background-color: var(--color-primary);
            color: #ffffff;
            border-radius: 16px;
            font-size: 15px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            box-shadow: var(--neumorph-flat);
            transition: all var(--transition-speed);
        }

        .btn-back:hover {
            background-color: var(--color-primary-hover);
            transform: translateY(-2px);
        }

        .btn-back:active {
            box-shadow: var(--neumorph-inset);
            transform: translateY(0);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(13, 44, 84, 0.85);
            backdrop-filter: blur(10px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
        }

        .modal-content-wrapper {
            position: relative;
            max-width: 90%;
            max-height: 90%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .modal-content {
            width: 100%;
            max-width: 500px;
            height: auto;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            background-color: #ffffff;
            padding: 10px;
        }

        .modal.show .modal-content {
            transform: scale(1);
        }

        .close-btn {
            position: absolute;
            top: -50px;
            right: 0;
            color: #ffffff;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
            background: none;
            border: none;
            outline: none;
        }

        .close-btn:hover {
            color: var(--color-red);
        }

        @media (max-width: 480px) {
            .about-card {
                padding: 25px 20px;
            }

            .info-row {
                flex-direction: column;
                gap: 4px;
            }

            .info-label {
                width: 100%;
                font-size: 13px;
            }

            .info-value {
                font-size: 14px;
            }
        }
    </style>
</head>

<body>

    <div class="about-container">
        <div class="about-card">
            <!-- Clickable Logo -->
            <div class="about-logo-wrapper" onclick="openModal()" title="คลิกเพื่อดูรูปภาพขนาดใหญ่">
                <img src="assets/aboutus.png" alt="NCDs Prevention Logo" class="about-logo">
            </div>

            <h1 class="about-title">NCDs Prevention Portal</h1>
            <p class="about-subtitle">สำนักงานสาธารณสุขอำเภอตาลสุม</p>

            <!-- Info Grid -->
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">ชื่อระบบ:</div>
                    <div class="info-value">ระบบคัดกรอง ดูแล และป้องกันโรคไม่ติดต่อเรื้อรัง (NCDs Prevention Portal)
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">เวอร์ชั่นพัฒนา:</div>
                    <div class="info-value">Version 2.6.0 (พ.ศ. 2569)</div>
                </div>
                <div class="info-row">
                    <div class="info-label">ผู้พัฒนา:</div>
                    <div class="info-value">นายบุญธรรม พันธ์ใหญ่<br><span
                            style="font-size: 13px; color: var(--text-secondary); font-weight: normal;">นักวิชาการคอมพิวเตอร์<br>สำนักงานสาธารณสุขอำเภอตาลสุม</span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">อัพเดทล่าสุด:</div>
                    <div class="info-value">2 มิถุนายน 2569</div>
                </div>
            </div>

            <!-- Back Button -->
            <?php
            // Determine back URL
            $backUrl = 'index.php';
            if (isset($_SESSION['vhv_id'])) {
                $backUrl = 'vhv/index.php';
            } elseif (isset($_SESSION['admin_username'])) {
                $backUrl = 'admin/index.php';
            }
            ?>
            <a href="<?= htmlspecialchars($backUrl) ?>" class="btn-back">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                <span>ย้อนกลับ</span>
            </a>
        </div>
    </div>

    <!-- Modal for Logo Preview -->
    <div id="imageModal" class="modal" onclick="closeModal(event)">
        <div class="modal-content-wrapper" onclick="event.stopPropagation()">
            <button class="close-btn" onclick="closeModal(event)">&times;</button>
            <img class="modal-content" src="assets/aboutus.png" alt="NCDs Prevention Logo Enlarged">
        </div>
    </div>

    <script>
        const modal = document.getElementById('imageModal');

        function openModal() {
            modal.style.display = 'flex';
            // Force redraw before adding class for smooth transition
            modal.offsetHeight;
            modal.classList.add('show');
            document.body.style.overflow = 'hidden'; // Disable scroll on body
        }

        function closeModal(event) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto'; // Re-enable scroll
            }, 300); // Match transition duration
        }

        // Close on ESC key press
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('show')) {
                closeModal();
            }
        });
    </script>

</body>

</html>