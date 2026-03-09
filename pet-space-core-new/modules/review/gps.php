<?php
/**
 * PetSpace Review — GPS 검증
 */
defined( 'ABSPATH' ) || exit;

function psc_gps_distance( float $lat1, float $lng1, float $lat2, float $lng2 ): float {
    $r    = 6371000;
    $dlat = deg2rad( $lat2 - $lat1 );
    $dlng = deg2rad( $lng2 - $lng1 );
    $a    = sin( $dlat / 2 ) ** 2
          + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $dlng / 2 ) ** 2;
    return $r * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
}

function psc_gps_verify_store( float $user_lat, float $user_lng, int $store_id ): array {
    $gps    = psc_get_store_gps( $store_id );
    $radius = psc_get_store_gps_radius( $store_id );

    if ( ! $gps['lat'] || ! $gps['lng'] ) {
        return [
            'verified' => false,
            'distance' => 0,
            'radius'   => $radius,
            'message'  => '매장 위치 정보가 설정되지 않았습니다',
        ];
    }

    $distance = psc_gps_distance( $user_lat, $user_lng, $gps['lat'], $gps['lng'] );
    $verified = $distance <= $radius;

    return [
        'verified' => $verified,
        'distance' => round( $distance ),
        'radius'   => $radius,
        'message'  => $verified
            ? "✅ 매장 반경 {$radius}m 이내 확인 완료"
            : "매장에서 " . round( $distance ) . "m 떨어져 있습니다 (허용 반경: {$radius}m)",
    ];
}
