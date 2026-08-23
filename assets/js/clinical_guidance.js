/**
 * Clinical Guidance & Health Progress Engine for NCD Portal (Simple & Easy)
 * รพ.สต. และ อสม. หมอคนที่ 1 — สสอ.ตาลสุม
 */

(function (window) {
    'use strict';

    const CARE_LEVEL_CONFIG = {
        good: { label: 'ดูแลปกติ', badgeClass: 'bg-success text-white', color: '#10B981', icon: '🟢', days: 90 },
        fair: { label: 'ดูแลใส่ใจ', badgeClass: 'bg-warning text-dark', color: '#F59E0B', icon: '🟡', days: 30 },
        poor: { label: 'เฝ้าระวังพิเศษ', badgeClass: 'bg-orange text-white', color: '#F97316', icon: '🟠', days: 14 },
        critical: { label: 'ดูแลเร่งด่วน', badgeClass: 'bg-danger text-white', color: '#EF4444', icon: '🔴', days: 7 }
    };

    const SLEEP_CONFIG = {
        good: { label: 'หลับสนิทดี', icon: '🌙', desc: 'พักผ่อนเพียงพอ หลับสนิท' },
        restless: { label: 'หลับๆ ตื่นๆ', icon: '🥱', desc: 'ตื่นกลางดึกบ่อย' },
        poor: { label: 'นอนไม่ค่อยหลับ', icon: '😫', desc: 'นอนหลับยาก' }
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
        const defaultBtnHtml = `
            <div style="display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%;">
                <span style="width: 38px; height: 38px; border-radius: 50%; background: rgba(255, 255, 255, 0.25); display: flex; align-items: center; justify-content: center; padding: 6px; box-sizing: border-box; box-shadow: inset 0 1px 2px rgba(255,255,255,0.5), 0 2px 6px rgba(0,0,0,0.15); flex-shrink: 0;">
                    <svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" width="100%" height="100%">
                        <circle cx="50" cy="50" r="44" stroke-width="6.5" />
                        <circle cx="43" cy="37" r="4.5" fill="currentColor" stroke="none" />
                        <path d="M 47 28 L 57 44 L 49 44 C 49 49 45 53 39 56 L 39 68" stroke-width="6.5" />
                        <path d="M 59 47 C 62.5 50 62.5 54 59 57" stroke-width="5.5" />
                        <path d="M 66 41 C 72.5 47 72.5 57 66 63" stroke-width="5.5" />
                        <path d="M 73 35 C 82.5 44 82.5 64 73 73" stroke-width="5.5" />
                    </svg>
                </span>
                <div style="text-align: left; line-height: 1.25;">
                    <div style="font-size: 15.5px; font-weight: 900; letter-spacing: -0.2px;">เปิดเสียงคุณหมอสรุปผล</div>
                    <div style="font-size: 11.5px; opacity: 0.92; font-weight: 600;">(แตะเพื่อให้ระบบอ่านให้ชาวบ้านฟัง)</div>
                </div>
            </div>
        `;

        const playingBtnHtml = `
            <div style="display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%;">
                <span style="font-size: 22px; animation: pulse 1s infinite;">⏹️</span>
                <div style="text-align: left; line-height: 1.25;">
                    <div style="font-size: 15px; font-weight: 900;">กำลังอ่านออกเสียง...</div>
                    <div style="font-size: 11.5px; opacity: 0.95; font-weight: 600;">(แตะเพื่อหยุดเสียง)</div>
                </div>
            </div>
        `;

        // 1. ถ้ากำลังเล่นเสียงอยู่ ให้กดเพื่อหยุด (Toggle Stop)
        if (currentAudio) {
            currentAudio.pause();
            currentAudio.currentTime = 0;
            currentAudio = null;
            if (btnElement) {
                btnElement.innerHTML = defaultBtnHtml;
                btnElement.style.background = 'linear-gradient(135deg, #10B981 0%, #059669 100%)';
            }
            return;
        }

        if (window.speechSynthesis && window.speechSynthesis.speaking) {
            window.speechSynthesis.cancel();
            if (btnElement) {
                btnElement.innerHTML = defaultBtnHtml;
                btnElement.style.background = 'linear-gradient(135deg, #10B981 0%, #059669 100%)';
            }
            return;
        }

        if (btnElement) {
            btnElement.innerHTML = playingBtnHtml;
            btnElement.style.background = 'linear-gradient(135deg, #EF4444 0%, #DC2626 100%)';
        }

        // 2. เล่นไฟล์เสียง HD Studio Voice (เสียงพากย์ธรรมชาติแท้ 100%)
        const soundFile = getAudioBasePath() + 'voice_' + (audioKey || 'normal') + '.mp3';
        const audio = new Audio(soundFile);
        currentAudio = audio;

        audio.onended = function () {
            currentAudio = null;
            if (btnElement) {
                btnElement.innerHTML = defaultBtnHtml;
                btnElement.style.background = 'linear-gradient(135deg, #10B981 0%, #059669 100%)';
            }
        };

        audio.onerror = function () {
            currentAudio = null;
            // Fallback: ใช้ Web Speech API ถ้าหาไฟล์ไม่พบ
            if ('speechSynthesis' in window && fallbackText) {
                const cleanText = fallbackText.replace(/["'“”✨🌿🟢🟡🔴🚨💡💬]/g, '').trim();
                const utterance = new SpeechSynthesisUtterance(cleanText);
                utterance.lang = 'th-TH';
                utterance.rate = 0.92;
                utterance.pitch = 1.05;

                utterance.onend = function () {
                    if (btnElement) {
                        btnElement.innerHTML = defaultBtnHtml;
                        btnElement.style.background = 'linear-gradient(135deg, #10B981 0%, #059669 100%)';
                    }
                };
                utterance.onerror = function () {
                    if (btnElement) {
                        btnElement.innerHTML = defaultBtnHtml;
                        btnElement.style.background = 'linear-gradient(135deg, #10B981 0%, #059669 100%)';
                    }
                };
                window.speechSynthesis.speak(utterance);
            } else {
                if (btnElement) {
                    btnElement.innerHTML = defaultBtnHtml;
                    btnElement.style.background = 'linear-gradient(135deg, #10B981 0%, #059669 100%)';
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

                <!-- Positive Doctor Speech Script with Big Prominent Voice Button & Watermark Graphic -->
                <div style="
                    background: var(--bg-darker); 
                    border-radius: 22px; 
                    padding: 16px 14px; 
                    margin-bottom: 16px; 
                    box-shadow: var(--neumorph-inset);
                    position: relative;
                    overflow: hidden;
                ">
                    <!-- Large Watermark Graphic: Exact Circular Speaking Face Profile with 3 Sound Waves -->
                    <div style="
                        position: absolute;
                        right: 2px;
                        bottom: -10px;
                        width: 105px;
                        height: 105px;
                        pointer-events: none;
                        user-select: none;
                        opacity: 0.12;
                        color: #10B981;
                        z-index: 0;
                    ">
                        <svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" width="100%" height="100%">
                            <circle cx="50" cy="50" r="44" stroke-width="6.5" />
                            <circle cx="43" cy="37" r="4.5" fill="currentColor" stroke="none" />
                            <path d="M 47 28 L 57 44 L 49 44 C 49 49 45 53 39 56 L 39 68" stroke-width="6.5" />
                            <path d="M 59 47 C 62.5 50 62.5 54 59 57" stroke-width="5.5" />
                            <path d="M 66 41 C 72.5 47 72.5 57 66 63" stroke-width="5.5" />
                            <path d="M 73 35 C 82.5 44 82.5 64 73 73" stroke-width="5.5" />
                        </svg>
                    </div>

                    <div style="position: relative; z-index: 1;">
                        <!-- Big Prominent Audio Button with Glowing Speaking Face Badge -->
                        <button type="button" onclick="ClinicalGuidance.speak('${safeAudioKey}', '${safeScript}', this)" style="
                            width: 100%; 
                            background: linear-gradient(135deg, #10B981 0%, #059669 100%); 
                            color: white; 
                            border: none; 
                            padding: 13px 16px; 
                            border-radius: 18px; 
                            font-size: 15.5px; 
                            font-weight: 900; 
                            cursor: pointer; 
                            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.42); 
                            margin-bottom: 12px; 
                            transition: transform 0.15s ease, box-shadow 0.15s ease;
                        ">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%;">
                                <!-- Speaking Head Circular Icon Badge matching user reference -->
                                <span style="
                                    width: 40px; 
                                    height: 40px; 
                                    border-radius: 50%; 
                                    background: rgba(255, 255, 255, 0.25); 
                                    display: flex; 
                                    align-items: center; 
                                    justify-content: center; 
                                    padding: 6px; 
                                    box-sizing: border-box; 
                                    box-shadow: inset 0 1px 2px rgba(255,255,255,0.5), 0 2px 8px rgba(0,0,0,0.18); 
                                    flex-shrink: 0;
                                ">
                                    <svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" width="100%" height="100%">
                                        <circle cx="50" cy="50" r="44" stroke-width="6.5" />
                                        <circle cx="43" cy="37" r="4.5" fill="currentColor" stroke="none" />
                                        <path d="M 47 28 L 57 44 L 49 44 C 49 49 45 53 39 56 L 39 68" stroke-width="6.5" />
                                        <path d="M 59 47 C 62.5 50 62.5 54 59 57" stroke-width="5.5" />
                                        <path d="M 66 41 C 72.5 47 72.5 57 66 63" stroke-width="5.5" />
                                        <path d="M 73 35 C 82.5 44 82.5 64 73 73" stroke-width="5.5" />
                                    </svg>
                                </span>
                                <div style="text-align: left; line-height: 1.25;">
                                    <div style="font-size: 15.5px; font-weight: 900; letter-spacing: -0.2px;">เปิดเสียงคุณหมอสรุปผล</div>
                                    <div style="font-size: 11.5px; opacity: 0.92; font-weight: 600;">(แตะเพื่อให้ระบบอ่านให้ชาวบ้านฟัง)</div>
                                </div>
                            </div>
                        </button>

                        <!-- Speech Quote Bubble (Neumorphic Flat/Inset Well) -->
                        <div style="
                            background: var(--bg-card); 
                            border-radius: 16px; 
                            padding: 12px 14px; 
                            font-size: 13.5px; 
                            color: var(--text-primary); 
                            line-height: 1.55; 
                            font-weight: 600; 
                            box-shadow: var(--neumorph-flat);
                            border: 1px solid var(--border-color, transparent);
                        ">
                            <div style="font-size: 12px; font-weight: 800; color: #10B981; margin-bottom: 5px; display: flex; align-items: center; gap: 6px;">
                                <span style="width: 20px; height: 20px; display: inline-block;">
                                    <svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" width="100%" height="100%">
                                        <circle cx="50" cy="50" r="44" stroke-width="7" />
                                        <circle cx="43" cy="37" r="5" fill="currentColor" stroke="none" />
                                        <path d="M 47 28 L 57 44 L 49 44 C 49 49 45 53 39 56 L 39 68" stroke-width="7" />
                                        <path d="M 59 47 C 62.5 50 62.5 54 59 57" stroke-width="6" />
                                        <path d="M 66 41 C 72.5 47 72.5 57 66 63" stroke-width="6" />
                                    </svg>
                                </span>
                                <span>คำพูดสรุปผลตรวจจากคุณหมอ:</span>
                            </div>
                            <span style="color: #10B981; font-size: 16px; font-weight: 900; margin-right: 4px;">“</span>${result.what_to_say}<span style="color: #10B981; font-size: 16px; font-weight: 900; margin-left: 4px;">”</span>
                        </div>
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
