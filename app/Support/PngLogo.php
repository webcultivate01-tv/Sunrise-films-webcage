<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Turns the letterhead mark (an 8-bit RGBA PNG) into a small greyscale image
 * SimplePdf can embed: black where the mark is opaque, white where it is
 * transparent - which is exactly how it sits on the white receipt card.
 *
 * There is no GD extension and no PDF library here, so the PNG is decoded by
 * hand (inflate, undo the scanline filters, box-average the alpha channel down).
 * That is a couple of seconds of work on the full-size logo, so the result is
 * cached under storage/cache and rebuilt only when the logo file changes.
 */
final class PngLogo
{
    /** Source pixels averaged into one output pixel, per axis. */
    private const SHRINK = 4;

    /**
     * @return array{width: int, height: int, data: string}|null Null when the file is missing or not an 8-bit RGBA PNG.
     */
    public static function grayscale(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $cacheFile = BASE_PATH . '/storage/cache/logo-' . md5($path . '|' . filemtime($path) . '|' . self::SHRINK) . '.bin';

        if (is_file($cacheFile)) {
            $cached = @unserialize((string) file_get_contents($cacheFile), ['allowed_classes' => false]);

            if (is_array($cached) && isset($cached['width'], $cached['height'], $cached['data'])) {
                return $cached;
            }
        }

        $result = self::decode((string) file_get_contents($path));

        if ($result !== null && is_dir(dirname($cacheFile))) {
            @file_put_contents($cacheFile, serialize($result), LOCK_EX);
        }

        return $result;
    }

    /**
     * @return array{width: int, height: int, data: string}|null
     */
    private static function decode(string $png): ?array
    {
        if (substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }

        $width = $height = 0;
        $idat  = '';
        $pos   = 8;
        $len   = strlen($png);

        while ($pos + 8 <= $len) {
            $size = unpack('N', substr($png, $pos, 4))[1];
            $type = substr($png, $pos + 4, 4);
            $body = substr($png, $pos + 8, $size);
            $pos += 12 + $size;

            if ($type === 'IHDR') {
                $h = unpack('Nw/Nh/Cdepth/Ctype/Ccomp/Cfilter/Cinterlace', $body);

                // Only the 8-bit, non-interlaced RGBA the logo is saved as.
                if ($h['depth'] !== 8 || $h['type'] !== 6 || $h['interlace'] !== 0) {
                    return null;
                }

                $width  = $h['w'];
                $height = $h['h'];
            } elseif ($type === 'IDAT') {
                $idat .= $body;
            } elseif ($type === 'IEND') {
                break;
            }
        }

        $raw = @gzuncompress($idat);

        if ($raw === false || $width === 0 || $height === 0) {
            return null;
        }

        $stride = $width * 4;
        $shrink = self::SHRINK;
        $outW   = intdiv($width, $shrink);
        $outH   = intdiv($height, $shrink);
        $sums   = array_fill(0, $outW, 0);
        $out    = '';
        $prev   = array_fill(0, $stride, 0);

        for ($y = 0; $y < $height; $y++) {
            $filter = ord($raw[$y * ($stride + 1)]);
            $line   = array_values(unpack('C*', substr($raw, $y * ($stride + 1) + 1, $stride)));

            for ($i = 0; $i < $stride; $i++) {
                $a = $i >= 4 ? $line[$i - 4] : 0;
                $b = $prev[$i];

                switch ($filter) {
                    case 1:
                        $line[$i] = ($line[$i] + $a) & 255;
                        break;
                    case 2:
                        $line[$i] = ($line[$i] + $b) & 255;
                        break;
                    case 3:
                        $line[$i] = ($line[$i] + (($a + $b) >> 1)) & 255;
                        break;
                    case 4:
                        $c  = $i >= 4 ? $prev[$i - 4] : 0;
                        $p  = $a + $b - $c;
                        $pa = abs($p - $a);
                        $pb = abs($p - $b);
                        $pc = abs($p - $c);

                        $line[$i] = ($line[$i] + ($pa <= $pb && $pa <= $pc ? $a : ($pb <= $pc ? $b : $c))) & 255;
                        break;
                }
            }

            $prev = $line;

            // Accumulate the alpha byte (every 4th, starting at offset 3) of
            // this row into the output pixel columns.
            $blockRow = intdiv($y, $shrink);

            if ($blockRow < $outH) {
                for ($x = 0; $x < $outW * $shrink; $x++) {
                    $sums[intdiv($x, $shrink)] += $line[$x * 4 + 3];
                }
            }

            if (($y + 1) % $shrink === 0 && $blockRow < $outH) {
                $div = $shrink * $shrink;

                foreach ($sums as $column => $sum) {
                    $out .= chr(255 - intdiv($sum, $div));
                    $sums[$column] = 0;
                }
            }
        }

        return ['width' => $outW, 'height' => $outH, 'data' => $out];
    }
}
