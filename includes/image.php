<?php
/**
 * Thumbnail generation (GD). Gallery grids load small JPEG thumbnails; the
 * original full-quality file is what visitors download.
 */
final class Image
{
    /**
     * Create a thumbnail for an uploaded image.
     * @return array{thumb: ?string, width: ?int, height: ?int}
     */
    public static function thumbnail(string $relative, int $maxEdge = 720): array
    {
        $src = APP_ROOT . '/' . $relative;
        $info = @getimagesize($src);
        if (!$info || !function_exists('imagecreatetruecolor')) {
            return ['thumb' => null, 'width' => null, 'height' => null];
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
            IMAGETYPE_PNG  => @imagecreatefrompng($src),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
            default        => false,
        };
        if (!$img) {
            return ['thumb' => null, 'width' => $info[0], 'height' => $info[1]];
        }

        // Phones store rotation in EXIF; apply it so thumbnails are upright.
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($src)['Orientation'] ?? 1);
            $img = match ($orientation) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => $img,
            };
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $maxEdge / max($w, $h));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));
        $thumb = imagecreatetruecolor($tw, $th);
        imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255)); // flatten PNG transparency
        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $tw, $th, $w, $h);

        $dir = UPLOAD_DIR . '/gallery/thumbs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = pathinfo($relative, PATHINFO_FILENAME) . '-t.jpg';
        imagejpeg($thumb, "$dir/$name", 82);
        imagedestroy($thumb);
        imagedestroy($img);

        return ['thumb' => "uploads/gallery/thumbs/$name", 'width' => min($w, 65535), 'height' => min($h, 65535)];
    }
}
