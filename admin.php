<?php
// Forgalom-statisztika admin felület
require __DIR__ . '/db.php';
session_start();

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (isset($_POST['password'])) {
    if (defined('ADMIN_PASSWORD_HASH') && password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_ok'] = true;
        header('Location: admin.php');
        exit;
    }
    $loginError = 'Hibás jelszó.';
}

$loggedIn = !empty($_SESSION['admin_ok']);
$db = $loggedIn ? get_db() : null;

if ($loggedIn) {
    $totalViews = (int)($db->query("SELECT COUNT(*) c FROM page_views")->fetch_assoc()['c']);
    $totalUnique = (int)($db->query("SELECT COUNT(DISTINCT ip) c FROM page_views")->fetch_assoc()['c']);
    $todayViews = (int)($db->query("SELECT COUNT(*) c FROM page_views WHERE DATE(viewedAt) = CURDATE()")->fetch_assoc()['c']);
    $todayUnique = (int)($db->query("SELECT COUNT(DISTINCT ip) c FROM page_views WHERE DATE(viewedAt) = CURDATE()")->fetch_assoc()['c']);

    $daily = [];
    $res = $db->query(
        "SELECT DATE(viewedAt) d, COUNT(*) views, COUNT(DISTINCT ip) uniques
         FROM page_views
         WHERE viewedAt >= (CURDATE() - INTERVAL 29 DAY)
         GROUP BY DATE(viewedAt)
         ORDER BY d DESC"
    );
    while ($row = $res->fetch_assoc()) $daily[] = $row;
    $maxViews = 1;
    foreach ($daily as $row) $maxViews = max($maxViews, (int)$row['views']);

    $recent = [];
    $res = $db->query(
        "SELECT viewedAt, ip, userAgent FROM page_views ORDER BY viewedAt DESC LIMIT 100"
    );
    while ($row = $res->fetch_assoc()) {
        $dt = new DateTime($row['viewedAt'], new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Budapest'));
        $row['viewedAt'] = $dt->format('Y-m-d H:i:s');
        $recent[] = $row;
    }
}
?>
<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Admin – Vagyonvédelmi Napló</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap');

  :root{
    --paper:#f6f1e4;
    --paper-raised:#ece3ce;
    --ink:#211c13;
    --ink-soft:#75694f;
    --rule:#d9cca8;
    --brass:#9c6b2b;
    --brass-strong:#6f4c1c;
  }
  @media (prefers-color-scheme: dark){
    :root{
      --paper:#161209;
      --paper-raised:#1f1a10;
      --ink:#ece2c9;
      --ink-soft:#a5987a;
      --rule:#3a3120;
      --brass:#d6a35a;
      --brass-strong:#eec284;
    }
  }
  *{box-sizing:border-box;}
  html,body{margin:0;}
  body{
    background:var(--paper);
    color:var(--ink);
    font-family:"IBM Plex Sans",-apple-system,sans-serif;
    padding:32px 16px 64px;
    display:flex;
    justify-content:center;
  }
  .page{width:100%;max-width:760px;}
  h1{
    font-family:"Fraunces",serif;
    font-weight:600;
    font-size:1.7rem;
    margin:0 0 24px;
  }
  .login-form{
    display:flex;
    flex-direction:column;
    gap:12px;
    max-width:280px;
  }
  .login-form input{
    font-family:"IBM Plex Mono",monospace;
    font-size:0.9rem;
    padding:10px 12px;
    border-radius:8px;
    border:1px solid var(--rule);
    background:var(--paper-raised);
    color:var(--ink);
  }
  .login-form button{
    font-family:"IBM Plex Mono",monospace;
    font-size:0.85rem;
    padding:10px 16px;
    border-radius:999px;
    border:1px solid var(--brass);
    background:transparent;
    color:var(--brass-strong);
    cursor:pointer;
  }
  .error{color:#9c3b2f;font-size:0.85rem;margin:-4px 0 4px;}
  .stat-row{
    display:flex;
    gap:16px;
    flex-wrap:wrap;
    margin-bottom:28px;
  }
  .stat{
    flex:1;
    min-width:140px;
    background:var(--paper-raised);
    border-radius:12px;
    padding:16px 18px;
  }
  .stat .label{font-size:0.78rem;color:var(--ink-soft);margin-bottom:6px;}
  .stat .value{font-family:"IBM Plex Mono",monospace;font-size:1.6rem;font-weight:500;}
  table{width:100%;border-collapse:collapse;font-size:0.85rem;}
  th,td{text-align:left;padding:6px 8px;border-bottom:1px solid var(--rule);}
  th{color:var(--ink-soft);font-weight:500;font-family:"IBM Plex Mono",monospace;font-size:0.75rem;}
  td.num{font-family:"IBM Plex Mono",monospace;text-align:right;}
  .bar-cell{width:40%;}
  .bar-track{background:var(--rule);border-radius:4px;height:8px;overflow:hidden;}
  .bar-fill{background:var(--brass);height:100%;}
  .top-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;}
  .logout{font-size:0.8rem;color:var(--ink-soft);text-decoration:none;}
  .logout:hover{color:var(--ink);}
  h2{
    font-family:"Fraunces",serif;
    font-weight:600;
    font-size:1.15rem;
    margin:36px 0 12px;
  }
  td.ip{font-family:"IBM Plex Mono",monospace;}
  td.ua{color:var(--ink-soft);font-size:0.78rem;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
</style>
</head>
<body>
<div class="page">
<?php if (!$loggedIn): ?>
  <h1>Admin belépés</h1>
  <form class="login-form" method="post">
    <?php if (!empty($loginError)): ?><div class="error"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
    <input type="password" name="password" placeholder="Jelszó" autofocus>
    <button type="submit">Belépés</button>
  </form>
<?php else: ?>
  <div class="top-row">
    <h1 style="margin:0;">Forgalom</h1>
    <a class="logout" href="admin.php?logout=1">Kilépés</a>
  </div>
  <div class="stat-row">
    <div class="stat"><div class="label">Ma megtekintve</div><div class="value"><?= $todayViews ?></div></div>
    <div class="stat"><div class="label">Ma egyedi látogató</div><div class="value"><?= $todayUnique ?></div></div>
    <div class="stat"><div class="label">Összes megtekintés</div><div class="value"><?= $totalViews ?></div></div>
    <div class="stat"><div class="label">Összes egyedi látogató</div><div class="value"><?= $totalUnique ?></div></div>
  </div>
  <table>
    <thead>
      <tr><th>Dátum</th><th class="bar-cell">Megtekintés</th><th class="num">Megtekintés</th><th class="num">Egyedi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($daily as $row): $pct = round(($row['views'] / $maxViews) * 100); ?>
      <tr>
        <td><?= htmlspecialchars($row['d']) ?></td>
        <td class="bar-cell"><div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div></td>
        <td class="num"><?= (int)$row['views'] ?></td>
        <td class="num"><?= (int)$row['uniques'] ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$daily): ?>
      <tr><td colspan="4" style="color:var(--ink-soft);">Még nincs adat.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <h2>Legutóbbi látogatók</h2>
  <table>
    <thead>
      <tr><th>Időpont</th><th>IP cím</th><th>Böngésző</th></tr>
    </thead>
    <tbody>
      <?php foreach ($recent as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row['viewedAt']) ?></td>
        <td class="ip"><?= htmlspecialchars($row['ip'] ?? '—') ?></td>
        <td class="ua"><?= htmlspecialchars($row['userAgent'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?>
      <tr><td colspan="3" style="color:var(--ink-soft);">Még nincs adat.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
</body>
</html>
