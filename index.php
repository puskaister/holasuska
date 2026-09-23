<?php
require __DIR__ . '/db.php';
$db = get_db();

$stmt = $db->prepare("INSERT INTO page_views (ip, userAgent) VALUES (?, ?)");
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
$stmt->bind_param('ss', $ip, $ua);
$stmt->execute();

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

$videos = [];
$res = $db->query("SELECT id, dateLabel, source, title, url, summary, image FROM articles WHERE section = 'video' ORDER BY articleDate DESC, id DESC");
while ($row = $res->fetch_assoc()) {
    $videos[] = $row;
}

$lastChecked = null;
$articleCount = count($timeline) + count($reference);
$res = $db->query("SELECT lastChecked, articleCount FROM meta_status WHERE id = 1");
if ($row = $res->fetch_assoc()) {
    $lastChecked = $row['lastChecked'];
}

$jsonOpts = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;

$categoryLabels = ['jogalkotas' => 'Jogalkotás', 'vita' => 'Vita és kritika', 'elemzes' => 'Elemzés', 'alapinfo' => 'Alapinfo'];

$metaTitle = 'NVVH – Nemzeti Vagyonvisszaszerzési és Vagyonvédelmi Hivatal hírfigyelő';
$metaDescription = 'Napra kész hírfigyelő a Nemzeti Vagyonvisszaszerzési és Vagyonvédelmi Hivatalról (NVVH): jogalkotás, viták és elemzések egy helyen, magyar hírportálokból gyűjtve.';
$canonicalUrl = 'http://holasuska.hu/';

function nvvh_hash_id($str) {
    $h = 0;
    $len = strlen($str);
    for ($i = 0; $i < $len; $i++) {
        $h = ($h * 31 + ord($str[$i])) & 0xFFFFFFFF;
    }
    return $h;
}

function nvvh_category_color_var($cat) {
    if ($cat === 'jogalkotas') return 'var(--law)';
    if ($cat === 'vita') return 'var(--dispute)';
    if ($cat === 'elemzes') return 'var(--analysis)';
    return 'var(--brass)';
}

function nvvh_entry_visual_svg($item, $no) {
    $h = nvvh_hash_id($item['id']);
    $rayCount = 10 + ($h % 5);
    $rotate = $h % 360;
    $color = nvvh_category_color_var($item['category'] ?? '');
    $rays = '';
    for ($i = 0; $i < $rayCount; $i++) {
        $angle = (360 / $rayCount) * $i;
        $rad = $angle * M_PI / 180;
        $x1 = number_format(50 + cos($rad) * 30, 1, '.', '');
        $y1 = number_format(50 + sin($rad) * 30, 1, '.', '');
        $x2 = number_format(50 + cos($rad) * 38, 1, '.', '');
        $y2 = number_format(50 + sin($rad) * 38, 1, '.', '');
        $rays .= '<line x1="'.$x1.'" y1="'.$y1.'" x2="'.$x2.'" y2="'.$y2.'" stroke="'.$color.'" stroke-width="1" stroke-linecap="round" opacity="0.65"/>';
    }
    $label = '№ ' . str_pad((string)$no, 2, '0', STR_PAD_LEFT);
    return '<svg viewBox="0 0 100 100" preserveAspectRatio="xMidYMid meet" role="img" aria-hidden="true">' .
        '<rect width="100" height="100" fill="var(--paper-raised)"/>' .
        '<g transform="rotate('.$rotate.' 50 50)">'.$rays.'</g>' .
        '<circle cx="50" cy="50" r="29" fill="none" stroke="'.$color.'" stroke-width="1.6"/>' .
        '<circle cx="50" cy="50" r="23.5" fill="none" stroke="'.$color.'" stroke-width="0.8" opacity="0.55"/>' .
        '<text x="50" y="53.5" text-anchor="middle" font-family="IBM Plex Mono, monospace" font-size="9.5" fill="'.$color.'" letter-spacing="0.5">'.htmlspecialchars($label).'</text>' .
    '</svg>';
}

function nvvh_entry_visual_html($item, $no) {
    $fallback = '<div class="entry-fallback">' . nvvh_entry_visual_svg($item, $no) . '</div>';
    if (empty($item['image'])) return $fallback;
    $src = htmlspecialchars($item['image'], ENT_QUOTES);
    return '<img src="'.$src.'" alt="" loading="lazy" referrerpolicy="no-referrer" ' .
        'onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'block\';">' .
        '<div class="entry-fallback" style="display:none">' . nvvh_entry_visual_svg($item, $no) . '</div>';
}

function nvvh_render_entry($item, $no, $categoryLabels) {
    $cat = $item['category'] ?? '';
    $label = $categoryLabels[$cat] ?? $cat;
    $title = htmlspecialchars($item['title'] ?? '', ENT_QUOTES);
    $url = htmlspecialchars($item['url'] ?? '', ENT_QUOTES);
    $summary = htmlspecialchars($item['summary'] ?? '', ENT_QUOTES);
    $source = htmlspecialchars($item['source'] ?? '', ENT_QUOTES);
    $dateLabel = htmlspecialchars($item['dateLabel'] ?? '', ENT_QUOTES);
    $visual = nvvh_entry_visual_html($item, $no);
    return '<article class="entry">' .
        '<div class="entry-visual">'.$visual.'</div>' .
        '<div class="entry-body">' .
          '<div class="entry-head">' .
            '<span class="entry-date">'.$dateLabel.'</span>' .
            '<span class="tag '.htmlspecialchars($cat, ENT_QUOTES).'">'.htmlspecialchars($label, ENT_QUOTES).'</span>' .
          '</div>' .
          '<h3 class="entry-title"><a href="'.$url.'" target="_blank" rel="noopener">'.$title.'</a></h3>' .
          '<div class="entry-summary">'.$summary.'</div>' .
          '<div class="entry-source">'.$source.'</div>' .
        '</div>' .
      '</article>';
}

function nvvh_render_reference($item) {
    $title = htmlspecialchars($item['title'] ?? '', ENT_QUOTES);
    $url = htmlspecialchars($item['url'] ?? '', ENT_QUOTES);
    $source = htmlspecialchars($item['source'] ?? '', ENT_QUOTES);
    return '<div class="ref-entry"><a href="'.$url.'" target="_blank" rel="noopener">'.$title.'</a><span class="ref-source">'.htmlspecialchars($source, ENT_QUOTES).'</span></div>';
}

function nvvh_render_video($item) {
    $title = htmlspecialchars($item['title'] ?? '', ENT_QUOTES);
    $url = htmlspecialchars($item['url'] ?? '', ENT_QUOTES);
    $source = htmlspecialchars($item['source'] ?? '', ENT_QUOTES);
    $dateLabel = htmlspecialchars($item['dateLabel'] ?? '', ENT_QUOTES);
    $image = htmlspecialchars($item['image'] ?? '', ENT_QUOTES);
    $thumb = $image ? '<img src="'.$image.'" alt="" loading="lazy" referrerpolicy="no-referrer">' : '';
    return '<article class="video-card">' .
        '<a class="video-thumb" href="'.$url.'" target="_blank" rel="noopener">' .
          $thumb .
          '<span class="video-play" aria-hidden="true"><svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="22" fill="rgba(33,28,19,0.55)"/><path d="M19 15l16 9-16 9z" fill="#f6f1e4"/></svg></span>' .
        '</a>' .
        '<h3 class="video-title"><a href="'.$url.'" target="_blank" rel="noopener">'.$title.'</a></h3>' .
        '<div class="video-meta">'.$source.' · '.$dateLabel.'</div>' .
      '</article>';
}

$timelineAsc = $timeline;
usort($timelineAsc, function($a, $b) { return strcmp($a['date'] ?? '', $b['date'] ?? ''); });
$numberById = [];
foreach ($timelineAsc as $idx => $it) { $numberById[$it['id']] = $idx + 1; }

$timelineHtml = '';
if (!$timeline) {
    $timelineHtml = '<div class="empty">Nincs bejegyzés ebben a kategóriában.</div>';
} else {
    foreach ($timeline as $item) {
        $timelineHtml .= nvvh_render_entry($item, $numberById[$item['id']] ?? 0, $categoryLabels);
    }
}

$referenceHtml = '';
foreach ($reference as $item) {
    $referenceHtml .= nvvh_render_reference($item);
}

$videosHtml = '';
if (!$videos) {
    $videosHtml = '<div class="empty">Még nincs videó.</div>';
} else {
    foreach ($videos as $item) {
        $videosHtml .= nvvh_render_video($item);
    }
}

$jsonLdItems = [];
$pos = 1;
foreach ($timeline as $item) {
    $jsonLdItems[] = [
        '@type' => 'ListItem',
        'position' => $pos++,
        'url' => $item['url'],
        'name' => $item['title'],
    ];
}
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $metaTitle,
    'description' => $metaDescription,
    'url' => $canonicalUrl,
    'inLanguage' => 'hu',
    'about' => [
        '@type' => 'GovernmentOrganization',
        'name' => 'Nemzeti Vagyonvisszaszerzési és Vagyonvédelmi Hivatal',
        'alternateName' => 'NVVH',
    ],
    'mainEntity' => [
        '@type' => 'ItemList',
        'itemListElement' => $jsonLdItems,
    ],
];
?>
<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= htmlspecialchars($metaTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Vagyonvédelmi Napló">
<meta property="og:locale" content="hu_HU">
<meta property="og:title" content="<?= htmlspecialchars($metaTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
<meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?= htmlspecialchars($metaTitle) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($metaDescription) ?>">
<script type="application/ld+json"><?= json_encode($jsonLd, $jsonOpts) ?></script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap');

  :root{
    --paper:#f6f1e4;
    --paper-raised:#ece3ce;
    --ink:#211c13;
    --ink-soft:#75694f;
    --rule:#d9cca8;
    --brass:#9c6b2b;
    --brass-strong:#6f4c1c;
    --law:#2f5d4f;
    --dispute:#9c3b2f;
    --analysis:#33556f;
    --shadow: 0 1px 0 rgba(33,28,19,0.06);
  }
  @media (prefers-color-scheme: dark){
    :root:not([data-theme="light"]){
      --paper:#161209;
      --paper-raised:#1f1a10;
      --ink:#ece2c9;
      --ink-soft:#a5987a;
      --rule:#3a3120;
      --brass:#d6a35a;
      --brass-strong:#eec284;
      --law:#7fb69d;
      --dispute:#dd8b7d;
      --analysis:#8db3d1;
      --shadow: 0 1px 0 rgba(0,0,0,0.4);
    }
  }
  :root[data-theme="dark"]{
    --paper:#161209;
    --paper-raised:#1f1a10;
    --ink:#ece2c9;
    --ink-soft:#a5987a;
    --rule:#3a3120;
    --brass:#d6a35a;
    --brass-strong:#eec284;
    --law:#7fb69d;
    --dispute:#dd8b7d;
    --analysis:#8db3d1;
    --shadow: 0 1px 0 rgba(0,0,0,0.4);
  }

  *{box-sizing:border-box;}
  html,body{margin:0;}
  body{
    background:var(--paper);
    color:var(--ink);
    font-family:"IBM Plex Sans",-apple-system,sans-serif;
    padding-inline:16px;
    padding-block:32px 64px;
    display:flex;
    justify-content:center;
  }
  .page{width:100%;max-width:940px;}

  /* masthead */
  .masthead{
    display:flex;
    flex-direction:column;
    gap:14px;
    padding-bottom:20px;
    border-bottom:2px solid var(--ink);
    margin-bottom:6px;
  }
  .masthead-title-row{
    display:flex;
    align-items:center;
    gap:16px;
  }
  .seal{flex:none;width:56px;height:56px;}
  .title-wrap{flex:1;min-width:0;container-type:inline-size;}
  .masthead-foot{
    display:flex;
    align-items:center;
    gap:16px;
    flex-wrap:wrap;
  }
  .masthead-text{flex:1;min-width:220px;}
  h1{
    font-family:"Fraunces",serif;
    font-weight:600;
    font-size:clamp(1.35rem,5.2vw,2.35rem);
    margin:0;
    letter-spacing:-0.01em;
  }
  h1 .dropcap{
    font-size:1.55em;
    font-weight:700;
  }
  @container (min-width:460px){
    h1{
      font-size:3.5cqw;
      white-space:nowrap;
    }
  }
  .subtitle{
    color:var(--ink-soft);
    font-size:0.92rem;
    line-height:1.5;
    max-width:52ch;
  }
  .status-row{
    display:flex;
    align-items:center;
    gap:8px;
    margin-top:16px;
    padding-top:12px;
    border-top:1px solid var(--rule);
    font-family:"IBM Plex Mono",monospace;
    font-size:0.78rem;
    color:var(--ink-soft);
    flex-wrap:wrap;
  }
  .dot{
    width:7px;height:7px;border-radius:50%;
    background:var(--law);
    box-shadow:0 0 0 3px color-mix(in srgb, var(--law) 22%, transparent);
    flex:none;
  }
  .status-row strong{color:var(--ink);font-weight:500;}

  .support-form{margin-left:auto;flex:none;}
  .support-btn{
    font-family:"IBM Plex Mono",monospace;
    font-size:0.78rem;
    letter-spacing:0.02em;
    padding:9px 16px;
    border-radius:14px;
    border:1px solid var(--law);
    background:var(--law);
    color:var(--paper);
    cursor:pointer;
    white-space:nowrap;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:2px;
    line-height:1.3;
  }
  .support-btn:hover{filter:brightness(1.1);}
  .support-btn-amount{font-size:0.68rem;opacity:0.75;}

  /* filters */
  .filters{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin:22px 0 18px;
  }
  .chip{
    font-family:"IBM Plex Mono",monospace;
    font-size:0.74rem;
    letter-spacing:0.02em;
    padding:6px 12px;
    border-radius:999px;
    border:1px solid var(--rule);
    background:transparent;
    color:var(--ink-soft);
    cursor:pointer;
  }
  .chip[aria-pressed="true"]{
    background:var(--ink);
    border-color:var(--ink);
    color:var(--paper);
  }
  @media (prefers-color-scheme: dark){
    :root:not([data-theme="light"]) .chip[aria-pressed="true"]{
      background:var(--brass-strong);
      border-color:var(--brass-strong);
      color:#1a1408;
    }
  }
  :root[data-theme="dark"] .chip[aria-pressed="true"]{
    background:var(--brass-strong);
    border-color:var(--brass-strong);
    color:#1a1408;
  }

  section.timeline{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:22px 20px;
  }
  @media (max-width:680px){
    section.timeline{grid-template-columns:1fr;}
  }
  .entry{
    display:flex;
    flex-direction:column;
    border:1px solid var(--rule);
    border-radius:3px;
    background:var(--paper-raised);
    box-shadow:var(--shadow);
    overflow:hidden;
  }
  .entry-visual{
    aspect-ratio:16/9;
    border-bottom:1px solid var(--rule);
  }
  .entry-visual svg{display:block;width:100%;height:100%;}
  .entry-visual img{display:block;width:100%;height:100%;object-fit:cover;}
  .entry-fallback{width:100%;height:100%;}
  .entry-body{
    padding:14px 16px 16px;
    display:flex;
    flex-direction:column;
    flex:1;
  }
  .entry-head{
    display:flex;
    align-items:baseline;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:4px;
  }
  .entry-date{
    font-family:"IBM Plex Mono",monospace;
    font-variant-numeric:tabular-nums;
    font-size:0.76rem;
    color:var(--ink-soft);
  }
  .tag{
    font-family:"IBM Plex Mono",monospace;
    font-size:0.68rem;
    letter-spacing:0.03em;
    text-transform:uppercase;
    padding:2px 7px;
    border-radius:3px;
    border:1px solid currentColor;
  }
  .tag.jogalkotas{color:var(--law);}
  .tag.vita{color:var(--dispute);}
  .tag.elemzes{color:var(--analysis);}
  .entry-title{
    font-family:"Fraunces",serif;
    font-weight:500;
    font-size:1.08rem;
    line-height:1.35;
    text-wrap:balance;
  }
  .entry-title a{color:var(--ink);text-decoration:none;border-bottom:1px solid var(--rule);}
  .entry-title a:hover{border-bottom-color:var(--brass);}
  .entry-summary{
    margin-top:6px;
    font-size:0.9rem;
    line-height:1.55;
    color:var(--ink-soft);
    flex:1;
  }
  .entry-source{
    margin-top:10px;
    font-size:0.76rem;
    color:var(--ink-soft);
    font-family:"IBM Plex Mono",monospace;
  }

  .empty{
    padding:32px 0;
    color:var(--ink-soft);
    font-size:0.9rem;
  }

  h2.section-heading{
    font-family:"IBM Plex Mono",monospace;
    font-size:0.78rem;
    letter-spacing:0.06em;
    text-transform:uppercase;
    color:var(--ink-soft);
    margin:40px 0 4px;
    padding-bottom:8px;
    border-bottom:1px solid var(--ink);
  }
  .reference-list{display:flex;flex-direction:column;}
  .ref-entry{
    padding-block:12px;
    border-top:1px solid var(--rule);
    display:flex;
    gap:12px;
    align-items:baseline;
    flex-wrap:wrap;
  }
  .ref-entry:last-child{border-bottom:1px solid var(--rule);}
  .ref-entry a{
    font-family:"Fraunces",serif;
    font-weight:500;
    font-size:0.98rem;
    color:var(--ink);
    text-decoration:none;
    border-bottom:1px solid var(--rule);
  }
  .ref-entry a:hover{border-bottom-color:var(--brass);}
  .ref-source{
    font-family:"IBM Plex Mono",monospace;
    font-size:0.72rem;
    color:var(--ink-soft);
  }

  .video-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(240px, 1fr));
    gap:20px;
  }
  .video-card{display:flex;flex-direction:column;}
  .video-thumb{
    position:relative;
    display:block;
    aspect-ratio:16/9;
    border-radius:10px;
    overflow:hidden;
    background:var(--paper-raised);
  }
  .video-thumb img{width:100%;height:100%;object-fit:cover;display:block;}
  .video-play{
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .video-play svg{width:44px;height:44px;}
  .video-title{
    margin:8px 0 0;
    font-family:"Fraunces",serif;
    font-weight:500;
    font-size:0.95rem;
    line-height:1.35;
    text-wrap:balance;
  }
  .video-title a{color:var(--ink);text-decoration:none;border-bottom:1px solid var(--rule);}
  .video-title a:hover{border-bottom-color:var(--brass);}
  .video-meta{
    margin-top:4px;
    font-size:0.74rem;
    color:var(--ink-soft);
    font-family:"IBM Plex Mono",monospace;
  }

  footer{
    margin-top:44px;
    padding-top:16px;
    border-top:1px solid var(--rule);
    font-size:0.76rem;
    color:var(--ink-soft);
    line-height:1.6;
  }
</style>
</head>
<body>

<div class="page">
  <div class="masthead">
    <div class="masthead-title-row">
      <svg class="seal" viewBox="0 0 56 56" aria-hidden="true">
        <circle cx="28" cy="28" r="26" fill="none" stroke="var(--brass)" stroke-width="1.4"/>
        <circle cx="28" cy="28" r="21" fill="none" stroke="var(--brass)" stroke-width="1"/>
        <text x="28" y="26" text-anchor="middle" font-family="IBM Plex Mono, monospace" font-size="8.5" fill="var(--brass)" letter-spacing="1">NVVH</text>
        <text x="28" y="37" text-anchor="middle" font-family="IBM Plex Mono, monospace" font-size="6" fill="var(--ink-soft)" letter-spacing="1.5">FIGYELŐ</text>
      </svg>
      <div class="title-wrap">
        <h1><span class="dropcap">N</span>emzeti <span class="dropcap">V</span>agyonvisszaszerzési és <span class="dropcap">V</span>agyonvédelmi <span class="dropcap">H</span>ivatal</h1>
      </div>
    </div>
    <div class="masthead-foot">
      <div class="masthead-text">
        <div class="subtitle">Az NVVH-ról szóló cikkek egy helyen, hogy ne neked kelljen vadászni rájuk és legyél képben!</div>
        <div class="status-row">
          <span class="dot" id="status-dot"></span>
          <span>Utolsó frissítés: <strong id="last-checked">—</strong></span>
          <span>·</span>
          <span id="article-count">— bejegyzés</span>
        </div>
      </div>
      <form class="support-form" action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
        <input type="hidden" name="cmd" value="_xclick">
        <input type="hidden" name="business" value="info@pshonlap.hu">
        <input type="hidden" name="currency_code" value="HUF">
        <input type="hidden" name="amount" value="3000">
        <input type="hidden" name="no_shipping" value="1">
        <input type="hidden" name="item_name" value="Támogatás – NVVH Figyelő">
        <button type="submit" class="support-btn">
          <span>Támogasd a munkánkat ha tudod</span>
          <span class="support-btn-amount">3000 Ft</span>
        </button>
      </form>
    </div>
  </div>

  <div class="filters" id="filters" role="group" aria-label="Szűrés kategória szerint">
    <button class="chip" data-filter="mind" aria-pressed="true">Mind</button>
    <button class="chip" data-filter="jogalkotas" aria-pressed="false">Jogalkotás</button>
    <button class="chip" data-filter="vita" aria-pressed="false">Vita és kritika</button>
    <button class="chip" data-filter="elemzes" aria-pressed="false">Elemzés</button>
  </div>

  <h2 class="section-heading">NVVH hírek időrendben</h2>
  <section class="timeline" id="timeline"><?= $timelineHtml ?></section>

  <h2 class="section-heading">Videók</h2>
  <div class="video-grid" id="video-grid"><?= $videosHtml ?></div>

  <h2 class="section-heading">Háttér és jogszabályok</h2>
  <div class="reference-list" id="reference-list"><?= $referenceHtml ?></div>

  <footer>
    A lista a nyilvános magyar hírportálokon és jogszabálytárakban elérhető cikkeket gyűjti össze a témában. Naponta egyszer automatikusan frissül; az egyes cikkek tartalmáért a forrásul szolgáló kiadványok felelősek. Az adatok forrása: ennek a webhelynek a saját adatbázisa.
  </footer>
</div>

<script>
(function(){
  var CATEGORY_LABEL = {jogalkotas:"Jogalkotás", vita:"Vita és kritika", elemzes:"Elemzés", alapinfo:"Alapinfo"};

  var SEED_TIMELINE = <?php echo json_encode($timeline, $jsonOpts); ?>;
  var SEED_REFERENCE = <?php echo json_encode($reference, $jsonOpts); ?>;
  var SEED_LAST_CHECKED = <?php echo json_encode($lastChecked, $jsonOpts); ?>;
  var state = {timeline: SEED_TIMELINE.slice(), reference: SEED_REFERENCE.slice(), filter:"mind", lastChecked:SEED_LAST_CHECKED};

  function hashCode(str){
    var h = 0;
    for(var i=0;i<str.length;i++){ h = (h*31 + str.charCodeAt(i)) >>> 0; }
    return h;
  }

  function categoryColorVar(cat){
    if(cat === "jogalkotas") return "var(--law)";
    if(cat === "vita") return "var(--dispute)";
    if(cat === "elemzes") return "var(--analysis)";
    return "var(--brass)";
  }

  function entryVisualSvg(item, no){
    var h = hashCode(item.id);
    var rayCount = 10 + (h % 5);
    var rotate = h % 360;
    var color = categoryColorVar(item.category);
    var rays = "";
    for(var i=0;i<rayCount;i++){
      var angle = (360/rayCount)*i;
      var rad = angle*Math.PI/180;
      var x1 = (50 + Math.cos(rad)*30).toFixed(1);
      var y1 = (50 + Math.sin(rad)*30).toFixed(1);
      var x2 = (50 + Math.cos(rad)*38).toFixed(1);
      var y2 = (50 + Math.sin(rad)*38).toFixed(1);
      rays += '<line x1="'+x1+'" y1="'+y1+'" x2="'+x2+'" y2="'+y2+'" stroke="'+color+'" stroke-width="1" stroke-linecap="round" opacity="0.65"/>';
    }
    var label = "№ " + String(no).padStart(2,"0");
    return '<svg viewBox="0 0 100 100" preserveAspectRatio="xMidYMid meet" role="img" aria-hidden="true">' +
      '<rect width="100" height="100" fill="var(--paper-raised)"/>' +
      '<g transform="rotate('+rotate+' 50 50)">'+rays+'</g>' +
      '<circle cx="50" cy="50" r="29" fill="none" stroke="'+color+'" stroke-width="1.6"/>' +
      '<circle cx="50" cy="50" r="23.5" fill="none" stroke="'+color+'" stroke-width="0.8" opacity="0.55"/>' +
      '<text x="50" y="53.5" text-anchor="middle" font-family="IBM Plex Mono, monospace" font-size="9.5" fill="'+color+'" letter-spacing="0.5">'+label+'</text>' +
    '</svg>';
  }

  function escapeAttr(s){
    return String(s).replace(/&/g,"&amp;").replace(/"/g,"&quot;");
  }

  function escapeHtml(s){
    return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");
  }

  function entryVisualHtml(item, no){
    var fallback = '<div class="entry-fallback">'+entryVisualSvg(item, no)+'</div>';
    if(!item.image) return fallback;
    return '<img src="'+escapeAttr(item.image)+'" alt="" loading="lazy" referrerpolicy="no-referrer" ' +
      'onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'block\';">' +
      '<div class="entry-fallback" style="display:none">'+entryVisualSvg(item, no)+'</div>';
  }

  function fmtRelative(iso){
    if(!iso) return "ismeretlen";
    var d = new Date(iso);
    if(isNaN(d)) return "ismeretlen";
    var diffMin = Math.round((Date.now()-d.getTime())/60000);
    if(diffMin < 1) return "épp most";
    if(diffMin < 60) return diffMin+" perce";
    var diffH = Math.round(diffMin/60);
    if(diffH < 24) return diffH+" órája";
    var diffD = Math.round(diffH/24);
    return diffD+" napja";
  }

  function renderStatus(){
    document.getElementById("last-checked").textContent = fmtRelative(state.lastChecked);
    document.getElementById("article-count").textContent = (state.timeline.length + state.reference.length) + " bejegyzés";
  }

  function renderTimeline(){
    var sorted = state.timeline.slice().sort(function(a,b){ return a.date < b.date ? 1 : -1; });
    var withNumbers = state.timeline.slice().sort(function(a,b){ return a.date < b.date ? -1 : 1; })
      .map(function(item, idx){ return Object.assign({}, item, {no: idx+1}); });
    var numberById = {};
    withNumbers.forEach(function(it){ numberById[it.id] = it.no; });

    var visible = state.filter === "mind" ? sorted : sorted.filter(function(it){ return it.category === state.filter; });

    var el = document.getElementById("timeline");
    if(visible.length === 0){
      el.innerHTML = '<div class="empty">Nincs bejegyzés ebben a kategóriában.</div>';
      return;
    }
    el.innerHTML = visible.map(function(item){
      var no = numberById[item.id];
      return '' +
        '<article class="entry">' +
          '<div class="entry-visual">'+entryVisualHtml(item, no)+'</div>' +
          '<div class="entry-body">' +
            '<div class="entry-head">' +
              '<span class="entry-date">'+escapeHtml(item.dateLabel)+'</span>' +
              '<span class="tag '+escapeAttr(item.category)+'">'+escapeHtml(CATEGORY_LABEL[item.category])+'</span>' +
            '</div>' +
            '<h3 class="entry-title"><a href="'+escapeAttr(item.url)+'" target="_blank" rel="noopener">'+escapeHtml(item.title)+'</a></h3>' +
            '<div class="entry-summary">'+escapeHtml(item.summary)+'</div>' +
            '<div class="entry-source">'+escapeHtml(item.source)+'</div>' +
          '</div>' +
        '</article>';
    }).join("");
  }

  function renderReference(){
    var el = document.getElementById("reference-list");
    el.innerHTML = state.reference.map(function(item){
      return '' +
        '<div class="ref-entry">' +
          '<a href="'+escapeAttr(item.url)+'" target="_blank" rel="noopener">'+escapeHtml(item.title)+'</a>' +
          '<span class="ref-source">'+escapeHtml(item.source)+'</span>' +
        '</div>';
    }).join("");
  }

  function renderAll(){ renderStatus(); renderTimeline(); renderReference(); }
  renderAll();
  setInterval(renderStatus, 60000);

  document.getElementById("filters").addEventListener("click", function(e){
    var btn = e.target.closest(".chip");
    if(!btn) return;
    state.filter = btn.dataset.filter;
    Array.prototype.forEach.call(document.querySelectorAll(".chip"), function(c){
      c.setAttribute("aria-pressed", String(c === btn));
    });
    renderTimeline();
  });
})();
</script>
</body>
</html>
