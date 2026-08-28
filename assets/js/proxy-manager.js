// assets/js/proxy-manager.js - Presentation & Proxy Controller with Sleek Preloading Progress Bar & Auto Daily Expiry
(function(window) {
    'use strict';

    const ProxyManager = {
        swRegistration: null,
        isProxyActive: localStorage.getItem('ncd_proxy_active') === 'true',

        getTodayDateString: function() {
            const d = new Date();
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },

        checkDailyExpiry: function() {
            const isProxy = localStorage.getItem('ncd_proxy_active') === 'true';
            if (isProxy) {
                const proxyDate = localStorage.getItem('ncd_proxy_date');
                const today = this.getTodayDateString();
                if (proxyDate && proxyDate !== today) {
                    console.log('ProxyManager: Auto-reverting to Live Mode (New day detected: ' + today + ', proxy date was: ' + proxyDate + ')');
                    this.isProxyActive = false;
                    localStorage.setItem('ncd_proxy_active', 'false');
                    localStorage.removeItem('ncd_proxy_date');
                    localStorage.setItem('ncd_proxy_reverted', 'true');
                    return true;
                }
            }
            return false;
        },

        init: function() {
            // Check daily expiry: If opened on a new day, auto-revert to Live mode!
            this.checkDailyExpiry();

            if ('serviceWorker' in navigator) {
                const isInAdmin = window.location.pathname.includes('/admin/');
                const swPath = isInAdmin ? '../sw-proxy.js' : 'sw-proxy.js';
                const swScope = isInAdmin ? '../' : './';

                navigator.serviceWorker.register(swPath, { scope: swScope }).then((reg) => {
                    this.swRegistration = reg;
                    this.sendSWMessage({
                        action: 'SET_MODE',
                        mode: this.isProxyActive ? 'proxy' : 'live'
                    });
                }).catch((err) => {
                    console.log('ProxyManager SW registration info:', err);
                });

                navigator.serviceWorker.addEventListener('controllerchange', () => {
                    this.sendSWMessage({
                        action: 'SET_MODE',
                        mode: this.isProxyActive ? 'proxy' : 'live'
                    });
                });
            }
        },

        sendSWMessage: function(msg) {
            return new Promise((resolve) => {
                if (!navigator.serviceWorker || !navigator.serviceWorker.controller) {
                    if (this.swRegistration && this.swRegistration.active) {
                        const messageChannel = new MessageChannel();
                        messageChannel.port1.onmessage = (event) => resolve(event.data);
                        this.swRegistration.active.postMessage(msg, [messageChannel.port2]);
                        return;
                    }
                    resolve({ success: false, reason: 'SW not active' });
                    return;
                }
                const messageChannel = new MessageChannel();
                messageChannel.port1.onmessage = (event) => resolve(event.data);
                navigator.serviceWorker.controller.postMessage(msg, [messageChannel.port2]);
            });
        },

        setMode: async function(isProxy) {
            this.isProxyActive = !!isProxy;
            localStorage.setItem('ncd_proxy_active', this.isProxyActive ? 'true' : 'false');
            if (this.isProxyActive) {
                localStorage.setItem('ncd_proxy_date', this.getTodayDateString());
            } else {
                localStorage.removeItem('ncd_proxy_date');
            }
            await this.sendSWMessage({
                action: 'SET_MODE',
                mode: this.isProxyActive ? 'proxy' : 'live'
            });
            return this.isProxyActive;
        },

        // Trigger Pre-warm with Sleek Progress Modal
        startPrewarm: async function(callback) {
            this.showPreloadModal();

            const isInAdmin = window.location.pathname.includes('/admin/');
            const basePath = isInAdmin ? '' : 'admin/';
            const rootPath = isInAdmin ? '../' : '';

            // Complete exhaustive list of pages, scripts, styles, CDNs, and GeoJSON for 100% offline parity
            const urlsToCache = [
                window.location.href,
                basePath + 'index.php',
                basePath + 'reports.php',
                basePath + 'analytics.php',
                basePath + 'gamification.php',
                basePath + 'db_manager.php',
                rootPath + 'public_dashboard.php',
                rootPath + 'manual.php',
                rootPath + 'assets/css/style.css',
                rootPath + 'assets/js/app.js',
                rootPath + 'assets/js/proxy-manager.js',
                rootPath + 'assets/js/clinical_guidance.js',
                rootPath + 'assets/geojson/tansum_boundary.json',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
                'https://leaflet.github.io/Leaflet.heat/dist/leaflet-heat.js',
                'https://cdn.jsdelivr.net/npm/apexcharts',
                'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Prompt:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400&family=Sarabun:wght@300;400;500;600;700&display=swap'
            ];

            const steps = [
                { pct: 15, text: '📦 กำลังสำรองไฟล์ระบบ Assets, สไตล์ชีต, ApexCharts และ Leaflet GIS...' },
                { pct: 35, text: '🔤 กำลังสำรองชุดฟอนต์ภาษาไทยและอังกฤษ (Prompt, Sarabun, Outfit)...' },
                { pct: 60, text: '📊 กำลังประมวลผลสถิติแดชบอร์ดรายรอบ (รอบ 1, 2, 3+) ของ 8 รพ.สต....' },
                { pct: 85, text: '🗺️ กำลังจัดเตรียมแผนที่ GIS GeoJSON และพิกัดขอบเขตตำบล/หมู่บ้าน...' },
                { pct: 95, text: '⚡ กำลังติดตั้งและบันทึกเข้าสู่ Presentation Offline Proxy Layer...' },
                { pct: 100, text: '🎉 ข้อมูลและดีไซน์พร้อมสำหรับการนำเสนอแบบออฟไลน์ 100% เรียบร้อยแล้ว!' }
            ];

            for (let i = 0; i < steps.length; i++) {
                const s = steps[i];
                await new Promise(r => setTimeout(r, 420));
                this.updatePreloadProgress(s.pct, s.text);
            }

            // Send URLs to ServiceWorker for persistent local caching
            await this.sendSWMessage({
                action: 'CACHE_URLS',
                urls: urlsToCache
            });

            localStorage.setItem('ncd_proxy_ready', 'true');
            localStorage.setItem('ncd_proxy_time', new Date().toLocaleString('th-TH'));
            localStorage.setItem('ncd_proxy_date', this.getTodayDateString());

            setTimeout(() => {
                this.hidePreloadModal();
                if (typeof callback === 'function') callback();
            }, 600);
        },

        clearCache: async function() {
            await this.sendSWMessage({ action: 'CLEAR_CACHE' });
            localStorage.removeItem('ncd_proxy_ready');
            localStorage.removeItem('ncd_proxy_time');
            localStorage.removeItem('ncd_proxy_date');
        },

        // UI Preload Modal Creation
        showPreloadModal: function() {
            let modal = document.getElementById('proxy-preload-modal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'proxy-preload-modal';
                modal.style.cssText = `
                    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                    background: rgba(15, 23, 42, 0.7);
                    backdrop-filter: blur(12px);
                    display: flex; align-items: center; justify-content: center;
                    z-index: 99999; opacity: 0; transition: opacity 0.25s ease;
                `;
                modal.innerHTML = `
                    <div style="
                        background: var(--bg-card, #ffffff);
                        border-radius: 24px;
                        border: 1px solid rgba(139, 92, 246, 0.35);
                        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45), 0 0 30px rgba(139, 92, 246, 0.2);
                        width: 90%; max-width: 500px; padding: 36px 30px;
                        text-align: center; font-family: 'Prompt', sans-serif;
                    ">
                        <div style="
                            width: 68px; height: 68px; margin: 0 auto 18px;
                            border-radius: 50%; background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(59, 130, 246, 0.25));
                            display: flex; align-items: center; justify-content: center;
                            box-shadow: 0 0 25px rgba(139, 92, 246, 0.35);
                            border: 1.5px solid rgba(139, 92, 246, 0.3);
                        ">
                            <span style="font-size: 32px;">⚡</span>
                        </div>
                        <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary, #1e293b); margin: 0 0 8px; letter-spacing: -0.3px;">
                            กำลังสำรองข้อมูลและดีไซน์ทั้งระบบ (Full Auto-Cache)
                        </h3>
                        <p id="proxy-preload-desc" style="font-size: 13.5px; color: var(--text-secondary, #64748b); margin: 0 0 24px; min-height: 42px; line-height: 1.55;">
                            📦 กำลังสำรองไฟล์ระบบ Assets, สไตล์ชีต, ApexCharts และ Leaflet GIS...
                        </p>

                        <!-- Sleek Gradient Progress Bar -->
                        <div style="
                            background: rgba(100, 116, 139, 0.14);
                            border-radius: 50px; height: 16px;
                            overflow: hidden; padding: 3px;
                            box-shadow: inset 0 2px 5px rgba(0,0,0,0.1);
                            margin-bottom: 14px;
                        ">
                            <div id="proxy-preload-bar" style="
                                width: 5%; height: 100%; border-radius: 50px;
                                background: linear-gradient(90deg, #3b82f6, #8b5cf6, #10b981);
                                transition: width 0.38s cubic-bezier(0.4, 0, 0.2, 1);
                                box-shadow: 0 0 12px rgba(139, 92, 246, 0.5);
                            "></div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--text-muted, #94a3b8); font-weight: 600;">
                            <span>⚡ Full Offline Presentation Suite</span>
                            <span id="proxy-preload-pct" style="color: #8b5cf6; font-weight: 800; font-size: 14px;">5%</span>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }
            setTimeout(() => { modal.style.opacity = '1'; }, 10);
        },

        updatePreloadProgress: function(pct, desc) {
            const bar = document.getElementById('proxy-preload-bar');
            const pctText = document.getElementById('proxy-preload-pct');
            const descEl = document.getElementById('proxy-preload-desc');
            if (bar) bar.style.width = pct + '%';
            if (pctText) pctText.innerText = pct + '%';
            if (descEl && desc) descEl.innerText = desc;
        },

        hidePreloadModal: function() {
            const modal = document.getElementById('proxy-preload-modal');
            if (modal) {
                modal.style.opacity = '0';
                setTimeout(() => { modal.remove(); }, 300);
            }
        }
    };

    window.ProxyManager = ProxyManager;
    document.addEventListener('DOMContentLoaded', () => {
        ProxyManager.init();
    });
})(window);
