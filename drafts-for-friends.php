<?php
/*
Plugin Name: Drafts for Friends
Plugin URI: http://automattic.com/
Description: Now you don't need to add friends as users to the blog in order to let them preview your drafts
Author: Jake Spurlock
Version: 0.5
Author URI: http://jakespurlock.com
*/

class DraftsForFriends	{

	/**
	 * Plugin version
	 * @var null
	 */
	protected $version = '0.5';

	/**
	 * Name space
	 * @var null
	 */
	protected $namespace = 'js';

	/**
	 * Slug
	 * @var null
	 */
	protected $slug = '0.5';

	function __construct(){
    	add_action( 'init', array( $this, 'init' ) );
	}

	function init() {
		global $current_user;
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_filter( 'the_posts', array( $this, 'the_posts_intercept' ) );
		add_filter( 'posts_results', array( $this, 'posts_results_intercept' ) );

		$this->admin_options = $this->get_admin_options();

		$this->user_options = ( $current_user->id > 0 && isset( $this->admin_options[ $current_user->id ] ) ) ? $this->admin_options[ $current_user->id ] : array();

		$this->save_admin_options();

		$this->admin_page_init();

		$this->shared_post = null;

		// Start the AJAX requests with the
		add_action( 'wp_ajax_process_delete', array( $this, 'process_delete' ) );

	}

	function admin_page_init() {
		if ( is_admin() ) {
			wp_enqueue_script('jquery');
			wp_enqueue_script( $this->slug, plugins_url( 'js/drafts-for-friends.js', __FILE__ ), array( 'jquery'), $this->version );
			wp_enqueue_style( $this->slug, plugins_url( 'css/drafts-for-friends.css', __FILE__ ), '', $this->version );
		}
	}

	function get_admin_options() {
		$saved_options = get_option('shared');
		return is_array( $saved_options ) ? $saved_options : array();
	}

    function save_admin_options(){
        global $current_user;
        if ( $current_user->id > 0 ) {
            $this->admin_options[ $current_user->id ] = $this->user_options;
        }
        update_option( 'shared', $this->admin_options );
    }

	function add_admin_pages(){
		add_submenu_page("edit.php", __('Drafts for Friends', 'draftsforfriends'), __('Drafts for Friends', 'draftsforfriends'),
			1, 'drafts-for-friends', array( $this, 'output_existing_menu_sub_admin_page'));
	}

	/**
	  * Calculate the expiration date.
	  */
	function calc( $params ) {
		$exp = 60;
		$multiply = 60;
		if ( isset( $params['expires'] ) ) {
			$exp = absint( $params['expires'] );
		}
		$mults = array('s' => 1, 'm' => 60, 'h' => 3600, 'd' => 24*3600);
		if ($params['measure'] && $mults[$params['measure']]) {
			$multiply = $mults[$params['measure']];
		}
		return $exp * $multiply;
	}

	function process_post_options( $params ) {

		// Get the current user.
		global $current_user;

		// Do we have a post to save?
		if ( $params['post_id'] ) {

			$status = get_post_status( absint( $params['post_id'] ) );

			switch ( $status ) {

				// If there isn't a post, bounce...
				case null || false :
					return __('There is no such post!', 'draftsforfriends');
					break;

				// Is this a published post?
				case 'publish':
					return __('The post is published!', 'draftsforfriends');
					break;

				// Time to save the post.
				default:
					$this->user_options['shared'][] = array(
						'id' => $params['post_id'],
						'expires' => time() + $this->calc( $params ),
						'key' => $this->namespace . '-' . mt_rand()
						);
					$this->save_admin_options();
					break;
			}
		}
	}

	/**
	 * Let's put together the delete action. Parse the $_GET request,
	 * and after the nonce clears, delete the selected post from the options.
	 *
	 * @param $params array The items from the $_GET request.
	 *
	 */
	function process_delete( $params ) {

		$params = ( empty( $params ) ) ? $_GET : $params;

		// Check the nonce.
		if ( ! wp_verify_nonce( $_GET['nonce'], 'delete' ) )
			die( 'The nonce failed, and we couldn\'t go any further...' );

		$shared = array();

		foreach( $this->user_options['shared'] as $share ) {
			if ( isset( $share['key'] ) && $share['key'] == $params['key'] ) {
				continue;
			}
			$shared[] = $share;
		}
		$this->user_options['shared'] = $shared;
		$this->save_admin_options();

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			die( __('Shared post has been successfully deleted.', 'draftsforfriends') );
		} else {
			return __('Shared post has been successfully deleted.', 'draftsforfriends');
		}
	}

	/**
	 * Let's put together the extend action. Parse the $_POST request,
	 * and after the nonce clears, extend the selected post from the options.
	 *
	 * @param $params array The items from the $_POST request.
	 *
	 */
	function process_extend( $params ) {

		// Check the nonce.
		if ( ! wp_verify_nonce( $_POST['extend'], 'extend' ) )
			die( 'The nonce failed, and we couldn\'t go any further...' );

		// Set up the array of all of the shared posts.
		$shared = array();
		foreach( $this->user_options['shared'] as $share ) {
			if ( $share['key'] == $params['key'] ) {
				$share['expires'] += $this->calc( $params );
			}
			$shared[] = $share;
		}
		$this->user_options['shared'] = $shared;
		$this->save_admin_options();
		return 'Post sharing time has been updated.';
	}

	/**
	 * Get the relevant post statuses, and then pluck off published and private.
	 */
	function get_unpublished_post_statuses() {
		$stati = get_post_statuses();
		$statuses = array();
		foreach ( $stati as $status => $value ) {
			if ( ! in_array( $value, array( 'Private', 'Published' ) ) ) {
				$statuses[] = $status;
			}
		}
		return $statuses;
	}

	/**
	 * Get all of the users posts, and then sort them.
	 */
	function get_the_user_drafts( $uid ) {

		$statuses = $this->get_unpublished_post_statuses();

		// Do we have the query in the cache?
		$posts = wp_cache_get( $uid . '_unpub_posts' );
		if ( $posts == false ) {
			// Setup the query.
			$args = array(
				'post_status'	=> apply_filters( 'friends_statuses', $statuses ),
				'post_type'		=> apply_filters( 'friends_get_post_types', array( 'post', 'page' ) ),
				'posts_per_page'=> apply_filters( 'friends_posts_per_page', 20 ),
				);
			$posts = new WP_Query( $args );
			wp_cache_set( $uid . '_unpub_posts', $posts );
		}

		// Setup the drafts array.
		$drafts = array();

		// Let's setup the parents as the statuses.
		foreach ( $posts->posts as $the_post ) {
			$drafts[ $the_post->post_status ] = array();
		}

		// Now, loop again through all of the kids.
		foreach ( $posts->posts as $the_post ) {
			$post_array = array(
				'ID'			=> $the_post->ID,
				'post_title'	=> $the_post->post_title
				);
			array_push($drafts[$the_post->post_status], $post_array);
		}
		return $drafts;
	}

	/**
	  * Let's build the pulldown that will spit out the dropdown.
	  */
	function drafts_dropdown() {

		global $current_user;

		$drafts = $this->get_the_user_drafts( $current_user->data->ID );

		// Let's get the output started...
		$output = '<select id="draftsforfriends-postid" name="post_id">';
		$output .= '<option value="">' . __('Choose a draft:', 'draftsforfriends') . '</option>';
		foreach ( $drafts as $draft => $type ) {
			$output .= '<option value="" disabled>' . esc_html( ucfirst( $draft ) ) . '</option>';
			foreach ( $type as $draft ) {
				$output .= '<option value="' . esc_attr( intval( $draft['ID'] ) ) . '">' . esc_html( $draft['post_title'] ) . '</option>';
			}
		}
		$output .= '</select>';
		return $output;
	}

	/**
	 * Get the delete URL for a shared item.
	 */
	function get_delete_url( $share ) {
		$delete_url = admin_url( 'edit.php' );
		$args = array(
			'page'		=> 'drafts-for-friends',
			'action' 	=> 'process_delete',
			'key'		=> $share['key'],
			'nonce'		=> wp_create_nonce( 'delete' ),
			);
		$url = add_query_arg( $args, $delete_url );
		return $url;
	}

	/**
	 * Get the share URL
	 */
	function get_share_url( $share ) {

		// Start off with the home_url();
		$url = home_url();

		// Add the arguments.
		$args = array(
			'p' 				=> absint( $share['id'] ),
			'draftsforfriends' 	=> esc_attr( $share['key'] ),
			);

		$url = add_query_arg( $args, $url );

		// Send it all back home.
		return esc_url( $url );
	}

	/**
	 * Get a boolean value to see whether the datestamp is in the past or future
	 */
	function is_in_the_past( $date ) {
		$expire_time = new DateTime();
		$expire_time->setTimezone( new DateTimeZone( get_option('timezone_string') ) );
		$expire_time->setTimestamp( $date );
		$current_time = new DateTime();

		if ( $expire_time > $current_time ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Based on the shared timestamp, return the expires time, or that it is expired.
	 */
	function get_expired_time( $share ) {
		if ( $this->is_in_the_past( $share['expires'] ) ) {
			return 'Expired: <time datetime="' . esc_attr( date('Y-m-d', $share['expires'] ) ) . '">' . date('l jS \of F Y h:i A', $share['expires'] ). '</time>';
		} else {
			return human_time_diff( intval( $share['expires'] ), current_time( 'timestamp', get_option( 'timezone_string' ) ) );
		}
	}

	/**
	 * Get the current user's shared posts.
	 */
	function get_shared() {
		return ( isset( $this->user_options['shared'] ) ) ? $this->user_options['shared'] : '';
	}

	/**
	 * Ouput the admin page
	 */
	function output_existing_menu_sub_admin_page() {

		if ( isset( $_POST['draftsforfriends_submit'] ) && $_POST['draftsforfriends_submit'] ) {
			$t = $this->process_post_options( $_POST );
		} elseif ( isset( $_POST['action'] ) && $_POST['action'] == 'extend') {
			$t = $this->process_extend( $_POST );
		} elseif ( isset( $_GET['action'] ) && $_GET['action'] == 'process_delete' ) {
			$t = $this->process_delete( $_GET );
		} ?>

		<div class="wrap">

			<h2><?php _e('Drafts for Friends', 'draftsforfriends'); ?></h2>

			<div class="updated hide">
				<?php if ( isset( $t ) )
					echo esc_html( $t ); ?>
			</div>

			<h3><?php _e('Currently Shared Drafts', 'draftsforfriends'); ?></h3>

			<!-- Let's get the table started. -->
			<table class="widefat">
				<thead>
					<tr>
						<th><?php _e('ID', 'draftsforfriends'); ?></th>
						<th><?php _e('Title', 'draftsforfriends'); ?></th>
						<th><?php _e('Link', 'draftsforfriends'); ?></th>
						<th><?php _e('Expires', 'draftsforfriends'); ?></th>
						<th colspan="2" class="actions"><?php _e('Actions', 'draftsforfriends'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php $s = $this->get_shared();
					if ( $s ) :
						foreach( $s as $share ): ?>
							<tr class="<?php echo esc_attr( $share['key'] ); ?>">
								<td><?php echo absint( $share['id'] ); ?></td>
								<td>
									<a href="<?php echo esc_url( $this->get_share_url( $share ) ); ?>"><?php echo esc_html( get_the_title( $share['id'] ) ); ?></a> - <small><strong><?php echo esc_html( ucfirst( get_post_status( absint( $share['id'] ) ) ) ); ?></strong></small>
									<div class="row-actions">
										<span class="edit"><a href="<?php echo get_edit_post_link( $share['id'] ); ?>" title="Edit this item">Edit</a> | </span>
										<span class="view"><a href="<?php echo $this->get_share_url( $share ); ?>" title="Preview" rel="permalink">Preview</a></span>
									</div>
								</td>
								<td><input type="url" name="" value="<?php echo esc_attr( esc_url( $this->get_share_url( $share ) ) ); ?>" placeholder=""></td>
								</td>
								<td><?php echo wp_kses_post( $this->get_expired_time( $share ) ); ?></td>
								<td class="actions">
									<a class="button draftsforfriends-extend edit" id="draftsforfriends-extend-link-<?php echo esc_attr( $share['key'] ); ?>"
										href="javascript:draftsforfriends.toggle_extend('<?php echo esc_js( $share['key'] ); ?>');">
											<?php _e('Extend', 'draftsforfriends'); ?>
									</a>
									<form class="draftsforfriends-extend" id="<?php echo esc_attr( 'draftsforfriends-extend-form-' . $share['key'] ); ?>" method="post">
										<?php wp_nonce_field( 'extend', 'extend' ); ?>
										<input type="hidden" name="action" value="extend">
										<input type="hidden" name="key" value="<?php echo $share['key']; ?>" />
										<input type="submit" class="button submit-extend" name="draftsforfriends_extend_submit" value="<?php esc_attr_e('Extend', 'draftsforfriends'); ?>"/>
										<?php _e('by', 'draftsforfriends');?>
										<?php echo $this->tmpl_measure_select(); ?>
										<a class="draftsforfriends-extend-cancel" href="javascript:draftsforfriends.cancel_extend('<?php echo esc_js( $share['key'] ); ?>');"><?php _e('Cancel', 'draftsforfriends'); ?></a>
									</form>
								</td>
								<td class="actions">
									<a class="delete button delete-draft-link" data-share="<?php echo esc_attr( $share['key'] ); ?>" data-id="<?php echo esc_attr( $share['id'] ); ?>" href="<?php echo esc_url( $this->get_delete_url( $share ) ); ?>"><?php echo esc_html( __('Delete', 'draftsforfriends') ); ?></a>
								</td>
							</tr><?php
						endforeach;
					else: ?>
						<tr><td colspan="5"><?php _e('No shared drafts!', 'draftsforfriends'); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
			<h3><?php _e('Drafts for Friends', 'draftsforfriends'); ?></h3>
			<form id="draftsforfriends-share" action="" method="post">
				<p><?php echo $this->drafts_dropdown(); ?></p>
				<p><input type="submit" class="button" name="draftsforfriends_submit" value="<?php esc_attr_e('Share it', 'draftsforfriends'); ?>" /><?php _e('for', 'draftsforfriends'); ?><?php echo $this->tmpl_measure_select(); ?>.</p>
			</form>
		</div>
	<?php
	}

	/**
	 * Can a friend view a post, check against the list.
	 *
	 * @param 	$pid 	int 	Post ID.
	 * @return 	bool 	false.
	 */
	function can_view( $pid ) {

		// Let's get the admin options.
		foreach( $this->admin_options as $option ) {
			// Get all of the shares.
			$shares = $option['shared'];
			foreach( $shares as $share ) {
				if ( isset( $_GET['draftsforfriends'] ) && $share['key'] == $_GET['draftsforfriends'] && $pid ) {
					// If the expiration date isn't in the past, then we can set the variable to be true.
					if( ! $this->is_in_the_past( $share['expires'] ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * If the post isn't published, and the friend can view, show it.
	 */
	function posts_results_intercept( $posts ) {

		// Make sure that we aren't on an archive page, or something like that.
		if ( 1 != count( $posts ) )
			return $posts;

		// Get the first post in the array.
		$the_post = $posts[0];

		// Get the post status.
		$status = $the_post->post_status;

		// If the post isn't published, and the user can view, set the shared post
		if ( ( 'publish' != $status ) && ( $this->can_view( $the_post->ID ) ) ) {
			$this->shared_post = $the_post;
		}
		return $posts;
	}


	/**
	 * If the current post is a shared post, add it to the array.
	 */
	function the_posts_intercept( $posts ) {

		if ( empty( $posts ) && ! is_null( $this->shared_post ) ) {
			return array( $this->shared_post );
		} else {
			$this->shared_post = null;
			return $posts;
		}
	}

	/**
	 * Build the measure select.
	 */
	function tmpl_measure_select() {
		$secs 	= __('seconds', 'draftsforfriends');
		$mins 	= __('minutes', 'draftsforfriends');
		$hours 	= __('hours', 'draftsforfriends');
		$days 	= __('days', 'draftsforfriends');
		$output = '<input name="expires" type="number" min="0" step="1" value="2" size="4"/>
					<select name="measure">
						<option value="s">' . esc_html( $secs ) . '</option>
						<option value="m">' . esc_html( $mins ) . '</option>
						<option value="h" selected="selected">' . esc_html( $hours ) . '</option>
						<option value="d">' . esc_html( $days ) . '</option>
					</select>';
		return $output;
	}

}

$drafts = new DraftsForFriends();
