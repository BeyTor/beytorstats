-- ╔══════════════════════════════════════════════════════╗
-- ║         SiteStats — Veritabanı Kurulum SQL           ║
-- ║  Bu dosyayı install.php otomatik çalıştırır.         ║
-- ║  Elle çalıştırmak için phpMyAdmin veya MySQL CLI     ║
-- ║  kullanabilirsiniz.                                  ║
-- ╚══════════════════════════════════════════════════════╝

SET NAMES utf8mb4;
SET time_zone = '+03:00';

-- ─── Ziyaretçiler Tablosu ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `visitors` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `visitor_token`     CHAR(64)        NOT NULL UNIQUE,
    `ip_hash`           CHAR(64)        NOT NULL,
    `user_agent`        VARCHAR(255)    DEFAULT NULL,
    `first_visit`       DATETIME        NOT NULL,
    `last_seen`         DATETIME        NOT NULL,
    `is_unique_counted` TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    INDEX `idx_first_visit` (`first_visit`),
    INDEX `idx_last_seen`   (`last_seen`),
    INDEX `idx_ip_hash`     (`ip_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Sayfa Görüntüleme Tablosu ─────────────────────────────
CREATE TABLE IF NOT EXISTS `pageviews` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Dış Link Tıklamaları Tablosu ─────────────────────────
CREATE TABLE IF NOT EXISTS `link_clicks` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `visitor_token` CHAR(64)        NOT NULL,
    `url`           VARCHAR(1024)   NOT NULL,
    `link_text`     VARCHAR(255)    DEFAULT NULL,
    `source_page`   VARCHAR(512)    DEFAULT NULL,
    `clicked_at`    DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_clicked_at` (`clicked_at`),
    INDEX `idx_url`        (`url`(128))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Admin Kullanıcı Tablosu ───────────────────────────────
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `username`     VARCHAR(64)     NOT NULL UNIQUE,
    `password`     VARCHAR(255)    NOT NULL,
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
