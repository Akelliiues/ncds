/**
 * Clinical Guidance & Health Progress Engine for NCD Portal (Simple & Easy)
 * รพ.สต. และ อสม. หมอคนที่ 1 — สสอ.ตาลสุม
 */

(function(window) {
    'use strict';

    const CARE_LEVEL_CONFIG = {
        good:     { label: 'ดูแลปกติ', badgeClass: 'bg-success text-white', color: '#10B981', icon: '🟢', days: 90 },
        fair:     { label: 'ดูแลใส่ใจ', badgeClass: 'bg-warning text-dark', color: '#F59E0B', icon: '🟡', days: 30 },
        poor:     { label: 'เฝ้าระวังพิเศษ', badgeClass: 'bg-orange text-white', color: '#F97316', icon: '🟠', days: 14 },
        critical: { label: 'ดูแลเร่งด่วน', badgeClass: 'bg-danger text-white', color: '#EF4444', icon: '🔴', days: 7 }
    };

    const SLEEP_CONFIG = {
        good:     { label: 'หลับสนิทดี', icon: '🌙', desc: 'พักผ่อนเพียงพอ หลับสนิท' },
        restless: { label: 'หลับๆ ตื่นๆ', icon: '🥱', desc: 'ตื่นกลางดึกบ่อย' },
        poor:     { label: 'นอนไม่ค่อยหลับ', icon: '😫', desc: 'นอนหลับยาก' }
    };

    /**
     * ระบบอ่านออกเสียงบทสนทนาพลังบวก (Text-to-Speech Voice Coach)
     */
    function speak(text, btnElement) {
        if (!('speechSynthesis' in window)) {
            alert('อุปกรณ์นี้ยังไม่รองรับระบบเสียงพูดในตัว');
            return;
        }

        if (window.speechSynthesis.speaking) {
            window.speechSynthesis.cancel();
            if (btnElement) {
                btnElement.innerHTML = '<span>🔊</span> <span>เปิดเสียงพูด</span>';
                btnElement.style.background = '#10B981';
            }
            return;
        }

        const cleanText = text.replace(/["'“”✨🌿🟢🟡🔴🚨💡💬]/g, '').trim();
        const utterance = new SpeechSynthesisUtterance(cleanText);
        utterance.lang = 'th-TH';
        utterance.rate = 0.92; // ความเร็วพอดี นุ่มนวล ชัดถ้อยชัดคำสำหรับผู้สูงอายุ
        utterance.pitch = 1.05; // โทนเสียงอบอุ่น เป็นมิตร

        // ค้นหาเสียงภาษาไทยถ้ามี
        const voices = window.speechSynthesis.getVoices();
        const thaiVoice = voices.find(v => (v.lang && v.lang.toLowerCase().includes('th')) || (v.name && v.name.includes('Thai')));
        if (thaiVoice) {
            utterance.voice = thaiVoice;
        }

        if (btnElement) {
            btnElement.innerHTML = '<span>⏹️</span> <span>กำลังพูด... (กดหยุด)</span>';
            btnElement.style.background = '#EF4444';
        }

        utterance.onend = function() {
            if (btnElement) {
                btnElement.innerHTML = '<span>🔊</span> <span>ฟังอีกครั้ง</span>';
                btnElement.style.background = '#10B981';
            }
        };

        utterance.onerror = function() {
            if (btnElement) {
                btnElement.innerHTML = '<span>🔊</span> <span>เปิดเสียงพูด</span>';
                btnElement.style.background = '#10B981';
            }
        };

        window.speechSynthesis.speak(utterance);
    }

    /**
     * วิเคราะห์ผลสุขภาพ คำนวณ Care Level, Guidance, และ Health Progress
     */
    function analyze(data) {
        const sys = parseFloat(data.bp_sys) || null;
        const dia = parseFloat(data.bp_dia) || null;
        const fbs = parseFloat(data.fbs) || null;
        const weight = parseFloat(data.weight) || null;
        const height = parseFloat(data.height) || null;
        const waist = parseFloat(data.waist) || null;
        const sleep = data.sleep_quality || 'good';
        const prev = data.previous_data || null;

        // คำนวณ BMI
        let bmi = null;
        if (weight && height) {
            const hM = height / 100;
            bmi = +(weight / (hM * hM)).toFixed(1);
        } else if (data.bmi) {
            bmi = parseFloat(data.bmi);
        }

        // 1. ประเมิน Care Level & ภาวะเร่งด่วน
        let careLevel = 'good';
        let isEmergency = false;
        let emergencyMessage = '';

        if (sys >= 180 || dia >= 110) {
            careLevel = 'critical';
            isEmergency = true;
            emergencyMessage = 'ความดันโลหิตสูงวิกฤต (≥180/110) เสี่ยงเส้นเลือดสมอง';
        } else if (fbs && fbs >= 200) {
            careLevel = 'critical';
            isEmergency = true;
            emergencyMessage = 'ระดับน้ำตาลในเลือดสูงมาก (≥200 mg/dL)';
        } else if (fbs && fbs < 70) {
            careLevel = 'critical';
            isEmergency = true;
            emergencyMessage = 'ภาวะน้ำตาลในเลือดต่ำ (<70 mg/dL) เสี่ยงหมดสติ';
        } else if ((sys >= 140 || dia >= 90) || (fbs && fbs >= 126)) {
            careLevel = 'poor';
        } else if ((sys >= 120 || dia >= 80) || (fbs && fbs >= 100) || (bmi && bmi >= 25)) {
            careLevel = 'fair';
        } else {
            careLevel = 'good';
        }

        const careMeta = CARE_LEVEL_CONFIG[careLevel];
        const nextDays = careMeta.days;

        // คำนวณวันนัดครั้งถัดไป
        const nextDateObj = new Date();
        nextDateObj.setDate(nextDateObj.getDate() + nextDays);
        const nextDateIso = nextDateObj.toISOString().split('T')[0];
        const thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        const nextDateThai = nextDateObj.getDate() + ' ' + thaiMonths[nextDateObj.getMonth() + 1] + ' ' + (nextDateObj.getFullYear() + 543);

        // 2. คำนวณ Health Progress (เทียบกับครั้งก่อน)
        let healthProgress = 'baseline';
        let progressLabel = 'บันทึกครั้งแรก';
        let progressBadgeStyle = 'background: rgba(59, 130, 246, 0.12); color: #3B82F6; border: 1px solid rgba(59, 130, 246, 0.3);';
        let progressIcon = '✨';

        if (prev) {
            const prevSys = parseFloat(prev.bp_sys) || null;
            const prevFbs = parseFloat(prev.fbs) || null;

            let score = 0;
            if (sys && prevSys) {
                if (sys - prevSys <= -5) score += 1;
                else if (sys - prevSys >= 8) score -= 1;
            }
            if (fbs && prevFbs) {
                if (fbs - prevFbs <= -8) score += 1;
                else if (fbs - prevFbs >= 10) score -= 1;
            }

            if (score > 0) {
                healthProgress = 'improved';
                progressLabel = 'สุขภาพดีขึ้น';
                progressBadgeStyle = 'background: rgba(16, 185, 129, 0.12); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.3);';
                progressIcon = '🟢';
            } else if (score < 0) {
                healthProgress = 'worsened';
                progressLabel = 'ชวนใส่ใจดูแลเพิ่ม';
                progressBadgeStyle = 'background: rgba(245, 158, 11, 0.12); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3);';
                progressIcon = '🟡';
            } else {
                healthProgress = 'stable';
                progressLabel = 'สุขภาพทรงตัวคงที่';
                progressBadgeStyle = 'background: rgba(59, 130, 246, 0.12); color: #3B82F6; border: 1px solid rgba(59, 130, 246, 0.3);';
                progressIcon = '🌿';
            }
        }

        // 3. บทพูดพลังบวกสั้นกระชับ & คำแนะนำ 1 บรรทัด (Simple & Easy ไม่รกรุงรัง)
        let whatToSay = '';
        let conciseTip = '';

        if (isEmergency) {
            whatToSay = 'ค่าน้ำตาลและความดันรอบนี้ต้องรีบดูแลเป็นพิเศษค่ะ เดี๋ยว อสม. ช่วยประสานคุณหมอที่ รพ.สต. ให้นะคะ';
            conciseTip = 'ให้นั่งพักในที่อากาศถ่ายเท หลีกเลี่ยงของหวานจัด และประสาน รพ.สต. หรือ 1669 ทันที';
        } else if (healthProgress === 'improved') {
            whatToSay = 'ยินดีด้วยนะคะ ผลตรวจรอบนี้ดีขึ้นมากเลย ดูแลตัวเองได้ยอดเยี่ยมมาก ทำต่อเนื่องไปนะคะ';
            conciseTip = 'รักษาวินัยการทานอาหารรสจืด ดื่มน้ำเปล่าบ่อยๆ และออกกำลังกายสม่ำเสมอ';
        } else if (careLevel === 'poor' || healthProgress === 'worsened') {
            whatToSay = 'รอบนี้เริ่มสูงขึ้นนิดหน่อยนะคะ ไม่เป็นไรค่ะ เรามาช่วยกันลดหวาน ลดเค็ม ดื่มน้ำเปล่าเพิ่มกันนะคะ';
            conciseTip = 'ชวนลดของหวาน ของทอด แกงกะทิ ทานยาตรงเวลา และพักผ่อนให้เพียงพอ';
        } else {
            whatToSay = 'ผลสุขภาพโดยรวมปกติดีค่ะ สดชื่นแข็งแรง รักษาสุขภาพแบบนี้ต่อไปเรื่อยๆ นะคะ';
            conciseTip = 'รับประทานอาหารรสไม่หวานจัด ดื่มน้ำสะอาด 6-8 แก้ว และขยับกายออกกำลังเบาๆ ทุกวัน';
        }

        if (sleep === 'poor') {
            conciseTip += ' · จิบน้ำอุ่นและงดเล่นมือก่อนนอน';
        }

        return {
            care_level: careLevel,
            care_meta: careMeta,
            is_emergency: isEmergency,
            emergency_message: emergencyMessage,
            next_visit_days: nextDays,
            next_visit_date: nextDateIso,
            next_visit_thai: nextDateThai,
            health_progress: healthProgress,
            progress_label: progressLabel,
            progress_badge_style: progressBadgeStyle,
            progress_icon: progressIcon,
            sleep_quality: sleep,
            sleep_meta: SLEEP_CONFIG[sleep] || SLEEP_CONFIG.good,
            what_to_say: whatToSay,
            concise_tip: conciseTip
        };
    }

    /**
     * Render Simple & Easy Guidance Card with Text-to-Speech Button
     */
    function renderGuidanceCard(result) {
        let emergencyHtml = '';
        if (result.is_emergency) {
            emergencyHtml = `
                <div style="background: rgba(239, 68, 68, 0.1); border: 1.5px solid #EF4444; border-radius: 14px; padding: 12px 14px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                    <div style="font-size: 13px; font-weight: 800; color: #DC2626; display: flex; align-items: center; gap: 6px;">
                        <span>🚨</span> <span>${result.emergency_message}</span>
                    </div>
                    <a href="tel:1669" style="background: #DC2626; color: white; border: none; padding: 6px 14px; border-radius: 10px; font-size: 12.5px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                        📞 โทร 1669
                    </a>
                </div>
            `;
        }

        const safeScript = result.what_to_say.replace(/'/g, "\\'").replace(/"/g, '&quot;');

        return `
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 14px; box-shadow: var(--neumorph-flat); margin-bottom: 12px;">
                ${emergencyHtml}

                <!-- Header: Progress Badge & Next Appointment in 1 Clean Line -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 6px;">
                    <span style="${result.progress_badge_style}; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
                        <span>${result.progress_icon}</span> <span>${result.progress_label}</span>
                    </span>
                    <span style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">
                        📅 นัดครั้งถัดไป: <strong style="color: var(--color-primary);">${result.next_visit_thai}</strong>
                    </span>
                </div>

                <!-- Positive Speech Script with Instant Audio Speaker Button -->
                <div style="background: rgba(16, 185, 129, 0.08); border-left: 3.5px solid #10B981; border-radius: 0 14px 14px 0; padding: 12px; font-size: 13.5px; color: var(--text-primary); line-height: 1.45; margin-bottom: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">
                        <span style="font-size: 11.5px; font-weight: 800; color: #10B981; display: flex; align-items: center; gap: 4px;">
                            <span>💬</span> <span>บทพูดชวนคุยกับชาวบ้าน:</span>
                        </span>
                        <button type="button" onclick="ClinicalGuidance.speak('${safeScript}', this)" style="background: #10B981; color: white; border: none; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.35); transition: all 0.2s;">
                            <span>🔊</span> <span>เปิดเสียงพูด</span>
                        </button>
                    </div>
                    <div style="font-style: italic; font-weight: 600; color: var(--text-primary); font-size: 14px;">"${result.what_to_say}"</div>
                </div>

                <!-- 1-Line Clean Tip -->
                <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.4; display: flex; align-items: flex-start; gap: 6px; padding: 4px 2px;">
                    <span style="flex-shrink: 0;">💡</span>
                    <span><strong>คำแนะนำ:</strong> ${result.concise_tip}</span>
                </div>
            </div>
        `;
    }

    window.ClinicalGuidance = {
        analyze: analyze,
        speak: speak,
        renderGuidanceCard: renderGuidanceCard,
        CARE_LEVEL_CONFIG: CARE_LEVEL_CONFIG,
        SLEEP_CONFIG: SLEEP_CONFIG
    };

})(window);
