/**
 * Clinical Guidance & Health Progress Engine for NCD Portal (Simple & Easy)
 * รพ.สต. และ อสม. หมอคนที่ 1 — สสอ.ตาลสุม
 */

(function(window) {
    'use strict';

    const CARE_LEVEL_CONFIG = {
        good:     { label: 'ดูแลปกติ (Good)', badgeClass: 'bg-success text-white', color: '#10B981', icon: '🟢', days: 90 },
        fair:     { label: 'ดูแลพิเศษ (Fair)', badgeClass: 'bg-warning text-dark', color: '#F59E0B', icon: '🟡', days: 30 },
        poor:     { label: 'ดูแลมากพิเศษ (Poor)', badgeClass: 'bg-orange text-white', color: '#F97316', icon: '🟠', days: 14 },
        critical: { label: 'ดูแลเร่งด่วน (Critical)', badgeClass: 'bg-danger text-white', color: '#EF4444', icon: '🔴', days: 7 }
    };

    const SLEEP_CONFIG = {
        good:     { label: 'หลับสนิทดี', icon: '😴', desc: 'พักผ่อนเพียงพอ หลับสนิทตลอดคืน' },
        restless: { label: 'หลับๆ ตื่นๆ', icon: '🥱', desc: 'ตื่นกลางดึกบ่อย หรือหลับไม่ต่อเนื่อง' },
        poor:     { label: 'นอนไม่ค่อยหลับ / หลับยาก', icon: '😫', desc: 'นอนหลับยาก ใช้เวลานาน หรือนอนน้อยมาก' }
    };

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

        // 1. ประเมิน Care Level
        let careLevel = 'good';
        let isEmergency = false;
        let emergencyReasons = [];

        // เช็คภาวะวิกฤต (Critical / Emergency)
        if (sys >= 180 || dia >= 110) {
            careLevel = 'critical';
            isEmergency = true;
            emergencyReasons.push('ความดันโลหิตสูงวิกฤต (≥180/110 mmHg) เสี่ยงเส้นเลือดสมองแตก/ตีบเฉียบพลัน');
        }
        if (fbs && fbs >= 200) {
            careLevel = 'critical';
            isEmergency = true;
            emergencyReasons.push('ระดับน้ำตาลในเลือดสูงมาก (≥200 mg/dL)');
        }
        if (fbs && fbs < 70) {
            careLevel = 'critical';
            isEmergency = true;
            emergencyReasons.push('ภาวะน้ำตาลในเลือดต่ำ (<70 mg/dL) เสี่ยงหมดสติ/ช็อก');
        }

        // ถ้าไม่วิกฤต ประเมินตามเกณฑ์ปกติ
        if (!isEmergency) {
            if ((sys >= 140 || dia >= 90) || (fbs && fbs >= 126)) {
                careLevel = 'poor';
            } else if ((sys >= 120 || dia >= 80) || (fbs && fbs >= 100) || (bmi && bmi >= 25)) {
                careLevel = 'fair';
            } else {
                careLevel = 'good';
            }
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
        let progressLabel = 'ค่าตั้งต้น (ตรวจครั้งแรก)';
        let progressBadge = 'badge bg-secondary text-white';
        let progressIcon = '⚪';
        let progressText = 'บันทึกเป็นข้อมูลสุขภาพตั้งต้นสำหรับติดตามในรอบถัดไป';
        let changeDetails = [];

        if (prev) {
            const prevSys = parseFloat(prev.bp_sys) || null;
            const prevDia = parseFloat(prev.bp_dia) || null;
            const prevFbs = parseFloat(prev.fbs) || null;
            const prevWeight = parseFloat(prev.weight) || null;

            let score = 0; // +1 ดีขึ้น, -1 แย่ลง

            if (sys && prevSys) {
                const diffSys = sys - prevSys;
                if (diffSys <= -5) {
                    score += 1;
                    changeDetails.push(`ความดันตัวบนลดลง ${Math.abs(diffSys)} mmHg (${prevSys} ➔ ${sys}) ✅`);
                } else if (diffSys >= 8) {
                    score -= 1;
                    changeDetails.push(`ความดันตัวบนเพิ่มขึ้น ${diffSys} mmHg (${prevSys} ➔ ${sys}) ⚠️`);
                } else {
                    changeDetails.push(`ความดันคงที่ (${prevSys} ➔ ${sys} mmHg)`);
                }
            }

            if (fbs && prevFbs) {
                const diffFbs = fbs - prevFbs;
                if (diffFbs <= -8) {
                    score += 1;
                    changeDetails.push(`น้ำตาลลดลง ${Math.abs(diffFbs)} mg/dL (${prevFbs} ➔ ${fbs}) ✅`);
                } else if (diffFbs >= 10) {
                    score -= 1;
                    changeDetails.push(`น้ำตาลเพิ่มขึ้น ${diffFbs} mg/dL (${prevFbs} ➔ ${fbs}) ⚠️`);
                } else {
                    changeDetails.push(`น้ำตาลคงที่ (${prevFbs} ➔ ${fbs} mg/dL)`);
                }
            }

            if (weight && prevWeight && bmi >= 23) {
                const diffW = +(weight - prevWeight).toFixed(1);
                if (diffW <= -0.5) {
                    score += 0.5;
                    changeDetails.push(`น้ำหนักลดลง ${Math.abs(diffW)} kg (${prevWeight} ➔ ${weight}) ✅`);
                } else if (diffW >= 1.0) {
                    score -= 0.5;
                    changeDetails.push(`น้ำหนักเพิ่มขึ้น ${diffW} kg (${prevWeight} ➔ ${weight}) ⚠️`);
                }
            }

            if (score > 0) {
                healthProgress = 'improved';
                progressLabel = 'ดีขึ้น (Improved)';
                progressBadge = 'badge bg-success text-white';
                progressIcon = '🟢';
                progressText = 'สุขภาพดีขึ้นกว่ารอบที่แล้ว! ควบคุมความดัน/น้ำตาลได้มีพัฒนาการ';
            } else if (score < 0) {
                healthProgress = 'worsened';
                progressLabel = 'ต้องระวัง / แย่ลง (Worsened)';
                progressBadge = 'badge bg-danger text-white';
                progressIcon = '🔴';
                progressText = 'ค่าสุขภาพสูงขึ้นกว่ารอบก่อนหน้า ควรใส่ใจการคุมอาหาร ทานยา และการพักผ่อนเป็นพิเศษ';
            } else {
                healthProgress = 'stable';
                progressLabel = 'ทรงตัว (Stable)';
                progressBadge = 'badge bg-warning text-dark';
                progressIcon = '🟡';
                progressText = 'ระดับสุขภาพคงที่ ควบคุมได้ในเกณฑ์เดิม รักษาระดับต่อไป';
            }
        }

        // 3. สร้าง Clinical Guidance & Scripting (What to say / What to ask / Tips)
        let whatToSay = '';
        let whatToAsk = [];
        let tips = [];

        if (isEmergency) {
            whatToSay = '"ผลตรวจรอบนี้อยู่ในเกณฑ์ที่ต้องเฝ้าระวังด่วนค่ะ ไม่ต้องตกใจนะคะ เดี๋ยวหนูประสาน รพ.สต. ให้คุณหมอดูแลทันทีค่ะ"';
            whatToAsk = [
                'มีอาการเวียนศีรษะ ตาพร่ามัว เจ็บแน่นหน้าอก หรือชาครึ่งซีกไหม?',
                'ได้ทานยาประจำตัวครบไหม หรือลืมทานยาช่วงนี้?',
                'ช่วง 2-3 วันนี้ดื่มน้ำหวาน กินผลไม้หวานจัด หรือมีเรื่องเครียดมากไหม?'
            ];
            tips.push('ให้นั่งพักในที่อากาศถ่ายเท ไม่ลุกเดินกะทันหัน');
            tips.push('งดอาหารหวาน มัน เค็ม เด็ดขาด');
            tips.push('ติดต่อ รพ.สต. หรือกดปุ่มโทร 1669 เพื่อส่งตรวจยืนยัน');
        } else if (healthProgress === 'improved') {
            whatToSay = '"ยินดีด้วยนะคะ! ผลตรวจรอบนี้ดีขึ้นกว่าเดิมมากเลย ดูแลตัวเองได้ยอดเยี่ยมมาก ทำแบบนี้ต่อไปเรื่อยๆ นะคะ"';
            whatToAsk = [
                'รอบนี้ปรับเปลี่ยนอะไรบ้างคะ เช่น ลดข้าว ลดหวาน หรือออกกำลังกายเพิ่ม?',
                'นอนหลับสบายดีไหมช่วงนี้?'
            ];
            tips.push('ชื่นชมและให้กำลังใจเพื่อสร้างแรงจูงใจต่อเนื่อง');
            tips.push('คงพฤติกรรมการกินยาและ 3อ. 2ส. 1น. ให้สม่ำเสมอ');
        } else if (careLevel === 'poor' || healthProgress === 'worsened') {
            whatToSay = '"ผลตรวจรอบนี้เริ่มสูงขึ้นนิดหน่อยนะคะ ไม่เป็นไรค่ะ เรามาช่วยกันปรับอาหารและการใช้ชีวิตกันใหม่นะคะ"';
            whatToAsk = [
                'ช่วงนี้ทานแกงกะทิ ของทอด น้ำอัดลม หรือผลไม้หวานบ่อยไหม?',
                'ได้ทานยาตรงเวลาทุกมื้อไหมคะ?',
                'ช่วงนี้นอนดึก หลับๆ ตื่นๆ หรือมีเรื่องกังวลใจไหม?'
            ];
            tips.push('ลดข้าว/แป้งเหลือ 1 ทัพพีต่อมื้อ เน้นผักต้มผักสด');
            tips.push('งดเครื่องดื่มรสหวาน ขนมหวาน และของเค็มจัด');
            tips.push('เดินแกว่งแขนเบาๆ หลังมื้ออาหารวันละ 20-30 นาที');
        } else {
            whatToSay = '"ผลสุขภาพโดยรวมอยู่ในเกณฑ์ปกติค่ะ ดูแลสุขภาพได้ดีมาก ทำต่อเนื่องไปนะคะ"';
            whatToAsk = [
                'ทานอาหารครบ 5 หมู่ ดื่มน้ำเปล่าวันละ 6-8 แก้วไหมคะ?',
                'นอนหลับสนิทดีไหมช่วงนี้?'
            ];
            tips.push('รักษาการรับประทานอาหารรสจืด ไม่หวาน ไม่มัน');
            tips.push('ออกกำลังกายสม่ำเสมอสัปดาห์ละ 3-5 วัน');
        }

        // เสริมคำแนะนำเรื่องการนอนหลับ (1น.)
        if (sleep === 'poor') {
            tips.push('😴 สุขอนามัยการนอน: งดชา กาแฟ น้ำอัดลมช่วงบ่าย เข้านอนเวลาเดิม และงดดูจอมือถือก่อนนอน 30 นาที');
        } else if (sleep === 'restless') {
            tips.push('😴 สุขอนามัยการนอน: ดื่มน้ำอุ่นก่อนนอน ทำจิตใจให้ผ่อนคลาย เพื่อช่วยให้หลับสนิทต่อเนื่อง');
        }

        return {
            care_level: careLevel,
            care_meta: careMeta,
            is_emergency: isEmergency,
            emergency_reasons: emergencyReasons,
            next_visit_days: nextDays,
            next_visit_date: nextDateIso,
            next_visit_thai: nextDateThai,
            health_progress: healthProgress,
            progress_label: progressLabel,
            progress_badge: progressBadge,
            progress_icon: progressIcon,
            progress_text: progressText,
            change_details: changeDetails,
            sleep_quality: sleep,
            sleep_meta: SLEEP_CONFIG[sleep] || SLEEP_CONFIG.good,
            what_to_say: whatToSay,
            what_to_ask: whatToAsk,
            tips: tips
        };
    }

    /**
     * Render HTML Card แสดงคำแนะนำและบทสนทนา (Simple & Easy UI)
     */
    function renderGuidanceCard(result) {
        let emergencyHtml = '';
        if (result.is_emergency) {
            emergencyHtml = `
                <div class="alert alert-danger border-2 shadow-sm mb-3 text-start" style="border-radius:14px; background:#FEF2F2; border-color:#EF4444;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="fs-4">🚨</span>
                        <h6 class="mb-0 fw-bold text-danger">ภาวะค่าวิตกกังวล — ต้องดำเนินการเร่งด่วน</h6>
                    </div>
                    <ul class="small mb-3 ps-3 text-danger">
                        ${result.emergency_reasons.map(r => `<li><strong>${r}</strong></li>`).join('')}
                    </ul>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="tel:1669" class="btn btn-danger btn-sm fw-bold px-3 shadow-sm">
                            <i class="bi bi-telephone-fill me-1"></i> โทร 1669 (แพทย์ฉุกเฉิน)
                        </a>
                        <button type="button" class="btn btn-outline-danger btn-sm fw-bold" onclick="alert('กรุณาติดต่อเจ้าหน้าที่ รพ.สต. ในพื้นที่เพื่อส่งตัวเข้ารับการตรวจยืนยัน')">
                            <i class="bi bi-hospital me-1"></i> โทรแจ้ง จนท. รพ.สต.
                        </button>
                    </div>
                </div>
            `;
        }

        let changeHtml = '';
        if (result.change_details && result.change_details.length > 0) {
            changeHtml = `
                <div class="mb-3 p-2 rounded bg-light border text-start small">
                    <div class="fw-bold text-secondary mb-1">📊 สรุปการเปลี่ยนแปลงจากรอบก่อนหน้า:</div>
                    <ul class="mb-0 ps-3">
                        ${result.change_details.map(c => `<li>${c}</li>`).join('')}
                    </ul>
                </div>
            `;
        }

        let html = `
            <div class="card border-0 shadow-sm text-start mb-3" style="border-radius:16px; overflow:hidden;">
                <div class="card-header bg-gradient py-3 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2" style="background: linear-gradient(135deg, #1E293B 0%, #334155 100%); color:white;">
                    <div>
                        <span class="badge ${result.progress_badge} px-2 py-1 fs-6">${result.progress_icon} ${result.progress_label}</span>
                    </div>
                    <div class="small opacity-75">
                        📅 นัดครั้งถัดไป: <strong>${result.next_visit_thai}</strong> (อีก ${result.next_visit_days} วัน)
                    </div>
                </div>
                <div class="card-body p-3">
                    ${emergencyHtml}
                    ${changeHtml}

                    <!-- 💬 บทพูดคุยแนะนำ (What to say) -->
                    <div class="p-3 rounded-3 mb-3" style="background:#F0FDF4; border-left:4px solid #10B981;">
                        <div class="small fw-bold text-success mb-1">
                            <i class="bi bi-chat-quote-fill me-1"></i> 💬 บทพูดชวนคุยกับชาวบ้าน (อสม. อ่านตามนี้ได้เลย):
                        </div>
                        <div class="fs-6 fst-italic text-dark">${result.what_to_say}</div>
                    </div>

                    <!-- 📋 คำถามชวนคุย (What to ask) -->
                    ${result.what_to_ask && result.what_to_ask.length > 0 ? `
                        <div class="mb-3 p-3 rounded-3" style="background:#F8FAFC; border-left:4px solid #3B82F6;">
                            <div class="small fw-bold text-primary mb-1">
                                <i class="bi bi-question-circle-fill me-1"></i> 📋 คำถามชวนคุยซักประวัติ:
                            </div>
                            <ul class="small mb-0 ps-3 text-secondary">
                                ${result.what_to_ask.map(q => `<li>${q}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}

                    <!-- ✅ คำแนะนำ 3อ. 2ส. 1น. -->
                    <div class="p-3 rounded-3" style="background:#FFFBEB; border-left:4px solid #F59E0B;">
                        <div class="small fw-bold text-warning-emphasis mb-1">
                            <i class="bi bi-check-circle-fill me-1"></i> ✅ คำแนะนำการปรับเปลี่ยนพฤติกรรม (3อ. 2ส. 1น.):
                        </div>
                        <ul class="small mb-0 ps-3 text-dark">
                            ${result.tips.map(t => `<li>${t}</li>`).join('')}
                        </ul>
                    </div>
                </div>
            </div>
        `;

        return html;
    }

    window.ClinicalGuidance = {
        analyze: analyze,
        renderGuidanceCard: renderGuidanceCard,
        CARE_LEVEL_CONFIG: CARE_LEVEL_CONFIG,
        SLEEP_CONFIG: SLEEP_CONFIG
    };

})(window);
