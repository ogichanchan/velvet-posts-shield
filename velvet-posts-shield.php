<?php
/**
 * Plugin Name: Velvet Posts Shield
 * Plugin URI: https://github.com/ogichanchan/velvet-posts-shield
 * Description: A unique PHP-only WordPress utility. A velvet style posts plugin acting as a shield. Focused on simplicity and efficiency.
 * Version: 1.0.0
 * Author: ogichanchan
 * Author URI: https://github.com/ogichanchan
 * License: GPLv2 or later
 * Text Domain: velvet-posts-shield
 *
 * @package VelvetPostsShield
 */

// Deny direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class for Velvet Posts Shield.
 * Manages plugin settings, admin interface, and frontend content shielding logic.
 */
class Velvet_Posts_Shield {

	/**
	 * Plugin options stored in the database.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Constructor.
	 * Initializes the plugin by loading options and setting up WordPress hooks.
	 */
	public function __construct() {
		$this->options = get_option( 'velvet_posts_shield_options', array() );

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_inline_styles_and_scripts' ) ); // For inline styles/scripts on admin page

		// Frontend hooks for shielding content.
		add_action( 'template_redirect', array( $this, 'shield_post_content_redirect' ) );
		add_filter( 'the_content', array( $this, 'shield_post_content_message' ) );
	}

	/**
	 * Adds the plugin's settings page to the WordPress admin menu.
	 */
	public function add_admin_menu() {
		add_options_page(
			esc_html__( 'Velvet Shield Settings', 'velvet-posts-shield' ),
			esc_html__( 'Velvet Shield', 'velvet-posts-shield' ),
			'manage_options',
			'velvet-posts-shield',
			array( $this, 'options_page_html' )
		);
	}

	/**
	 * Initializes plugin settings by registering setting fields and sections.
	 */
	public function settings_init() {
		register_setting( 'velvet_posts_shield_group', 'velvet_posts_shield_options', array( $this, 'sanitize_options' ) );

		add_settings_section(
			'velvet_posts_shield_main_section',
			esc_html__( 'Shield Configuration', 'velvet-posts-shield' ),
			array( $this, 'main_section_callback' ),
			'velvet-posts-shield'
		);

		add_settings_field(
			'vps_enable_shield',
			esc_html__( 'Enable Shield', 'velvet-posts-shield' ),
			array( $this, 'enable_shield_callback' ),
			'velvet-posts-shield',
			'velvet_posts_shield_main_section'
		);

		add_settings_field(
			'vps_shielded_post_types',
			esc_html__( 'Shielded Post Types', 'velvet-posts-shield' ),
			array( $this, 'shielded_post_types_callback' ),
			'velvet-posts-shield',
			'velvet_posts_shield_main_section'
		);

		add_settings_field(
			'vps_shield_message',
			esc_html__( 'Shield Message', 'velvet-posts-shield' ),
			array( $this, 'shield_message_callback' ),
			'velvet-posts-shield',
			'velvet_posts_shield_main_section'
		);

		add_settings_field(
			'vps_redirect_to_login',
			esc_html__( 'Redirect to Login', 'velvet-posts-shield' ),
			array( $this, 'redirect_to_login_callback' ),
			'velvet-posts-shield',
			'velvet_posts_shield_main_section'
		);
	}

	/**
	 * Callback for the main settings section.
	 */
	public function main_section_callback() {
		echo '<p>' . esc_html__( 'Configure the velvet shield to protect your content from non-logged-in users.', 'velvet-posts-shield' ) . '</p>';
	}

	/**
	 * Callback for the 'Enable Shield' checkbox setting.
	 */
	public function enable_shield_callback() {
		$enabled = isset( $this->options['enable_shield'] ) ? (bool) $this->options['enable_shield'] : false;
		echo '<label for="vps_enable_shield">';
		echo '<input type="checkbox" id="vps_enable_shield" name="velvet_posts_shield_options[enable_shield]" value="1"' . checked( $enabled, true, false ) . '/> ';
		esc_html_e( 'Activate the content shield.', 'velvet-posts-shield' );
		echo '</label>';
	}

	/**
	 * Callback for the 'Shielded Post Types' multiselect setting.
	 */
	public function shielded_post_types_callback() {
		$selected_post_types = isset( $this->options['shielded_post_types'] ) ? (array) $this->options['shielded_post_types'] : array();
		$post_types          = get_post_types( array( 'public' => true ), 'objects' );

		echo '<select name="velvet_posts_shield_options[shielded_post_types][]" id="vps_shielded_post_types" multiple="multiple" style="min-width: 250px;">';
		foreach ( $post_types as $post_type_obj ) {
			echo '<option value="' . esc_attr( $post_type_obj->name ) . '"' . selected( in_array( $post_type_obj->name, $selected_post_types, true ), true, false ) . '>';
			echo esc_html( $post_type_obj->label );
			echo '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select which post types should be shielded from non-logged-in users.', 'velvet-posts-shield' ) . '</p>';
	}

	/**
	 * Callback for the 'Shield Message' textarea setting.
	 */
	public function shield_message_callback() {
		$message = isset( $this->options['shield_message'] ) ? $this->options['shield_message'] : esc_html__( 'This content is shielded. Please log in to view.', 'velvet-posts-shield' );
		echo '<textarea name="velvet_posts_shield_options[shield_message]" id="vps_shield_message" rows="5" cols="50" class="large-text">' . esc_textarea( $message ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'This message will be displayed to non-logged-in users for shielded content, unless redirect is enabled.', 'velvet-posts-shield' ) . '</p>';
	}

	/**
	 * Callback for the 'Redirect to Login' checkbox setting.
	 */
	public function redirect_to_login_callback() {
		$redirect = isset( $this->options['redirect_to_login'] ) ? (bool) $this->options['redirect_to_login'] : false;
		echo '<label for="vps_redirect_to_login">';
		echo '<input type="checkbox" id="vps_redirect_to_login" name="velvet_posts_shield_options[redirect_to_login]" value="1"' . checked( $redirect, true, false ) . '/> ';
		esc_html_e( 'Redirect non-logged-in users to the login page instead of displaying a message.', 'velvet-posts-shield' );
		echo '</label>';
	}

	/**
	 * Sanitizes plugin options before saving them to the database.
	 *
	 * @param array $input The raw input array from the settings form.
	 * @return array The sanitized options array.
	 */
	public function sanitize_options( $input ) {
		$new_input = array();

		$new_input['enable_shield'] = isset( $input['enable_shield'] ) ? (bool) $input['enable_shield'] : false;

		$new_input['shielded_post_types'] = array();
		if ( isset( $input['shielded_post_types'] ) && is_array( $input['shielded_post_types'] ) ) {
			$public_post_types = array_keys( get_post_types( array( 'public' => true ) ) );
			foreach ( $input['shielded_post_types'] as $post_type ) {
				// Only allow valid public post types.
				if ( in_array( $post_type, $public_post_types, true ) ) {
					$new_input['shielded_post_types'][] = sanitize_key( $post_type );
				}
			}
		}

		$new_input['shield_message'] = isset( $input['shield_message'] ) ? wp_kses_post( $input['shield_message'] ) : esc_html__( 'This content is shielded. Please log in to view.', 'velvet-posts-shield' );

		$new_input['redirect_to_login'] = isset( $input['redirect_to_login'] ) ? (bool) $input['redirect_to_login'] : false;

		return $new_input;
	}

	/**
	 * Displays the plugin's options page HTML.
	 */
	public function options_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap velvet-shield-settings-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'velvet_posts_shield_group' );
				do_settings_sections( 'velvet-posts-shield' );
				submit_button( esc_html__( 'Save Changes', 'velvet-posts-shield' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Injects inline styles for the admin settings page.
	 * This adheres to the "PHP ONLY: All CSS or JS must be inline" rule.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function admin_inline_styles_and_scripts( $hook ) {
		// Only load on the plugin's settings page.
		if ( 'settings_page_velvet-posts-shield' !== $hook ) {
			return;
		}

		// Inline CSS for a "velvet" look and admin page improvements.
		echo '<style type="text/css">';
		echo '
        .velvet-shield-settings-wrap {
            max-width: 800px;
            margin-top: 20px;
            background: #fff;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            border-radius: 8px;
        }
        .velvet-shield-settings-wrap h1 {
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 30px;
            color: #4CAF50; /* A soft, elegant green */
        }
        .form-table th {
            padding-left: 0;
            width: 250px;
            font-weight: 600;
            color: #333;
        }
        .form-table td {
            padding-top: 15px;
            padding-bottom: 15px;
        }
        .form-table textarea,
        .form-table select {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.07);
        }
        .form-table textarea:focus,
        .form-table select:focus,
        .form-table input[type="checkbox"]:focus {
            border-color: #5cb35d;
            box-shadow: 0 0 0 1px #5cb35d;
            outline: none;
        }
        .form-table .description {
            font-style: italic;
            color: #777;
            margin-top: 5px;
        }
        .submit button,
        .submit input[type="submit"] {
            background-color: #4CAF50;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-shadow: none; /* Override WP default */
            box-shadow: none; /* Override WP default */
            height: auto; /* Override WP default */
        }
        .submit button:hover,
        .submit input[type="submit"]:hover {
            background-color: #45a049;
        }
        ';
		echo '</style>';
	}

	/**
	 * Frontend logic: Redirects non-logged-in users if 'redirect to login' is enabled
	 * and the current post is shielded.
	 */
	public function shield_post_content_redirect() {
		// Check if the shield conditions are met for the current post and user.
		if ( ! $this->is_shield_active_for_current_post() ) {
			return;
		}

		// If 'Redirect to Login' is enabled, perform the redirection.
		if ( isset( $this->options['redirect_to_login'] ) && (bool) $this->options['redirect_to_login'] ) {
			// Redirect to login page, preserving the current URL as the redirect_to parameter.
			wp_safe_redirect( wp_login_url( get_permalink() ) );
			exit;
		}
	}

	/**
	 * Frontend logic: Filters the post content to display a custom message
	 * for non-logged-in users if the post is shielded and redirection is not enabled.
	 *
	 * @param string $content The original post content.
	 * @return string The modified content (shield message) or original content.
	 */
	public function shield_post_content_message( $content ) {
		// If redirection is enabled, this filter should not apply (redirection takes precedence).
		if ( isset( $this->options['redirect_to_login'] ) && (bool) $this->options['redirect_to_login'] ) {
			return $content;
		}

		// Check if the shield conditions are met for the current post and user.
		if ( ! $this->is_shield_active_for_current_post() ) {
			return $content;
		}

		// Retrieve the custom shield message, or use a default.
		$message = isset( $this->options['shield_message'] ) && ! empty( $this->options['shield_message'] )
			? wp_kses_post( $this->options['shield_message'] )
			: esc_html__( 'This content is shielded. Please log in to view.', 'velvet-posts-shield' );

		$login_url       = wp_login_url( get_permalink() );
		$login_link_text = esc_html__( 'Login here', 'velvet-posts-shield' );

		// Construct the HTML for the shielded message with inline styles.
		$shielded_content_html = '<div style="border: 1px solid #ddd; padding: 20px; margin: 20px 0; background-color: #f9f9f9; text-align: center; border-radius: 5px; font-family: \'Georgia\', serif; color: #555;">';
		$shielded_content_html .= '<p style="font-size: 1.2em; line-height: 1.6;">' . $message . '</p>';
		$shielded_content_html .= '<p><a href="' . esc_url( $login_url ) . '" style="display: inline-block; padding: 8px 15px; background-color: #4CAF50; color: #fff; text-decoration: none; border-radius: 3px; transition: background-color 0.3s ease;">' . $login_link_text . '</a></p>';
		$shielded_content_html .= '</div>';

		return $shielded_content_html;
	}

	/**
	 * Determines if the content shield should be active for the current request.
	 *
	 * @return bool True if the shield should be applied, false otherwise.
	 */
	private function is_shield_active_for_current_post() {
		// 1. Check if the shield is generally enabled in options.
		if ( ! isset( $this->options['enable_shield'] ) || ! (bool) $this->options['enable_shield'] ) {
			return false;
		}

		// 2. Check if the current user is logged in.
		if ( is_user_logged_in() ) {
			return false; // Logged-in users bypass the shield.
		}

		// 3. Check if we are on a singular post/page/custom post type.
		if ( ! is_singular() ) {
			return false; // The shield only applies to single content views.
		}

		// 4. Check if the current post type is selected for shielding.
		$current_post_type   = get_post_type();
		$shielded_post_types = isset( $this->options['shielded_post_types'] ) ? (array) $this->options['shielded_post_types'] : array();

		if ( ! in_array( $current_post_type, $shielded_post_types, true ) ) {
			return false; // The current post type is not configured to be shielded.
		}

		// All conditions met: the shield should be active.
		return true;
	}

	/**
	 * Plugin activation hook.
	 * Sets default options if they don't already exist.
	 */
	public static function activate() {
		$default_options = array(
			'enable_shield'       => false,
			'shielded_post_types' => array( 'post' ), // Default to shielding 'posts' type.
			'shield_message'      => esc_html__( 'This content is shielded. Please log in to view.', 'velvet-posts-shield' ),
			'redirect_to_login'   => false,
		);
		// Add options only if they don't exist to prevent overwriting existing settings on subsequent activations.
		add_option( 'velvet_posts_shield_options', $default_options );
	}

	/**
	 * Plugin deactivation hook.
	 * Cleans up plugin options upon deactivation.
	 */
	public static function deactivate() {
		// Delete all plugin options when the plugin is deactivated.
		delete_option( 'velvet_posts_shield_options' );
	}
}

// Register activation and deactivation hooks for the plugin.
register_activation_hook( __FILE__, array( 'Velvet_Posts_Shield', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Velvet_Posts_Shield', 'deactivate' ) );

/**
 * Initializes the Velvet_Posts_Shield plugin.
 * This function ensures the plugin class is instantiated only once and after
 * WordPress has finished loading all other plugins.
 */
function run_velvet_posts_shield() {
	new Velvet_Posts_Shield();
}
add_action( 'plugins_loaded', 'run_velvet_posts_shield' );
?>