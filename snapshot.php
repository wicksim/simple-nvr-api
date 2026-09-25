<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$camera = $_GET['camera'] ?? null;

if ($camera === "terrasse") {
    $rtsp = 'rtsp://terrasse:$Fr5Aq1Jh16!@192.168.0.101:554/stream2';
} else if ($camera === 'eingang') {
    $rtsp = 'rtsp://eingang:$Fr5Aq1Jh16!@192.168.0.103:554/stream2';
} else if ($camera === 'rueckseite_haus') {
    $rtsp = 'rtsp://rueckseite_haus:$Fr5Aq1Jh16!@192.168.0.102:554/stream2';
} else {
    http_response_code(400);
    exit('Invalid camera name');
}

$tmp = tempnam(sys_get_temp_dir(), 'frame_') . '.jpg';

$cmd = sprintf(
    'ffmpeg8 -rtsp_transport udp -probesize 4096 -analyzeduration 0 -i %s -vf "scale=640:-1" -frames:v 1 -q:v 2 -y %s 2>/dev/null',
    escapeshellarg($rtsp),
    escapeshellarg($tmp)
);

exec($cmd, $output, $returnCode);

if ($returnCode !== 0 || !is_file($tmp)) {
    http_response_code(500);
    exit('Unable to capture frame');
}

header('Content-Type: image/jpeg');
header('Cache-Control: no-store, no-cache, must-revalidate');
readfile($tmp);

unlink($tmp);