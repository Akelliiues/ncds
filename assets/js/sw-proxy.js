// assets/js/sw-proxy.js - Tan Sum NCD Presentation & Offline-First Proxy Service Worker
const CACHE_NAME = 'tansum-ncd-proxy-v1';
let isProxyMode = false;

// Files to cache for offline presentation
const ESSENTIAL_ASSETS = [
    '../css/style.css',
    '../js/app.js',
    '../../admin/index.php',
    '../../public_dashboard.php'
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ESSENTIAL_ASSETS).catch((err) => {
                console.log('SW: Pre-cache non-fatal warning:', err);
            });
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

// Listen for messages from client (e.g. Set Mode, Cache URLs, Clear)
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
                    const response = await fetch(url, { cache: 'no-store' });
                    if (response && response.status === 200) {
                        await cache.put(url, response.clone());
                    }
                } catch (e) {
                    console.log('SW: Failed to cache url:', url, e);
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

// Fetch Interceptor
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // If method is POST or not GET, let it pass through
    if (request.method !== 'GET') {
        return;
    }

    // When in Full Proxy Mode (Offline / Presentation)
    if (isProxyMode) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                // Try network if not in cache, fallback gracefully
                return fetch(request).catch(() => {
                    return caches.match('../../admin/index.php');
                });
            })
        );
        return;
    }

    // In Live Mode: Network First with silent cache update
    event.respondWith(
        fetch(request)
            .then((networkResponse) => {
                // If successful response and static asset or GET page, update cache in background
                if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseToCache);
                    });
                }
                return networkResponse;
            })
            .catch(() => {
                // Fallback to cache if network drops unexpectedly even in live mode
                return caches.match(request);
            })
    );
});
