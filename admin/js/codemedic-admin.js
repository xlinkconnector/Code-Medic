jQuery(document).ready(function($) {
    
    // 1. Tab Navigation
    $('.nav-tab-btn').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.codemedic-tab').removeClass('active');
        $('#' + $(this).data('tab')).addClass('active');
        
        // Refresh CodeMirror when tab becomes visible
        if($(this).data('tab') === 'sandbox' && sandboxEditor) {
            sandboxEditor.codemirror.refresh();
        }
    });

    // 2. Initialize Code Editor for Sandbox
    var sandboxEditor;
    if ($('#codemedic_sandbox_code').length && codemedicSettings.codeEditor) {
        sandboxEditor = wp.codeEditor.initialize($('#codemedic_sandbox_code'), codemedicSettings.codeEditor);
    }

    // 3. Run Sandbox Code
    $('#run-code').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $output = $('#sandbox-result');
        
        $btn.addClass('updating-message').prop('disabled', true);
        $output.text('Running...');

        // Sync CodeMirror to textarea
        sandboxEditor.codemirror.save();
        var code = $('#codemedic_sandbox_code').val();

        $.post(codemedicSettings.ajaxUrl, {
            action: 'codemedic_run_sandbox',
            nonce: codemedicSettings.nonce,
            code: code
        }).done(function(response) {
            if (response.success) {
                var output = response.data.output;
                if (!output) output = '[No Output]';
                $output.text(output + '\n\n[Execution Time: ' + response.data.time + 's]');
            } else {
                // Handle JSON error from Sentinel or regular error
                var errMsg = response.data.error || response.data;
                $output.text(errMsg);
            }
        }).fail(function(xhr) {
             // Catch fatal errors that Sentinel might return as JSON string in body
             // or actual 500s if Sentinel failed
             if(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.error) {
                 $output.text(xhr.responseJSON.data.error);
             } else {
                 $output.text('Server Error: ' + xhr.status + ' ' + xhr.statusText);
             }
        }).always(function() {
            $btn.removeClass('updating-message').prop('disabled', false);
        });
    });

    // 4. AI Diagnosis
    $('.diagnose-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $card = $btn.closest('.log-card');
        var $resultBox = $card.find('.ai-diagnosis');
        var logData = $btn.data('log');

        $btn.prop('disabled', true).text('Consulting AI...');
        
        $.post(codemedicSettings.ajaxUrl, {
            action: 'codemedic_get_diagnosis',
            nonce: codemedicSettings.nonce,
            log: logData
        }).done(function(response) {
            if (response.success) {
                var data = response.data;
                var html = '<h4>Diagnosis Analysis</h4>';
                html += '<p>' + data.analysis + '</p>';
                html += '<h4>Suggested Fix (Safety: ' + data.safety_score + '%)</h4>';
                html += '<pre style="background:#fff; padding:10px; overflow:auto;">' + escapeHtml(data.fixed_code) + '</pre>';
                
                $resultBox.html(html).slideDown();
            } else {
                alert('AI Error: ' + response.data);
            }
        }).always(function() {
            $btn.prop('disabled', false).text('Ask Code Medic');
        });
    });

    // 5. Settings Save
    $('#codemedic-settings-form').on('submit', function(e) {
        e.preventDefault();
        $.post(codemedicSettings.ajaxUrl, {
            action: 'codemedic_save_settings',
            nonce: codemedicSettings.nonce,
            api_key: $('input[name="api_key"]').val()
        }).done(function(response) {
            $('#settings-message').html('<span style="color:green;">' + response.data + '</span>');
        });
    });
    
    // 6. Clear Logs
    $('#clear-logs').on('click', function(e) {
        if(!confirm('Clear all error logs?')) return;
        $.post(codemedicSettings.ajaxUrl, {
            action: 'codemedic_clear_logs',
            nonce: codemedicSettings.nonce
        }).done(function() {
            location.reload();
        });
    });

    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
