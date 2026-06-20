<?php
/**
 * scripture.php — API để đọc file Kinh Thánh đối đáp
 * GET ?action=list      → danh sách file
 * GET ?action=get&file=xxx → nội dung 1 file (parsed)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$dir = dirname(__DIR__) . '/docs/Kinh thanh doi dap/';

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $files = glob($dir . '*.txt');
    $list  = [];
    foreach ($files as $f) {
        $base  = basename($f, '.txt');
        // Parse number from filename like "1 Đấng Tạo Hóa"
        if (preg_match('/^(\d+)\s+(.+)$/', $base, $m)) {
            $list[] = ['num' => (int)$m[1], 'title' => $m[2], 'file' => $base];
        } else {
            $list[] = ['num' => 0, 'title' => $base, 'file' => $base];
        }
    }
    usort($list, fn($a, $b) => $a['num'] - $b['num']);
    echo json_encode(['ok' => true, 'count' => count($list), 'list' => $list], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'get') {
    $file = $_GET['file'] ?? '';
    // Sanitize: no path traversal
    $file = basename($file);
    $path = $dir . $file . '.txt';
    if (!file_exists($path)) {
        echo json_encode(['ok' => false, 'error' => 'File not found']);
        exit;
    }

    $raw  = file_get_contents($path);
    // Normalize line endings
    $raw  = str_replace("\r\n", "\n", $raw);
    $lines = explode("\n", $raw);

    $title   = '';
    $slides  = [];   // [{role:'le'|'chan'|'ref'|'all', text:string}]
    $curRole = '';
    $curText = '';

    foreach ($lines as $line) {
        $line = trim($line);

        // Title tags like <Đấng Tạo Hóa>
        if (preg_match('/^<Kinh thánh đối đáp/ui', $line)) continue;
        if (preg_match('/^<(.+)>$/', $line, $m) && $title === '') {
            $title = $m[1];
            continue;
        }

        // Role markers
        if ($line === '<lẻ>') {
            if ($curText !== '') {
                $slides[] = ['role' => $curRole ?: 'all', 'text' => trim($curText)];
                $curText  = '';
            }
            $curRole = 'le';
            continue;
        }
        if ($line === '<chẳn>') {
            if ($curText !== '') {
                $slides[] = ['role' => $curRole ?: 'all', 'text' => trim($curText)];
                $curText  = '';
            }
            $curRole = 'chan';
            continue;
        }
        if ($line === '<tất cả>' || $line === '<tất cả>') {
            if ($curText !== '') {
                $slides[] = ['role' => $curRole ?: 'all', 'text' => trim($curText)];
                $curText  = '';
            }
            $curRole = 'all';
            continue;
        }

        // Scripture reference like (Sáng-thế ký 1:1-6)
        if (preg_match('/^\(.+\)$/', $line)) {
            if ($curText !== '') {
                $slides[] = ['role' => $curRole ?: 'all', 'text' => trim($curText)];
                $curText  = '';
            }
            $slides[] = ['role' => 'ref', 'text' => $line];
            $curRole  = '';
            continue;
        }

        // Accumulate text
        if ($line !== '') {
            $curText .= ($curText ? ' ' : '') . $line;
        } elseif ($curText !== '') {
            // Empty line = end of this block
            $slides[] = ['role' => $curRole ?: 'all', 'text' => trim($curText)];
            $curText  = '';
        }
    }
    if ($curText !== '') {
        $slides[] = ['role' => $curRole ?: 'all', 'text' => trim($curText)];
    }

    // Filter empty
    $slides = array_values(array_filter($slides, fn($s) => $s['text'] !== ''));

    echo json_encode([
        'ok'     => true,
        'title'  => $title ?: $file,
        'file'   => $file,
        'slides' => $slides,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
