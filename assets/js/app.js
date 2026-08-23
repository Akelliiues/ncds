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
        toast.innerHTML = '🔄 กำลังอัปเดตแอป NCDs Portal...';
        toast.style.cssText = `
            position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
            background: #1e40af; color: #fff; padding: 12px 24px;
            border-radius: 24px; font-size: 15px; font-weight: 700;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4); z-index: 99999;
            white-space: nowrap; animation: fadeInUp 0.3s ease;
        `;
        document.body.appendChild(toast);
    }

    // 2. Interactive App Install & Hub Modal (Triggered by Logo tap)
    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent default automatic browser banner
        e.preventDefault();
        // Stash the event so it can be triggered on user demand (e.g. Logo click)
        deferredPrompt = e;
        console.log('PWA: beforeinstallprompt captured and ready.');
    });

    window.addEventListener('appinstalled', (evt) => {
        console.log('PWA: App installed successfully!');
        deferredPrompt = null;
        if (typeof showToast === 'function') {
            showToast('🎉 ติดตั้งแอป NCDs Portal ลงเครื่องเรียบร้อยแล้ว!');
        }
    });

    // Function to check if running inside Standalone PWA mode
    window.isAppStandalone = function() {
        return window.matchMedia('(display-mode: standalone)').matches || 
               window.matchMedia('(display-mode: fullscreen)').matches || 
               (window.navigator.standalone === true) || 
               document.referrer.includes('android-app://');
    };

    // Open App Install Modal on Logo Click
    window.openAppInstallModal = function(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        let modal = document.getElementById('app-install-hub-modal');
        if (!modal) {
            modal = createAppInstallModalDOM();
        }

        renderInstallModalContent();
        modal.style.display = 'flex';
        modal.offsetHeight; // force reflow
        modal.classList.add('show');
    };

    window.closeAppInstallModal = function() {
        const modal = document.getElementById('app-install-hub-modal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 250);
        }
    };

    function createAppInstallModalDOM() {
        const modal = document.createElement('div');
        modal.id = 'app-install-hub-modal';
        modal.style.cssText = `
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            padding: 16px;
            opacity: 0;
            transition: opacity 0.25s ease;
            box-sizing: border-box;
        `;

        modal.innerHTML = `
            <div id="app-install-modal-card" style="background: var(--bg-card, #ffffff); color: var(--text-primary, #0d2c54); width: 100%; max-width: 440px; border-radius: 24px; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto; text-align: center; position: relative; border: 1px solid var(--border-color, rgba(0,0,0,0.1)); transform: scale(0.92); transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                <button type="button" onclick="closeAppInstallModal()" style="position: absolute; top: 16px; right: 16px; background: rgba(128,128,128,0.15); border: none; border-radius: 50%; width: 32px; height: 32px; font-size: 16px; font-weight: bold; cursor: pointer; color: var(--text-secondary, #64748b); display: flex; align-items: center; justify-content: center;">✕</button>
                <div id="app-install-dynamic-content"></div>
            </div>
        `;

        modal.addEventListener('click', (ev) => {
            if (ev.target === modal) {
                closeAppInstallModal();
            }
        });

        document.body.appendChild(modal);
        return modal;
    }

    function renderInstallModalContent() {
        const container = document.getElementById('app-install-dynamic-content');
        if (!container) return;

        const isStandalone = window.isAppStandalone();
        const isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
        const hasPrompt = !!deferredPrompt;

        let iconSrc = 'assets/icon.png';
        if (window.location.pathname.includes('/vhv/') || window.location.pathname.includes('/admin/')) {
            iconSrc = '../assets/icon.png';
        }

        let actionSectionHtml = '';

        if (isStandalone) {
            actionSectionHtml = `
                <div style="background: rgba(16, 185, 129, 0.12); border: 2px solid #10b981; border-radius: 16px; padding: 18px 14px; margin-bottom: 18px; text-align: center;">
                    <div style="font-size: 34px; margin-bottom: 4px;">✅</div>
                    <div style="color: #10b981; font-weight: 800; font-size: 16px; margin-bottom: 4px;">ติดตั้งบนอุปกรณ์นี้เรียบร้อยแล้ว</div>
                    <div style="color: var(--text-secondary, #475569); font-size: 13px; line-height: 1.5;">กำลังเปิดใช้งานในโหมดแอปพลิเคชันเต็มหน้าจอ (Standalone App) รองรับการใช้งานออฟไลน์เต็มรูปแบบ</div>
                </div>
            `;
        } else if (hasPrompt) {
            actionSectionHtml = `
                <div style="background: rgba(59, 130, 246, 0.08); border-radius: 16px; padding: 14px; margin-bottom: 16px; text-align: left; border: 1px dashed rgba(59, 130, 246, 0.3);">
                    <div style="font-size: 13px; font-weight: 800; color: #2563eb; margin-bottom: 8px; display: flex; align-items: center; gap: 4px;">
                        <span>🔒</span> <span>สิทธิ์การใช้งานที่จำเป็นในหน้างาน:</span>
                    </div>
                    <div style="display: flex; gap: 12px; font-size: 13px; color: var(--text-primary, #1e293b); flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" id="modal-perm-gps" checked style="accent-color: #10b981; width: 16px; height: 16px;">
                            <span>📍 พิกัด GPS (ตำแหน่งบ้าน)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" id="modal-perm-camera" checked style="accent-color: #10b981; width: 16px; height: 16px;">
                            <span>📷 กล้อง (สแกน QR)</span>
                        </label>
                    </div>
                </div>
                <button type="button" onclick="triggerPwaPromptFromModal()" style="width: 100%; font-size: 16px; font-weight: 800; padding: 14px; border-radius: 16px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4); margin-bottom: 12px; transition: transform 0.15s;">
                    <span>📲</span> <span>กดเพื่อติดตั้งลงหน้าจอมือถือทันที</span>
                </button>
            `;
        } else if (isIos) {
            actionSectionHtml = `
                <div style="background: rgba(168, 85, 247, 0.08); border: 2px solid rgba(168, 85, 247, 0.25); border-radius: 16px; padding: 16px; text-align: left; margin-bottom: 16px;">
                    <div style="font-weight: 800; color: #9333ea; font-size: 15px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                        <span>🍎</span> <span>วิธีติดตั้งบน iPhone / iPad:</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13.5px; color: var(--text-primary, #334155);">
                        <div style="display: flex; align-items: flex-start; gap: 8px;">
                            <span style="background: #9333ea; color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; flex-shrink: 0;">1</span>
                            <span>แตะปุ่ม <strong>แชร์ (Share 📤)</strong> ที่แถบเมนูด้านล่างของ Safari</span>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 8px;">
                            <span style="background: #9333ea; color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; flex-shrink: 0;">2</span>
                            <span>เลื่อนลงมาแล้วเลือก <strong>"เพิ่มไปยังหน้าจอโฮม" (Add to Home Screen ➕)</strong></span>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 8px;">
                            <span style="background: #9333ea; color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; flex-shrink: 0;">3</span>
                            <span>แตะ <strong>"เพิ่ม" (Add)</strong> ที่มุมบนขวา</span>
                        </div>
                    </div>
                    <button type="button" onclick="requestAppPermissions()" style="margin-top: 14px; width: 100%; background: linear-gradient(135deg, #a855f7, #7e22ce); color: white; border: none; padding: 10px; border-radius: 12px; font-size: 13px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 12px rgba(168, 85, 247, 0.3);">
                        📍📷 อนุญาตสิทธิ์ GPS & กล้องถ่ายรูป
                    </button>
                </div>
            `;
        } else {
            actionSectionHtml = `
                <div style="background: var(--bg-darker, #f1f5f9); border-radius: 16px; padding: 16px; margin-bottom: 16px; text-align: left; border: 1px solid var(--border-color, rgba(0,0,0,0.08));">
                    <div style="font-weight: 800; color: var(--color-primary, #0d2c54); font-size: 15px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                        <span>📲</span> <span>วิธีติดตั้งลงหน้าจอหลัก:</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13.5px; color: var(--text-primary, #334155);">
                        <div style="display: flex; align-items: flex-start; gap: 8px;">
                            <span style="background: var(--color-primary, #0d2c54); color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; flex-shrink: 0;">1</span>
                            <span>แตะที่ <strong>เมนูเบราว์เซอร์ (จุด 3 จุด ⋮ หรือ ⋯)</strong> ที่มุมบนขวา</span>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 8px;">
                            <span style="background: var(--color-primary, #0d2c54); color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; flex-shrink: 0;">2</span>
                            <span>เลือก <strong>"ติดตั้งแอปพลิเคชัน" (Install app)</strong> หรือ <strong>"เพิ่มลงในหน้าจอหลัก"</strong></span>
                        </div>
                    </div>
                    <button type="button" onclick="requestAppPermissions()" style="margin-top: 14px; width: 100%; background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; border: none; padding: 10px; border-radius: 12px; font-size: 13px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
                        📍📷 อนุญาตสิทธิ์ GPS & กล้องถ่ายรูป
                    </button>
                </div>
            `;
        }

        container.innerHTML = `
            <div style="margin-bottom: 16px;">
                <img src="${iconSrc}" alt="NCDs Portal Logo" style="width: 68px; height: 68px; border-radius: 18px; box-shadow: 0 6px 16px rgba(0,0,0,0.15); margin-bottom: 10px;">
                <h3 style="margin: 0; font-size: 19px; font-weight: 800; color: var(--color-accent, #0d2c54);">NCDs by อสม. ตาลสุม</h3>
                <p style="margin: 4px 0 0 0; color: var(--text-secondary, #64748b); font-size: 13.5px;">ระบบคัดกรองและปรับเปลี่ยนพฤติกรรมสุขภาพเชิงรุก</p>
            </div>

            ${actionSectionHtml}

            <!-- App Highlights -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; text-align: left;">
                <div style="background: var(--bg-darker, #f8fafc); padding: 10px 12px; border-radius: 12px; border: 1px solid var(--border-color, rgba(0,0,0,0.06));">
                    <div style="font-size: 13px; font-weight: 800; color: var(--text-primary, #0f172a); margin-bottom: 2px;">⚡ เปิดเร็วทันใจ</div>
                    <div style="font-size: 11.5px; color: var(--text-secondary, #64748b);">เต็มหน้าจอ ไร้แถบ URL</div>
                </div>
                <div style="background: var(--bg-darker, #f8fafc); padding: 10px 12px; border-radius: 12px; border: 1px solid var(--border-color, rgba(0,0,0,0.06));">
                    <div style="font-size: 13px; font-weight: 800; color: var(--text-primary, #0f172a); margin-bottom: 2px;">📶 ทำงานออฟไลน์</div>
                    <div style="font-size: 11.5px; color: var(--text-secondary, #64748b);">บันทึกได้แม้อยู่ในจุดอับเน็ต</div>
                </div>
            </div>

            <!-- Footer Links -->
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color, rgba(0,0,0,0.1)); padding-top: 14px;">
                <button type="button" onclick="closeAppInstallModal(); if(typeof openDevModal==='function') openDevModal(event);" style="background: none; border: none; color: var(--color-primary, #2563eb); font-size: 13px; font-weight: 800; cursor: pointer; padding: 0;">
                    👨‍💻 ข้อมูลผู้พัฒนา & ระบบ
                </button>
                <button type="button" onclick="closeAppInstallModal()" style="background: var(--bg-darker, #e2e8f0); color: var(--text-secondary, #475569); border: none; padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 800; cursor: pointer;">
                    ปิด
                </button>
            </div>
        `;

        // Add class show for animation
        const modal = document.getElementById('app-install-hub-modal');
        if (modal) {
            modal.style.opacity = '1';
            const card = document.getElementById('app-install-modal-card');
            if (card) card.style.transform = 'scale(1)';
        }
    }

    // Trigger PWA Prompt from Modal
    window.triggerPwaPromptFromModal = async function() {
        const reqGps = document.getElementById('modal-perm-gps')?.checked;
        const reqCamera = document.getElementById('modal-perm-camera')?.checked;

        if (reqGps || reqCamera) {
            await window.requestAppPermissions();
        }

        if (deferredPrompt) {
            deferredPrompt.prompt();
            const choiceResult = await deferredPrompt.userChoice;
            if (choiceResult.outcome === 'accepted') {
                console.log('PWA: User accepted install from modal');
                closeAppInstallModal();
            } else {
                console.log('PWA: User dismissed install from modal');
            }
            deferredPrompt = null;
        } else {
            alert('เบราว์เซอร์กำลังประมวลผลการติดตั้ง กรุณาดูไอคอนที่หน้าจอมือถือของท่านครับ');
            closeAppInstallModal();
        }
    };
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