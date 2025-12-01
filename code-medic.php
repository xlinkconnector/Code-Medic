<?php
/**
 * Plugin Name: Code Medic
 * Plugin URI:  https://facebook.com/superseanfrancisco
 * Description: The comprehensive AI-powered self-healing tool and developer sandbox for WordPress. Detects fatal errors, suggests AI fixes, and provides a safe playground for code.
 * Version:     1.0.0
 * Author:      Code Medic Team
 * License:     GPL-2.0+
 * Text Domain: code-medic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CODEMEDIC_VERSION', '1.0.0' );
define( 'CODEMEDIC_PATH', plugin_dir_path( __FILE__ ) );
define( 'CODEMEDIC_URL', plugin_dir_url( __FILE__ ) );

// 1. Load the Sentinel IMMEDIATELY. 
// It must be the first thing to run to catch potential errors in other plugins.
require_once CODEMEDIC_PATH . 'includes/class-codemedic-sentinel.php';
CodeMedic_Sentinel::instance();

// 2. Load Core Classes
require_once CODEMEDIC_PATH . 'includes/class-codemedic-surgeon.php';
require_once CODEMEDIC_PATH . 'includes/class-codemedic-sandbox.php';
require_once CODEMEDIC_PATH . 'admin/class-codemedic-admin.php';

// 3. Initialize Admin Logic
if ( is_admin() ) {
	$surgeon = new CodeMedic_Surgeon();
	$sandbox = new CodeMedic_Sandbox();
	new CodeMedic_Admin( $surgeon, $sandbox );
}

/**
 * Activation Hook
 * Creates necessary directories for the sandbox.
 */
register_activation_hook( __FILE__, 'codemedic_activate' );
function codemedic_activate() {
	$upload_dir = wp_upload_dir();
	$sandbox_dir = $upload_dir['basedir'] . '/codemedic-sandbox';
	if ( ! file_exists( $sandbox_dir ) ) {
		mkdir( $sandbox_dir, 0755, true );
	}
	// Secure the sandbox directory
	if ( ! file_exists( $sandbox_dir . '/index.php' ) ) {
		file_put_contents( $sandbox_dir . '/index.php', '<?php // Silence is golden.' );
	}
    // Create a .htaccess to prevent direct access to sandbox files
    if ( ! file_exists( $sandbox_dir . '/.htaccess' ) ) {
        file_put_contents( $sandbox_dir . '/.htaccess', 'Deny from all' );
    }
}
