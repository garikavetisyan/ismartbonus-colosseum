<?php
/**
 * iSmartBonus Withdrawal Engine
 *
 * Handles withdrawal requests for bonus value that has already
 * become eligible through the bonus lifecycle.
 *
 * Supported flows:
 *
 * 1. On-chain withdrawal
 * 2. Cash pickup
 *
 * The engine does NOT calculate bonus eligibility.
 * Eligibility is handled separately by the bonus lifecycle engine.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Supported withdrawal methods.
 */
function isb_get_withdrawal_methods() {

    return array(
        'onchain',
        'pickup',
    );
}


/**
 * Validate withdrawal method.
 */
function isb_validate_withdrawal_method( $method ) {

    $method = sanitize_text_field( $method );

    if ( ! in_array(
        $method,
        isb_get_withdrawal_methods(),
        true
    ) ) {

        return new WP_Error(
            'invalid_withdrawal_method',
            'Unsupported withdrawal method.'
        );
    }

    return $method;
}


/**
 * Get orders that contain value prepared for withdrawal.
 */
function isb_get_withdrawable_orders( $user_id ) {

    global $wpdb;

    $user_id = absint( $user_id );

    if ( ! $user_id ) {
        return new WP_Error(
            'invalid_user',
            'Invalid user ID.'
        );
    }

    $table = $wpdb->prefix . 'user_orders';

    return $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT
                id,
                withdraw_usd_total,
                withdraw_status
            FROM {$table}
            WHERE user_id = %d
              AND withdraw_usd_total IS NOT NULL
              AND (
                    withdraw_status IS NULL
                    OR withdraw_status = 'rejected'
                  )
            ",
            $user_id
        )
    );
}


/**
 * Calculate total value currently prepared for withdrawal.
 */
function isb_get_withdrawable_total( $user_id ) {

    global $wpdb;

    $user_id = absint( $user_id );

    if ( ! $user_id ) {
        return 0;
    }

    $table = $wpdb->prefix . 'user_orders';

    $total = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT SUM(withdraw_usd_total)
            FROM {$table}
            WHERE user_id = %d
              AND withdraw_usd_total IS NOT NULL
              AND (
                    withdraw_status IS NULL
                    OR withdraw_status = 'rejected'
                  )
            ",
            $user_id
        )
    );

    return round( (float) $total, 2 );
}


/**
 * Protect withdrawal endpoint against rapid repeated requests.
 */
function isb_check_withdrawal_rate_limit(
    $user_id,
    $minimum_seconds = 10
) {

    global $wpdb;

    $table = $wpdb->prefix . 'user_orders';

    $last_request = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT UNIX_TIMESTAMP(
                MAX(withdraw_requested_at)
            )
            FROM {$table}
            WHERE user_id = %d
              AND withdraw_requested_at IS NOT NULL
            ",
            $user_id
        )
    );

    if (
        $last_request &&
        ( time() - (int) $last_request ) < $minimum_seconds
    ) {

        return new WP_Error(
            'withdrawal_rate_limit',
            'Please wait before submitting another withdrawal request.'
        );
    }

    return true;
}


/**
 * Prepare on-chain withdrawal details.
 *
 * This records the destination requested by the user.
 * Actual blockchain settlement is handled separately.
 */
function isb_prepare_onchain_withdrawal(
    $address,
    $network
) {

    $address = sanitize_text_field( $address );
    $network = sanitize_text_field( $network );

    if ( strlen( $address ) < 5 ) {

        return new WP_Error(
            'invalid_wallet_address',
            'Invalid wallet address.'
        );
    }

    if ( empty( $network ) ) {

        return new WP_Error(
            'missing_network',
            'Blockchain network is required.'
        );
    }

    return array(
        'network' => $network,
        'address' => $address,
    );
}


/**
 * Prepare cash pickup withdrawal.
 *
 * Cash pickup requests receive:
 *
 * - pickup point ID
 * - six-digit PIN
 * - cryptographically random verification token
 */
function isb_prepare_pickup_withdrawal( $point_id ) {

    $point_id = absint( $point_id );

    if ( ! $point_id ) {

        return new WP_Error(
            'invalid_pickup_point',
            'Pickup point is required.'
        );
    }

    return array(
        'details' => array(
            'point' => $point_id,
        ),

        'pin_code' => random_int(
            100000,
            999999
        ),

        'verification_token' =>
            bin2hex( random_bytes( 16 ) ),
    );
}


/**
 * Create withdrawal request.
 */
function isb_create_withdrawal_request(
    $user_id,
    $method,
    $params = array()
) {

    global $wpdb;

    $user_id = absint( $user_id );

    if ( ! $user_id ) {

        return new WP_Error(
            'invalid_user',
            'Invalid user.'
        );
    }


    /*
     * Validate withdrawal method.
     */
    $method = isb_validate_withdrawal_method(
        $method
    );

    if ( is_wp_error( $method ) ) {
        return $method;
    }


    /*
     * Rate-limit withdrawal requests.
     */
    $rate_limit =
        isb_check_withdrawal_rate_limit(
            $user_id
        );

    if ( is_wp_error( $rate_limit ) ) {
        return $rate_limit;
    }


    /*
     * Find eligible withdrawal rows.
     */
    $orders =
        isb_get_withdrawable_orders(
            $user_id
        );

    if ( is_wp_error( $orders ) ) {
        return $orders;
    }

    if ( empty( $orders ) ) {

        return new WP_Error(
            'nothing_to_withdraw',
            'No eligible positions are available for withdrawal.'
        );
    }


    $details = array();

    $verification_code  = null;
    $verification_token = null;


    /*
     * ON-CHAIN WITHDRAWAL
     */
    if ( $method === 'onchain' ) {

        $address =
            isset( $params['address'] )
                ? $params['address']
                : '';

        $network =
            isset( $params['network'] )
                ? $params['network']
                : '';

        $onchain =
            isb_prepare_onchain_withdrawal(
                $address,
                $network
            );

        if ( is_wp_error( $onchain ) ) {
            return $onchain;
        }

        $details = $onchain;
    }


    /*
     * CASH PICKUP
     */
    if ( $method === 'pickup' ) {

        $point_id =
            isset( $params['point'] )
                ? $params['point']
                : 0;

        $pickup =
            isb_prepare_pickup_withdrawal(
                $point_id
            );

        if ( is_wp_error( $pickup ) ) {
            return $pickup;
        }

        $details =
            $pickup['details'];

        $verification_code =
            $pickup['pin_code'];

        $verification_token =
            $pickup['verification_token'];
    }


    /*
     * Extract order IDs.
     */
    $ids = array_map(
        'intval',
        wp_list_pluck(
            $orders,
            'id'
        )
    );

    if ( empty( $ids ) ) {

        return new WP_Error(
            'withdrawal_orders_missing',
            'Withdrawal positions could not be resolved.'
        );
    }


    $table =
        $wpdb->prefix . 'user_orders';

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count( $ids ),
                '%d'
            )
        );


    /*
     * Base update.
     */
    $sql = "
        UPDATE {$table}
        SET
            withdraw_status = 'pending',
            withdraw_method = %s,
            withdraw_requested_at = %s,
            withdraw_details = %s
    ";

    $arguments = array(
        $method,
        current_time( 'mysql' ),
        wp_json_encode( $details ),
    );


    /*
     * Pickup requires additional verification fields.
     */
    if ( $method === 'pickup' ) {

        $sql .= ",
            withdraw_verification_code = %s,
            withdraw_verification_token = %s
        ";

        $arguments[] =
            (string) $verification_code;

        $arguments[] =
            $verification_token;
    }


    $sql .= "
        WHERE id IN ({$placeholders})
    ";


    foreach ( $ids as $id ) {
        $arguments[] = $id;
    }


    $prepared_sql =
        $wpdb->prepare(
            $sql,
            $arguments
        );


    $affected =
        $wpdb->query(
            $prepared_sql
        );


    if ( $affected === false ) {

        return new WP_Error(
            'withdrawal_database_error',
            'Unable to create withdrawal request.'
        );
    }


    return array(

        'success' => true,

        'status' => 'pending',

        'method' => $method,

        'orders' => $ids,

        'withdrawable_total' =>
            isb_get_withdrawable_total(
                $user_id
            ),

        'pickup_verification' =>
            $method === 'pickup'
                ? array(
                    'pin_code' =>
                        $verification_code,

                    'verification_token' =>
                        $verification_token,
                )
                : null,
    );
}


/**
 * REST endpoint
 *
 * POST:
 *
 * /wp-json/ismartbonus/v1/withdraw
 */
add_action(
    'rest_api_init',
    function () {

        register_rest_route(
            'ismartbonus/v1',
            '/withdraw',
            array(

                'methods' =>
                    WP_REST_Server::CREATABLE,

                'permission_callback' =>
                    function () {
                        return is_user_logged_in();
                    },

                'callback' =>
                    function (
                        WP_REST_Request $request
                    ) {

                        $user_id =
                            get_current_user_id();

                        $params =
                            $request->get_json_params();

                        $method =
                            isset( $params['method'] )
                                ? sanitize_text_field(
                                    $params['method']
                                )
                                : '';

                        $result =
                            isb_create_withdrawal_request(
                                $user_id,
                                $method,
                                $params
                            );

                        if (
                            is_wp_error(
                                $result
                            )
                        ) {

                            return new WP_REST_Response(
                                array(
                                    'success' => false,
                                    'code' =>
                                        $result->get_error_code(),
                                    'message' =>
                                        $result->get_error_message(),
                                ),
                                400
                            );
                        }


                        return rest_ensure_response(
                            $result
                        );
                    },
            )
        );
    }
);
