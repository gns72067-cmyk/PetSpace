<?php
/**
 * PetSpace Review — DB 테이블 생성
 */
defined( 'ABSPATH' ) || exit;

function psc_review_create_tables(): void {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $sql_reviews = "CREATE TABLE IF NOT EXISTS " . PSC_REVIEW_TABLE . " (
        id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        store_id          BIGINT UNSIGNED NOT NULL,
        user_id           BIGINT UNSIGNED NOT NULL,
        rating            TINYINT(1) NOT NULL,
        content           TEXT NOT NULL,
        tags              VARCHAR(500) DEFAULT NULL,
        visited_at        DATETIME DEFAULT NULL,
        status            ENUM('pending','published','hidden','deleted') NOT NULL DEFAULT 'pending',
        likes             INT UNSIGNED NOT NULL DEFAULT 0,
        verify_type       ENUM('receipt','gps') DEFAULT NULL,
        receipt_verified  TINYINT(1) NOT NULL DEFAULT 0,
        receipt_image_id  BIGINT UNSIGNED DEFAULT NULL,
        gps_lat           DECIMAL(10,7) DEFAULT NULL,
        gps_lng           DECIMAL(10,7) DEFAULT NULL,
        report_count      TINYINT UNSIGNED NOT NULL DEFAULT 0,
        report_status     ENUM('none','reported','reviewed') NOT NULL DEFAULT 'none',
        created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_store_id  (store_id),
        KEY idx_user_id   (user_id),
        KEY idx_status    (status),
        KEY idx_likes     (likes),
        KEY idx_visited   (visited_at)
    ) $charset;";

    $sql_images = "CREATE TABLE IF NOT EXISTS " . PSC_REVIEW_IMAGE_TABLE . " (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        review_id       BIGINT UNSIGNED NOT NULL,
        attachment_id   BIGINT UNSIGNED DEFAULT NULL,
        url             VARCHAR(500) NOT NULL,
        sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_review_id (review_id)
    ) $charset;";

    $sql_likes = "CREATE TABLE IF NOT EXISTS " . PSC_REVIEW_LIKE_TABLE . " (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        review_id       BIGINT UNSIGNED NOT NULL,
        user_id         BIGINT UNSIGNED NOT NULL,
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_review_user (review_id, user_id)
    ) $charset;";

    $sql_reports = "CREATE TABLE IF NOT EXISTS " . PSC_REVIEW_REPORT_TABLE . " (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        review_id       BIGINT UNSIGNED NOT NULL,
        reporter_id     BIGINT UNSIGNED NOT NULL,
        store_id        BIGINT UNSIGNED NOT NULL,
        reason          VARCHAR(500) NOT NULL,
        status          ENUM('pending','resolved') NOT NULL DEFAULT 'pending',
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_review_id (review_id),
        KEY idx_status    (status)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_reviews );
    dbDelta( $sql_images );
    dbDelta( $sql_likes );
    dbDelta( $sql_reports );

    // visited_at 컬럼 없으면 추가 (기존 DB 마이그레이션)
    $columns = $wpdb->get_results(
        "SHOW COLUMNS FROM " . PSC_REVIEW_TABLE . " LIKE 'visited_at'"
    );
    if ( empty( $columns ) ) {
        $wpdb->query(
            "ALTER TABLE " . PSC_REVIEW_TABLE .
            " ADD COLUMN visited_at DATETIME DEFAULT NULL AFTER tags"
        );
    }
}
