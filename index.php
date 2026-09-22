<?php
require __DIR__ . '/db.php';
$db = get_db();

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

$lastChecked = null;
$articleCount = count($timeline) + count($reference);
$res = $db->query("SELECT lastChecked, articleCount FROM meta_status WHERE id = 1");
if ($row = $res->fetch_assoc()) {
    $lastChecked = $row['lastChecked'];
}

$jsonOpts = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
?>
<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>NVVH témájú cikkek egy oldalon</title>
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
    align-items:flex-start;
    gap:16px;
    padding-bottom:20px;
    border-bottom:2px solid var(--ink);
    margin-bottom:6px;
    flex-wrap:wrap;
  }
  .seal{flex:none;width:56px;height:56px;}
  .masthead-text{flex:1;min-width:220px;}
  h1{
    font-family:"Fraunces",serif;
    font-weight:600;
    font-size:clamp(1.7rem,5vw,2.35rem);
    margin:0 0 4px;
    letter-spacing:-0.01em;
    text-wrap:balance;
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
    <svg class="seal" viewBox="0 0 56 56" aria-hidden="true">
      <circle cx="28" cy="28" r="26" fill="none" stroke="var(--brass)" stroke-width="1.4"/>
      <circle cx="28" cy="28" r="21" fill="none" stroke="var(--brass)" stroke-width="1"/>
      <text x="28" y="26" text-anchor="middle" font-family="IBM Plex Mono, monospace" font-size="8.5" fill="var(--brass)" letter-spacing="1">NVVH</text>
      <text x="28" y="37" text-anchor="middle" font-family="IBM Plex Mono, monospace" font-size="6" fill="var(--ink-soft)" letter-spacing="1.5">FIGYELŐ</text>
    </svg>
    <div class="masthead-text">
      <h1>NVVH témájú cikkek egy oldalon</h1>
      <div class="subtitle">Kövesd a SUSKA útját</div>
      <div class="status-row">
        <span class="dot" id="status-dot"></span>
        <span>Utolsó ellenőrzés: <strong id="last-checked">—</strong></span>
        <span>·</span>
        <span id="article-count">— bejegyzés</span>
      </div>
    </div>
  </div>

  <div class="filters" id="filters" role="group" aria-label="Szűrés kategória szerint">
    <button class="chip" data-filter="mind" aria-pressed="true">Mind</button>
    <button class="chip" data-filter="jogalkotas" aria-pressed="false">Jogalkotás</button>
    <button class="chip" data-filter="vita" aria-pressed="false">Vita és kritika</button>
    <button class="chip" data-filter="elemzes" aria-pressed="false">Elemzés</button>
  </div>

  <section class="timeline" id="timeline"></section>

  <h2 class="section-heading">Háttér és jogszabályok</h2>
  <div class="reference-list" id="reference-list"></div>

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
              '<span class="entry-date">'+item.dateLabel+'</span>' +
              '<span class="tag '+item.category+'">'+CATEGORY_LABEL[item.category]+'</span>' +
            '</div>' +
            '<div class="entry-title"><a href="'+item.url+'" target="_blank" rel="noopener">'+item.title+'</a></div>' +
            '<div class="entry-summary">'+item.summary+'</div>' +
            '<div class="entry-source">'+item.source+'</div>' +
          '</div>' +
        '</article>';
    }).join("");
  }

  function renderReference(){
    var el = document.getElementById("reference-list");
    el.innerHTML = state.reference.map(function(item){
      return '' +
        '<div class="ref-entry">' +
          '<a href="'+item.url+'" target="_blank" rel="noopener">'+item.title+'</a>' +
          '<span class="ref-source">'+item.source+'</span>' +
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
