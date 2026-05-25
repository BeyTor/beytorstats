<?php
/**
 * SiteStats — Veritabanı Bağlantısı
 * ─────────────────────────────────
 * Kurulum için sadece bu dosyadaki bilgileri doldurun.
 */

define('DB_HOST', 'localhost');      // Genellikle localhost
define('DB_PORT', '3306');           // MySQL varsayılan portu
define('DB_NAME', 'sitestats_db');   // Veritabanı adı
define('DB_USER', 'root');           // Kullanıcı adı
define('DB_PASS', 'sifreniz');       // Şifre
define('DB_CHARSET', 'utf8mb4');

// ─── Bağlantı ───────────────────────────────────────────────
try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        ),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı bağlantısı kurulamadı.',
        'error'   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
