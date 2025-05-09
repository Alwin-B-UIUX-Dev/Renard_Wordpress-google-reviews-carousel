<?php
/*
Plugin Name: Google Reviews Carousel
Description: Affiche les avis Google de votre boutique en carousel grâce à un shortcode configurable.
Version: 1.1
Author: Alwin Bolzon
Text Domain: google-reviews-carousel
Domain Path: /languages
*/

if ( ! defined('ABSPATH') ) exit; // Empêche l'accès direct au fichier

// ==== 1. Chargement de la traduction ====
add_action('plugins_loaded', 'grc_load_textdomain');
function grc_load_textdomain() {
    load_plugin_textdomain('google-reviews-carousel', false, dirname(plugin_basename(__FILE__)) . '/languages' );
}

// ==== 2. Ajout du menu dans l'admin ====
add_action('admin_menu', 'grc_add_admin_menu');
function grc_add_admin_menu() {
    add_options_page(
        __('Google Reviews Carousel', 'google-reviews-carousel'),
        __('Google Reviews Carousel', 'google-reviews-carousel'),
        'manage_options',
        'google-reviews-carousel',
        'grc_options_page'
    );
}

// ==== 3. Initialisation des paramètres et champs admin ====
add_action('admin_init', 'grc_settings_init');
function grc_settings_init() {
    register_setting('grc_settings', 'grc_options');

    add_settings_section(
        'grc_settings_section',
        __('Paramètres des avis Google', 'google-reviews-carousel'),
        'grc_settings_section_cb',
        'google-reviews-carousel'
    );

    add_settings_field(
        'grc_place_id',
        __('ID de la fiche Google My Business', 'google-reviews-carousel'),
        'grc_place_id_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    add_settings_field(
        'grc_api_key',
        __('Clé API Google', 'google-reviews-carousel'),
        'grc_api_key_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    add_settings_field(
        'grc_reviews_count',
        __('Nombre d\'avis à afficher dans le carousel', 'google-reviews-carousel'),
        'grc_reviews_count_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    add_settings_field(
        'grc_duration',
        __('Durée (en secondes) d\'affichage de chaque "slide"', 'google-reviews-carousel'),
        'grc_duration_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
}
function grc_settings_section_cb() {
    echo '<p>' . __('Renseignez votre Place ID et votre clé API Google.<br/>
    <strong>Attention : l\'API Google ne fournit jamais plus de 5 avis publics !</strong><br/>
    <a target="_blank" href="https://developers.google.com/maps/documentation/places/web-service/details">Aide Google Places API</a>', 'google-reviews-carousel') . '</p>';
}

function grc_place_id_render() {
    $options = get_option( 'grc_options' );
    ?>
    <input type='text' name='grc_options[grc_place_id]' value='<?php echo esc_attr($options['grc_place_id'] ?? ''); ?>' style="width:400px;" />
    <?php
}
function grc_api_key_render() {
    $options = get_option( 'grc_options' );
    ?>
    <input type='text' name='grc_options[grc_api_key]' value='<?php echo esc_attr($options['grc_api_key'] ?? ''); ?>' style="width:400px;" />
    <?php
}
function grc_reviews_count_render() {
    $options = get_option( 'grc_options' );
    ?>
    <input type='number' name='grc_options[grc_reviews_count]' value='<?php echo esc_attr($options['grc_reviews_count'] ?? 3); ?>' min="1" max="5" />
    <span style="color:#666;font-size:0.9em;"><?php _e('(max 5, limitation API Google)', 'google-reviews-carousel'); ?></span>
    <?php
}
function grc_duration_render() {
    $options = get_option( 'grc_options' );
    ?>
    <input type='number' name='grc_options[grc_duration]' value='<?php echo esc_attr($options['grc_duration'] ?? 15); ?>' min="1" max="60" /> <?php _e('secondes', 'google-reviews-carousel'); ?>
    <?php
}

function grc_options_page() {
    ?>
    <div class="wrap">
        <h2>Google Reviews Carousel</h2>
        <?php
        // Affiche une notice si mauvais identifiants API
        if ( isset($_GET['settings-updated']) )
            grc_admin_test_api();

        settings_errors();
        ?>
        <form action='options.php' method='post'>
            <?php
            settings_fields('grc_settings');
            do_settings_sections('google-reviews-carousel');
            submit_button();
            ?>
        </form>
        <hr>
        <h3><?php _e('Utilisation', 'google-reviews-carousel'); ?></h3>
        <p><?php _e('Ajoutez le shortcode', 'google-reviews-carousel'); ?> <code>[grc_google_reviews]</code> <?php _e('sur la page ou l\'article où vous souhaitez afficher les avis en carousel.', 'google-reviews-carousel'); ?></p>
    </div>
    <?php
}

// Validation d'API côté admin
function grc_admin_test_api() {
    $options = get_option('grc_options');
    if ( empty($options['grc_place_id']) || empty($options['grc_api_key']) )
        return;
    $url = "https://maps.googleapis.com/maps/api/place/details/json?place_id={$options['grc_place_id']}&fields=reviews&key={$options['grc_api_key']}";
    $response = wp_remote_get($url);
    if ( is_wp_error($response) ) {
        add_settings_error('grc_options', 'grc_api_error',  __('Erreur lors de la connexion à l\'API Google.', 'google-reviews-carousel'), 'error');
        return;
    }
    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);
    if (!isset($json['result']) && isset($json['status']) && $json['status'] !== 'OK') {
        add_settings_error('grc_options', 'grc_api_error',  sprintf(__('Erreur Google API : %s', 'google-reviews-carousel'), $json['status']), 'error');
    }
}

// ==== 4. Fonction pour récupérer les avis Google Places avec cache ====
function grc_get_google_reviews() {
    $options = get_option('grc_options');
    if (empty($options['grc_place_id']) || empty($options['grc_api_key'])) {
        return [];
    }
    $place_id = $options['grc_place_id'];
    $api_key = $options['grc_api_key'];
    $transient_key = 'grc_reviews_cache_' . md5($place_id);
    $cached = get_transient($transient_key);
    if (false !== $cached) {
        return $cached;
    }
    $url = "https://maps.googleapis.com/maps/api/place/details/json?place_id={$place_id}&fields=reviews,rating,user_ratings_total,name&key={$api_key}";
    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        return [];
    }
    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    // Vérifie si les avis existent, gestion des messages d'erreur
    if ( isset($json['result']['reviews']) && is_array($json['result']['reviews']) ) {
        // Range d'abord par date décroissante, puis limite à 5 (protection API Google)
        usort($json['result']['reviews'], function($a, $b) {
            return $b['time'] - $a['time'];
        });
        $reviews = array_slice($json['result']['reviews'], 0, 5);
        set_transient($transient_key, $reviews, 3600); // 1h
        return $reviews;
    }
    return [];
}

// ==== 5. Chargement conditionnel de Slick (JS/CSS) uniquement si shortcode détecté ====
function grc_maybe_enqueue_scripts() {
    $enqueue = false;
    // Check si le shortcode existe dans le post affiché
    if ( is_singular() ) {
        global $post;
        if ( has_shortcode($post->post_content, 'grc_google_reviews') ) 
            $enqueue = true;
    }
    // Peut aussi être affiché dans les widgets ou en ACF, donc permet le forçage via filtre si besoin
    $enqueue = apply_filters('grc_force_enqueue_scripts', $enqueue);
    if ( $enqueue ) {
        // Slick Carousel CSS
        wp_enqueue_style('slick-css', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css');
        // Ton style custom
        wp_enqueue_style('grc-style', plugins_url('grc-style.css', __FILE__));
        // Slick Carousel JS
        wp_enqueue_script('slick-js', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array('jquery'), '1.8.1', true);
    }
}
add_action('wp', 'grc_maybe_enqueue_scripts');

// ==== 6. Shortcode d'affichage : [grc_google_reviews] ====
add_shortcode('grc_google_reviews', 'grc_google_reviews_shortcode');
function grc_google_reviews_shortcode($atts) {
    $options = get_option('grc_options');
    $show_count = min( intval($options['grc_reviews_count'] ?? 3), 5 );
    $duration = intval($options['grc_duration'] ?? 15) * 1000; // ms pour JS

    $reviews = grc_get_google_reviews();
    if (empty($reviews)) {
        return '<em>' . __('Aucun avis Google trouvé ou configuration incomplète.', 'google-reviews-carousel') . '</em>';
    }

    ob_start();
    ?>
    <div class="grc-reviews-carousel">
        <?php foreach ($reviews as $review): ?>
            <div class="grc-review">
                <div class="grc-author"><?php echo esc_html($review['author_name']); ?></div>
                <div class="grc-rating" aria-label="<?php printf(esc_attr__('Note : %d sur 5', 'google-reviews-carousel'), $review['rating']); ?>">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php echo ($i <= $review['rating']) ? '★' : '☆'; ?>
                    <?php endfor; ?>
                </div>
                <div class="grc-text"><?php echo esc_html($review['text']); ?></div>
                <?php if (!empty($review['time'])): ?>
                    <div class="grc-date"><small><?php echo date_i18n('d/m/Y',   $review['time']); ?></small></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function(  $  ){
            $('.grc-reviews-carousel').slick({
                slidesToShow: <?php echo $show_count; ?>,
                slidesToScroll: 1,
                autoplay: true,
                autoplaySpeed: <?php echo $duration; ?>,
                dots: true,
                arrows: true,
                adaptiveHeight: true
            });
        });
    </script>
    <?php
    return ob_get_clean();
}

// ==== 7. Nettoyage des options à la désinstallation ====
register_uninstall_hook(__FILE__, 'grc_plugin_uninstall');
function grc_plugin_uninstall() {
    delete_option('grc_options');
    // Supprimer aussi tous les transients de review si besoin
    global $wpdb;
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_grc_reviews_cache_%'");
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_grc_reviews_cache_%'");
}
