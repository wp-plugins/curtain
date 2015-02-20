<?php


/*
	* Plugin Name: Curtain
	* Plugin URI: https://wordpress.org/plugins/curtain/
	* Description: Hide your website behind something fluffy.
	* Text Domain: curtain
	* Domain Path: /lang/
	* Version: 1.0.0
	* Author: Leonard Lamprecht
	* Author URI: https://profiles.wordpress.org/mindrun/#content-plugins
	* License: GPLv2
*/


namespace curtain;

class main {

	public function __construct() {

		$actions = [
			'init',
			'admin_bar_menu',
			'ct_enqueue_scripts',
			'admin_menu',
			'admin_init',
			'admin_enqueue_scripts',
			'admin_notices',
			'plugins_loaded'
		];

		$lang = [
			__( 'Hide your website behind something fluffy.', 'curtain' )
		];

		foreach( $actions as $key => $action ) {
			add_action( $action, [ $this, $action ] );
		}

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'links' ] );

		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

	}

	private function current_url( $remove = false ) {

		global $blog_id, $pagenow;

		$query = $_SERVER['QUERY_STRING'];
		$url = get_admin_url( $blog_id, $pagenow . ( $query ? '?' . $query : null ) );

		if( $remove ) {
			$url = preg_replace( '/([?&])' . $remove . '=[^&]+(&|$)/', '$1', $url );
		}

		return $url;

	}

	private function set_mode( $before ) {

		$options = $this->options();
		$options['mode'] = $before;

		return update_option( 'curtain', $options );

	}

	protected function defaults( $options = false ) {

		$color = get_background_color();

		$handles = [
			'mode' => 0,
			'background' => '#' . ( !$color ? 'ffffff' : $color ),
			'heading' => __( 'Maintenance', 'curtain' ),
			'description' => sprintf( __( 'Please excuse the inconveniences, this site is currently in maintenance work. %s Check back soon!', 'curtain' ), '&#8212;' )
		];

		if( $options ) {
			array_shift( $handles );
		}

		return $handles;

	}

	private function head() {

		$defaults = [
			'wp_no_robots',
			'wp_generator',
			'wp_print_styles',
			'wp_print_head_scripts'
		];

		do_action( 'ct_enqueue_scripts' );

		foreach( $defaults as $int => $hook ) {
			call_user_func( $hook );
		}

		do_action( 'ct_head' );

	}

	private function footer() {

		do_action( 'ct_footer' );

		wp_print_footer_scripts();
		wp_admin_bar_render();

	}

	public static function caps( $add = null ) {

		global $wp_roles;

		$roles = [
			'administrator',
			'editor'
		];

		$cap = 'manage_curtain';

		if( $add == 1 ) {

			foreach( $roles as $int => $role ) {
				get_role( $role )->add_cap( $cap );
			}

		} else if( $add == 2 ) {

			foreach( $wp_roles->roles as $role => $caps ) {
				$wp_roles->remove_cap( $role, $cap );
			}

		} else {

			return $roles;

		}

	}

	public function activate() {

		load_plugin_textdomain( 'curtain', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

		add_option( 'curtain', self::defaults() );
		$this->caps( 1 );

	}

	public function deactivate() {

		delete_option( 'curtain' );
		$this->caps( 2 );

	}

	public function options( $tag = false ) {

		$options = (array) get_option( 'curtain' );
		return ( $tag ? $options[$tag] : $options );

	}

	public function admin_notices() {

		if( isset( $_GET['mode'] ) ) {

			$mode = $_GET['mode'];
			$status = ( $mode ? __( 'activated', 'curtain' ) : __( 'deactivated', 'curtain' ) );

			echo '<div class="' . ( $mode ? 'updated' : 'error' ) . '"><p>';

				printf( __( 'The Maintenance mode has been %s.', 'curtain' ), '<b>' . $status . '</b>' );

			echo '</p></div>';

		}

	}

	public function init() {

		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		if( ! $this->options( 'mode' ) || is_user_logged_in() || $GLOBALS['pagenow'] == 'wp-login.php' ) {
			return;
		}

		$version = get_plugin_data( __FILE__ )['Version'];

		header( $_SERVER['SERVER_PROTOCOL'] . ' 503 Service Unavailable', true, 503 );

		die( include( plugin_dir_path( __FILE__ ) . 'notice.php' ) );

	}

	public function admin_bar_menu( $admin_bar ) {

		if( !current_user_can( 'manage_curtain' ) ) {
			return;
		}

		$mode = $this->options( 'mode' );
		$status = ( $mode ? __( 'hidden', 'curtain' ) : __( 'visible', 'curtain' ) );

		$nodes = [

			[
				'id'    => 'curtain',
				'title' => '<span class="ab-icon"></span>',
				'href'  => add_query_arg( 'curtain', ( $mode ? 0 : 1 ), $this->current_url( 'activate' ) ),
				'meta'  => array( 'class' => ( $mode ? 'on' : 'off' ) ),
				'parent' => 'top-secondary'
			],

			[
				'id'    => 'curtain-mode',
				'title' => __( 'Your site is', 'curtain' ) . ' <b>' . $status . '</b>',
				'href'  => false,
				'parent' => 'curtain'
			]

		];

		foreach( $nodes as $key => $data ) {
			$admin_bar->add_node( $data );
		}

	}

	public function ct_enqueue_scripts() {

		$background = $this->options( 'background' );

		function contrast( $hex ) {

			$red = intval( substr( $hex, 0, 2 ), 16 );
			$green = intval( substr( $hex, 2, 2 ), 16 );
			$blue = intval( substr( $hex, 4, 2 ), 16 );

			$yiq = ( ( $red * 299 ) + ( $green * 587 ) + ( $blue * 114 ) ) / 1000;

			return ( $yiq >= 140 ) ? 'black' : 'white';

		}

		$text = contrast( explode( '#', $background )[1] );

		$body = "\t" . "body {
			color: {$text};
			background: {$background};
		}";

		wp_enqueue_style( 'curtain', plugins_url( 'assets/front.css', __FILE__ ), false, null );
		wp_add_inline_style( 'curtain', $body );

	}

	public function links( $actions ) {

		$link = [
			'settings' => '<a href="' . admin_url( 'options-general.php?page=curtain' ) . '">' . __( 'Settings', 'curtain' ) . '</a>'
		];

		return array_merge( $actions, $link );

	}

	private function need_reset() {

		global $wp_roles;

		$options = $this->options();

		foreach( $wp_roles->roles as $role => $details ) {

			if( isset( $details['capabilities']['manage_curtain'] ) ) {
				$actual[] = $role;
			}

		}

		array_shift( $options );

		if( $this->defaults( 1 ) == $options && $actual == $this->caps() ) {
			return false;
		} else {
			return ' show';
		}

	}

	public function load_options() {

		?>

		<div class="wrap curtain">

			<h2><?php _e( 'Maintenance', 'curtain' ); ?></h2>

			<form method="post" action="options.php">

			<?php

				settings_fields( 'settings' );
				do_settings_sections( 'curtain' );

			?>

			<p class="submit">

				<?php submit_button( __( 'Save Changes' ), 'primary', 'submit', false ); ?>
				<a href="<?php echo $_SERVER['REQUEST_URI']; ?>&reset" class="button reset<?php echo $this->need_reset(); ?>"><?php _e( 'Reset', 'curtain' ); ?></a>

			</p>

			</form>

		</div>

		<?php

	}

	public function admin_menu() {

		$title = __( 'Maintenance', 'curtain' );
		add_options_page( $title, $title, 'manage_options', 'curtain', [ $this, 'load_options' ] );

	}

	public function plugins_loaded() {

		load_plugin_textdomain( 'curtain', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

	}

	public function admin_init() {

		if( isset( $_GET['reset'] ) && $_GET['page'] == 'curtain' ) {

			$old = $this->options( 'mode' );

			$this->deactivate();
			$this->activate();

			if( $this->options( 'mode' ) !== $old ) {
				$this->set_mode( $old );
			}

			wp_redirect( admin_url( 'options-general.php?page=curtain' ) );

		}

		if( isset( $_GET['curtain'] ) ) {

			if( $this->set_mode( intval( $_GET['curtain'] ) ) ) {

				wp_redirect( add_query_arg( 'mode', $_GET['curtain'], $this->current_url( 'curtain' ) ) );
				exit;

			}

		}

		if( is_admin() ) {
			new Settings;
		}

	}

	public function admin_enqueue_scripts() {

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

	    $assets = array(
			'admin.css',
			'admin.js'
	    );

	    foreach( $assets as $int => $file ) {

	        $type = ( explode( '.', $file )[1] == 'js' ? 'script' : 'style' );

	        call_user_func( 'wp_enqueue_' . $type, 'curtain', plugins_url( 'assets/' . $file, __FILE__ ) );

	    }

	}

}

class settings extends main {

	public function __construct() {

		$labels = [
			__( 'Background', 'curtain' ),
			__( 'Heading', 'curtain' ),
			__( 'Description', 'curtain' ),
			__( 'Managers', 'curtain' )
		];

		add_settings_section( 'settings', false, false, 'curtain' );
		register_setting( 'settings', 'curtain', [ $this, 'sanitize' ] );

		$defaults = array_merge( parent::defaults( 1 ), [ 'roles' => 0 ] );

		foreach( $defaults as $handle => $value ) {

			$array_pos = array_search( $handle, array_keys( $defaults ) );
			add_settings_field( $handle, $labels[$array_pos], [ $this, $handle ], 'curtain', 'settings', array( 'label_for' => $handle ) );

		}

	}

	public function sanitize( $options = [] ) {

		$old = (array) $this->options();

		foreach( $options as $name => $value ) {

			if( $name == 'roles' ) {

				$me = __CLASS__;
				$me::caps( 2 );

				foreach( $value as $key => $role ) {
					get_role( $role )->add_cap( 'manage_curtain' );
				}

			} else {
				$old[$name] = ( is_numeric( $value ) ? intval( $value ) : $value );
			}

		}

		return $old;

	}

	public function background() {

		$default = parent::defaults( 1 )['background'];

		?>

		<input name="curtain[background]" type="text" value="<?php echo parent::options( 'background' ) ?>" data-default-color="<?php echo $default ?>">
		<p class="description"><?php echo __( "The default color equals your theme's background", "curtain" ) ?></p>

		<?php

	}

	public function heading() {

		$ph = __( 'The headline of the page', 'curtain' );

		?>

		<input name="curtain[heading]" id="heading" type="text" placeholder="<?php echo $ph ?>" value="<?php echo parent::options( 'heading' ) ?>" class="regular-text">

		<?php

	}

	public function description() {

		$ph = __( 'Briefly describe why your site is in maintenance mode', 'curtain' );

		?>

		<textarea name="curtain[description]" id="description" placeholder="<?php echo $ph ?>" class="large-text"><?php echo parent::options( 'description' ) ?></textarea>

		<?php

	}

	public function roles() {

		echo '<select id="roles" name="curtain[roles][]" multiple size="3">';

		foreach( get_editable_roles() as $handle => $info ) {

			$select = ( isset( $info['capabilities']['manage_curtain'] ) ? ' selected' : null );
			echo '<option value="' . $handle . '"' . $select . '>' . translate_user_role( $info['name'] ) . '</option>';

		}

		echo '</select>';

		?>

		<p class="description"><?php echo __( "Who can enable/disable the maintenance mode?", "curtain" ) ?></p>

		<?php

	}

}

new main;

?>