<?php
/**
 * BeyTor Stats — Otomatik Kurulum Sihirbazı
 */

session_start();

// ─── SABİT DEĞERLER ──────────────────────────────────────
const BS_DB_NAME = 'beytor_stats';
const BS_DB_USER = 'beytor_user';

$step  = $_POST['step'] ?? $_GET['step'] ?? '0';
$error   = '';
$dbFound = false; // beytor_stats veritabanı mevcut mu?

// ─── ADIM 0: Formdan gelen bilgilerle bağlan + db.php yaz ─
if ($step === 'db' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost  = trim($_POST['db_host']  ?? 'localhost');
    $dbPort  = trim($_POST['db_port']  ?? '3306');
    $dbName  = trim($_POST['db_name']  ?? '');
    $dbUser  = trim($_POST['db_user']  ?? '');
    $dbPass  = trim($_POST['db_pass']  ?? '');
    $dbPass2 = trim($_POST['db_pass2'] ?? '');

    if (empty($dbName) || empty($dbUser)) {
        $error = 'Veritabanı adı ve kullanıcı adı boş bırakılamaz.';
        $step  = '0';
    } elseif ($dbPass !== $dbPass2) {
        $error = 'Şifreler eşleşmiyor.';
        $step  = '0';
    } else {
        try {
            // Bağlantıyı test et
            new PDO(
                "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
                $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            // Başarılı → db.php'yi yaz
            writeDbPhp($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
            $step = '1';
        } catch (PDOException $e) {
            $error = 'Bağlantı hatası: ' . htmlspecialchars($e->getMessage());
            $step  = '0';
        }
    }
}

// ─── ADIM 0: Root ile bağlan, DB var mı kontrol et ───────
if ($step === 'check' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost   = trim($_POST['db_host']   ?? 'localhost');
    $dbPort   = trim($_POST['db_port']   ?? '3306');
    $rootUser = trim($_POST['root_user'] ?? '');
    $rootPass = trim($_POST['root_pass'] ?? '');

    $_SESSION['bs_host']      = $dbHost;
    $_SESSION['bs_port']      = $dbPort;
    $_SESSION['bs_root_user'] = $rootUser;
    $_SESSION['bs_root_pass'] = $rootPass;

    if (empty($rootUser)) {
        $error = 'Kullanıcı adı boş bırakılamaz.';
        $step  = '0';
    } else {
        try {
            $rootPdo = new PDO(
                "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
                $rootUser, $rootPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            // beytor_stats var mı?
            $res = $rootPdo->query("SHOW DATABASES LIKE '" . BS_DB_NAME . "'")->fetchColumn();
            $_SESSION['bs_db_exists'] = (bool)$res;
            $step = '0b'; // sonuç ekranı
        } catch (PDOException $e) {
            $error = 'Bağlantı hatası: ' . htmlspecialchars($e->getMessage());
            $step  = '0';
        }
    }
}

// ─── ADIM 0b: Yeni DB oluştur ────────────────────────────
if ($step === 'db_create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost   = $_SESSION['bs_host']      ?? 'localhost';
    $dbPort   = $_SESSION['bs_port']      ?? '3306';
    $rootUser = $_SESSION['bs_root_user'] ?? '';
    $rootPass = $_SESSION['bs_root_pass'] ?? '';
    $newPass  = trim($_POST['new_db_pass'] ?? '');
    $doDelete = isset($_POST['delete_first']);

    if (empty($newPass)) {
        $error = 'Şifre boş bırakılamaz.';
        $step  = '0b';
    } else {
        try {
            $rootPdo = new PDO(
                "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
                $rootUser, $rootPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Silme isteniyorsa önce sil
            if ($doDelete) {
                $rootPdo->exec("DROP DATABASE IF EXISTS `" . BS_DB_NAME . "`");
                $rootPdo->exec("DROP USER IF EXISTS '" . BS_DB_USER . "'@'localhost'");
            }

            // Veritabanı + kullanıcı oluştur
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . BS_DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $rootPdo->exec("CREATE USER IF NOT EXISTS '" . BS_DB_USER . "'@'localhost' IDENTIFIED BY '{$newPass}'");
            $rootPdo->exec("GRANT ALL PRIVILEGES ON `" . BS_DB_NAME . "`.* TO '" . BS_DB_USER . "'@'localhost'");
            $rootPdo->exec("FLUSH PRIVILEGES");

            // Yeni kullanıcıyla doğrula
            new PDO(
                "mysql:host={$dbHost};port={$dbPort};dbname=" . BS_DB_NAME . ";charset=utf8mb4",
                BS_DB_USER, $newPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            writeDbPhp($dbHost, $dbPort, BS_DB_NAME, BS_DB_USER, $newPass);
            $step = '1';

        } catch (PDOException $e) {
            $error = 'Hata: ' . htmlspecialchars($e->getMessage());
            $step  = '0b';
        }
    }
}

// ─── ADIM 0c: Mevcut DB ile devam et ────────────────────
if ($step === 'db_existing' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost   = $_SESSION['bs_host']      ?? 'localhost';
    $dbPort   = $_SESSION['bs_port']      ?? '3306';
    $dbUser   = trim($_POST['db_user']    ?? '');
    $dbPass   = trim($_POST['db_pass']    ?? '');
    $dbPass2  = trim($_POST['db_pass2']   ?? '');
    $dbName   = trim($_POST['db_name']    ?? '');

    if (empty($dbUser) || empty($dbName)) {
        $error = 'Kullanıcı adı ve veritabanı adı boş bırakılamaz.';
        $step  = '0';
    } elseif ($dbPass !== $dbPass2) {
        $error = 'Şifreler eşleşmiyor.';
        $step  = '0';
    } else {
        try {
            new PDO(
                "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
                $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            writeDbPhp($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
            $step = '1';
        } catch (PDOException $e) {
            $error = 'Bağlantı hatası: ' . htmlspecialchars($e->getMessage());
            $step  = '0';
        }
    }
}

// ─── db.php yaz ──────────────────────────────────────────
function writeDbPhp(string $host, string $port, string $name, string $user, string $pass): void {
    $content = "<?php\n"
        . "define('DB_HOST', " . var_export($host, true) . ");\n"
        . "define('DB_PORT', " . var_export($port, true) . ");\n"
        . "define('DB_NAME', " . var_export($name, true) . ");\n"
        . "define('DB_USER', " . var_export($user, true) . ");\n"
        . "define('DB_PASS', " . var_export($pass, true) . ");\n"
        . "define('DB_CHARSET', 'utf8mb4');\n\n"
        . "try {\n"
        . "    \$pdo = new PDO(\n"
        . "        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),\n"
        . "        DB_USER, DB_PASS,\n"
        . "        [\n"
        . "            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n"
        . "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n"
        . "            PDO::ATTR_EMULATE_PREPARES   => false,\n"
        . "        ]\n"
        . "    );\n"
        . "} catch (PDOException \$e) {\n"
        . "    http_response_code(500);\n"
        . "    echo json_encode(['success' => false, 'message' => 'Veritabanı bağlantısı kurulamadı.', 'error' => \$e->getMessage()], JSON_UNESCAPED_UNICODE);\n"
        . "    exit;\n"
        . "}\n";
    file_put_contents(__DIR__ . '/db.php', $content);
}

// ─── ADIM 1 → 2: Tabloları kur + admin oluştur ───────────
if ($step === '2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUser  = trim($_POST['admin_user']  ?? '');
    $adminPass  = trim($_POST['admin_pass']  ?? '');
    $adminPass2 = trim($_POST['admin_pass2'] ?? '');
    $basicUser  = trim($_POST['basic_user']  ?? '');
    $basicPass  = trim($_POST['basic_pass']  ?? '');

    if (empty($adminUser) || empty($adminPass) || empty($basicUser) || empty($basicPass)) {
        $error = 'Tüm alanları doldurun.';
        $step  = '1';
    } elseif ($adminPass !== $adminPass2) {
        $error = 'Dashboard şifreleri eşleşmiyor.';
        $step  = '1';
    } else {
        try {
            require_once __DIR__ . '/db.php';

            // Tabloları doğrudan PHP içinde oluştur (setup.sql'e bağımlılık yok)
            $tables = [
                "CREATE TABLE IF NOT EXISTS `visitors` (
                    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `visitor_token`     CHAR(64)        NOT NULL,
                    `ip_hash`           CHAR(64)        NOT NULL,
                    `user_agent`        VARCHAR(255)    DEFAULT NULL,
                    `first_visit`       DATETIME        NOT NULL,
                    `last_seen`         DATETIME        NOT NULL,
                    `is_unique_counted` TINYINT(1)      NOT NULL DEFAULT 1,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_visitor_token` (`visitor_token`),
                    INDEX `idx_first_visit` (`first_visit`),
                    INDEX `idx_last_seen`   (`last_seen`),
                    INDEX `idx_ip_hash`     (`ip_hash`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS `pageviews` (
                    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `visitor_token` CHAR(64)        NOT NULL,
                    `page`          VARCHAR(512)    NOT NULL,
                    `title`         VARCHAR(255)    DEFAULT NULL,
                    `referrer`      VARCHAR(512)    DEFAULT 'direct',
                    `viewed_at`     DATETIME        NOT NULL,
                    PRIMARY KEY (`id`),
                    INDEX `idx_viewed_at`     (`viewed_at`),
                    INDEX `idx_visitor_token` (`visitor_token`),
                    INDEX `idx_page`          (`page`(128))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS `link_clicks` (
                    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `visitor_token` CHAR(64)        NOT NULL,
                    `url`           VARCHAR(1024)   NOT NULL,
                    `link_text`     VARCHAR(255)    DEFAULT NULL,
                    `source_page`   VARCHAR(512)    DEFAULT NULL,
                    `clicked_at`    DATETIME        NOT NULL,
                    PRIMARY KEY (`id`),
                    INDEX `idx_clicked_at` (`clicked_at`),
                    INDEX `idx_url`        (`url`(128))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                "CREATE TABLE IF NOT EXISTS `admin_users` (
                    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                    `username`   VARCHAR(64)   NOT NULL,
                    `password`   VARCHAR(255)  NOT NULL,
                    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_username` (`username`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ];

            foreach ($tables as $sql) {
                $pdo->exec($sql);
            }

            $hash = password_hash($adminPass, PASSWORD_BCRYPT);
            $pdo->prepare("
                INSERT INTO admin_users (username, password)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE password = VALUES(password)
            ")->execute([$adminUser, $hash]);

            $step = 'done';

            // ── .htpasswd oluştur (Basic Auth) ───────────────
            $htpasswdHash = base64_encode(pack('H*', sha1($basicPass)));
            $htpasswdContent = "{$basicUser}:{SHA}{$htpasswdHash}\n";
            $htpasswdPath = __DIR__ . '/.htpasswd';
            file_put_contents($htpasswdPath, $htpasswdContent);

            // ── .htaccess oluştur ─────────────────────────────
            $htaccessContent = <<<HTACCESS
# BeyTor Stats — Güvenlik Kuralları
Options -Indexes

# Basic Auth Koruması
AuthType Basic
AuthName "BeyTor Stats"
AuthUserFile {$htpasswdPath}
Require valid-user

# Hassas dosyalara erişimi engelle
<FilesMatch "^(db\.php|setup\.sql|inject\.php|\.htpasswd|README\.md)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Sadece izin verilen dosyalar
<FilesMatch "^(dashboard\.php|stats\.php|analytics\.js)$">
    Order Allow,Deny
    Allow from all
    Satisfy any
</FilesMatch>
HTACCESS;
            file_put_contents(__DIR__ . '/.htaccess', $htaccessContent);

            // ── inject.php'yi ana dizine taşı ────────────────
            $injectSrc = __DIR__ . '/inject.php';
            $injectDst = dirname(__DIR__) . '/inject.php';
            if (file_exists($injectSrc)) {
                if (file_exists($injectDst)) {
                    @unlink($injectDst);
                }
                if (!@rename($injectSrc, $injectDst)) {
                    copy($injectSrc, $injectDst);
                    @unlink($injectSrc);
                }
            }

            @unlink(__FILE__);

        } catch (Throwable $e) {
            $error = 'Veritabanı hatası: ' . htmlspecialchars($e->getMessage());
            $step  = '1';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>BeyTor Stats — Kurulum</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Fraunces:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:      #07070f;
    --surface: #0f0f1c;
    --surface2:#141428;
    --border:  rgba(255,255,255,0.08);
    --border2: rgba(255,255,255,0.04);
    --accent:  #6ee7b7;
    --accent2: #818cf8;
    --accent3: #fb923c;
    --danger:  #f87171;
    --warn:    #fbbf24;
    --text:    #e2e8f0;
    --muted:   #64748b;
  }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Mono', monospace;
    min-height: 100vh;
    padding: 32px 16px 60px;
  }

  /* ── STEPPER ─────────────────────────────────────────── */
  .stepper {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    margin-bottom: 40px;
  }
  .step-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    position: relative;
  }
  .step-circle {
    width: 32px; height: 32px;
    border-radius: 50%;
    border: 1.5px solid var(--border);
    background: var(--surface);
    color: var(--muted);
    font-size: 12px;
    display: flex; align-items: center; justify-content: center;
    transition: all .3s;
    z-index: 1;
  }
  .step-circle.active {
    border-color: var(--accent);
    color: var(--accent);
    background: rgba(110,231,183,.1);
  }
  .step-circle.done {
    border-color: var(--accent);
    background: var(--accent);
    color: #07070f;
  }
  .step-label {
    font-size: 8.5px;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--muted);
    white-space: nowrap;
  }
  .step-label.active { color: var(--accent); }
  .step-line {
    width: 60px; height: 1px;
    background: var(--border);
    margin-bottom: 20px;
  }

  /* ── CARD ────────────────────────────────────────────── */
  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 40px;
    width: 100%;
    max-width: 680px;
    margin: 0 auto;
  }

  .logo {
    font-family: 'Fraunces', serif;
    font-size: 26px;
    color: var(--accent);
    margin-bottom: 4px;
    text-align: center;
  }

  .wizard-title {
    font-size: 10px;
    color: var(--muted);
    letter-spacing: .16em;
    text-transform: uppercase;
    text-align: center;
    margin-bottom: 36px;
  }

  /* ── PANELS ──────────────────────────────────────────── */
  .panel-title {
    font-family: 'Fraunces', serif;
    font-size: 20px;
    letter-spacing: -.02em;
    margin-bottom: 6px;
  }
  .panel-sub {
    font-size: 11px;
    color: var(--muted);
    line-height: 1.6;
    margin-bottom: 28px;
  }

  /* ── WARNING BOX ─────────────────────────────────────── */
  .warn-box {
    background: rgba(251,191,36,.05);
    border: 1px solid rgba(251,191,36,.2);
    border-radius: 12px;
    padding: 18px 20px;
    margin-bottom: 28px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
  }
  .warn-icon { font-size: 20px; flex-shrink: 0; }
  .warn-text { font-size: 12px; color: #fde68a; line-height: 1.7; }
  .warn-text strong { color: var(--warn); }

  /* ── TABS ─────────────────────────────────────────────── */
  .tabs { display: flex; gap: 8px; margin-bottom: 20px; }
  .tab {
    flex: 1; padding: 10px 16px; border-radius: 9px;
    border: 1px solid var(--border);
    background: var(--surface2);
    color: var(--muted); font-family: 'DM Mono', monospace;
    font-size: 11px; letter-spacing: .06em;
    cursor: pointer; transition: all .2s; text-align: center;
  }
  .tab.active {
    border-color: rgba(129,140,248,.4);
    background: rgba(129,140,248,.08);
    color: var(--accent2);
  }
  .tab-panel { display: none; }
  .tab-panel.active { display: block; }

  /* ── STEPS LIST ──────────────────────────────────────── */
  .steps-list { display: flex; flex-direction: column; gap: 14px; margin-bottom: 24px; }
  .step-row {
    display: flex; gap: 14px; align-items: flex-start;
    background: var(--surface2); border: 1px solid var(--border2);
    border-radius: 10px; padding: 14px 16px;
  }
  .step-num {
    width: 24px; height: 24px; border-radius: 50%;
    background: rgba(129,140,248,.12); border: 1px solid rgba(129,140,248,.25);
    color: var(--accent2); font-size: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 1px;
  }
  .step-content { flex: 1; }
  .step-content h4 { font-size: 12px; color: var(--text); margin-bottom: 4px; }
  .step-content p  { font-size: 11px; color: var(--muted); line-height: 1.6; }
  .step-content code {
    background: var(--bg); border: 1px solid var(--border);
    border-radius: 5px; padding: 2px 7px;
    color: var(--accent3); font-size: 11px;
  }

  /* ── CODE BLOCK ──────────────────────────────────────── */
  .code-block {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 20px;
  }
  .code-header {
    padding: 8px 14px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
  }
  .code-lang { font-size: 9px; letter-spacing:.14em; text-transform:uppercase; color: var(--muted); }
  .copy-btn {
    background: none; border: 1px solid var(--border);
    color: var(--muted); font-family: 'DM Mono', monospace;
    font-size: 9px; letter-spacing:.1em; text-transform:uppercase;
    padding: 3px 9px; border-radius: 5px; cursor: pointer; transition: all .2s;
  }
  .copy-btn:hover, .copy-btn.copied { color: var(--accent); border-color: rgba(110,231,183,.3); }
  pre { padding: 16px; overflow-x: auto; font-size: 12px; line-height: 1.7; }
  .hl-key { color: var(--accent2); }
  .hl-val { color: var(--accent3); }
  .hl-cmt { color: var(--muted); font-style: italic; }

  /* ── CONFIRM CHECKBOX ────────────────────────────────── */
  .confirm-box {
    display: flex; align-items: flex-start; gap: 12px;
    background: rgba(110,231,183,.04);
    border: 1px solid rgba(110,231,183,.15);
    border-radius: 10px; padding: 14px 16px;
    margin-bottom: 24px; cursor: pointer;
  }
  .confirm-box input[type="checkbox"] {
    width: 16px; height: 16px; flex-shrink: 0;
    accent-color: var(--accent); cursor: pointer; margin-top: 1px;
  }
  .confirm-box label {
    font-size: 12px; color: var(--text); line-height: 1.6;
    cursor: pointer; letter-spacing: 0; text-transform: none; margin: 0;
  }

  /* ── FORM ────────────────────────────────────────────── */
  .form-label {
    display: block; font-size: 9px; letter-spacing:.16em;
    text-transform: uppercase; color: var(--muted); margin-bottom: 7px;
  }
  input[type="text"],
  input[type="password"] {
    width: 100%; background: var(--bg);
    border: 1px solid var(--border); border-radius: 10px;
    color: var(--text); font-family: 'DM Mono', monospace;
    font-size: 13px; padding: 11px 14px; margin-bottom: 18px;
    outline: none; transition: border-color .2s;
  }
  input:focus { border-color: rgba(110,231,183,.35); }

  /* ── BUTTONS ─────────────────────────────────────────── */
  .btn-primary {
    width: 100%; background: var(--accent); color: #07070f;
    border: none; border-radius: 10px;
    font-family: 'DM Mono', monospace; font-size: 13px;
    font-weight: 500; letter-spacing:.06em;
    padding: 13px; cursor: pointer; transition: opacity .2s;
    margin-top: 4px;
  }
  .btn-primary:hover { opacity: .85; }
  .btn-primary:disabled { opacity: .35; cursor: not-allowed; }
  .btn-secondary {
    display: inline-block; width: 100%;
    background: none; border: 1px solid var(--border);
    color: var(--muted); border-radius: 10px;
    font-family: 'DM Mono', monospace; font-size: 12px;
    padding: 11px; cursor: pointer; transition: all .2s;
    text-align: center; text-decoration: none; margin-top: 8px;
  }
  .btn-secondary:hover { color: var(--text); border-color: rgba(255,255,255,.2); }

  /* ── ALERT ───────────────────────────────────────────── */
  .alert {
    border-radius: 10px; padding: 13px 15px;
    font-size: 12px; margin-bottom: 22px; line-height: 1.6;
  }
  .alert.error {
    background: rgba(248,113,113,.07);
    border: 1px solid rgba(248,113,113,.2);
    color: var(--danger);
  }
  .alert.success {
    background: rgba(110,231,183,.07);
    border: 1px solid rgba(110,231,183,.2);
    color: var(--accent);
  }

  /* ── SUCCESS STEPS ───────────────────────────────────── */
  .success-steps { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
  .success-row {
    display: flex; gap: 10px; align-items: center;
    font-size: 12px; color: var(--muted);
  }
  .success-row .ico { color: var(--accent); }

  .divider { height: 1px; background: var(--border); margin: 24px 0; }

  @media (max-width: 560px) {
    .card { padding: 28px 20px; }
    .step-line { width: 32px; }
  }
</style>
</head>
<body>

<?php
$stepNum  = ($step === 'done') ? 3 : (int)$step;
$isDone   = ($step === 'done');
?>

<!-- STEPPER -->
<div class="stepper">
  <div class="step-item">
    <div class="step-circle <?= $stepNum > 0 ? 'done' : 'active' ?>">
      <?= $stepNum > 0 ? '✓' : '1' ?>
    </div>
    <div class="step-label <?= $stepNum === 0 ? 'active' : '' ?>">Veritabanı</div>
  </div>
  <div class="step-line"></div>
  <div class="step-item">
    <div class="step-circle <?= $stepNum > 1 ? 'done' : ($stepNum === 1 ? 'active' : '') ?>">
      <?= $stepNum > 1 ? '✓' : '2' ?>
    </div>
    <div class="step-label <?= $stepNum === 1 ? 'active' : '' ?>">Admin</div>
  </div>
  <div class="step-line"></div>
  <div class="step-item">
    <div class="step-circle <?= $isDone ? 'done' : '' ?>">
      <?= $isDone ? '✓' : '3' ?>
    </div>
    <div class="step-label <?= $isDone ? 'active' : '' ?>">Tamamlandı</div>
  </div>
</div>

<div class="card">
  <div class="logo">BeyTor Stats</div>
  <div class="wizard-title">Kurulum Sihirbazı</div>

  <?php /* ══════════════ ADIM 0: VERİTABANI BİLGİLERİ ══════════════ */ ?>
  <?php if ($step === '0'): ?>

    <?php
      // Domain'den site adını tespit et ve temizle
      $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
      $host     = preg_replace('/^www\./i', '', $host);
      $sitePart = preg_replace('/[^a-z0-9]/i', '', explode('.', $host)[0]);
      $sitePart = strtolower(substr($sitePart, 0, 12));
      $autoDbName  = $sitePart . '_beytorstats';
      $autoDbUser  = $sitePart . '_beytor';
    ?>

    <div class="panel-title">Veritabanı bağlantısı</div>
    <div class="panel-sub">
      Hosting panelinizden oluşturduğunuz veritabanı bilgilerini girin.
      Veritabanı adı ve kullanıcı adı sitenize göre otomatik belirlendi.
    </div>

    <?php if ($error): ?>
      <div class="alert error"><?= $error ?></div>
    <?php endif; ?>

    <!-- PLESK REHBER -->
    <div class="warn-box" style="margin-bottom:8px">
      <div class="warn-icon">🔵</div>
      <div class="warn-text">
        <strong>Plesk kullanıcısıysanız şu adımları izleyin:</strong><br/><br/>
        <strong>1.</strong> Plesk → <code>Veritabanları</code> → <code>Veritabanı Ekle</code><br/>
        <strong>2.</strong> Veritabanı adı kutusuna sadece <code>beytorstats</code> yazın<br/>
        &nbsp;&nbsp;&nbsp;→ Plesk otomatik prefix ekler, tam ad: <code>siteadi_com_beytorstats</code> gibi olacak<br/>
        <strong>3.</strong> Kullanıcı adı kutusuna sadece <code>beytor</code> yazın<br/>
        &nbsp;&nbsp;&nbsp;→ Plesk otomatik prefix ekler, tam ad: <code>siteadi_beytor</code> gibi olacak<br/>
        <strong>4.</strong> Şifrenizi belirleyin ve not alın<br/>
        <strong>5.</strong> <code>Veritabanı Oluştur</code> butonuna tıklayın<br/>
        <strong>6.</strong> Plesk'in oluşturduğu <strong>tam veritabanı adı</strong> ve <strong>tam kullanıcı adını</strong> aşağıya girin
      </div>
    </div>

    <div class="warn-box" style="margin-bottom:24px;border-color:rgba(251,146,60,.2);background:rgba(251,146,60,.04)">
      <div class="warn-icon">🟠</div>
      <div class="warn-text" style="color:#fed7aa">
        <strong>cPanel kullanıcısıysanız:</strong><br/><br/>
        <strong>1.</strong> cPanel → <code>MySQL Databases</code> → <code>Create New Database</code><br/>
        &nbsp;&nbsp;&nbsp;Veritabanı adı: <code>beytorstats</code> → tam ad: <code>kullanici_beytorstats</code> olacak<br/>
        <strong>2.</strong> <code>MySQL Users</code> → <code>Add New User</code><br/>
        &nbsp;&nbsp;&nbsp;Kullanıcı adı: <code>beytor</code> → tam ad: <code>kullanici_beytor</code> olacak<br/>
        <strong>3.</strong> <code>Add User to Database</code> → <code>All Privileges</code> verin<br/>
        <strong>4.</strong> Oluşan <strong>tam adları</strong> aşağıdaki alanlara girin
      </div>
    </div>

    <form method="POST" action="install.php" id="db-form">
      <input type="hidden" name="step" value="db" />

      <div style="display:grid;grid-template-columns:1fr 120px;gap:0 12px">
        <div>
          <label class="form-label">Veritabanı Sunucusu (Host)</label>
          <input type="text" name="db_host" value="localhost" placeholder="localhost" />
        </div>
        <div>
          <label class="form-label">Port</label>
          <input type="text" name="db_port" value="3306" placeholder="3306" />
        </div>
      </div>

      <label class="form-label">Veritabanı Adı</label>
      <input type="text" name="db_name" id="db_name"
             value=""
             placeholder="Plesk'teki tam veritabanı adını girin" required autocomplete="off" />

      <label class="form-label">Kullanıcı Adı</label>
      <input type="text" name="db_user" id="db_user"
             value=""
             placeholder="Plesk'teki tam kullanıcı adını girin" required autocomplete="off" />

      <!-- ŞİFRE -->
      <label class="form-label">Şifre</label>
      <div style="position:relative;margin-bottom:12px">
        <input type="password" name="db_pass" id="db_pass"
               placeholder="Şifre girin veya otomatik üretin"
               required autocomplete="new-password"
               style="margin-bottom:0;padding-right:48px" />
        <button type="button" onclick="togglePass('db_pass','eye1')"
                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--muted);padding:4px"
                title="Göster/Gizle" id="eye1">👁</button>
      </div>
      <div style="display:flex;gap:8px;margin-bottom:18px">
        <button type="button" onclick="generatePass()"
                style="flex:1;background:rgba(129,140,248,.1);border:1px solid rgba(129,140,248,.25);color:var(--accent2);font-family:'DM Mono',monospace;font-size:11px;padding:9px;border-radius:8px;cursor:pointer;transition:all .2s"
                onmouseover="this.style.background='rgba(129,140,248,.18)'"
                onmouseout="this.style.background='rgba(129,140,248,.1)'">
          🎲 Güçlü Şifre Üret
        </button>
        <button type="button" onclick="copyPass()"
                style="background:rgba(110,231,183,.08);border:1px solid rgba(110,231,183,.2);color:var(--accent);font-family:'DM Mono',monospace;font-size:11px;padding:9px 14px;border-radius:8px;cursor:pointer;transition:all .2s"
                id="copy-btn">
          📋 Kopyala
        </button>
      </div>

      <!-- ŞİFRE TEKRAR -->
      <label class="form-label">Şifre Tekrar</label>
      <div style="position:relative;margin-bottom:20px">
        <input type="password" name="db_pass2" id="db_pass2"
               placeholder="Şifreyi tekrar girin"
               required autocomplete="new-password"
               style="margin-bottom:0;padding-right:48px" />
        <button type="button" onclick="togglePass('db_pass2','eye2')"
                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--muted);padding:4px"
                title="Göster/Gizle" id="eye2">👁</button>
      </div>

      <div id="pass-match-msg" style="font-size:11px;margin-bottom:12px;min-height:18px"></div>

      <button type="submit" class="btn-primary" id="submit-btn">Bağlantıyı Test Et ve Devam Et →</button>
    </form>

    <div class="divider"></div>
    <div style="font-size:11px;color:var(--muted);text-align:center;line-height:1.7">
      Önceki kurulumdan kalan veritabanını silmek istiyorsanız<br/>
      hosting panelinizden (Plesk/cPanel) yapabilirsiniz.
    </div>

    <script>
    // Şifre göster/gizle
    function togglePass(inputId, btnId) {
      const inp = document.getElementById(inputId);
      const btn = document.getElementById(btnId);
      if (inp.type === 'password') { inp.type = 'text'; btn.textContent = '🙈'; }
      else { inp.type = 'password'; btn.textContent = '👁'; }
    }

    // Güçlü şifre üret
    function generatePass() {
      const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
      let pass = '';
      for (let i = 0; i < 16; i++) pass += chars[Math.floor(Math.random() * chars.length)];
      document.getElementById('db_pass').value  = pass;
      document.getElementById('db_pass2').value = pass;
      document.getElementById('db_pass').type   = 'text';
      document.getElementById('db_pass2').type  = 'text';
      document.getElementById('eye1').textContent = '🙈';
      document.getElementById('eye2').textContent = '🙈';
      checkMatch();
    }

    // Şifre kopyala
    function copyPass() {
      const val = document.getElementById('db_pass').value;
      if (!val) return;
      navigator.clipboard.writeText(val).then(() => {
        const btn = document.getElementById('copy-btn');
        btn.textContent = '✓ Kopyalandı';
        setTimeout(() => btn.textContent = '📋 Kopyala', 2000);
      });
    }

    // Şifre eşleşme kontrolü
    function checkMatch() {
      const p1  = document.getElementById('db_pass').value;
      const p2  = document.getElementById('db_pass2').value;
      const msg = document.getElementById('pass-match-msg');
      const btn = document.getElementById('submit-btn');
      if (!p2) { msg.textContent = ''; btn.disabled = false; return; }
      if (p1 === p2) {
        msg.style.color = '#6ee7b7'; msg.textContent = '✓ Şifreler eşleşiyor';
        btn.disabled = false;
      } else {
        msg.style.color = '#f87171'; msg.textContent = '✗ Şifreler eşleşmiyor';
        btn.disabled = true;
      }
    }

    // Form submit öncesi kontrol
    document.getElementById('db-form').addEventListener('submit', function(e) {
      const p1 = document.getElementById('db_pass').value;
      const p2 = document.getElementById('db_pass2').value;
      if (p1 !== p2) { e.preventDefault(); checkMatch(); }
    });

    document.getElementById('db_pass').addEventListener('input', checkMatch);
    document.getElementById('db_pass2').addEventListener('input', checkMatch);
    </script>

  <?php /* ══════════════ ADIM 0b: KULLANICI GİRİŞ DEĞİŞTİ ══════════════ */ ?>
  <?php elseif ($step === '0b'): ?>

    <?php $dbExists = $_SESSION['bs_db_exists'] ?? false; ?>

    <?php if ($error): ?>
      <div class="alert error"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($dbExists): ?>

      <div class="panel-title">Veritabanı bulundu ✓</div>
      <div class="panel-sub"><code>beytor_stats</code> mevcut. Kullanıcı bilgilerini girin.</div>

      <div class="info-box" style="margin-bottom:20px">
        ✓ <strong>beytor_stats</strong> sunucuda mevcut, bağlanmaya hazır.
      </div>

      <form method="POST" action="install.php">
        <input type="hidden" name="step" value="db_existing" />
        <label class="form-label">Kullanıcı Adı</label>
        <input type="text" name="db_user" value="beytor_user" required autocomplete="off" />
        <label class="form-label">Şifre</label>
        <input type="password" name="db_pass" placeholder="••••••••" required />
        <button type="submit" class="btn-primary">Devam Et →</button>
      </form>

      <div class="divider"></div>

      <div class="warn-box">
        <div class="warn-icon">🗑️</div>
        <div class="warn-text">
          Sıfırdan kurmak istiyorsanız önce hosting panelinizden <strong>beytor_stats</strong> veritabanını silin, ardından tekrar buraya gelin.
        </div>
      </div>

    <?php else: ?>

      <div class="panel-title">Veritabanı bulunamadı</div>
      <div class="panel-sub">Önce hosting panelinizden veritabanı oluşturun, sonra geri dönün.</div>

      <div class="warn-box">
        <div class="warn-icon">ℹ️</div>
        <div class="warn-text">
          <strong>beytor_stats</strong> veritabanı bulunamadı.<br/>
          Plesk veya cPanel'den oluşturup tekrar deneyin.
        </div>
      </div>

    <?php endif; ?>

    <a href="install.php" class="btn-secondary">← Geri</a>

  <?php /* ══════════════ ADIM 1: ADMİN KULLANICI ══════════════ */ ?>
  <?php elseif ($step === '1'): ?>

    <div class="panel-title">Erişim bilgilerini belirleyin</div>
    <div class="panel-sub">
      İki ayrı koruma katmanı oluşturulacak. Her ikisi için de farklı bilgiler belirleyin.
    </div>

    <?php if ($error): ?>
      <div class="alert error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" action="install.php">
      <input type="hidden" name="step" value="2" />

      <!-- KATMAN 1: BASIC AUTH -->
      <div style="background:rgba(129,140,248,.05);border:1px solid rgba(129,140,248,.15);border-radius:10px;padding:16px 18px;margin-bottom:20px">
        <div style="font-size:9px;letter-spacing:.14em;text-transform:uppercase;color:var(--accent2);margin-bottom:14px">🔒 1. Katman — Tarayıcı Koruması (HTTP Basic Auth)</div>
        <label class="form-label">Kullanıcı Adı</label>
        <input type="text" name="basic_user" placeholder="Tarayıcı giriş kullanıcı adı" required autocomplete="off" />
        <label class="form-label">Şifre</label>
        <input type="password" name="basic_pass" placeholder="Tarayıcı giriş şifresi" required />
      </div>

      <!-- KATMAN 2: DASHBOARD -->
      <div style="background:rgba(110,231,183,.05);border:1px solid rgba(110,231,183,.15);border-radius:10px;padding:16px 18px;margin-bottom:20px">
        <div style="font-size:9px;letter-spacing:.14em;text-transform:uppercase;color:var(--accent);margin-bottom:14px">🛡️ 2. Katman — Dashboard Girişi</div>
        <label class="form-label">Admin Kullanıcı Adı</label>
        <input type="text" name="admin_user" placeholder="Dashboard kullanıcı adı" required autocomplete="off" />
        <label class="form-label">Şifre</label>
        <input type="password" name="admin_pass" placeholder="••••••••" required />
        <label class="form-label">Şifre Tekrar</label>
        <input type="password" name="admin_pass2" placeholder="••••••••" required />
      </div>

      <div class="warn-box" style="margin-bottom:20px">
        <div class="warn-icon">💡</div>
        <div class="warn-text">İki katman için <strong>farklı</strong> kullanıcı adı ve şifre belirlemeniz güvenliği artırır.</div>
      </div>

      <button type="submit" class="btn-primary">Kurulumu Tamamla →</button>
    </form>

    <a href="install.php?step=0" class="btn-secondary">← Geri</a>

  <?php /* ══════════════ ADIM 2: TAMAMLANDI ══════════════ */ ?>
  <?php else: ?>

    <div class="alert success">
      ✓ Kurulum başarıyla tamamlandı! Tablolar oluşturuldu, güvenlik katmanları aktif.
    </div>

    <div class="panel-title">Hazırsınız! 🎉</div>

    <div class="success-steps">
      <div class="success-row"><span class="ico">✓</span><span>Tablolar oluşturuldu.</span></div>
      <div class="success-row"><span class="ico">✓</span><span>Admin kullanıcısı kaydedildi.</span></div>
      <div class="success-row"><span class="ico">✓</span><span><code>.htaccess</code> ile dosya erişim koruması aktif.</span></div>
      <div class="success-row"><span class="ico">✓</span><span><code>.htpasswd</code> ile tarayıcı koruması (Basic Auth) aktif.</span></div>
      <div class="success-row"><span class="ico">✓</span><span><code>install.php</code> güvenlik için silindi.</span></div>
    </div>

    <div class="divider"></div>

    <div class="panel-sub" style="margin-bottom:16px">
      <strong style="color:var(--text)">Son adım:</strong>
      Script enjektörünü açın — hangi sayfalara analytics eklenecek seçin.
    </div>

    <a href="../inject.php" class="btn-primary" style="display:block;text-align:center;text-decoration:none;padding:13px;border-radius:10px;background:var(--accent);color:#07070f;font-size:13px;font-weight:500;margin-bottom:10px">
      Script Enjektörünü Aç →
    </a>
    <a href="dashboard.php" class="btn-primary" style="display:block;text-align:center;text-decoration:none;padding:13px;border-radius:10px;background:none;border:1px solid var(--border);color:var(--muted);font-size:13px;">
      Dashboard'a Git
    </a>

  <?php endif; ?>
</div>

<script>
  // Tab değiştir
  function switchTab(id, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    btn.classList.add('active');
  }

  // Onay checkbox
  function toggleCheck() {
    const cb  = document.getElementById('confirm-check');
    const btn = document.getElementById('next-btn');
    if (!cb || !btn) return;
    cb.checked = !cb.checked;
    btn.disabled = !cb.checked;
  }

  // Kod kopyala
  function copyCode(btn, id) {
    const text = document.getElementById(id).innerText;
    navigator.clipboard.writeText(text).then(() => {
      btn.textContent = '✓ Kopyalandı';
      btn.classList.add('copied');
      setTimeout(() => { btn.textContent = 'Kopyala'; btn.classList.remove('copied'); }, 2000);
    });
  }
</script>
</body>
</html>
