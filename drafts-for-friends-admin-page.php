<?php
/**
 * Drafts for Friends Admin Page
 *
 * @package    drafts-for-friends
 * @author     Jake Spurlock <whyisjake@gmail.com>
 * @version    Release: 0.5
 *
 */

// Keeping these on here for now, in the instance the Javascript is disabled. (Could probably remove tho...)
if ( isset( $_POST['drafts-for-friends_submit'] ) && $_POST['drafts-for-friends_submit'] ) {
	$t = $this->process_post_options( $_POST );
} elseif ( isset( $_POST['action'] ) && $_POST['action'] == 'extend') {
	$t = $this->process_extend( $_POST );
} elseif ( isset( $_GET['action'] ) && $_GET['action'] == 'process_delete' ) {
	$t = $this->process_delete( $_GET );
} ?>

<div class="wrap">

	<h2><?php _e('Drafts for Friends', 'drafts-for-friends'); ?></h2>

	<div class="updated hide">
		<?php if ( isset( $t ) )
			echo esc_html( $t ); ?>
	</div>

	<h3><?php _e('Currently Shared Drafts', 'drafts-for-friends'); ?></h3>

	<!-- Let's get the table started. -->
	<table class="widefat" id="shared-drafts">
		<thead>
			<tr>
				<th><?php _e( 'ID', 'drafts-for-friends' ); ?></th>
				<th><?php _e( 'Title', 'drafts-for-friends' ); ?></th>
				<th><?php _e( 'Link', 'drafts-for-friends' ); ?></th>
				<th><?php _e( 'Expires', 'drafts-for-friends' ); ?></th>
				<th colspan="2" class="actions"><?php _e( 'Actions', 'drafts-for-friends' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php $s = $this->get_shared();
			if ( $s ) :
				foreach( $s as $share ):
					$this->row_builder( $share );
				endforeach;
			else: ?>
				<tr class="none-found"><td colspan="6"><?php _e('No shared drafts!', 'drafts-for-friends'); ?></td></tr>
			<?php endif; ?>
		</tbody>
	</table>
	<h3><?php _e('Drafts for Friends', 'drafts-for-friends'); ?></h3>
	<form class="drafts-for-friends-share" method="post">
		<div>
			<?php echo $this->drafts_dropdown(); ?> <span class="loading"></span>
		</div>
		<div>
			<?php wp_nonce_field( 'process', 'process' ); ?>
			<input type="hidden" name="action" value="process_post_options">
			<input type="submit" class="button" name="drafts-for-friends_submit" value="<?php esc_attr_e('Share it', 'drafts-for-friends'); ?>" />
			<?php _e('for', 'drafts-for-friends'); ?>
			<input name="expires" type="number" min="0" step="1" value="2" size="4"/>
			<?php echo $this->build_time_measure_select(); ?>.
		</div>
	</form>
</div>