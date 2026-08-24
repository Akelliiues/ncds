<?php
// config/rank_icons.php - คลังไอคอนอันดับเกียรติยศ อสม. ครบทั้ง 50 อันดับ (Exact 50 Rank Icons System)
// ตรงตามชุดสัญลักษณ์ Gamification Master Icons 100% พร้อมโทนสีจานและเงา Neumorphic 3D

if (!function_exists('get_50_rank_icon_def')) {
    function get_50_rank_icon_def($rank) {
        $rank = (int)$rank;
        if ($rank < 1) $rank = 51;
        if ($rank > 50) $rank = 51;

        $rankMap = [
            // 1. Imperial Crown on Dark Teal Disc
            1 => [
                'name' => '👑 สุดยอดขุนพลสาธารณสุข',
                'discClass' => 'disc-gold-radiant',
                'svg' => '<path fill="#fbbf24" d="M3 18.5h18v2.5H3v-2.5zm1.8-3.5l2.4-8.5 4.8 4.8 4-6.8 4 6.8 4.8-4.8 2.4 8.5H4.8z"/>
                          <circle cx="12" cy="4" r="1.6" fill="#fef08a"/>
                          <circle cx="4.2" cy="6" r="1.4" fill="#fef08a"/>
                          <circle cx="19.8" cy="6" r="1.4" fill="#fef08a"/>
                          <circle cx="12" cy="14.5" r="1.8" fill="#10B981"/>
                          <circle cx="8" cy="15" r="1.2" fill="#EF4444"/>
                          <circle cx="16" cy="15" r="1.2" fill="#3B82F6"/>'
            ],
            // 2. Star Trophy on Red Disc
            2 => [
                'name' => '⭐ ยอดอัศวินสุขภาพชุมชน',
                'discClass' => 'disc-ruby-gold',
                'svg' => '<path fill="#fbbf24" d="M12 2l2.4 5.2 5.6.5-4.2 3.8 1.3 5.5L12 14.2l-5.1 2.8 1.3-5.5-4.2-3.8 5.6-.5L12 2z"/>
                          <path fill="#fef08a" d="M8 18h8v2H8v-2zm-2 2.8h12V23H6v-2.2z"/>'
            ],
            // 3. 1-2-3 Podium on Yellow/Amber Disc
            3 => [
                'name' => '🏆 ดาวรุ่งแห่งความห่วงใย',
                'discClass' => 'disc-golden',
                'svg' => '<path fill="#ef4444" d="M9 7h6v14H9V7zM3 11h6v10H3V11zm12 3h6v7h-6v-7z"/>
                          <circle cx="12" cy="10.5" r="2" fill="#ffffff"/><text x="12" y="12" font-size="3" font-weight="bold" text-anchor="middle" fill="#ef4444">1</text>
                          <circle cx="6" cy="14.5" r="2" fill="#ffffff"/><text x="6" y="16" font-size="3" font-weight="bold" text-anchor="middle" fill="#ef4444">2</text>
                          <circle cx="18" cy="16.5" r="2" fill="#ffffff"/><text x="18" y="18" font-size="3" font-weight="bold" text-anchor="middle" fill="#ef4444">3</text>'
            ],
            // 4. Rosette Ribbon Medal #1 on Teal Disc
            4 => [
                'name' => '🥇 ผู้พิทักษ์หัวใจไร้โรค',
                'discClass' => 'disc-teal',
                'svg' => '<path fill="#ef4444" d="M8 14l-3 7 5-2 2 3 1-8H8zm8 0l3 7-5-2-2 3-1-8h5z"/>
                          <circle cx="12" cy="9.5" r="6" fill="#fbbf24"/>
                          <circle cx="12" cy="9.5" r="4.5" fill="#fef08a"/>
                          <text x="12" y="12" font-size="7" font-weight="900" text-anchor="middle" fill="#b45309">1</text>'
            ],
            // 5. Neck Ribbon Gold Medal #1 on Navy Disc
            5 => [
                'name' => '🌟 ขวัญใจสุขภาพดีถ้วนหน้า',
                'discClass' => 'disc-navy-gold',
                'svg' => '<path fill="#ef4444" d="M6 3l4 6h4l4-6h-3l-3 4-3-4H6z"/><path fill="#ffffff" d="M9 3l3 4 3-4h-1.5l-1.5 2-1.5-2H9z"/>
                          <circle cx="12" cy="15" r="6" fill="#fbbf24"/>
                          <circle cx="12" cy="15" r="4.5" fill="#fef08a"/>
                          <text x="12" y="17.5" font-size="7" font-weight="900" text-anchor="middle" fill="#b45309">1</text>'
            ],
            // 6. Certificate Scroll with Seal on Teal Disc
            6 => [
                'name' => '📜 ยอดนักปราบเบาหวาน ชั้นเอก',
                'discClass' => 'disc-teal',
                'svg' => '<path fill="#ffffff" d="M19 4H7a3 3 0 00-3 3v11a3 3 0 003 3h12a2 2 0 002-2V6a2 2 0 00-2-2z"/>
                          <path fill="#e2e8f0" d="M7 6h12v12H7a1 1 0 01-1-1V7a1 1 0 011-1z"/>
                          <path fill="#94a3b8" d="M9 8h8v1.5H9V8zm0 3h8v1.5H9V11zm0 3h5v1.5H9V14z"/>
                          <circle cx="16" cy="16" r="2.5" fill="#ef4444"/>
                          <path fill="#ef4444" d="M15 17l-1.5 4 2.5-1 2.5 1-1.5-4h-2z"/>'
            ],
            // 7. Star Medal on Tri-color Ribbon on Red Disc
            7 => [
                'name' => '⭐ ยอดนักปราบเบาหวาน ชั้นโท',
                'discClass' => 'disc-ruby-gold',
                'svg' => '<path fill="#ffffff" d="M6 2h12v6H6V2z"/>
                          <path fill="#3b82f6" d="M6 2h4v6H6V2zm8 0h4v6h-4V2z"/>
                          <path fill="#ef4444" d="M10 2h4v6h-4V2z"/>
                          <circle cx="12" cy="15" r="6" fill="#fbbf24"/>
                          <path fill="#fef08a" d="M12 11l1 2.5 2.5.2-2 1.8.6 2.5-2.1-1.3-2.1 1.3.6-2.5-2-1.8 2.5-.2L12 11z"/>'
            ],
            // 8. #1 Victory Hand Foam Glove on Navy Disc
            8 => [
                'name' => '☝️ ยอดนักปราบเบาหวาน ชั้นตรี',
                'discClass' => 'disc-navy-gold',
                'svg' => '<path fill="#ef4444" d="M10 3h3v8h2v2h1v4a3 3 0 01-3 3H9a3 3 0 01-3-3v-6a2 2 0 012-2h2V3z"/>
                          <path fill="#ffffff" d="M11 5h1v6h-1V5z"/>
                          <text x="11.5" y="17" font-size="4.5" font-weight="900" text-anchor="middle" fill="#ffffff">#1</text>'
            ],
            // 9. Golden Star Envelope on Yellow Disc
            9 => [
                'name' => '✉️ ยอดนักปราบเบาหวาน ชั้นจัตวา',
                'discClass' => 'disc-golden',
                'svg' => '<path fill="#f97316" d="M4 6h16a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2z"/>
                          <path fill="#ffffff" d="M4 6l8 6 8-6H4z"/>
                          <path fill="#fbbf24" d="M12 9l1 2.2 2.2.2-1.7 1.5.5 2.1-2-1.2-2 1.2.5-2.1-1.7-1.5 2.2-.2L12 9z"/>'
            ],
            // 10. Silver Medal #2 on Mint Disc
            10 => [
                'name' => '🥈 ยอดนักปราบเบาหวาน ชั้นเบญจ',
                'discClass' => 'disc-teal',
                'svg' => '<path fill="#38bdf8" d="M6 3l4 6h4l4-6h-3l-3 4-3-4H6z"/><path fill="#ffffff" d="M9 3l3 4 3-4h-1.5l-1.5 2-1.5-2H9z"/>
                          <circle cx="12" cy="15" r="6" fill="#cbd5e1"/>
                          <circle cx="12" cy="15" r="4.5" fill="#f1f5f9"/>
                          <text x="12" y="17.5" font-size="7" font-weight="900" text-anchor="middle" fill="#475569">2</text>'
            ],
            // 11. Bronze Medal #3 on Amber/Red Disc
            11 => [
                'name' => '🥉 ผู้ปกป้องสุขภาวะ ชั้นเอก',
                'discClass' => 'disc-amber',
                'svg' => '<path fill="#ef4444" d="M6 3l4 6h4l4-6h-3l-3 4-3-4H6z"/><path fill="#ffffff" d="M9 3l3 4 3-4h-1.5l-1.5 2-1.5-2H9z"/>
                          <circle cx="12" cy="15" r="6" fill="#d97706"/>
                          <circle cx="12" cy="15" r="4.5" fill="#fde68a"/>
                          <text x="12" y="17.5" font-size="7" font-weight="900" text-anchor="middle" fill="#78350f">3</text>'
            ],
            // 12. Classic Gold Trophy Cup on Navy Disc
            12 => [
                'name' => '🏆 ผู้ปกป้องสุขภาวะ ชั้นโท',
                'discClass' => 'disc-navy-gold',
                'svg' => '<path fill="#fbbf24" d="M19 4h-2V3a1 1 0 00-1-1H8a1 1 0 00-1 1v1H5a3 3 0 00-3 3v2a4 4 0 004 4h.4a6 6 0 005.6 4.9V19H8a1 1 0 000 2h8a1 1 0 000-2h-4v-2.1a6 6 0 005.6-4.9H18a4 4 0 004-4V7a3 3 0 00-3-3zM4 9V7a1 1 0 011-1h2v4.8A2 2 0 014 9zm16 0a2 2 0 01-2 1.8V6h2a1 1 0 011 1v2z"/>'
            ],
            // 13. Brilliant Diamond on Teal Disc
            13 => [
                'name' => '💎 ผู้ปกป้องสุขภาวะ ชั้นตรี',
                'discClass' => 'disc-diamond',
                'svg' => '<path fill="#38bdf8" d="M6 4L2 9.5l10 11.5 10-11.5L18 4H6z"/>
                          <path fill="#e0f2fe" d="M12 5.5l2.6 3.5H9.4L12 5.5zM7.2 5.5h2.6L7.4 9 4.2 5.5h3zm9.6 0h3l-3.2 3.5-2.4-3.5h2.6z"/>
                          <path fill="#0284c7" d="M4.3 10.5l4.6 7.2-5.4-7.2h.8zm15.4 0l-5.4 7.2 4.6-7.2h.8zm-8.7 8.2l-4.2-7.2h10.4l-4.2 7.2-1 1.7-1-1.7z"/>'
            ],
            // 14. Military Honor Cross on Gold Disc
            14 => [
                'name' => '🎖️ ผู้ปกป้องสุขภาวะ ชั้นจัตวา',
                'discClass' => 'disc-golden',
                'svg' => '<path fill="#1e293b" d="M7 2h10v6H7V2z"/><path fill="#ef4444" d="M9 2h2v6H9V2zm4 0h2v6h-2V2z"/>
                          <path fill="#fbbf24" d="M12 8.5l2 3h3l-2.5 2.5 1 3.5-3.5-2-3.5 2 1-3.5L7 11.5h3l2-3z"/>
                          <circle cx="12" cy="14" r="2" fill="#fef08a"/>'
            ],
            // 15. Laurel Wreath on Red Disc
            15 => [
                'name' => '🌿 ผู้ปกป้องสุขภาวะ ชั้นเบญจ',
                'discClass' => 'disc-ruby-gold',
                'svg' => '<path fill="#fbbf24" d="M12 3.5l1.2 2.6 2.8.2-2.1 1.9.6 2.8-2.5-1.5-2.5 1.5.6-2.8-2.1-1.9 2.8-.2L12 3.5z"/>
                          <path fill="#fef08a" d="M7 9.5c-.8-1.5-2-2.5-3.5-3 .2 1.8 1.2 3.2 2.5 4 .2-.4.6-.7 1-1zm10 0c.8-1.5 2-2.5 3.5-3-.2 1.8-1.2 3.2-2.5 4-.2-.4-.6-.7-1-1zm-9 4c-1.2-1.2-2.8-1.8-4.5-1.8.8 1.8 2.2 3 3.8 3.5.2-.6.4-1.1.7-1.7zm8 0c1.2-1.2 2.8-1.8 4.5-1.8-.8 1.8-2.2 3-3.8 3.5-.2-.6-.4-1.1-.7-1.7zm-6.5 4.5c-1.5-.8-3.2-.8-4.8-.2 1.2 1.5 3 2.2 4.8 2.2 0-.7 0-1.4 0-2zm5 0c1.5-.8 3.2-.8 4.8-.2-1.2 1.5-3 2.2-4.8 2.2 0-.7 0-1.4 0-2z"/>'
            ],
            // 16. Golden Star Ticket on Yellow Disc
            16 => [
                'name' => '🎟️ เสาหลักสุขภาพดี ชั้นเอก',
                'discClass' => 'disc-golden',
                'svg' => '<path fill="#ffffff" d="M3 6a2 2 0 012-2h14a2 2 0 012 2v3a2 2 0 000 4v3a2 2 0 01-2 2H5a2 2 0 01-2-2v-3a2 2 0 000-4V6z"/>
                          <path fill="#f59e0b" d="M5 6h14v12H5V6zm7 2.5l1.2 2.7 2.8.3-2.1 1.9.6 2.8-2.5-1.5-2.5 1.5.6-2.8-2.1-1.9 2.8-.3L12 8.5z"/>'
            ],
            // 17. Framed Diploma on Green Disc
            17 => [
                'name' => '📜 เสาหลักสุขภาพดี ชั้นโท',
                'discClass' => 'disc-teal',
                'svg' => '<path fill="#fbbf24" d="M3 4a2 2 0 012-2h14a2 2 0 012 2v16a2 2 0 01-2 2H5a2 2 0 01-2-2V4z"/>
                          <path fill="#ffffff" d="M5 5h14v14H5V5zm3 3h8v1.5H8V8zm0 3h8v1.5H8V11zm0 3h5v1.5H8V14z"/>
                          <circle cx="16" cy="16" r="2" fill="#ef4444"/>'
            ],
            // 18. Star Rosette Ribbon on Navy Disc
            18 => [
                'name' => '🎖️ เสาหลักสุขภาพดี ชั้นตรี',
                'discClass' => 'disc-navy-gold',
                'svg' => '<path fill="#ef4444" d="M8 13.5l-3 7.5 5-2 2 3.5 1-9H8zm8 0l3 7.5-5-2-2 3.5-1-9h6z"/>
                          <circle cx="12" cy="9" r="6" fill="#fbbf24"/>
                          <path fill="#ffffff" d="M12 6.5l.8 1.8 1.8.2-1.4 1.2.4 1.8-1.6-1-1.6 1 .4-1.8-1.4-1.2 1.8-.2.8-1.8z"/>'
            ],
            // 19. Ascending Bar Chart with Arrow on Red Disc
            19 => [
                'name' => '📊 เสาหลักสุขภาพดี ชั้นจัตวา',
                'discClass' => 'disc-ruby-gold',
                'svg' => '<path fill="#ffffff" d="M4 19h16v2H4v-2zM6 14h3v4H6v-4zm5-5h3v9h-3V9zm5-5h3v14h-3V4z"/>
                          <path fill="#fef08a" d="M7 7l5-4 5 4h-3v3h-4V7H7z"/>'
            ],
            // 20. Protection Star Shield on Teal Disc
            20 => [
                'name' => '🛡️ เสาหลักสุขภาพดี ชั้นเบญจ',
                'discClass' => 'disc-teal',
                'svg' => '<path fill="#fbbf24" d="M12 2L4 5.5v5.8c0 5.4 3.4 10.4 8 11.7 4.6-1.3 8-6.3 8-11.7V5.5L12 2z"/>
                          <path fill="#0d9488" d="M12 4.5l6 2.8v4.5c0 4-2.5 7.8-6 9-3.5-1.2-6-5-6-9V7.3l6-2.8z"/>
                          <path fill="#fbbf24" d="M12 8l1 2.2 2.2.2-1.7 1.5.5 2.1-2-1.2-2 1.2.5-2.1-1.7-1.5 2.2-.2L12 8z"/>'
            ],
            // 21. Bullseye Target with Arrow on Navy Disc
            21 => [
                'name' => '🎯 ผู้หว่านเมล็ดพันธุ์ ชั้นเอก',
                'discClass' => 'disc-navy-gold',
                'svg' => '<circle cx="12" cy="12" r="9" fill="#ef4444"/><circle cx="12" cy="12" r="6.5" fill="#ffffff"/><circle cx="12" cy="12" r="4" fill="#ef4444"/><circle cx="12" cy="12" r="1.5" fill="#fbbf24"/>
                          <path d="M15 9l5-5m-2 0h2v2" stroke="#fbbf24" stroke-width="2" stroke-linecap="round"/>'
            ],
            // 22. Mountain Peak with Red Flag on Yellow Disc
            22 => [
                'name' => '🏔️ ผู้หว่านเมล็ดพันธุ์ ชั้นโท',
                'discClass' => 'disc-golden',
                'svg' => '<path fill="#334155" d="M12 5L3 20h18L12 5z"/>
                          <path fill="#e2e8f0" d="M12 5l-3 5 2 2 2-3 2 3 2-2-5-5z"/>
                          <path fill="#ef4444" d="M12 2v3h3l-1.5-1.5L15 2h-3z"/>'
            ],
            // 23. Cheering Champions on Podium on Teal Disc
            23 => [
                'name' => '👥 ผู้หว่านเมล็ดพันธุ์ ชั้นตรี',
                'discClass' => 'disc-teal',
                'svg' => '<path fill="#ffffff" d="M9 10h6v11H9V10zM3 13h6v8H3v-8zm12 3h6v5h-6v-5z"/>
                          <circle cx="12" cy="5" r="1.5" fill="#fbbf24"/><path d="M10 8l2-2 2 2v2h-4V8z" fill="#fbbf24"/>
                          <circle cx="6" cy="8" r="1.3" fill="#ffffff"/><path d="M4.5 11l1.5-1.5 1.5 1.5V13h-3v-2z" fill="#ffffff"/>
                          <circle cx="18" cy="11" r="1.3" fill="#ffffff"/><path d="M16.5 14l1.5-1.5 1.5 1.5V16h-3v-2z" fill="#ffffff"/>'
            ],
            // 24. Gold Star in Ring Badge on Navy Disc
            24 => [
                'name' => '⭐ ผู้หว่านเมล็ดพันธุ์ ชั้นจัตวา',
                'discClass' => 'disc-navy-gold',
                'svg' => '<circle cx="12" cy="12" r="9" fill="#1e293b" stroke="#fbbf24" stroke-width="2"/>
                          <path fill="#fbbf24" d="M12 6.5l1.6 3.4 3.7.4-2.7 2.6.7 3.7-3.3-1.8-3.3 1.8.7-3.7-2.7-2.6 3.7-.4L12 6.5z"/>'
            ],
            // 25. Championship Belt with Crown on Red Disc
            25 => [
                'name' => '🥋 ผู้หว่านเมล็ดพันธุ์ ชั้นเบญจ',
                'discClass' => 'disc-ruby-gold',
                'svg' => '<path fill="#1e293b" d="M2 10h20v4H2v-4z"/>
                          <circle cx="12" cy="12" r="6" fill="#fbbf24"/>
                          <path fill="#b45309" d="M9.5 13.5h5v1h-5v-1zm.5-3l1 2 1-1.5 1 1.5 1-2h-4z"/>'
            ],
            // 26. Thumbs Up Hand on Teal Disc
            26 => [
                'name' => '👍 พลังขับเคลื่อนตำบล ชั้นเอก',
                'discClass' => 'disc-teal',
                'svg' => '<path fill="#f59e0b" d="M9 10h2l1-4c0-1.5 1-2 2-2s1.5.5 1.5 2v4h4a2 2 0 012 2v1.5a2 2 0 01-.5 1.4l-2 3.5a2 2 0 01-1.7 1.1H10a2 2 0 01-2-2v-6a2 2 0 011-1.5z"/>
                          <path fill="#1e293b" d="M4 10h4v11H4V10z"/>'
            ],
            // 27. Ranked Checklist (1,2,3) on Navy Disc
            27 => [
                'name' => '📋 พลังขับเคลื่อนตำบล ชั้นโท',
                'discClass' => 'disc-navy-gold',
                'svg' => '<rect x="4" y="4" width="16" height="16" rx="3" fill="#ffffff"/>
                          <circle cx="8" cy="8" r="1.8" fill="#ef4444"/><rect x="11.5" y="7" width="6.5" height="2" rx="1" fill="#cbd5e1"/>
                          <circle cx="8" cy="12" r="1.8" fill="#f59e0b"/><rect x="11.5" y="11" width="6.5" height="2" rx="1" fill="#cbd5e1"/>
                          <circle cx="8" cy="16" r="1.8" fill="#10b981"/><rect x="11.5" y="15" width="6.5" height="2" rx="1" fill="#cbd5e1"/>'
            ],
            // 28. Star Ribbon Banner on Amber Disc
            28 => [
                'name' => '🚩 พลังขับเคลื่อนตำบล ชั้นตรี',
                'discClass' => 'disc-amber',
                'svg' => '<path fill="#ef4444" d="M6 3h12v18l-6-4-6 4V3z"/>
                          <path fill="#fbbf24" d="M12 6.5l1.2 2.6 2.8.3-2.1 1.8.6 2.8-2.5-1.5-2.5 1.5.6-2.8-2.1-1.8 2.8-.3L12 6.5z"/>'
            ],
            // 29. Star Trophy Cup on Mint Disc
            29 => [
                'name' => '🏆 พลังขับเคลื่อนตำบล ชั้นจัตวา',
                'discClass' => 'disc-teal',
                'svg' => '<path fill="#fbbf24" d="M17 5h-1V4a1 1 0 00-1-1H9a1 1 0 00-1 1v1H7a2 2 0 00-2 2v2a3 3 0 003 3h.5a5 5 0 004.5 3.9V18H10a1 1 0 000 2h4a1 1 0 000-2h-3v-2.1a5 5 0 004.5-3.9H16a3 3 0 003-3V7a2 2 0 00-2-2z"/>
                          <path fill="#ffffff" d="M12 7l.8 1.6 1.8.2-1.3 1.2.4 1.8-1.7-.9-1.7.9.4-1.8-1.3-1.2 1.8-.2.8-1.6z"/>'
            ],
            // 30. Crown Medal on Ribbon on Navy Disc
            30 => [
                'name' => '👑 พลังขับเคลื่อนตำบล ชั้นเบญจ',
                'discClass' => 'disc-navy-gold',
                'svg' => '<path fill="#ef4444" d="M6 3l4 6h4l4-6h-3l-3 4-3-4H6z"/><path fill="#ffffff" d="M9 3l3 4 3-4h-1.5l-1.5 2-1.5-2H9z"/>
                          <circle cx="12" cy="15" r="6" fill="#fbbf24"/>
                          <path fill="#b45309" d="M9.5 16.5h5v1h-5v-1zm.5-3l1 2 1-1.5 1 1.5 1-2h-4z"/>'
            ],
            // 31. Reward Gift Box on Red Disc
            31 => [
                'name' => '🎁 ผู้จุดประกายรักตนเอง ชั้นเอก',
                'discClass' => 'disc-ruby-gold',
                'svg' => '<rect x="4" y="9" width="16" height="11" rx="2" fill="#fbbf24"/>
                          <rect x="3" y="6" width="18" height="4" rx="1.5" fill="#fef08a"/>
                          <rect x="10.5" y="6" width="3" height="14" fill="#ef4444"/>
                          <path fill="#ef4444" d="M12 6c-1.5-2-3.5-2-3.5 0s2 2 3.5 1c1.5 1 3.5-1 3.5-1s-2-2-3.5 0z"/>'
            ],
            // 32. Ascending Trend Bars on Teal Disc
            32 => [
                'name' => '📈 ผู้จุดประกายรักตนเอง ชั้นโท',
                'discClass' => 'disc-teal',
                'svg' => '<path fill="#10b981" d="M4 15h3v5H4v-5zm5-4h3v9H9v-9zm5-4h3v13h-3V7zm5-4h3v17h-3V3z"/>
                          <path d="M4 12l6-5 5 3 6-7m-3 0h3v3" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" fill="none"/>'
            ],
            // 33. Blasting Rocket on Navy Disc
            33 => [
                'name' => '🚀 ผู้จุดประกายรักตนเอง ชั้นตรี',
                'discClass' => 'disc-navy-gold',
                'svg' => '<path fill="#ffffff" d="M12 2c3 2 6 7 6 11l-3 2-3-3-3 3-3-2c0-4 3-9 6-11z"/>
                          <circle cx="12" cy="8" r="2" fill="#3b82f6"/>
                          <path fill="#ef4444" d="M8 13l-3 4 3-1 2 2-2-5zm8 0l3 4-3-1-2 2 2-5z"/>
                          <path fill="#f59e0b" d="M10 16l2 5 2-5-2 1-2-1z"/>'
            ],
            // 34. Verified Checkmark Badge on Yellow Disc
            34 => [
                'name' => '✅ ผู้จุดประกายรักตนเอง ชั้นจัตวา',
                'discClass' => 'disc-golden',
                'svg' => '<path fill="#10b981" d="M12 2l2.5 2 3.2-.5 1.5 2.8 3.1 1.2-.2 3.3 2 2.6-1.5 3 .8 3.2-2.8 1.6-.5 3.3-3.3.4-1.8 2.7-3.1-.8-2.7 2-2.7-2-3.1.8-1.8-2.7-3.3-.4-.5-3.3-2.8-1.6.8-3.2-1.5-3 2-2.6-.2-3.3 3.1-1.2 1.5-2.8 3.2.5L12 2z"/>
                          <path d="M8 12l3 3 5-6" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>'
            ],
            // 35. Star Pennant Flag on Mint Disc
            35 => [
                'name' => '🚩 ผู้จุดประกายรักตนเอง ชั้นเบญจ',
                'discClass' => 'disc-teal',
                'svg' => '<path fill="#334155" d="M5 2h2v19H5V2z"/>
                          <path fill="#ef4444" d="M7 4l14 5-14 5V4z"/>
                          <path fill="#ffffff" d="M11 7.5l.6 1.3 1.4.1-1 1 .3 1.4-1.3-.7-1.3.7.3-1.4-1-1 1.4-.1.6-1.3z"/>'
            ],
            // 36. Gold Star Coin with Ring on Navy Disc
            36 => [
                'name' => '⭐ ทูตสุขภาพสร้างพลังบวก ชั้นเอก',
                'discClass' => 'disc-navy-gold',
                'svg' => '<circle cx="12" cy="12" r="9" fill="#fbbf24"/>
                          <circle cx="12" cy="12" r="7" fill="#f59e0b"/>
                          <path fill="#fef08a" d="M12 7l1.5 3.2 3.5.4-2.6 2.4.7 3.5-3.1-1.7-3.1 1.7.7-3.5-2.6-2.4 3.5-.4L12 7z"/>'
            ],
            // 37. Silver Rosette on Teal Disc
            37 => [
                'name' => '🎖️ ทูตสุขภาพสร้างพลังบวก ชั้นโท',
                'discClass' => 'disc-teal',
                'svg' => '<path fill="#ffffff" d="M8 14l-3 7 5-2 2 3 1-8H8zm8 0l3 7-5-2-2 3-1-8h5z"/>
                          <circle cx="12" cy="9.5" r="6" fill="#ef4444"/>
                          <circle cx="12" cy="9.5" r="4.5" fill="#ffffff"/>'
            ],
            // 38. Graduation Cap & Diploma on Red Disc
            38 => [
                'name' => '🎓 ทูตสุขภาพสร้างพลังบวก ชั้นตรี',
                'discClass' => 'disc-ruby-gold',
                'svg' => '<path fill="#1e293b" d="M12 4L3 8l9 4 9-4-9-4z"/>
                          <path fill="#1e293b" d="M6 10.5v4c0 2.5 3 4.5 6 4.5s6-2 6-4.5v-4l-6 2.5-6-2.5z"/>
                          <path d="M19 9v6" stroke="#fbbf24" stroke-width="1.5"/>'
            ],
            // 39. Hand Raising Victory Trophy on Navy Disc
            39 => [
                'name' => '🏆 ทูตสุขภาพสร้างพลังบวก ชั้นจัตวา',
                'discClass' => 'disc-navy-gold',
                'svg' => '<path fill="#fbbf24" d="M16 3h-1V2h-6v1H8a2 2 0 00-2 2v1.5a2.5 2.5 0 002.5 2.5h.4a4 4 0 003.1 3v2h-2a1 1 0 000 2h4a1 1 0 000-2h-2V12a4 4 0 003.1-3h.4a2.5 2.5 0 002.5-2.5V5a2 2 0 00-2-2z"/>
                          <path fill="#f59e0b" d="M10 16h4v4h-4v-4z"/><path fill="#3b82f6" d="M9 19l2 3h2l2-3H9z"/>'
            ],
            // 40. Star on Spotlight Stage on Gold Disc
            40 => [
                'name' => '🌟 ทูตสุขภาพสร้างพลังบวก ชั้นเบญจ',
                'discClass' => 'disc-golden',
                'svg' => '<ellipse cx="12" cy="18" rx="8" ry="3" fill="#1e293b"/>
                          <path fill="rgba(255,255,255,0.4)" d="M4 2l5 16h6l5-16H4z"/>
                          <path fill="#fbbf24" d="M12 8l1.6 3.4 3.7.4-2.7 2.6.7 3.7-3.3-1.8-3.3 1.8.7-3.7-2.7-2.6 3.7-.4L12 8z"/>'
            ],
            // 41. Loudspeaker Megaphone on Red Disc
            41 => [
                'name' => '📢 ปราชญ์สุขภาพคู่บ้านคู่เมือง ชั้นเอก',
                'discClass' => 'disc-ruby-gold',
                'svg' => '<path fill="#ffffff" d="M4 9h3l6-4v14l-6-4H4a1 1 0 01-1-1v-4a1 1 0 011-1z"/>
                          <path fill="#334155" d="M6 14l1 5h3l-1-5H6z"/>
                          <path d="M16 8a4 4 0 010 8m2-10a7 7 0 010 12" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" fill="none"/>'
            ],
            // 42. Flaming Torch on Mint Disc
            42 => [
                'name' => '🔥 ปราชญ์สุขภาพคู่บ้านคู่เมือง ชั้นโท',
                'discClass' => 'disc-teal',
                'svg' => '<path fill="#fbbf24" d="M9 12h6l-1 9h-4l-1-9z"/>
                          <path fill="#ef4444" d="M12 2c2 3 4 5 2 7-1-1-2-1-2-1s-1 1-2 1c-2-2 0-4 2-7z"/>
                          <path fill="#fef08a" d="M12 5c1 1.5 2 2.5 1 3.5-.5-.5-1-.5-1-.5s-.5.5-1 .5c-1-1 0-2 1-3.5z"/>'
            ],
            // 43. Trophy inside Laurel Wreath on Navy Disc
            43 => [
                'name' => '🏆 ปราชญ์สุขภาพคู่บ้านคู่เมือง ชั้นตรี',
                'discClass' => 'disc-navy-gold',
                'svg' => '<path fill="#fbbf24" d="M15 6h-1V5H10v1H9a2 2 0 00-2 2v1a2.5 2.5 0 002.5 2.5h.3a4 4 0 002.2 2.2V16H11a1 1 0 000 2h2a1 1 0 000-2h-1v-1.3a4 4 0 002.2-2.2h.3a2.5 2.5 0 002.5-2.5V8a2 2 0 00-2-2z"/>
                          <path fill="#fef08a" d="M6 8c-.6 1-1 2-1 3.5.5 0 1-.2 1.5-.5M6 13c-.3 1 0 2 .5 3 .5-.3.8-.7 1-1.2M18 8c.6 1 1 2 1 3.5-.5 0-1-.2-1.5-.5M18 13c.3 1 0 2-.5 3-.5-.3-.8-.7-1-1.2"/>'
            ],
            // 44. Winged Star Badge on Red Disc
            44 => [
                'name' => '🪽 ปราชญ์สุขภาพคู่บ้านคู่เมือง ชั้นจัตวา',
                'discClass' => 'disc-ruby-gold',
                'svg' => '<path fill="#ffffff" d="M3 10c3 0 5 2 6 4-2 1-4 0-6-4zm18 0c-3 0-5 2-6 4 2 1 4 0 6-4z"/>
                          <circle cx="12" cy="12" r="5" fill="#fbbf24"/>
                          <path fill="#fef08a" d="M12 9l.8 1.6 1.8.2-1.3 1.2.4 1.8-1.7-.9-1.7.9.4-1.8-1.3-1.2 1.8-.2.8-1.6z"/>'
            ],
            // 45. Crown on Velvet Cushion on Dark Teal Disc
            45 => [
                'name' => '👑 ปราชญ์สุขภาพคู่บ้านคู่เมือง ชั้นเบญจ',
                'discClass' => 'disc-navy-gold',
                'svg' => '<path fill="#ef4444" d="M4 14c0 3 4 5 8 5s8-2 8-5c-2-1-5-1.5-8-1.5S6 13 4 14z"/>
                          <path fill="#fbbf24" d="M6 14h12l-1-5-2.5 2.5-2.5-4-2.5 4L7 9l-1 5z"/>
                          <circle cx="12" cy="7.5" r="1" fill="#fef08a"/>'
            ],
            // 46. Ascending Step Podium on Teal Disc
            46 => [
                'name' => '🪜 แสงสว่างนำทางชีวิตชีวา ชั้นเอก',
                'discClass' => 'disc-teal',
                'svg' => '<path fill="#10b981" d="M15 5h5v15h-5V5zM9 10h6v10H9V10zM3 14h6v6H3v-6z"/>
                          <text x="5.5" y="18.5" font-size="3.5" font-weight="900" fill="#ffffff">3</text>
                          <text x="11.5" y="15.5" font-size="3.5" font-weight="900" fill="#ffffff">2</text>
                          <text x="17.5" y="11.5" font-size="4" font-weight="900" fill="#ffffff">1</text>'
            ],
            // 47. Olympic Medal #1 on Red Disc
            47 => [
                'name' => '🥇 แสงสว่างนำทางชีวิตชีวา ชั้นโท',
                'discClass' => 'disc-ruby-gold',
                'svg' => '<path fill="#3b82f6" d="M6 3l4 6h4l4-6h-3l-3 4-3-4H6z"/><path fill="#ffffff" d="M9 3l3 4 3-4h-1.5l-1.5 2-1.5-2H9z"/>
                          <circle cx="12" cy="15" r="6" fill="#fbbf24"/>
                          <text x="12" y="17.5" font-size="7" font-weight="900" text-anchor="middle" fill="#b45309">1</text>'
            ],
            // 48. Star Rosette Badge on Yellow Disc
            48 => [
                'name' => '🎖️ แสงสว่างนำทางชีวิตชีวา ชั้นตรี',
                'discClass' => 'disc-golden',
                'svg' => '<path fill="#ef4444" d="M8 13.5l-3 7.5 5-2 2 3.5 1-9H8zm8 0l3 7.5-5-2-2 3.5-1-9h6z"/>
                          <circle cx="12" cy="9" r="6" fill="#fbbf24"/>
                          <path fill="#ffffff" d="M12 6.5l.8 1.8 1.8.2-1.4 1.2.4 1.8-1.6-1-1.6 1 .4-1.8-1.4-1.2 1.8-.2.8-1.8z"/>'
            ],
            // 49. Trophy inside Green Shield on Mint Disc
            49 => [
                'name' => '🛡️ แสงสว่างนำทางชีวิตชีวา ชั้นจัตวา',
                'discClass' => 'disc-teal',
                'svg' => '<path fill="#10b981" d="M12 2L4 5.5v5.8c0 5.4 3.4 10.4 8 11.7 4.6-1.3 8-6.3 8-11.7V5.5L12 2z"/>
                          <path fill="#fbbf24" d="M15 7h-1V6H10v1H9a2 2 0 00-2 2v1a2.5 2.5 0 002.5 2.5h.3a3.5 3.5 0 001.7 1.8V16H10v1.5h4V16h-1.5v-1.7a3.5 3.5 0 001.7-1.8h.3a2.5 2.5 0 002.5-2.5V9a2 2 0 00-2-2z"/>'
            ],
            // 50. Trophy with Celebration Confetti on Navy Disc
            50 => [
                'name' => '🎆 แสงสว่างนำทางชีวิตชีวา ชั้นเบญจ',
                'discClass' => 'disc-navy-gold',
                'svg' => '<path fill="#fbbf24" d="M17 5h-1V4a1 1 0 00-1-1H9a1 1 0 00-1 1v1H7a2 2 0 00-2 2v2a3 3 0 003 3h.5a5 5 0 004.5 3.9V18H10a1 1 0 000 2h4a1 1 0 000-2h-3v-2.1a5 5 0 004.5-3.9H16a3 3 0 003-3V7a2 2 0 00-2-2z"/>
                          <circle cx="5" cy="4" r="1" fill="#ef4444"/><circle cx="19" cy="4" r="1" fill="#38bdf8"/>
                          <circle cx="4" cy="16" r="1" fill="#10b981"/><circle cx="20" cy="16" r="1" fill="#f59e0b"/>
                          <path d="M12 2v1M6 9h1m10 0h1" stroke="#fef08a" stroke-width="1.5" stroke-linecap="round"/>'
            ],
            // 51+ (General VHV Participants)
            51 => [
                'name' => '🎗️ อสม. ผู้ร่วมขับเคลื่อนสุขภาวะ',
                'discClass' => 'disc-soft',
                'svg' => '<circle cx="12" cy="9.5" r="5.5" fill="#94a3b8"/>
                          <path fill="#ef4444" d="M8.5 13.5l-2.5 7.5 4.5-2 2 3 1-8.5h-5zm7 0l2.5 7.5-4.5-2-2 3-1-8.5h5z"/>
                          <circle cx="12" cy="9.5" r="3" fill="#ffffff" opacity="0.4"/>'
            ]
        ];

        return $rankMap[$rank] ?? $rankMap[51];
    }
}

if (!function_exists('render_50_rank_emblem')) {
    function render_50_rank_emblem($rank, $size = 'md', $extraStyle = '') {
        $rank = (int)$rank;
        if ($rank < 1) $rank = 51;
        
        $def = get_50_rank_icon_def($rank);
        
        // Dimensions based on size
        $sizes = [
            'xs' => '24px',
            'sm' => '38px',
            'md' => '46px',
            'lg' => '56px',
            'xl' => '72px'
        ];
        $dim = $sizes[$size] ?? '46px';
        
        // Determine correct relative URL path based on caller directory
        $callerPath = $_SERVER['PHP_SELF'] ?? '';
        $isSubdir = (strpos($callerPath, '/vhv/') !== false || strpos($callerPath, '/admin/') !== false);
        $baseRel = $isSubdir ? '../assets/img/ranks/' : 'assets/img/ranks/';
        
        $imgFile = ($rank >= 1 && $rank <= 50) ? 'rank_' . $rank . '.png' : 'rank_default.png';
        $imgSrc = $baseRel . $imgFile . '?v=20260824_6';

        // Extra glowing border & shadow for Top 3
        $glowStyle = '';
        if ($rank === 1) {
            $glowStyle = 'filter: drop-shadow(0 0 12px rgba(251, 191, 36, 0.8)) drop-shadow(0 4px 10px rgba(0,0,0,0.3)); transform: scale(1.06);';
        } elseif ($rank === 2) {
            $glowStyle = 'filter: drop-shadow(0 0 10px rgba(239, 68, 68, 0.7)) drop-shadow(0 4px 8px rgba(0,0,0,0.25)); transform: scale(1.04);';
        } elseif ($rank === 3) {
            $glowStyle = 'filter: drop-shadow(0 0 10px rgba(13, 148, 136, 0.7)) drop-shadow(0 4px 8px rgba(0,0,0,0.25)); transform: scale(1.02);';
        } else {
            $glowStyle = 'filter: drop-shadow(0 2px 5px rgba(0,0,0,0.2));';
        }

        $style = 'width: ' . $dim . '; height: ' . $dim . '; border-radius: 50%; object-fit: contain; display: inline-block; vertical-align: middle; transition: transform 0.2s ease; ' . $glowStyle;
        if (!empty($extraStyle)) {
            $style .= ' ' . $extraStyle;
        }

        return '<img src="' . htmlspecialchars($imgSrc) . '" alt="อันดับ ' . $rank . '" class="rank-trophy-img" style="' . htmlspecialchars($style) . '" title="' . htmlspecialchars($def['name']) . '" loading="lazy" onerror="this.onerror=null; this.src=\'' . $baseRel . 'rank_default.png\';">';
    }
}
