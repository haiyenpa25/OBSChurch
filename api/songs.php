<?php
/**
 * Songs API — cho Lyric Controller
 * Rule: UTF-8, json_encode(JSON_UNESCAPED_UNICODE)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$DB = __DIR__ . '/../data/thanh-ca.db';

if (!file_exists($DB)) {
    echo json_encode(['error' => 'DB chưa tồn tại. Chạy txt-import.php trước.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = new PDO("sqlite:$DB");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("PRAGMA encoding='UTF-8'");

$action     = $_GET['action'] ?? 'list';
$collection = $_GET['collection'] ?? '';
$q          = $_GET['q'] ?? '';
$id         = $_GET['id'] ?? '';
$limit      = min(2000, intval($_GET['limit'] ?? 500));

switch ($action) {

    // ── LIST: Danh sách bài hát (chỉ trả number + title + author để nhẹ) ─
    case 'list': {
        $where = []; $params = [];
        if ($collection) {
            $where[] = "source_id = :src";
            $params[':src'] = $collection;
        }
        if ($q) {
            $where[] = "(title LIKE :q OR number LIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }
        $sql = "SELECT id,slug,number,title,author,cat_top,source_id
                FROM songs" .
               ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
               " ORDER BY source_id, CAST(number AS INTEGER) LIMIT $limit";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'ok'    => true,
            'count' => count($songs),
            'songs' => $songs,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;
    }

    // ── SONG: Chi tiết 1 bài kèm sections ────────────────────────────────
    case 'song': {
        if (!$id) { echo json_encode(['error' => 'Thiếu id'], JSON_UNESCAPED_UNICODE); exit; }

        // Tìm theo slug hoặc id
        if (is_numeric($id)) {
            $stmt = $db->prepare("SELECT * FROM songs WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => intval($id)]);
        } else {
            $stmt = $db->prepare("SELECT * FROM songs WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $id]);
        }
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$song) { echo json_encode(['error' => 'Không tìm thấy bài'], JSON_UNESCAPED_UNICODE); exit; }

        $song['sections'] = json_decode($song['sections_json'] ?? '[]', true) ?: [];
        $song['lyrics_arr'] = json_decode($song['lyrics_json'] ?? '[]', true) ?: [];
        unset($song['sections_json'], $song['lyrics_json']);

        echo json_encode(['ok' => true, 'song' => $song], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;
    }

    // ── SEARCH ────────────────────────────────────────────────────────────
    case 'search': {
        if (!$q) { echo json_encode(['songs' => []], JSON_UNESCAPED_UNICODE); exit; }
        $stmt = $db->prepare("SELECT id,slug,number,title,author,cat_top,source_id
            FROM songs WHERE title LIKE :q OR number LIKE :q2
            ORDER BY CAST(number AS INTEGER) LIMIT 50");
        $stmt->execute([':q' => '%'.$q.'%', ':q2' => '%'.$q.'%']);
        echo json_encode(['songs' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        break;
    }

    // ── STATS ─────────────────────────────────────────────────────────────
    case 'stats': {
        $total = $db->query("SELECT COUNT(*) FROM songs")->fetchColumn();
        $byCol = $db->query("SELECT source_id, COUNT(*) as cnt FROM songs GROUP BY source_id")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['total' => $total, 'by_collection' => $byCol], JSON_UNESCAPED_UNICODE);
        break;
    }

    default:
        echo json_encode(['error' => 'action không hợp lệ'], JSON_UNESCAPED_UNICODE);
}
