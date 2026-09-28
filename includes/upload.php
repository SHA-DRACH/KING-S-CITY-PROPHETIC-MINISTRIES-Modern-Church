<?php
/**
 * Secure file uploads.
 *  - extension whitelist + real MIME detection (finfo) + size limits
 *  - images are verified with getimagesize()
 *  - random filenames (original name is never used on disk)
 *  - PHP execution disabled in /uploads via .htaccess
 */

class UploadException extends RuntimeException {}

final class Upload
{
    /**
     * Validate and store an uploaded file.
     * @param array  $file   entry from $_FILES
     * @param string $type   image|video|audio|document
     * @param string $subdir folder under /uploads
     * @return string relative path, e.g. "uploads/sermons/ab12….jpg"
     */
    public static function store(array $file, string $type, string $subdir, string $module = ''): string
    {
        $rules = UPLOAD_RULES[$type] ?? throw new UploadException('Unsupported upload type.');

        if (!isset($file['error']) || is_array($file['error'])) {
            throw new UploadException('Invalid upload.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new UploadException(match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the server allows (check upload_max_filesize / post_max_size in php.ini).',
                UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_FILE => 'No file was selected.',
                default => 'The upload failed on the server.',
            });
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new UploadException('Invalid upload source.');
        }
        return self::validateAndSave($file['tmp_name'], (string) $file['name'], (int) $file['size'], $type, $subdir, $module, true);
    }

    public static function maxMb(string $type): int
    {
        $rules = UPLOAD_RULES[$type];
        return $type === 'video' ? max(10, (int) setting('max_video_upload_mb', (string) $rules['max_mb'])) : $rules['max_mb'];
    }

    /**
     * Shared validation for normal and chunked uploads: size, extension whitelist,
     * real MIME type (finfo) and image integrity, then a random filename.
     */
    public static function validateAndSave(string $src, string $originalName, int $size, string $type, string $subdir, string $module = '', bool $isHttpUpload = false): string
    {
        $rules = UPLOAD_RULES[$type] ?? throw new UploadException('Unsupported upload type.');
        $subdir = preg_replace('/[^a-z0-9_\/-]/', '', strtolower($subdir)) ?: 'documents';

        $maxMb = self::maxMb($type);
        if ($size <= 0 || $size > $maxMb * 1024 * 1024) {
            throw new UploadException("The file must be smaller than {$maxMb} MB.");
        }
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset($rules['types'][$ext])) {
            throw new UploadException('Allowed file types: ' . strtoupper(implode(', ', array_keys($rules['types']))) . '.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($src) ?: '';
        if (!in_array($mime, $rules['types'][$ext], true)) {
            throw new UploadException('The file content does not match its extension' . (config('app.debug') ? " (detected $mime)." : '.'));
        }
        if ($type === 'image' && @getimagesize($src) === false) {
            throw new UploadException('The image appears to be corrupted.');
        }

        $dir = UPLOAD_DIR . '/' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new UploadException('Upload folder is not writable.');
        }
        $name = date('Ymd') . '-' . bin2hex(random_bytes(12)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $ok = $isHttpUpload ? move_uploaded_file($src, "$dir/$name") : rename($src, "$dir/$name");
        if (!$ok) {
            throw new UploadException('Could not save the uploaded file.');
        }
        @chmod("$dir/$name", 0644);

        $relative = "uploads/$subdir/$name";
        DB::insert('media', [
            'file_path'     => $relative,
            'original_name' => mb_substr(basename($originalName), 0, 255),
            'mime_type'     => $mime,
            'file_type'     => $type,
            'size_bytes'    => $size,
            'module'        => $module ?: $subdir,
            'uploaded_by'   => user_id(),
        ]);
        return $relative;
    }

    /**
     * Store $_FILES[$field] if a file was chosen; returns null when the field is empty.
     * Large files arrive in chunks first (admin/upload-chunk.php); the form then only
     * sends "<field>__chunked" = a one-time token proving this user uploaded it.
     */
    public static function fromField(string $field, string $type, string $subdir, string $module = ''): ?string
    {
        $token = $_POST[$field . '__chunked'] ?? '';
        if (is_string($token) && $token !== '') {
            $done = $_SESSION['chunked'][$token] ?? null;
            if (!$done || $done['type'] !== $type || (int) $done['user'] !== (int) user_id()) {
                throw new UploadException('The uploaded file could not be verified. Please upload it again.');
            }
            unset($_SESSION['chunked'][$token]);
            return $done['path'];
        }
        $f = $_FILES[$field] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return self::store($f, $type, $subdir, $module);
    }

    /** Delete a stored upload (only files inside /uploads can ever be removed). */
    public static function delete(?string $relative): void
    {
        if (!$relative || !str_starts_with($relative, 'uploads/')) {
            return;
        }
        $full = realpath(APP_ROOT . '/' . $relative);
        $root = realpath(UPLOAD_DIR);
        if ($full && $root && str_starts_with($full, $root . DIRECTORY_SEPARATOR) && is_file($full)) {
            @unlink($full);
            DB::delete('media', 'file_path = ?', [$relative]);
        }
    }

    public static function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }

    public static function accept(string $type): string
    {
        return implode(',', array_map(fn($e) => '.' . $e, array_keys(UPLOAD_RULES[$type]['types'])));
    }
}
