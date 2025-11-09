<?php
namespace RiftCollect;

// Lightweight perceptual hashing (dHash) utilities for image matching

final class ImageHash
{
    private const exts = ['jpg','jpeg','png','webp','gif'];

    // Compute a 64-bit dHash (as 16-hex string) from a GD image resource
    public static function dhashFromGd($im, int $size = 8): ?string
    {
        if (!is_resource($im) && !(is_object($im) && get_resource_type($im) === 'gd')) {
            // PHP 8 returns GdImage object
        }
        $w = imagesx($im); $h = imagesy($im);
        if ($w <= 0 || $h <= 0) return null;
        $tw = $size + 1; $th = $size;
        $tmp = imagecreatetruecolor($tw, $th);
        imagecopyresampled($tmp, $im, 0, 0, 0, 0, $tw, $th, $w, $h);
        // grayscale matrix
        $gray = [];
        for ($y=0; $y<$th; $y++) {
            $row = [];
            for ($x=0; $x<$tw; $x++) {
                $rgb = imagecolorat($tmp, $x, $y);
                $r = ($rgb >> 16) & 0xFF; $g = ($rgb >> 8) & 0xFF; $b = $rgb & 0xFF;
                $v = (int)round(0.299*$r + 0.587*$g + 0.114*$b);
                $row[] = $v;
            }
            $gray[] = $row;
        }
        imagedestroy($tmp);
        // compute hash by horizontal gradient comparisons
        $bits = '';
        for ($y=0; $y<$th; $y++) {
            for ($x=0; $x<$size; $x++) {
                $bits .= ($gray[$y][$x] < $gray[$y][$x+1]) ? '1' : '0';
            }
        }
        // pack into hex string
        $hex = '';
        for ($i=0; $i<strlen($bits); $i+=4) {
            $n = bindec(substr($bits, $i, 4));
            $hex .= dechex($n);
        }
        return $hex;
    }

    public static function dhashFromFile(string $path, int $size = 8): ?string
    {
        if (!is_file($path)) return null;
        $im = self::loadImage($path);
        if (!$im) return null;
        $h = self::dhashFromGd($im, $size);
        imagedestroy($im);
        return $h;
    }

    public static function hammingDistHex(string $a, string $b): int
    {
        // Compare hex strings by XOR-ing per nibble
        $len = min(strlen($a), strlen($b)); $d = 0;
        for ($i=0; $i<$len; $i++) {
            $x = hexdec($a[$i]); $y = hexdec($b[$i]);
            $z = $x ^ $y; // 0..15
            // count bits in 0..15
            $d += [0,1,1,2,1,2,2,3,1,2,2,3,2,3,3,4][$z];
        }
        // Penalize length mismatch
        $d += (max(strlen($a), strlen($b)) - $len) * 4;
        return $d;
    }

    public static function loadImage(string $path)
    {
        if (!function_exists('imagecreatefromjpeg')) return null;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        try {
            if ($ext === 'jpg' || $ext === 'jpeg') return @imagecreatefromjpeg($path);
            if ($ext === 'png') return @imagecreatefrompng($path);
            if ($ext === 'gif') return @imagecreatefromgif($path);
            if ($ext === 'webp' && function_exists('imagecreatefromwebp')) return @imagecreatefromwebp($path);
        } catch (\Throwable $e) { return null; }
        return null;
    }

    // Build or update the on-disk hash cache; returns id => ['hash'=>hex, 'path'=>..., 'mtime'=>int]
    public static function buildHashCache(\PDO $pdo, string $assetsRoot, string $cachePath): array
    {
        $prev = [];
        if (is_file($cachePath)) {
            $txt = @file_get_contents($cachePath);
            $prev = json_decode((string)$txt, true) ?: [];
        }
        // Fetch known cards
        $rows = $pdo->query('SELECT id, set_code FROM ' . Database::t('cards_cache'))->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $id = (string)($r['id'] ?? ''); $set = (string)($r['set_code'] ?? '');
            if ($id === '' || $set === '') continue;
            // Resolve local file path
            if (!preg_match('/-(\d{1,4})$/', $id, $m)) continue;
            $num4 = str_pad($m[1], 4, '0', STR_PAD_LEFT);
            $dir = rtrim($assetsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cards' . DIRECTORY_SEPARATOR . $set;
            $path = null;
            foreach (self::exts as $ext) {
                $p = $dir . DIRECTORY_SEPARATOR . 'card-' . $num4 . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
                if (is_file($p)) { $path = $p; break; }
            }
            if (!$path) continue;
            $mt = (int)@filemtime($path);
            $prevEnt = $prev[$id] ?? null;
            if ($prevEnt && isset($prevEnt['mtime']) && (int)$prevEnt['mtime'] === $mt && !empty($prevEnt['hash'])) {
                $out[$id] = $prevEnt; continue;
            }
            $h = self::dhashFromFile($path, 8);
            if (!$h) continue;
            $out[$id] = ['hash' => $h, 'path' => $path, 'mtime' => $mt];
        }
        // Save if changed
        if ($out) {
            if (!is_dir(dirname($cachePath))) { @mkdir(dirname($cachePath), 0775, true); }
            @file_put_contents($cachePath, json_encode($out, JSON_UNESCAPED_UNICODE));
        }
        return $out;
    }
}
