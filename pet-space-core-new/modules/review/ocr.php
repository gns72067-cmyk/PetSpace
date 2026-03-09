<?php
/**
 * PetSpace Review — OCR 처리
 */
defined( 'ABSPATH' ) || exit;

function psc_ocr_extract( string $image_url ): array {
    $provider = defined( 'PSC_OCR_PROVIDER' ) ? PSC_OCR_PROVIDER : 'google';
    return $provider === 'naver'
        ? psc_ocr_naver( $image_url )
        : psc_ocr_google( $image_url );
}

function psc_ocr_google( string $image_url ): array {
    $api_key = defined( 'PSC_GOOGLE_VISION_API_KEY' ) ? PSC_GOOGLE_VISION_API_KEY : '';
    if ( ! $api_key ) {
        return [ 'success' => false, 'message' => 'API 키가 설정되지 않았습니다' ];
    }

    $response = wp_remote_post(
        "https://vision.googleapis.com/v1/images:annotate?key={$api_key}",
        [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => json_encode( [
                'requests' => [ [
                    'image'    => [ 'source' => [ 'imageUri' => $image_url ] ],
                    'features' => [ [ 'type' => 'DOCUMENT_TEXT_DETECTION' ] ],
                ] ],
            ] ),
            'timeout' => 15,
        ]
    );

    if ( is_wp_error( $response ) ) {
        return [ 'success' => false, 'message' => $response->get_error_message() ];
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    $text = $data['responses'][0]['fullTextAnnotation']['text'] ?? '';

    if ( ! $text ) {
        return [ 'success' => false, 'message' => '텍스트를 인식할 수 없습니다' ];
    }

    return [
        'success'  => true,
        'raw_text' => $text,
        'parsed'   => psc_ocr_parse( $text ),
    ];
}

function psc_ocr_naver( string $image_url ): array {
    return [ 'success' => false, 'message' => 'Naver OCR 미구현' ];
}

function psc_ocr_parse( string $text ): array {
    $parsed = [ 'store_name' => '', 'biz_number' => '', 'address' => '', 'date' => '', 'amount' => '' ];
    $lines  = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $text ) ) ) );

    // 사업자번호
    if ( preg_match( '/(\d{3}-\d{2}-\d{5})/', $text, $m ) ) {
        $parsed['biz_number'] = $m[1];
    }

    // 날짜
    if ( preg_match( '/(\d{4}[-\.\/년]\s*\d{1,2}[-\.\/월]\s*\d{1,2})/', $text, $m ) ) {
        $parsed['date'] = rtrim( preg_replace( '/\s+/', '', preg_replace( '/[년월\/]/', '-', $m[1] ) ), '-' );
    }

    // 결제금액
    if ( preg_match(
        '/(?:합\s*계|받을\s*금액|결제\s*금액|총\s*액|합계금액|승인금액|total|amount)[^\d]*(\d[\d,]+)/iu',
        $text, $m
    ) ) {
        $parsed['amount'] = $m[1] . '원';
    }
    if ( empty( $parsed['amount'] ) ) {
        $max_val = 0;
        foreach ( $lines as $line ) {
            if ( preg_match( '/^[\s\*]*(\d{1,3}(?:,\d{3})+)\s*$/', $line, $lm ) ) {
                $val = (int) str_replace( ',', '', $lm[1] );
                if ( $val > $max_val ) { $max_val = $val; $parsed['amount'] = $lm[1] . '원'; }
            }
        }
    }

    // 주소
    foreach ( $lines as $line ) {
        if ( preg_match( '/(?:서울|부산|대구|인천|광주|대전|울산|세종|경기|강원|충[남북]|전[남북]|경[남북]|제주)/', $line )
          && preg_match( '/(?:로|길|동|번지|구|시|군)/', $line ) ) {
            $parsed['address'] = $line;
            break;
        }
    }

    // 매장명
    if ( preg_match( '/(?:상호|업체명|매장명|점포명|가맹점명)\s*[:\：]?\s*(.+)/u', $text, $m ) ) {
        $parsed['store_name'] = trim( $m[1] );
    }
    if ( empty( $parsed['store_name'] ) && ! empty( $parsed['biz_number'] ) ) {
        foreach ( $lines as $i => $line ) {
            if ( str_contains( $line, $parsed['biz_number'] ) ) {
                $before = trim( preg_replace( '/\s*(TEL|tel|전화|FAX|fax)[^\s]*/iu', '',
                    trim( preg_replace( '/' . preg_quote( $parsed['biz_number'], '/' ) . '.*$/u', '', $line ) )
                ) );
                $parsed['store_name'] = mb_strlen( $before ) >= 2 ? $before : ( $lines[ $i - 1 ] ?? '' );
                break;
            }
        }
    }
    if ( empty( $parsed['store_name'] ) ) {
        foreach ( $lines as $line ) {
            if ( mb_strlen( $line ) >= 2 && ! preg_match( '/^\d+$/', $line ) ) {
                $parsed['store_name'] = $line;
                break;
            }
        }
    }

    // 매장명 후처리
    if ( ! empty( $parsed['store_name'] ) ) {
        $parsed['store_name'] = trim( preg_replace(
            '/\s*(TEL|tel|전화|FAX|fax|☎|\d{2,4}-\d{3,4}-\d{4}).*$/iu', '', $parsed['store_name']
        ) );
        foreach ( [ '사업자', '대표', '주소', '전화', 'TEL', 'FAX', '등록번호', '영수증' ] as $prefix ) {
            if ( mb_strpos( $parsed['store_name'], $prefix ) === 0 ) { $parsed['store_name'] = ''; break; }
        }
    }

    return $parsed;
}

function psc_ocr_verify_store( array $parsed, int $store_id ): array {
    $store_name    = get_the_title( $store_id );
    $store_biz     = get_post_meta( $store_id, 'store_biz_number', true );
    $store_address = get_post_meta( $store_id, 'store_address', true );

    $matched = []; $unmatched = []; $score = 0;

    // 사업자번호 검증 (50점)
    if ( $parsed['biz_number'] && $store_biz ) {
        $match = preg_replace( '/[^0-9]/', '', $parsed['biz_number'] ) === preg_replace( '/[^0-9]/', '', $store_biz );
        $match ? ( $matched[] = 'biz_number' ) && ( $score += 50 ) : ( $unmatched[] = 'biz_number' );
    }

    // 매장명 검증 (30점)
    if ( $parsed['store_name'] && $store_name ) {
        similar_text( mb_strtolower( $parsed['store_name'] ), mb_strtolower( $store_name ), $pct );
        $pct >= 80 ? ( $matched[] = 'store_name' ) && ( $score += 30 ) : ( $unmatched[] = 'store_name' );
    }

    // 주소 검증 (20점)
    if ( $parsed['address'] && $store_address ) {
        $addr_matched = false;
        foreach ( preg_split( '/\s+/', $store_address ) as $word ) {
            if ( mb_strlen( $word ) > 1 && str_contains( $parsed['address'], $word ) ) { $addr_matched = true; break; }
        }
        $addr_matched ? ( $matched[] = 'address' ) && ( $score += 20 ) : ( $unmatched[] = 'address' );
    }

    $label_map = [ 'store_name' => '매장명', 'biz_number' => '사업자번호', 'address' => '주소', 'date' => '날짜', 'amount' => '결제금액' ];
    $fields = [];
    foreach ( $label_map as $key => $label ) {
        $fields[] = [
            'label'  => $label,
            'value'  => $parsed[ $key ] ?? '',
            'status' => in_array( $key, $matched, true ) ? 'match'
                      : ( in_array( $key, $unmatched, true ) ? 'miss'
                      : ( ! empty( $parsed[ $key ] ) ? 'ref' : '' ) ),
        ];
    }

    return [
        'verified'  => $score >= 50,
        'score'     => $score,
        'matched'   => $matched,
        'unmatched' => $unmatched,
        'fields'    => $fields,
        'parsed'    => $parsed,
    ];
}
