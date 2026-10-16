<?php
/**
 * Rebuild webhook delivery (spec §10.1): an empty POST to GN_BUILD_HOOK_URL (the Workers Builds
 * deploy hook), debounced to one call per 60 seconds, retried with backoff, last 50 logged.
 *
 * How the debounce works:
 * - Every change (triggers.php) is added to a queue, and one `gn_rebuild_dispatch` cron event is
 *   scheduled: GN_REBUILD_SETTLE seconds from now, but never sooner than 60 seconds after the last
 *   call. Later changes join the queue and the already scheduled event.
 * - The settle window exists because the block editor saves a post in two requests (the post, then
 *   the SCF meta boxes); both land in one call, and the build sees the finished save.
 * - Production runs WP-Cron from a system cron every minute (§3), so a build starts within about a
 *   minute of publishing (§14).
 *
 * Without a hook URL (local development) the call is only logged.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

const GN_REBUILD_SETTLE   = 10;
const GN_REBUILD_INTERVAL = 60;
const GN_REBUILD_RETRIES  = array( 60, 300, 900 ); // Backoff before retry 1, 2, 3.
const GN_REBUILD_LOG_SIZE = 50;

/**
 * Queues a rebuild for a change to published content.
 *
 * @param string $event  What happened, e.g. "publish", "update", "homepage".
 * @param string $object What it happened to, e.g. "post kalojira", for the log.
 */
function gn_queue_rebuild( string $event, string $object ): void {
	if ( ! empty( $GLOBALS['gn_suspend_rebuild'] ) ) {
		return; // Throwaway test content (`wp gn graphql-check`) never triggers a build.
	}
	$queue = (array) get_option( 'gn_rebuild_queue', array() );
	$key   = "{$event} {$object}";
	if ( ! in_array( $key, $queue, true ) ) {
		$queue[] = $key;
		update_option( 'gn_rebuild_queue', $queue, false );
	}
	if ( ! wp_next_scheduled( 'gn_rebuild_dispatch' ) ) {
		$last = (int) get_option( 'gn_rebuild_last_call', 0 );
		wp_schedule_single_event( max( time() + GN_REBUILD_SETTLE, $last + GN_REBUILD_INTERVAL ), 'gn_rebuild_dispatch' );
	}
}

/**
 * Sends the queued changes as one build call. Runs from cron (or "Rebuild now").
 */
function gn_rebuild_dispatch(): void {
	// One sender at a time: add_option() is an atomic insert.
	if ( ! add_option( 'gn_rebuild_lock', time(), '', false ) ) {
		if ( time() - (int) get_option( 'gn_rebuild_lock' ) < 60 ) {
			return;
		}
		update_option( 'gn_rebuild_lock', time(), false ); // Stale lock from a crashed run.
	}
	try {
		// Covers both cron (event already removed) and a direct call such as "Rebuild now".
		wp_clear_scheduled_hook( 'gn_rebuild_dispatch' );
		$queue = (array) get_option( 'gn_rebuild_queue', array() );
		if ( ! $queue ) {
			return;
		}
		delete_option( 'gn_rebuild_queue' );
		update_option( 'gn_rebuild_last_call', time(), false );
		gn_rebuild_send( $queue, 0 );
	} finally {
		delete_option( 'gn_rebuild_lock' );
	}
}
add_action( 'gn_rebuild_dispatch', 'gn_rebuild_dispatch' );

/**
 * One delivery attempt; schedules the next retry on failure.
 *
 * @param string[] $events  Changes this build covers.
 * @param int      $attempt 0 for the first try, 1–3 for retries.
 */
function gn_rebuild_send( array $events, int $attempt ): void {
	$url = gn_build_hook_url();
	if ( '' === $url ) {
		gn_rebuild_log( $events, $attempt, 'logged only (no GN_BUILD_HOOK_URL)', true );
		return;
	}

	$response = wp_remote_post(
		$url,
		array(
			'timeout'  => 15,
			'body'     => '',
			'blocking' => true,
		)
	);
	$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	$ok   = $code >= 200 && $code < 300;
	gn_rebuild_log( $events, $attempt, is_wp_error( $response ) ? 'error: ' . $response->get_error_message() : "HTTP {$code}", $ok );

	if ( ! $ok && isset( GN_REBUILD_RETRIES[ $attempt ] ) ) {
		wp_schedule_single_event( time() + GN_REBUILD_RETRIES[ $attempt ], 'gn_rebuild_retry', array( $events, $attempt + 1 ) );
	}
}
add_action( 'gn_rebuild_retry', 'gn_rebuild_send', 10, 2 );

/**
 * Adds a delivery to the log (newest first, last GN_REBUILD_LOG_SIZE kept).
 */
function gn_rebuild_log( array $events, int $attempt, string $result, bool $ok ): void {
	$log = (array) get_option( 'gn_rebuild_log', array() );
	array_unshift(
		$log,
		array(
			'time'    => time(),
			'events'  => array_slice( $events, 0, 10 ),
			'count'   => count( $events ),
			'attempt' => $attempt,
			'result'  => $result,
			'ok'      => $ok,
		)
	);
	update_option( 'gn_rebuild_log', array_slice( $log, 0, GN_REBUILD_LOG_SIZE ), false );
}

/**
 * The hook URL with its secret part hidden, for screens and logs.
 */
function gn_build_hook_url_masked(): string {
	$url = gn_build_hook_url();
	if ( '' === $url ) {
		return '';
	}
	$parts = wp_parse_url( $url );
	return ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' ) . '/…' . substr( $url, -4 );
}
