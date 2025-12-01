<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeMedic_Sandbox {

	/**
	 * Run the provided PHP code in a sandbox.
	 * * @param string $code The PHP code to execute.
	 * @return array Result containing output or error.
	 */
	public function run_code( $code ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'success' => false, 'error' => 'Permission denied.' );
		}

		// 1. Prepare Directory
		$upload_dir = wp_upload_dir();
		$sandbox_dir = $upload_dir['basedir'] . '/codemedic-sandbox';
		
		// 2. Create a unique temporary file
		$filename = 'sandbox-' . wp_create_nonce( 'sandbox_run' ) . '.php';
		$filepath = $sandbox_dir . '/' . $filename;

		// 3. Wrap code safely
        // Note: We strip existing PHP tags to prevent nesting errors, then wrap in our own.
        $code = trim( $code );
        $code = preg_replace( '/^<\?php/i', '', $code );
        $code = preg_replace( '/\?>$/i', '', $code );
        
		$file_content = "<?php\n" . $code;
		
		if ( file_put_contents( $filepath, $file_content ) === false ) {
			return array( 'success' => false, 'error' => 'Could not write temporary execution file.' );
		}

		// 4. Capture Output
		ob_start();
		$start_time = microtime( true );

		try {
            // We include the file. If it has a fatal error, CodeMedic_Sentinel::handle_sandbox_crash catches it.
			include $filepath;
		} catch ( Throwable $e ) {
			echo "\n\n[Exception Caught]: " . $e->getMessage();
		}

		$output = ob_get_clean();
		$end_time = microtime( true );
		$execution_time = round( $end_time - $start_time, 4 );

		// 5. Cleanup
		@unlink( $filepath );

		return array(
			'success' => true,
			'output'  => $output,
			'time'    => $execution_time,
		);
	}
}
