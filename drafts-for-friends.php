<?php
/*
Plugin Name: Drafts for Friends
Plugin URI: http://jakespurlock.com/drafts-for-friends/
Description: Now you don't need to add friends as users to the blog in order to let them preview your drafts
Author: Jake Spurlock
Version: 0.5
Author URI: http://jakespurlock.com
Text Domain: drafts-for-friends
License: license.txt
*/
/**
 * Drafts for Friends
 *
 * @package    drafts-for-friends
 * @author     Jake Spurlock <whyisjake@gmail.com>
 * @version    Release: 0.5
 *
 */

class JS_Drafts_For_Friends	{

	/**
	 * Plugin version
	 * @var string
	 */
	protected $version = '0.5';

	/**
	 * Name space
	 * @var string
	 */
	protected $namespace = 'js';

	/**
	 * Slug
	 * @var string
	 */
	protected $slug = 'drafts-for-friends';

	/**
	 * Set the shared post to be null as a default.
	 * @var null
	 */
	protected $shared_post = null;

	/**
	 * Let's get this going...
	 */
	public function __construct(){
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Init, things to get started.
	 */
	public function init() {

		// Need to know which user to pull the shared items from.
		global $current_user;

		// Add the admin page.
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );

		// Intercept the post
		add_filter( 'the_posts', array( $this, 'the_posts_intercept' ) );
		add_filter( 'posts_results', array( $this, 'posts_results_intercept' ) );

		// Enqueue the admin scripts/styles
		add_action( 'admin_enqueue_scripts', array( $this, 'load_resources' ) );

		// Get the stored values in the database.
		$this->admin_options = $this->get_admin_options();

		// Line up the stored values to the current user.
		$this->user_options = ( $current_user->id > 0 && isset( $this->admin_options[ $current_user->id ] ) ) ? $this->admin_options[ $current_user->id ] : array();

		// If the user didn't have anything before, save an empty array.
		$this->save_admin_options();

		// Add actions for the AJAX calls
		add_action( 'wp_ajax_process_delete', array( $this, 'process_delete' ) );
		add_action( 'wp_ajax_process_extend', array( $this, 'process_extend' ) );
		add_action( 'wp_ajax_process_post_options', array( $this, 'process_post_options' ) );

	}

	/**
	 * If we are on the Drafts for Friends page, load the CSS/JS.
	 * At the same time, set a javascript variable.
	 */
	public function load_resources() {
		$screen = get_current_screen();
		if ( is_admin() && $screen->id == 'posts_page_drafts-for-friends' ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( $this->slug, plugins_url( 'js/drafts-for-friends.js', __FILE__ ), array( 'jquery' ), $this->version );
			wp_enqueue_style( $this->slug, plugins_url( 'css/drafts-for-friends.css', __FILE__ ), '', $this->version );
			$strings = array(
				'loading_gif'	=> esc_js( get_admin_url( '', '/images/wpspin_light.gif' ) ),
				'added'			=> esc_js( __( 'Draft successfully added', 'drafts-for-friends' ) ),
			);

			wp_localize_script( $this->slug, 'drafts', $strings );
		}
	}

	/**
	 * Get the stored options for all of the shared objects.
	 * @return array Array of saved drafts
	 */
	public function get_admin_options() {
		$saved_options = get_option( 'shared' );
		return is_array( $saved_options ) ? $saved_options : array();
	}

	/**
	 * Store/save the shared objects.
	 */
	public function save_admin_options(){
		global $current_user;
		if ( $current_user->id > 0 ) {
			$this->admin_options[ $current_user->id ] = $this->user_options;
		}
		update_option( 'shared', $this->admin_options );
	}

	/**
	 * Add the admin page.
	 */
	public function add_admin_pages(){
		add_submenu_page( 'edit.php', __( 'Drafts for Friends', 'drafts-for-friends' ), __( 'Drafts for Friends', 'drafts-for-friends' ), 1, $this->slug,  array( $this, 'output_existing_menu_sub_admin_page' ) );
	}

	/**
	 * Calculate the expiration date.
	 * @param 	array 	$params Array of share values.
	 * @return 	string 	New date stamp
	 */
	private function calc( $params ) {

		// Setup some variables, yo.
		$expiration = 60;
		$multiply 	= 60;

		// Make sure that we have a valid number as the expiration value
		if ( isset( $params['expires'] ) )
			$expiration = absint( $params['expires'] );

		// Setup the defaults.
		$mults = array(
			's' => 1,
			'm' => MINUTE_IN_SECONDS,
			'h' => HOUR_IN_SECONDS,
			'd' => DAY_IN_SECONDS,
		);

		// Make sure that we have the units to measure.
		if ( $params['measure'] && $mults[ $params['measure'] ] ) {
			$multiply = $mults[ esc_html( $params['measure'] ) ];
		}

		// Multiply the values for the new expiration date.
		$new_date = $expiration * $multiply;

		// Spit it all back out.
		return absint( $new_date );
	}

	/**
	 * Process the posts/urls and set an expiration date.
	 * @param 	array 	$params Array of share values.
	 * @return 	string 	Either the row of the table will be returned, or the string saying that the post was updated.
	 */
	public function process_post_options( $params ) {

		// If we are doing a normal $_GET request, the params get passed
		// through the page load, if this comes over AJAX, we need to grab
		// them for use in the function.
		$params = ( empty( $params ) ) ? $_POST : $params;

		// Check the nonce.
		if ( ! wp_verify_nonce( $params['process'], 'process' ) )
			die( esc_attr__( 'The nonce failed, and we couldn\'t go any further...', 'drafts-for-friends' ) );


		// Get the current user.
		global $current_user;

		// Do we have a post to save?
		if ( $params['post_id'] ) {

			// One function call will let us know if the post exists, and what the status is.
			$status = get_post_status( absint( $params['post_id'] ) );

			// Roll through the different scenarios.
			switch ( $status ) {

				// If there isn't a post, bounce...
				case null || false :
					return __( 'There is no such post!', 'drafts-for-friends' );
					break;

				// Is this a published post?
				case 'publish':
					return __( 'The post is published!', 'drafts-for-friends' );
					break;

				// Time to save the post.
				default:
					$share = array(
						'id' 		=> absint( $params['post_id'] ),
						'expires' 	=> intval( time() + $this->calc( $params ) ),
						'key' 		=> esc_attr( $this->namespace . '-' . mt_rand() ),
					);
					$this->user_options['shared'][] = $share;
					$this->save_admin_options();

					// If we are doing AJAX, send back the new row.
					if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
						die( $this->row_builder( $share ) );
					} else {
						return __( 'Draft successfully added', 'drafts-for-friends' );
					}
					break;
			}
		}
	}

	/**
	 * Build a row to add to the table.
	 *
	 * @param 	array 	$share The array of items containing the key, and ID of the post.
	 * @return 	string 	Row of the table that holds all of the shared data.
	 */
	private function row_builder( $share ) { ?>

		<tr class="<?php echo esc_attr( $share['key'] ); ?>">
			<td class="id">
				<?php echo absint( $share['id'] ); ?>
			</td>
			<td class="title">
				<a href="<?php echo esc_url( $this->get_share_url( $share ) ); ?>"><?php echo esc_html( get_the_title( $share['id'] ) ); ?></a> - <small><strong><?php echo esc_html( ucfirst( get_post_status( absint( $share['id'] ) ) ) ); ?></strong></small>
				<div class="row-actions">
					<span class="edit"><a href="<?php echo get_edit_post_link( $share['id'] ); ?>" title="Edit this item">Edit</a> | </span>
					<span class="view"><a href="<?php echo $this->get_share_url( $share ); ?>" title="Preview" rel="permalink">Preview</a></span>
				</div>
			</td>
			<td class="share_url">
				<input type="url" name="" value="<?php echo esc_attr( esc_url( $this->get_share_url( $share ) ) ); ?>" placeholder="">
			</td>
			<td class="time">
				<?php echo wp_kses_post( $this->get_expired_time( $share ) ); ?>
			</td>
			<td class="actions">
				<a class="button drafts-for-friends-extend-button edit" id="drafts-for-friends-extend-link-<?php echo esc_attr( $share['key'] ); ?>" data-key="<?php echo esc_attr( $share['key'] ); ?>" href="#"><?php _e( 'Extend', 'drafts-for-friends' ); ?></a>
				<form class="drafts-for-friends-extend" data-key="<?php echo esc_attr( $share['key'] ); ?>" id="<?php echo esc_attr( 'drafts-for-friends-extend-form-' . $share['key'] ); ?>" method="post">
					<?php wp_nonce_field( 'extend', 'extend' ); ?>
					<input type="hidden" name="action" value="process_extend">
					<input type="hidden" name="key" value="<?php echo esc_attr( $share['key'] ); ?>" />
					<input type="submit" class="button submit-extend" name="drafts-for-friends_extend_submit" value="<?php esc_attr_e( 'Extend', 'drafts-for-friends' ); ?>"/>
					<?php _e( 'by', 'drafts-for-friends' ); ?>
					<input name="expires" type="number" min="0" step="1" value="2" size="4"/>
					<?php echo $this->build_time_measure_select(); ?>
					<a class="drafts-for-friends-extend-cancel" data-key="<?php echo esc_attr( $share['key'] ); ?>" href=""><?php _e( 'Cancel', 'drafts-for-friends' ); ?></a>
				</form>
			</td>
			<td class="actions">
				<a class="delete button delete-draft-link" data-share="<?php echo esc_attr( $share['key'] ); ?>" data-id="<?php echo esc_attr( $share['id'] ); ?>" href="<?php echo esc_url( $this->get_delete_url( $share ) ); ?>"><?php echo esc_html( __( 'Delete', 'drafts-for-friends' ) ); ?></a>
			</td>
		</tr><?php

	}

	/**
	 * Let's put together the delete action. Parse the $_GET request,
	 * and after the nonce clears, delete the selected post from the options.
	 *
	 * @param 	array 	$params The items from the $_GET request.
	 * @return 	string 	Success note.
	 *
	 */
	public function process_delete( $params ) {

		// If we are doing a normal $_GET request, the params get passed
		// through the page load, if this comes over AJAX, we need to grab
		// them for use in the function.
		$params = ( empty( $params ) ) ? $_GET : $params;

		// Check the nonce.
		if ( ! wp_verify_nonce( $_GET['nonce'], 'delete' ) )
			die( esc_attr__( 'The nonce failed, and we couldn\'t go any further...', 'drafts-for-friends' ) );

		// Setup the shared array
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
			die( __( 'Shared post has been successfully deleted.', 'drafts-for-friends' ) );
		} else {
			return __( 'Shared post has been successfully deleted.', 'drafts-for-friends' );
		}
	}

	/**
	 * Let's put together the extend action. Parse the $_POST request,
	 * and after the nonce clears, extend the selected post from the options.
	 *
	 * @param 	array 	$params The items from the $_POST request.
	 * @return 	mixed 	Either an array, or string depending on if DOING_AJAX or not.
	 *
	 */
	public function process_extend( $params ) {

		// If we are doing a normal $_GET request, the params get passed
		// through the page load, if this comes over AJAX, we need to grab
		// them for use in the function.
		$params = ( empty( $params ) ) ? $_POST : $params;

		// Check the nonce.
		if ( ! wp_verify_nonce( $_POST['extend'], 'extend' ) )
			die( json_encode( array( 'error' => esc_attr__( 'The nonce failed, and we couldn\'t go any further...', 'drafts-for-friends' ) ) ) );

		// Set up the array of all of the shared posts.
		$shared = array();
		$new_expiration;
		foreach( $this->user_options['shared'] as $share ) {
			if ( $share['key'] == $params['key'] ) {
				$new_expiration = $share['expires'] += $this->calc( $params );
			}
			$shared[] = $share;
		}
		$this->user_options['shared'] = $shared;
		$this->save_admin_options();
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$return_array = array(
				'message'	=> esc_attr__( 'Post sharing time has been updated.', 'drafts-for-friends' ),
				'time'		=> esc_attr( $this->get_expired_time( array( 'expires' => $new_expiration ) ) ),
			);
			die( json_encode( $return_array ) );
		} else {
			return __( 'Post sharing time has been updated.', 'drafts-for-friends' );
		}
	}

	/**
	 * Get the relevant post statuses, and then pluck off published and private.
	 *
	 * @return 	array 	All of the draft style post statuses.
	 */
	private function get_unpublished_post_statuses() {
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
	 *
	 * @param  	int 	User ID.
	 * @return 	array 	All of the users drafts.
	 *
	 */
	private function get_the_user_drafts( $uid ) {

		// Let's get all of the post statuses
		$statuses = $this->get_unpublished_post_statuses();

		// Setup the query.
		$args = array(
			'author'		=> absint( $uid ),
			'post_status'	=> apply_filters( 'friends_statuses', $statuses ),
			'post_type'		=> apply_filters( 'friends_get_post_types', array( 'post', 'page' ) ),
			'posts_per_page'=> apply_filters( 'friends_posts_per_page', 20 ),

			);
		$posts = new WP_Query( $args );

		// Setup the drafts array.
		$drafts = array();

		// Create the array of post drafts
		foreach ( $posts->posts as $the_post ) {
			$post_array = array(
				'ID' 			=> $the_post->ID,
				'post_title' 	=> $the_post->post_title
			);
			$drafts[ $the_post->post_status ][] = $post_array;
		}

		return $drafts;
	}

	/**
	  * Let's build the pulldown that will spit out the dropdown.
	  *
	  * @return	string 	Select with all of the users drafts.
	  */
	private function drafts_dropdown() {

		global $current_user;

		$drafts = $this->get_the_user_drafts( $current_user->data->ID );

		// Let's get the output started...
		$output = '<select id="drafts-for-friends-postid" name="post_id">';
		$output .= '<option value="">' . __( 'Choose a draft:', 'drafts-for-friends' ) . '</option>';
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
	 *
	 * @param 	array 	$share The array that holds the key of the post to delete.
	 * @return 	string 	$url of the delete URL.
	 *
	 */
	private function get_delete_url( $share ) {
		$delete_url = admin_url( 'edit.php' );
		$args = array(
			'page'		=> 'drafts-for-friends',
			'action' 	=> 'process_delete',
			'key'		=> esc_attr( $share['key'] ),
			'nonce'		=> wp_create_nonce( 'delete' ),
			);
		$url = add_query_arg( $args, $delete_url );
		return $url;
	}

	/**
	 * Get the share URL
	 *
	 * @param 	array 	$share The array that holds the key of the post to generate a share URL for.
	 * @return 	string 	$url that can be shared.
	 *
	 */
	private function get_share_url( $share ) {

		// Start off with the home_url();
		$url = home_url( '/' );

		// Add the arguments.
		$args = array(
			'p'						=> absint( $share['id'] ),
			'drafts-for-friends'	=> esc_attr( $share['key'] ),
			);

		$url = add_query_arg( $args, $url );

		// Send it all back home.
		return esc_url( $url );
	}

	/**
	 * Get a boolean value to see whether the date stamp is in the past or future
	 * This is kind of a hack, (what isn't on the Internet...) came from a helpful comment in the PHP.net forums.
	 *
	 * @param
	 */
	private function is_in_the_past( $date ) {

		// Get the expired time.
		$expire_time = new DateTime();
		$expire_time->setTimezone( new DateTimeZone( get_option( 'timezone_string' ) ) );
		$expire_time->setTimestamp( $date );

		// Get the current time.
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
	private function get_expired_time( $share ) {

		if ( $this->is_in_the_past( $share['expires'] ) ) {

			$offset = $this->get_timezone_offset( get_option( 'timezone_string' ), 'UTC' );
			if ( $offset ) {
				$time = $share['expires'] + $offset;
				return __( 'Expired: ', 'drafts-for-friends' ) . '<time datetime="' . esc_attr( date( 'Y-m-d', $time ) ) . '">' . esc_html( date( 'l jS \of F Y h:i A', $time ) ) . '</time>';
			} else {
				return __( 'Expired: ', 'drafts-for-friends' ) . '<time datetime="' . esc_attr( date( 'Y-m-d', $share['expires'] ) ) . '">' . esc_html( date( 'l jS \of F Y h:i A', $share['expires'] ) ) . '</time>';
			}
		} else {
			return __( 'Expires in ', 'drafts-for-friends'  ) . human_time_diff( intval( $share['expires'] ), current_time( 'timestamp', get_option( 'timezone_string' ) ) );
		}
	}

	/**
	* Returns the offset from the origin timezone to the remote timezone, in seconds.
	*
	* @param 	string 	$remote_tz, the remote time zone to check against.
	* @param 	string 	$origin_tz, the origin, probably UTC... If null the servers current timezone is used as the origin.
	* @return 	int 	$offset, the timezone offset, in seconds.
	*/
	private function get_timezone_offset( $remote_tz, $origin_tz = null ) {
		if ( $origin_tz === null ) {
			if ( ! is_string( $origin_tz = date_default_timezone_get() ) ) {
				// A UTC time stamp was returned -- bail out!
				return false;
			}
		}
		$origin_dtz = new DateTimeZone( $origin_tz );
		$remote_dtz = new DateTimeZone( $remote_tz );
		$origin_dt 	= new DateTime( "now", $origin_dtz );
		$remote_dt 	= new DateTime( "now", $remote_dtz );
		$offset 	= $origin_dtz->getOffset( $origin_dt ) - $remote_dtz->getOffset( $remote_dt );
		return $offset;
	}

	/**
	 * Build the measure select.
	 *
	 * @return 	string 	The select drop down.
	 */
	private function build_time_measure_select() {
		$secs 	= __( 'seconds', 'drafts-for-friends' );
		$mins 	= __( 'minutes', 'drafts-for-friends' );
		$hours 	= __( 'hours', 'drafts-for-friends' );
		$days 	= __( 'days', 'drafts-for-friends' );
		$output = '<select name="measure">
				<option value="s">' . esc_html( $secs ) . '</option>
				<option value="m">' . esc_html( $mins ) . '</option>
				<option value="h" selected="selected">' . esc_html( $hours ) . '</option>
				<option value="d">' . esc_html( $days ) . '</option>
			</select>';
		return $output;
	}

	/**
	 * Get the current user's shared posts.
	 * @return 	array 	All of the users shared drafts.
	 */
	private function get_shared() {
		return ( isset( $this->user_options['shared'] ) ) ? $this->user_options['shared'] : '';
	}

	/**
	 * Output the admin page
	 *
	 * @return 	string 	The admin page for Drafts for Friends
	 *
	 */
	public function output_existing_menu_sub_admin_page() {

		// Include the page
		include_once dirname( __FILE__ ) . '/drafts-for-friends-admin-page.php';
	}

	/**
	 * Can a friend view a post, check against the list.
	 *
	 * @param 	int 	$pid is the post id..
	 * @return 	bool 	Default is false, true if the URL matches up.
	 *
	 */
	function can_view( $pid ) {

		$options = $this->admin_options;

		// Let's get the admin options.
		foreach( $options as $option ) {
			// Get all of the shares.
			$shares = $option['shared'];
			foreach( $shares as $share ) {
				if ( isset( $_GET['drafts-for-friends'] ) && $share['key'] == $_GET['drafts-for-friends'] && $pid ) {
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
	 *
	 * @param array $posts that come back as a result of the WP_Query object
	 * @return array The same array that was a parameter
	 *
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
	 * If the current post is a shared post, return an array with the shared post.
	 *
	 * @param object $posts that come back as a result of the WP_Query object
	 * @return array The same array that was a parameter
	 *
	 */
	function the_posts_intercept( $posts ) {

		if ( empty( $posts ) && ! is_null( $this->shared_post ) ) {
			return array( $this->shared_post );
		} else {
			$this->shared_post = null;
			return $posts;
		}
	}

}

$drafts = new JS_Drafts_For_Friends();