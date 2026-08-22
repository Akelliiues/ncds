// assets/js/app.js
// PWA Service Worker Registration & Installation Prompt Handler

document.addEventListener('DOMContentLoaded', () => {
    // 1. Register Service Worker
    if ('serviceWorker' in navigator) {
        // Determine correct path to service-worker.js
        let swPath = 'service-worker.js';
        let swScope = './';

        if (window.location.pathname.includes('/vhv/')) {
            swPath = 'service-worker.js';
            swScope = './';
        } else {
            swPath = 'vhv/service-worker.js';
            swScope = '/vhv/';
        }

        navigator.serviceWorker.register(swPath, { scope: swScope })
            .then(reg => {
                console.log('SW: Registered successfully with scope:', reg.scope);
                // Check for updates periodically (every 5 minutes)
                setInterval(() => {
                    reg.update().catch(e => console.log('SW: Update check failed', e));
                }, 5 * 60 * 1000);
            })
            .catch(err => {
                console.error('SW: Registration failed:', err);
            });

        // Auto reload when new SW is activated (clears old manifest cache)
        let refreshing = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (!refreshing) {
                refreshing = true;
                console.log('SW: New version activated, reloading...');
                showUpdateToast();
                setTimeout(() => window.location.reload(), 1500);
            }
        });

        // Listen for SW_UPDATED message (sent by new SW after activate)
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'SW_UPDATED') {
                console.log('SW: Update confirmed via message.');
            }
        });
    }

    // showUpdateToast: แสดง toast แจ้งผู้ใช้ว่าแอปกำลัง update (ล้าง cache เก่า)
    function showUpdateToast() {
        const existing = document.getElementById('sw-update-toast');
        if (existing) return;
        const toast = document.createElement('div');
        toast.id = 'sw-update-toast';
        toast.innerHTML = '🔄 กำลังอัปเดตแอป NCDs ตาลสุม...';
        toast.style.cssText = `
            position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
            background: #1e40af; color: #fff; padding: 12px 24px;
            border-radius: 24px; font-size: 15px; font-weight: 700;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4); z-index: 99999;
            white-space: nowrap; animation: fadeInUp 0.3s ease;
        `;
        document.body.appendChild(toast);
    }

    // 2. Custom PWA Install Banner for Android/Chrome
    let deferredPrompt;

    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent Chrome 67 and earlier from automatically showing the prompt
        e.preventDefault();
        // Stash the event so it can be triggered later.
        deferredPrompt = e;
        // Show the install banner
        showInstallBanner();
    });

    function showInstallBanner() {
        if (document.getElementById('pwa-install-banner')) return;

        // Inject Banner CSS dynamically
        const style = document.createElement('style');
        style.innerHTML = `
            .pwa-install-banner {
                position: fixed;
                bottom: 24px;
                left: 50%;
                transform: translateX(-50%) translateY(180%);
                width: 90%;
                max-width: 440px;
                background: linear-gradient(135deg, #1e293b, #0f172a);
                border: 2px solid #3b82f6;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.1);
                border-radius: 16px;
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 12px;
                z-index: 9999;
                transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                font-family: 'Sarabun', sans-serif;
                box-sizing: border-box;
            }
            .pwa-install-banner.show {
                transform: translateX(-50%) translateY(0);
            }
            .pwa-install-header {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .pwa-install-icon {
                font-size: 26px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(59, 130, 246, 0.15);
                width: 46px;
                height: 46px;
                border-radius: 12px;
                border: 1px solid rgba(59, 130, 246, 0.3);
                flex-shrink: 0;
            }
            .pwa-install-title {
                color: white;
                font-size: 15px;
                font-weight: 800;
                margin: 0;
                text-align: left;
            }
            .pwa-install-desc {
                color: #94a3b8;
                font-size: 12.5px;
                line-height: 1.4;
                margin: 4px 0 0 0;
                text-align: left;
            }
            .pwa-install-actions {
                display: flex;
                gap: 10px;
                margin-top: 4px;
            }
            .pwa-install-btn-confirm {
                flex: 2;
                background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                color: white;
                border: none;
                padding: 10px 16px;
                border-radius: 10px;
                font-size: 13.5px;
                font-weight: bold;
                cursor: pointer;
                text-align: center;
                box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
                transition: all 0.2s;
            }
            .pwa-install-btn-confirm:active {
                transform: scale(0.97);
            }
            .pwa-install-btn-cancel {
                flex: 1;
                background: rgba(255, 255, 255, 0.05);
                color: #94a3b8;
                border: 1px solid rgba(255, 255, 255, 0.1);
                padding: 10px;
                border-radius: 10px;
                font-size: 13.5px;
                font-weight: bold;
                cursor: pointer;
                text-align: center;
                transition: all 0.2s;
            }
        `;
        document.head.appendChild(style);

        // Inject Banner HTML dynamically
        const banner = document.createElement('div');
        banner.id = 'pwa-install-banner';
        banner.className = 'pwa-install-banner';
        banner.innerHTML = `
            <div class="pwa-install-header">
                <div class="pwa-install-icon">📲</div>
                <div>
                    <h4 class="pwa-install-title">ติดตั้งแอป NCDs ตาลสุม ลงเครื่อง</h4>
                    <p class="pwa-install-desc">ติดตั้งเพื่อใช้งานออฟไลน์ บันทึกข้อมูลคัดกรองได้รวดเร็วแม้ไม่มีสัญญาณอินเทอร์เน็ต</p>
                </div>
            </div>
            <div style="background: rgba(15, 23, 42, 0.6); padding: 10px; border-radius: 10px; border: 1px dashed rgba(56, 189, 248, 0.3); margin-top: 4px;">
                <div style="font-size: 12px; font-weight: 700; color: #38bdf8; margin-bottom: 6px; display: flex; align-items: center; gap: 4px;">
                    <span>🔒</span> <span>ขออนุญาตสิทธิ์ที่จำเป็นเพื่อใช้งานหน้างานราบรื่น:</span>
                </div>
                <div style="display: flex; gap: 12px; font-size: 12px; color: #e2e8f0; flex-wrap: wrap;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                        <input type="checkbox" id="perm-gps-check" checked style="accent-color: #10b981;">
                        <span>📍 พิกัด GPS (ตำแหน่งบ้าน)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                        <input type="checkbox" id="perm-camera-check" checked style="accent-color: #10b981;">
                        <span>📷 กล้อง (สแกน QR Code)</span>
                    </label>
                </div>
            </div>
            <div class="pwa-install-actions">
                <button class="pwa-install-btn-cancel" id="pwa-install-cancel">ไว้ทีหลัง</button>
                <button class="pwa-install-btn-confirm" id="pwa-install-confirm">ยินยอมสิทธิ์ & ติดตั้งทันที</button>
            </div>
        `;
        document.body.appendChild(banner);

        // Slide up
        setTimeout(() => {
            banner.classList.add('show');
        }, 1500); // Delay slightly for smoother user experience

        // Button clicks
        document.getElementById('pwa-install-confirm').addEventListener('click', async () => {
            banner.classList.remove('show');
            
            const reqGps = document.getElementById('perm-gps-check')?.checked;
            const reqCamera = document.getElementById('perm-camera-check')?.checked;

            // 1. Pre-request GPS Location permission
            if (reqGps && navigator.geolocation) {
                try {
                    await new Promise((resolve) => {
                        navigator.geolocation.getCurrentPosition(
                            pos => { console.log('PWA: GPS Permission Granted', pos); resolve(true); },
                            err => { console.warn('PWA: GPS Permission Refused/Timeout', err); resolve(false); },
                            { timeout: 5000, enableHighAccuracy: true }
                        );
                    });
                } catch (e) {}
            }

            // 2. Pre-request Camera permission
            if (reqCamera && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                    // Stop stream immediately after permission granted
                    stream.getTracks().forEach(track => track.stop());
                    console.log('PWA: Camera Permission Granted');
                } catch (e) {
                    console.warn('PWA: Camera Permission Refused/Error', e);
                }
            }

            // 3. Trigger native PWA install prompt
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('PWA: User accepted install');
                    } else {
                        console.log('PWA: User dismissed install');
                    }
                    deferredPrompt = null;
                });
            }
        });

        document.getElementById('pwa-install-cancel').addEventListener('click', () => {
            banner.classList.remove('show');
        });
    }

    // 3. Custom iOS Safari Prompt Guide
    const isIos = () => {
        const userAgent = window.navigator.userAgent.toLowerCase();
        return /iphone|ipad|ipod/.test(userAgent);
    };

    const isInStandaloneMode = () => {
        return ('standalone' in window.navigator) && (window.navigator.standalone);
    };

    if (isIos() && !isInStandaloneMode()) {
        showIosInstallPrompt();
    }

    function showIosInstallPrompt() {
        if (document.getElementById('ios-install-prompt')) return;

        // Inject iOS Prompt CSS dynamically
        const style = document.createElement('style');
        style.innerHTML = `
            .ios-install-prompt {
                position: fixed;
                bottom: 24px;
                left: 50%;
                transform: translateX(-50%) translateY(180%);
                width: 90%;
                max-width: 440px;
                background: linear-gradient(135deg, #1e293b, #0f172a);
                border: 2px solid #a855f7;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.1);
                border-radius: 16px;
                padding: 16px;
                z-index: 9999;
                transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                font-family: 'Sarabun', sans-serif;
                color: white;
                box-sizing: border-box;
            }
            .ios-install-prompt.show {
                transform: translateX(-50%) translateY(0);
            }
            .ios-prompt-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 12px;
            }
            .ios-prompt-icon {
                font-size: 26px;
                background: rgba(168, 85, 247, 0.15);
                width: 46px;
                height: 46px;
                border-radius: 12px;
                border: 1px solid rgba(168, 85, 247, 0.3);
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .ios-prompt-title {
                font-size: 15px;
                font-weight: 800;
                margin: 0;
                text-align: left;
            }
            .ios-prompt-instructions {
                font-size: 13px;
                color: #94a3b8;
                line-height: 1.6;
                margin: 0 0 14px 0;
                text-align: left;
            }
            .ios-prompt-instruction-item {
                display: flex;
                align-items: flex-start;
                gap: 8px;
                margin-bottom: 8px;
            }
            .ios-prompt-actions {
                display: flex;
                justify-content: flex-end;
            }
            .ios-prompt-btn-close {
                background: rgba(255, 255, 255, 0.05);
                color: #94a3b8;
                border: 1px solid rgba(255, 255, 255, 0.1);
                padding: 8px 16px;
                border-radius: 10px;
                font-size: 13px;
                font-weight: bold;
                cursor: pointer;
                transition: all 0.2s;
            }
            .ios-prompt-btn-close:active {
                background: rgba(255, 255, 255, 0.1);
            }
        `;
        document.head.appendChild(style);

        // Inject iOS Prompt HTML dynamically
        const banner = document.createElement('div');
        banner.id = 'ios-install-prompt';
        banner.className = 'ios-install-prompt';
        banner.innerHTML = `
            <div class="ios-prompt-header">
                <div class="ios-prompt-icon">🍎</div>
                <div>
                    <h4 class="ios-prompt-title">ติดตั้ง NCD ตาลสุม บน iPhone/iPad</h4>
                </div>
            </div>
            <div class="ios-prompt-instructions">
                <div class="ios-prompt-instruction-item">
                    <span>1. แตะปุ่มแชร์ <strong>Share</strong> (ไอคอน <span style="font-size:16px;">⎋</span> หรือลูกศรชี้ขึ้นที่แถบ Safari ด้านล่าง)</span>
                </div>
                <div class="ios-prompt-instruction-item">
                    <span>2. เลื่อนลงด้านล่างแล้วเลือก <strong>"เพิ่มไปยังหน้าจอโฮม" (Add to Home Screen)</strong> ➕</span>
                </div>
                <div class="ios-prompt-instruction-item" style="color: #38bdf8; font-weight: 700; margin-top: 6px;">
                    <span>🔒 อย่าลืมเปิดอนุญาตพิกัด GPS และกล้องถ่ายรูปเพื่อปักหมุดและสแกน QR Code</span>
                </div>
            </div>
            <div class="ios-prompt-actions" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <button type="button" id="ios-request-perm" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 8px 12px; border-radius: 10px; font-size: 12px; font-weight: bold; cursor: pointer;">📍📷 อนุญาตสิทธิ์ GPS & กล้อง</button>
                <button class="ios-prompt-btn-close" id="ios-prompt-close">รับทราบ</button>
            </div>
        `;
        document.body.appendChild(banner);

        // Slide up
        setTimeout(() => {
            banner.classList.add('show');
        }, 2000);

        document.getElementById('ios-request-perm').addEventListener('click', async () => {
            const btn = document.getElementById('ios-request-perm');
            btn.innerText = '⏳ กำลังขอสิทธิ์...';
            const res = await window.requestAppPermissions();
            if (res.gps || res.camera) {
                btn.innerText = '✅ อนุญาตสิทธิ์เรียบร้อยแล้ว';
                btn.style.background = '#059669';
            } else {
                btn.innerText = '⚠️ กรุณากดอนุญาตในป๊อบอัป';
            }
        });

        document.getElementById('ios-prompt-close').addEventListener('click', () => {
            banner.classList.remove('show');
        });
    }
});

// Global Helper: Request App Permissions (GPS Location & Camera)
window.requestAppPermissions = async function() {
    let gpsStatus = false;
    let cameraStatus = false;

    // 1. Request GPS Location
    if (navigator.geolocation) {
        gpsStatus = await new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition(
                pos => resolve(true),
                err => resolve(false),
                { timeout: 6000, enableHighAccuracy: true }
            );
        });
    }

    // 2. Request Camera Access
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            stream.getTracks().forEach(track => track.stop());
            cameraStatus = true;
        } catch (e) {
            cameraStatus = false;
        }
    }

    return { gps: gpsStatus, camera: cameraStatus };
};

// ==========================================
// restored helpers & NumPad class for VHV screening
// ==========================================

// Geo-location Helper with Promise
function getCurrentLocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Geolocation is not supported by your browser.'));
            return;
        }

        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        };

        navigator.geolocation.getCurrentPosition(
            position => {
                resolve({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy
                });
            },
            error => {
                reject(error);
            },
            options
        );
    });
}

// Offline/Online Status Monitor
window.addEventListener('online', updateOnlineStatus);
window.addEventListener('offline', updateOnlineStatus);

function updateOnlineStatus() {
    const isOnline = navigator.onLine;
    let statusBanner = document.getElementById('offline-banner');
    
    if (!isOnline) {
        if (!statusBanner) {
            statusBanner = document.createElement('div');
            statusBanner.id = 'offline-banner';
            statusBanner.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                background-color: #ef4444;
                color: white;
                text-align: center;
                padding: 10px;
                font-weight: bold;
                z-index: 9999;
                font-size: 16px;
            `;
            statusBanner.innerHTML = '⚠️ คุณกำลังใช้งานโหมดออฟไลน์ - ข้อมูลจะถูกบันทึกเมื่อเชื่อมต่ออินเทอร์เน็ต';
            document.body.prepend(statusBanner);
        }
    } else {
        if (statusBanner) {
            statusBanner.remove();
        }
    }
}

// Zero-Typing Numeric Pad Helper
class VhvNumPad {
    constructor(inputId, padContainerId, displayBoxId = null) {
        this.input = document.getElementById(inputId);
        this.container = document.getElementById(padContainerId);
        this.displayBox = displayBoxId ? document.getElementById(displayBoxId) : null;
        this.currentValue = '';
        if (this.input && this.container) {
            this.init();
        }
    }

    init() {
        this.container.innerHTML = `
            <div class="numpad-grid">
                <button type="button" class="numpad-btn" data-val="1">1</button>
                <button type="button" class="numpad-btn" data-val="2">2</button>
                <button type="button" class="numpad-btn" data-val="3">3</button>
                <button type="button" class="numpad-btn" data-val="4">4</button>
                <button type="button" class="numpad-btn" data-val="5">5</button>
                <button type="button" class="numpad-btn" data-val="6">6</button>
                <button type="button" class="numpad-btn" data-val="7">7</button>
                <button type="button" class="numpad-btn" data-val="8">8</button>
                <button type="button" class="numpad-btn" data-val="9">9</button>
                <button type="button" class="numpad-btn btn-action" data-val=".">.</button>
                <button type="button" class="numpad-btn" data-val="0">0</button>
                <button type="button" class="numpad-btn btn-action" data-val="del">⌫</button>
            </div>
        `;

        this.container.querySelectorAll('.numpad-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const val = btn.getAttribute('data-val');
                this.handlePress(val);
            });
        });
    }

    handlePress(val) {
        if (val === 'del') {
            this.currentValue = this.currentValue.slice(0, -1);
        } else if (val === '.') {
            if (!this.currentValue.includes('.')) {
                this.currentValue += '.';
            }
        } else {
            // limit to length
            if (this.currentValue.length < 6) {
                this.currentValue += val;
            }
        }
        this.updateDisplay();
    }

    setValue(val) {
        this.currentValue = val.toString();
        this.updateDisplay();
    }
    
    updateDisplay() {
        this.input.value = this.currentValue;
        if (this.displayBox) {
            this.displayBox.innerText = this.currentValue || '0';
        }
        // Trigger input event programmatically
        const event = new Event('input', { bubbles: true });
        this.input.dispatchEvent(event);
    }
}

// GIS House-Level Map Privacy Jittering (PDPA Compliance ±50m)
window.getDeterministicPrivacyJitter = function(lat, lng, seedStr) {
    if (!lat || !lng) return { lat: 0, lng: 0 };
    let hash = 2166136261;
    const str = String(seedStr || (lat + ',' + lng));
    for (let i = 0; i < str.length; i++) {
        hash ^= str.charCodeAt(i);
        hash = (hash * 16777619) >>> 0;
    }
    const rx = ((hash & 0xFFFF) / 0xFFFF) - 0.5;
    const ry = (((hash >>> 16) & 0xFFFF) / 0xFFFF) - 0.5;
    return {
        lat: lat + (rx * 0.0009),
        lng: lng + (ry * 0.0009)
    };
};