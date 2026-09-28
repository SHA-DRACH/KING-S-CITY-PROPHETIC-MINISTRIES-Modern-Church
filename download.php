<?php
/**
 * Photo downloads for visitors.
 *   download.php?photo=ID   → the original full-quality photo
 *   download.php?album=ID   → every photo in the album as a ZIP
 * Only photos in published albums that allow downloads are served.
 */
require __DIR__ . '/includes/bootstrap.php';

/** Resolve a stored path to a real file inside /uploads or /assets/images (never elsewhere). */
function downloadable_file(?string $relative): ?string
{
    if (!$relative || !preg_match('#^(uploads|assets/images)/#', $relative)) {
        return null;
    }
    $full = realpath(APP_ROOT . '/' . $relative);
    $root = realpath(APP_ROOT);
    return ($full && str_starts_with($full, $root . DIRECTORY_SEPARATOR) && is_file($full)) ? $full : null;
}

function download_name(array $album, int $n, string $ext): string
{
    $slug = slugify(setting('church_short_name', 'kings-city'));
    return $slug . '-' . date($album['date_precision'] === 'month' ? 'Y-m' : 'Y-m-d', strtotime($album['album_date'])) . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT) . '.' . $ext;
}

if ($photoId = query_int('photo')) {
    $photo = DB::one('SELECT g.*, a.album_date, a.date_precision, a.allow_download, a.is_published AS album_published
        FROM gallery g JOIN photo_albums a ON a.id = g.album_id WHERE g.id = ? AND g.is_published = 1 AND g.media_type = "image"', [$photoId]);
    if (!$photo || !$photo['album_published'] || !$photo['allow_download'] || !($file = downloadable_file($photo['file_path']))) {
        abort(404, 'This photo is not available for download.');
    }
    DB::query('UPDATE gallery SET download_count = download_count + 1 WHERE id = ?', [$photoId]);
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ((new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($file));
    header('Content-Disposition: attachment; filename="' . download_name($photo, (int) $photo['id'], $ext) . '"');
    header('Cache-Control: private, max-age=0');
    readfile($file);
    exit;
}

if ($albumId = query_int('album')) {
    $album = Album::findPublic($albumId);
    if (!$album || !$album['allow_download']) {
        abort(404, 'This album is not available for download.');
    }
    if (!class_exists('ZipArchive')) {
        abort(404, 'Album downloads are not available on this server. Please download photos one by one.');
    }
    set_time_limit(300);
    $photos = DB::all('SELECT id, file_path FROM gallery WHERE album_id = ? AND is_published = 1 AND media_type = "image" ORDER BY id', [$albumId]);
    $tmp = tempnam(sys_get_temp_dir(), 'kcz');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $n = 0;
    foreach ($photos as $p) {
        if ($file = downloadable_file($p['file_path'])) {
            // Photos are already compressed: store them without recompressing (much faster)
            $name = download_name($album, ++$n, strtolower(pathinfo($file, PATHINFO_EXTENSION)));
            $zip->addFile($file, $name);
            $zip->setCompressionName($name, ZipArchive::CM_STORE);
        }
    }
    $zip->close();
    if (!$n) {
        @unlink($tmp);
        abort(404, 'This album has no photos yet.');
    }
    DB::query('UPDATE gallery SET download_count = download_count + 1 WHERE album_id = ? AND is_published = 1', [$albumId]);
    $zipName = slugify(setting('church_short_name', 'kings-city') . ' ' . Album::label($album)) . '.zip';
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    readfile($tmp);
    @unlink($tmp);
    exit;
}

abort(404);
