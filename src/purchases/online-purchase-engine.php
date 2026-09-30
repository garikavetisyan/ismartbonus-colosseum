<?php
/**
 * iSmartBonus Online Purchase Engine
 *
 * Processes confirmed commissions from online / affiliate partners.
 *
 * Online purchases do NOT use the physical merchant QR flow.
 * Purchase attribution and commission confirmation are handled
 * separately by the relevant online partner / affiliate integration.
 *
 * Standard online distribution:
 *
 * 30% of confirmed commission -> iSmartBonus revenue
 * 70% of confirmed commission -> customer bonus / digital asset allocation
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calculate distribution of a confirmed online partner commission.
 *
 * Example:
 *
 * Confirmed partner commission: $10
 *
 * iSmartBonus revenue:           $3
 * Customer bonus allocation:     $7
 *
 * The customer does not receive an immediate checkout discount
 * in the standard online affiliate model.
 */
function isb_calculate_online_purchase_distribution(
    $purchase_amount,
    $confirmed_commission
) {
    $purchase_amount       = (float) $purchase_amount;
    $confirmed_commission  = (float) $confirmed_commission;

    if ( $purchase_amount < 0 ) {
        return new WP_Error(
            'invalid_purchase_amount',
            'Purchase amount cannot be negative.'
        );
    }

    if ( $confirmed_commission <= 0 ) {
        return new WP_Error(
            'invalid_commission',
            'Confirmed partner commission must be greater than zero.'
        );
    }

    $user_discount  = 0;
    $company_profit = $confirmed_commission * 0.30;
    $for_pay_crypto = $confirmed_commission * 0.70;

    return array(
        'purchase_amount'      => round( $purchase_amount, 2 ),
        'confirmed_commission' => round( $confirmed_commission, 2 ),
        'user_discount'        => 0,
        'company_profit'       => round( $company_profit, 2 ),
        'for_pay_crypto'       => round( $for_pay_crypto, 2 ),
    );
}


/**
 * Process a confirmed online purchase / affiliate commission.
 *
 * Expected input comes from a separate partner integration after
 * attribution and commission confirmation.
 *
 * This function does not trust or implement a specific Amazon,
 * CJ, Booking, or other affiliate API. Those integrations should
 * normalize their confirmed transaction data before calling this layer.
 */
function isb_process_online_purchase( array $purchase ) {

    global $wpdb;

    $user_id = isset( $purchase['user_id'] )
        ? absint( $purchase['user_id'] )
        : 0;

    $partner_id = isset( $purchase['partner_id'] )
        ? absint( $purchase['partner_id'] )
        : 0;

    $purchase_amount = isset( $purchase['purchase_amount'] )
        ? (float) $purchase['purchase_amount']
        : 0;

    $confirmed_commission = isset( $purchase['confirmed_commission'] )
        ? (float) $purchase['confirmed_commission']
        : 0;

    $external_transaction_id =
        isset( $purchase['external_transaction_id'] )
            ? sanitize_text_field(
                wp_unslash(
                    $purchase['external_transaction_id']
                )
            )
            : '';

    $return_days = isset( $purchase['return_days'] )
        ? absint( $purchase['return_days'] )
        : 0;


    /*
     * Validate customer.
     */
    if ( ! $user_id || ! get_userdata( $user_id ) ) {
        return new WP_Error(
            'invalid_user',
            'Customer could not be identified.'
        );
    }


    /*
     * Validate online partner.
     */
    $partner = get_userdata( $partner_id );

    if ( ! $partner ) {
        return new WP_Error(
            'invalid_partner',
            'Online partner could not be identified.'
        );
    }


    /*
     * Every imported online transaction must have a unique
     * external reference supplied by the attribution layer.
     */
    if ( $external_transaction_id === '' ) {
        return new WP_Error(
            'missing_transaction_id',
            'External transaction ID is required.'
        );
    }


    /*
     * Build an internal idempotency key.
     *
     * This prevents the same affiliate transaction from
     * creating bonuses more than once.
     */
    $unique_token = hash(
        'sha256',
        'online|' .
        $partner_id . '|' .
        $user_id . '|' .
        $external_transaction_id
    );


    /*
     * Duplicate protection.
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
            'This online transaction has already been processed.'
        );
    }


    /*
     * Apply the standard online 30/70 distribution.
     */
    $distribution =
        isb_calculate_online_purchase_distribution(
            $purchase_amount,
            $confirmed_commission
        );

    if ( is_wp_error( $distribution ) ) {
        return $distribution;
    }


    /*
     * Online affiliate purchases do not apply an immediate
     * iSmartBonus discount at checkout.
     */
    $paid = $purchase_amount;

    $return_possible =
        $return_days > 0 ? 'yes' : 'no';


    /*
     * Store the normalized online transaction in the same
     * order ledger used by the iSmartBonus bonus system.
     */
    $inserted = $wpdb->insert(
        $wpdb->prefix . 'user_orders',
        array(
            'user_id'         => $user_id,
            'partner'         => $partner->user_login,
            'sum'             => $distribution['purchase_amount'],
            'paid'            => $paid,

            /*
             * For online transactions "discount" represents
             * the confirmed partner commission available for
             * distribution, not an immediate checkout discount.
             */
            'discount'        =>
                $distribution['confirmed_commission'],

            'created_at'      => current_time( 'mysql' ),
            'partner_id'      => $partner_id,
            'return_days'     => $return_days,
            'return_possible' => $return_possible,

            'user_discount'   =>
                $distribution['user_discount'],

            'company_profit'  =>
                $distribution['company_profit'],

            'for_pay_crypto'  =>
                $distribution['for_pay_crypto'],

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
            'Online purchase could not be stored.'
        );
    }


    return array(
        'success'                 => true,
        'order_id'                => $wpdb->insert_id,
        'external_transaction_id' => $external_transaction_id,
        'unique_token'            => $unique_token,
        'distribution'            => $distribution,
    );
}
