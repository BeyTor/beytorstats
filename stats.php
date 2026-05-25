<?php
/**
 * BeyTor Stats — Ana API v2
 * GET  ?action=visit        → Ziyareti kaydet + istatistik döndür
 * POST ?action=pageview     → Sayfa görüntüleme kaydet
 * POST ?action=linkclick    → Dış link tıklaması kaydet
 * GET  ?action=dashboard    → Dashboard verileri (korumalı)
 * GET  ?action=detail&period=today|month|year|all → Detay grafik
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db.php';

const SECRET_SALT    = 'beytorstats_salt_2024_degistir';
const ACTIVE_SECONDS = 60;

function getVisitorToken(): string {
    if (!empty($_COOKIE['ss_visitor_token'])) return $_COOKIE['ss_visitor_token'];
    $token = bin2hex(random_bytes(32));
    setcookie('ss_visitor_token', $token, [
        'expires' => time() + (60*60*24*365*2), 'path' => '/',
        'secure'  => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly'=> true, 'samesite' => 'Lax',
    ]);
    $_COOKIE['ss_visitor_token'] = $token;
    return $token;
}

function getClientIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $v = $_SERVER[$k];
            return trim($k === 'HTTP_X_FORWARDED_FOR' ? explode(',',$v)[0] : $v);
        }
    }
    return '0.0.0.0';
}

function hashIp(string $ip): string { return hash('sha256', $ip.'|'.SECRET_SALT); }

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function parseDevice(string $ua): array {
    $ua = strtolower($ua);
    // Cihaz
    if (preg_match('/mobile|android|iphone|ipod|blackberry|windows phone/', $ua)) $device = 'Mobil';
    elseif (preg_match('/ipad|tablet/', $ua)) $device = 'Tablet';
    else $device = 'Masaüstü';
    // Tarayıcı
    if (str_contains($ua, 'edg/')) $browser = 'Edge';
    elseif (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) $browser = 'Opera';
    elseif (str_contains($ua, 'chrome')) $browser = 'Chrome';
    elseif (str_contains($ua, 'firefox')) $browser = 'Firefox';
    elseif (str_contains($ua, 'safari')) $browser = 'Safari';
    elseif (str_contains($ua, 'msie') || str_contains($ua, 'trident')) $browser = 'IE';
    else $browser = 'Diğer';
    // İşletim sistemi
    if (str_contains($ua, 'windows')) $os = 'Windows';
    elseif (str_contains($ua, 'mac os')) $os = 'macOS';
    elseif (str_contains($ua, 'android')) $os = 'Android';
    elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad')) $os = 'iOS';
    elseif (str_contains($ua, 'linux')) $os = 'Linux';
    else $os = 'Diğer';
    return compact('device', 'browser', 'os');
}

function parseReferrer(string $ref): string {
    if (!$ref || $ref === 'direct') return 'Direkt';
    $host = parse_url($ref, PHP_URL_HOST) ?? '';
    $host = preg_replace('/^www\./', '', strtolower($host));
    if (str_contains($host, 'google'))    return 'Google';
    if (str_contains($host, 'bing'))      return 'Bing';
    if (str_contains($host, 'yandex'))    return 'Yandex';
    if (str_contains($host, 'facebook'))  return 'Facebook';
    if (str_contains($host, 'instagram')) return 'Instagram';
    if (str_contains($host, 'twitter') || str_contains($host, 'x.com')) return 'Twitter/X';
    if (str_contains($host, 'linkedin'))  return 'LinkedIn';
    if (str_contains($host, 'youtube'))   return 'YouTube';
    if (str_contains($host, 'whatsapp'))  return 'WhatsApp';
    return $host ?: 'Diğer';
}


function recordPageView(PDO $pdo, string $token, string $page, string $title, string $referrer, string $now): void {
    $page     = substr($page ?: '/', 0, 512);
    $title    = substr($title ?: '', 0, 255);
    $referrer = substr($referrer ?: 'direct', 0, 512);

    // Aynı sayfa aynı ziyaretçi tarafından birkaç saniye içinde iki kez yazılmasın.
    $check = $pdo->prepare("SELECT id FROM pageviews WHERE visitor_token=? AND page=? AND viewed_at >= (NOW() - INTERVAL 10 SECOND) LIMIT 1");
    $check->execute([$token, $page]);
    if ($check->fetchColumn()) return;

    $pdo->prepare("INSERT INTO pageviews (visitor_token,page,title,referrer,viewed_at) VALUES (?,?,?,?,?)")
        ->execute([$token, $page, $title, $referrer, $now]);
}

function isAdmin(): bool {
    session_start();
    return isset($_SESSION['ss_admin']);
}

$action    = $_GET['action'] ?? ($_POST['action'] ?? 'visit');
$now       = date('Y-m-d H:i:s');
$token     = getVisitorToken();
$ipHash    = hashIp(getClientIp());
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

try {

    // ── Sayfa görüntüleme ────────────────────────────────────
    if ($action === 'pageview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        recordPageView(
            $pdo,
            $token,
            $_POST['page'] ?? '/',
            $_POST['title'] ?? '',
            $_POST['referrer'] ?? 'direct',
            $now
        );
        respond(['success' => true]);
    }

    // ── Link tıklaması ───────────────────────────────────────
    if ($action === 'linkclick' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $url    = substr($_POST['url']    ?? '', 0, 1024);
        $text   = substr($_POST['text']   ?? '', 0, 255);
        $source = substr($_POST['source'] ?? '', 0, 512);
        $pdo->prepare("INSERT INTO link_clicks (visitor_token,url,link_text,source_page,clicked_at) VALUES (?,?,?,?,?)")
            ->execute([$token, $url, $text, $source, $now]);
        respond(['success' => true]);
    }

    // ── Dashboard API (admin only) ───────────────────────────
    if ($action === 'dashboard') {
        if (!isAdmin()) respond(['success' => false, 'message' => 'Yetkisiz'], 403);

        $daily   = (int)$pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM visitors WHERE DATE(first_visit)=CURDATE()")->fetchColumn();
        $monthly = (int)$pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM visitors WHERE YEAR(first_visit)=YEAR(NOW()) AND MONTH(first_visit)=MONTH(NOW())")->fetchColumn();
        $yearly  = (int)$pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM visitors WHERE YEAR(first_visit)=YEAR(NOW())")->fetchColumn();
        $total   = (int)$pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM visitors")->fetchColumn();
        $liveS = $pdo->prepare("SELECT COUNT(*) FROM visitors WHERE last_seen >= (NOW() - INTERVAL ? SECOND)");
        $liveS->execute([ACTIVE_SECONDS]);
        $live = (int)$liveS->fetchColumn();

        // 7 günlük grafik
        $chart = $pdo->query("SELECT DATE(first_visit) as d, COUNT(DISTINCT ip_hash) as cnt FROM visitors WHERE first_visit>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(first_visit) ORDER BY d")->fetchAll();
        $chartMap = [];
        foreach ($chart as $r) $chartMap[$r['d']] = (int)$r['cnt'];
        $chart7 = [];
        for ($i=6;$i>=0;$i--) { $d=date('Y-m-d',strtotime("-$i days")); $chart7[]=[$d, $chartMap[$d]??0]; }

        // En çok ziyaret edilen sayfalar
        $topPages = $pdo->query("SELECT page, COUNT(*) as cnt FROM pageviews GROUP BY page ORDER BY cnt DESC LIMIT 10")->fetchAll();

        // Pasta grafik verisi
        $pieData = $pdo->query("SELECT page, COUNT(*) as cnt FROM pageviews GROUP BY page ORDER BY cnt DESC LIMIT 8")->fetchAll();

        // Referrer kaynakları
        $refs = $pdo->query("SELECT referrer FROM pageviews WHERE referrer IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $refMap = [];
        foreach ($refs as $r) { $k = parseReferrer($r); $refMap[$k] = ($refMap[$k]??0)+1; }
        arsort($refMap);

        // Cihaz & tarayıcı istatistikleri
        $uas = $pdo->query("SELECT user_agent FROM visitors WHERE user_agent IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $deviceMap = []; $browserMap = []; $osMap = [];
        foreach ($uas as $ua) {
            $p = parseDevice($ua);
            $deviceMap[$p['device']]   = ($deviceMap[$p['device']]??0)+1;
            $browserMap[$p['browser']] = ($browserMap[$p['browser']]??0)+1;
            $osMap[$p['os']]           = ($osMap[$p['os']]??0)+1;
        }
        arsort($deviceMap); arsort($browserMap); arsort($osMap);

        // Son ziyaretler
        $recent = $pdo->query("SELECT page,title,referrer,viewed_at FROM pageviews ORDER BY viewed_at DESC LIMIT 15")->fetchAll();
        foreach ($recent as &$r) { $r['referrer_label'] = parseReferrer($r['referrer']); }

        // En aktif saatler (0-23)
        $hours = $pdo->query("SELECT HOUR(viewed_at) as h, COUNT(*) as cnt FROM pageviews GROUP BY h ORDER BY h")->fetchAll();
        $hourMap = array_fill(0, 24, 0);
        foreach ($hours as $h) $hourMap[(int)$h['h']] = (int)$h['cnt'];

        // Yeni vs Geri Dönen
        $newVisitors = (int)$pdo->query("SELECT COUNT(*) FROM visitors WHERE DATE(first_visit)=DATE(last_seen)")->fetchColumn();
        $returning   = $total - $newVisitors;

        respond([
            'success'     => true,
            'daily'       => $daily,
            'monthly'     => $monthly,
            'yearly'      => $yearly,
            'total'       => $total,
            'live'        => $live,
            'chart7'      => $chart7,
            'top_pages'   => $topPages,
            'pie_data'    => $pieData,
            'referrers'   => $refMap,
            'devices'     => $deviceMap,
            'browsers'    => $browserMap,
            'os'          => $osMap,
            'recent'      => $recent,
            'hours'       => $hourMap,
            'new_visitors'=> $newVisitors,
            'returning'   => $returning,
        ]);
    }

    // ── Detay grafik (kart tıklama) ──────────────────────────
    if ($action === 'detail') {
        if (!isAdmin()) respond(['success' => false, 'message' => 'Yetkisiz'], 403);
        $period = $_GET['period'] ?? 'today';

        $rows = [];
        if ($period === 'today') {
            // Bugün — saatlik
            $rows = $pdo->query("SELECT HOUR(first_visit) as label, COUNT(DISTINCT ip_hash) as cnt FROM visitors WHERE DATE(first_visit)=CURDATE() GROUP BY HOUR(first_visit) ORDER BY label")->fetchAll();
            $data = array_fill(0, 24, 0);
            foreach ($rows as $r) $data[(int)$r['label']] = (int)$r['cnt'];
            $labels = array_map(fn($h) => sprintf('%02d:00', $h), range(0,23));
            respond(['success'=>true,'labels'=>$labels,'data'=>array_values($data),'title'=>'Bugün — Saatlik']);
        } elseif ($period === 'month') {
            // Bu ay — günlük
            $rows = $pdo->query("SELECT DAY(first_visit) as label, COUNT(DISTINCT ip_hash) as cnt FROM visitors WHERE YEAR(first_visit)=YEAR(NOW()) AND MONTH(first_visit)=MONTH(NOW()) GROUP BY DAY(first_visit) ORDER BY label")->fetchAll();
            $days = date('t');
            $data = array_fill(1, $days, 0);
            foreach ($rows as $r) $data[(int)$r['label']] = (int)$r['cnt'];
            respond(['success'=>true,'labels'=>array_map(fn($d)=>$d.'/'.date('m'), range(1,$days)),'data'=>array_values($data),'title'=>date('F Y').' — Günlük']);
        } elseif ($period === 'year') {
            // Bu yıl — aylık
            $rows = $pdo->query("SELECT MONTH(first_visit) as label, COUNT(DISTINCT ip_hash) as cnt FROM visitors WHERE YEAR(first_visit)=YEAR(NOW()) GROUP BY MONTH(first_visit) ORDER BY label")->fetchAll();
            $months = ['Oca','Şub','Mar','Nis','May','Haz','Tem','Ağu','Eyl','Eki','Kas','Ara'];
            $data = array_fill(0, 12, 0);
            foreach ($rows as $r) $data[(int)$r['label']-1] = (int)$r['cnt'];
            respond(['success'=>true,'labels'=>$months,'data'=>$data,'title'=>date('Y').' — Aylık']);
        } elseif ($period === 'all') {
            // Tüm zamanlar — yıllık
            $rows = $pdo->query("SELECT YEAR(first_visit) as label, COUNT(DISTINCT ip_hash) as cnt FROM visitors GROUP BY YEAR(first_visit) ORDER BY label")->fetchAll();
            $labels = array_column($rows, 'label');
            $data   = array_map(fn($r)=>(int)$r['cnt'], $rows);
            respond(['success'=>true,'labels'=>$labels,'data'=>$data,'title'=>'Tüm Zamanlar — Yıllık']);
        }
        respond(['success'=>false,'message'=>'Geçersiz period']);
    }

    // ── Ziyaret kaydı + temel istatistik ────────────────────
    $pdo->beginTransaction();
    $ex = $pdo->prepare("SELECT id FROM visitors WHERE visitor_token = ?");
    $ex->execute([$token]);
    if (!$ex->fetch()) {
        $pdo->prepare("INSERT INTO visitors (visitor_token,ip_hash,user_agent,first_visit,last_seen,is_unique_counted) VALUES (?,?,?,?,?,1)")
            ->execute([$token,$ipHash,$userAgent,$now,$now]);
    } else {
        $pdo->prepare("UPDATE visitors SET last_seen=?,ip_hash=?,user_agent=? WHERE visitor_token=?")
            ->execute([$now,$ipHash,$userAgent,$token]);
    }

    // Analytics.js GET action=visit içinde sayfa bilgisini de gönderir.
    // Böylece POST pageview kaçsa bile dashboard sayfa/referrer/saat verileri dolmaya devam eder.
    if (!empty($_GET['page']) || !empty($_GET['title']) || !empty($_GET['referrer'])) {
        recordPageView(
            $pdo,
            $token,
            $_GET['page'] ?? '/',
            $_GET['title'] ?? '',
            $_GET['referrer'] ?? 'direct',
            $now
        );
    }

    $daily   = (int)$pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM visitors WHERE DATE(first_visit)=CURDATE()")->fetchColumn();
    $monthly = (int)$pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM visitors WHERE YEAR(first_visit)=YEAR(NOW()) AND MONTH(first_visit)=MONTH(NOW())")->fetchColumn();
    $yearly  = (int)$pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM visitors WHERE YEAR(first_visit)=YEAR(NOW())")->fetchColumn();
    $liveS   = $pdo->prepare("SELECT COUNT(*) FROM visitors WHERE last_seen>=(NOW()-INTERVAL ? SECOND)");
    $liveS->execute([ACTIVE_SECONDS]);
    $live = (int)$liveS->fetchColumn();
    $pdo->commit();

    respond(['success'=>true,'daily_visits'=>$daily,'monthly_visits'=>$monthly,'yearly_visits'=>$yearly,'live_visitors'=>$live]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond(['success'=>false,'message'=>'Sunucu hatası: '.$e->getMessage()], 500);
}
