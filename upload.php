<?php
// upload.php — receives JPEG frames from C++ students
// Place this file on your web host

define('SECRET', 'classwatch2024'); // must match C++ client SECRET

$secret = $_POST['secret'] ?? '';
if ($secret !== SECRET) {
    http_response_code(403);
    die('forbidden');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
// sanitize IP for filename
$sid = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $ip);

if (!isset($_FILES['frame']) || $_FILES['frame']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('no frame');
}

$tmpFile = $_FILES['frame']['tmp_name'];
$size    = $_FILES['frame']['size'];

// Sanity checks
if ($size <= 0 || $size > 10000000) {
    http_response_code(400);
    die('bad size');
}

// Verify it looks like a JPEG
$bytes = file_get_contents($tmpFile, false, null, 0, 3);
if (substr($bytes, 0, 2) !== "\xFF\xD8") {
    http_response_code(400);
    die('not jpeg');
}

// Save frame
$framesDir = __DIR__ . '/frames';
if (!is_dir($framesDir)) mkdir($framesDir, 0755, true);

$framePath = $framesDir . '/' . $sid . '.jpg';
move_uploaded_file($tmpFile, $framePath);

// Update student registry
$regPath = $framesDir . '/students.json';
$students = [];
if (file_exists($regPath)) {
    $students = json_decode(file_get_contents($regPath), true) ?? [];
}

$students[$sid] = [
    'ip'    => $ip,
    'since' => $students[$sid]['since'] ?? date('H:i:s'),
    'last'  => time(),
];

// Remove students inactive for more than 15 seconds
foreach ($students as $k => $v) {
    if (time() - $v['last'] > 15) {
        unset($students[$k]);
        // Remove their frame file too
        $old = $framesDir . '/' . $k . '.jpg';
        if (file_exists($old)) unlink($old);
    }
}

file_put_contents($regPath, json_encode($students), LOCK_EX);

http_response_code(200);
echo 'ok';
?>
