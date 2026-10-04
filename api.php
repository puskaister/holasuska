<?php
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

function json_out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_token() {
    $hdr = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    if (!hash_equals(API_TOKEN, $hdr)) {
        json_out(['error' => 'unauthorized'], 401);
    }
}

function read_json_body() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) json_out(['error' => 'invalid_json'], 400);
    return $data;
}

// Fetch an article page and return its og:image (or twitter:image) URL, or null.
// Parses the HTML with DOMDocument so meta tags split across lines (e.g. Economx) still match.
function fetch_og_image($url) {
    if (!preg_match('#^https?://#i', $url)) return null;
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36';
    $html = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15, CURLOPT_USERAGENT => $ua, CURLOPT_HTTPHEADER => ['Accept-Language: hu'],
        ]);
        $html = curl_exec($ch);
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 15, 'header' => "User-Agent: $ua\r\nAccept-Language: hu\r\n"]]);
        $html = @file_get_contents($url, false, $ctx);
    }
    if (!$html) return null;
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    $found = [];
    foreach ($doc->getElementsByTagName('meta') as $m) {
        $key = strtolower($m->getAttribute('property') ?: $m->getAttribute('name'));
        $content = trim($m->getAttribute('content'));
        if ($content !== '' && in_array($key, ['og:image', 'og:image:url', 'og:image:secure_url', 'twitter:image', 'twitter:image:src'], true) && !isset($found[$key])) {
            $found[$key] = $content;
        }
    }
    foreach (['og:image', 'og:image:secure_url', 'og:image:url', 'twitter:image', 'twitter:image:src'] as $k) {
        if (isset($found[$k]) && preg_match('#^https?://#i', $found[$k])) return $found[$k];
    }
    return null;
}

$db = get_db();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'list') {
    $timeline = [];
    $res = $db->query("SELECT id, category, articleDate AS date, dateLabel, source, title, url, summary, image FROM articles WHERE section = 'hirfolyam' ORDER BY articleDate DESC, id DESC");
    while ($row = $res->fetch_assoc()) {
        if ($row['image'] === null) unset($row['image']);
        $timeline[] = $row;
    }
    $reference = [];
    $res = $db->query("SELECT id, source, title, url FROM articles WHERE section = 'hatter' ORDER BY source ASC, id ASC");
    while ($row = $res->fetch_assoc()) {
        $reference[] = $row;
    }
    $status = ['lastChecked' => null, 'articleCount' => 0];
    $res = $db->query("SELECT lastChecked, articleCount FROM meta_status WHERE id = 1");
    if ($row = $res->fetch_assoc()) $status = $row;
    json_out(['timeline' => $timeline, 'reference' => $reference, 'status' => $status]);
}

if ($method === 'GET' && $action === 'urls') {
    $urls = [];
    $res = $db->query("SELECT url FROM articles");
    while ($row = $res->fetch_assoc()) $urls[] = $row['url'];
    json_out(['urls' => $urls]);
}

if ($method === 'POST' && $action === 'upsert_article') {
    require_token();
    $d = read_json_body();
    foreach (['id', 'section', 'source', 'title', 'url'] as $f) {
        if (empty($d[$f])) json_out(['error' => 'missing_field', 'field' => $f], 400);
    }
    $stmt = $db->prepare(
        "INSERT INTO articles (id, section, category, articleDate, dateLabel, source, title, url, summary, image)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           section = VALUES(section), category = VALUES(category), articleDate = VALUES(articleDate),
           dateLabel = VALUES(dateLabel), source = VALUES(source), title = VALUES(title),
           url = VALUES(url), summary = VALUES(summary), image = VALUES(image)"
    );
    $category = $d['category'] ?? null;
    $date = $d['date'] ?? null;
    $dateLabel = $d['dateLabel'] ?? null;
    $summary = $d['summary'] ?? null;
    $image = !empty($d['image']) ? $d['image'] : fetch_og_image($d['url']);
    $stmt->bind_param('ssssssssss', $d['id'], $d['section'], $category, $date, $dateLabel, $d['source'], $d['title'], $d['url'], $summary, $image);
    $stmt->execute();
    json_out(['ok' => true, 'id' => $d['id'], 'image' => $image]);
}

if ($method === 'POST' && $action === 'refetch_images') {
    // Fill in images for articles that have none (?all=1 re-fetches every article).
    require_token();
    set_time_limit(300);
    $where = !empty($_GET['all']) ? "section <> 'hatter'" : "section <> 'hatter' AND (image IS NULL OR image = '')";
    $res = $db->query("SELECT id, url FROM articles WHERE $where");
    $stmt = $db->prepare("UPDATE articles SET image = ? WHERE id = ?");
    $updated = [];
    $missing = [];
    while ($row = $res->fetch_assoc()) {
        $img = fetch_og_image($row['url']);
        if ($img) {
            $stmt->bind_param('ss', $img, $row['id']);
            $stmt->execute();
            $updated[$row['id']] = $img;
        } else {
            $missing[] = $row['id'];
        }
    }
    json_out(['ok' => true, 'updated' => $updated, 'missing' => $missing]);
}

if ($method === 'POST' && $action === 'set_status') {
    require_token();
    $d = read_json_body();
    $lastChecked = $d['lastChecked'] ?? null;
    $countRes = $db->query("SELECT COUNT(*) AS c FROM articles");
    $articleCount = (int)($countRes->fetch_assoc()['c']);
    $stmt = $db->prepare(
        "INSERT INTO meta_status (id, lastChecked, articleCount) VALUES (1, ?, ?)
         ON DUPLICATE KEY UPDATE lastChecked = VALUES(lastChecked), articleCount = VALUES(articleCount)"
    );
    $stmt->bind_param('si', $lastChecked, $articleCount);
    $stmt->execute();
    json_out(['ok' => true, 'lastChecked' => $lastChecked, 'articleCount' => $articleCount]);
}

if ($method === 'POST' && $action === 'mark_click') {
    // No token: called from public page JS. Scope is narrow (only the caller's own
    // most recent page_views row, matched by server-derived REMOTE_ADDR) so abuse
    // potential is limited to a visitor marking their own row as clicked.
    $d = read_json_body();
    $url = isset($d['url']) ? substr((string)$d['url'], 0, 600) : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if ($ip) {
        $stmt = $db->prepare(
            "UPDATE page_views SET clicked = 1, clickedUrl = ?
             WHERE ip = ? AND viewedAt >= (NOW() - INTERVAL 30 MINUTE)
             ORDER BY viewedAt DESC LIMIT 1"
        );
        $stmt->bind_param('ss', $url, $ip);
        $stmt->execute();
    }
    json_out(['ok' => true]);
}

json_out(['error' => 'unknown_action'], 404);
