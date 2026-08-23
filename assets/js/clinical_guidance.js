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

    let currentAudio = null;

    function getAudioBasePath() {
        if (window.location.pathname.includes('/vhv/') || window.location.pathname.includes('/admin/')) {
            return '../assets/audio/';
        }
        return 'assets/audio/';
    }

    /**
     * ระบบเล่นเสียงพากย์พยาบาลธรรมชาติ HD (Studio-Quality Natural Thai Voice)
     */
    function speak(audioKey, fallbackText, btnElement) {
        // 1. ถ้ากำลังเล่นเสียงอยู่ ให้กดเพื่อหยุด (Toggle Stop)
        if (currentAudio) {
            currentAudio.pause();
            currentAudio.currentTime = 0;
            currentAudio = null;
            if (btnElement) {
                btnElement.innerHTML = '<span>🔊</span> <span>เปิดเสียงพูด</span>';
                btnElement.style.background = '#10B981';
            }
            return;
        }

        if (window.speechSynthesis && window.speechSynthesis.speaking) {
            window.speechSynthesis.cancel();
            if (btnElement) {
                btnElement.innerHTML = '<span>🔊</span> <span>เปิดเสียงพูด</span>';
                btnElement.style.background = '#10B981';
            }
            return;
        }

        if (btnElement) {
            btnElement.innerHTML = '<span>⏹️</span> <span>กำลังพูด... (กดหยุด)</span>';
            btnElement.style.background = '#EF4444';
        }

        // 2. เล่นไฟล์เสียง HD Studio Voice (เสียงพากย์ธรรมชาติแท้ 100%)
        const soundFile = getAudioBasePath() + 'voice_' + (audioKey || 'normal') + '.mp3';
        const audio = new Audio(soundFile);
        currentAudio = audio;

        audio.onended = function() {
            currentAudio = null;
            if (btnElement) {
                btnElement.innerHTML = '<span>🔊</span> <span>ฟังอีกครั้ง</span>';
                btnElement.style.background = '#10B981';
            }
        };

        audio.onerror = function() {
            currentAudio = null;
            // Fallback: ใช้ Web Speech API ถ้าหาไฟล์ไม่พบ
            if ('speechSynthesis' in window && fallbackText) {
                const cleanText = fallbackText.replace(/["'“”✨🌿🟢🟡🔴🚨💡💬]/g, '').trim();
                const utterance = new SpeechSynthesisUtterance(cleanText);
                utterance.lang = 'th-TH';
                utterance.rate = 0.92;
                utterance.pitch = 1.05;

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
            } else {
                if (btnElement) {
                    btnElement.innerHTML = '<span>🔊</span> <span>เปิดเสียงพูด</span>';
                    btnElement.style.background = '#10B981';
                }
            }
        };

        audio.play().catch(e => {
            audio.onerror();
        });
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
        let audioKey = 'normal';

        if (isEmergency) {
            audioKey = 'critical';
            whatToSay = 'ค่าน้ำตาลและความดันรอบนี้ต้องรีบดูแลเป็นพิเศษค่ะ เดี๋ยว อสม. ช่วยประสานคุณหมอที่ รพ.สต. ให้นะคะ';
            conciseTip = 'ให้นั่งพักในที่อากาศถ่ายเท หลีกเลี่ยงของหวานจัด และประสาน รพ.สต. หรือ 1669 ทันที';
        } else if (healthProgress === 'improved') {
            audioKey = 'improved';
            whatToSay = 'ยินดีด้วยนะคะ! ผลตรวจรอบนี้ดีขึ้นมากเลย ดูแลตัวเองได้ยอดเยี่ยมมาก ทำต่อเนื่องไปนะคะ';
            conciseTip = 'รักษาวินัยการทานอาหารรสจืด ดื่มน้ำเปล่าบ่อยๆ และออกกำลังกายสม่ำเสมอ';
        } else if (careLevel === 'poor' || healthProgress === 'worsened') {
            audioKey = 'warning';
            whatToSay = 'รอบนี้เริ่มสูงขึ้นนิดหน่อยนะคะ ไม่เป็นไรค่ะ เรามาช่วยกันลดหวาน ลดเค็ม ดื่มน้ำเปล่าเพิ่มกันนะคะ';
            conciseTip = 'ชวนลดของหวาน ของทอด แกงกะทิ ทานยาตรงเวลา และพักผ่อนให้เพียงพอ';
        } else {
            audioKey = 'normal';
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
            concise_tip: conciseTip,
            audio_key: audioKey
        };
    }

    /**
     * Render Simple & Easy Guidance Card with Natural Studio Voice Button
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
        const safeAudioKey = result.audio_key || 'normal';

        return `
            <div style="background: var(--bg-card); border: 1px solid var(--border-color, transparent); border-radius: 20px; padding: 16px; box-shadow: var(--neumorph-flat); margin-bottom: 16px;">
                ${emergencyHtml}

                <!-- Header: Progress Badge & Next Appointment in 1 Clean Line -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 6px;">
                    <span style="${result.progress_badge_style}; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; box-shadow: var(--neumorph-flat);">
                        <span>${result.progress_icon}</span> <span>${result.progress_label}</span>
                    </span>
                    <span style="font-size: 12px; color: var(--text-secondary); font-weight: 700;">
                        📅 นัดครั้งถัดไป: <strong style="color: var(--color-primary);">${result.next_visit_thai}</strong>
                    </span>
                </div>

                <!-- Positive Doctor Speech Script with Big Prominent Voice Button -->
                <div style="background: var(--bg-darker); border-radius: 18px; padding: 14px; margin-bottom: 12px; box-shadow: var(--neumorph-inset);">
                    <!-- Big Prominent Audio Button -->
                    <button type="button" onclick="ClinicalGuidance.speak('${safeAudioKey}', '${safeScript}', this)" style="width: 100%; background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; border: none; padding: 12px 16px; border-radius: 16px; font-size: 15px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35); margin-bottom: 10px; transition: transform 0.15s ease;">
                        <span style="font-size: 18px;">🔊</span> <span>เปิดเสียงคุณหมอให้ฟัง</span>
                    </button>

                    <!-- Speech Quote Bubble (Neumorphic Inset Well) -->
                    <div style="background: var(--bg-card); border-radius: 14px; padding: 12px 14px; font-size: 13.5px; color: var(--text-primary); line-height: 1.55; font-weight: 600; box-shadow: var(--neumorph-flat);">
                        <span style="color: #10B981; font-size: 16px; font-weight: 900; margin-right: 4px;">“</span>${result.what_to_say}<span style="color: #10B981; font-size: 16px; font-weight: 900; margin-left: 4px;">”</span>
                    </div>
                </div>

                <!-- 1-Line Clean Tip -->
                <div style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.4; display: flex; align-items: flex-start; gap: 6px; padding: 2px 2px;">
                    <span style="flex-shrink: 0;">💡</span>
                    <span><strong>คำแนะนำสุขภาพ:</strong> ${result.concise_tip}</span>
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
