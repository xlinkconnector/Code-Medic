<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CodeMedic_Sentinel
 * Monitors PHP execution and catches fatal errors via shutdown function.
 */
class CodeMedic_Sentinel {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_shutdown_function( array( $this, 'shutdown_handler' ) );
	}

	/**
	 * The Shutdown Handler
	 * This runs even if the script dies a horrible death.
	 */
	public function shutdown_handler() {
		$error = error_get_last();

		// We only care about Fatal Errors (1) and Parse Errors (4)
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR ) ) ) {
			
			// Identify if this error came from our Sandbox
			if ( strpos( $error['file'], 'codemedic-sandbox' ) !== false ) {
				$this->handle_sandbox_crash( $error );
				return;
			}

			// Otherwise, it's a real site error. Log it.
			$this->log_error( $error );
		}
	}

	/**
	 * Handles errors occurring inside the Developer Sandbox.
	 * Instead of WSOD, we output a JSON response so the JS frontend can display the error.
	 */
	private function handle_sandbox_crash( $error ) {
		// Clear any partial output buffer
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		$response = array(
			'success' => false,
			'data'    => array(
				'output' => '',
				'error'  => 'Fatal Error: ' . $error['message'] . ' on line ' . $error['line'],
			),
		);

		header( 'Content-Type: application/json' );
		echo json_encode( $response );
		exit;
	}

	/**
	 * Logs a detected error to the database for the Admin Dashboard to see.
	 */
	private function log_error( $error ) {
		$log = get_option( 'codemedic_error_log', array() );
		
		// Capture context (surrounding lines)
		$context = '';
		if ( file_exists( $error['file'] ) && is_readable( $error['file'] ) ) {
			$lines = file( $error['file'] );
			$start = max( 0, $error['line'] - 10 );
			$context_lines = array_slice( $lines, $start, 20 );
			$context = implode( "", $context_lines );
		}

		$new_entry = array(
			'timestamp' => time(),
			'message'   => $error['message'],
			'file'      => $error['file'],
			'line'      => $error['line'],
			'type'      => $error['type'],
			'context'   => $context, // The code snippet for the AI
			'fixed'     => false,
		);

		// Prepend and keep only last 20 errors
		array_unshift( $log, $new_entry );
		$log = array_slice( $log, 0, 20 );

		update_option( 'codemedic_error_log', $log );
	}

    /**
     * Public method to get logs
     */
    public function get_logs() {
        return get_option( 'codemedic_error_log', array() );
    }

    /**
     * Clear logs
     */
    public function clear_logs() {
        update_option( 'codemedic_error_log', array() );
    }
}
