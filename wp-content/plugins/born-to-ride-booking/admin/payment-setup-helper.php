<?php
/**
 * Helper per setup sistema pagamenti
 */

// Aggiungi voce temporanea nel menu admin
add_action('admin_menu', function() {
    add_submenu_page(
        'btr-dashboard',
        'Setup Pagamenti',
        'Setup Pagamenti',
        'manage_options',
        'btr-payment-setup',
        'btr_payment_setup_page'
    );
});

function btr_payment_setup_page() {
    ?>
    <div class="wrap">
        <h1>Setup Sistema Pagamenti</h1>
        
        <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">
            <h2>Stato Configurazione</h2>
            <?php
            $page_id = get_option('btr_payment_selection_page_id');
            $page = $page_id ? get_post($page_id) : null;
            $page_by_slug = get_page_by_path('selezione-piano-pagamento');
            ?>
            
            <table class="widefat">
                <tr>
                    <td><strong>Pagina configurata:</strong></td>
                    <td><?php echo $page ? '✅ Sì' : '❌ No'; ?></td>
                </tr>
                <tr>
                    <td><strong>ID pagina:</strong></td>
                    <td><?php echo $page_id ?: 'Non configurato'; ?></td>
                </tr>
                <tr>
                    <td><strong>Pagina esistente:</strong></td>
                    <td><?php echo $page_by_slug ? '✅ Sì (ID: ' . $page_by_slug->ID . ')' : '❌ No'; ?></td>
                </tr>
                <?php if ($page): ?>
                <tr>
                    <td><strong>URL pagina:</strong></td>
                    <td><a href="<?php echo get_permalink($page->ID); ?>" target="_blank"><?php echo get_permalink($page->ID); ?></a></td>
                </tr>
                <?php endif; ?>
            </table>
            
            <h3>Azioni rapide:</h3>
            <p>
                <a href="<?php echo home_url('/fix-payment-page-config.php'); ?>" 
                   class="button button-primary" 
                   target="_blank">
                    Esegui Fix Configurazione
                </a>
                
                <a href="<?php echo home_url('/debug-redirect-issue.php'); ?>" 
                   class="button" 
                   target="_blank">
                    Debug Redirect
                </a>
                
                <a href="<?php echo home_url('/test-payment-selection-flow.php'); ?>" 
                   class="button" 
                   target="_blank">
                    Test Flusso Completo
                </a>
            </p>
            
            <?php if (!$page && !$page_by_slug): ?>
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 20px 0;">
                <p><strong>⚠️ Attenzione:</strong> La pagina di selezione pagamento non esiste. Clicca "Esegui Fix Configurazione" per crearla automaticamente.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">
            <h2>Istruzioni</h2>
            <ol>
                <li>Clicca su "Esegui Fix Configurazione" per verificare e sistemare la configurazione</li>
                <li>Usa "Debug Redirect" per testare il redirect dopo il salvataggio anagrafici</li>
                <li>Usa "Test Flusso Completo" per verificare l'intero processo</li>
            </ol>
            
            <h3>Flusso atteso:</h3>
            <p style="font-size: 16px;">
                <strong>Anagrafici</strong> → <strong>Selezione Piano Pagamento</strong> → <strong>Checkout</strong>
            </p>
        </div>
    </div>
    <?php
}