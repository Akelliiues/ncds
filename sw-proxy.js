// sw-proxy.js - Tan Sum NCD Presentation & Offline-First Root Service Worker
const CACHE_NAME = 'tansum-ncd-proxy-v2';
let isProxyMode = false;

// Complete list of essential assets for full offline parity
const ESSENTIAL_ASSETS = [
    'assets/css/style.css',
    'assets/js/app.js',
    'assets/js/proxy-manager.js',
    'assets/js/clinical_guidance.js',
    'assets/geojson/tansum_boundary.json',
    'admin/index.php',
    'admin/db_manager.php',
    'admin/reports.php',
    'admin/analytics.php',
    'admin/gamification.php',
    'public_dashboard.php',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    'https://leaflet.github.io/Leaflet.heat/dist/leaflet-heat.js',
    'https://cdn.jsdelivr.net/npm/apexcharts'
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(async (cache) => {
            for (const url of ESSENTIAL_ASSETS) {
                try {
                    const res = await fetch(url, { mode: 'cors' });
                    if (res && (res.status === 200 || res.type === 'opaque')) {
                        await cache.put(url, res);
                    }
                } catch (e) {
                    console.log('SW: Install cache warm note:', url);
                }
            }
        })
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        Promise.all([
            self.clients.claim(),
            caches.keys().then((keys) => {
                return Promise.all(
                    keys.map((key) => {
                        if (key !== CACHE_NAME) {
                            return caches.delete(key);
                        }
                    })
                );
            })
        ])
    );
});

// Client Message Listener
self.addEventListener('message', (event) => {
    const data = event.data;
    if (!data) return;

    if (data.action === 'SET_MODE') {
        isProxyMode = (data.mode === 'proxy');
        if (event.ports && event.ports[0]) {
            event.ports[0].postMessage({ success: true, mode: isProxyMode ? 'proxy' : 'live' });
        }
    } else if (data.action === 'CACHE_URLS' && Array.isArray(data.urls)) {
        caches.open(CACHE_NAME).then(async (cache) => {
            for (const url of data.urls) {
                try {
                    const response = await fetch(url, { cache: 'no-store', mode: 'cors' });
                    if (response && (response.status === 200 || response.type === 'opaque')) {
                        await cache.put(url, response.clone());
                    }
                } catch (e) {
                    console.log('SW: Pre-warm warning for:', url);
                }
            }
            if (event.ports && event.ports[0]) {
                event.ports[0].postMessage({ success: true, cachedCount: data.urls.length });
            }
        });
    } else if (data.action === 'CLEAR_CACHE') {
        caches.delete(CACHE_NAME).then(() => {
            if (event.ports && event.ports[0]) {
                event.ports[0].postMessage({ success: true });
            }
        });
    } else if (data.action === 'GET_STATUS') {
        caches.open(CACHE_NAME).then(async (cache) => {
            const keys = await cache.keys();
            if (event.ports && event.ports[0]) {
                event.ports[0].postMessage({
                    success: true,
                    mode: isProxyMode ? 'proxy' : 'live',
                    itemCount: keys.length
                });
            }
        });
    }
});

// Fetch Interceptor: Handles all Offline & Proxy requests
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only intercept GET requests
    if (request.method !== 'GET') {
        return;
    }

    // In Full Proxy Mode (Offline / Presentation)
    if (isProxyMode) {
        event.respondWith(
            caches.match(request, { ignoreSearch: true }).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(request).catch(async () => {
                    // Smart navigation fallback
                    if (request.mode === 'navigate') {
                        const urlStr = request.url.toLowerCase();
                        if (urlStr.includes('public_dashboard')) {
                            const pMatch = await caches.match('public_dashboard.php');
                            if (pMatch) return pMatch;
                        }
                        const adminMatch = await caches.match('admin/index.php');
                        if (adminMatch) return adminMatch;
                    }
                    return new Response('<h2 style="font-family: sans-serif; text-align: center; margin-top: 50px;">⚡ Presentation Offline Proxy</h2><p style="text-align: center;">กรุณากด Pre-warm ข้อมูลในหน้า DB Manager ก่อนใช้งานออฟไลน์</p>', {
                        headers: { 'Content-Type': 'text/html; charset=utf-8' }
                    });
                });
            })
        );
        return;
    }

    // Live Mode: Network First with silent background caching
    event.respondWith(
        fetch(request)
            .then((networkResponse) => {
                if (networkResponse && (networkResponse.status === 200 || networkResponse.type === 'opaque')) {
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseToCache);
                    });
                }
                return networkResponse;
            })
            .catch(async () => {
                // If network is cut / down, serve from cache instead of Dinosaur!
                const match = await caches.match(request, { ignoreSearch: true });
                if (match) return match;
                if (request.mode === 'navigate') {
                    const urlStr = request.url.toLowerCase();
                    if (urlStr.includes('public_dashboard')) {
                        const pMatch = await caches.match('public_dashboard.php');
                        if (pMatch) return pMatch;
                    }
                    const adminMatch = await caches.match('admin/index.php');
                    if (adminMatch) return adminMatch;
                }
                return new Response('Offline Cached Response', { status: 200 });
            })
    );
});
