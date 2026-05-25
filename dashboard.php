<?php
session_start();
require_once __DIR__ . '/db.php';

if (isset($_GET['logout'])) { session_destroy(); header('Location: dashboard.php'); exit; }

if (!isset($_SESSION['ss_admin'])) {
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['username'])) {
        $s = $pdo->prepare("SELECT password FROM admin_users WHERE username=?");
        $s->execute([trim($_POST['username'])]);
        $r = $s->fetch();
        if ($r && password_verify(trim($_POST['password']),$r['password'])) {
            $_SESSION['ss_admin']=trim($_POST['username']); header('Location: dashboard.php'); exit;
        }
        $err='Hatalı kullanıcı adı veya şifre.';
    }
    ?><!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>BeyTor Stats</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Fraunces:ital,wght@0,700&display=swap" rel="stylesheet"/>
    <style>*{box-sizing:border-box;margin:0;padding:0}:root{--bg:#07070f;--s:#0f0f1c;--b:rgba(255,255,255,.08);--a:#6ee7b7;--t:#e2e8f0;--m:#64748b;--d:#f87171}body{background:var(--bg);color:var(--t);font-family:'DM Mono',monospace;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}.card{background:var(--s);border:1px solid var(--b);border-radius:20px;padding:48px;width:100%;max-width:400px}.logo{font-family:'Fraunces',serif;font-size:26px;color:var(--a);margin-bottom:4px}.sub{font-size:10px;color:var(--m);letter-spacing:.14em;text-transform:uppercase;margin-bottom:36px}label{display:block;font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:var(--m);margin-bottom:7px}input{width:100%;background:var(--bg);border:1px solid var(--b);border-radius:9px;color:var(--t);font-family:'DM Mono',monospace;font-size:14px;padding:11px 14px;margin-bottom:18px;outline:none}input:focus{border-color:rgba(110,231,183,.35)}button{width:100%;background:var(--a);color:#07070f;border:none;border-radius:9px;font-family:'DM Mono',monospace;font-size:13px;font-weight:500;padding:13px;cursor:pointer}.err{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.2);color:var(--d);border-radius:9px;padding:12px 14px;font-size:12px;margin-bottom:20px}</style>
    </head><body><div class="card"><div class="logo">BeyTor Stats</div><div class="sub">Admin Girişi</div>
    <?php if(!empty($err)):?><div class="err"><?=htmlspecialchars($err)?></div><?php endif?>
    <form method="POST"><label>Kullanıcı Adı</label><input type="text" name="username" required/><label>Şifre</label><input type="password" name="password" required/><button type="submit">Giriş Yap →</button></form>
    </div></body></html><?php exit;
}

$page = $_GET['page'] ?? 'overview';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>BeyTor Stats — Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Fraunces:ital,wght@0,700;1,400&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#07070f;--s:#0e0e1b;--s2:#121220;--b:rgba(255,255,255,.07);--b2:rgba(255,255,255,.04);--g1:#6ee7b7;--g2:#818cf8;--g3:#fb923c;--live:#34d399;--t:#e2e8f0;--m:#64748b;--danger:#f87171;--r:14px}
html{scroll-behavior:smooth}body{background:var(--bg);color:var(--t);font-family:'DM Mono',monospace;min-height:100vh}
.layout{display:grid;grid-template-columns:200px 1fr;min-height:100vh}
aside{background:var(--s);border-right:1px solid var(--b);padding:24px 14px;display:flex;flex-direction:column;gap:4px;position:sticky;top:0;height:100vh;overflow-y:auto}
.logo{font-family:'Fraunces',serif;font-size:18px;color:var(--g1);margin-bottom:2px;padding:0 6px}
.logo-sub{font-size:7px;letter-spacing:.14em;text-transform:uppercase;color:var(--m);padding:0 6px;margin-bottom:18px}
.nav-item{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:8px;font-size:11px;color:var(--m);text-decoration:none;transition:all .2s;border:none;background:none;width:100%;text-align:left;font-family:'DM Mono',monospace;cursor:pointer}
.nav-item:hover,.nav-item.active{background:var(--b2);color:var(--t)}
.nav-sep{height:1px;background:var(--b);margin:8px 0}
.nav-bottom{margin-top:auto}
main{padding:28px 32px;overflow-x:hidden}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;gap:12px;flex-wrap:wrap}
.page-title{font-family:'Fraunces',serif;font-size:24px;letter-spacing:-.02em}
.page-sub{font-size:9px;color:var(--m);margin-top:2px;letter-spacing:.1em;text-transform:uppercase}
.header-right{display:flex;gap:8px;align-items:center}
.refresh-btn{background:rgba(110,231,183,.1);border:1px solid rgba(110,231,183,.2);color:var(--g1);font-family:'DM Mono',monospace;font-size:10px;letter-spacing:.08em;padding:7px 14px;border-radius:8px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:6px}
.refresh-btn:hover{background:rgba(110,231,183,.18)}
.refresh-btn.spinning .ico{animation:spin .6s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.logout-btn{font-family:'DM Mono',monospace;font-size:10px;color:var(--m);background:none;border:1px solid var(--b);border-radius:8px;padding:7px 14px;cursor:pointer;text-decoration:none;transition:all .2s}
.logout-btn:hover{color:var(--danger);border-color:rgba(248,113,113,.3)}
/* LIVE BANNER */
.live-banner{display:flex;align-items:center;gap:12px;background:rgba(52,211,153,.06);border:1px solid rgba(52,211,153,.15);border-radius:12px;padding:12px 18px;margin-bottom:18px}
.live-dot{width:8px;height:8px;border-radius:50%;background:var(--live);position:relative;flex-shrink:0}
.live-dot::after{content:'';position:absolute;inset:-3px;border-radius:50%;border:1.5px solid var(--live);animation:ring 1.8s ease-out infinite;opacity:0}
@keyframes ring{0%{transform:scale(1);opacity:.7}100%{transform:scale(2.4);opacity:0}}
.live-num{font-size:22px;font-weight:700;color:var(--live);letter-spacing:-.03em}
.live-lbl{font-size:8px;letter-spacing:.14em;text-transform:uppercase;color:var(--m)}
.live-desc{font-size:10px;color:var(--t);opacity:.6;margin-top:1px}
/* CARDS */
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px}
.card{background:var(--s);border:1px solid var(--b);border-radius:var(--r);padding:16px;position:relative;overflow:hidden;cursor:pointer;transition:all .2s}
.card:hover{border-color:rgba(255,255,255,.15);transform:translateY(-1px)}
.card::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 50% 0%,var(--glow,transparent) 0%,transparent 70%);pointer-events:none}
.card.day{--glow:rgba(110,231,183,.06)}.card.month{--glow:rgba(129,140,248,.06)}.card.year{--glow:rgba(251,146,60,.06)}.card.all{--glow:rgba(148,163,184,.05)}
.card-lbl{font-size:8px;letter-spacing:.16em;text-transform:uppercase;color:var(--m);margin-bottom:6px}
.card-val{font-size:26px;font-weight:700;letter-spacing:-.04em;line-height:1}
.card-hint{font-size:8px;color:var(--m);margin-top:4px;opacity:.7}
.card.day .card-val{color:var(--g1)}.card.month .card-val{color:var(--g2)}.card.year .card-val{color:var(--g3)}.card.all .card-val{color:var(--t)}
.card-icon{position:absolute;right:14px;top:14px;font-size:18px;opacity:.2}
/* GRID LAYOUTS */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.three-col{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:14px}
/* SECTION */
.section{background:var(--s);border:1px solid var(--b);border-radius:var(--r);padding:18px;margin-bottom:14px}
.section-title{font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:var(--m);margin-bottom:14px;display:flex;align-items:center;justify-content:space-between}
/* BAR CHART */
.bar-chart{display:flex;align-items:flex-end;gap:10px;height:120px}
.bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;justify-content:flex-end}
.bar{width:100%;border-radius:4px 4px 0 0;background:linear-gradient(180deg,var(--g2) 0%,rgba(129,140,248,.4) 100%);min-height:3px;transition:height .5s cubic-bezier(.4,0,.2,1)}
.bar-day{font-size:9px;color:var(--m)}.bar-cnt{font-size:9px;color:var(--t);min-height:11px}
/* DETAIL CHART */
.detail-chart-wrap{
  height:260px;
  display:flex;
  align-items:flex-end;
  gap:10px;
  overflow-x:auto;
  overflow-y:hidden;
  padding:12px 6px 18px;
  scrollbar-width:thin;
}
.d-bar-col{
  flex:0 0 48px;
  height:100%;
  display:grid;
  grid-template-rows:18px 1fr 22px;
  align-items:end;
  justify-items:center;
  gap:7px;
}
.d-bar-val{
  font-size:10px;
  line-height:1;
  color:var(--t);
  opacity:.85;
  min-height:12px;
}
.d-bar-track{
  width:100%;
  height:100%;
  display:flex;
  align-items:flex-end;
}
.d-bar{
  width:100%;
  border-radius:7px 7px 2px 2px;
  background:linear-gradient(180deg,var(--g1) 0%,rgba(110,231,183,.36) 100%);
  min-height:4px;
  transition:height .4s ease;
}
.d-lbl{
  font-size:9px;
  line-height:1.15;
  color:var(--m);
  white-space:normal;
  text-align:center;
  min-height:18px;
  transform:none;
  margin-top:0;
}
/* HOUR HEATMAP */
.hour-grid{display:grid;grid-template-columns:repeat(24,1fr);gap:3px;margin-top:8px}
.hour-cell{height:28px;border-radius:3px;background:rgba(129,140,248,.1);position:relative;cursor:default}
.hour-cell:hover::after{content:attr(data-tip);position:absolute;bottom:110%;left:50%;transform:translateX(-50%);background:#1e1e2e;border:1px solid var(--b);color:var(--t);font-size:9px;padding:3px 7px;border-radius:5px;white-space:nowrap;z-index:10}
/* PIE */
.pie-wrap{display:flex;align-items:center;gap:20px}
.pie-legend{flex:1;display:flex;flex-direction:column;gap:6px}
.pie-legend-item{display:flex;align-items:center;gap:8px;font-size:10px;color:var(--m)}
.pie-legend-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.pie-legend-label{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pie-legend-pct{color:var(--t);font-weight:500}
/* TABLE */
table{width:100%;border-collapse:collapse;font-size:10px}
th{text-align:left;font-size:8px;letter-spacing:.14em;text-transform:uppercase;color:var(--m);padding:0 0 8px;border-bottom:1px solid var(--b)}
td{padding:7px 0;border-bottom:1px solid var(--b2);color:var(--t);vertical-align:middle}
tr:last-child td{border-bottom:none}
.td-page{color:var(--g2);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.td-page a{color:inherit;text-decoration:none}
.td-page a:hover{text-decoration:underline}
.td-cnt{color:var(--g1);font-size:12px;font-weight:700;text-align:right}
.td-ref{color:var(--g3);font-size:9px}
.td-time{color:var(--m);font-size:9px;text-align:right;white-space:nowrap}
.td-bar-wrap{width:80px}
.td-bar-inner{height:4px;border-radius:2px;background:var(--g2);transition:width .4s ease}
/* STAT ROW */
.stat-row{display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--b2);font-size:11px}
.stat-row:last-child{border-bottom:none}
.stat-row-label{color:var(--m)}
.stat-row-val{color:var(--t);font-weight:500}
/* BADGE */
.badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:8px;letter-spacing:.1em;text-transform:uppercase}
.badge-mobile{background:rgba(251,146,60,.1);color:var(--g3);border:1px solid rgba(251,146,60,.2)}
.badge-desktop{background:rgba(129,140,248,.1);color:var(--g2);border:1px solid rgba(129,140,248,.2)}
.badge-tablet{background:rgba(110,231,183,.1);color:var(--g1);border:1px solid rgba(110,231,183,.2)}
/* MODAL */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;pointer-events:none;transition:opacity .2s}
.modal-overlay.open{opacity:1;pointer-events:all}
.modal{background:var(--s);border:1px solid var(--b);border-radius:16px;padding:24px;width:100%;max-width:min(1120px,96vw);max-height:88vh;overflow-y:auto}
.modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.modal-title{font-family:'Fraunces',serif;font-size:18px}
.modal-close{background:none;border:1px solid var(--b);color:var(--m);border-radius:8px;padding:6px 12px;cursor:pointer;font-family:'DM Mono',monospace;font-size:11px}
/* ABOUT */
.about-card{background:var(--s2);border:1px solid var(--b);border-radius:12px;padding:18px 20px;margin-bottom:12px}
.about-card h3{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--g1);margin-bottom:8px}
.about-card p,.about-card li{font-size:11px;color:var(--m);line-height:1.8}
.about-card a{color:var(--g2);text-decoration:none}
.about-card ul{padding-left:16px}
.version-badge{display:inline-block;background:rgba(110,231,183,.1);border:1px solid rgba(110,231,183,.2);color:var(--g1);font-size:9px;padding:2px 10px;border-radius:20px;letter-spacing:.1em;margin-bottom:10px}
/* LOADING */
.loading{opacity:.4;animation:pulse 1s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:.4}50%{opacity:.8}}
/* MOBILE */
@media(max-width:900px){.layout{grid-template-columns:1fr}aside{display:none}main{padding:18px 14px}.cards{grid-template-columns:repeat(2,1fr)}.two-col,.three-col{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="layout">
<aside>
  <div class="logo">BeyTor Stats</div>
  <div class="logo-sub">Analytics</div>
  <a class="nav-item <?=$page==='overview'?'active':''?>" href="?page=overview">📊 Genel Bakış</a>
  <a class="nav-item <?=$page==='pages'?'active':''?>" href="?page=pages">📄 Sayfalar</a>
  <a class="nav-item <?=$page==='inject'?'active':''?>" href="?page=inject">🧩 Enjektör</a>
  <a class="nav-item <?=$page==='about'?'active':''?>" href="?page=about">ℹ️ Hakkında</a>
  <div class="nav-sep"></div>
  <div class="nav-bottom">
    <a class="nav-item" href="?logout=1">🚪 Çıkış Yap</a>
  </div>
</aside>

<main>
<?php if($page==='overview'): ?>
<!-- ══════ GENEL BAKIŞ ══════ -->
<div class="page-header">
  <div><div class="page-title">Genel Bakış</div><div class="page-sub" id="last-update">Yükleniyor...</div></div>
  <div class="header-right">
    <button class="refresh-btn" onclick="loadDashboard(true)"><span class="ico">↻</span> Yenile</button>
    <a class="logout-btn" href="?logout=1">Çıkış</a>
  </div>
</div>

<div class="live-banner">
  <div class="live-dot"></div>
  <div class="live-num loading" id="live-num">—</div>
  <div><div class="live-lbl">Şu An Çevrimiçi</div><div class="live-desc">Son 60 saniye içinde aktif</div></div>
  <div style="margin-left:auto;display:flex;gap:16px;font-size:10px">
    <div><div style="color:var(--m);font-size:8px;letter-spacing:.1em;text-transform:uppercase">Yeni</div><div style="color:var(--g1);font-weight:700" id="new-visitors">—</div></div>
    <div><div style="color:var(--m);font-size:8px;letter-spacing:.1em;text-transform:uppercase">Geri Dönen</div><div style="color:var(--g2);font-weight:700" id="returning">—</div></div>
  </div>
</div>

<div class="cards">
  <div class="card day" onclick="openDetail('today')"><div class="card-icon">☀️</div><div class="card-lbl">Bugün</div><div class="card-val loading" id="c-day">—</div><div class="card-hint">Tıkla → 24 saatlik detay</div></div>
  <div class="card month" onclick="openDetail('month')"><div class="card-icon">📅</div><div class="card-lbl">Bu Ay</div><div class="card-val loading" id="c-month">—</div><div class="card-hint">Tıkla → Günlük detay</div></div>
  <div class="card year" onclick="openDetail('year')"><div class="card-icon">📆</div><div class="card-lbl">Bu Yıl</div><div class="card-val loading" id="c-year">—</div><div class="card-hint">Tıkla → Aylık detay</div></div>
  <div class="card all" onclick="openDetail('all')"><div class="card-icon">♾️</div><div class="card-lbl">Tüm Zamanlar</div><div class="card-val loading" id="c-all">—</div><div class="card-hint">Tıkla → Yıllık detay</div></div>
</div>

<div class="two-col">
  <div class="section">
    <div class="section-title">Son 7 Gün — Benzersiz Ziyaretçi</div>
    <div class="bar-chart" id="chart7">
      <?php for($i=0;$i<7;$i++): ?>
      <div class="bar-col"><div class="bar-cnt"></div><div class="bar loading" style="height:30%"></div><div class="bar-day">—</div></div>
      <?php endfor; ?>
    </div>
  </div>
  <div class="section">
    <div class="section-title">Sayfa Dağılımı (Pasta)</div>
    <div class="pie-wrap">
      <canvas id="pie-canvas" width="130" height="130"></canvas>
      <div class="pie-legend" id="pie-legend"><div style="color:var(--m);font-size:10px">Yükleniyor...</div></div>
    </div>
  </div>
</div>

<div class="two-col">
  <div class="section">
    <div class="section-title">En Aktif Saatler</div>
    <div class="hour-grid" id="hour-grid">
      <?php for($h=0;$h<24;$h++): ?>
      <div class="hour-cell loading" data-tip="<?=$h?>:00 — yükleniyor"></div>
      <?php endfor; ?>
    </div>
    <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:8px;color:var(--m)">
      <span>00:00</span><span>06:00</span><span>12:00</span><span>18:00</span><span>23:00</span>
    </div>
  </div>
  <div class="section">
    <div class="section-title">Cihaz & Platform</div>
    <div id="device-stats"><div style="color:var(--m);font-size:10px">Yükleniyor...</div></div>
  </div>
</div>

<div class="section">
  <div class="section-title">Son Ziyaretler</div>
  <table>
    <tr><th>Sayfa</th><th>Kaynak</th><th style="text-align:right">Zaman</th></tr>
    <tbody id="recent-tbody"><tr><td colspan="3" style="color:var(--m);padding:12px 0">Yükleniyor...</td></tr></tbody>
  </table>
</div>

<?php elseif($page==='pages'): ?>
<!-- ══════ SAYFALAR ══════ -->
<div class="page-header">
  <div><div class="page-title">Sayfalar</div><div class="page-sub" id="last-update-p">Yükleniyor...</div></div>
  <div class="header-right">
    <button class="refresh-btn" onclick="loadPages(true)"><span class="ico">↻</span> Yenile</button>
    <a class="logout-btn" href="?logout=1">Çıkış</a>
  </div>
</div>

<div class="two-col">
  <div class="section">
    <div class="section-title">En Çok Ziyaret Edilen Sayfalar</div>
    <table>
      <tr><th>Sayfa</th><th>Trend</th><th style="text-align:right">Görüntüleme</th></tr>
      <tbody id="pages-tbody"><tr><td colspan="3" style="color:var(--m)">Yükleniyor...</td></tr></tbody>
    </table>
  </div>
  <div class="section">
    <div class="section-title">Trafik Kaynakları</div>
    <div id="refs-wrap"><div style="color:var(--m);font-size:10px">Yükleniyor...</div></div>
  </div>
</div>

<div class="three-col">
  <div class="section">
    <div class="section-title">Tarayıcılar</div>
    <div id="browser-wrap"><div style="color:var(--m);font-size:10px">Yükleniyor...</div></div>
  </div>
  <div class="section">
    <div class="section-title">İşletim Sistemleri</div>
    <div id="os-wrap"><div style="color:var(--m);font-size:10px">Yükleniyor...</div></div>
  </div>
  <div class="section">
    <div class="section-title">Cihaz Türleri</div>
    <div id="device-wrap"><div style="color:var(--m);font-size:10px">Yükleniyor...</div></div>
  </div>
</div>

<?php elseif($page==='inject'): ?>

<div class="page-header">
  <div><div class="page-title">Script Enjektörü</div><div class="page-sub">HTML sayfalarına analytics.js ekle / kaldır</div></div>
  <div class="header-right">
    <a class="logout-btn" href="../inject.php" target="_blank">Yeni Sekmede Aç</a>
    <a class="logout-btn" href="?logout=1">Çıkış</a>
  </div>
</div>

<div class="section" style="padding:0;overflow:hidden;height:calc(100vh - 120px)">
  <iframe src="../inject.php" style="width:100%;height:100%;border:0;background:var(--bg)"></iframe>
</div>

<?php elseif($page==='about'): ?>
<!-- ══════ HAKKINDA ══════ -->
<div class="page-header">
  <div><div class="page-title">Hakkında</div><div class="page-sub">BeyTor Stats v2.0</div></div>
  <a class="logout-btn" href="?logout=1">Çıkış</a>
</div>
<div class="about-card"><div class="version-badge">v2.0.0</div><h3>BeyTor Stats Nedir?</h3><p>Web sitenizin ziyaretçi istatistiklerini gerçek zamanlı takip eden, tamamen bağımsız PHP + MySQL tabanlı analitik sistemi. <a href="https://www.beytor.com" target="_blank">BeyTor</a> tarafından geliştirilmiştir.</p></div>
<div class="about-card"><h3>Özellikler</h3><ul><li>Günlük, aylık, yıllık benzersiz ziyaretçi sayımı</li><li>Anlık aktif kullanıcı takibi</li><li>Sayfa bazlı detaylı istatistikler</li><li>Cihaz, tarayıcı, OS takibi</li><li>Trafik kaynağı analizi</li><li>Çok dilli site desteği</li></ul></div>
<div class="about-card"><h3>KVKK</h3><p>IP adresleri SHA-256 ile hash'lenerek saklanır. Ham IP kaydedilmez. Veriler üçüncü taraflarla paylaşılmaz.</p></div>
<div class="about-card"><h3>Sorumluluk Reddi</h3><p>Yazılım "olduğu gibi" sunulmaktadır. Kullanımdan doğabilecek zararlardan BeyTor sorumlu tutulamaz. Sistem güvenliği kullanıcının sorumluluğundadır.</p></div>
<div class="about-card"><h3>Geliştirici</h3><p>🌐 <a href="https://www.beytor.com" target="_blank">www.beytor.com</a></p></div>
<?php endif; ?>
</main>
</div>

<!-- DETAIL MODAL -->
<div class="modal-overlay" id="detail-modal" onclick="if(event.target===this)closeDetail()">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-title">Detay</div>
      <button class="modal-close" onclick="closeDetail()">✕ Kapat</button>
    </div>
    <div class="detail-chart-wrap" id="modal-chart"></div>
    <div style="margin-top:10px;font-size:9px;color:var(--m);text-align:center" id="modal-sub"></div>
  </div>
</div>

<script>
const fmt = n => Number(n||0).toLocaleString('tr-TR');
const ago = s => { const d=Math.floor((Date.now()-new Date(s))/1000); if(d<60) return d+'s önce'; if(d<3600) return Math.floor(d/60)+'d önce'; if(d<86400) return Math.floor(d/3600)+'sa önce'; return Math.floor(d/86400)+'g önce'; };
const PIE_COLORS = ['#6ee7b7','#818cf8','#fb923c','#f87171','#fbbf24','#34d399','#60a5fa','#e879f9'];
const arr = v => Array.isArray(v) ? v : [];
const obj = v => (v && typeof v === 'object' && !Array.isArray(v)) ? v : {};
const val = (v, fallback=0) => (v === undefined || v === null || v === '') ? fallback : v;
const emptyMsg = txt => `<div style="color:var(--m);font-size:10px;padding:8px 0">${txt}</div>`;

// ── DASHBOARD DATA ──────────────────────────────────────────
async function loadDashboard(spin=false) {
  const btn = document.querySelector('.refresh-btn');
  if(spin && btn) btn.classList.add('spinning');
  try {
    const r = await fetch('stats.php?action=dashboard', {credentials:'include',cache:'no-store'});
    const d = await r.json();
    if(!d.success) return;

    // Cards — stats.php eski/yeni alan adlarıyla uyumlu
    const stats = {
      daily:   val(d.daily,   d.daily_visits),
      monthly: val(d.monthly, d.monthly_visits),
      yearly:  val(d.yearly,  d.yearly_visits),
      total:   val(d.total,   d.yearly_visits),
      live:    val(d.live,    d.live_visitors)
    };
    ['day','month','year','all'].forEach((k,i) => {
      const el = document.getElementById('c-'+k);
      if(el){ el.textContent=fmt([stats.daily,stats.monthly,stats.yearly,stats.total][i]); el.classList.remove('loading'); }
    });
    const ln = document.getElementById('live-num');
    if(ln){ ln.textContent=fmt(stats.live); ln.classList.remove('loading'); }
    const nv = document.getElementById('new-visitors');
    if(nv) nv.textContent = fmt(val(d.new_visitors, 0));
    const rv = document.getElementById('returning');
    if(rv) rv.textContent = fmt(val(d.returning, 0));

    // Last update
    const lu = document.getElementById('last-update');
    if(lu) lu.textContent = 'Son güncelleme: ' + new Date().toLocaleTimeString('tr-TR');

    // 7 günlük grafik
    const chart7 = arr(d.chart7);
    const wrap7 = document.getElementById('chart7');
    if(wrap7) {
      if(chart7.length) {
        const max = Math.max(1,...chart7.map(r=>+r[1]||0));
        wrap7.innerHTML = chart7.map(([day,cnt])=>{
          const pct = Math.max(4,Math.round(((+cnt||0)/max)*100));
          const label = String(day || '').slice(5).split('-').reverse().join('/') || '—';
          return `<div class="bar-col"><div class="bar-cnt">${cnt||''}</div><div class="bar" style="height:${pct}%"></div><div class="bar-day">${label}</div></div>`;
        }).join('');
      } else {
        wrap7.innerHTML = '<div style="color:var(--m);font-size:10px;align-self:center;width:100%;text-align:center">Henüz 7 günlük grafik verisi yok</div>';
      }
    }

    // Pasta grafik
    const pieData = arr(d.pie_data);
    if(pieData.length) drawPie(pieData);
    else {
      const legend = document.getElementById('pie-legend');
      const canvas = document.getElementById('pie-canvas');
      if(legend) legend.innerHTML = emptyMsg('Henüz sayfa görüntüleme yok');
      if(canvas) canvas.getContext('2d').clearRect(0,0,canvas.width,canvas.height);
    }

    // Saat ısı haritası
    const hours = obj(d.hours);
    const hourValues = Object.values(hours);
    const maxHour = Math.max(1,...hourValues.map(v=>+v||0));
    document.querySelectorAll('.hour-cell').forEach((cell,h) => {
      const cnt = +hours[h] || 0;
      const intensity = cnt/maxHour;
      cell.style.background = `rgba(129,140,248,${0.08 + intensity*0.7})`;
      cell.dataset.tip = `${String(h).padStart(2,'0')}:00 — ${cnt} ziyaret`;
      cell.classList.remove('loading');
    });

    // Cihaz istatistikleri
    const deviceStats = obj(d.devices);
    const wrapDevice = document.getElementById('device-stats');
    if(wrapDevice) {
      const entries = Object.entries(deviceStats);
      if(entries.length) {
        const total = entries.reduce((a,[,b])=>a+(+b||0),0)||1;
        wrapDevice.innerHTML = entries.map(([k,v])=>{
          const pct = Math.round((+v||0)/total*100);
          const cls = k==='Mobil'?'badge-mobile':k==='Tablet'?'badge-tablet':'badge-desktop';
          return `<div class="stat-row"><span class="badge ${cls}">${k}</span><span class="stat-row-val">${fmt(v)} <span style="color:var(--m);font-size:9px">(${pct}%)</span></span></div>`;
        }).join('') + (Object.keys(obj(d.browsers)).length ? `<div style="margin-top:10px">${Object.entries(obj(d.browsers)).slice(0,4).map(([k,v])=>`<div class="stat-row"><span class="stat-row-label">${k}</span><span class="stat-row-val">${fmt(v)}</span></div>`).join('')}</div>` : '');
      } else {
        wrapDevice.innerHTML = emptyMsg('Henüz cihaz verisi yok');
      }
    }

    // Son ziyaretler
    const recent = arr(d.recent);
    const tbody = document.getElementById('recent-tbody');
    if(tbody) tbody.innerHTML = recent.map(r=>`
      <tr>
        <td class="td-page"><a href="${r.page || '#'}" target="_blank">${r.page || '—'}</a></td>
        <td class="td-ref">${r.referrer_label||'Direkt'}</td>
        <td class="td-time">${r.viewed_at ? ago(r.viewed_at) : '—'}</td>
      </tr>`).join('') || '<tr><td colspan="3" style="color:var(--m)">Henüz sayfa görüntüleme yok</td></tr>';

  } catch(e){ console.error(e); }
  finally { if(btn) btn.classList.remove('spinning'); }
}

// ── PIE CHART ───────────────────────────────────────────────
function drawPie(data) {
  const canvas = document.getElementById('pie-canvas');
  const legend = document.getElementById('pie-legend');
  if(!canvas||!legend) return;
  const ctx = canvas.getContext('2d');
  const total = data.reduce((a,r)=>a+(+r.cnt),0)||1;
  let angle = -Math.PI/2;
  const cx=65,cy=65,r=55,inner=28;
  ctx.clearRect(0,0,130,130);
  data.slice(0,8).forEach((row,i)=>{
    const slice = (+row.cnt/total)*Math.PI*2;
    ctx.beginPath();ctx.moveTo(cx,cy);
    ctx.arc(cx,cy,r,angle,angle+slice);
    ctx.closePath();ctx.fillStyle=PIE_COLORS[i%PIE_COLORS.length];ctx.fill();
    angle+=slice;
  });
  // donut hole
  ctx.beginPath();ctx.arc(cx,cy,inner,0,Math.PI*2);ctx.fillStyle='#0e0e1b';ctx.fill();
  // legend
  legend.innerHTML = data.slice(0,8).map((row,i)=>{
    const pct=Math.round(+row.cnt/total*100);
    const name=row.page.length>20?'...'+row.page.slice(-17):row.page;
    return `<div class="pie-legend-item"><div class="pie-legend-dot" style="background:${PIE_COLORS[i%PIE_COLORS.length]}"></div><span class="pie-legend-label" title="${row.page}">${name}</span><span class="pie-legend-pct">${pct}%</span></div>`;
  }).join('');
}

// ── PAGES DATA ──────────────────────────────────────────────
async function loadPages(spin=false) {
  const btn = document.querySelector('.refresh-btn');
  if(spin&&btn) btn.classList.add('spinning');
  try {
    const r = await fetch('stats.php?action=dashboard',{credentials:'include',cache:'no-store'});
    const d = await r.json();
    if(!d.success) return;

    const lu = document.getElementById('last-update-p');
    if(lu) lu.textContent='Son güncelleme: '+new Date().toLocaleTimeString('tr-TR');

    // Sayfalar tablosu
    const topPages = arr(d.top_pages);
    const tbody=document.getElementById('pages-tbody');
    if(tbody) {
      const max = topPages.reduce((a,r)=>Math.max(a,+r.cnt||0),1);
      tbody.innerHTML = topPages.map(r=>{
        const pct=Math.round((+r.cnt||0)/max*100);
        return `<tr>
          <td class="td-page"><a href="${r.page || '#'}" target="_blank" title="${r.page || ''}">${r.page || '—'}</a></td>
          <td class="td-bar-wrap"><div class="td-bar-inner" style="width:${pct}%"></div></td>
          <td class="td-cnt">${fmt(+r.cnt||0)}</td>
        </tr>`;
      }).join('')||'<tr><td colspan="3" style="color:var(--m)">Henüz sayfa görüntüleme yok</td></tr>';
    }

    // Referrer
    const refs = obj(d.referrers);
    const refsWrap=document.getElementById('refs-wrap');
    if(refsWrap) {
      const entries = Object.entries(refs).slice(0,8);
      const total=entries.reduce((a,[,b])=>a+(+b||0),0)||1;
      refsWrap.innerHTML=entries.map(([k,v])=>{
        const pct=Math.round((+v||0)/total*100);
        return `<div class="stat-row"><span class="stat-row-label">${k}</span><div style="flex:1;margin:0 8px;height:4px;background:var(--b2);border-radius:2px"><div style="height:100%;width:${pct}%;background:var(--g3);border-radius:2px"></div></div><span class="stat-row-val">${fmt(v)}</span></div>`;
      }).join('')||emptyMsg('Henüz trafik kaynağı yok');
    }

    // Tarayıcı
    const bw=document.getElementById('browser-wrap');
    const browsers = Object.entries(obj(d.browsers)).slice(0,6);
    if(bw) bw.innerHTML=browsers.map(([k,v])=>`<div class="stat-row"><span class="stat-row-label">${k}</span><span class="stat-row-val">${fmt(v)}</span></div>`).join('') || emptyMsg('Henüz tarayıcı verisi yok');

    // OS
    const ow=document.getElementById('os-wrap');
    const osList = Object.entries(obj(d.os)).slice(0,6);
    if(ow) ow.innerHTML=osList.map(([k,v])=>`<div class="stat-row"><span class="stat-row-label">${k}</span><span class="stat-row-val">${fmt(v)}</span></div>`).join('') || emptyMsg('Henüz işletim sistemi verisi yok');

    // Cihaz
    const dw=document.getElementById('device-wrap');
    const devices = Object.entries(obj(d.devices)).slice(0,4);
    if(dw) dw.innerHTML=devices.map(([k,v])=>`<div class="stat-row"><span class="stat-row-label">${k}</span><span class="stat-row-val">${fmt(v)}</span></div>`).join('') || emptyMsg('Henüz cihaz verisi yok');

  } catch(e){console.error(e);}
  finally{if(btn)btn.classList.remove('spinning');}
}

// ── DETAIL MODAL ────────────────────────────────────────────
async function openDetail(period) {
  document.getElementById('detail-modal').classList.add('open');
  document.getElementById('modal-title').textContent='Yükleniyor...';
  document.getElementById('modal-chart').innerHTML='<div style="color:var(--m);padding:20px">Yükleniyor...</div>';
  try {
    const r=await fetch(`stats.php?action=detail&period=${period}`,{credentials:'include',cache:'no-store'});
    const d=await r.json();
    if(!d.success) return;
    document.getElementById('modal-title').textContent=d.title;
    const detailData = arr(d.data);
    const max=Math.max(1,...detailData.map(v=>+v||0));
    const detailLabels = arr(d.labels);
    const chart = document.getElementById('modal-chart');
    if (!detailLabels.length) {
      chart.innerHTML = '<div style="color:var(--m);padding:20px">Detay verisi yok</div>';
    } else {
      chart.innerHTML=detailLabels.map((lbl,i)=>{
        const cnt=+detailData[i]||0;
        const pct=Math.max(cnt > 0 ? 8 : 3, Math.round((cnt/max)*100));
        return `<div class="d-bar-col"><div class="d-bar-val">${cnt ? fmt(cnt) : ''}</div><div class="d-bar-track"><div class="d-bar" style="height:${pct}%" title="${lbl}: ${fmt(cnt)}"></div></div><div class="d-lbl">${lbl}</div></div>`;
      }).join('');
    }
    document.getElementById('modal-sub').textContent='Toplam: '+fmt(detailData.reduce((a,b)=>a+(+b||0),0))+' benzersiz ziyaretçi';
  } catch(e){console.error(e);}
}
function closeDetail(){document.getElementById('detail-modal').classList.remove('open');}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeDetail();});

// ── OTOMATİK GÜNCELLEME ──────────────────────────────────────
const currentPage = '<?=$page?>';
if(currentPage==='overview'){loadDashboard();setInterval(()=>loadDashboard(),30000);}
else if(currentPage==='pages'){loadPages();setInterval(()=>loadPages(),30000);}
</script>
</body>
</html>
