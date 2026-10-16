<?php
/**
 * Tools → Rebuild log: the last 50 rebuild deliveries, what's queued, and "Rebuild now" (§10.1).
 * Editors can read it; only administrators can trigger a rebuild.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		add_management_page( 'Rebuild log', 'Rebuild log', 'edit_others_posts', 'gn-rebuild-log', 'gn_rebuild_log_screen' );
	}
);

/**
 * Renders Tools → Rebuild log.
 */
function gn_rebuild_log_screen(): void {
	$log   = (array) get_option( 'gn_rebuild_log', array() );
	$queue = (array) get_option( 'gn_rebuild_queue', array() );
	$next  = wp_next_scheduled( 'gn_rebuild_dispatch' );
	$hook  = gn_build_hook_url_masked();
	?>
	<div class="wrap">
		<h1>Rebuild log</h1>
		<p>
			<?php if ( '' !== $hook ) : ?>
				Astro রিবিল্ড হুক: <code><?php echo esc_html( $hook ); ?></code>
			<?php else : ?>
				<strong>GN_BUILD_HOOK_URL সেট নেই:</strong> রিবিল্ড শুধু লগে লেখা হচ্ছে (লোকাল ডেভেলপমেন্ট)।
			<?php endif; ?>
		</p>
		<p>
			অপেক্ষায়: <?php echo esc_html( $queue ? implode( ', ', $queue ) : 'কিছু নেই' ); ?>
			<?php if ( $next ) : ?>
				· পরের পাঠানো: <?php echo esc_html( wp_date( 'H:i:s', $next ) ); ?>
			<?php endif; ?>
		</p>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gn_rebuild_now">
				<?php wp_nonce_field( 'gn_rebuild_now' ); ?>
				<?php submit_button( 'Rebuild now', 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<table class="widefat striped" style="margin-top:16px">
			<thead><tr><th>সময়</th><th>পরিবর্তন</th><th>চেষ্টা</th><th>ফল</th></tr></thead>
			<tbody>
			<?php if ( ! $log ) : ?>
				<tr><td colspan="4">এখনও কোনো রিবিল্ড হয়নি।</td></tr>
			<?php endif; ?>
			<?php foreach ( $log as $entry ) : ?>
				<tr>
					<td><?php echo esc_html( wp_date( 'j M Y, H:i:s', $entry['time'] ) ); ?></td>
					<td><?php echo esc_html( implode( ', ', $entry['events'] ) . ( $entry['count'] > count( $entry['events'] ) ? ' …' : '' ) ); ?></td>
					<td><?php echo esc_html( 0 === $entry['attempt'] ? '১ম' : 'পুনরায় #' . gn_bn_digits( $entry['attempt'] ) ); ?></td>
					<td style="color:<?php echo $entry['ok'] ? '#2D6A4F' : '#b32d2e'; ?>"><?php echo esc_html( $entry['result'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

add_action(
	'admin_post_gn_rebuild_now',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'gn_rebuild_now' );
		gn_queue_rebuild( 'manual', 'by ' . wp_get_current_user()->user_login );
		wp_clear_scheduled_hook( 'gn_rebuild_dispatch' );
		gn_rebuild_dispatch();
		wp_safe_redirect( admin_url( 'tools.php?page=gn-rebuild-log' ) );
		exit;
	}
);
