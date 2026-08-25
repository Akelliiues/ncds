<?php
// about.php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/demo_banner.php';
require_once __DIR__ . '/config/line_config.php';
date_default_timezone_set('Asia/Bangkok');

function get_system_last_update() {
    $last_update = null;

    // 1. Try to get the latest Git commit timestamp
    if (function_exists('shell_exec')) {
        $git_time = @shell_exec('git log -1 --format=%ct');
        if ($git_time) {
            $last_update = intval(trim($git_time));
        }
    }

    // 2. If Git is not available, scan modification times
    if (!$last_update) {
        $max_time = 0;
        $paths = [
            '*.php',
            'admin/*.php',
            'vhv/*.php',
            'api/*.php',
            'assets/css/*.css',
            'assets/js/*.js'
        ];
        
        foreach ($paths as $path) {
            $files = glob(__DIR__ . '/' . $path);
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (file_exists($file)) {
                        $mtime = filemtime($file);
                        if ($mtime > $max_time) {
                            $max_time = $mtime;
                        }
                    }
                }
            }
        }
        $last_update = $max_time ? $max_time : filemtime(__FILE__);
    }

    return $last_update;
}

function format_thai_date($timestamp) {
    $thai_months = [
        1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    $day = date('j', $timestamp);
    $month = $thai_months[intval(date('n', $timestamp))];
    $year = date('Y', $timestamp) + 543;
    return "$day $month $year";
}

$last_update_ts = get_system_last_update();
$last_update_str = format_thai_date($last_update_ts);

function get_system_build_number($last_update_ts) {
    $commit_count = null;
    if (function_exists('shell_exec')) {
        $count = @shell_exec('git rev-list --count HEAD');
        if ($count !== null && trim($count) !== '') {
            $commit_count = trim($count);
        }
    }
    
    $time_code = date('ymd.Hi', $last_update_ts);
    
    if ($commit_count) {
        return "{$commit_count}.{$time_code}";
    }
    
    return $time_code;
}

$build_number = get_system_build_number($last_update_ts);

function get_system_changelog() {
    $changelog = [];
    $json_file = __DIR__ . '/changelog.json';

    // 1. Primary: Curated changelog.json (Concise High-Level Summaries)
    if (file_exists($json_file)) {
        $json_data = json_decode(file_get_contents($json_file), true);
        if (is_array($json_data)) {
            $count = 0;
            foreach ($json_data as $item) {
                if (empty($item['title'])) continue;
                $changelog[] = [
                    'title' => $item['title'],
                    'timestamp' => isset($item['date']) ? strtotime($item['date']) : time(),
                    'type' => $item['type'] ?? 'feature'
                ];
                $count++;
                if ($count >= 5) break; // Limit to top 5 items
            }
        }
    }

    // 2. Fallback to Git log if JSON is empty
    if (empty($changelog) && function_exists('shell_exec')) {
        $git_log = @shell_exec('git log -10 --pretty=format:"%s|%ct" 2>/dev/null');
        if ($git_log) {
            $lines = explode("\n", trim($git_log));
            $count = 0;
            foreach ($lines as $line) {
                if (empty($line) || strpos($line, '|') === false) continue;
                list($message, $timestamp) = explode('|', $line, 2);
                $message = trim($message);
                $timestamp = intval(trim($timestamp));

                $msg_lower = strtolower($message);
                if (strpos($msg_lower, 'merge') !== false || strlen($message) < 5) continue;

                $type = 'feature';
                if (strpos($msg_lower, 'fix') !== false || strpos($msg_lower, 'แก้ไข') !== false) {
                    $type = 'fix';
                } elseif (strpos($msg_lower, 'doc') !== false || strpos($msg_lower, 'คู่มือ') !== false) {
                    $type = 'docs';
                } elseif (strpos($msg_lower, 'sec') !== false || strpos($msg_lower, 'ความปลอดภัย') !== false) {
                    $type = 'security';
                }

                $changelog[] = [
                    'title' => $message,
                    'timestamp' => $timestamp,
                    'type' => $type
                ];

                $count++;
                if ($count >= 5) break;
            }
        }
    }

    // Default fallback
    if (empty($changelog)) {
        $changelog[] = [
            'title' => 'ปรับปรุงระบบและเพิ่มประสิทธิภาพการทำงานทั่วไป',
            'timestamp' => time(),
            'type' => 'feature'
        ];
    }

    return $changelog;
}

function get_time_diff_display($timestamp) {
    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 0) $diff = 0;

    if ($diff < 3600) {
        $mins = max(1, intval($diff / 60));
        return "$mins นาทีที่แล้ว";
    } elseif ($diff < 86400) {
        $hours = intval($diff / 3600);
        return "$hours ชั่วโมงที่แล้ว";
    } elseif ($diff < 172800) {
        return "เมื่อวานนี้";
    } else {
        $thai_months = [
            1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
            'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
        ];
        $day = date('j', $timestamp);
        $month = $thai_months[intval(date('n', $timestamp))];
        $year = date('Y', $timestamp) + 543;
        return "$day $month $year";
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เกี่ยวกับระบบและผู้พัฒนา - NCDs Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --color-primary: #0D2C54;
            --color-primary-light: #1A3E6D;
            --color-accent: #00A878;
            --color-red: #EF4444;
            --color-amber: #F59E0B;
            --color-blue: #3B82F6;
            --color-purple: #8B5CF6;
            --color-pink: #EC4899;
            --bg-main: #F0F4F8;
            --bg-card: #FFFFFF;
            --bg-darker: #F4F7FB;
            --text-primary: #1E293B;
            --text-secondary: #64748B;
            --text-muted: #94A3B8;
            --neumorph-flat: 8px 8px 20px #d1d9e6, -8px -8px 20px #ffffff;
            --neumorph-inset: inset 3px 3px 6px #d1d9e6, inset -3px -3px 6px #ffffff;
            --border-radius: 24px;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: var(--font-base, 'Prompt', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px 16px;
            box-sizing: border-box;
        }

        .about-container {
            width: 100%;
            max-width: 680px;
            animation: fadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .about-card {
            background-color: var(--bg-card);
            border-radius: var(--border-radius);
            padding: 32px 28px;
            box-shadow: var(--neumorph-flat);
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Compact Sleek Header */
        .about-header {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1.5px solid rgba(13, 44, 84, 0.06);
            text-align: left;
        }

        .about-logo-badge {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: linear-gradient(145deg, #ffffff, #e6ecf5);
            box-shadow: 4px 4px 12px rgba(13, 44, 84, 0.12), -4px -4px 12px #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 2px solid rgba(255, 255, 255, 0.9);
            padding: 6px;
            box-sizing: border-box;
        }

        .about-logo-badge:hover {
            transform: scale(1.06) rotate(3deg);
            box-shadow: 6px 6px 16px rgba(13, 44, 84, 0.18), -4px -4px 12px #ffffff;
        }

        .about-logo-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 14px;
        }

        .about-header-text {
            flex-grow: 1;
            min-width: 0;
        }

        .about-title-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 4px;
        }

        .about-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--color-primary);
            margin: 0;
            letter-spacing: -0.5px;
        }

        .version-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            background: linear-gradient(135deg, #00A878, #059669);
            color: #ffffff;
            font-size: 11.5px;
            font-weight: 800;
            border-radius: 20px;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 6px rgba(0, 168, 120, 0.3);
        }

        .about-desc {
            color: var(--text-secondary);
            font-size: 13.5px;
            margin: 0 0 6px 0;
            font-weight: 600;
            line-height: 1.4;
        }

        .org-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: var(--color-primary);
            font-weight: 700;
            background-color: var(--bg-darker);
            padding: 4px 10px;
            border-radius: 12px;
            box-shadow: var(--neumorph-inset);
        }

        /* Standout Emergency Fast-Track Hero Banner */
        .fasttrack-hero-card {
            background: linear-gradient(135deg, #fff5f5 0%, #fee2e2 100%);
            border: 1.5px solid #fca5a5;
            border-radius: 20px;
            padding: 18px 20px;
            margin-bottom: 24px;
            text-align: left;
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.12);
            position: relative;
            overflow: hidden;
        }

        .fasttrack-hero-card::after {
            content: "🚨";
            position: absolute;
            right: -10px;
            bottom: -15px;
            font-size: 80px;
            opacity: 0.12;
            pointer-events: none;
        }

        .fasttrack-hero-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .fasttrack-hero-title {
            font-size: 15px;
            font-weight: 800;
            color: #991b1b;
            margin: 0;
        }

        .fasttrack-hero-tag {
            background-color: #ef4444;
            color: #ffffff;
            font-size: 10.5px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 10px;
            text-transform: uppercase;
        }

        .fasttrack-hero-desc {
            font-size: 12.5px;
            color: #7f1d1d;
            line-height: 1.5;
            margin: 0 0 12px 0;
            font-weight: 500;
        }

        /* 3-Step Flow Pipeline */
        .fasttrack-pipeline {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            background: rgba(255, 255, 255, 0.75);
            padding: 10px;
            border-radius: 14px;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .pipeline-step {
            text-align: center;
            padding: 4px;
        }

        .pipeline-step-badge {
            font-size: 11px;
            font-weight: 800;
            color: #b91c1c;
            display: block;
            margin-bottom: 2px;
        }

        .pipeline-step-text {
            font-size: 11px;
            color: #475569;
            line-height: 1.3;
            margin: 0;
            font-weight: 600;
        }

        /* Section Headings */
        .section-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13.5px;
            font-weight: 800;
            color: var(--color-primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 24px 0 14px 0;
        }

        /* Innovation Highlights Grid (8 Items) */
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 24px;
            text-align: left;
        }

        .feature-tile {
            background-color: var(--bg-darker);
            border-radius: 16px;
            padding: 14px 14px;
            box-shadow: var(--neumorph-inset);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            transition: all 0.2s ease;
        }

        .feature-tile:hover {
            transform: translateY(-2px);
            background-color: #ffffff;
            box-shadow: 0 6px 14px rgba(13, 44, 84, 0.06);
        }

        .feature-icon-box {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
            background: #ffffff;
            box-shadow: 2px 2px 6px rgba(13, 44, 84, 0.08);
        }

        .feature-tile-content h4 {
            margin: 0 0 3px 0;
            font-size: 13.5px;
            font-weight: 800;
            color: var(--color-primary);
            line-height: 1.3;
        }

        .feature-tile-content p {
            margin: 0;
            font-size: 11.5px;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        /* Specs Grid */
        .specs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 22px;
            text-align: left;
        }

        .spec-item {
            background-color: var(--bg-darker);
            border-radius: 16px;
            padding: 12px 14px;
            box-shadow: var(--neumorph-inset);
        }

        .spec-label {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .spec-val {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.3;
        }

        /* Developer Card */
        .dev-card {
            background: linear-gradient(145deg, #f8faff, #eef3fb);
            border-radius: 18px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
            box-shadow: var(--neumorph-flat);
            text-align: left;
            border: 1px solid rgba(255, 255, 255, 0.9);
        }

        .dev-avatar {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            object-fit: cover;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 10px rgba(13, 44, 84, 0.15);
            flex-shrink: 0;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .dev-avatar:hover {
            transform: scale(1.1);
        }

        .dev-info {
            flex-grow: 1;
            min-width: 0;
        }

        .dev-name-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 2px;
        }

        .dev-name {
            font-size: 14.5px;
            font-weight: 800;
            color: var(--color-primary);
        }

        .verified-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 21px;
            height: 21px;
            background: var(--bg-card, #ffffff);
            border-radius: 50%;
            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.08), -2px -2px 5px rgba(255, 255, 255, 0.9), inset 1px 1px 1px rgba(255, 255, 255, 0.8);
            cursor: help;
            vertical-align: middle;
            margin-left: 4px;
            padding: 2px;
            box-sizing: border-box;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            flex-shrink: 0;
        }

        .verified-badge:hover {
            transform: scale(1.2);
            box-shadow: 0 0 10px rgba(114, 205, 39, 0.45);
        }

        .verified-badge svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        [data-theme="dark"] .verified-badge {
            background: #1e293b;
            box-shadow: 2px 2px 6px rgba(0, 0, 0, 0.5), -1px -1px 3px rgba(255, 255, 255, 0.05);
        }

        [data-theme="dark"] .verified-badge svg circle,
        [data-theme="dark"] .verified-badge svg path {
            stroke: #84cc16;
        }

        .dev-role {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 600;
            line-height: 1.35;
        }

        /* Changelog Section */
        .changelog-box {
            background-color: var(--bg-darker);
            border-radius: 18px;
            padding: 18px;
            margin-bottom: 24px;
            box-shadow: var(--neumorph-inset);
            text-align: left;
        }

        .changelog-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .changelog-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            line-height: 1.45;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(13, 44, 84, 0.04);
        }

        .changelog-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .log-tag {
            font-size: 10.5px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 6px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .tag-feature { background-color: rgba(0, 168, 120, 0.12); color: #059669; }
        .tag-fix { background-color: rgba(245, 158, 11, 0.12); color: #d97706; }
        .tag-docs { background-color: rgba(59, 130, 246, 0.12); color: #2563eb; }
        .tag-security { background-color: rgba(239, 68, 68, 0.12); color: #dc2626; }

        .log-title {
            color: var(--text-primary);
            font-weight: 700;
            flex-grow: 1;
            min-width: 0;
        }

        .log-time {
            color: var(--text-muted);
            font-size: 11px;
            flex-shrink: 0;
            font-weight: 500;
        }

        /* Action Buttons */
        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            height: 48px;
            border: none;
            background: linear-gradient(135deg, #0D2C54, #1A3E6D);
            color: #ffffff;
            border-radius: 16px;
            font-size: 15px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(13, 44, 84, 0.25);
            transition: all 0.2s ease;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(13, 44, 84, 0.35);
        }

        .btn-back:active {
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
            max-width: 480px;
            height: auto;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            background-color: #ffffff;
            padding: 10px;
            box-sizing: border-box;
        }

        .modal.show .modal-content {
            transform: scale(1);
        }

        .close-btn {
            position: absolute;
            top: -45px;
            right: 0;
            color: #ffffff;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            outline: none;
        }

        @media (max-width: 560px) {
            .about-card {
                padding: 24px 18px;
            }

            .fasttrack-pipeline {
                grid-template-columns: 1fr;
                gap: 6px;
            }

            .feature-grid, .specs-grid {
                grid-template-columns: 1fr;
            }

            .about-header {
                gap: 14px;
            }

            .about-logo-badge {
                width: 60px;
                height: 60px;
            }

            .about-title {
                font-size: 20px;
            }
        }
    </style>
</head>

<body>

    <div class="about-container">
        <div class="about-card">
            
            <!-- Compact & Modern Header -->
            <div class="about-header">
                <div class="about-logo-badge" onclick="openModal('assets/aboutus.png')" title="คลิกเพื่อดูรูปภาพขนาดใหญ่">
                    <img src="assets/aboutus.png" alt="NCDs Portal Logo" class="about-logo-img">
                </div>
                <div class="about-header-text">
                    <div class="about-title-row">
                        <h1 class="about-title">NCDs Portal</h1>
                        <span class="version-badge">v2.0 Stable</span>
                    </div>
                    <div class="about-desc">ระบบคัดกรอง ดูแล และจัดการโรคไม่ติดต่อเรื้อรัง</div>
                    <div class="org-badge">
                        🏥 สาธารณสุขอำเภอ<?= DISTRICT_NAME ?> • จังหวัด<?= PROVINCE_NAME ?>
                    </div>
                </div>
            </div>

            <!-- Standout Feature Hero: End-to-End Emergency Fast-Track & Referral Hub -->
            <div class="fasttrack-hero-card">
                <div class="fasttrack-hero-header">
                    <span style="font-size: 20px;">🚨</span>
                    <h3 class="fasttrack-hero-title">ระบบแจ้งเหตุวิกฤต Fast-Track & ส่งต่อโรงพยาบาล 2 ทาง</h3>
                    <span class="fasttrack-hero-tag">Real-time</span>
                </div>
                <p class="fasttrack-hero-desc">
                    เชื่อมโยงการทำงานแบบไร้รอยต่อระหว่าง <strong>อสม. ในชุมชน</strong>, <strong>โต๊ะพยาบาล รพ.สต.</strong> และ <strong>ห้องฉุกเฉิน รพ.ตาลสุม</strong> เพื่อความปลอดภัยสูงสุดของผู้ป่วยวิกฤต
                </p>
                <div class="fasttrack-pipeline">
                    <div class="pipeline-step">
                        <span class="pipeline-step-badge">1. ชุมชน (อสม.)</span>
                        <p class="pipeline-step-text">ยิงสัญญาณฉุกเฉินด่วน เมื่อพบสัญญาณชีพวิกฤต</p>
                    </div>
                    <div class="pipeline-step">
                        <span class="pipeline-step-badge">2. รพ.สต. (ไซเรน)</span>
                        <p class="pipeline-step-text">โปรแกรมไซเรนเตือนโต๊ะพยาบาล รับเรื่องทันที 24 ชม.</p>
                    </div>
                    <div class="pipeline-step">
                        <span class="pipeline-step-badge">3. รพ.ตาลสุม (Refer)</span>
                        <p class="pipeline-step-text">สั่งส่งต่อ Fast-Track พร้อมเลข Refer & GPS นำทาง</p>
                    </div>
                </div>
            </div>

            <!-- 8 Key Innovations Suite Grid -->
            <div class="section-label">
                <span>✨ 8 นวัตกรรมดิจิทัลสาธารณสุขครบวงจร</span>
                <span style="font-size: 11px; color: var(--color-accent); font-weight: 800;">Smart NCDs Suite</span>
            </div>

            <div class="feature-grid">
                <!-- 1. Voice Coach -->
                <div class="feature-tile">
                    <div class="feature-icon-box" style="color: #00A878;">🎙️</div>
                    <div class="feature-tile-content">
                        <h4>Clinical Voice Coach</h4>
                        <p>ระบบเสียงคุณหมอพากย์ไทย สรุปผลตรวจ 4 ด้านและคำแนะนำดูแลสุขภาพ</p>
                    </div>
                </div>

                <!-- 2. 3D Claymorphism -->
                <div class="feature-tile">
                    <div class="feature-icon-box" style="color: #3B82F6;">🌱</div>
                    <div class="feature-tile-content">
                        <h4>3D Claymorphism Self-Screening</h4>
                        <p>ประเมินสุขภาพ 1 นาที ไร้การเลื่อนจอ พร้อมสรุปวิธีลดความดัน-น้ำตาล & แชร์ LINE</p>
                    </div>
                </div>

                <!-- 3. Advanced Analytics & R2R -->
                <div class="feature-tile">
                    <div class="feature-icon-box" style="color: #8B5CF6;">📊</div>
                    <div class="feature-tile-content">
                        <h4>6-Dimension Analytics & R2R</h4>
                        <p>แดชบอร์ดสุขภาพ 6 มิติ วิเคราะห์ความชุกรายพื้นที่ โมเดลพยากรณ์ และชุดข้อมูลวิจัย</p>
                    </div>
                </div>

                <!-- 4. PWA Offline -->
                <div class="feature-tile">
                    <div class="feature-icon-box" style="color: #F59E0B;">📲</div>
                    <div class="feature-tile-content">
                        <h4>PWA & Offline-First Ready</h4>
                        <p>ติดตั้งลงมือถือ คัดกรองและบันทึกงานได้ 100% แม้ไม่มีเน็ต พร้อม Auto-Sync</p>
                    </div>
                </div>

                <!-- 5. QR Code Wallet Cards -->
                <div class="feature-tile">
                    <div class="feature-icon-box" style="color: #0D2C54;">📷</div>
                    <div class="feature-tile-content">
                        <h4>QR Code Wallet Card & Scanner</h4>
                        <p>สแกนกล้องดึงข้อมูลกลุ่มเป้าหมายใน 0.5 วินาที พร้อมระบบพิมพ์การ์ด 12 ใบ/A4</p>
                    </div>
                </div>

                <!-- 6. DPAC Tracking & Retention -->
                <div class="feature-tile">
                    <div class="feature-icon-box" style="color: #EF4444;">❤️</div>
                    <div class="feature-tile-content">
                        <h4>Smart DPAC & Dropout Alarm</h4>
                        <p>ติดตามกลุ่มเสี่ยงปรับพฤติกรรม 3อ. 2ส. 1น. ต่อเนื่อง พร้อมระบบเตือนเคสหลุดติดตาม</p>
                    </div>
                </div>

                <!-- 7. Gamification Leaderboard -->
                <div class="feature-tile">
                    <div class="feature-icon-box" style="color: #EAB308;">🏆</div>
                    <div class="feature-tile-content">
                        <h4>Gamification & Leaderboards</h4>
                        <p>กระดานจัดอันดับผลงาน อสม. ระดับหมู่บ้าน ตำบล อำเภอ และเหรียญรางวัลเกียรติยศ</p>
                    </div>
                </div>

                <!-- 8. Broadcast Hub & Notifications -->
                <div class="feature-tile">
                    <div class="feature-icon-box" style="color: #EC4899;">📢</div>
                    <div class="feature-tile-content">
                        <h4>ศูนย์ข้อความ & แจ้งเตือนประกาศ</h4>
                        <p>สื่อสารข่าวสารสุขภาพ นโยบายเร่งด่วน และประกาศสำคัญถึง อสม. และเจ้าหน้าที่ทันที</p>
                    </div>
                </div>
            </div>

            <!-- System Specs -->
            <div class="specs-grid">
                <div class="spec-item">
                    <div class="spec-label">รหัสการพัฒนา (Build)</div>
                    <div class="spec-val"><?= htmlspecialchars($build_number) ?></div>
                </div>
                <div class="spec-item">
                    <div class="spec-label">อัปเดตล่าสุด</div>
                    <div class="spec-val"><?= htmlspecialchars($last_update_str) ?></div>
                </div>
            </div>

            <!-- Developer Profile Card -->
            <div class="dev-card">
                <img src="assets/developer.jpg" alt="นายบุญธรรม พันธ์ใหญ่" class="dev-avatar" onclick="openModal('assets/developer.jpg')" title="คลิกเพื่อดูรูปภาพขนาดใหญ่">
                <div class="dev-info">
                    <div class="dev-name-row">
                        <span class="dev-name">นายบุญธรรม พันธ์ใหญ่</span>
                        <span class="verified-badge" title="ผู้พัฒนาระบบที่ได้รับการรับรอง (Verified Developer)">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="9" stroke="#72cd27" stroke-width="2.3" stroke-linecap="round" stroke-dasharray="41 5 4 5" transform="rotate(-30 12 12)" />
                                <path d="M8.5 12.2l2.3 2.3 5.2-5.5" stroke="#72cd27" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </div>
                    <div class="dev-role">
                        <span style="display: inline-block; background: rgba(13, 44, 84, 0.08); color: var(--color-primary); padding: 2px 8px; border-radius: 6px; font-weight: 800; font-size: 11.5px; margin-bottom: 4px;">Solo Developer</span><br>
                        นักวิชาการคอมพิวเตอร์ • สำนักงานสาธารณสุขอำเภอ<?= DISTRICT_NAME ?>
                    </div>
                </div>
            </div>

            <!-- Changelog Section -->
            <div class="changelog-box">
                <div class="section-label" style="margin-top: 0; margin-bottom: 12px;">
                    <span>📜 บันทึกการปรับปรุงระบบล่าสุด</span>
                    <span style="font-size: 11px; color: var(--color-accent); font-weight: 800;">Changelog</span>
                </div>
                <div class="changelog-list">
                    <?php
                    $system_updates = get_system_changelog();
                    foreach ($system_updates as $update):
                        $tag_class = 'tag-feature';
                        $tag_label = 'ฟีเจอร์';
                        if ($update['type'] === 'fix') {
                            $tag_class = 'tag-fix';
                            $tag_label = 'ปรับปรุง';
                        } elseif ($update['type'] === 'docs') {
                            $tag_class = 'tag-docs';
                            $tag_label = 'คู่มือ';
                        } elseif ($update['type'] === 'security') {
                            $tag_class = 'tag-security';
                            $tag_label = 'ความปลอดภัย';
                        }
                    ?>
                        <div class="changelog-row">
                            <span class="log-tag <?= $tag_class ?>"><?= $tag_label ?></span>
                            <span class="log-title"><?= htmlspecialchars($update['title']) ?></span>
                            <span class="log-time"><?= get_time_diff_display($update['timestamp']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Public Open Data Link -->
            <div style="margin-top: 14px; margin-bottom: 10px;">
                <a href="public_dashboard.php" style="display: flex; align-items: center; justify-content: center; gap: 8px; background: linear-gradient(135deg, rgba(2, 132, 199, 0.1), rgba(14, 165, 233, 0.1)); border: 1.5px solid rgba(2, 132, 199, 0.3); padding: 12px; border-radius: 16px; color: var(--color-accent); font-weight: 800; font-size: 13.5px; text-decoration: none; transition: all 0.2s ease;">
                    <span>📊 เข้าสู่ศูนย์ข้อมูลสถิติสุขภาพ NCDs (Open Data Cockpit)</span>
                </a>
            </div>

            <!-- Back Button -->
            <?php
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
                <span>ย้อนกลับสู่ระบบ</span>
            </a>

        </div>
    </div>

    <!-- Modal for Image Preview -->
    <div id="imageModal" class="modal" onclick="closeModal(event)">
        <div class="modal-content-wrapper" onclick="event.stopPropagation()">
            <button class="close-btn" onclick="closeModal(event)">&times;</button>
            <img id="modalImage" class="modal-content" src="assets/aboutus.png" alt="Enlarged View">
        </div>
    </div>

    <script>
        const modal = document.getElementById('imageModal');
        const modalImage = document.getElementById('modalImage');

        function openModal(imgSrc) {
            if (imgSrc) {
                modalImage.src = imgSrc;
            }
            modal.style.display = 'flex';
            modal.offsetHeight;
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(event) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }, 300);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('show')) {
                closeModal();
            }
        });
    </script>
    <script src="assets/js/app.js"></script>
</body>

</html>