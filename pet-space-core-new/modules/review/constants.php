<?php
/**
 * PetSpace Review — 상수 정의
 */
defined( 'ABSPATH' ) || exit;

global $wpdb;

define( 'PSC_REVIEW_TABLE',        $wpdb->prefix . 'psc_reviews' );
define( 'PSC_REVIEW_IMAGE_TABLE',  $wpdb->prefix . 'psc_review_images' );
define( 'PSC_REVIEW_LIKE_TABLE',   $wpdb->prefix . 'psc_review_likes' );
define( 'PSC_REVIEW_REPORT_TABLE', $wpdb->prefix . 'psc_review_reports' );

define( 'PSC_REVIEW_MODULE_URL',   PSC_MODULES_URL . 'review/' );
define( 'PSC_REVIEW_MODULE_DIR',   PSC_MODULES_DIR . 'review/' );

// 리뷰 상태
define( 'PSC_REVIEW_STATUS_PENDING',   'pending' );
define( 'PSC_REVIEW_STATUS_PUBLISHED', 'published' );
define( 'PSC_REVIEW_STATUS_HIDDEN',    'hidden' );
define( 'PSC_REVIEW_STATUS_DELETED',   'deleted' );

// 인증 타입
define( 'PSC_VERIFY_RECEIPT', 'receipt' );
define( 'PSC_VERIFY_GPS',     'gps' );
define( 'PSC_VERIFY_BOTH',    'both' );

// 최대 재시도 횟수 (OCR)
define( 'PSC_OCR_MAX_RETRY', 3 );

// 최대 이미지 수
define( 'PSC_REVIEW_MAX_IMAGES', 5 );

// 리뷰 최대 태그 선택 수
define( 'PSC_REVIEW_MAX_TAGS', 3 );
