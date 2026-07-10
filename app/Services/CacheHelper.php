<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    /**
     * Cache items with tags if supported, otherwise normal cache.
     *
     * @param array $tags
     * @param string $key
     * @param int $ttl
     * @param \Closure $callback
     * @return mixed
     */
    public static function remember(array $tags, string $key, int $ttl, \Closure $callback)
    {
        if (Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Flush cache by tags if supported, otherwise flush entire cache.
     *
     * @param array $tags
     * @return void
     */
    public static function flush(array $tags): void
    {
        if (Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
            Cache::tags($tags)->flush();
            return;
        }

        Cache::flush();
    }
}
