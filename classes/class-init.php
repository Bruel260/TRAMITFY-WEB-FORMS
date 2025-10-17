<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Cannot access pages directly.

/**
 * Custom theme configuration init.
 * 
 * @since 4.4.8
 */

class Codevz_Core_Init {

	public $config = [];

	// Class instance.
	private static $instance = null;

	public function __construct() {

		add_action( 'init', [ $this, 'init' ] );

	}

	public function init() {

		// Get custom theme configuration.
		$this->config = Codevz_Core_Config::get();

		// Filters and actions.
		add_filter( 'codevz_config_name', 			[ $this, 'name' ] );
		add_filter( 'codevz_config_api', 			[ $this, 'api' ] );
		add_filter( 'codevz_config_api_demos', 		[ $this, 'api_demos' ] );
		add_filter( 'codevz_config_icon', 			[ $this, 'icon' ] );
		add_filter( 'codevz_config_item_id', 		[ $this, 'item_id' ] );
		add_filter( 'codevz_config_token_key', 		[ $this, 'token_key' ] );
		add_filter( 'codevz_config_docs', 			[ $this, 'docs' ] );
		add_filter( 'codevz_config_find_purchase', 	[ $this, 'find_purchase' ] );
		add_filter( 'codevz_config_buy_link', 		[ $this, 'buy_link' ] );
		add_filter( 'codevz_config_youtube',		[ $this, 'youtube' ] );
		add_filter( 'codevz_config_changelog_link',	[ $this, 'changelog_link' ] );
		add_filter( 'codevz_config_support_link', 	[ $this, 'support_link' ] );
		add_filter( 'codevz_config_faq_link', 		[ $this, 'faq_link' ] );
		add_filter( 'codevz_config_plugins', 		[ $this, 'plugins' ] );
		add_filter( 'codevz_config_demos', 			[ $this, 'demos' ] );

		add_action( 'admin_head', 					[ $this, 'colors' ] );
		add_action( 'customize_controls_print_footer_scripts', [ $this, 'colors' ] );

		// Reset theme options.
		// Database theme options ID.
		$options_id = 'codevz_theme_options';

		// Get saved options.
		$options = get_option( $options_id );

		// Reset for review only.
		if ( $this->config[ 'reset' ] && $this->config[ 'reset' ] != get_option( 'codevz_reset_options' ) ) {

			update_option( 'codevz_reset_options', $this->config[ 'reset' ] );

			update_option( 'codevz_options_backup_' . $this->config[ 'reset' ], get_option( $options_id ) );

			update_option( $options_id, $this->config[ 'options' ] );

		// First time theme installed user.
		} else if ( ! isset( $options[ 'layout' ] ) && ! isset( $options[ 'css_out' ] ) && ! isset( $options[ 'primary' ] ) ) {

			if ( empty( get_option( 'codevz_first_time_options_backup' ) ) ) {
				update_option( 'codevz_first_time_options_backup', $options );
			}

			update_option( $options_id, $this->config[ 'options' ] );

		}

	}

	// Instance.
	public static function instance() {

		if ( self::$instance === null ) {

			self::$instance = new self();

		}

		return self::$instance;
	}

	// Server API.
	public function api( $api ) {

		return $this->config[ 'api' ] ? $this->config[ 'api' ] : $api;

	}

	// Server API for demos.
	public function api_demos( $api_demos ) {

		return $this->config[ 'api_demos' ] ? $this->config[ 'api_demos' ] : $api_demos;

	}

	// Theme name.
	public function name( $name ) {

		return $this->config[ 'name' ] ? $this->config[ 'name' ] : $name;

	}

	// Dashboard icon.
	public function icon( $icon ) {

		if ( ! empty( $this->config[ 'icon' ] ) ) {
			return $this->config[ 'icon' ];
		}

		return $icon;

	}

	// Theme plugins.
	public function plugins( $plugins ) {

		return $this->config[ 'plugins' ] ? $this->config[ 'plugins' ] : $plugins;

	}

	// Theme demos.
	public function demos( $demos ) {

		return $this->config[ 'demos' ] ? $this->config[ 'demos' ] : $demos;

	}

	// Documentation link.
	public function docs( $docs ) {

		return $this->config[ 'docs' ] ? $this->config[ 'docs' ] : $docs;

	}

	// Docs find purchase code link.
	public function find_purchase( $find_purchase ) {

		return $this->config[ 'find_purchase' ] ? $this->config[ 'find_purchase' ] : $find_purchase;

	}

	// Purchase link.
	public function buy_link( $buy_link ) {

		return $this->config[ 'buy_link' ] ? $this->config[ 'buy_link' ] : $buy_link;

	}

	// Changelog link.
	public function changelog_link( $changelog ) {

		return $this->config[ 'changelog' ] ? $this->config[ 'changelog' ] : $changelog;

	}

	// Youtube channel link.
	public function youtube( $youtube ) {

		return $this->config[ 'youtube' ] ? $this->config[ 'youtube' ] : $youtube;

	}

	// Support link.
	public function support_link( $support ) {

		return $this->config[ 'support' ] ? $this->config[ 'support' ] : $support;

	}

	// FAQ link.
	public function faq_link( $faq ) {

		return $this->config[ 'faq' ] ? $this->config[ 'faq' ] : $faq;

	}

	// Themeforest item ID.
	public function item_id( $item_id ) {

		return $this->config[ 'item_id' ] ? $this->config[ 'item_id' ] : $item_id;

	}

	// Envato personal token key.
	public function token_key( $token_key ) {

		return $this->config[ 'token_key' ] ? $this->config[ 'token_key' ] : $token_key;

	}

	// Dashboard and admin colors.
	public function colors() {

		?>

		<style>

			<?php if ( $this->config[ 'color_1' ] || $this->config[ 'color_2' ] ) { ?>

				.xtra-dashboard-menus a:before,
				.xtra-dashboard-section-title:after,
				.xtra-dashboard-activation-form [type="submit"]:hover,
				.xtra-ss-item.xtra-dashboard-warning i,
				.xtra-button-primary,
				.xtra-button-secondary,
				.xtra-filters a:hover,
				.xtra-filters .xtra-current,
				.xtra-plugin-progress,
				.xtra-wizard-steps .xtra-current span,
				.xtra-wizard-steps .xtra-current span:after,
				.xtra-wizard-steps li:before,
				.xtra-wizard-steps li + li:after,
				.xtra-wizard-progress div,
				.xtra-tooltip,
				.xtra-tooltip:after,
				.xtra-pro,
				.xtra-dashboard-activation-form [type="submit"],
				.codevz-plus-gopro,
				.xtra-checkbox input:checked ~ .checkmark, 
				.xtra-radio input:checked ~ .checkmark,
				.active_stylekit:after,
				.codevz-field-switcher label input:checked ~ em,
				.wp-customizer .codevz_image_select ul li:before,
				.codevz_image_select ul li:hover:after,
				.codevz_image_select .codevz_on:after {
					color: <?php echo esc_html( $this->config[ 'color_2' ] ); ?>;
					background: <?php echo esc_html( $this->config[ 'color_1' ] ); ?>;
				}

			<?php } ?>

			<?php if ( $this->config[ 'color_1' ] ) { ?>

				.xtra-dashboard-warning,
				.xtra-wizard-steps li:before,
				.xtra-inactive-notice,
				i.codevz-section-focus:hover,
				.xtra-checkbox input:checked ~ .checkmark, 
				.xtra-radio input:checked ~ .checkmark,
				.xtra-list .checkmark:after,
				.codevz_image_select ul li:hover,
				.codevz_image_select .codevz_on {
					border-color: <?php echo esc_html( $this->config[ 'color_1' ] ); ?>;
				}
				.xtra-loading:after {
					border-left-color: #fff
				}
				.xtra-dashboard-warning i,
				.xtra-dashboard-icon-box-warning,
				i.codevz-section-focus:hover,
				.cz_copied {
					color: <?php echo esc_html( $this->config[ 'color_1' ] ); ?>;
				}

			<?php } ?>

			<?php if ( $this->config[ 'color_2' ] ) { ?>

				.xtra-button-primary:not([disabled]),
				.xtra-button-secondary:not([disabled]),
				.xtra-ss-item a {
					color: <?php echo esc_html( $this->config[ 'color_2' ] ); ?>;
				}
				.xtra-radio .checkmark:after {
					background: <?php echo esc_html( $this->config[ 'color_2' ] ); ?>;
				}
				.xtra-checkbox .checkmark:after, 
				.xtra-radio .checkmark:after {
					border-color: <?php echo esc_html( $this->config[ 'color_2' ] ); ?>;
				}

			<?php } ?>

			<?php if ( $this->config[ 'button_primary_color' ] ) { ?>

				.xtra-button-primary,
				.xtra-button-primary:not([disabled]),
				.xtra-dashboard-activation-form [type="submit"],
				.xtra-ss-item a,
				.xtra-wizard-steps .xtra-current span, 
				.xtra-wizard-steps .xtra-current span:after {
					color: <?php echo esc_html( $this->config[ 'button_primary_color' ] ); ?>
				}

			<?php } ?>

			<?php if ( $this->config[ 'button_primary_bg' ] ) { ?>

				.xtra-button-primary,
				.xtra-button-primary:not([disabled]),
				.xtra-dashboard-activation-form [type="submit"],
				.xtra-ss-item a,
				.xtra-wizard-steps .xtra-current span, 
				.xtra-wizard-steps .xtra-current span:after {
					background: <?php echo esc_html( $this->config[ 'button_primary_bg' ] ); ?>
				}

			<?php } ?>

			<?php if ( $this->config[ 'button_secondary_color' ] ) { ?>

				.xtra-button-secondary,
				.xtra-button-secondary:not([disabled]) {
					color: <?php echo esc_html( $this->config[ 'button_secondary_color' ] ); ?> !important
				}

			<?php } ?>

			<?php if ( $this->config[ 'button_secondary_bg' ] ) { ?>

				.xtra-button-secondary,
				.xtra-button-secondary:not([disabled]) {
					background: <?php echo esc_html( $this->config[ 'button_secondary_bg' ] ); ?> !important
				}

			<?php } ?>

		</style>

		<?php

	}

}

Codevz_Core_Init::instance();