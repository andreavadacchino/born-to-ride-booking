<?php
// Test AJAX semplice
if (!defined('ABSPATH')) {
    require_once('../../../../wp-load.php');
}

if (!current_user_can('manage_options')) {
    wp_die('Accesso negato');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test AJAX</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>Test AJAX</h1>
    <button id="test-ajax">Test AJAX Call</button>
    <div id="result"></div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#test-ajax').click(function() {
            console.log('Clicking test button...');
            
            var data = {
                action: 'btr_build_release_ajax',
                ajax_action: 'btr_get_suggested_changes',
                _wpnonce: '<?php echo wp_create_nonce('btr_build_release_nonce'); ?>'
            };
            
            console.log('Data:', data);
            console.log('URL:', '<?php echo admin_url('admin-ajax.php'); ?>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: data,
                success: function(response) {
                    console.log('Success:', response);
                    $('#result').html('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
                },
                error: function(xhr, status, error) {
                    console.log('Error:', error);
                    console.log('Status:', status);
                    console.log('Response:', xhr.responseText);
                    $('#result').html('Error: ' + error + '<br>Response: <pre>' + xhr.responseText + '</pre>');
                }
            });
        });
    });
    </script>
</body>
</html>