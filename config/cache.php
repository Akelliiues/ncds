<?php
// config/cache.php - High Performance Multi-tier Cache Manager for NCDs Portal

class NcdCache {
    private static $memoryStore = [];
    private static $cacheDir = null;

    public static function getCacheDir() {
        if (self::$cacheDir === null) {
            $dir = __DIR__ . '/../tmp/cache';
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            self::$cacheDir = $dir;
        }
        return self::$cacheDir;
    }

    public static function get($key, $default = null) {
        // 1. Memory store
        if (array_key_exists($key, self::$memoryStore)) {
            $item = self::$memoryStore[$key];
            if ($item['expire'] === 0 || $item['expire'] >= time()) {
                return $item['data'];
            }
            unset(self::$memoryStore[$key]);
        }

        // 2. APCu if available
        if (function_exists('apcu_fetch')) {
            $success = false;
            $val = apcu_fetch('ncd_' . $key, $success);
            if ($success) {
                self::$memoryStore[$key] = ['data' => $val, 'expire' => time() + 60];
                return $val;
            }
        }

        // 3. File-based cache
        $file = self::getCacheDir() . '/' . md5($key) . '.cache';
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $payload = @unserialize($content);
                if (is_array($payload) && isset($payload['expire']) && array_key_exists('data', $payload)) {
                    if ($payload['expire'] === 0 || $payload['expire'] >= time()) {
                        self::$memoryStore[$key] = $payload;
                        return $payload['data'];
                    }
                    @unlink($file);
                }
            }
        }

        return $default;
    }

    public static function getMetadata($key) {
        $file = self::getCacheDir() . '/' . md5($key) . '.cache';
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $payload = @unserialize($content);
                if (is_array($payload) && isset($payload['expire'])) {
                    return [
                        'cached' => true,
                        'created_at' => $payload['created_at'] ?? filemtime($file),
                        'expire' => $payload['expire']
                    ];
                }
            }
        }
        return ['cached' => false, 'created_at' => null, 'expire' => null];
    }

    public static function set($key, $value, $ttl = 300) {
        $expire = $ttl > 0 ? time() + $ttl : 0;
        $now = time();
        self::$memoryStore[$key] = ['data' => $value, 'expire' => $expire, 'created_at' => $now];

        if (function_exists('apcu_store')) {
            apcu_store('ncd_' . $key, $value, $ttl);
        }

        $file = self::getCacheDir() . '/' . md5($key) . '.cache';
        $payload = serialize(['data' => $value, 'expire' => $expire, 'created_at' => $now]);
        @file_put_contents($file, $payload, LOCK_EX);
        return true;
    }

    public static function forget($key) {
        unset(self::$memoryStore[$key]);
        if (function_exists('apcu_delete')) {
            apcu_delete('ncd_' . $key);
        }
        $file = self::getCacheDir() . '/' . md5($key) . '.cache';
        if (file_exists($file)) {
            @unlink($file);
        }
        return true;
    }

    public static function flush() {
        self::$memoryStore = [];
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
        $dir = self::getCacheDir();
        $files = @glob($dir . '/*.cache');
        if ($files) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
        return true;
    }

    public static function remember($key, $ttl, $callback) {
        $val = self::get($key, null);
        if ($val !== null) {
            return $val;
        }
        $val = $callback();
        self::set($key, $val, $ttl);
        return $val;
    }
}

if (!function_exists('remember_cache')) {
    function remember_cache($key, $callback, $ttl = 300, $forceRefresh = false) {
        if ($forceRefresh) {
            NcdCache::forget($key);
        }
        return NcdCache::remember($key, $ttl, $callback);
    }
}

if (!function_exists('get_cache')) {
    function get_cache($key, $default = null) {
        return NcdCache::get($key, $default);
    }
}

if (!function_exists('set_cache')) {
    function set_cache($key, $value, $ttl = 300) {
        return NcdCache::set($key, $value, $ttl);
    }
}

if (!function_exists('forget_cache')) {
    function forget_cache($key) {
        return NcdCache::forget($key);
    }
}

if (!function_exists('get_cache_meta')) {
    function get_cache_meta($key) {
        return NcdCache::getMetadata($key);
    }
}
