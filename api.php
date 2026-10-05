<?php
// Tiny storage API for the group-photo page. Upload next to index.html.
// Make sure this folder is writable by PHP (students.json is created here). No password: anyone can edit.
const DATA_FILE = __DIR__ . '/students.json';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function defaults() {
    $o = [];
    foreach ([12, 32, 52, 72] as $i => $x)
        $o[] = ['id' => 's' . ($i + 1), 'name' => 'Student-' . ($i + 1), 'x' => $x, 'y' => 30, 'w' => 10, 'h' => 34,
                'job' => '', 'current' => '', 'permanent' => '', 'mobile' => ''];
    return $o;
}
function fail($code, $msg) { http_response_code($code); echo json_encode(['error' => $msg]); exit; }
function txt($v, $n = 120) { return mb_substr(trim((string)$v), 0, $n); }
function num($v, $min, $max) { return max($min, min($max, round((float)$v, 2))); }

// Read-modify-write under an exclusive lock so simultaneous edits don't clobber each other.
function mutate(callable $fn) {
    $fh = fopen(DATA_FILE, 'c+');
    if (!$fh) fail(500, 'Cannot open students.json (check folder permissions)');
    flock($fh, LOCK_EX);
    $d = json_decode(stream_get_contents($fh), true);
    if (!is_array($d) || !$d) $d = defaults();
    $d = $fn($d);
    ftruncate($fh, 0); rewind($fh);
    fwrite($fh, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fh); flock($fh, LOCK_UN); fclose($fh);
    return $d;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['students' => mutate(function ($d) { return $d; })]); exit;
}

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $in['action'] ?? '';

if ($action === 'details') {            // anyone may fill in details
    $id = (string)($in['id'] ?? '');
    $d = mutate(function ($d) use ($id, $in) {
        foreach ($d as &$s) if ($s['id'] === $id)
            foreach (['job', 'current', 'permanent', 'mobile'] as $k) if (isset($in[$k])) $s[$k] = txt($in[$k]);
        return $d;
    });
    echo json_encode(['students' => $d]); exit;
}

if ($action === 'layout') {             // add / move / resize / rename / remove boxes
    if (!is_array($in['students'] ?? null)) fail(400, 'Bad data');
    $new = $in['students'];
    $d = mutate(function ($old) use ($new) {
        $by = []; foreach ($old as $s) $by[$s['id']] = $s;
        $out = [];
        foreach (array_slice($new, 0, 100) as $n) {
            $id = txt($n['id'] ?? '', 40); if ($id === '') continue;
            $b = $by[$id] ?? ['job' => '', 'current' => '', 'permanent' => '', 'mobile' => ''];
            $out[] = array_merge($b, ['id' => $id, 'name' => txt($n['name'] ?? 'Student', 60) ?: 'Student',
                'x' => num($n['x'] ?? 0, 0, 100), 'y' => num($n['y'] ?? 0, 0, 100),
                'w' => num($n['w'] ?? 10, 2, 100), 'h' => num($n['h'] ?? 30, 2, 100)]);
        }
        return $out ?: $old;
    });
    echo json_encode(['students' => $d]); exit;
}
fail(400, 'Unknown action');
