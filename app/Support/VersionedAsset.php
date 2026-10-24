<?php

namespace App\Support;

/**
 * Asset URLs with a content fingerprint (?v=1a2b3c4d), so browsers and the
 * Vercel CDN fetch a file again as soon as it changes, despite the long
 * Cache-Control lifetimes set in vercel.json.
 */
class VersionedAsset
{
    /** @var array<string, string> */
    private static array $versions = [];

    public static function url(string $path): string
    {
        $path = ltrim($path, '/');
        $version = self::$versions[$path] ??= self::fingerprint($path);

        return asset($path).($version === '' ? '' : '?v='.$version);
    }

    private static function fingerprint(string $path): string
    {
        $file = public_path($path);

        if (is_file($file)) {
            return substr(md5_file($file), 0, 8);
        }

        // Fall back to the deployed commit when the file is not bundled.
        return substr((string) config('practice.deploy_version'), 0, 8);
    }
}
