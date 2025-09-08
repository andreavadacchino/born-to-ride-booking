<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita accessi diretti
}

class BTR_Admin_Interface {

    public function __construct() {
        // Aggiungi il menu amministrativo
        //add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Inizializza le impostazioni
        add_action( 'admin_init', array( $this, 'init_admin_settings' ) );

        // Carica gli stili e gli script nel backend
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Inizializza il metabox
        new BTR_Metabox();
    }

    /**
     * Caricamento degli stili e script per il backend
     *
     *
     *
     *
     */
    public function enqueue_admin_assets( $hook ) {
        // Carica gli stili solo nelle pagine del plugin
        if ( strpos( $hook, 'btr-booking' ) === false ) {
            return;
        }

        // Carica gli stili CSS
        wp_enqueue_style( 'btr-admin-styles', BTR_PLUGIN_URL . 'assets/css/admin-styles.css', array(), '1.0.0' );

        // Carica gli script JavaScript
        wp_enqueue_script( 'btr-admin-scripts', BTR_PLUGIN_URL . 'assets/js/admin-scripts.js', array( 'jquery' ), '1.0.0', true );

        // Passa variabili PHP a JavaScript
        wp_localize_script( 'btr-admin-scripts', 'btr_ajax_object', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'btr_ajax_nonce' ),
        ) );
    }

    /**
     * Creazione del menu amministrativo personalizzato
     */
    public function add_admin_menu() {
        // Il menu principale è già creato da BTR_Main_Menu, aggiungiamo solo il submenu
        add_submenu_page(
            'btr-booking',                                          // Parent slug
            __( 'Impostazioni', 'born-to-ride-booking' ),           // Titolo della pagina
            __( 'Impostazioni', 'born-to-ride-booking' ),           // Titolo del menu
            'manage_options',                                        // Capacità richiesta
            'btr-settings',                                         // Slug della pagina
            array( $this, 'render_admin_page' )                    // Callback per il rendering
        );
    }



    /**
     * Renderizza la pagina delle impostazioni del plugin
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Born to Ride Booking - Impostazioni', 'born-to-ride-booking' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'btr_booking_settings_group' );
                do_settings_sections( 'btr-booking' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Inizializza le impostazioni amministrative
     */
    public function init_admin_settings() {
        // Aggiungi una sezione delle impostazioni
        add_settings_section(
            'btr_booking_general_section',                              // ID della sezione
            __( 'Impostazioni Generali', 'born-to-ride-booking' ),      // Titolo
            array( $this, 'render_general_section' ),                   // Callback
            'btr-booking'                                               // Slug della pagina
        );

        // Campo per abilitare/disabilitare il plugin
        add_settings_field(
            'btr_booking_enable_option',                                 // ID del campo
            __( 'Abilita Prenotazioni', 'born-to-ride-booking' ),      // Titolo del campo
            array( $this, 'render_enable_option' ),                     // Callback per il rendering
            'btr-booking',                                              // Slug della pagina
            'btr_booking_general_section'                               // ID della sezione
        );

        // Campo per selezionare il tipo di gestione delle prenotazioni
        add_settings_field(
            'btr_booking_type_option',                                   // ID del campo
            __( 'Tipo di Gestione Prenotazioni', 'born-to-ride-booking' ), // Titolo del campo
            array( $this, 'render_type_option' ),                        // Callback per il rendering
            'btr-booking',                                              // Slug della pagina
            'btr_booking_general_section'                               // ID della sezione
        );

        // Sezione etichette personalizzate
        add_settings_section(
            'btr_booking_labels_section',
            __( 'Etichette Personalizzate', 'born-to-ride-booking' ),
            array( $this, 'render_labels_section' ),
            'btr-booking'
        );

        // Campo per etichetta Adulto singolare
        add_settings_field(
            'btr_label_adult_singular',
            __( 'Etichetta Adulto (singolare)', 'born-to-ride-booking' ),
            array( $this, 'render_label_adult_singular' ),
            'btr-booking',
            'btr_booking_labels_section'
        );

        // Campo per etichetta Adulto plurale
        add_settings_field(
            'btr_label_adult_plural',
            __( 'Etichetta Adulti (plurale)', 'born-to-ride-booking' ),
            array( $this, 'render_label_adult_plural' ),
            'btr-booking',
            'btr_booking_labels_section'
        );

        // Campo per etichetta Bambino singolare
        add_settings_field(
            'btr_label_child_singular',
            __( 'Etichetta Bambino (singolare)', 'born-to-ride-booking' ),
            array( $this, 'render_label_child_singular' ),
            'btr-booking',
            'btr_booking_labels_section'
        );

        // Campo per etichetta Bambino plurale
        add_settings_field(
            'btr_label_child_plural',
            __( 'Etichetta Bambini (plurale)', 'born-to-ride-booking' ),
            array( $this, 'render_label_child_plural' ),
            'btr-booking',
            'btr_booking_labels_section'
        );

        // Campo per etichetta Partecipante
        add_settings_field(
            'btr_label_participant',
            __( 'Etichetta Partecipante', 'born-to-ride-booking' ),
            array( $this, 'render_label_participant' ),
            'btr-booking',
            'btr_booking_labels_section'
        );

        // Campo per etichetta Neonato singolare
        add_settings_field(
            'btr_label_infant_singular',
            __( 'Etichetta Neonato (singolare)', 'born-to-ride-booking' ),
            array( $this, 'render_label_infant_singular' ),
            'btr-booking',
            'btr_booking_labels_section'
        );

        // Campo per etichetta Neonati plurale
        add_settings_field(
            'btr_label_infant_plural',
            __( 'Etichetta Neonati (plurale)', 'born-to-ride-booking' ),
            array( $this, 'render_label_infant_plural' ),
            'btr-booking',
            'btr_booking_labels_section'
        );

        // Registra le impostazioni
        register_setting( 'btr_booking_settings_group', 'btr_booking_enable_option', array( $this, 'sanitize_enable_option' ) );
        register_setting( 'btr_booking_settings_group', 'btr_booking_type_option', array( $this, 'sanitize_type_option' ) );
        register_setting( 'btr_booking_settings_group', 'btr_label_adult_singular', array( $this, 'sanitize_text_field' ) );
        register_setting( 'btr_booking_settings_group', 'btr_label_adult_plural', array( $this, 'sanitize_text_field' ) );
        register_setting( 'btr_booking_settings_group', 'btr_label_child_singular', array( $this, 'sanitize_text_field' ) );
        register_setting( 'btr_booking_settings_group', 'btr_label_child_plural', array( $this, 'sanitize_text_field' ) );
        register_setting( 'btr_booking_settings_group', 'btr_label_participant', array( $this, 'sanitize_text_field' ) );
        register_setting( 'btr_booking_settings_group', 'btr_label_infant_singular', array( $this, 'sanitize_text_field' ) );
        register_setting( 'btr_booking_settings_group', 'btr_label_infant_plural', array( $this, 'sanitize_text_field' ) );
    }

    /**
     * Renderizza la sezione delle impostazioni generali
     */
    public function render_general_section() {
        echo '<p>' . esc_html__( 'Configura le impostazioni generali per il plugin Born to Ride Booking.', 'born-to-ride-booking' ) . '</p>';
    }

    /**
     * Renderizza il campo di input per abilitare le prenotazioni
     */
    public function render_enable_option() {
        $value = get_option( 'btr_booking_enable_option', 0 );
        ?>
        <input type="checkbox" name="btr_booking_enable_option" value="1" <?php checked( 1, $value ); ?> />
        <label for="btr_booking_enable_option"><?php esc_html_e( 'Abilita il plugin per gestire le prenotazioni.', 'born-to-ride-booking' ); ?></label>
        <?php
    }

    /**
     * Sanitizza l'opzione 'Abilita Prenotazioni'
     */
    public function sanitize_enable_option( $input ) {
        return absint( $input );
    }

    /**
     * Renderizza il campo di selezione per il tipo di gestione delle prenotazioni
     */
    public function render_type_option() {
        $value = get_option( 'btr_booking_type_option', 'per_tipologia_camere' );
        ?>
        <select name="btr_booking_type_option" id="btr_booking_type_option" class="regular-text">
            <option value="per_tipologia_camere" <?php selected( $value, 'per_tipologia_camere' ); ?>><?php esc_html_e( 'Gestione per Tipologia di Camere', 'born-to-ride-booking' ); ?></option>
            <option value="per_numero_persone" <?php selected( $value, 'per_numero_persone' ); ?>><?php esc_html_e( 'Gestione per Numero di Persone', 'born-to-ride-booking' ); ?></option>
        </select>
        <?php
    }

    /**
     * Sanitizza l'opzione 'Tipo di Gestione Prenotazioni'
     */
    public function sanitize_type_option( $input ) {
        $valid = array( 'per_tipologia_camere', 'per_numero_persone' );
        if ( in_array( $input, $valid, true ) ) {
            return $input;
        }
        return 'per_tipologia_camere';
    }

    /**
     * Renderizza la sezione delle etichette
     */
    public function render_labels_section() {
        echo '<p>' . esc_html__( 'Personalizza le etichette utilizzate nel processo di prenotazione.', 'born-to-ride-booking' ) . '</p>';
    }

    /**
     * Renderizza il campo per etichetta Adulto singolare
     */
    public function render_label_adult_singular() {
        $value = get_option( 'btr_label_adult_singular', 'Adulto' );
        ?>
        <input type="text" name="btr_label_adult_singular" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e( 'Etichetta per un singolo adulto (es. Adulto)', 'born-to-ride-booking' ); ?></p>
        <?php
    }

    /**
     * Renderizza il campo per etichetta Adulto plurale
     */
    public function render_label_adult_plural() {
        $value = get_option( 'btr_label_adult_plural', 'Adulti' );
        ?>
        <input type="text" name="btr_label_adult_plural" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e( 'Etichetta per più adulti (es. Adulti)', 'born-to-ride-booking' ); ?></p>
        <?php
    }

    /**
     * Renderizza il campo per etichetta Bambino singolare
     */
    public function render_label_child_singular() {
        $value = get_option( 'btr_label_child_singular', 'Bambino' );
        ?>
        <input type="text" name="btr_label_child_singular" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e( 'Etichetta per un singolo bambino (es. Bambino)', 'born-to-ride-booking' ); ?></p>
        <?php
    }

    /**
     * Renderizza il campo per etichetta Bambino plurale
     */
    public function render_label_child_plural() {
        $value = get_option( 'btr_label_child_plural', 'Bambini' );
        ?>
        <input type="text" name="btr_label_child_plural" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e( 'Etichetta per più bambini (es. Bambini)', 'born-to-ride-booking' ); ?></p>
        <?php
    }

    /**
     * Renderizza il campo per etichetta Partecipante
     */
    public function render_label_participant() {
        $value = get_option( 'btr_label_participant', 'Partecipante' );
        ?>
        <input type="text" name="btr_label_participant" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e( 'Etichetta generica per partecipante (es. Partecipante)', 'born-to-ride-booking' ); ?></p>
        <?php
    }

    /**
     * Renderizza il campo per etichetta Neonato singolare
     */
    public function render_label_infant_singular() {
        $value = get_option( 'btr_label_infant_singular', 'Neonato' );
        ?>
        <input type="text" name="btr_label_infant_singular" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e( 'Etichetta per un neonato (es. Neonato)', 'born-to-ride-booking' ); ?></p>
        <?php
    }

    /**
     * Renderizza il campo per etichetta Neonati plurale
     */
    public function render_label_infant_plural() {
        $value = get_option( 'btr_label_infant_plural', 'Neonati' );
        ?>
        <input type="text" name="btr_label_infant_plural" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e( 'Etichetta per più neonati (es. Neonati)', 'born-to-ride-booking' ); ?></p>
        <?php
    }

    /**
     * Sanitizza i campi di testo
     */
    public function sanitize_text_field( $input ) {
        return sanitize_text_field( $input );
    }

}