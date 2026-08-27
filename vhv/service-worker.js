const CACHE_NAME = 'ncd-tansum-v11_20260827_1640'; // Auto-Sync Default Ready
const ASSETS_TO_CACHE = [
    'login.php',
    'index.php',
    'scan.php',
    'screening_form.php?shell=true',
    'dpac_form.php?shell=true',
    'leaderboard.php',
    'profile.php',
    'manifest.json',
    '../assets/css/style.css',
    '../assets/js/app.js',
    'https://unpkg.com/html5-qrcode',
    'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Sarabun:wght@300;400;600;800&display=swap'
];

// Force update message handler
self.addEventListener('message', event => {
    if (event.data && (event.data.action === 'skipWaiting' || event.data.type === 'SKIP_WAITING')) {
        self.skipWaiting();
    }
});

// Install Event - Pre-cache all static assets and dynamic offline shells
self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('SW: Pre-caching static assets and offline shells...');
                return cache.addAll(ASSETS_TO_CACHE);
            })
    );
});

// Activate Event - Clear old caches and take immediate control of clients
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
        }).then(() => {
            return self.clients.claim();
        }).then(() => {
            // Notify all open tabs that a new version is active
            return self.clients.matchAll({ type: 'window' }).then(clients => {
                clients.forEach(client => {
                    client.postMessage({ type: 'SW_UPDATED', version: CACHE_NAME });
                });
            });
        })
    );
});

// Fetch Event - Network first, fall back to cache with offline shell routing
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
                if (response && response.status === 200 && event.request.method === 'GET') {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, responseClone);
                    });
                }
                return response;
            })
            .catch(() => {
                // Fallback to cache if network is unavailable
                return caches.match(event.request).then(response => {
                    if (response) {
                        return response;
                    }
                    
                    // Offline dynamic routing fallbacks
                    if (requestUrl.pathname.endsWith('/screening_form.php')) {
                        return caches.match('screening_form.php?shell=true');
                    }
                    if (requestUrl.pathname.endsWith('/dpac_form.php')) {
                        return caches.match('dpac_form.php?shell=true');
                    }
                    if (requestUrl.pathname.endsWith('/index.php') || requestUrl.pathname.endsWith('/vhv/')) {
                        return caches.match('index.php');
                    }
                    if (requestUrl.pathname.endsWith('/scan.php')) {
                        return caches.match('scan.php');
                    }
                    if (requestUrl.pathname.endsWith('/leaderboard.php')) {
                        return caches.match('leaderboard.php');
                    }
                });
            })
    );
});
