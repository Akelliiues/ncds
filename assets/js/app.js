// assets/js/app.js
// PWA Service Worker Registration & Installation Prompt Handler (Forced Auto-Update Engine)

const CURRENT_APP_BUILD_ID = '20260827_1635';

document.addEventListener('DOMContentLoaded', () => {
    // 0. Proactive Cache & Build Version Validation
    if ('caches' in window) {
        const storedBuild = localStorage.getItem('ncd_app_build_id');
        if (storedBuild !== CURRENT_APP_BUILD_ID) {
            console.log('App: New build detected, flushing old cache storage...');
            caches.keys().then(keys => {
                return Promise.all(keys.map(key => caches.delete(key)));
            }).then(() => {
                localStorage.setItem('ncd_app_build_id', CURRENT_APP_BUILD_ID);
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.getRegistrations().then(regs => {
                        regs.forEach(reg => reg.update());
                    });
                }
            });
        }
    }

    // 1. Register Service Worker with Forced Immediate Update
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

                // Check for updates immediately on launch
                reg.update().catch(() => {});

                // If new SW is waiting, force skip waiting
                if (reg.waiting) {
                    reg.waiting.postMessage({ action: 'skipWaiting' });
                }

                reg.addEventListener('updatefound', () => {
                    const newWorker = reg.installing;
                    if (newWorker) {
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                newWorker.postMessage({ action: 'skipWaiting' });
                            }
                        });
                    }
                });

                // Check for updates periodically (every 2 minutes)
                setInterval(() => {
                    reg.update().catch(e => console.log('SW: Periodic update check failed', e));
                }, 2 * 60 * 1000);
            })
            .catch(err => {
                console.error('SW: Registration failed:', err);
            });

        // Auto reload when new SW is activated (clears old manifest & page cache)
        let refreshing = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (!refreshing) {
                refreshing = true;
                console.log('SW: New version activated, reloading with fresh assets...');
                showUpdateToast();
                setTimeout(() => {
                    window.location.reload();
                }, 800);
            }
        });

        // Listen for SW_UPDATED message (sent by new SW after activate)
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'SW_UPDATED') {
                console.log('SW: Update confirmed via message:', event.data.version);
            }
        });
    }

    // showUpdateToast: แสดง toast แจ้งผู้ใช้ว่าแอปกำลัง update (ล้าง cache เก่า)
    function showUpdateToast() {
        const existing = document.getElementById('sw-update-toast');
        if (existing) return;
        const toast = document.createElement('div');
        toast.id = 'sw-update-toast';
        toast.innerHTML = '✨ กำลังอัปเดตฟีเจอร์เวอร์ชันล่าสุด...';
        toast.style.cssText = `
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
            background: linear-gradient(135deg, #0284c7, #1e40af); color: #fff; padding: 12px 26px;
            border-radius: 999px; font-size: 14px; font-weight: 800;
            box-shadow: 0 8px 30px rgba(0,0,0,0.35); z-index: 9999999;
            white-space: nowrap; animation: fadeInUp 0.3s ease; border: 1.5px solid rgba(255,255,255,0.3);
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

// ==========================================
// VHV Offline Data & Sync Engine (ระบบส่งข้อมูลค้างส่ง - ภาษาเข้าใจง่ายสำหรับ อสม.)
// ==========================================
window.VhvSyncEngine = {
    isSyncing: false,

    getQueue: function() {
        try {
            return JSON.parse(localStorage.getItem('offline_submissions') || '[]');
        } catch (e) {
            return [];
        }
    },

    setQueue: function(queue) {
        localStorage.setItem('offline_submissions', JSON.stringify(queue));
        this.updateSyncUI();
    },

    getQueueCount: function() {
        return this.getQueue().length;
    },

    updateSyncUI: function() {
        const count = this.getQueueCount();
        const isOnline = navigator.onLine;
        
        // 1. Bottom floating friendly action card
        let banner = document.getElementById('offline-sync-floating-banner');
        if (count > 0) {
            if (!banner) {
                banner = document.createElement('div');
                banner.id = 'offline-sync-floating-banner';
                banner.style.cssText = `
                    position: fixed;
                    bottom: 24px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: calc(100% - 32px);
                    max-width: 460px;
                    background: var(--bg-card, #1e293b);
                    color: var(--text-primary, #ffffff);
                    border: 2px solid ${isOnline ? '#10B981' : '#F59E0B'};
                    border-radius: 20px;
                    padding: 14px 18px;
                    box-shadow: 0 12px 30px rgba(0,0,0,0.45);
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    box-sizing: border-box;
                    animation: slideUp 0.3s ease;
                `;
                document.body.appendChild(banner);
            }
            
            if (isOnline) {
                banner.style.borderColor = '#10B981';
                banner.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0;">
                        <span style="font-size: 26px; flex-shrink: 0;">📦</span>
                        <div style="min-width: 0;">
                            <div style="font-weight: 800; font-size: 14px; color: #10B981; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">มีข้อมูลรอส่ง ${count} คน</div>
                            <div style="font-size: 12px; color: var(--text-secondary, #94a3b8); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">มีเน็ตแล้ว พร้อมส่งเข้าระบบ</div>
                        </div>
                    </div>
                    <button type="button" onclick="window.VhvSyncEngine.syncAll()" style="background: #10B981; color: white; border: none; padding: 9px 15px; border-radius: 12px; font-weight: 800; font-size: 13px; cursor: pointer; white-space: nowrap; box-shadow: 0 4px 14px rgba(16,185,129,0.4); display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0;">
                        🚀 ส่งข้อมูลทันที
                    </button>
                `;
            } else {
                banner.style.borderColor = '#F59E0B';
                banner.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                        <span style="font-size: 24px; flex-shrink: 0;">📶</span>
                        <div>
                            <div style="font-weight: 800; font-size: 13.5px; color: #F59E0B;">เก็บในเครื่องแล้ว ${count} คน (ไม่มีเน็ต)</div>
                            <div style="font-size: 11.5px; color: var(--text-secondary, #94a3b8);">เมื่อต่อเน็ตแล้ว กดส่งข้อมูลได้ทันทีครับ</div>
                        </div>
                    </div>
                `;
            }
        } else {
            if (banner) {
                banner.remove();
            }
        }

        // 2. In-page static banner in vhv/index.php if container exists
        const pageContainer = document.getElementById('offline-sync-card-container');
        if (pageContainer) {
            if (count > 0) {
                pageContainer.style.display = 'block';
                if (isOnline) {
                    pageContainer.innerHTML = `
                        <div style="background: rgba(16,185,129,0.12); border: 2px solid #10B981; border-radius: 16px; padding: 14px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 28px;">📦</span>
                                <div>
                                    <h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #10B981;">มีข้อมูลคัดกรองที่บันทึกไว้ในเครื่อง (${count} รายการ)</h4>
                                    <p style="margin: 2px 0 0 0; font-size: 12.5px; color: var(--text-secondary);">มีสัญญาณอินเทอร์เน็ตแล้ว สามารถกดส่งข้อมูลเข้าระบบได้ทันที</p>
                                </div>
                            </div>
                            <button type="button" onclick="window.VhvSyncEngine.syncAll()" class="btn-giant btn-giant-primary" style="margin: 0; width: auto; padding: 10px 18px; font-size: 13.5px; border-radius: 12px; background: #10B981; border-color: #10B981; color: white; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 14px rgba(16,185,129,0.35);">
                                🚀 กดส่งข้อมูลเข้าระบบตอนนี้ (${count} คน)
                            </button>
                        </div>
                    `;
                } else {
                    pageContainer.innerHTML = `
                        <div style="background: rgba(245,158,11,0.12); border: 2px solid #F59E0B; border-radius: 16px; padding: 14px 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 28px;">📵</span>
                            <div>
                                <h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #D97706;">กำลังใช้งานโหมดไม่มีสัญญาณเน็ต (ออฟไลน์)</h4>
                                <p style="margin: 2px 0 0 0; font-size: 12.5px; color: var(--text-secondary);">บันทึกข้อมูลคัดกรองเก็บไว้ในโทรศัพท์แล้ว <strong>${count} รายการ</strong> (ปลอดภัย 100%) เมื่อกลับถึงจุดที่มีสัญญาณเน็ตให้กดส่งข้อมูลครับ</p>
                            </div>
                        </div>
                    `;
                }
            } else {
                pageContainer.style.display = 'none';
                pageContainer.innerHTML = '';
            }
        }
    },

    syncAll: async function() {
        if (this.isSyncing) return;

        const queue = this.getQueue();
        if (queue.length === 0) {
            alert('✅ ไม่มีข้อมูลค้างส่งในเครื่องครับ ข้อมูลทั้งหมดเป็นปัจจุบันแล้ว');
            return;
        }

        if (!navigator.onLine) {
            alert('⚠️ โทรศัพท์ยังไม่ได้เชื่อมต่ออินเทอร์เน็ตครับ\n\nกรุณาเปิดสัญญาณเน็ตมือถือ หรือเชื่อมต่อ Wi-Fi ก่อนกดส่งข้อมูลเข้าระบบครับ');
            return;
        }

        this.isSyncing = true;

        // Create or show progress overlay
        let overlay = document.getElementById('sync-progress-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'sync-progress-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(15, 23, 42, 0.75);
                backdrop-filter: blur(6px);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                box-sizing: border-box;
            `;
            document.body.appendChild(overlay);
        }
        overlay.style.display = 'flex';

        const totalItems = queue.length;
        let successCount = 0;
        let failCount = 0;

        const updateOverlayText = (currentIndex, residentName) => {
            overlay.innerHTML = `
                <div style="background: var(--bg-card, #1e293b); color: var(--text-primary, #ffffff); border-radius: 20px; padding: 28px 24px; max-width: 380px; width: 100%; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.5); border: 1px solid var(--border-color, rgba(255,255,255,0.1));">
                    <div style="font-size: 44px; margin-bottom: 12px;">🚀</div>
                    <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 800; color: var(--color-accent, #38bdf8);">กำลังส่งข้อมูลเข้าระบบ</h3>
                    <p style="margin: 0 0 14px 0; font-size: 13.5px; color: var(--text-secondary, #94a3b8);">
                        กำลังส่งคนที่ <strong>${currentIndex + 1}</strong> จากทั้งหมด <strong>${totalItems}</strong> คน
                    </p>
                    <div style="font-size: 13px; font-weight: 700; color: #10B981; margin-bottom: 16px; padding: 6px 12px; background: rgba(16,185,129,0.1); border-radius: 10px; display: inline-block;">
                        👤 ${residentName || 'ผู้รับการตรวจ'}
                    </div>
                    <div style="width: 100%; height: 8px; background: rgba(255,255,255,0.1); border-radius: 10px; overflow: hidden; margin-bottom: 12px;">
                        <div style="width: ${((currentIndex + 1) / totalItems) * 100}%; height: 100%; background: #10B981; transition: width 0.3s ease;"></div>
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted, #64748b);">กรุณาอย่าเพิ่งปิดหน้าจอนี้ จนกว่าจะส่งเสร็จสิ้น...</div>
                </div>
            `;
        };

        const isVhvDir = window.location.pathname.includes('/vhv/');
        const screeningApiUrl = isVhvDir ? '../api/save_screening.php' : 'api/save_screening.php';
        const dpacApiUrl = isVhvDir ? '../api/save_dpac.php' : 'api/save_dpac.php';

        const remainingQueue = [];

        for (let i = 0; i < queue.length; i++) {
            const item = queue[i];
            updateOverlayText(i, item._residentName);

            try {
                let targetUrl = screeningApiUrl;
                let bodyPayload = new URLSearchParams();

                if (item._type === 'dpac' || item._type === 'skip_dpac_case') {
                    targetUrl = dpacApiUrl;
                }

                for (const key in item) {
                    if (!key.startsWith('_')) {
                        bodyPayload.append(key, item[key]);
                    }
                }

                const response = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: bodyPayload
                });

                const data = await response.json();
                if (data.status === 'success') {
                    successCount++;
                } else {
                    console.error("Sync item error:", data.message);
                    failCount++;
                    remainingQueue.push(item);
                }
            } catch (err) {
                console.error("Sync network error:", err);
                failCount++;
                remainingQueue.push(item);
            }
        }

        this.setQueue(remainingQueue);
        this.isSyncing = false;

        if (successCount > 0 && failCount === 0) {
            overlay.innerHTML = `
                <div style="background: var(--bg-card, #1e293b); color: var(--text-primary, #ffffff); border-radius: 20px; padding: 28px 24px; max-width: 380px; width: 100%; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.5); border: 2px solid #10B981;">
                    <div style="font-size: 48px; margin-bottom: 12px;">🎉</div>
                    <h3 style="margin: 0 0 8px 0; font-size: 20px; font-weight: 800; color: #10B981;">ส่งข้อมูลสำเร็จครบถ้วน!</h3>
                    <p style="margin: 0 0 16px 0; font-size: 14px; color: var(--text-secondary, #94a3b8);">
                        ส่งข้อมูลคัดกรองทั้งหมด <strong>${successCount} รายการ</strong> เข้าระบบและบันทึกแต้มสะสมเรียบร้อยแล้ว
                    </p>
                    <button type="button" onclick="window.location.reload()" style="background: #10B981; color: white; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 800; font-size: 15px; cursor: pointer; width: 100%; box-shadow: 0 4px 14px rgba(16,185,129,0.4);">
                        ตกลง (รีเฟรชหน้าจอ)
                    </button>
                </div>
            `;
            setTimeout(() => {
                window.location.reload();
            }, 1800);
        } else if (successCount > 0 && failCount > 0) {
            overlay.innerHTML = `
                <div style="background: var(--bg-card, #1e293b); color: var(--text-primary, #ffffff); border-radius: 20px; padding: 28px 24px; max-width: 380px; width: 100%; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.5); border: 2px solid #F59E0B;">
                    <div style="font-size: 48px; margin-bottom: 12px;">⚠️</div>
                    <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 800; color: #F59E0B;">ส่งสำเร็จบางส่วน</h3>
                    <p style="margin: 0 0 16px 0; font-size: 13.5px; color: var(--text-secondary, #94a3b8);">
                        ส่งสำเร็จ <strong>${successCount} คน</strong>, ค้างส่ง <strong>${failCount} คน</strong> (ข้อมูลยังปลอดภัยในเครื่อง)<br>โปรดลองกดส่งใหม่อีกครั้งเมื่อเน็ตเสถียรครับ
                    </p>
                    <button type="button" onclick="document.getElementById('sync-progress-overlay').style.display='none'; window.location.reload();" style="background: #F59E0B; color: white; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 800; font-size: 14px; cursor: pointer; width: 100%;">
                        ปิดหน้าต่างนี้
                    </button>
                </div>
            `;
        } else {
            overlay.innerHTML = `
                <div style="background: var(--bg-card, #1e293b); color: var(--text-primary, #ffffff); border-radius: 20px; padding: 28px 24px; max-width: 380px; width: 100%; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.5); border: 2px solid #EF4444;">
                    <div style="font-size: 48px; margin-bottom: 12px;">❌</div>
                    <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 800; color: #EF4444;">ไม่สามารถส่งข้อมูลได้</h3>
                    <p style="margin: 0 0 16px 0; font-size: 13.5px; color: var(--text-secondary, #94a3b8);">
                        สัญญาณอินเทอร์เน็ตอาจหลุด ข้อมูลทั้งหมด <strong>${totalItems} รายการ</strong> ยังคงถูกเก็บไว้อย่างปลอดภัยในเครื่องครับ
                    </p>
                    <button type="button" onclick="document.getElementById('sync-progress-overlay').style.display='none';" style="background: #EF4444; color: white; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 800; font-size: 14px; cursor: pointer; width: 100%;">
                        ลองใหม่ภายหลัง
                    </button>
                </div>
            `;
        }
    },

    init: function() {
        this.updateSyncUI();
        window.addEventListener('online', () => {
            this.updateSyncUI();
        });
        window.addEventListener('offline', () => {
            this.updateSyncUI();
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    if (window.VhvSyncEngine) {
        window.VhvSyncEngine.init();
    }
});

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

// ==========================================================================
// Universal NCDs Page Preloader & Loading Transition Engine
// ==========================================================================
(function() {
    let safetyTimeout = null;

    // 1. Global showPageLoading
    window.showPageLoading = function(title, subtitle, icon, targetUrl) {
        let overlay = document.getElementById('page-loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'page-loading-overlay';
            overlay.className = 'ncd-page-overlay';
            overlay.innerHTML = `
                <div class="loading-modal-card">
                    <div class="loading-spinner-ring">
                        <span class="loading-pulse-icon" id="loading-icon">📊</span>
                    </div>
                    <div>
                        <div style="font-size: 16px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;" id="loading-title">
                            ระบบ NCDs Portal
                        </div>
                        <div style="font-size: 12.5px; color: var(--text-secondary); line-height: 1.5;" id="loading-subtitle">
                            กำลังประมวลผลข้อมูล...
                        </div>
                    </div>
                    <div class="loading-progress-track">
                        <div class="loading-progress-bar"></div>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
        }

        const titleEl = overlay.querySelector('#loading-title');
        const subEl = overlay.querySelector('#loading-subtitle');
        const iconEl = overlay.querySelector('#loading-icon');

        if (title && titleEl) {
            if (title.includes('<') || title.includes('\n')) {
                titleEl.innerHTML = title.replace(/\n/g, '<br>');
            } else {
                titleEl.innerText = title;
            }
        }
        if (subtitle && subEl) {
            if (subtitle.includes('<') || subtitle.includes('\n')) {
                subEl.innerHTML = subtitle.replace(/\n/g, '<br>');
            } else {
                subEl.innerText = subtitle;
            }
        }
        if (icon && iconEl) iconEl.innerText = icon;

        overlay.style.display = 'flex';
        overlay.classList.add('active');
        overlay.style.opacity = '1';

        // Safety Auto-Dismiss after 4.5 seconds to guarantee it NEVER freezes
        if (safetyTimeout) clearTimeout(safetyTimeout);
        safetyTimeout = setTimeout(() => {
            window.hidePageLoading();
        }, 4500);

        if (targetUrl) {
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 40);
        }
    };

    // 2. Global hidePageLoading
    window.hidePageLoading = function() {
        if (safetyTimeout) {
            clearTimeout(safetyTimeout);
            safetyTimeout = null;
        }
        const overlay = document.getElementById('page-loading-overlay');
        if (overlay) {
            overlay.classList.remove('active');
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 200);
        }
        const preloaders = document.querySelectorAll('#admin-page-preloader, #dashboard-preloader, #ncd-global-preloader');
        preloaders.forEach(preloader => {
            preloader.style.opacity = '0';
            preloader.style.visibility = 'hidden';
            setTimeout(() => {
                if (preloader && preloader.parentNode) preloader.parentNode.removeChild(preloader);
            }, 200);
        });
    };

    // --------------------------------------------------------------------------
    // 3. VHV Menu Blur Pre-Loader & Navigation Engine (~1s Smooth Transition)
    // --------------------------------------------------------------------------
    let vhvMenuTimeout = null;

    window.showVhvMenuLoader = function(title, subtitle, icon, targetUrl) {
        let loader = document.getElementById('vhv-menu-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'vhv-menu-loader';
            loader.innerHTML = `
                <div class="vhv-loader-card">
                    <div class="vhv-loader-ring">
                        <span class="vhv-loader-icon" id="vhv-loader-icon">⚡</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 3px;">
                        <h4 class="vhv-loader-title" id="vhv-loader-title">กำลังเปลี่ยนหน้า...</h4>
                        <p class="vhv-loader-sub" id="vhv-loader-sub">กรุณารอสักครู่</p>
                    </div>
                    <div class="vhv-loader-bar-track">
                        <div class="vhv-loader-bar-progress"></div>
                    </div>
                </div>
            `;
            document.body.appendChild(loader);
        }

        const iconEl = loader.querySelector('#vhv-loader-icon');
        const titleEl = loader.querySelector('#vhv-loader-title');
        const subEl = loader.querySelector('#vhv-loader-sub');

        if (iconEl) iconEl.innerText = icon || '⚡';
        if (titleEl) titleEl.innerText = title || 'กำลังเปลี่ยนหน้า...';
        if (subEl) subEl.innerText = subtitle || 'กรุณารอสักครู่';

        loader.classList.add('active');

        // Safety timeout to prevent any stuck state
        if (vhvMenuTimeout) clearTimeout(vhvMenuTimeout);
        vhvMenuTimeout = setTimeout(() => {
            window.hideVhvMenuLoader();
        }, 5000);

        if (targetUrl) {
            // Snappy ~500ms (0.5s) smooth transition for fast & responsive feel
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 500);
        }
    };

    window.hideVhvMenuLoader = function() {
        if (vhvMenuTimeout) {
            clearTimeout(vhvMenuTimeout);
            vhvMenuTimeout = null;
        }
        const loader = document.getElementById('vhv-menu-loader');
        if (loader) {
            loader.classList.remove('active');
        }
    };

    // Backward compatibility aliases
    window.showMiniLoader = function(text, targetUrl) {
        window.showVhvMenuLoader('กำลังเปลี่ยนหน้า...', text || 'กำลังโหลดข้อมูล...', '⚡', targetUrl);
    };
    window.hideMiniLoader = window.hideVhvMenuLoader;

    // 4. Dismiss all loaders on load / pageshow / DOMContentLoaded
    const dismissAllLoaders = function() {
        if (document.body && document.body.getAttribute('data-preserve-loader') === 'true') {
            return;
        }
        window.hidePageLoading();
        window.hideVhvMenuLoader();
    };

    window.addEventListener('load', dismissAllLoaders);
    window.addEventListener('pageshow', dismissAllLoaders);
    document.addEventListener('DOMContentLoaded', dismissAllLoaders);

    // If script runs when document is already ready
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(dismissAllLoaders, 50);
    }

    // --------------------------------------------------------------------------
    // 5. Universal Guard: Prevent duplicate loading & reloading on current active page
    // --------------------------------------------------------------------------
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('tel:') || href.startsWith('mailto:') || link.getAttribute('target') === '_blank') {
            return;
        }

        // Clean current path and target path
        const currentPath = (window.location.pathname.split('/').pop() || 'index.php').split('?')[0].split('#')[0];
        const targetPath = href.split('?')[0].split('#')[0].split('/').pop();
        const currentSearch = window.location.search || '';
        const targetSearch = href.includes('?') ? '?' + href.split('?')[1].split('#')[0] : '';

        // If clicking a link to the exact same page with the exact same query parameters:
        if (currentPath === targetPath && currentSearch === targetSearch && !link.classList.contains('force-reload') && !link.classList.contains('force-loader')) {
            // Cancel navigation and prevent any inline onclick loader from popping up
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            if (window.hidePageLoading) window.hidePageLoading();
            if (window.hideVhvMenuLoader) window.hideVhvMenuLoader();

            // Smooth scroll back to top of the current page
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return false;
        }
    }, true); // Capture phase ensures it runs BEFORE any inline onclick

    // --------------------------------------------------------------------------
    // 5.1 Automatic click interception for VHV Menu Links (.bottom-nav a, etc.)
    // --------------------------------------------------------------------------
    document.addEventListener('click', (e) => {
        const link = e.target.closest('.bottom-nav a, .nav-link, a.vhv-menu-link');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.getAttribute('target') === '_blank') {
            return;
        }

        // Compare target URL with current URL pathname
        const currentPath = (window.location.pathname.split('/').pop() || 'index.php').split('?')[0].split('#')[0];
        const targetPath = href.split('?')[0].split('#')[0].split('/').pop();

        // If clicking link to current active page without queries/changes, skip loader
        if (currentPath === targetPath && !href.includes('?') && !link.classList.contains('force-loader')) {
            return;
        }

        e.preventDefault();

        let title = 'กำลังเปลี่ยนหน้า';
        let subtitle = 'กำลังโหลดข้อมูล กรุณารอสักครู่...';
        let icon = '⚡';

        if (targetPath.includes('index.php')) {
            title = 'หน้าแรก';
            subtitle = 'กำลังเปิดรายการงานค้าง อสม....';
            icon = '🏠';
        } else if (targetPath.includes('scan.php')) {
            title = 'สแกนบ้านเป้าหมาย';
            subtitle = 'กำลังเปิดกล้องและระบบสแกน QR...';
            icon = '📷';
        } else if (targetPath.includes('leaderboard.php')) {
            title = 'คะแนน & ของรางวัล';
            subtitle = 'กำลังประมวลผลอันดับและภารกิจ...';
            icon = '🏆';
        } else if (targetPath.includes('profile.php')) {
            title = 'ข้อมูลส่วนตัว';
            subtitle = 'กำลังเปิดข้อมูล อสม....';
            icon = '👤';
        } else if (targetPath.includes('self_screening.php')) {
            title = 'ประเมินสุขภาพตนเอง';
            subtitle = 'กำลังเตรียมแบบคัดกรอง อสม....';
            icon = '🌱';
        }

        window.showVhvMenuLoader(title, subtitle, icon, href);
    });

    // 6. Automatic Form Submission Interception
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.getAttribute('data-no-loader') || form.getAttribute('target') === '_blank') {
            return;
        }
        const submitBtn = form.querySelector('[type="submit"]') || form.querySelector('button:not([type="button"])');
        let title = 'กำลังบันทึกข้อมูล';
        let subtitle = 'ระบบกำลังประมวลผล กรุณารอสักครู่...';
        let icon = '💾';

        const action = form.action || '';
        if (action.includes('process_etl.php') || action.includes('import_hdc.php')) {
            title = 'กำลังประมวลผล ETL นำเข้าข้อมูล';
            subtitle = 'กำลังจัดทำดัชนีและตรวจสอบความถูกต้อง...';
            icon = '⚙️';
        } else if (action.includes('screening') || action.includes('save_screening') || action.includes('dpac')) {
            title = 'กำลังบันทึกผลการคัดกรอง';
            subtitle = 'กำลังคำนวณ CV Risk Score และความเสี่ยง...';
            icon = '🩺';
        } else if (action.includes('rewards')) {
            title = 'กำลังบันทึกข้อมูลของรางวัล';
            subtitle = 'กำลังอัปเดตแคตตาล็อกและคะแนนสะสม...';
            icon = '🎁';
        } else if (submitBtn && submitBtn.innerText) {
            const txt = submitBtn.innerText.trim();
            if (txt.includes('ค้นหา') || txt.includes('กรอง')) {
                title = 'กำลังค้นหาข้อมูล';
                subtitle = 'กำลังประมวลผลข้อมูลตามเงื่อนไข...';
                icon = '🔍';
            } else if (txt.includes('เข้าสู่ระบบ') || txt.includes('ล็อกอิน')) {
                title = 'กำลังเข้าสู่ระบบ';
                subtitle = 'กำลังตรวจสอบสิทธิ์การใช้งาน...';
                icon = '🔐';
            }
        }
        window.showPageLoading(title, subtitle, icon);
    });
})();