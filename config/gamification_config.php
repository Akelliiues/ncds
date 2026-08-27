<?php
// config/gamification_config.php - ระบบจัดการกระดานคะแนน ฉายาเกียรติยศ และเงื่อนไขแต้มสะสม
// รองรับการปรับแต่งอิสระ พร้อมระบบสำรองและคืนค่าเริ่มต้น (Factory Defaults Restore)

require_once __DIR__ . '/db.php';

if (!function_exists('get_default_gamification_config')) {
    function get_default_gamification_config() {
        $district = defined('DISTRICT_NAME') ? DISTRICT_NAME : 'ตาลสุม';
        return [
            // 1. ฉายา อสม. ระดับสูงสุด Top 1 - 5
            'top5_titles' => [
                1 => '👑 สุดยอดขุนพลสาธารณสุข' . $district,
                2 => '⭐ ยอดอัศวินสุขภาพชุมชน',
                3 => '🏆 ดาวรุ่งแห่งความห่วงใย',
                4 => '🥇 ผู้พิทักษ์หัวใจไร้โรค',
                5 => '🌟 ขวัญใจสุขภาพดีถ้วนหน้า'
            ],
            // 2. ฉายา อสม. กลุ่มอันดับ 6 - 50 (แบ่งกลุ่มละ 5 อันดับ)
            'tier_titles' => [
                1 => '💎 ยอดนักปราบเบาหวานและความดัน',
                2 => '🌿 ผู้ปกป้องสุขภาวะ' . $district,
                3 => '🎖️ เสาหลักสุขภาพดีชุมชน',
                4 => '🏅 ผู้หว่านเมล็ดพันธุ์สุขภาพ',
                5 => '📜 พลังขับเคลื่อนตำบลสุขภาพดี',
                6 => '🌟 ผู้จุดประกายรักตนเอง',
                7 => '🏷️ ทูตสุขภาพสร้างพลังบวก',
                8 => '🛡️ ปราชญ์สุขภาพคู่บ้านคู่เมือง',
                9 => '✨ แสงสว่างนำทางชีวิตชีวา'
            ],
            // คำสร้อยต่อท้ายอันดับ 6-50
            'tier_suffixes' => [
                0 => 'ชั้นเอก',
                1 => 'ชั้นโท',
                2 => 'ชั้นตรี',
                3 => 'ชั้นจัตวา',
                4 => 'ชั้นเบญจ'
            ],
            // 3. ฉายาและสโลแกนประจำ รพ.สต.
            'hospital_titles' => [
                '03750' => '🏥 ศูนย์เชี่ยวชาญดูแลหัวใจและเมตาบอลิก',
                '03751' => '🛡️ ค่ายพิทักษ์สุขภาพเบาหวาน 100%',
                '03752' => '⚡ ปราการเหล็กปราบความดันโลหิตสูง',
                '03753' => '🌟 เสาหลักสุขภาวะชุมชนเข้มแข็ง',
                '03754' => '🎖️ กองพันสุขภาพดีวิถีตาลสุม',
                '03755' => '🌿 ฐานพลังชีวีปลอดโรคไม่ติดต่อ',
                '03756' => '🥇 สุดยอดหน่วยคัดกรองครอบคลุมยอดเยี่ยม',
                '03757' => '✨ ประภาคารนำทางสุขภาพดีถ้วนหน้า'
            ],
            // 4. กฎการคำนวณแต้มสะสม
            'scoring_rules' => [
                'mode' => 'progressive', // 'progressive' (Round N = N pts) or 'custom'
                'round_points' => [
                    1 => 1.00,
                    2 => 2.00,
                    3 => 3.00,
                    4 => 4.00,
                    5 => 5.00
                ],
                'dpac_points' => 1.00
            ],
            // 5. การควบคุมการแสดงผลหน้าบ้าน
            'display_settings' => [
                'show_vhv_titles' => true,
                'show_hospital_titles' => true,
                'top_board_limit' => 50
            ]
        ];
    }
}

if (!function_exists('get_gamification_config')) {
    function get_gamification_config() {
        $defaults = get_default_gamification_config();
        $storedJson = get_system_setting('gamification_settings', null);

        if (!$storedJson) {
            return $defaults;
        }

        $custom = json_decode($storedJson, true);
        if (!is_array($custom)) {
            return $defaults;
        }

        // Deep merge with defaults to ensure all keys exist
        $merged = $defaults;
        if (!empty($custom['top5_titles']) && is_array($custom['top5_titles'])) {
            foreach ($custom['top5_titles'] as $k => $v) {
                if (!empty(trim($v))) {
                    $merged['top5_titles'][(int)$k] = trim($v);
                }
            }
        }

        if (!empty($custom['tier_titles']) && is_array($custom['tier_titles'])) {
            foreach ($custom['tier_titles'] as $k => $v) {
                if (!empty(trim($v))) {
                    $merged['tier_titles'][(int)$k] = trim($v);
                }
            }
        }

        if (!empty($custom['hospital_titles']) && is_array($custom['hospital_titles'])) {
            foreach ($custom['hospital_titles'] as $k => $v) {
                if (!empty(trim($v))) {
                    $merged['hospital_titles'][(string)$k] = trim($v);
                }
            }
        }

        if (!empty($custom['scoring_rules']) && is_array($custom['scoring_rules'])) {
            if (!empty($custom['scoring_rules']['mode'])) {
                $merged['scoring_rules']['mode'] = $custom['scoring_rules']['mode'];
            }
            if (!empty($custom['scoring_rules']['round_points']) && is_array($custom['scoring_rules']['round_points'])) {
                foreach ($custom['scoring_rules']['round_points'] as $r => $pts) {
                    $merged['scoring_rules']['round_points'][(int)$r] = max(0.25, (float)$pts);
                }
            }
            if (isset($custom['scoring_rules']['dpac_points'])) {
                $merged['scoring_rules']['dpac_points'] = max(0.25, (float)$custom['scoring_rules']['dpac_points']);
            }
        }

        if (!empty($custom['display_settings']) && is_array($custom['display_settings'])) {
            if (isset($custom['display_settings']['show_vhv_titles'])) {
                $merged['display_settings']['show_vhv_titles'] = (bool)$custom['display_settings']['show_vhv_titles'];
            }
            if (isset($custom['display_settings']['show_hospital_titles'])) {
                $merged['display_settings']['show_hospital_titles'] = (bool)$custom['display_settings']['show_hospital_titles'];
            }
            if (isset($custom['display_settings']['top_board_limit'])) {
                $merged['display_settings']['top_board_limit'] = (int)$custom['display_settings']['top_board_limit'];
            }
        }

        return $merged;
    }
}

if (!function_exists('save_gamification_config')) {
    function save_gamification_config($newConfig) {
        global $pdo;
        if (!isset($pdo)) return false;

        $jsonStr = json_encode($newConfig, JSON_UNESCAPED_UNICODE);
        try {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, description)
                VALUES ('gamification_settings', ?, 'การตั้งค่ากระดานคะแนน ฉายาเกียรติยศ และเงื่อนไขแต้มสะสม')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
            ");
            return $stmt->execute([$jsonStr]);
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('reset_gamification_config')) {
    function reset_gamification_config($section = 'all') {
        global $pdo;
        if (!isset($pdo)) return false;

        $defaults = get_default_gamification_config();
        if ($section === 'all') {
            return save_gamification_config($defaults);
        }

        $current = get_gamification_config();
        if (isset($defaults[$section])) {
            $current[$section] = $defaults[$section];
            return save_gamification_config($current);
        }
        return false;
    }
}

if (!function_exists('get_active_vhv_title')) {
    function get_active_vhv_title($rank) {
        $rank = (int)$rank;
        if ($rank <= 0 || $rank > 50) return '';

        $config = get_gamification_config();
        if (!($config['display_settings']['show_vhv_titles'] ?? true)) {
            return '';
        }

        // Top 1-5
        if ($rank >= 1 && $rank <= 5) {
            return $config['top5_titles'][$rank] ?? '';
        }

        // Tier 6-50
        $groupIndex = floor(($rank - 6) / 5) + 1;
        $suffixIndex = ($rank - 6) % 5;

        $base = $config['tier_titles'][$groupIndex] ?? '';
        $suffix = $config['tier_suffixes'][$suffixIndex] ?? '';

        if ($base && $suffix) {
            return $base . ' ' . $suffix;
        }
        return $base;
    }
}

if (!function_exists('get_active_hospital_title')) {
    function get_active_hospital_title($hoscode) {
        $hoscode = (string)$hoscode;
        $config = get_gamification_config();
        if (!($config['display_settings']['show_hospital_titles'] ?? true)) {
            return '';
        }

        return $config['hospital_titles'][$hoscode] ?? '';
    }
}
