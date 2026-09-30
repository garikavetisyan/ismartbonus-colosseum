<?php
/**
 * iSmartBonus Offline Purchase Engine
 *
 * Processes verified purchases made through physical iSmartBonus
 * merchant partners.
 *
 * The customer is identified through the signed offline partner QR flow.
 * Financial values are calculated server-side from the merchant's
 * configured commission percentage.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calculate distribution for the standard offline performance model.
 *
 * Current model:
 *
 * 25% of merchant commission -> immediate customer discount
 * 25% of merchant commission -> iSmartBonus revenue
 * 50% of merchant commission -> digital asset / bonus allocation
 *
 * Example:
 *
 * Purchase:             100,000 AMD
 * Merchant commission:       10%
 *
 * Total commission:      10,000 AMD
 * Customer discount:      2,500 AMD
 * iSmartBonus revenue:     2,500 AMD
 * Digital asset reserve:   5,000 AMD
 */
function isb_calculate_offline_purchase_distribution(
    $purchase_amount,
    $commission_percentage
) {
    $purchase_amount       = (float) $purchase_amount;
    $commission_percentage = (float) $commission_percentage;

    if ( $purchase_amount <= 0 ) {
        return new WP_Error(
            'invalid_purchase_amount',
            'Purchase amount must be greater than zero.'
        );
    }

    if (
        $commission_percentage <= 0 ||
        $commission_percentage > 100
    ) {
        return new WP_Error(
            'invalid_commission_percentage',
            'Commission percentage must be between 0 and 100.'
        );
    }

    $commission = (
        $purchase_amount * $commission_percentage
    ) / 100;

    $user_discount  = $commission * 0.25;
    $company_profit = $commission * 0.25;
    $for_pay_crypto = $commission * 0.50;

    $customer_paid = $purchase_amount - $user_discount;

    return array(
        'purchase_amount'       => round( $purchase_amount, 2 ),
        'commission_percentage' => round( $commission_percentage, 4 ),
        'commission'            => round( $commission, 2 ),
        'customer_paid'         => round( $customer_paid, 2 ),
        'user_discount'         => round( $user_discount, 2 ),
        'company_profit'        => round( $company_profit, 2 ),
        'for_pay_crypto'        => round( $for_pay_crypto, 2 ),
    );
}


/**
 * Process a verified offline purchase.
 *
 * Important:
 * - Partner commission is retrieved server-side.
 * - QR payload must pass signature verification.
 * - Duplicate QR/order tokens are rejected.
 */
function isb_process_offline_purchase( array $purchase ) {

    global $wpdb;

    $user_id     = isset( $purchase['user_id'] )
        ? absint( $purchase['user_id'] )
        : 0;

    $partner_id  = isset( $purchase['partner_id'] )
        ? absint( $purchase['partner_id'] )
        : 0;

    $amount      = isset( $purchase['amount'] )
        ? (float) $purchase['amount']
        : 0;

    $return_days = isset( $purchase['return_days'] )
        ? absint( $purchase['return_days'] )
        : 0;

    if ( ! $user_id || ! get_userdata( $user_id ) ) {
        return new WP_Error(
            'invalid_user',
            'Customer could not be identified.'
        );
    }

    $partner = get_userdata( $partner_id );

    if ( ! $partner ) {
        return new WP_Error(
            'invalid_partner',
            'Merchant partner could not be identified.'
        );
    }

    /*
     * Commission percentage comes from the merchant configuration.
     * The client cannot define the financial distribution.
     */
    $commission_percentage =
        isb_partner_get_discount_percentage( $partner_id );

    if ( is_wp_error( $commission_percentage ) ) {
        return $commission_percentage;
    }

    /*
     * Verify signed QR payload.
     */
    $qr_payload = isset( $purchase['qr'] ) &&
                  is_array( $purchase['qr'] )
        ? $purchase['qr']
        : array();

    $verified_qr = isb_partner_qr_verify_payload(
        $qr_payload
    );

    if ( is_wp_error( $verified_qr ) ) {
        return $verified_qr;
    }

    /*
     * QR must belong to the customer whose purchase
     * is being processed.
     */
    if (
        (int) $verified_qr['userId'] !==
        (int) $user_id
    ) {
        return new WP_Error(
            'qr_user_mismatch',
            'QR user does not match purchase user.'
        );
    }

    /*
     * Create an order token tied to the customer,
     * merchant and signed QR token.
     */
    $unique_token =
        $user_id .
        $partner_id .
        $verified_qr['uniqueToken'];

    /*
     * Prevent the same QR purchase from being
     * processed more than once.
     */
    $duplicate = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}user_orders
             WHERE unique_token = %s",
            $unique_token
        )
    );

    if ( $duplicate > 0 ) {
        return new WP_Error(
            'duplicate_purchase',
            'This purchase has already been processed.'
        );
    }

    /*
     * Calculate the iSmartBonus distribution.
     */
    $distribution =
        isb_calculate_offline_purchase_distribution(
            $amount,
            $commission_percentage
        );

    if ( is_wp_error( $distribution ) ) {
        return $distribution;
    }

    $return_possible =
        $return_days > 0 ? 'yes' : 'no';

    /*
     * Store verified purchase.
     */
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'user_orders',
        array(
            'user_id'         => $user_id,
            'partner'         => $partner->user_login,
            'sum'             => $distribution['purchase_amount'],
            'paid'            => $distribution['customer_paid'],
            'discount'        => $distribution['commission'],
            'created_at'      => current_time( 'mysql' ),
            'partner_id'      => $partner_id,
            'return_days'     => $return_days,
            'return_possible' => $return_possible,
            'user_discount'   => $distribution['user_discount'],
            'company_profit'  => $distribution['company_profit'],
            'for_pay_crypto'  => $distribution['for_pay_crypto'],
            'unique_token'    => $unique_token,
        ),
        array(
            '%d',
            '%s',
            '%f',
            '%f',
            '%f',
            '%s',
            '%d',
            '%d',
            '%s',
            '%f',
            '%f',
            '%f',
            '%s',
        )
    );

    if ( ! $inserted ) {
        return new WP_Error(
            'purchase_storage_error',
            'Purchase could not be stored.'
        );
    }

    return array(
        'success'      => true,
        'order_id'     => $wpdb->insert_id,
        'unique_token' => $unique_token,
        'distribution' => $distribution,
    );
}
