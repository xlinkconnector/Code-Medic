<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeMedic_Admin {

	private $surgeon;
	private $sandbox;

	public function __construct( $surgeon, $sandbox ) {
		$this->surgeon = $surgeon;
		$this->sandbox = $sandbox;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		
        // AJAX Endpoints
		add_action( 'wp_ajax_codemedic_get_diagnosis', array( $this, 'ajax_get_diagnosis' ) );
		add_action( 'wp_ajax_codemedic_run_sandbox', array( $this, 'ajax_run_sandbox' ) );
		add_action( 'wp_ajax_codemedic_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_codemedic_clear_logs', array( $this, 'ajax_clear_logs' ) );
	}

	public function register_menu() {
		add_menu_page(
			'Code Medic',
			'Code Medic',
			'manage_options',
			'code-medic',
			array( $this, 'render_dashboard' ),
			'dashicons-heart', // The Medic Icon
			99
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_code-medic' !== $hook ) {
			return;
		}

		// Enqueue WordPress Code Editor (CodeMirror)
		$settings = wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );
		
		wp_enqueue_style( 'codemedic-css', CODEMEDIC_URL . 'admin/css/codemedic-admin.css', array(), '1.0.0' );
		
        // Inline CSS for simplicity of file generation
        wp_add_inline_style( 'codemedic-css', "
            .codemedic-wrapper { max-width: 1200px; margin: 20px 0; }
            .codemedic-header { background: #fff; padding: 20px; border-left: 4px solid #72aee6; box-shadow: 0 1px 2px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
            .codemedic-header h1 { margin: 0; font-size: 24px; color: #1d2327; }
            .codemedic-nav { margin-bottom: 20px; border-bottom: 1px solid #c3c4c7; }
            .codemedic-nav button { background: none; border: none; padding: 10px 20px; font-size: 14px; cursor: pointer; border-bottom: 2px solid transparent; margin-right: 5px; }
            .codemedic-nav button.active { border-bottom-color: #2271b1; color: #2271b1; font-weight: 600; }
            .codemedic-tab { display: none; }
            .codemedic-tab.active { display: block; }
            
            /* Logs Table */
            .log-card { background: #fff; border: 1px solid #c3c4c7; margin-bottom: 10px; padding: 15px; border-left: 4px solid #d63638; }
            .log-card.resolved { border-left-color: #00a32a; }
            .log-meta { font-size: 12px; color: #646970; margin-bottom: 5px; }
            .log-code { background: #f0f0f1; padding: 10px; font-family: monospace; overflow-x: auto; margin: 10px 0; border-radius: 4px; }
            .log-actions { margin-top: 10px; }

            /* Sandbox */
            .sandbox-container { display: flex; gap: 20px; height: 600px; }
            .sandbox-editor { flex: 1; border: 1px solid #c3c4c7; background: #fff; display: flex; flex-direction: column; }
            .sandbox-output { flex: 1; border: 1px solid #c3c4c7; background: #1e1e1e; color: #0f0; padding: 15px; font-family: monospace; white-space: pre-wrap; overflow-y: auto; }
            .sandbox-toolbar { padding: 10px; background: #f6f7f7; border-bottom: 1px solid #c3c4c7; display: flex; justify-content: space-between; }
            .CodeMirror { height: 100% !important; }
            
            /* AI Diagnosis Box */
            .ai-diagnosis { background: #f0f6fc; border: 1px solid #72aee6; padding: 15px; margin-top: 10px; border-radius: 4px; display: none; }
            .ai-diagnosis h4 { margin-top: 0; color: #2271b1; }
        " );

		wp_enqueue_script( 'codemedic-js', CODEMEDIC_URL . 'admin/js/codemedic-admin.js', array( 'jquery', 'wp-util' ), '1.0.0', true );
        
        // Pass PHP data to JS
		wp_add_inline_script( 'codemedic-js', sprintf( 'var codemedicSettings = %s;', wp_json_encode( array(
			'codeEditor' => $settings,
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'codemedic_admin_nonce' )
		) ) ) );
	}

	public function render_dashboard() {
		$logs = CodeMedic_Sentinel::instance()->get_logs();
		$api_key = get_option( 'codemedic_openai_key' );
		?>
		<div class="wrap codemedic-wrapper">
			<div class="codemedic-header">
				<div>
                    <h1>Code Medic <span class="dashicons dashicons-heart" style="color: #d63638;"></span></h1>
                    <p>Self-Healing AI & Developer Sandbox</p>
                </div>
                <div>
                    <span class="status-badge" style="background: <?php echo $api_key ? '#d1e4dd' : '#f8d7da'; ?>; color: <?php echo $api_key ? '#0f5132' : '#842029'; ?>; padding: 5px 10px; border-radius: 20px; font-size: 12px;">
                        <?php echo $api_key ? 'AI Connected' : 'AI Disconnected'; ?>
                    </span>
                </div>
			</div>

			<nav class="codemedic-nav">
				<button class="nav-tab-btn active" data-tab="dashboard">Diagnosis Logs</button>
				<button class="nav-tab-btn" data-tab="sandbox">Builder Sandbox</button>
				<button class="nav-tab-btn" data-tab="settings">Settings</button>
			</nav>

			<!-- Tab 1: Dashboard -->
			<div id="dashboard" class="codemedic-tab active">
                <div style="margin-bottom: 15px; text-align: right;">
                    <button class="button" id="clear-logs">Clear Logs</button>
                </div>
				<?php if ( empty( $logs ) ) : ?>
					<div class="notice notice-success inline"><p>No fatal errors detected. Your site is healthy!</p></div>
				<?php else : ?>
					<?php foreach ( $logs as $index => $log ) : ?>
						<div class="log-card" id="log-<?php echo $index; ?>">
							<div class="log-meta">
								<strong><?php echo date( 'Y-m-d H:i:s', $log['timestamp'] ); ?></strong> | 
								<?php echo esc_html( $log['file'] ); ?>:<?php echo esc_html( $log['line'] ); ?>
							</div>
							<div class="log-message">
								<strong>Error:</strong> <?php echo esc_html( $log['message'] ); ?>
							</div>
							<div class="log-code">
                                <?php echo nl2br( esc_html( $log['context'] ) ); ?>
                            </div>
							<div class="log-actions">
								<button class="button button-primary diagnose-btn" data-log='<?php echo json_encode( $log ); ?>'>
                                    <span class="dashicons dashicons-superhero-alt"></span> Ask Code Medic
                                </button>
                                <div class="ai-diagnosis"></div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<!-- Tab 2: Sandbox -->
			<div id="sandbox" class="codemedic-tab">
				<div class="sandbox-container">
					<div class="sandbox-editor">
						<div class="sandbox-toolbar">
                            <span><strong>PHP Builder</strong></span>
                            <div>
                                <button class="button button-primary" id="run-code"><span class="dashicons dashicons-controls-play"></span> Run Code</button>
                            </div>
                        </div>
						<textarea id="codemedic_sandbox_code" name="codemedic_sandbox_code"><?php echo "<?php\n// Write your test code here.\n// All output is captured safely.\n\necho 'Hello from Code Medic Sandbox!';\n\nglobal \$wpdb;\n// \$results = \$wpdb->get_results(\"SELECT * FROM \$wpdb->users LIMIT 1\");\n// print_r(\$results);"; ?></textarea>
					</div>
					<div class="sandbox-output">
						<div id="sandbox-result">Output will appear here...</div>
					</div>
				</div>
			</div>

			<!-- Tab 3: Settings -->
			<div id="settings" class="codemedic-tab">
				<form id="codemedic-settings-form">
					<table class="form-table">
						<tr>
							<th scope="row">OpenAI API Key</th>
							<td>
								<input type="password" name="api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text">
								<p class="description">Required for AI Diagnosis features.</p>
							</td>
						</tr>
					</table>
					<p class="submit">
						<button type="submit" class="button button-primary">Save Settings</button>
					</p>
                    <div id="settings-message"></div>
				</form>
			</div>
		</div>
		<?php
	}

    /* AJAX Handlers */

	public function ajax_get_diagnosis() {
		check_ajax_referer( 'codemedic_admin_nonce', 'nonce' );
		
        // Unslash and decode the log data
        $log_data = isset( $_POST['log'] ) ? $_POST['log'] : array();
        if( empty( $log_data ) ) wp_send_json_error( 'No data' );

		$diagnosis = $this->surgeon->diagnose( $log_data );

		if ( is_wp_error( $diagnosis ) ) {
			wp_send_json_error( $diagnosis->get_error_message() );
		}

		wp_send_json_success( $diagnosis );
	}

	public function ajax_run_sandbox() {
		check_ajax_referer( 'codemedic_admin_nonce', 'nonce' );
		$code = isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : '';
		
		$result = $this->sandbox->run_code( $code );
		
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	public function ajax_save_settings() {
		check_ajax_referer( 'codemedic_admin_nonce', 'nonce' );
		$key = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';
		update_option( 'codemedic_openai_key', $key );
		wp_send_json_success( 'Settings saved.' );
	}

    public function ajax_clear_logs() {
        check_ajax_referer( 'codemedic_admin_nonce', 'nonce' );
        CodeMedic_Sentinel::instance()->clear_logs();
        wp_send_json_success();
    }
}
