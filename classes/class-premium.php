<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Cannot access pages directly.

/**
 * Premium functions and auto update.
 * 
 * @since 4.4.5
 */

class Codevz_Core_Premium {

	// Class instance.
	private static $instance = null;

	public function __construct() {

		add_action( 'admin_init', [ $this, 'admin_init' ], 11 );
		add_action( 'after_setup_theme', [ $this, 'white_label_check' ] );
		add_action( 'customize_save_after', [ $this, 'white_label' ] );

	}

	// Instance.
	public static function instance() {

		if ( self::$instance === null ) {

			self::$instance = new self();

		}

		return self::$instance;
	}

	/**
	 * Redirect to dashboard after theme activated.
	 * 
	 * @since 2.7.0
	 * @return -
	 */
	public function admin_init() {

		$is_free = Codevz_Core_Theme::is_free();

		// Check automatic theme update on admin init.
		add_filter( 'pre_set_site_transient_update_themes', [ $this, 'update' ] );

		// Plugin install notice.
		if ( ! Codevz_Core_Theme::$plugin && $is_free ) {

			add_action( 'admin_notices', [ $this, 'admin_notice' ] );

		}

		// Prevent WPBakery elements to load.
		if ( $is_free ) {

			add_filter( 'vc_single_param_edit_holder_output', [ $this, 'vc_single_param_edit_holder_output' ], 11, 5 );

		}

	}

	/**
	 * Prevent WPBakery elements to load if theme is not activated.
	 * 
	 * @since 4.7.2
	 * @return string
	 */
	public function vc_single_param_edit_holder_output( $output, $param, $value, $settings, $atts ) {

		$base = Codevz_Core_Theme::contains( $settings['base'], 'cz_' );
		$type = ( $param['type'] !== 'cz_title' && $param['type'] !== 'cz_hidden' );

		if ( $base && $type && isset( $param['heading'] ) ) {

			$output = '<div class="vc_col-xs-99 vc_shortcode-param vc_column"><div class="wpb_element_label">' . esc_html( $param['heading'] ) . '</div><div class="edit_form_line"><a href="' . esc_url( get_admin_url() ) . 'admin.php?page=theme-activation"><span class="xtra-pro" style="position:static;margin-top:5px;">' . esc_html( Codevz_Core_Strings::get( 'pro' ) ) . '</span></a></div></div>';

		}

		return $output;

	}

	/**
	 * Add the admin notice for installing codevz plugin.
	 * 
	 * @since 4.7.2
	 * @return string
	 */
	public function admin_notice() {

		$color = Codevz_Core_Config::get( 'color_1' );

		$plugins = Codevz_Core_Config::get( 'plugins' );
		$plugin  = array_keys( $plugins );
		$plugin  = reset( $plugin );

		echo '<div class="codevz-admin-notice">';
		echo '<img src="' . esc_url( Codevz_Core_Theme::$url ) . 'assets/img/dashboard.png" />';

		$title = Codevz_Core_Theme::option( 'white_label_theme_name', Codevz_Core_Strings::get( 'theme_name' ) );

		echo '<h2>' . esc_html( Codevz_Core_Strings::get( 'thanks_install', $title ) );
		echo '<small>' . esc_html( Codevz_Core_Strings::get( 'thanks_install_plugin' ) ) . '</small>';
		echo '</h2>';

		echo '<a href="#" class="button button-primary">' . esc_html( Codevz_Core_Strings::get( 'thanks_install_plugin_b' ) ) . '</a>';
		echo '</div>';

		?>

			<script>

				jQuery( function( $ ) {

					$( 'body' ).on( 'click', '.codevz-admin-notice .button', function( e ) {

						var $this = $( this ),
							problem = function() {
								$this.removeClass( 'button disabled' ).find( '.xtra-loading' ).remove();
								$this.attr( 'href', '<?php echo esc_attr( $plugins[ $plugin ][ 'source' ] ); ?>' ).html( 'Click to download plugin and install it manually' );
							};

						$this.html( '<?php echo esc_html( Codevz_Core_Strings::get( 'thanks_installing_plugin' ) ); ?>' ).addClass( 'disabled' ).prepend( '<span class="xtra-loading"></span>' );

						$.ajax(
							{
								type: 'POST',
								url: ajaxurl,
								data: {
									action: 'codevz_wizard',
									type: 'plugin',
									name: '<?php echo esc_attr( $plugin ); ?>',
									nonce: '<?php echo esc_attr( wp_create_nonce( 'xtra-wizard' ) ); ?>'
								},
								success: function( obj ) {

									console.log( obj );

									if ( ( typeof obj === 'object' && obj.status === '200' ) || ( typeof obj === 'string' && obj.indexOf( '200' ) >= 0 ) ) {

										window.location = '<?php echo esc_url( admin_url( 'admin.php?page=theme-importer' ) ); ?>';

									} else {

										problem();

										return false;

									}

								},
								error: function( xhr, type, message ) {

									problem();

									console.log( xhr, type, message );

								}
							}
						);

						e.preventDefault();
						
					});

				});

			</script>

			<style>
				.codevz-admin-notice {
					position: relative;
					margin: 20px 0;
					padding: 20px 40px;
					display: flex;
					align-items: center;
					box-sizing: border-box;
					overflow: hidden;
					background: #fff;
					border-radius: 5px;
					max-width: 1140px;
					border: 1px solid <?php echo esc_html( $color ? $color : '#ffbb00' ); ?>;
					box-shadow: 0 0 40px rgba(17, 17, 17, 0.07)
				}
				.codevz-admin-notice img {
					width: 60px;
					height: 60px;
					margin-right: 40px
				}
				.rtl .codevz-admin-notice img {
					margin-left: 40px;
					margin-right: 0
				}
				.codevz-admin-notice h2 {
					font-size: 20px;
					font-weight: bold
				}
				.codevz-admin-notice small {
					display: block;
					opacity: .6;
					font-size: 14px;
					margin-top: 10px;
					font-weight: normal;
				}
				.codevz-admin-notice a {
					position: absolute;
					right: 40px;
					padding: 5px 20px !important;
					font-size: 14px !important
				}
				.rtl .codevz-admin-notice a {
					left: 40px;
					right: auto
				}
				.codevz-admin-notice .xtra-loading {
					position: relative;
					display: inline-block;
					width: 10px;
					height: 10px;
					vertical-align: middle;
					margin-right: 12px;
					border-radius: 100px;
					transform: translateZ(0);
					border: 2px solid rgba(40, 40, 40, 0.1);
					border-left: 2px solid #222222;
					animation: loader 1.1s infinite linear
				}
				.rtl .codevz-admin-notice .xtra-loading {
					margin-left: 12px;
					margin-right: 0
				}
				@keyframes loader {
					0% {transform: rotate(0deg)}
					100% {transform: rotate(360deg)}
				}
			</style>

		<?php

	}

	/**
	 * Theme automatic update
	 * 
	 * @since 2.7.0
	 */
	public static function update( $transient ) {

		// API.
		$api = apply_filters( 'codevz_config_api', false );
		if ( ! $api ) {
			return $transient;
		}

		// Original theme slug from config.
		$theme_slug = sanitize_title_with_dashes( apply_filters( 'codevz_config_name', false ) );
		if ( ! $theme_slug ) {
			return $transient;
		}

		// Always fetch new versions from the API
		$request = wp_remote_get( $api . 'versions.json' );

		if ( ! is_wp_error( $request ) ) {
			$body = wp_remote_retrieve_body( $request );
			$versions = json_decode( $body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return $transient;
			}
		} else {
			return $transient;
		}

		// There is no new update or its child theme, so skip!
		if ( ! isset( $versions['themes'][ $theme_slug ] ) ) {
			return $transient;
		}

		// Current theme
		$theme = wp_get_theme();

		// Slug and version.
		if ( ! empty( $theme->parent() ) ) {

			$current_theme = sanitize_title_with_dashes( $theme->get( 'Template' ) );

			$old_version = $theme->parent()->Version;

		} else {

			$current_theme = sanitize_title_with_dashes( $theme->get( 'Name' ) );

			$old_version = $theme->get( 'Version' );

		}

		$new_version = $versions['themes'][ $theme_slug ]['version'];

		// Compare versions and inform WordPress about new update
		if ( $old_version != $new_version && version_compare( $old_version, $new_version, '<' ) ) {

			if ( $theme_slug === 'xtra' ) {
				$theme_zip = $api . $theme_slug . '.zip';
			} else {
				$theme_zip = $api . 'themes/' . $theme_slug . '.zip';
			}

			$transient->response[ $current_theme ] = [
				'theme'        => $current_theme,
				'new_version'  => $new_version,
				'url'          => str_replace( 'api/', '', $api ),
				'package'      => $theme_zip
			];

		} else if ( isset( $transient->response[ $current_theme ] ) ) {

			unset( $transient->response[ $current_theme ] );

		}

		return $transient;

	}

	/**
	 * Check white labeled themes after update.
	 * 
	 * @return -
	 */
	public function white_label_check() {

		// White label after update.
		$white_label = Codevz_Core_Theme::option( 'white_label_theme_name' );

		if ( $white_label ) {

			$theme = wp_get_theme();

			if ( empty( $theme->parent() ) && $white_label !== $theme->get( 'Name' ) ) {

				self::white_label();

			}

		}

	}

	/**
	 * Theme white label
	 * 
	 * @since 3.2.0
	 */
	public static function white_label() {

		if ( ! Codevz_Core_Theme::$plugin ) {
			return;
		}

		$dir 			= trailingslashit( get_template_directory() );
		$basename 		= basename( $dir );

		$name 			= Codevz_Core_Theme::option( 'white_label_theme_name' );
		$desc 			= Codevz_Core_Theme::option( 'white_label_theme_description' );
		$link 			= Codevz_Core_Theme::option( 'white_label_link', 'https://codevz.com/' );
		$author 		= Codevz_Core_Theme::option( 'white_label_author', 'Codevz' );
		$author_link 	= $link;
		$slug 			= sanitize_title_with_dashes( $name );
		$screenshot 	= Codevz_Core_Theme::option( 'white_label_theme_screenshot', Codevz_Core_Theme::$url . 'assets/img/screenshot.png' );

		$is_child_theme = is_child_theme();

		if ( empty( $name ) ) {
			return;
		}

		// WP_Filesystem.
		$wpfs = Codevz_Plus::wpfs();

		// Get theme version.
		$theme = wp_get_theme();
		$ver = empty( $theme->parent() ) ? $theme->get( 'Version' ) : $theme->parent()->Version;

		$information = '/*
	Theme Name:   ' . $name . '
	Theme URI:    ' . $link . '
	Description:  ' . $desc . '
	Version:      ' . $ver . '
	Author:       ' . $author . '
	Author URI:   ' . $author_link . '
	License:      GPLv2
	License URI:  http://gnu.org/licenses/gpl-2.0.html
	Tags:         one-column, two-columns, right-sidebar, custom-menu, rtl-language-support, sticky-post, translation-ready
*/

/*
	PLEASE DO NOT edit this file, if you want add custom CSS go to Theme Options > Additional CSS
*/';

		// Save style.css
		$result = $wpfs->put_contents( $dir . 'style.css', $information, FS_CHMOD_FILE );

		// Replace image.
		$new_image = $wpfs->get_contents( $screenshot );
		$result = $wpfs->put_contents( $dir . 'screenshot.png', $new_image, FS_CHMOD_FILE );
		$result = $wpfs->put_contents( str_replace( '/' . $basename . '/', '/' . $slug . '-child/screenshot.png', $dir ), $new_image, FS_CHMOD_FILE );

		// Rename folder name.
		$new_name = str_replace( '/' . $basename . '/', '/' . $slug . '/', $dir );
		rename( $dir, $new_name );

		// Check child theme.
		if ( $is_child_theme ) {

			// Child theme.
			$child = '/*
		Theme Name:	' . $name . ' Child
		Theme URI:	' . $link . '
		Description:' . $desc . '
		Author:		' . $author . '
		Author URI:	' . $author_link . '
		Template:	' . strtolower( $name ) . '
		Version:	1.0
	*/

	/*
		PLEASE DO NOT edit this file, if you want add custom CSS go to Theme Options > Additional CSS
	*/';

			$new_name = str_replace( '/' . $basename . '/', '/' . $slug . '-child/', $dir );
			$child_dir = str_replace( '/' . $basename . '/', '/' . $basename . '-child/', $dir );
			rename( $child_dir, $new_name );

			$result = $wpfs->put_contents( str_replace( '/' . $basename . '/', '/' . $slug . '-child/style.css', $dir ), $child, FS_CHMOD_FILE );

			// Activate child theme.
			switch_theme( $slug . '-child' );

		} else {

			// Theme activate.
			switch_theme( $slug );
		}

	}

}

Codevz_Core_Premium::instance();