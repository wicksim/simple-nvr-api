<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$camera = $_GET['camera'] ?? '';

// Only allow letters, numbers, underscore and hyphen.
// This deliberately excludes '/', '\', '.', spaces, etc.
if (!preg_match('/^[A-Za-z0-9_-]+$/', $camera)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid camera name.']);
    exit;
}

$folder = '/volume1/nvr/data/' . $camera . '/events/';

$sinceHours = $_GET['since-hours'] ?? 24;
if ($sinceHours === '' || !is_numeric($sinceHours) || !is_finite((float) $sinceHours) || (float) $sinceHours < 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid since-hours.']);
    exit;
}

if (!is_dir($folder)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Camera folder not found.']);
    exit;
}
$sinceHours = (float) $sinceHours; // Current time and cutoff are explicitly UTC. 
$nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$cutoffUtc = $nowUtc->modify("-{$sinceHours} hours");

$files = scandir($folder);

$clips = [];

foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }


    /* * Expected filename: * * 20260918065924_20260918070030_PERSON.mkv * * start timestamp YYYYMMDDHHMMSS * end timestamp YYYYMMDDHHMMSS * trigger letters/numbers/underscore/hyphen * extension .mkv * * The entire filename must match this format. */
    if (!preg_match('/^(\d{14})_(\d{14})_([A-Za-z0-9_-]+)\.mkv$/', $file, $matches)) {
        continue;
    }
    $startTimestamp = $matches[1];
    $endTimestamp = $matches[2];
    $triggerString = $matches[3];// Interpret timestamps explicitly as UTC. 

    $startUtc = DateTimeImmutable::createFromFormat('!YmdHis', $startTimestamp, new DateTimeZone('UTC'));
    $endUtc = DateTimeImmutable::createFromFormat('!YmdHis', $endTimestamp, new DateTimeZone('UTC'));

    // Reject invalid calendar dates/times.
    if ($startUtc === false || $startUtc->format('YmdHis') !== $startTimestamp || $endUtc === false || $endUtc->format('YmdHis') !== $endTimestamp) {
        continue;
    }

    // Only include files whose start time is within the requested window. 
    if ($startUtc < $cutoffUtc) {
        continue;
    }

    $path = $folder . '/' . $file;
    if (!is_file($path)) {
        continue;
    } // Split concatenated triggers into a list.
    $triggers = preg_split('/[_-]+/', $triggerString);
    $clips[] = ['start' => $startUtc->format('Y-m-d\TH:i:s\Z'), 'end' => $endUtc->format('Y-m-d\TH:i:s\Z'), 'triggers' => array_values(array_filter($triggers)), 'filename' => $file];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($clips, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);