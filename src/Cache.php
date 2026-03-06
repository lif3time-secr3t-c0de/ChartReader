<?php

class Cache {
    private static $cacheDir = __DIR__ . '/../cache/';

    public static function get($key) {
        $file = self::$cacheDir . md5($key) . '.cache';
        if (file_exists($file)) {
            $raw = file_get_contents($file);
            $data = json_decode($raw, true);

            if (!is_array($data) || !isset($data['expires'])) {
                @unlink($file);
                return null;
            }

            if ($data['expires'] > time()) {
                return $data['content'] ?? null;
            }

            @unlink($file);
        }

        return null;
    }

    public static function set($key, $content, $ttl = 3600) {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        $file = self::$cacheDir . md5($key) . '.cache';
        $data = [
            'expires' => time() + $ttl,
            'content' => $content
        ];

        return file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    public static function delete($key) {
        $file = self::$cacheDir . md5($key) . '.cache';
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}
