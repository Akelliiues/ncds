<?php
// config/cache.php - High-Performance Multi-Tier Cache Engine (APCu / Redis / Atomic File Cache)
// 100% Standalone & Zero-Dependency Graceful Fallback

if (!class_exists('NcdCache')) {
    class NcdCache
    {
        private static $cacheDir = null;
        private static $redis = null;
        private static $hasRedis = null;
        private static $hasApcu = null;

        /**
         * Initialize storage paths and detect available in-memory backends
         */
        private static function init()
        {
            if (self::$cacheDir !== null) return;

            self::$cacheDir = dirname(__DIR__) . '/runtime/cache';
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0777, true);
            }

            // 1. Check APCu
            self::$hasApcu = function_exists('apcu_fetch') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN);

            // 2. Check Redis (Optional external backend)
            self::$hasRedis = false;
            if (class_exists('Redis')) {
                try {
                    $r = new \Redis();
                    $host = defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1';
                    $port = defined('REDIS_PORT') ? (int)REDIS_PORT : 6379;
                    if (@$r->connect($host, $port, 0.2)) { // 200ms timeout
                        self::$redis = $r;
                        self::$hasRedis = true;
                    }
                } catch (\Throwable $e) {
                    self::$hasRedis = false;
                }
            }
        }

        /**
         * Get cached value or compute and store it atomically
         * 
         * @param string $key Cache key
         * @param int $ttlSeconds Time to live in seconds (default 300 = 5 minutes)
         * @param callable $callback Generator function
         * @return mixed Cached or computed data
         */
        public static function remember($key, $ttlSeconds, $callback)
        {
            self::init();

            $cached = self::get($key);
            if ($cached !== null) {
                return $cached;
            }

            $data = call_user_func($callback);
            if ($data !== null) {
                self::set($key, $data, $ttlSeconds);
            }
            return $data;
        }

        /**
         * Retrieve an item from cache
         */
        public static function get($key)
        {
            self::init();
            $safeKey = 'ncd_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);

            // 1. Try APCu
            if (self::$hasApcu) {
                $success = false;
                $val = apcu_fetch($safeKey, $success);
                if ($success) return $val;
            }

            // 2. Try Redis
            if (self::$hasRedis && self::$redis) {
                try {
                    $raw = self::$redis->get($safeKey);
                    if ($raw !== false && $raw !== null) {
                        return json_decode($raw, true);
                    }
                } catch (\Throwable $e) {}
            }

            // 3. Try Atomic File Cache
            $filePath = self::$cacheDir . '/' . md5($safeKey) . '.cache';
            if (file_exists($filePath)) {
                $content = @file_get_contents($filePath);
                if ($content) {
                    $payload = @json_decode($content, true);
                    if (is_array($payload) && isset($payload['exp']) && isset($payload['data'])) {
                        if ($payload['exp'] > time()) {
                            return $payload['data'];
                        } else {
                            @unlink($filePath); // Expired
                        }
                    }
                }
            }

            return null;
        }

        /**
         * Store an item in the cache
         */
        public static function set($key, $data, $ttlSeconds = 300)
        {
            self::init();
            $safeKey = 'ncd_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
            $ttl = max(1, (int)$ttlSeconds);

            // 1. Save to APCu
            if (self::$hasApcu) {
                @apcu_store($safeKey, $data, $ttl);
            }

            // 2. Save to Redis
            if (self::$hasRedis && self::$redis) {
                try {
                    self::$redis->setex($safeKey, $ttl, json_encode($data, JSON_UNESCAPED_UNICODE));
                } catch (\Throwable $e) {}
            }

            // 3. Save to Atomic File Cache
            $filePath = self::$cacheDir . '/' . md5($safeKey) . '.cache';
            $payload = [
                'exp' => time() + $ttl,
                'created_at' => time(),
                'key' => $key,
                'data' => $data
            ];
            $tempFile = $filePath . '.' . uniqid('tmp', true);
            if (@file_put_contents($tempFile, json_encode($payload, JSON_UNESCAPED_UNICODE)) !== false) {
                @rename($tempFile, $filePath);
            }
            return true;
        }

        /**
         * Invalidate a single key
         */
        public static function forget($key)
        {
            self::init();
            $safeKey = 'ncd_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);

            if (self::$hasApcu) @apcu_delete($safeKey);
            if (self::$hasRedis && self::$redis) {
                try { self::$redis->del($safeKey); } catch (\Throwable $e) {}
            }

            $filePath = self::$cacheDir . '/' . md5($safeKey) . '.cache';
            if (file_exists($filePath)) @unlink($filePath);
            return true;
        }

        /**
         * Flush all cached items
         */
        public static function flush()
        {
            self::init();
            if (self::$hasApcu) @apcu_clear_cache();
            if (self::$hasRedis && self::$redis) {
                try { self::$redis->flushDB(); } catch (\Throwable $e) {}
            }

            if (is_dir(self::$cacheDir)) {
                $files = glob(self::$cacheDir . '/*.cache*');
                if (is_array($files)) {
                    foreach ($files as $f) {
                        @unlink($f);
                    }
                }
            }
            return true;
        }
    }
}
