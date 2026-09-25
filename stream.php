<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// ------------------------------------------------------------
// Input parameters
// ------------------------------------------------------------

$camera = $_GET['camera'] ?? '';
$file = $_GET['file'] ?? '';

// Camera names are restricted to a single safe path component.
if (!preg_match('/^[A-Za-z0-9_-]+$/', $camera)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    die('Invalid camera name.');
}

// ------------------------------------------------------------
// Validate filename
// ------------------------------------------------------------

/*
 * Expected filename:
 *
 * 20260918065924_20260918070030_PERSON_CAR.mkv
 *
 *   start timestamp   YYYYMMDDHHMMSS
 *   end timestamp     YYYYMMDDHHMMSS
 *   triggers          one or more [A-Za-z0-9_-] parts
 *   extension          .mkv
 *
 * The filename must be a single path component.
 */
if (
    !preg_match(
        '/^\d{14}_\d{14}_[A-Za-z0-9_-]+\.mkv$/',
        $file
    )
) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    die('Invalid filename.');
}

// ------------------------------------------------------------
// Build path
// ------------------------------------------------------------


$folder = '/volume1/nvr/data/' . $camera . '/events/';
$path = $folder . '/' . $file;

if (!is_dir($folder)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    die('Camera folder not found.');
}

if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    die('File not found.');
}

// ------------------------------------------------------------
// Open file
// ------------------------------------------------------------

$size = filesize($path);

if ($size === false) {
    http_response_code(500);
    die('Unable to determine file size.');
}

$handle = fopen($path, 'rb');

if ($handle === false) {
    http_response_code(500);
    die('Unable to open file.');
}

// ------------------------------------------------------------
// Handle HTTP Range requests
// ------------------------------------------------------------

$start = 0;
$end = $size - 1;

if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];

    if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {

        if ($matches[1] === '') {
            // Suffix range, e.g. bytes=-500
            $suffix = (int) $matches[2];

            if ($suffix > 0) {
                $start = max(0, $size - $suffix);
            }
        } else {
            $start = (int) $matches[1];

            if ($matches[2] !== '') {
                $end = (int) $matches[2];
            }
        }

        // Clamp the requested range.
        $start = max(0, $start);
        $end = min($size - 1, $end);

        if ($start > $end || $start >= $size) {
            fclose($handle);

            http_response_code(416);
            header("Content-Range: bytes */$size");
            exit;
        }

        http_response_code(206);
    }
}

// ------------------------------------------------------------
// Response headers
// ------------------------------------------------------------

$length = $end - $start + 1;

header('Content-Type: video/x-matroska');
header('Content-Length: ' . $length);
header('Accept-Ranges: bytes');
header('Content-Disposition: inline; filename="' . addslashes($file) . '"');

if (isset($_SERVER['HTTP_RANGE'])) {
    header("Content-Range: bytes $start-$end/$size");
}

// ------------------------------------------------------------
// Stream
// ------------------------------------------------------------

if (fseek($handle, $start, SEEK_SET) !== 0) {
    fclose($handle);
    http_response_code(500);
    die('Unable to seek in file.');
}

$remaining = $length;

while ($remaining > 0 && !feof($handle)) {

    // Keep memory usage low.
    $chunkSize = min(1024 * 1024, $remaining);

    $buffer = fread($handle, $chunkSize);

    if ($buffer === false) {
        break;
    }

    echo $buffer;

    $remaining -= strlen($buffer);

    // Ensure data is sent immediately.
    flush();
}

fclose($handle);