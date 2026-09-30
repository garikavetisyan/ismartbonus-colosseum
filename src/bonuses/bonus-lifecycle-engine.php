<?php
/**
 * iSmartBonus Bonus Lifecycle Engine
 *
 * Calculates bonus availability based on the age of a confirmed
 * transaction.
 *
 * Current lifecycle implemented by the platform:
 *
 * Days 0-14:
 *   Bonus is locked.
 *
 * Days 15-364:
 *   Available amount increases linearly over a 365-day period.
 *
 * Day 365+:
 *   Full bonus amount is available.
 *
 * This module contains lifecycle calculation only.
 * Payment execution and blockchain operations are handled separately.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Minimum age before a bonus can become available.
 */
function isb_bonus_minimum_unlock_days() {
    return 15;
}


/**
 * Full bonus vesting period.
 */
function isb_bonus_full_unlock_days() {
    return 365;
}


/**
 * Calculate completed days since the transaction became eligible.
 */
function isb_bonus_days_passed( $paid_at, $current_timestamp = null ) {

    $paid_timestamp = strtotime( $paid_at );

    if ( ! $paid_timestamp ) {
        return new WP_Error(
            'invalid_paid_at',
            'Transaction date is invalid.'
        );
    }

    if ( $current_timestamp === null ) {
        $current_timestamp = time();
    }

    $current_timestamp = (int) $current_timestamp;

    if ( $current_timestamp < $paid_timestamp ) {
        return new WP_Error(
            'invalid_bonus_date',
            'Transaction date cannot be in the future.'
        );
    }

    return (int) floor(
        ( $current_timestamp - $paid_timestamp )
        / DAY_IN_SECONDS
    );
}


/**
 * Calculate the currently available bonus amount.
 *
 * Formula used by the existing iSmartBonus lifecycle:
 *
 * available =
 *     total_bonus / 365 * days_passed
 *
 * during the progressive unlock period.
 */
function isb_calculate_available_bonus(
    $total_bonus,
    $paid_at,
    $current_timestamp = null
) {

    $total_bonus = (float) $total_bonus;

    if ( $total_bonus <= 0 ) {
        return new WP_Error(
            'invalid_bonus_amount',
            'Bonus amount must be greater than zero.'
        );
    }

    $days_passed = isb_bonus_days_passed(
        $paid_at,
        $current_timestamp
    );

    if ( is_wp_error( $days_passed ) ) {
        return $days_passed;
    }

    $minimum_days = isb_bonus_minimum_unlock_days();
    $full_days    = isb_bonus_full_unlock_days();


    /*
     * Initial lock period.
     */
    if ( $days_passed < $minimum_days ) {

        return array(
            'status'           => 'locked',
            'days_passed'      => $days_passed,
            'total_bonus'      => round( $total_bonus, 8 ),
            'available_bonus'  => 0,
            'locked_bonus'     => round( $total_bonus, 8 ),
            'unlock_progress'  => 0,
        );
    }


    /*
     * Full unlock after 365 days.
     */
    if ( $days_passed >= $full_days ) {

        return array(
            'status'           => 'fully_unlocked',
            'days_passed'      => $days_passed,
            'total_bonus'      => round( $total_bonus, 8 ),
            'available_bonus'  => round( $total_bonus, 8 ),
            'locked_bonus'     => 0,
            'unlock_progress'  => 1,
        );
    }


    /*
     * Progressive unlock.
     *
     * This reproduces the current production formula:
     *
     * total_bonus / 365 * days_passed
     */
    $available_bonus =
        ( $total_bonus / $full_days )
        * $days_passed;

    $available_bonus = min(
        $available_bonus,
        $total_bonus
    );

    $locked_bonus =
        $total_bonus - $available_bonus;

    return array(
        'status'           => 'partially_unlocked',
        'days_passed'      => $days_passed,
        'total_bonus'      => round( $total_bonus, 8 ),
        'available_bonus'  => round( $available_bonus, 8 ),
        'locked_bonus'     => round( $locked_bonus, 8 ),
        'unlock_progress'  => round(
            $available_bonus / $total_bonus,
            8
        ),
    );
}


/**
 * Validate a requested early withdrawal amount.
 */
function isb_validate_bonus_withdrawal(
    $total_bonus,
    $requested_amount,
    $paid_at,
    $already_withdrawn = false,
    $current_timestamp = null
) {

    $requested_amount = (float) $requested_amount;

    if ( $already_withdrawn ) {
        return new WP_Error(
            'bonus_already_withdrawn',
            'A withdrawal has already been made for this transaction.'
        );
    }

    if ( $requested_amount <= 0 ) {
        return new WP_Error(
            'invalid_withdrawal_amount',
            'Withdrawal amount must be greater than zero.'
        );
    }

    $lifecycle = isb_calculate_available_bonus(
        $total_bonus,
        $paid_at,
        $current_timestamp
    );

    if ( is_wp_error( $lifecycle ) ) {
        return $lifecycle;
    }


    /*
     * Nothing can be withdrawn during the initial lock period.
     */
    if ( $lifecycle['status'] === 'locked' ) {
        return new WP_Error(
            'bonus_locked',
            'The bonus is still inside its initial lock period.'
        );
    }


    /*
     * Before full vesting, withdrawal cannot exceed
     * the progressively unlocked amount.
     */
    if (
        $lifecycle['status'] === 'partially_unlocked' &&
        $requested_amount > $lifecycle['available_bonus']
    ) {
        return new WP_Error(
            'withdrawal_exceeds_available_bonus',
            'Requested amount exceeds the currently available bonus.'
        );
    }


    /*
     * The current production flow expects the complete amount
     * when the 365-day period has finished.
     */
    if (
        $lifecycle['status'] === 'fully_unlocked' &&
        abs( $requested_amount - (float) $total_bonus ) > 0.00000001
    ) {
        return new WP_Error(
            'full_withdrawal_required',
            'After full unlock, the withdrawal amount must equal the full bonus amount.'
        );
    }


    return array(
        'allowed'          => true,
        'requested_amount' => round( $requested_amount, 8 ),
        'lifecycle'        => $lifecycle,
    );
}
