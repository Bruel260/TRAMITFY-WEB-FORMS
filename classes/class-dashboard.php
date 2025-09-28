<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

/**
 * Theme dashboard, activtion, importer, plugins, system status, etc.
 * 
 * @since  4.3.0
 */

class Codevz_Core_Dashboard {

	public $is_free = false;
	public $disable = false;
	public $theme 	= false;
	public $slug 	= false;
	public $option 	= false;
	public $menus 	= false;
	public $plugins = false;
	public $demos 	= false;

	private static $instance = null;

	public function __construct() {

		add_action( 'init', [ $this, 'init' ] );

	}

	public function init() {

		// IDs.
		$this->slug 	= 'theme-activation';
		$this->option 	= 'codevz_theme_activation';

		// Deregister license.
		if ( ! empty( $_POST['deregister'] ) ) {

			// Get saved activation.
			$activation = get_option( $this->option );
			$purchase_code = isset( $activation[ 'purchase_code' ] ) ? $activation[ 'purchase_code' ] : null;

			$this->deregister( $purchase_code, strlen( $purchase_code ) < 40 );

		// Register license.
		} else if ( ! empty( $_POST[ 'register' ] ) ) {

			$purchase_code = sanitize_text_field( wp_unslash( $_POST['register'] ) );

			$this->register( $purchase_code, strlen( $purchase_code ) < 40 );

		}

		// Check free.
		$this->is_free = Codevz_Core_Theme::is_free();

		// Disable features.
		if ( ! Codevz_Core_Theme::$premium ) {

			$this->disable = array_flip( [ 'envato', 'activation', 'importer_page', 'plugins', 'status', 'uninstall', 'feedback', 'docs', 'youtube', 'changelog', 'ticksy', 'faq' ] );

		} else {

			$this->disable = array_flip( (array) Codevz_Core_Theme::option( 'disable' ) );

		}

		if ( Codevz_Core_Theme::option( 'white_label_exclude_admin' ) && function_exists( 'current_user_can' ) && current_user_can( 'administrator' ) ) {
			$this->disable = [];
		}

		// Check white label for menu.
		if ( ! isset( $this->disable[ 'menu' ] ) || $this->is_free ) {

			// Theme info.
			$this->theme = wp_get_theme();
			$this->theme->version = empty( $this->theme->parent() ) ? $this->theme->get( 'Version' ) : $this->theme->parent()->Version;

			// Admin menus.
			$this->menus 	= [

				'activation' 	=> Codevz_Core_Strings::get( 'activation' ),
				'importer' 		=> Codevz_Core_Strings::get( 'importer' ),
				'importer_page' => Codevz_Core_Strings::get( 'importer_page' ),
				'plugins' 		=> Codevz_Core_Strings::get( 'plugins' ),
				'options' 		=> Codevz_Core_Strings::get( 'options' ),
				'status' 		=> Codevz_Core_Strings::get( 'status' ),
				'feedback' 		=> Codevz_Core_Strings::get( 'feedback' ),
				'uninstall' 	=> Codevz_Core_Strings::get( 'uninstall' ),

			];

			// Free version.
			//if ( $this->is_free ) {

				//unset( $this->menus[ 'activation' ] );
				//$this->menus[ 'activation' ] = '<div class="dashicons dashicons-lock" aria-hidden="true" style="font-size: 18px;margin-' . ( is_rtl() ? 'left' : 'right' ) . ': 5px;"></div> ' . Codevz_Core_Strings::get( 'pro' );

			//}

			// White label check activation.
			if ( isset( $this->disable[ 'activation' ] ) ) {

				unset( $this->menus[ 'activation' ] );

			}

			// White label check importer.
			if ( isset( $this->disable[ 'importer' ] ) ) {

				unset( $this->menus[ 'importer' ] );
				unset( $this->menus[ 'uninstall' ] );

			}

			if ( isset( $this->disable[ 'importer_page' ] ) ) {

				unset( $this->menus[ 'importer_page' ] );

			}

			if ( isset( $this->disable[ 'plugins' ] ) ) {

				unset( $this->menus[ 'plugins' ] );

			}

			if ( isset( $this->disable[ 'uninstall' ] ) ) {

				unset( $this->menus[ 'uninstall' ] );

			}

			if ( isset( $this->disable[ 'status' ] ) ) {

				unset( $this->menus[ 'status' ] );

			}

			if ( isset( $this->disable[ 'feedback' ] ) ) {

				unset( $this->menus[ 'feedback' ] );

			}

			// White label check theme options.
			if ( isset( $this->disable[ 'options' ] ) ) {

				unset( $this->menus[ 'options' ] );

			}

			// White label check videos.
			if ( isset( $this->disable[ 'videos' ] ) ) {

				unset( $this->menus[ 'videos' ] );

			}

			// Theme plugins.
			$this->plugins 	= apply_filters( 'codevz_config_plugins', [] );

			// Default check.
			$class_exists = [
				'codevz-plus' 		=> 'Codevz_Plus',
				'elementor' 		=> 'Elementor\Autoloader',
				'js_composer' 		=> 'Vc_Manager',
				'revslider' 		=> 'RevSliderAdmin',
				'woocommerce' 		=> 'WooCommerce',
				'contact-form-7'	=> 'WPCF7',
				'litespeed-cache' 	=> 'LiteSpeed\Core',
			];

			// Plugin active check.
			foreach( $class_exists as $slug => $class ) {

				if ( isset( $this->plugins[ $slug ] ) ) {

					$this->plugins[ $slug ][ 'class_exists' ] = $class;

				}

			}

			// List of demos.
			$this->demos = apply_filters( 'codevz_config_demos', [] );

			// Actions.
			add_action( 'admin_menu', [ $this, 'admin_menu' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
			add_action( 'wp_ajax_codevz_wizard', [ $this, 'wizard' ] );
			add_action( 'wp_ajax_codevz_feedback', [ $this, 'feedback_submit' ] );
			add_action( 'wp_ajax_codevz_page_importer', [ $this, 'page_importer_ajax' ] );

			add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );

		}

	}

	public static function instance() {

		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;

	}

	// Check admin only for theme dashboard.
	public function is_codevz_dashboard() {

		global $pagenow;

		$pages = [
			'theme-activation', 
			'theme-importer', 
			'theme-importer_page', 
			'theme-plugins', 
			'theme-status', 
			'theme-uninstall',
			'theme-feedback'
		];

		// Check if current page matches the custom pages
		if ( $pagenow == 'admin.php' && isset( $_GET[ 'page' ] ) && in_array( $_GET[ 'page' ], $pages ) ) {

			return true;

		}

	}

	// Admin body class for dashboard.
	function admin_body_class( $classes ) {

		if ( $this->is_codevz_dashboard() ) {

			$classes .= ' codevz-dashboard';

		}

		return $classes;

	}

	/**
	 * Load admin dashboard assets
	 * 
	 * @return -
	 */
	public function enqueue( $hook ) {

		if ( ! Codevz_Core_Theme::contains( $hook, 'theme' ) ) {
			return false;
		}

		wp_enqueue_style( 'codevz-dashboard-font', 'https://fonts.googleapis.com/css?family=Poppins:400,500,600,700' );
		wp_enqueue_style( 'codevz-dashboard', esc_url( Codevz_Core_Theme::$url ) . 'assets/css/dashboard.css', [], $this->theme->version, 'all' );
		wp_enqueue_script( 'codevz-dashboard', esc_url( Codevz_Core_Theme::$url ) . 'assets/js/dashboard.js', [], $this->theme->version, true );

		// RTL styles.
		if ( is_rtl() ) {
			wp_enqueue_style( 'codevz-dashboard-rtl', esc_url( Codevz_Core_Theme::$url ) . 'assets/css/dashboard.rtl.css', [], $this->theme->version, 'all' );
		}

		$plugins = [];

		// List of inactive plugins.
		foreach( $this->plugins as $slug => $plugin ) {

			if ( ! $this->plugin_is_active( $slug ) ) {

				$plugins[ $slug ] = $plugin[ 'name' ];

			}

		}

		// Translations for scripts.
		wp_localize_script( 'codevz-dashboard', 'codevzWizard', [

			'plugins' 			=> $plugins,
			'of' 				=> Codevz_Core_Strings::get( 'of' ),
			'close' 			=> Codevz_Core_Strings::get( 'close' ),
			'plugin_before' 	=> Codevz_Core_Strings::get( 'plugin_before' ),
			'plugin_after' 		=> Codevz_Core_Strings::get( 'plugin_after' ),
			'import_before' 	=> Codevz_Core_Strings::get( 'import_before' ),
			'import_after' 		=> Codevz_Core_Strings::get( 'import_after' ),
			'codevz_plus' 		=> Codevz_Core_Strings::get( 'codevz_plus' ),
			'js_composer' 		=> Codevz_Core_Strings::get( 'js_composer' ),
			'elementor' 		=> Codevz_Core_Strings::get( 'elementor' ),
			'revslider' 		=> Codevz_Core_Strings::get( 'revslider' ),
			'cf7' 				=> Codevz_Core_Strings::get( 'cf7' ),
			'woocommerce' 		=> Codevz_Core_Strings::get( 'woocommerce' ),
			'downloading' 		=> Codevz_Core_Strings::get( 'downloading' ),
			'demo_files' 		=> Codevz_Core_Strings::get( 'demo_files' ),
			'downloaded' 		=> Codevz_Core_Strings::get( 'downloaded' ),
			'options' 			=> Codevz_Core_Strings::get( 'options' ),
			'widgets' 			=> Codevz_Core_Strings::get( 'widgets' ),
			'slider' 			=> Codevz_Core_Strings::get( 'slider' ),
			'posts' 			=> Codevz_Core_Strings::get( 'posts' ),
			'images' 			=> Codevz_Core_Strings::get( 'images' ),
			'error_500' 		=> Codevz_Core_Strings::get( 'error_500' ),
			'error_503' 		=> Codevz_Core_Strings::get( 'error_503' ),
			'ajax_error' 		=> Codevz_Core_Strings::get( 'ajax_error' ),
			'features' 			=> Codevz_Core_Strings::get( 'features' ),
			'feedback_empty' 	=> Codevz_Core_Strings::get( 'feedback_empty' ),
			'page_importer_empty' => Codevz_Core_Strings::get( 'page_importer_empty' )

		]);

	}

	/**
	 * Add admin menus.
	 * 
	 * @return array
	 */
	public function admin_menu() {

		// Check core plugin installed.
		//if ( ! Codevz_Core_Theme::$plugin && $this->is_free ) {
			//return false;
		//}

		// Icon.
		$icon = 'data:image/svg+xml;bas'.'e6'.'4,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMTEiIGhlaWdodD0iMjEzIiB2aWV3Qm94PSIwIDAgMjExIDIxMyI+IDxkZWZzPiA8c3R5bGU+IC5jbHMtMSB7IGZpbGw6ICNmZmY7IGZpbGwtcnVsZTogZXZlbm9kZDsgfSA8L3N0eWxlPiA8L2RlZnM+IDxwYXRoIGlkPSJDb2xvcl9GaWxsXzEiIGRhdGEtbmFtZT0iQ29sb3IgRmlsbCAxIiBjbGFzcz0iY2xzLTEiIGQ9Ik01Mi41MzMsMTYuMDI4Qzg2LjUyLDE1LjIxMSwxMTMuMDQ2LDQyLjYyLDk3LjgsNzcuMTM4Yy01LjcxNSwxMi45NDQtMTkuMDU0LDIwLjQ1LTMxLjk1NiwyMy45MTMtOS40NTIsMi41MzctMTkuMjY2LTEuNzQzLTIzLjk2Ny00LjQyOC0zLjM5NC0xLjkzOS02Ljk1LTIuMDI2LTkuNzY0LTQuNDI4LTguODQ0LTcuNTUtMjAuODIxLTI2Ljk1Ni0xNC4yLTQ2LjA1NGE0OC41NjEsNDguNTYxLDAsMCwxLDIzLjA4LTI2LjU3QzQ0Ljc1NywxNy42NTMsNDkuMTkzLDE4LjIxNyw1Mi41MzMsMTYuMDI4Wm05NC4wOTQsMGMxMS45MjItLjIxLDIyLjAyMS43MywyOS4yOTMsNS4zMTQsMTQuODkxLDkuMzg2LDI4LjYwNSwzNy45NDQsMTUuMDkxLDU5LjMzOS01Ljk2LDkuNDM2LTE3LjAxMiwxNy4yNjMtMjkuMjkzLDIwLjM3SDE0MS4zYy02LjYwOSwxLjYzOC0xNS40OTUsNC45NDktMjAuNDE3LDguODU3LTEwLjI0Niw4LjEzNi0xNi4wMjgsMjAuNS0xOS41MjgsMzUuNDI2djE5LjQ4NWMtNS4wMzYsMTguMDY4LTIzLjkxNywzOC45MTEtNDkuNzEsMzIuNzY5LTQuNzI0LTEuMTI0LTExLjA1Mi0yLjc3OC0xNS4wOS01LjMxMy01LjcxNC0zLjU4OC05LjU2LTkuMzgyLTEzLjMxNS0xNS4wNTdhNDUuMTUzLDQ1LjE1MywwLDAsMS02LjIxNC0xNC4xN2MtMS45LTcuODkzLjQ5NC0xNS4zNjgsMi42NjMtMjEuMjU2LDMuOTM5LTEwLjY5Myw5LjgyMi0yMC4yOTEsMTkuNTI5LTI0LjgsOC4zNTctMy44ODEsMTguMTcyLTIuNDgxLDI4LjQwNi01LjMxNCwxMi40NjYtMy40NTEsMjUuOTctMTAuMjYzLDMyLjg0NC0xOS40ODRBNjkuMTM5LDY5LjEzOSwwLDAsMCwxMTEuMTIsNjkuMTY3VjU0LjExMWMxLjQ2My02LjM1NywyLjk4NC0xMy42NzcsNi4yMTQtMTguNkMxMjIuMSwyOC4yNTYsMTMxLjEsMjEuMzE5LDEzOS41MjYsMTcuOCwxNDEuOTIsMTYuOCwxNDQuNzQ1LDE3LjI3MiwxNDYuNjI3LDE2LjAyOFptNTEuNDg1LDU0LjAyNWMwLjcxNCwwLjkuMzE1LDAuMjQzLDAuODg4LDEuNzcxaC0wLjg4OFY3MC4wNTNabS00Ni4xNTksNDIuNTEyYzI5LjMzMSwxLjM3OCw1Mi4xNjEsMjQuNjIsNDEuNzIxLDU1LjgtMS4zNTksNC4wNTgtMS4xMjIsOC40MzMtMy41NTEsMTEuNTEzLTYuNDI1LDguMTUyLTE4LjYsMTUuODM4LTMwLjE4MSwxOC42LTcuNzQ3LDEuODQ4LTE1LjE3LTEuNzM5LTE5LjUyOS0zLjU0My0zLjIzNi0xLjMzOS02LC4wNzktOC44NzYtMS43NzEtMTMuNC04LjYyNy0yNi4xMjktMzEuMTQ3LTE3Ljc1NC01My4xNCw0LjA4My0xMC43MjEsMTMuNzItMjAuMjY0LDIzLjk2Ny0yNC44QzE0MS43NDQsMTEzLjQ1NSwxNDguMiwxMTQuNzk0LDE1MS45NTMsMTEyLjU2NVoiLz4gPC9zdmc+';
		$icon = Codevz_Core_Theme::option( 'white_label_menu_icon', apply_filters( 'codevz_config_icon', $icon ) );

		// Add welcome theme menu.
		$title = Codevz_Core_Theme::option( 'white_label_theme_name', Codevz_Core_Strings::get( 'theme_name' ) );

		add_menu_page( $title, $title, 'manage_options', $this->slug, [ $this, 'activation' ], $icon, 2 );

		$position = 1;

		// Sub menus.
		foreach( $this->menus as $slug => $title ) {

			if ( $slug === 'uninstall' && ! get_option( 'xtra-downloaded-demo' ) ) {
				continue;
			}

			if ( $this->is_free && ( $slug === 'importer_page' || $slug === 'plugins' ) ) {

				$x = '';

			}

			if ( $slug === 'options' ) {

				add_submenu_page( $this->slug, $title, $title, 'manage_options', admin_url( 'customize.php' ), null, $position );

			} else {

				add_submenu_page( $this->slug, $title, $title, 'manage_options', 'theme-' . $slug, [ $this, $slug ], $position );

			}

			$position++;

		}

	}

	/**
	 * Render before any tab content.
	 * 
	 * @return string.
	 */
	private function render_before( $active = null ) {

		echo '<div class="wrap xtra-dashboard-' . esc_attr( $active ) . '">';

		echo '<div class="xtra-dashboard">';

		echo '<div class="xtra-dashboard-header">';

			$title = Codevz_Core_Theme::option( 'white_label_theme_name', Codevz_Core_Strings::get( 'theme_name' ) );

			echo '<img class="xtra-dashboard-logo" src="' . esc_html( Codevz_Core_Theme::option( 'white_label_welcome_page_logo', esc_url( Codevz_Core_Theme::$url ) . 'assets/img/dashboard.png' ) ) . '" alt="' . esc_attr( Codevz_Core_Strings::get( 'theme_name' ) ) . '" />';

			echo '<div class="xtra-dashboard-title">' . esc_html( Codevz_Core_Strings::get( 'welcome', $title ) ) . '<small><span>' . esc_html( $this->is_free ? Codevz_Core_Strings::get( 'not_active_dash' ) : Codevz_Core_Strings::get( 'activated_dash' ) ) . '</span>' . esc_html( Codevz_Core_Strings::get( 'version' ) ) . ' <strong>' . esc_html( $this->theme->version ) . '</strong></small></div>';

			// White label check envato banner.
			if ( ! isset( $this->disable[ 'envato' ] ) ) {

				echo wp_kses_post( apply_filters( 'codevz_buy_market', '<a href="' . esc_url( apply_filters( 'codevz_config_buy_link', '#' ) ) . '" class="xtra-market" target="_blank"><img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/envato.png" /></a>' ) );

			}

		echo '</div>';

		echo '<div class="xtra-dashboard-content">';

		echo '<div class="xtra-dashboard-menus">';

		$activation = get_option( $this->option );
		$activation = ( empty( $activation['purchase_code'] ) || ! empty( $_POST['deregister'] ) );

		foreach( $this->menus as $slug => $title ) {

			if ( $slug === 'uninstall' && ! get_option( 'xtra-downloaded-demo' ) ) {
				continue;
			}

			$link = ( $slug === 'options' ) ? 'customize.php' : 'admin.php?page=theme-' . $slug;

			$img = ( $slug === 'activation' && ! $activation ) ? 'activated' : $slug;

			$additional = '';

			if ( $this->is_free && ( $slug === 'importer_page' || $slug === 'plugins' ) ) {

				$x = '';

			}

			echo '<a href="' . esc_url( admin_url( $link ) ) . '" class="' . esc_attr( $active === $slug ? 'xtra-current' : '' ) . '"><img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/' . esc_attr( $img ) . '.png" /><span>' . strip_tags( $title ) . '</span>' . wp_kses_post( $additional ) . '</a>';

		}

		if ( isset( $this->disable[ 'faq' ] ) && isset( $this->disable[ 'docs' ] ) && isset( $this->disable[ 'youtube' ] ) && isset( $this->disable[ 'changelog' ] ) && isset( $this->disable[ 'ticksy' ] ) ) {
			$x = '';
		} else {
			echo '<div class="xtra-dashboard-menus-separator" aria-hidden="true"></div>';
		}

		if ( ! isset( $this->disable[ 'docs' ] ) ) {

			echo '<a href="' . esc_url( apply_filters( 'codevz_config_docs', '#' ) ) . '" target="_blank"><img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/docs.png" /><span>' . esc_html( Codevz_Core_Strings::get( 'documentation' ) ) . '</span></a>';

		}

		if ( ! isset( $this->disable[ 'youtube' ] ) ) {

			echo '<a href="' . esc_url( apply_filters( 'codevz_config_youtube', '#' ) ) . '" target="_blank"><img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/videos.png" /><span>' . esc_html( Codevz_Core_Strings::get( 'video_tutorials' ) ) . '</span></a>';

		}

		if ( ! isset( $this->disable[ 'changelog' ] ) ) {

			echo '<a href="' . esc_url( apply_filters( 'codevz_config_changelog_link', '#' ) ) . '" target="_blank"><img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/changelog.png" /><span>' . esc_html( Codevz_Core_Strings::get( 'change_log' ) ) . '</span></a>';

		}

		if ( ! isset( $this->disable[ 'ticksy' ] ) ) {

			echo '<a href="' . esc_url( apply_filters( 'codevz_config_support_link', '#' ) ) . '" target="_blank"><img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/support.png" /><span>' . esc_html( Codevz_Core_Strings::get( 'support' ) ) . '</span></a>';

		}

		if ( ! isset( $this->disable[ 'faq' ] ) ) {

			echo '<a href="' . esc_url( apply_filters( 'codevz_config_faq_link', '#' ) ) . '" target="_blank"><img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/faq.png" /><span>' . esc_html( Codevz_Core_Strings::get( 'faq' ) ) . '</span></a>';

		}

		echo '</div>';

		echo '<div class="xtra-dashboard-main">';

	}

	/**
	 * Activation tab content.
	 * 
	 * @return string.
	 */
	private function render_after() {

		echo '</div>'; // main.

		echo '</div>'; // content.

		echo '</div>'; // Dashboard.

		echo '</div>'; // Wrap.

	}

	/**
	 * Showing error or success message anywhere.
	 * 
	 * @return string.
	 */
	private function message( $type, $message ) {

		$icon = $type === 'error' ? 'no-alt' : ( $type === 'info' ? 'info-outline' : 'saved' );

		if ( $type === 'warning' ) {
			$icon = 'megaphone';
		}

		echo '<div class="xtra-dashboard-' . esc_attr( $type ) . '"><i class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></i><span>' . wp_kses_post( $message ) . '</span></div>';

	}

	/**
	 * Showing icon and text with custom style.
	 * 
	 * @return string.
	 */
	private function icon_box( $icon, $title, $link, $class = '' ) {

		if ( $class ) {
			$class = ' xtra-dashboard-icon-box-' . $class;
		}

		echo '<a href="' . esc_url( $link ) . '" class="xtra-dashboard-icon-box' . esc_attr( $class ) . '" target="_blank"><i class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></i><div>' . wp_kses_post( $title ) . '</div></a>';

	}

	/**
	 * Show activation successful message.
	 * 
	 * @return string.
	 */
	private function activated_successfully() {

		$activation = get_option( $this->option );

		if ( empty( $activation['purchase_code'] ) ) {

			delete_option( $this->option );

			header( "Refresh:0" );

		}
		
		$expired = current_time( 'timestamp' ) > strtotime( $activation['support_until'] );

		echo '<div class="xtra-certificate">';

			echo '<div class="xtra-certificate-title">' . esc_html( Codevz_Core_Strings::get( 'certificate' ) );

			echo '<form method="post"><input type="hidden" name="deregister" value="1"><input type="submit" value="' . esc_attr( Codevz_Core_Strings::get( 'deregister_license' ) ) . '"></form>';

			echo '</div>';

			echo '<div class="xtra-purchase-code">' . esc_html( Codevz_Core_Strings::get( 'purchase_code' ) ) . '<div>' . esc_html( str_replace( substr( $activation['purchase_code'], -12, 10 ), '************', $activation['purchase_code'] ) ) . '</div></div>';

			echo '<div class="xtra-purchase-details">';

			$this->icon_box( 'calendar', '<b>' . esc_html( Codevz_Core_Strings::get( 'purchase_date' ) ) . '</b><span>' . date( 'd F Y', strtotime( esc_html( $activation['purchase_date'] ) ) ) . '</span>', '#', 'info' );

			$this->icon_box( 'sos', '<b>' . esc_html( Codevz_Core_Strings::get( 'support_until' ) ) . '</b><span>' . date( 'd F Y', strtotime( esc_html( $activation['support_until'] ) ) ) . '</span>', '#', ( $expired ? 'error' : 'info' ) );

			echo '</div>';

		echo '</div>';

		if ( $expired ) {

			$this->message( 'error', esc_html( Codevz_Core_Strings::get( 'support_expired' ) ) );

		}

		$this->icon_box( 'sos', esc_html( Codevz_Core_Strings::get( 'extend' ) ), esc_url( apply_filters( 'codevz_config_buy_link', '#' ) ), 'info' );

	}

	/**
	 * Activation tab content.
	 * 
	 * @return string.
	 */
	public function activation() {

		$this->render_before( 'activation' );

		ob_start();

		do_action( 'codevz_dashboard_activation_before' );

		$action = ob_get_clean();

		if ( $action ) {

			echo wp_kses_post( $action );

			$this->render_after();

		} else {

			// Get saved activation.
			$activation = get_option( $this->option );

			// Purchase code.
			$purchase_code = isset( $activation[ 'purchase_code' ] ) ? $activation[ 'purchase_code' ] : null;

			echo '<div class="xtra-dashboard-section-title">' . esc_html( Codevz_Core_Strings::get( 'license_activation' ) ) . '</div>';

			$form = true;

			// Deregister license.
			if ( ! empty( $_POST['deregister'] ) ) {

				$this->message( 'success', esc_html( Codevz_Core_Strings::get( 'deregistered' ) ) );

			} else if ( $purchase_code ) {

				if ( isset( $_POST[ 'register' ] ) ) {

					$this->message( 'success', esc_html( Codevz_Core_Strings::get( 'congrats' ) ) . ', ' . esc_html( Codevz_Core_Strings::get( 'activated' ) ) );

				}

				$this->activated_successfully();

				$form = false;

			} else if ( ! empty( $_POST[ 'register' ] ) ) {

				$this->message( 'error', esc_html( Codevz_Core_Strings::get( 'insert' ) ) );

			}

			if ( $form ) {

				echo '<p>' . esc_html( Codevz_Core_Strings::get( 'activate_war' ) ) . '</p>';

				echo '<form class="xtra-dashboard-activation-form" method="post"><input type="text" name="register" placeholder="' . esc_attr( Codevz_Core_Strings::get( 'placeholder' ) ) . '" required><input type="submit" value="' . esc_attr( Codevz_Core_Strings::get( 'activate' ) ) . '"></form>';

				$this->icon_box( 'editor-help', esc_html( Codevz_Core_Strings::get( 'find' ) ), apply_filters( 'codevz_config_find_purchase', '#' ), 'info' );

				$this->icon_box( 'cart', esc_html( Codevz_Core_Strings::get( 'buy_new' ) ), esc_url( apply_filters( 'codevz_config_buy_link', '#' ) ), 'success' );

			}

			$this->render_after();

		}

	}

	/**
	 * Plugins installation tab content.
	 * 
	 * @return string.
	 */
	public function plugins() {

		$this->render_before( 'plugins' );

		echo '<div class="xtra-dashboard-section-title">' . esc_html( Codevz_Core_Strings::get( 'install' ) ) . '</div>';

		echo '<div class="xtra-plugins" data-nonce="' . esc_attr( wp_create_nonce( 'xtra-wizard' ) ) . '">';

		$plugins = 0;

		foreach( $this->plugins as $slug => $plugin ) {

			// Check plugin.
			if ( $this->plugin_is_active( $slug ) ) {
				continue;
			}

			echo '<div class="xtra-plugin">';

				echo '<div class="xtra-plugin-header">';

				echo '<img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/' . esc_attr( Codevz_Core_Theme::contains( $slug, 'codevz-plus' ) ? 'codevz-plus' : $slug ) . '.jpg" alt="' . esc_attr( $plugin[ 'name' ] ) . '" />';
				
				if ( isset( $plugin[ 'required' ] ) ) {

					$plugin[ 'name' ] .= '<small>' . esc_html( Codevz_Core_Strings::get( 'required' ) ) . '</small>';

				} else if ( isset( $plugin[ 'recommended' ] ) ) {

					$plugin[ 'name' ] .= '<small>' . esc_html( Codevz_Core_Strings::get( 'recommended' ) ) . '</small>';

				}

				echo '<span>' . wp_kses_post( $plugin[ 'name' ] ) . '</span>';

				echo '</div>';

				echo '<div class="xtra-plugin-footer">';

					echo '<div class="xtra-plugin-details">';

					if ( isset( $plugin[ 'source' ] ) ) {
						echo esc_html( Codevz_Core_Strings::get( 'private' ) ) . '<br /><span>' . esc_html( Codevz_Core_Strings::get( 'premium' ) ) . '</span>';
					} else {
						echo esc_html( Codevz_Core_Strings::get( 'wp' ) ) . '<br /><span>' . esc_html( Codevz_Core_Strings::get( 'free_ver' ) ) . '</span>';
					}

					echo '</div>';

					if ( file_exists( $this->plugin_file( $slug, true ) ) ) {

						$title = Codevz_Core_Strings::get( 'activate' );

						$activated = Codevz_Core_Strings::get( 'activated_s' );

					} else {

						$title = Codevz_Core_Strings::get( 'install_activate' );

						$activated = Codevz_Core_Strings::get( 'installed_activated' );

					}

					if ( $this->is_free && ( $slug === 'revslider' || $slug === 'js_composer' ) ) {

						$title = Codevz_Core_Strings::get( 'pro' );

						echo '<a href="' . esc_url( get_admin_url() ) . 'admin.php?page=theme-activation" class="xtra-button-primary codevz-button-pro"><span>' . esc_html( $title ) . '</span></a>';

					} else {

						echo '<a href="#" class="xtra-button-primary" data-plugin="' . esc_attr( $slug ) . '" data-title="' . esc_attr( Codevz_Core_Strings::get( 'please_wait' ) ) . '"><span>' . esc_html( $title ) . '</span><i class="xtra-loading" aria-hidden="true"></i></a>';

					}

					echo '<div class="xtra-plugin-activated hidden"><i class="dashicons dashicons-yes" aria-hidden="true"></i> ' . esc_html( $activated ) . '</div>';

				echo '</div>';

				echo '<div class="xtra-plugin-progress" aria-hidden="true"></div>';

			echo '</div>';

			$plugins++;

		}

		echo '</div>';

		if ( ! $plugins ) {

			$this->message( 'success', Codevz_Core_Strings::get( 'no_plugins' ) );

		}

		$this->render_after();

	}

	/**
	 * Demo importer tab content.
	 * 
	 * @return string.
	 */
	public function importer() {

		$this->render_before( 'importer' );

		$activation = get_option( $this->option );

		$demos_count = count( $this->demos ) - 1;

		// Start importer HTML.
		echo '<div class="xtra-demo-importer">';

		if ( Codevz_Core_Theme::$premium && $demos_count > 21 ) {

			echo '<div class="xtra-filters">';

				echo '<div class="xtra-filters-title"><span class="dashicons dashicons-filter"></span></div>';

				echo '<a href="#" data-filter="" class="xtra-filters-all xtra-current">' . esc_html( Codevz_Core_Strings::get( 'all' ) ) . '<span>' . esc_html( $demos_count ) . '</span></a>';

				echo '<a href="#" data-filter="service">' . esc_html( Codevz_Core_Strings::get( 'service' ) ) . '</a>';
				echo '<a href="#" data-filter="shop">' . esc_html( Codevz_Core_Strings::get( 'shop' ) ) . '</a>';
				echo '<a href="#" data-filter="blog">' . esc_html( Codevz_Core_Strings::get( 'blog' ) ) . '</a>';

				echo '<input type="search" name="search" placeholder="' . esc_html( Codevz_Core_Strings::get( 'type' ) ) . '" />';

				echo '<i class="dashicons dashicons-search" aria-hidden="true"></i>';

			echo '</div>';

		}

		echo '<div class="xtra-demos xtra-lazyload clearfix">';

		$api = apply_filters( 'codevz_config_api_demos', false );

		foreach( $this->demos as $demo => $args ) {

			if ( $demo === 'pro_line' ) {

				if ( $this->is_free ) {

					echo '<div class="codevz-pro-line"><span>' . esc_html( Codevz_Core_Strings::get( 'pro_line' ) ) . '</span></div>';

				}

				continue;

			}

			// Check free version.
			$is_pro = ( $this->is_free && empty( $args[ 'free' ] ) );

			$rtl 	= is_rtl() && isset( $args[ 'rtl' ] ) ? 'rtl/' : '';
			$folder = apply_filters( 'codevz_rtl_checker', $rtl );

			$preview = $rtl ? 'arabic/' : '';
			$preview = str_replace( 'api', $preview . esc_attr( $demo ), $api );
			$preview = apply_filters( 'codevz_rtl_preview', $preview );

			$args[ 'demo' ] = $demo;
			$args[ 'image' ] = $api . 'demos/' . $folder . esc_attr( $demo ) . '.jpg';
			$args[ 'preview' ] = $preview;

			echo '<div class="xtra-demo ' . ( $is_pro ? 'xtra-demo-pro' : ( $this->is_free ? 'xtra-demo-free' : '' ) ) . '">';

				$keywords = isset( $args[ 'keywords' ] ) ? $args[ 'keywords' ] : '';

				$keywords .= empty( $args[ 'rtl' ] ) ? '' : ' rtl arabic';
				$keywords .= isset( $args[ 'category' ] ) ? ' ' . $args[ 'category' ] : '';
				$keywords .= empty( $args[ 'js_composer' ] ) ? ' js_composer wpbakery' : '';

				if ( ! empty( $args[ 'elementor' ] ) || ! empty( $args[ 'rtl' ][ 'elementor' ] ) ) {
					$keywords .= ' elementor';
				}

				$keywords .= ' ' . $demo;

				// Pro bbadge.
				if ( $is_pro ) {

					echo '<div class="xtra-demo-pro-badge" title="' . esc_attr( Codevz_Core_Strings::get( 'activate_war' ) ) . '"><span>' . esc_attr( Codevz_Core_Strings::get( 'pro_word' ) ) . '</span></div>';

				} else if ( $this->is_free ) {

					echo '<div class="xtra-demo-free-badge" title="' . esc_attr( Codevz_Core_Strings::get( 'free_badge_title' ) ) . '"><span>' . esc_attr( Codevz_Core_Strings::get( 'free_word' ) ) . '</span></div>';

				}

				// Keywords.
				echo '<div class="hidden">' . esc_html( $keywords ) . '</div>';

				// Preview image.
				echo '<img data-src="' . esc_url( $args[ 'image' ] ) . '" />';

				// Demo title.
				echo '<div class="xtra-demo-title">' . esc_html( ucwords( str_replace( '-', ' ', isset( $args[ 'title' ] ) ? $args[ 'title' ] : $args[ 'demo' ] ) ) ) . '</div>';

				// Buttons.
				echo '<div class="xtra-demo-buttons">';

					if ( $is_pro ) {

						echo '<a href="' . esc_url( get_admin_url() ) . 'admin.php?page=theme-activation" class="xtra-button-primary">' . esc_html( Codevz_Core_Strings::get( 'pro' ) ) . '</a>';

					} else {

						echo '<a href="#" class="xtra-button-primary" data-args=\'' . esc_html( wp_json_encode( $args ) ) . '\'>' . esc_html( Codevz_Core_Strings::get( 'import' ) ) . '</a>';

					}

					if ( get_option( 'xtra_uninstall_' . $demo ) && Codevz_Core_Theme::$premium ) {

						echo '<a href="' . esc_url( get_admin_url() ) . 'admin.php?page=theme-uninstall" class="xtra-button-secondary xtra-uninstall-button">' . esc_html( Codevz_Core_Strings::get( 'uninstall' ) ) . '</a>';

					} else {

						$only_elementor = isset( $args[ 'only_elementor' ] );

						if ( Codevz_Core_Theme::contains( $args[ 'preview' ], 'arabic' ) ) {

							$args[ 'preview' ] = str_replace( '/' . $demo, ( $only_elementor ? '' : '-elementor/' ) . $demo, $args[ 'preview' ] );

						} else {

							$args[ 'preview' ] = str_replace( $demo, ( $only_elementor ? '' : 'elementor/' ) . $demo, $args[ 'preview' ] );

						}

						echo '<a href="' . esc_url( $args[ 'preview' ] ) . '" class="xtra-button-secondary" target="_blank">' . esc_html( Codevz_Core_Strings::get( 'preview' ) ) . '</a>';

					}

				echo '</div>';

			echo '</div>';

		}

		echo '</div>';

		echo '</div>';

		$is_free = $this->is_free;

		// Wizard.
		echo '<div class="xtra-wizard hidden" data-nonce="' . esc_attr( wp_create_nonce( 'xtra-wizard' ) ) . '">';

			echo '<i class="xtra-back dashicons dashicons-arrow-left-alt"><span>' . esc_html( Codevz_Core_Strings::get( 'back' ) ) . '</span></i>';

			echo '<div class="xtra-wizard-main">';

				echo '<div class="xtra-wizard-preview">';

					// Demo image.
					echo '<img class="xtra-demo-image" src="#" alt="Demo preview" />';

					// Progress bar.
					echo '<img class="xtra-importer-spinner" src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/importing.png" />';
					echo '<div class="xtra-wizard-progress"><div data-current="0"><span></span></div></div>';

				echo '</div>';

				echo '<div class="xtra-wizard-content">';

					// Step 1.
					echo '<div data-step="1" class="xtra-current">';

						echo '<div class="xtra-wizard-welcome"><span>' . esc_html( Codevz_Core_Strings::get( 'welcome_to' ) ) . '</span><strong>' . esc_html( Codevz_Core_Strings::get( 'wizard' ) ) . '</strong></div>';

						echo '<div class="xtra-wizard-selected"><span>' . esc_html( Codevz_Core_Strings::get( 'selected' ) ) . '</span><strong>...</strong></div>';

						echo '<div class="xtra-wizard-selected"><span>' . esc_html( Codevz_Core_Strings::get( 'live_preview' ) ) . '</span><br /><br />';

							echo '<a href="#" class="xtra-live-preview xtra-live-preview-elementor xtra-button-secondary" target="_blank">' . esc_html( Codevz_Core_Strings::get( 'elementor_s' ) ) . '</a>';

							echo '<a href="#" class="xtra-live-preview xtra-live-preview-wpbakery xtra-button-secondary" target="_blank">' . esc_html( Codevz_Core_Strings::get( 'wpbakery' ) ) . '</a>';

						echo '</div>';

					echo '</div>'; // step 1.

					// Step 2.
					echo '<div data-step="2">';

						echo '<div class="xtra-step-title">' . esc_html( Codevz_Core_Strings::get( 'choose' ) ) . '</div>';

						echo '<div class="xtra-image-radios">';
							echo '<label class="xtra-image-radio"><input type="radio" name="pagebuilder" value="elementor" checked /><span><img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/elementor.jpg"><b>' . esc_html( Codevz_Core_Strings::get( 'elementor_s' ) ) . '</b></span></label>';
							echo '<label class="xtra-image-radio"><input type="radio" name="pagebuilder" value="js_composer" /><span data-tooltip="' . esc_attr( Codevz_Core_Strings::get( 'ata' ) ) . '"><img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/js_composer.jpg"><b>' . esc_html( Codevz_Core_Strings::get( 'wpbakery' ) ) . '</b></span>' . ( $is_free ? ' <span class="xtra-pro" style="top:8px;right:20px;left:auto;bottom:auto">' . Codevz_Core_Strings::get( 'pro_word' ) . '</span>' : '' ) . '</label>';
						echo '</div>';

						echo do_shortcode( apply_filters( 'codevz_rtl_checkbox', '<label class="xtra-checkbox codevz-rtl' . ( $is_free ? ' xtra-readonly' : '' ) . '" data-tooltip="' . esc_attr( $is_free ? Codevz_Core_Strings::get( 'ata' ) : Codevz_Core_Strings::get( 'desc' ) ) . '">' . esc_html( Codevz_Core_Strings::get( 'rtl' ) ) . '<input type="checkbox" name="rtl" ' . ( is_rtl() ? 'checked' : '' ) . ' /><span class="checkmark" aria-hidden="true"></span>' . ( $is_free ? ' <span class="xtra-pro" style="position:static">' . Codevz_Core_Strings::get( 'pro_word' ) . '</span>' : '' ) . '</label>' ) );

					echo '</div>'; // step 2.

					// Step 3.
					echo '<div data-step="3">';

						echo '<label class="xtra-radio"><input type="radio" name="config" value="full" checked /><b>' . esc_html( Codevz_Core_Strings::get( 'full_import' ) ) . '</b><span class="checkmark" aria-hidden="true"></span></label>';
						echo '<label class="xtra-radio"><input type="radio" name="config" value="custom"' . ( $is_free ? 'disabled' : '' ). ' /><b>' . esc_html( Codevz_Core_Strings::get( 'custom_import' ) ) . '</b><span class="checkmark" aria-hidden="true"></span>' . ( $is_free ? '&nbsp;&nbsp;<a style="position:static" href="' . esc_url( get_admin_url() ) . 'admin.php?page=theme-activation" target="_blank" class="xtra-pro"><span>' . Codevz_Core_Strings::get( 'pro' ) . '</span></a>' : '' ) . '</label>';
						echo '<label class="xtra-radio"><input type="radio" name="config" value="options"' . ( $is_free ? 'disabled' : '' ). ' /><b>' . esc_html( Codevz_Core_Strings::get( 'custom_options' ) ) . '</b><span class="checkmark" aria-hidden="true"></span>' . ( $is_free ? '&nbsp;&nbsp;<a style="position:static" href="' . esc_url( get_admin_url() ) . 'admin.php?page=theme-activation" target="_blank" class="xtra-pro"><span>' . Codevz_Core_Strings::get( 'pro' ) . '</span></a>' : '' ) . '</label>';

						// Custom import.
						echo '<div class="xtra-checkboxes clearfix" disabled>';
							echo '<label class="xtra-checkbox">' . esc_html( Codevz_Core_Strings::get( 'options' ) ) . '<input type="checkbox" name="options" checked /><span class="checkmark" aria-hidden="true"></span></label>';
							echo '<label class="xtra-checkbox">' . esc_html( Codevz_Core_Strings::get( 'widgets' ) ) . '<input type="checkbox" name="widgets" checked /><span class="checkmark" aria-hidden="true"></span></label>';
							echo '<label class="xtra-checkbox">' . esc_html( Codevz_Core_Strings::get( 'posts' ) ) . '<input type="checkbox" name="content" checked /><span class="checkmark" aria-hidden="true"></span></label>';
							echo '<label class="xtra-checkbox">' . esc_html( Codevz_Core_Strings::get( 'media' ) ) . '<input type="checkbox" name="images" checked /><span class="checkmark" aria-hidden="true"></span></label>';
							echo '<label class="xtra-checkbox">' . esc_html( Codevz_Core_Strings::get( 'woocommerce' ) ) . '<input type="checkbox" name="woocommerce" checked /><span class="checkmark" aria-hidden="true"></span></label>';
							echo '<label class="xtra-checkbox">' . esc_html( Codevz_Core_Strings::get( 'revslider' ) ) . '<input type="checkbox" name="slider" checked /><span class="checkmark" aria-hidden="true"></span></label>';
						echo '</div>';

						// Custom theme options import.
						echo '<div class="xtra-checkboxes xtra-custom-options clearfix">';
							echo '<label class="xtra-checkbox">' . esc_html( Codevz_Core_Strings::get( 'options_general' ) ) . '<input type="checkbox" name="general" /><span class="checkmark" aria-hidden="true"></span></label>';
							echo '<label class="xtra-checkbox">' . esc_html( Codevz_Core_Strings::get( 'options_header' ) ) . '<input type="checkbox" name="header" checked /><span class="checkmark" aria-hidden="true"></span></label>';
							echo '<label class="xtra-checkbox">' . esc_html( Codevz_Core_Strings::get( 'options_footer' ) ) . '<input type="checkbox" name="footer" /><span class="checkmark" aria-hidden="true"></span></label>';
							echo '<label class="xtra-checkbox">' . esc_html( Codevz_Core_Strings::get( 'options_posts' ) ) . '<input type="checkbox" name="posts" /><span class="checkmark" aria-hidden="true"></span></label>';
							echo '<label class="xtra-checkbox">' . esc_html( Codevz_Core_Strings::get( 'options_portfolio' ) ) . '<input type="checkbox" name="post_type_portfolio" /><span class="checkmark" aria-hidden="true"></span></label>';
							echo '<label class="xtra-checkbox">' . esc_html( Codevz_Core_Strings::get( 'options_woocommerce' ) ) . '<input type="checkbox" name="post_type_product" /><span class="checkmark" aria-hidden="true"></span></label>';
						echo '</div>';

					echo '</div>'; // step 3.

					// Step 4.
					echo '<div data-step="4"><ul class="xtra-list"></ul></div>';

					// Step 5.
					echo '<div data-step="5">';

						// Success.
						echo '<div class="xtra-importer-done xtra-demo-success">';

							echo '<img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/tick.png" />';
							echo '<span>' . esc_html( Codevz_Core_Strings::get( 'congrats' ) ) . '</span>';
							echo '<p>' . esc_html( Codevz_Core_Strings::get( 'imported' ) ) . '</p>';

							echo '<a href="' . esc_url( get_home_url() ) . '" class="xtra-button-primary" target="_blank"> ' . esc_html( Codevz_Core_Strings::get( 'view_website' ) ) . ' </a>';
							echo '<a href="' . esc_url( get_admin_url() ) . 'customize.php" class="xtra-button-secondary" target="_blank"> ' . esc_html( Codevz_Core_Strings::get( 'customize' ) ) . ' </a>';

						echo '</div>';

						// Error.
						echo '<div class="xtra-importer-done xtra-demo-error hidden">';

							echo '<img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/error.png" />';
							echo '<span>' . esc_html( Codevz_Core_Strings::get( 'error' ) ) . '</span>';
							echo '<p>' . esc_html( Codevz_Core_Strings::get( 'occured' ) ) . '</p>';

							echo '<a href="' . esc_html( apply_filters( 'codevz_config_docs', '#' ) ) . '" class="xtra-button-primary" target="_blank"> ' . esc_html( Codevz_Core_Strings::get( 'troubleshooting' ) ) . ' </a>';
							echo '<a href="#" class="xtra-button-secondary xtra-back-to-demos"> ' . esc_html( Codevz_Core_Strings::get( 'back' ) ) . ' </a>';

						echo '</div>';

					echo '</div>'; // step 5.

				echo '</div>';

			echo '</div>';

			// Wizard footer.
			echo '<div class="xtra-wizard-footer">';

				echo '<a href="#" class="xtra-button-secondary xtra-wizard-prev">' . esc_html( Codevz_Core_Strings::get( 'prev_step' ) ) . '</a>';

				echo '<ul class="xtra-wizard-steps clearfix">';
					echo '<li data-step="1" class="xtra-current"><span>' . esc_html( Codevz_Core_Strings::get( 'getting_started' ) ) . '</span></li>';
					echo '<li data-step="2"><span>' . esc_html( Codevz_Core_Strings::get( 'choose_2' ) ) . '</span></li>';
					echo '<li data-step="3"><span>' . esc_html( Codevz_Core_Strings::get( 'config' ) ) . '</span></li>';
					echo '<li data-step="4"><span>' . esc_html( Codevz_Core_Strings::get( 'importing' ) ) . '</span></li>';
				echo '</ul>';

				echo '<a href="#" class="xtra-button-primary xtra-wizard-next">' . esc_html( Codevz_Core_Strings::get( 'next_step' ) ) . '</a>';

			echo '</div>';

		echo '</div>';

		$this->render_after();

	}

	/**
	 * Page importer.
	 * 
	 * @return string.
	 */
	public function importer_page() {

		$this->render_before( 'importer_page' );

		echo '<div class="xtra-dashboard-section-title">' . esc_html( Codevz_Core_Strings::get( 'single_page' ) ) . '</div>';

		if ( $this->is_free ) {

			$this->status_item( 'warning', wp_kses_post( Codevz_Core_Strings::get( 'page_pro', '<br />' ) ), '', '<a href="' . esc_url( get_admin_url() ) . 'admin.php?page=theme-activation" target="_blank">' . esc_html( Codevz_Core_Strings::get( 'pro' ) ) . '</a>', 'codevz-parent-pro' );

			$this->render_after();

			return;

		}

		if ( ! Codevz_Core_Theme::option( 'site_color_sec' ) ) {

			$this->message( 'warning', esc_html( Codevz_Core_Strings::get( 'page_import_war' ) ) );

		}

		echo '<p style="font-size:14px;color:#7e7e7e;">' . esc_html( Codevz_Core_Strings::get( 'page_insert' ) ) . '</p>';

		echo '<br /><form class="xtra-page-importer-form">';

			echo '<input type="url" placeholder="' . esc_attr( Codevz_Core_Strings::get( 'page_insert_link' ) ) . '" />';

			echo '<a href="#" class="xtra-button-primary" data-nonce="' . esc_attr( wp_create_nonce( 'xtra-page-importer' ) ) . '"><span>' . esc_html( Codevz_Core_Strings::get( 'import' ) ) . '</span><i class="xtra-loading" aria-hidden="true"></i></a>';

			echo '<br /><br /><br /><span class="xtra-page-importer-message" aria-hidden="true"></span>';

		echo '</form>';

		$this->render_after();

	}

	/**
	 * Single page importer AJAX request.
	 * 
	 * @return JSON
	 */
	public function page_importer_ajax() {

		check_ajax_referer( 'xtra-page-importer', 'nonce' );

		// Check activation.
		if ( $this->is_free ) {

			wp_send_json(
				[
					'status' 	=> '202',
					'message' 	=> Codevz_Core_Strings::get( 'activation_error' )
				]
			);

		}

		// Check requested URL.
		if ( ! empty( $_POST[ 'url' ] ) ) {

			$url = sanitize_text_field( wp_unslash( $_POST[ 'url' ] ) );

			if ( filter_var( $url, FILTER_VALIDATE_URL ) === FALSE || ! Codevz_Core_Theme::contains( $url, [ 'xtratheme', 'themetor', 'codevz', 'weebtheme' ] ) ) {

				wp_send_json(
					[
						'status' 	=> '202',
						'message' 	=> Codevz_Core_Strings::get( 'valid_url' )
					]
				);

			}

			$url = sanitize_text_field( $url );

			// Check Elementor plugin.
			if ( Codevz_Core_Theme::contains( $url, '/elementor' ) && ! $this->plugin_is_active( 'elementor' ) ) {

				$data = $this->install_plugin( 'elementor' );

				if ( is_string( $data ) ) {

					wp_send_json(
						[

							'status' 	=> '202',
							'message' 	=> esc_html( Codevz_Core_Strings::get( 'find_plugin', 'elementor' ) )

						]
					);

				}

			}

			// Get requested page content.
			$response = wp_remote_get( $url . '?export_single_page=' . $url, [ 'sslverify' => false, 'timeout' => 300 ] );

			if ( is_wp_error( $response ) ) {

				wp_send_json(
					[
						'status' 	=> '202',
						'message' 	=> $response->get_error_message()
					]
				);

			}

			// Check data.
			if ( empty( $response['body'] ) && ! ini_get( 'allow_url_fopen' ) ) {

				wp_send_json(
					[
						'status' 	=> '202',
						'message' 	=> Codevz_Core_Strings::get( 'allow_url_fopen' )
					]
				);

			}

			if ( ! empty( $response[ 'body' ] ) ) {

				$response = json_decode( $response['body'], true );

				if ( ! empty( $response[ 'page' ] ) ) {

					// Start.
					$page = json_decode( $response[ 'page' ] );

					$page->ID = null;

					$page_exist = get_page_by_path( $page->post_name );

					if ( ! empty( $page_exist->ID ) ) {
						$page->post_name = $page->post_name . wp_rand( 111, 999 );
					}

					$page->post_title = $page->post_title . ' (Imported)';

					// Replace colors.
					if ( $page->post_content ) {

						if ( $response[ 'color2' ] ) {
							$color2 = Codevz_Core_Theme::option( 'site_color_sec' ) ? Codevz_Core_Theme::option( 'site_color_sec' ) : $response[ 'color1' ];
							$page->post_content = Codevz_Options::updateDatabase( $response[ 'color2' ], $color2, $page->post_content );
						}

						if ( $response[ 'color1' ] ) {
							$page->post_content = Codevz_Options::updateDatabase( $response[ 'color1' ], Codevz_Core_Theme::option( 'site_color' ), $page->post_content );
						}

					}

					$post_id = wp_insert_post( $page );

					if ( $post_id && ! empty( $response[ 'meta' ] ) ) {

						$meta = wp_json_encode( $response[ 'meta' ] );

						if ( $response[ 'color2' ] ) {
							$color2 = Codevz_Core_Theme::option( 'site_color_sec' ) ? Codevz_Core_Theme::option( 'site_color_sec' ) : $response[ 'color1' ];
							$meta = Codevz_Options::updateDatabase( $response[ 'color2' ], $color2, $meta );
							$meta = Codevz_Options::updateDatabase( strtoupper( $response[ 'color2' ] ), strtoupper( $color2 ), $meta );
						}

						if ( $response[ 'color1' ] ) {
							$meta = Codevz_Options::updateDatabase( $response[ 'color1' ], Codevz_Core_Theme::option( 'site_color' ), $meta );
							$meta = Codevz_Options::updateDatabase( strtoupper( $response[ 'color1' ] ), strtoupper( Codevz_Core_Theme::option( 'site_color' ) ), $meta );
						}

						$meta = Codevz_Demo_Importer::replace_upload_url( $meta, true );

						$meta = Codevz_Demo_Importer::replace_demo_link( $meta, false, false, 'elementor/' );
						$meta = Codevz_Demo_Importer::replace_demo_link( $meta, true, false, 'elementor/' );

						update_post_meta( $post_id, '_elementor_data', wp_slash_strings_only( $meta ) );
						update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
						update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
						update_post_meta( $post_id, '_elementor_version', '3.4.3' );

					}

					// Get code.
					$code = get_option( $this->option );
					$code = empty( $code['purchase_code'] ) ? '' : $code['purchase_code'];

					// Stats.
					$prms = [

						'api' 		=> apply_filters( 'codevz_config_api_demos', false ),
						'code' 		=> $code,
						'page' 		=> str_replace( [ 'http://', 'https://', '.', '/' ], [ '', '', '-', '_' ], rtrim( $url, '/\\' ) ),
						'builder' 	=> Codevz_Core_Theme::contains( $url, 'elementor' ) ? 'elementor' : 'wpbakery',
						'domain' 	=> get_permalink( $post_id )

					];

					$stats = wp_remote_get( 'https://codevz.com/importer/?import_page=' . wp_json_encode( $prms ) );

					wp_send_json(
						[
							'status' 	=> '200',
							'message' 	=> Codevz_Core_Strings::get( 'page_imported' ),
							'link' 		=> get_permalink( $post_id )
						]
					);

				} else if ( ! empty( $response[ 'message' ] ) ) {

					wp_send_json(
						[
							'status' 	=> '202',
							'message' 	=> $response[ 'message' ]
						]
					);

				} else if ( is_wp_error( $response ) ) {

					wp_send_json(
						[
							'status' 	=> '202',
							'message' 	=> $response->get_error_message()
						]
					);

				} else {

					wp_send_json(
						[
							'status' 	=> '202',
							'message' 	=> Codevz_Core_Strings::get( 'try_again' )
						]
					);

				}

			}

			wp_send_json(
				[
					'status' 	=> '202',
					'message' 	=> Codevz_Core_Strings::get( 'responding' )
				]
			);

		}

		wp_send_json(
			[
				'status' 	=> '202',
				'message' 	=> Codevz_Core_Strings::get( 'wrong' )
			]
		);

	}

	/**
	 * System status item content.
	 * 
	 * @return string.
	 */
	private function status_item( $type, $title, $value, $badge, $class = '' ) {

		echo '<div class="xtra-ss-item xtra-dashboard-' . esc_attr( $type === 'error' ? 'error' : ( $type === 'warning' ? 'warning' : 'success' ) ) . ' ' . esc_attr( $class ) . '">';

			echo '<img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/' . esc_attr( $type === 'error' ? 'error' : ( $type === 'warning' ? 'warning' : 'tick' ) ) . '.png" />';

			echo '<b>' . wp_kses_post( $title ) . '</b>';

			echo '<span>' . wp_kses_post( $value ) . '<i>' . wp_kses_post( $badge ) . '</i></span>';

		echo '</div>';

	}

	/**
	 * System status tab content.
	 * 
	 * @return string.
	 */
	public function status() {

		$this->render_before( 'status' );

		echo '<div class="xtra-dashboard-section-title">' . esc_html( Codevz_Core_Strings::get( 'status' ) ) . '</div>';

		echo '<div class="xtra-system-status">';

		// Theme Activated or no.
		if ( ! $this->is_free ) {

			$this->status_item( 'success', esc_html( Codevz_Core_Strings::get( 'tas' ) ), '', esc_html( Codevz_Core_Strings::get( 'good' ) ) );

		} else {

			$this->status_item( 'warning', esc_html( Codevz_Core_Strings::get( 'not_active' ) ), '', '<a href="' . esc_url( get_admin_url() ) . 'admin.php?page=theme-activation" target="_blank">' . esc_html( Codevz_Core_Strings::get( 'pro' ) ) . '</a>', 'codevz-parent-pro' );

		}

		// PHP version.
		if ( version_compare( phpversion(), '7.0.0', '>=' ) ) {

			$this->status_item( 'success', esc_html( Codevz_Core_Strings::get( 'php_ver' ) ), phpversion(), esc_html( Codevz_Core_Strings::get( 'good' ) ) );

		} else {

			$this->status_item( 'error', esc_html( Codevz_Core_Strings::get( 'php_ver' ) ), phpversion(), esc_html( Codevz_Core_Strings::get( 'php_error' ) ) );

		}

		// PHP Memory limit.
		$memory_limit = ini_get( 'memory_limit' );
		if ( (int) $memory_limit >= 128 || (int) $memory_limit < 0 ) {

			$this->status_item( 'success', esc_html( Codevz_Core_Strings::get( 'php_memory' ) ), $memory_limit, esc_html( Codevz_Core_Strings::get( 'good' ) ) );

		} else {

			$this->status_item( 'error', esc_html( Codevz_Core_Strings::get( 'php_memory' ) ), $memory_limit, esc_html( Codevz_Core_Strings::get( '128m' ) ) );

		}

		// PHP post max size.
		$pms = ini_get( 'post_max_size' );
		if ( (int) $pms >= 8 ) {

			$this->status_item( 'success', esc_html( Codevz_Core_Strings::get( 'max_size' ) ), $pms, esc_html( Codevz_Core_Strings::get( 'good' ) ) );

		} else {

			$this->status_item( 'error', esc_html( Codevz_Core_Strings::get( 'max_size' ) ), $pms, esc_html( Codevz_Core_Strings::get( '8r' ) ) );

		}

		// PHP max execution time.
		$met = ini_get( 'max_execution_time' );
		if ( (int) $met >= 30 ) {

			$this->status_item( 'success', esc_html( Codevz_Core_Strings::get( 'execution' ) ), $met, esc_html( Codevz_Core_Strings::get( 'good' ) ) );

		} else {

			$this->status_item( 'error', esc_html( Codevz_Core_Strings::get( 'execution' ) ), $met, esc_html( Codevz_Core_Strings::get( '30r' ) ) );

		}

		// cURL.
		if ( function_exists( 'curl_version' ) ) {

			$this->status_item( 'success', esc_html( Codevz_Core_Strings::get( 'server_php' ) ) . ' cURL', esc_html( Codevz_Core_Strings::get( 'active' ) ), esc_html( Codevz_Core_Strings::get( 'good' ) ) );

		} else {

			$this->status_item( 'error', esc_html( Codevz_Core_Strings::get( 'curl' ) ), '', esc_html( Codevz_Core_Strings::get( 'contact' ) ) );

		}

		// allow_url_fopen.
		if ( ini_get( 'allow_url_fopen' ) ) {

			$this->status_item( 'success', esc_html( Codevz_Core_Strings::get( 'server_php' ) ) . ' allow_url_fopen', esc_html( Codevz_Core_Strings::get( 'active' ) ), esc_html( Codevz_Core_Strings::get( 'good' ) ) );

		} else {

			$this->status_item( 'error', esc_html( Codevz_Core_Strings::get( 'fopen' ) ), '', esc_html( Codevz_Core_Strings::get( 'contact' ) ) );

		}

		echo '</div>';

		$this->render_after();

	}

	/**
	 * Feedback tab content.
	 * 
	 * @return string.
	 */
	public function feedback() {

		$this->render_before( 'feedback' );

		echo '<div class="xtra-dashboard-section-title">' . esc_html( Codevz_Core_Strings::get( 'feedback' ) ) . '</div>';

		$this->message( 'warning', esc_html( Codevz_Core_Strings::get( 'please_help', Codevz_Core_Strings::get( 'theme_name' ) ) ) );

		echo '<p style="font-size:14px;color:#7e7e7e;">' . esc_html( Codevz_Core_Strings::get( 'thanks', Codevz_Core_Strings::get( 'theme_name' ) ) ) . '</p>';

		echo '<br /><form class="xtra-feedback-form">';

			wp_editor( false, 'xtra-feedback', [ 'media_buttons' => true, 'textarea_rows' => 10 ] );

			echo '<br /><br /><a href="#" class="xtra-button-primary" data-nonce="' . esc_attr( wp_create_nonce( 'xtra-feedback' ) ) . '"><span>' . esc_html( Codevz_Core_Strings::get( 'submit' ) ) . '</span><i class="xtra-loading" aria-hidden="true"></i></a>';

			echo '<br /><br /><br /><span class="xtra-feedback-message" aria-hidden="true"></span>';

		echo '</form>';

		$this->render_after();

	}

	/**
	 * AJAX process feedback form message send to email.
	 * 
	 * @return string.
	 */
	public function feedback_submit() {

		check_ajax_referer( 'xtra-feedback', 'nonce' );

		if ( ! empty( $_POST[ 'message' ] ) ) {

			// Form.
			$from = get_option( 'admin_email' ); 
			$subject = 'XTRA Feedback';
			$sender = 'From: ' . get_bloginfo( 'name' ) . ' <' . $from . '>' . "\r\n";

			// Message.
			$message = wp_kses_post( wp_unslash( $_POST[ 'message' ] ) );
			$message .= '<br /><br />';
			$message .= get_home_url();
			$message .= '<br />';
			$message .= 'Theme: ' . Codevz_Core_Strings::get( 'theme_name' ) . ' - v' . $this->theme->version;

			// Headers.
			$headers[] = 'MIME-Version: 1.0' . "\r\n";
			$headers[] = 'Content-type: text/html; charset=UTF-8' . "\r\n";
			$headers[] = "X-Mailer: PHP \r\n";
			$headers[] = $sender;

			$mail = '';

			// Send feedback.
			if ( method_exists( 'Codevz_Plus', 'sendMail' ) ) {

				$mail = Codevz_Plus::sendMail( 'codevzz@gmail.com', $subject, $message, $headers );

			}

			if ( $mail ) {

				wp_send_json(
					[
						'status' 	=> '200',
						'message' 	=> esc_html( Codevz_Core_Strings::get( 'sent' ) )
					]
				);

			} else {

				wp_send_json(
					[
						'status' 	=> '202',
						'message' 	=> esc_html( Codevz_Core_Strings::get( 'sent_error' ) )
					]
				);

			}

		}

		wp_send_json(
			[
				'status' 	=> '202',
				'message' 	=> esc_html( Codevz_Core_Strings::get( 'no_msg' ) )
			]
		);

	}

	/**
	 * Uninstall demo tab content.
	 * 
	 * @return string.
	 */
	public function uninstall() {

		$this->render_before( 'uninstall' );

		echo '<div class="xtra-demos xtra-uninstall xtra-lazyload clearfix">';

		echo '<div class="xtra-dashboard-section-title">' . esc_html( Codevz_Core_Strings::get( 'un_demos' ) ) . '</div>';

		echo '<p class="xtra-uninstall-p">' . esc_html( Codevz_Core_Strings::get( 'un_desc' ) ) . '</p>';

		$has_demo = false;

		foreach ( $this->demos as $demo => $args ) {

			if ( get_option( 'xtra_uninstall_' . $demo ) ) {

				$has_demo = true;

				$rtl 	= is_rtl() && isset( $args[ 'rtl' ] ) ? 'rtl/' : '';
				$folder = apply_filters( 'codevz_rtl_checker', $rtl );

				echo '<div class="xtra-demo">';

					echo '<img data-src="' . esc_url( apply_filters( 'codevz_config_api_demos', apply_filters( 'codevz_config_api', false ) ) . 'demos/' . $folder . esc_attr( $demo ) . '.jpg' ) . '" />';

					echo '<div class="xtra-demo-title">' . esc_html( ucwords( str_replace( '-', ' ', isset( $args[ 'title' ] ) ? $args[ 'title' ] : $demo ) ) ) . '</div>';

					echo '<div class="xtra-demo-buttons">';

						echo '<a href="#" class="xtra-button-primary xtra-uninstall-button" data-demo="' . esc_html( $demo ) . '" data-title="' . esc_attr( Codevz_Core_Strings::get( 'wait' ) ) . '"><span>' . esc_html( Codevz_Core_Strings::get( 'uninstall' ) ) . '</span></a>';

					echo '</div>';

				echo '</div>';

			}

		}

		if ( ! $has_demo ) {

			$this->message( 'info', esc_html( Codevz_Core_Strings::get( 'yet' ) ) );

		}

		echo '</div>';

		echo '<div class="xtra-modal" data-nonce="' . esc_attr( wp_create_nonce( 'xtra-wizard' ) ) . '">';

			echo '<div class="xtra-modal-inner">';

				echo '<div class="xtra-uninstall-msg">';

					echo '<div class="xtra-dashboard-section-title"><img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/error.png" />' . esc_html( Codevz_Core_Strings::get( 'are_you_sure' ) ) . '</div>';

					echo '<p>' . esc_html( Codevz_Core_Strings::get( 'delete' ) ) . '</p>';

					echo '<img class="xtra-importer-spinner" src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/importing.png" />';

					echo '<a href="#" class="xtra-button-secondary"> ' . esc_html( Codevz_Core_Strings::get( 'no' ) ) . ' </a>';
					echo '<a href="#" class="xtra-button-primary" data-uninstall="' . esc_html( Codevz_Core_Strings::get( 'yes' ) ) . '" data-done="' . esc_attr( Codevz_Core_Strings::get( 'uninstalling' ) ) . '"> ' . esc_html( Codevz_Core_Strings::get( 'yes' ) ) . ' </a>';

				echo '</div>';

				// Done message.
				echo '<div class="xtra-uninstalled hidden">';
					echo '<img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/tick.png" />';
					echo '<h2>' . esc_html( Codevz_Core_Strings::get( 'demo_uninstalled' ) ) . '</h2>';
					echo '<a href="#" class="xtra-button-primary xtra-reload"> ' . esc_html( Codevz_Core_Strings::get( 'reload' ) ) . ' </a>';
					echo '<a href="#" class="xtra-button-secondary"> ' . Codevz_Core_Strings::get( 'close' ) . ' </a>';
				echo '</div>';

			echo '</div>';

		echo '</div>';

		$this->render_after();

	}

	/**
	 * Deregister license and delete activation option.
	 * 
	 * @return -
	 */
	public function deregister( $code, $envato ) {

		if ( ! $envato ) {
			$verify = wp_remote_get( 'https://xtratheme.com?type=deregister&domain=' . $this->get_host_name() . '&code=' . $code, [ 'sslverify' => false, 'timeout' => 300 ] );
		}

		delete_option( $this->option );

		return true;

	}

	/**
	 * Register license and add activation option to database.
	 * 
	 * @return -
	 */
	public function register( $code, $envato ) {

		$item_id 		= apply_filters( 'codevz_config_item_id', '' );
		$personalToken 	= apply_filters( 'codevz_config_token_key', '' );

		// Skip without item ID.
		if ( ! $item_id ) {
			return false;
		}

		// Check envato.
		if ( $envato ) {

			// Unique host name.
			$userAgent = "Purchase code verification on " . $this->get_host_name();

			// Surrounding whitespace can cause a 404 error, so trim it first
			$code = trim( $code );

			// Make sure the code looks valid before sending it to Envato
			if ( ! preg_match( "/^([a-f0-9]{8})-(([a-f0-9]{4})-){3}([a-f0-9]{12})$/i", $code ) ) {

				return 'Envato error: Your license code is invalid.';

			}

			// Build the request
			$response = wp_remote_get( "https://api.envato.com/v3/market/author/sale?code={$code}", [
				'headers' => [
					'Authorization' => "Bearer {$personalToken}",
					'User-Agent' 	=> "{$userAgent}",
				],
				'sslverify' => false,
				'timeout' => 300
			]);

			// Handle connection errors (such as an API outage)
			// You should show users an appropriate message asking to try again later
			if ( is_wp_error( $response ) ) { 
			    return $response->get_error_message();
			}

			// If we reach this point in the code, we have a proper response!
			// Let's get the response code to check if the purchase code was found
			$responseCode = wp_remote_retrieve_response_code( $response );

			// HTTP 404 indicates that the purchase code doesn't exist
			if ( $responseCode === 404 ) {

			    return 'Envato error: The purchase code does not exist.';

			}

			// Anything other than HTTP 200 indicates a request or API error
			// In this case, you should again ask the user to try again later
			if ( $responseCode !== 200 ) {
				return 'Envato error: Failed to validate code due to an HTTP error: ' . $responseCode;
			}

			$response = wp_remote_retrieve_body( $response );

			// Parse the response into an object with warnings supressed
			$body = $response ? json_decode( $response , true ) : [];

			if ( ! isset( $body[ 'sold_at' ] ) ) {
				return 'Envato error: Please try again in 10 seconds.';
			}

			// Check for errors while decoding the response (PHP 5.3+)
			if ( $body === false && json_last_error() !== JSON_ERROR_NONE ) {
				return 'Envato error: Parsing response.';
			}

			// If item id is wrong
			if ( isset( $body['item']['id'] ) && $body['item']['id'] != $item_id ) {
				return 'Envato error: Your purchase code is valid but it seems its for another item, Please add correct purchase code.';
			}

			// Compatibility with envato plugin.
			update_option( 'envato_purchase_code_' . $body['item']['id'], $code );

			// Save verified data.
			update_option( $this->option, [
				'type'			=> 'success',
				'themeforest'	=> true,
				'item_id' 		=> $body['item']['id'],
				'purchase_code' => $code,
				'purchase_date' => $body[ 'sold_at' ],
				'support_until' => $body[ 'supported_until' ]
			] );

			return true;

		} else {

			// Verify purchase on xtratheme.com for old users between 2019-2021
			$verify = wp_remote_get( 'https://xtratheme.com?type=register&domain=' . $this->get_host_name() . '&code=' . $code, [ 'sslverify' => false, 'timeout' => 300 ] );

			if ( is_wp_error( $verify ) ) {

				return $verify->get_error_message();

			} else if ( ! isset( $verify['body'] ) ) {

				return 'Envato error: Please try again in 10 seconds.';

			} else {

				$verify = json_decode( $verify['body'], true );

				if ( isset( $verify['type'] ) && $verify['type'] === 'error' ) {
					return $verify['message'];
				}

				if ( ! isset( $verify['purchase_code'] ) ) {

					return 'DB error: Your license is invalid, Please check your code and try again ...';

				}

			}

			// Registered successfully.
			update_option( $this->option, $verify );

			return true;

		}

	}

	/**
	 * Get current site host name.
	 * 
	 * @return string
	 */
	public function get_host_name( $url = '' ) {

		$pieces = parse_url( $url ? $url : get_home_url() );

		$domain = isset( $pieces['host'] ) ? $pieces['host'] : '';

		if ( preg_match( '/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs ) ) {
			return $regs['domain'];
		}

		return $domain;

	}

	/**
	 * Plugin installation and importer AJAX function.
	 * @return string
	 */
	public function wizard() {

		check_ajax_referer( 'xtra-wizard', 'nonce' );

		if ( ! empty( $_POST ) ) {

			$_POST = wp_unslash( $_POST );

		}

		// Import posts meta.
		if ( ! empty( $_POST[ 'meta' ] ) ) {

			wp_send_json(
				Codevz_Demo_Importer::import_process(
					[ 'meta' => 1 ]
				)
			);

		}

		// Check name.
		if ( empty( $_POST[ 'name' ] ) ) {

			wp_send_json(
				[
					'status' 	=> '202',
					'message' 	=> esc_html( Codevz_Core_Strings::get( 'ajax_error' ) )
				]
			);

		}

		// Fix redirects after plugin installation.
		if ( $_POST[ 'name' ] === 'redirect' ) {

			wp_send_json(
				[
					'status' 	=> '200',
					'message' 	=> 'Successfully redirected'
				]
			);

		}

		// Vars.
		$data = [];
		$name = isset( $_POST[ 'name' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'name' ] ) ) : '';
		$type = isset( $_POST[ 'type' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'type' ] ) ) : '';
		$demo = isset( $_POST[ 'demo' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'demo' ] ) ) : '';
		$parts = isset( $_POST[ 'parts' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'parts' ] ) ) : '';

		// Deactivate some plugins.
		if ( function_exists( 'elementor_pro_load_plugin' ) ) {
			deactivate_plugins( 'elementor-pro/elementor-pro.php' );
		}

		// Install & activate plugin.
		if ( $type === 'plugin' ) {

			$data = $this->install_plugin( $name );

			if ( is_string( $data ) ) {

				$data = [

					'status' 	=> '202',
					'message' 	=> esc_html( Codevz_Core_Strings::get( 'find_plugin', $name ) )

				];

			}

		// Download demo files.
		} else if ( $type === 'download' ) {

			// Check codevz plus.
			if ( ! class_exists( 'Codevz_Demo_Importer' ) ) {

				wp_send_json(
					[
						'status' 	=> '202',
						'message' 	=> esc_html( Codevz_Core_Strings::get( 'cp_error' ) )
					]
				);

			}

			$folder = isset( $_POST[ 'folder' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'folder' ] ) ) : '';

			$data = Codevz_Demo_Importer::download( $demo, $folder );

		// Import demo data.
		} else if ( $type === 'import' ) {

			$data = Codevz_Demo_Importer::import_process(
				[
					'demo' 			=> $demo,
					'features' 		=> [ $name ],
					'parts' 		=> $parts,
					'posts' 		=> empty( $_POST[ 'posts' ] ) ? 1 : sanitize_text_field( wp_unslash( $_POST[ 'posts' ] ) )
				]
			);

		// Uninstall demo data.
		} else if ( $type === 'uninstall' ) {

			$data = $this->uninstall_demo( $demo );

		} else {

			$data = [
				'status' 	=> '202',
				'message' 	=> esc_html( Codevz_Core_Strings::get( 'occured' ) )
			];

		}

		wp_send_json( $data );

	}

	/**
	 * Plugin installation and activation process.
	 * 
	 * @return array
	 */
	protected function install_plugin( $plugin = '' ) {

		// Plugin slug.
		$slug = esc_html( urldecode( $plugin ) );

		// Check plugin inside plugins.
		if ( ! isset( $this->plugins[ $slug ] ) ) {

			return [

				'status' 	=> '202',
				'message' 	=> esc_html( Codevz_Core_Strings::get( 'listed', $slug ) )

			];

		}

		// Pass necessary information via URL if WP_Filesystem is needed.
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'plugin' 	=> urlencode( $slug )
				),
				admin_url( 'admin-ajax.php' )
			),
			'xtra-wizard',
			'nonce'
		);

		if ( false === ( $creds = request_filesystem_credentials( esc_url_raw( $url ), '', false, false, [] ) ) ) {

			return [

				'status' 	=> '202',
				'message' 	=> 'Error: WordPress required FTP login details'

			];

		}

		// Prep variables for Plugin_Installer_Skin class.
		if ( isset( $this->plugins[ $slug ][ 'source' ] ) ) {
			$api = null;
			$source = $this->plugins[ $slug ][ 'source' ];
		} else {
			$api = $this->plugins_api( $slug );
			if ( is_string( $api ) ) {
				return [

					'status' 	=> '202',
					'message' 	=> wp_kses_post( 'WordPress API Error: ' . $api )

				];
			}
			$source = isset( $api->download_link ) ? $api->download_link : '';
		}

		// Check ZIP file.
		if ( ! $source ) {

			return [

				'status' 	=> '202',
				'message' 	=> esc_html( Codevz_Core_Strings::get( 'manually', $slug ) )

			];

		}

		$url = add_query_arg(
			array(
				'plugin' => urlencode( $slug )
			),
			'update.php'
		);

		if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$skin_args = array(
			'type'   => 'web',
			'title'  => $this->plugins[ $slug ]['name'],
			'url'    => esc_url_raw( $url ),
			'nonce'  => 'xtra-wizard',
			'plugin' => '',
			'api'    => $source ? null : $api,
			'extra'  => [ 'slug' => $slug ]
		);

		$skin = new Plugin_Installer_Skin( $skin_args );

		// Create a new instance of Plugin_Upgrader.
		$upgrader = new Plugin_Upgrader( $skin );

		// File path.
		$file = $this->plugin_file( $slug, true );

		// FIX: Check if file is not exist but folder exist. 
		$folder = dirname( $file );

		if ( ! file_exists( $file ) && is_dir( $folder ) ) {

			rename( $folder, $folder . '_backup_' . wp_rand( 111, 999 ) );

		}

		// Install plugin.
		if ( ! file_exists( $file ) ) {

			$upgrader->install( $source );

		}

		// Install plugin manually.
		if ( ! file_exists( $file ) ) {

			$plugin_dir = dirname( $file );

			// Final check if plugin installed?
			if ( ! file_exists( $file ) || is_dir( $plugin_dir ) ) {

				return [

					'status' 	=> '202',
					'message' 	=> esc_html( Codevz_Core_Strings::get( '300s', $slug ) )

				];

			}

		}

		// Activate plugin.
		$activate = activate_plugin( $this->plugin_file( $slug ) );

		// Check activation error.
		if ( is_wp_error( $activate ) ) {

			return [

				'status' 	=> '202',
				'message' 	=> esc_html( Codevz_Core_Strings::get( 'plugin_error' ) ) . $activate->get_error_message()

			];

		}

		return [

			'status' 	=> '200',
			'message' 	=> esc_html( Codevz_Core_Strings::get( 'plugin_installed', $slug ) )

		];

	}

	/**
	 * Try to grab information from WordPress API.
	 *
	 * @param string $slug Plugin slug.
	 * @return object Plugins_api response object on success, WP_Error on failure.
	 */
	protected function plugins_api( $slug ) {

		static $api = [];

		if ( ! isset( $api[ $slug ] ) ) {

			if ( ! function_exists( 'plugins_api' ) ) {

				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

			}

			$response = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );

			$api[ $slug ] = false;

			if ( is_wp_error( $response ) ) {

				return $response->get_error_message();

			} else {

				$api[ $slug ] = $response;

			}

		}

		return $api[ $slug ];

	}

	/**
	 * Check if plugin is active with file_exists function.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool
	 */
	private function plugin_file( $slug, $full_path = false ) {

		if ( $slug === 'contact-form-7' ) {

			$file = 'wp-contact-form-7';

		} else {

			$file = $slug;

		}

		return $full_path ? WP_PLUGIN_DIR . '/' . $slug . '/' . $file . '.php' : $slug . '/' . $file . '.php';

	}

	/**
	 * Check if plugin is active with file_exists function.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool
	 */
	private function plugin_is_active( $slug ) {

		if ( isset( $this->plugins[ $slug ][ 'class_exists' ] ) && class_exists( $this->plugins[ $slug ][ 'class_exists' ] ) ) {

			return true;

		} else if ( isset( $this->plugins[ $slug ][ 'function_exists' ] ) && function_exists( $this->plugins[ $slug ][ 'function_exists' ] ) ) {

			return true;

		}

		return false;

	}

	/**
	 * Uninstall imported demo data.
	 * 
	 * @return array
	 */
	private function uninstall_demo( $demo ) {

		$data = get_option( 'xtra_uninstall_' . $demo );

		if ( is_array( $data ) ) {

			foreach( $data as $type => $items ) {

				switch( $type ) {

					case 'options':

						delete_option( 'codevz_theme_options' );

						break;

					case 'posts':

						// Delete posts.
						foreach( $items as $item ) {

							if ( ! empty( $item[ 'id' ] ) && sanitize_title_with_dashes( get_the_title( $item[ 'id' ] ) ) === sanitize_title_with_dashes( $item[ 'title' ] ) ) {

								wp_delete_post( $item[ 'id' ], true );

							}

						}

						break;

					case 'attachments':

						foreach( $items as $item ) {

							if ( ! empty( $item[ 'id' ] ) && sanitize_title_with_dashes( get_the_title( $item[ 'id' ] ) ) === sanitize_title_with_dashes( $item[ 'title' ] ) ) {

								wp_delete_attachment( $item[ 'id' ], true );

							}

						}

						break;

					case 'terms':

						foreach( $items as $item ) {

							if ( ! empty( $item[ 'id' ] ) ) {

								wp_delete_term( $item[ 'id' ], $item[ 'taxonomy' ] );

							}

						}

						break;

					case 'sliders':

						if ( class_exists( 'RevSliderSlider' ) ) {

							foreach( $items as $item ) {

								$slider	= new RevSliderSlider();
								$slider->init_by_id( $item[ 0 ] );
								$slider->delete_slider();

							}

						}

						break;

				}

			}

			delete_option( 'xtra_uninstall_' . $demo );

			// Reset colors.
			delete_option( 'codevz_primary_color' );
			delete_option( 'codevz_secondary_color' );

			// Reset widgets.
			update_option( 'sidebars_widgets', [] );

			// Success.
			wp_send_json(
				[
					'status' 	=> '200',
					'message' 	=> esc_html( Codevz_Core_Strings::get( 'demo_uninstalled', $demo ) )
				]
			);

		} else {

			wp_send_json(
				[
					'status' 	=> '202',
					'message' 	=> 'Error, try again'
				]
			);

		}

	}

}

Codevz_Core_Dashboard::instance();