<?php
/**
 * iSmartBonus Offline Partner QR
 *
 * Creates and verifies signed QR payloads used to identify
 * iSmartBonus users during purchases at physical merchant locations.
 *
 * This flow is intended for offline merchants only.
 * Online marketplaces and affiliate partners use separate
 * purchase attribution and verification mechanisms.
 *
 * Part of the iSmartBonus Colosseum repository.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Use the WordPress authentication salt as the HMAC secret.
 */
function isb_partner_qr_signature_secret() {
    return wp_salt( 'auth' );
}

/**
 * Signed QR payload lifetime.
 */
function isb_partner_qr_token_lifetime() {
    return 48 * HOUR_IN_SECONDS;
}

/**
 * Normalize QR data before signing or verification.
 */
function isb_partner_qr_normalize_payload( $payload ) {

    return array(
        'qrVersion'   => 2,
        'userId'      => isset( $payload['userId'] )
            ? absint( $payload['userId'] )
            : 0,

        'timestamp'   => isset( $payload['timestamp'] )
            ? sanitize_text_field( wp_unslash( $payload['timestamp'] ) )
            : '',

        'uniqueToken' => isset( $payload['uniqueToken'] )
            ? sanitize_text_field( wp_unslash( $payload['uniqueToken'] ) )
            : '',

        'language'    => isset( $payload['language'] )
            ? isb_normalize_email_language( $payload['language'] )
            : '',
    );
}

/**
 * Generate HMAC signature for a QR payload.
 */
function isb_partner_qr_sign_payload( $payload ) {

    $normalized = isb_partner_qr_normalize_payload( $payload );

    return hash_hmac(
        'sha256',
        wp_json_encode( $normalized ),
        isb_partner_qr_signature_secret()
    );
}

/**
 * Create a signed QR payload for a user.
 */
function isb_partner_qr_create_payload(
    $user_id,
    $partner = null,
    $language = ''
) {

    $payload = array(
        'userId'      => absint( $user_id ),
        'timestamp'   => gmdate( 'c' ),
        'uniqueToken' => wp_generate_uuid4(),
        'language'    => $language,
    );

    $payload = isb_partner_qr_normalize_payload( $payload );

    $payload['sig'] = isb_partner_qr_sign_payload( $payload );

    return $payload;
}

/**
 * Verify a signed partner QR payload.
 */
function isb_partner_qr_verify_payload( $payload ) {

    if ( ! is_array( $payload ) ) {
        return new WP_Error(
            'invalid_qr_payload',
            'QR payload must be an object.',
            array( 'status' => 400 )
        );
    }

    $required = array(
        'userId',
        'timestamp',
        'uniqueToken',
        'sig',
    );

    foreach ( $required as $field ) {

        if (
            ! isset( $payload[ $field ] ) ||
            $payload[ $field ] === ''
        ) {
            return new WP_Error(
                'invalid_qr_payload',
                'Missing QR field: ' . $field . '.',
                array( 'status' => 400 )
            );
        }
    }

    $normalized = isb_partner_qr_normalize_payload( $payload );

    if ( $normalized['userId'] <= 0 ) {
        return new WP_Error(
            'invalid_qr_payload',
            'QR user is invalid.',
            array( 'status' => 400 )
        );
    }

    $timestamp = strtotime( $normalized['timestamp'] );

    if ( ! $timestamp ) {
        return new WP_Error(
            'invalid_qr_timestamp',
            'QR timestamp is invalid.',
            array( 'status' => 400 )
        );
    }

    $now      = time();
    $lifetime = isb_partner_qr_token_lifetime();

    // Reject timestamps more than 5 minutes in the future.
    if ( $timestamp > ( $now + 300 ) ) {
        return new WP_Error(
            'invalid_qr_timestamp',
            'QR timestamp is in the future.',
            array( 'status' => 400 )
        );
    }

    // Reject expired QR payloads.
    if ( ( $now - $timestamp ) > $lifetime ) {
        return new WP_Error(
            'expired_qr_token',
            'QR token has expired.',
            array( 'status' => 400 )
        );
    }

    $expected_sig = isb_partner_qr_sign_payload( $normalized );

    $provided_sig = sanitize_text_field(
        wp_unslash( $payload['sig'] )
    );

    if ( ! hash_equals( $expected_sig, $provided_sig ) ) {
        return new WP_Error(
            'invalid_qr_signature',
            'QR signature is invalid.',
            array( 'status' => 403 )
        );
    }

    $normalized['sig'] = $provided_sig;

    return $normalized;
}
