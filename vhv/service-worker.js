// vhv/service-worker.js

const CACHE_NAME = 'ncd-tansum-v1';
const ASSETS_TO_CACHE = [
    '/vhv/login.php',
    '/vhv/index.php',
    '/vhv/scan.php',
    '/vhv/screening_form.php',
    '/vhv/leaderboard.php',
    '/vhv/manifest.json',
    '/assets/css/style.css',
    '/assets/js/app.js',
    'https://unpkg.com/html5-qrcode',
    'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Sarabun:wght@300;400;600;800&display=swap'
];

// Install Event
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('SW: Pre-caching static assets...');
                return cache.addAll(ASSETS_TO_CACHE);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate Event
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.map(key => {
                    if (key !== CACHE_NAME) {
                        console.log('SW: Removing old cache...', key);
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Event - Network first fallback to cache for pages, cache first for assets
self.addEventListener('fetch', event => {
    const requestUrl = new URL(event.request.url);

    // Dynamic API endpoints should always bypass cache and go straight to network
    if (requestUrl.pathname.includes('/api/')) {
        event.respondWith(fetch(event.request));
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then(response => {
                // If response is valid, clone it and save to cache
                if (response && response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, responseClone);
                    });
                }
                return response;
            })
            .catch(() => {
                // Fallback to cache if network is unavailable
                return caches.match(event.request);
            })
    );
});
