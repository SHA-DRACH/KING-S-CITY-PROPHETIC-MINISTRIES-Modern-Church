<?php
/**
 * Chunked upload receiver for large files (sermon & hero videos, audio).
 * The browser sends the file in ~5 MB pieces so PHP's post_max_size is never hit.
 * After the last piece the file is validated exactly like a normal upload and a
 * one-time token is returned; the form then submits "<field>__chunked" = token.
 */
require __DIR__ . '/partials/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort(405);
}
$type = (string) ($_POST['type'] ?? '');
$allowed = ['video' => ['hero_video.upload', 'sermons.create', 'sermons.edit', 'media.upload', 'gallery.upload'],
            'audio' => ['sermons.create', 'sermons.edit', 'media.upload']];
if (!isset($allowed[$type])) {
    json_response(['ok' => false, 'message' => 'Unsupported file type.'], 422);
}
require_permission($allowed[$type]);

$uploadId = (string) ($_POST['upload_id'] ?? '');
$index = (int) ($_POST['index'] ?? -1);
$total = (int) ($_POST['total'] ?? 0);
$name = basename((string) ($_POST['name'] ?? ''));
$size = (int) ($_POST['size'] ?? 0);
$subdir = in_array($_POST['dir'] ?? '', ['videos', 'sermons', 'gallery'], true) ? $_POST['dir'] : 'videos';
$chunk = $_FILES['chunk'] ?? null;

if (!preg_match('/^[a-f0-9]{32}$/', $uploadId) || $index < 0 || $total < 1 || $index >= $total || $total > 2000 || $name === '') {
    json_response(['ok' => false, 'message' => 'Invalid upload request.'], 422);
}
if (!$chunk || $chunk['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($chunk['tmp_name'])) {
    json_response(['ok' => false, 'message' => 'A part of the file failed to upload. Please try again.'], 422);
}
$maxBytes = Upload::maxMb($type) * 1024 * 1024;
if ($size > $maxBytes) {
    json_response(['ok' => false, 'message' => 'The file must be smaller than ' . Upload::maxMb($type) . ' MB.'], 422);
}
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if (!isset(UPLOAD_RULES[$type]['types'][$ext])) {
    json_response(['ok' => false, 'message' => 'Allowed file types: ' . strtoupper(implode(', ', array_keys(UPLOAD_RULES[$type]['types']))) . '.'], 422);
}

$tmpDir = UPLOAD_DIR . '/tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0755, true);
}
// Remove abandoned partial uploads older than a day
if ($index === 0) {
    foreach (glob("$tmpDir/*.part") ?: [] as $old) {
        if (filemtime($old) < time() - 86400) {
            @unlink($old);
        }
    }
}
$part = $tmpDir . '/' . user_id() . '_' . $uploadId . '.part';
if ($index === 0 && is_file($part)) {
    unlink($part);
}
if ($index > 0 && (!is_file($part) || (int) ($_SESSION['chunk_progress'][$uploadId] ?? -1) !== $index - 1)) {
    json_response(['ok' => false, 'message' => 'Upload interrupted. Please try again.'], 422);
}

$in = fopen($chunk['tmp_name'], 'rb');
$out = fopen($part, 'ab');
stream_copy_to_stream($in, $out);
fclose($in);
fclose($out);
$_SESSION['chunk_progress'][$uploadId] = $index;

clearstatcache(true, $part);
if (filesize($part) > $maxBytes) {
    unlink($part);
    json_response(['ok' => false, 'message' => 'The file is too large.'], 422);
}

if ($index < $total - 1) {
    json_response(['ok' => true, 'received' => $index + 1]);
}

// Last piece: validate the complete file like any other upload
unset($_SESSION['chunk_progress'][$uploadId]);
try {
    $path = Upload::validateAndSave($part, $name, (int) filesize($part), $type, $subdir, 'chunked');
} catch (UploadException $e) {
    @unlink($part);
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}
$token = bin2hex(random_bytes(16));
$_SESSION['chunked'][$token] = ['path' => $path, 'type' => $type, 'user' => user_id()];
log_activity('upload', 'media', 'Uploaded ' . $type . ' ' . $name . ' (' . Upload::humanSize((int) filesize(APP_ROOT . '/' . $path)) . ')');
json_response(['ok' => true, 'done' => true, 'token' => $token, 'url' => media_url($path)]);
