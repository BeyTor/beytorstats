<?php
/**
 * BeyTor Stats — Script Enjektörü v3
 * ─────────────────────────────────────
 * Kök dizin + tüm alt klasörlerdeki .html dosyalarını tarar
 */

session_start();
if (empty($_SESSION['ss_admin'])) {
    header('Location: /beytorstats/dashboard.php');
    exit;
}

$statsDir  = 'beytorstats';
$scriptTag = '<script src="/' . $statsDir . '/analytics.js"></script>';
$backupDir = __DIR__ . '/_ss_backup';
$targetDir = __DIR__;
$action    = $_POST['action'] ?? '';
$results   = [];

// ─── ANALYTICS TAG KONTROLLERİ ───────────────────────────
function hasNewAnalyticsTag(string $content): bool {
    return (bool)preg_match(
        '~<script\b[^>]*\bsrc=["\']/beytorstats/analytics\.js(?:\?[^"\']*)?["\'][^>]*>\s*</script>~i',
        $content
    );
}

function hasOldAnalyticsTag(string $content): bool {
    return (bool)preg_match(
        '~<script\b[^>]*\bsrc=["\']/sitestats/analytics\.js(?:\?[^"\']*)?["\'][^>]*>\s*</script>~i',
        $content
    );
}

// Sadece eski /sitestats/ analytics tagını temizler
function removeOldAnalyticsTags(string $content): string {
    return preg_replace(
        '~\s*<script\b[^>]*\bsrc=["\']/sitestats/analytics\.js(?:\?[^"\']*)?["\'][^>]*>\s*</script>\s*~i',
        "\n",
        $content
    );
}

// Eski + yeni tüm BeyTor Stats analytics taglarını temizler
function removeAnalyticsTags(string $content): string {
    return preg_replace(
        '~\s*<script\b[^>]*\bsrc=["\']/(?:sitestats|beytorstats)/analytics\.js(?:\?[^"\']*)?["\'][^>]*>\s*</script>\s*~i',
        "\n",
        $content
    );
}

// ─── TÜM HTML DOSYALARINI TARA (alt klasörler dahil) ─────
function scanHtmlFiles(string $dir, string $base): array {
    $files = [];
    $items = glob($dir . '/*.html');
    foreach ($items as $f) {
        $rel = ltrim(str_replace($base, '', $f), '/\\');
        $files[] = $rel;
    }
    // Alt klasörler (1 seviye)
    $dirs = glob($dir . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $sub) {
        $name = basename($sub);
        if (in_array($name, ['_ss_backup', 'beytorstats', 'sitestats', 'PHPMailer', 'bootstrap-3.3.7'])) continue;
        $subItems = glob($sub . '/*.html');
        foreach ($subItems as $f) {
            $rel = ltrim(str_replace($base, '', $f), '/\\');
            $files[] = $rel;
        }
    }
    return $files;
}

// ─── EKLE ────────────────────────────────────────────────
if ($action === 'inject' && !empty($_POST['files'])) {
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
    foreach ($_POST['files'] as $rel) {
        $rel  = str_replace(['../', '..\\'], '', $rel);
        $file = $targetDir . '/' . $rel;
        if (!file_exists($file)) continue;
        $content = file_get_contents($file);
        if (str_contains($content, $scriptTag)) continue;
        $bkDir = $backupDir . '/' . dirname($rel);
        if (!is_dir($bkDir)) mkdir($bkDir, 0755, true);
        file_put_contents($bkDir . '/' . basename($rel) . '.bak', $content);
        $clean = removeAnalyticsTags($content);
        $new = preg_replace('/<\/body>/i', $scriptTag . "\n</body>", $clean, 1);
        file_put_contents($file, $new);
        $results[$rel] = 'added';
    }
}

// ─── ESKİ SCRIPTİ KALDIR ─────────────────────────────────
if ($action === 'remove_old' && !empty($_POST['files'])) {
    foreach ($_POST['files'] as $rel) {
        $rel  = str_replace(['../', '..\\'], '', $rel);
        $file = $targetDir . '/' . $rel;
        if (!file_exists($file)) continue;
        $content = file_get_contents($file);
        if (!hasOldAnalyticsTag($content)) continue;
        $bkDir = $backupDir . '/' . dirname($rel);
        if (!is_dir($bkDir)) mkdir($bkDir, 0755, true);
        file_put_contents($bkDir . '/' . basename($rel) . '.old-script.bak', $content);
        $new = removeOldAnalyticsTags($content);
        file_put_contents($file, $new);
        $results[$rel] = 'old_removed';
    }
}

// ─── KALDIR ──────────────────────────────────────────────
if ($action === 'remove' && !empty($_POST['files'])) {
    foreach ($_POST['files'] as $rel) {
        $rel  = str_replace(['../', '..\\'], '', $rel);
        $file = $targetDir . '/' . $rel;
        if (!file_exists($file)) continue;
        $content = file_get_contents($file);
        $new = removeAnalyticsTags($content);
        file_put_contents($file, $new);
        $results[$rel] = 'removed';
    }
}

// ─── DOSYALARI TARA ──────────────────────────────────────
$allFiles = scanHtmlFiles($targetDir, $targetDir);
$files = [];
foreach ($allFiles as $rel) {
    $file         = $targetDir . '/' . $rel;
    $content      = file_get_contents($file);
    $hasNewScript = hasNewAnalyticsTag($content);
    $hasOldScript = hasOldAnalyticsTag($content);
    $hasBody      = str_contains(strtolower($content), '</body>');

    $status = 'skip';
    if ($hasBody && $hasOldScript && !$hasNewScript) $status = 'old';
    if ($hasBody && $hasNewScript)                   $status = 'has';
    if ($hasBody && !$hasNewScript && !$hasOldScript) $status = 'ready';

    $files[] = [
        'name'      => $rel,
        'status'    => $status,
        'has_old'   => $hasOldScript,
        'has_new'   => $hasNewScript,
        'result'    => $results[$rel] ?? null
    ];
}

// Klasöre göre grupla
$groups = [];
foreach ($files as $f) {
    $dir = dirname($f['name']);
    if ($dir === '.') $dir = '/ (Kök dizin)';
    else $dir = '/' . $dir;
    $groups[$dir][] = $f;
}

$countReady = count(array_filter($files, fn($f) => $f['status'] === 'ready'));
$countHas   = count(array_filter($files, fn($f) => $f['status'] === 'has'));
$countOld   = count(array_filter($files, fn($f) => $f['status'] === 'old'));
$countSkip  = count(array_filter($files, fn($f) => $f['status'] === 'skip'));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>BeyTor Stats — Enjektör</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Fraunces:ital,wght@0,700&display=swap" rel="stylesheet"/>
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{--bg:#07070f;--s:#0f0f1c;--s2:#131325;--b:rgba(255,255,255,.08);--b2:rgba(255,255,255,.04);--g1:#6ee7b7;--g2:#818cf8;--g3:#fb923c;--warn:#fbbf24;--danger:#f87171;--t:#e2e8f0;--m:#64748b}
  body{background:var(--bg);color:var(--t);font-family:'DM Mono',monospace;min-height:100vh;padding:36px 16px 80px}
  .wrap{max-width:720px;margin:0 auto}
  .logo{font-family:'Fraunces',serif;font-size:24px;color:var(--g1);margin-bottom:2px}
  .sub{font-size:10px;color:var(--m);letter-spacing:.14em;text-transform:uppercase;margin-bottom:28px}

  .summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}
  .sum-card{background:var(--s);border:1px solid var(--b);border-radius:12px;padding:16px}
  .sum-val{font-size:26px;font-weight:700;letter-spacing:-.04em;line-height:1}
  .sum-lbl{font-size:9px;letter-spacing:.14em;text-transform:uppercase;color:var(--m);margin-top:4px}
  .sum-card.ready .sum-val{color:var(--g1)}.sum-card.has .sum-val{color:var(--g2)}.sum-card.old .sum-val{color:var(--warn)}.sum-card.skip .sum-val{color:var(--m)}

  .info-box{background:rgba(110,231,183,.05);border:1px solid rgba(110,231,183,.15);border-radius:10px;padding:13px 15px;font-size:11px;color:var(--m);line-height:1.7;margin-bottom:20px}
  .info-box strong{color:var(--g1)}

  /* GROUP */
  .group-title{font-size:9px;letter-spacing:.18em;text-transform:uppercase;color:var(--m);margin:24px 0 10px;display:flex;align-items:center;gap:10px}
  .group-title::after{content:'';flex:1;height:1px;background:var(--b)}
  .group-toolbar{display:flex;gap:8px;margin-bottom:10px}

  .file-list{display:flex;flex-direction:column;gap:7px;margin-bottom:6px}
  .file-row{display:flex;align-items:center;gap:10px;background:var(--s);border:1px solid var(--b);border-radius:10px;padding:10px 14px;transition:all .2s}
  .file-row.ready:hover{border-color:rgba(110,231,183,.25);background:rgba(110,231,183,.03)}
  .file-row.has:hover{border-color:rgba(129,140,248,.25)}
  .file-row.old:hover{border-color:rgba(251,191,36,.25);background:rgba(251,191,36,.03)}
  .file-row.skip{opacity:.4;cursor:default}
  .file-row.selected{border-color:rgba(110,231,183,.35);background:rgba(110,231,183,.05)}
  .file-row.has.selected{border-color:rgba(248,113,113,.35);background:rgba(248,113,113,.04)}
  .file-row.old.selected{border-color:rgba(251,191,36,.35);background:rgba(251,191,36,.04)}

  .file-cb{width:15px;height:15px;flex-shrink:0;accent-color:var(--g1);cursor:pointer}
  .file-row.has .file-cb{accent-color:var(--danger)}
  .file-row.old .file-cb{accent-color:var(--warn)}
  .file-name{flex:1;font-size:11px;color:var(--t)}
  .file-dir{font-size:9px;color:var(--m)}

  .file-actions{display:flex;gap:6px;align-items:center;flex-shrink:0}
  .preview-btn{font-family:'DM Mono',monospace;font-size:9px;letter-spacing:.08em;padding:4px 10px;border-radius:6px;cursor:pointer;border:1px solid var(--b);background:none;color:var(--m);text-decoration:none;transition:all .2s;white-space:nowrap}
  .preview-btn:hover{color:var(--g2);border-color:rgba(129,140,248,.3)}

  .file-badge{font-size:9px;letter-spacing:.1em;text-transform:uppercase;padding:3px 8px;border-radius:20px;flex-shrink:0}
  .badge-ready{background:rgba(110,231,183,.1);color:var(--g1);border:1px solid rgba(110,231,183,.2)}
  .badge-has{background:rgba(129,140,248,.1);color:var(--g2);border:1px solid rgba(129,140,248,.2)}
  .badge-old{background:rgba(251,191,36,.1);color:var(--warn);border:1px solid rgba(251,191,36,.2)}
  .badge-skip{background:rgba(100,116,139,.1);color:var(--m);border:1px solid rgba(100,116,139,.15)}
  .badge-added{background:rgba(110,231,183,.15);color:var(--g1);border:1px solid rgba(110,231,183,.3)}
  .badge-removed{background:rgba(248,113,113,.1);color:var(--danger);border:1px solid rgba(248,113,113,.2)}

  .select-btn{background:none;border:1px solid var(--b);color:var(--m);font-family:'DM Mono',monospace;font-size:9px;letter-spacing:.08em;padding:5px 11px;border-radius:7px;cursor:pointer;transition:all .2s}
  .select-btn:hover{color:var(--t);border-color:rgba(255,255,255,.2)}

  /* ACTION BAR */
  .action-bar{position:fixed;bottom:0;left:0;right:0;background:rgba(7,7,15,.93);backdrop-filter:blur(12px);border-top:1px solid var(--b);padding:14px 24px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;transform:translateY(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);z-index:999}
  .action-bar.visible{transform:translateY(0)}
  .action-bar-left{font-size:12px;color:var(--m)}
  .action-bar-left strong{color:var(--t)}
  .action-bar-right{display:flex;gap:10px}
  .btn{padding:10px 18px;border-radius:9px;font-family:'DM Mono',monospace;font-size:12px;font-weight:500;letter-spacing:.06em;cursor:pointer;border:none;transition:opacity .2s;text-decoration:none;display:inline-block}
  .btn-add{background:var(--g1);color:#07070f}
  .btn-remove{background:var(--danger);color:#fff}
  .btn-old{background:var(--warn);color:#07070f}
  .btn-cancel{background:none;border:1px solid var(--b);color:var(--m)}
  .btn:hover{opacity:.85}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">BeyTor Stats</div>
  <div class="sub">Script Enjektörü v3 — Alt klasörler dahil</div>

  <?php if(!empty($results)):
    $added=count(array_filter($results,fn($v)=>$v==='added'));
    $removed=count(array_filter($results,fn($v)=>$v==='removed'));
    $oldRemoved=count(array_filter($results,fn($v)=>$v==='old_removed'));
  ?>
  <div class="info-box">
    <?php if($added) echo "✓ <strong>$added dosyaya</strong> script eklendi. Yedekler <code>_ss_backup/</code> klasörüne kaydedildi.<br/>";?>
    <?php if($removed) echo "✓ <strong>$removed dosyadan</strong> script kaldırıldı.<br/>";?>
    <?php if($oldRemoved) echo "✓ <strong>$oldRemoved dosyadan</strong> eski /sitestats/ scripti kaldırıldı. Artık yeni script eklenebilir.";?>
  </div>
  <?php endif;?>

  <div class="summary">
    <div class="sum-card ready"><div class="sum-val"><?=$countReady?></div><div class="sum-lbl">Eklenebilir</div></div>
    <div class="sum-card has"><div class="sum-val"><?=$countHas?></div><div class="sum-lbl">Yeni Mevcut</div></div>
    <div class="sum-card old"><div class="sum-val"><?=$countOld?></div><div class="sum-lbl">Eski Script</div></div>
    <div class="sum-card skip"><div class="sum-val"><?=$countSkip?></div><div class="sum-lbl">Atlandı</div></div>
  </div>

  <form method="POST" id="main-form">
    <input type="hidden" name="action" id="form-action" value="inject"/>

    <?php foreach($groups as $groupName => $groupFiles):
      $groupId = preg_replace('/[^a-z0-9]/i','_', $groupName);
      $readyInGroup = array_filter($groupFiles, fn($f)=>$f['status']==='ready');
      $hasInGroup   = array_filter($groupFiles, fn($f)=>$f['status']==='has');
      $oldInGroup   = array_filter($groupFiles, fn($f)=>$f['status']==='old');
    ?>

    <div class="group-title"><?=htmlspecialchars($groupName)?> <span style="color:var(--b2)">(<?=count($groupFiles)?> dosya)</span></div>

    <?php if(!empty($oldInGroup)):?>
    <div class="group-toolbar">
      <button type="button" class="select-btn" onclick="selectGroup('old','<?=$groupId?>')">Eski Scriptleri Seç</button>
      <button type="button" class="select-btn" onclick="deselectGroup('old','<?=$groupId?>')">Seçimi Kaldır</button>
    </div>
    <?php endif;?>

    <?php if(!empty($readyInGroup)):?>
    <div class="group-toolbar">
      <button type="button" class="select-btn" onclick="selectGroup('ready','<?=$groupId?>')">Tümünü Seç</button>
      <button type="button" class="select-btn" onclick="deselectGroup('ready','<?=$groupId?>')">Seçimi Kaldır</button>
    </div>
    <?php endif;?>

    <div class="file-list">
    <?php foreach($groupFiles as $f):
      $urlPath = '/' . str_replace('\\', '/', $f['name']);
    ?>
      <label class="file-row <?=$f['status']?> <?=$f['result']==='added'?'selected':''?>"
             <?=$f['status']!=='skip'?'onclick="toggleRow(this)"':''?>>
        <?php if($f['status']!=='skip'):?>
        <input type="checkbox" name="files[]" value="<?=htmlspecialchars($f['name'])?>"
               class="file-cb <?=$f['status']?>-cb <?=$groupId?>-cb"
               <?=$f['result']==='added'?'checked':''?>
               onclick="event.stopPropagation()" onchange="updateBar()"/>
        <?php else:?>
        <span style="width:15px;flex-shrink:0"></span>
        <?php endif;?>

        <span class="file-name"><?=htmlspecialchars(basename($f['name']))?></span>

        <div class="file-actions">
          <?php if($f['status']==='has' || $f['result']==='added'):?>
          <a href="<?=htmlspecialchars($urlPath)?>" target="_blank" class="preview-btn">👁 Önizle</a>
          <?php endif;?>
          <span class="file-badge <?php
            if($f['result']==='added') echo 'badge-added';
            elseif($f['result']==='removed' || $f['result']==='old_removed') echo 'badge-removed';
            elseif($f['status']==='old') echo 'badge-old';
            elseif($f['status']==='has') echo 'badge-has';
            elseif($f['status']==='ready') echo 'badge-ready';
            else echo 'badge-skip';
          ?>">
            <?php
              if($f['result']==='added') echo '✓ Eklendi';
              elseif($f['result']==='removed') echo '✓ Kaldırıldı';
              elseif($f['result']==='old_removed') echo '✓ Eski Silindi';
              elseif($f['status']==='old') echo 'Eski Script';
              elseif($f['status']==='has') echo 'Yeni Mevcut';
              elseif($f['status']==='ready') echo 'Eklenebilir';
              else echo 'Atlandı';
            ?>
          </span>
        </div>
      </label>
    <?php endforeach;?>
    </div>

    <?php if(!empty($hasInGroup)):?>
    <div class="group-toolbar" style="margin-top:6px">
      <button type="button" class="select-btn" onclick="selectGroup('has','<?=$groupId?>')">Mevcut — Tümünü Seç (Kaldır)</button>
      <button type="button" class="select-btn" onclick="deselectGroup('has','<?=$groupId?>')">Seçimi Kaldır</button>
    </div>
    <?php endif;?>

    <?php endforeach;?>
  </form>
</div>

<div class="action-bar" id="action-bar">
  <div class="action-bar-left"><strong id="bar-count">0</strong> dosya seçildi</div>
  <div class="action-bar-right">
    <button type="button" class="btn btn-cancel" onclick="deselectAll()">İptal</button>
    <button type="button" class="btn btn-old" id="btn-old" style="display:none" onclick="submitAction('remove_old')">Eski Scripti Kaldır</button>
    <button type="button" class="btn btn-remove" id="btn-remove" style="display:none" onclick="submitAction('remove')">Seçilenleri Kaldır</button>
    <button type="button" class="btn btn-add" id="btn-add" style="display:none" onclick="submitAction('inject')">Seçilenlere Ekle</button>
  </div>
</div>

<script>
function toggleRow(label){
  const cb=label.querySelector('input[type="checkbox"]');
  if(!cb)return;
  cb.checked=!cb.checked;
  label.classList.toggle('selected',cb.checked);
  updateBar();
}
function selectGroup(type,group){
  document.querySelectorAll('.'+group+'-cb.'+type+'-cb').forEach(cb=>{
    cb.checked=true; cb.closest('.file-row').classList.add('selected');
  }); updateBar();
}
function deselectGroup(type,group){
  document.querySelectorAll('.'+group+'-cb.'+type+'-cb').forEach(cb=>{
    cb.checked=false; cb.closest('.file-row').classList.remove('selected');
  }); updateBar();
}
function deselectAll(){
  document.querySelectorAll('.file-cb').forEach(cb=>{
    cb.checked=false; cb.closest('.file-row').classList.remove('selected');
  }); updateBar();
}
function updateBar(){
  const rc=[...document.querySelectorAll('.ready-cb:checked')];
  const hc=[...document.querySelectorAll('.has-cb:checked')];
  const oc=[...document.querySelectorAll('.old-cb:checked')];
  const total=rc.length+hc.length+oc.length;
  document.getElementById('bar-count').textContent=total;
  document.getElementById('action-bar').classList.toggle('visible',total>0);
  document.getElementById('btn-add').style.display=rc.length>0?'':'none';
  document.getElementById('btn-remove').style.display=hc.length>0?'':'none';
  document.getElementById('btn-old').style.display=oc.length>0?'':'none';
}
function submitAction(action){
  document.getElementById('form-action').value=action;
  if(action==='inject') {
    document.querySelectorAll('.has-cb,.old-cb').forEach(cb=>cb.disabled=true);
  } else if(action==='remove_old') {
    document.querySelectorAll('.ready-cb,.has-cb').forEach(cb=>cb.disabled=true);
  } else {
    document.querySelectorAll('.ready-cb,.old-cb').forEach(cb=>cb.disabled=true);
  }
  document.getElementById('main-form').submit();
}
</script>
</body>
</html>
