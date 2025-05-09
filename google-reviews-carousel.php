<?php
/*
Plugin Name: Google Reviews Carousel
Description: Affiche les avis Google de votre boutique en carousel grâce à un shortcode configurable.
Version: 1.3
Author: Alwin Bolzon
Text Domain: google-reviews-carousel
Domain Path: /languages
*/

if (! defined('ABSPATH')) exit; // Empêche l'accès direct au fichier

// ==== 1. Chargement de la traduction ====
add_action('plugins_loaded', 'grc_load_textdomain');
function grc_load_textdomain()
{
    load_plugin_textdomain('google-reviews-carousel', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// ==== 2. Ajout du menu dans l'admin ====
add_action('admin_menu', 'grc_add_admin_menu');
function grc_add_admin_menu()
{
    add_options_page(
        __('Google Reviews Carousel', 'google-reviews-carousel'),
        __('Google Reviews Carousel', 'google-reviews-carousel'),
        'manage_options',
        'google-reviews-carousel',
        'grc_options_page'
    );
}

// ==== 3. Init & sanitization des paramètres et champs admin ====

// SANITIZE : gère la sauvegarde sécurisée et logique des cases à cocher
function grc_options_sanitize($options)
{
    $options['grc_show_stars']       = !empty($options['grc_show_stars'])       ? 1 : 0;
    $options['grc_show_photo']       = !empty($options['grc_show_photo'])       ? 1 : 0;
    $options['grc_adaptive_height']  = !empty($options['grc_adaptive_height'])  ? 1 : 0;
    $options['grc_text_size']        = isset($options['grc_text_size']) ? max(12, min(32, intval($options['grc_text_size']))) : 16;
    $options['grc_text_limit']       = isset($options['grc_text_limit']) ? max(30, intval($options['grc_text_limit'])) : 200;
    $options['grc_stars_color'] = isset($options['grc_stars_color']) && preg_match('/^#[a-f0-9]{6}$/i', $options['grc_stars_color']) ? $options['grc_stars_color'] : '#ffc107';
    $options['grc_card_bg_color'] = isset($options['grc_card_bg_color']) && preg_match('/^#[a-f0-9]{6}$/i', $options['grc_card_bg_color']) ? $options['grc_card_bg_color'] : '#ffffff';
    // Ajoute ici d'autres vérifications au besoin
    return $options;
}

add_action('admin_init', 'grc_settings_init');
function grc_settings_init()
{
    register_setting('grc_settings', 'grc_options', 'grc_options_sanitize');

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

    // Options de customisation visuelle
    add_settings_field(
        'grc_color_bg',
        __('Couleur de fond du carousel', 'google-reviews-carousel'),
        'grc_color_bg_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    add_settings_field(
        'grc_card_bg_color',
        __('Couleur de fond des cartes', 'google-reviews-carousel'),
        'grc_card_bg_color_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    add_settings_field(
        'grc_color_txt',
        __('Couleur du texte', 'google-reviews-carousel'),
        'grc_color_txt_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    add_settings_field(
        'grc_stars_color',
        __('Couleur des étoiles', 'google-reviews-carousel'),
        'grc_stars_color_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    add_settings_field(
        'grc_show_stars',
        __('Afficher les étoiles', 'google-reviews-carousel'),
        'grc_show_stars_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    add_settings_field(
        'grc_show_photo',
        __('Afficher la photo de l\'auteur', 'google-reviews-carousel'),
        'grc_show_photo_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    add_settings_field(
        'grc_adaptive_height',
        __('Hauteur adaptative du carousel (si NON coché, les avis auront tous la même hauteur)', 'google-reviews-carousel'),
        'grc_adaptive_height_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    add_settings_field(
        'grc_text_size',
        __('Taille du texte des avis (px)', 'google-reviews-carousel'),
        'grc_text_size_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    add_settings_field(
        'grc_text_limit',
        __("Limiter le texte des avis à (X caractères)", "google-reviews-carousel"),
        'grc_text_limit_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
}

function grc_settings_section_cb()
{
    echo '<p>' . __('Renseignez votre Place ID et votre clé API Google. <strong>Attention&nbsp;: l\'API Google ne fournit jamais plus de 5 avis.</strong>', 'google-reviews-carousel') . '</p>';
}

// == Champs classiques ==
function grc_place_id_render()
{
    $options = get_option('grc_options');
?>
    <input type="text" name="grc_options[grc_place_id]" value="<?php echo esc_attr($options['grc_place_id'] ?? ''); ?>" style="width: 300px;" />
<?php
}
function grc_api_key_render()
{
    $options = get_option('grc_options');
?>
    <input type="text" name="grc_options[grc_api_key]" value="<?php echo esc_attr($options['grc_api_key'] ?? ''); ?>" style="width: 300px;" />
<?php
}
function grc_reviews_count_render()
{
    $options = get_option('grc_options');
?>
    <input type="number" name="grc_options[grc_reviews_count]" value="<?php echo esc_attr($options['grc_reviews_count'] ?? 3); ?>" min="1" max="5" style="width: 60px;" />
<?php
}
function grc_duration_render()
{
    $options = get_option('grc_options');
?>
    <input type="number" name="grc_options[grc_duration]" value="<?php echo esc_attr($options['grc_duration'] ?? 15); ?>" min="3" max="120" style="width: 60px;" />
    <?php esc_html_e('secondes', 'google-reviews-carousel'); ?>
<?php
}
function grc_card_bg_color_render()
{
    $options = get_option('grc_options');
?>
    <input type="color" name="grc_options[grc_card_bg_color]" value="<?php echo esc_attr($options['grc_card_bg_color'] ?? '#ffffff'); ?>" />
<?php
}

// == NOUVEAUX CHAMPS DE CUSTOMISATION ==
function grc_color_bg_render()
{
    $options = get_option('grc_options');
?>
    <input type="color" name="grc_options[grc_color_bg]" value="<?php echo esc_attr($options['grc_color_bg'] ?? '#ffffff'); ?>" />
<?php
}
function grc_color_txt_render()
{
    $options = get_option('grc_options');
?>
    <input type="color" name="grc_options[grc_color_txt]" value="<?php echo esc_attr($options['grc_color_txt'] ?? '#333333'); ?>" />
<?php
}
function grc_show_stars_render()
{
    $options = get_option('grc_options');
?>
    <input type="checkbox" name="grc_options[grc_show_stars]" value="1" <?php checked(isset($options['grc_show_stars']) && $options['grc_show_stars']); ?> />
<?php
}
function grc_show_photo_render()
{
    $options = get_option('grc_options');
?>
    <input type="checkbox" name="grc_options[grc_show_photo]" value="1" <?php checked(isset($options['grc_show_photo']) && $options['grc_show_photo']); ?> />
<?php
}
function grc_adaptive_height_render()
{
    $options = get_option('grc_options');
?>
    <input type="checkbox" name="grc_options[grc_adaptive_height]" value="1" <?php checked(isset($options['grc_adaptive_height']) && $options['grc_adaptive_height']); ?> />
<?php
}
function grc_text_size_render()
{
    $options = get_option('grc_options');
?>
    <input type="number" name="grc_options[grc_text_size]" value="<?php echo esc_attr($options['grc_text_size'] ?? 16); ?>" min="12" max="32" style="width: 60px;" /> px
<?php
}
function grc_text_limit_render()
{
    $options = get_option('grc_options');
?>
    <input type="number"
        name="grc_options[grc_text_limit]"
        value="<?php echo isset($options['grc_text_limit']) ? intval($options['grc_text_limit']) : 200; ?>"
        min="30" step="10" style="width:70px;">
<?php
}
function grc_stars_color_render()
{
    $options = get_option('grc_options');
?>
    <input type="color" name="grc_options[grc_stars_color]" value="<?php echo esc_attr($options['grc_stars_color'] ?? '#ffc107'); ?>" />
<?php
}


// ==== 4. Génération de la page d’options ====
function grc_options_page()
{
?>
    <div class="wrap">
        <h1><?php esc_html_e('Google Reviews Carousel', 'google-reviews-carousel'); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('grc_settings');
            do_settings_sections('google-reviews-carousel');
            submit_button();
            ?>
        </form>
        <hr>
        <h2><?php esc_html_e('Aperçu (à intégrer via le shortcode [grc_google_reviews])', 'google-reviews-carousel'); ?></h2>
        <p><em><?php esc_html_e('L\'aperçu en live arrivera bientôt ! En attendant, utilisez le shortcode sur une page ou un article.', 'google-reviews-carousel'); ?></em></p>
    </div>
<?php
}

// ==== 5. Chargement CSS/JS uniquement sur le front ====
add_action('wp_enqueue_scripts', 'grc_maybe_enqueue_scripts');
function grc_maybe_enqueue_scripts()
{
    // Ajoute ici Slick Carousel et ton propre CSS/JS si besoin
    wp_enqueue_style('slick-carousel', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css');
    wp_enqueue_script('slick-carousel', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array('jquery'), '1.8.1', true);
    // Un petit style propre
    wp_add_inline_style('slick-carousel', '.grc-reviews-carousel { margin: 26px auto; } .grc-review { outline: none; border-radius: 8px; box-shadow:0 2px 12px #0001; margin: 8px; padding: 16px 24px;} .grc-rating{font-size:18px;color:#ffc107} .grc-author{font-weight:bold;} .grc-photo{border-radius:50%;width:40px;height:40px;object-fit:cover;} .grc-read-more{display:inline-block;margin-top:6px;font-size:90%;color:#1558fb;text-decoration:underline;}');
}

// ==== 6. Shortcode : [grc_google_reviews] ====
add_shortcode('grc_google_reviews', 'grc_google_reviews_shortcode');
function grc_google_reviews_shortcode($atts)
{
    $options = get_option('grc_options');
    $show_count = min(intval($options['grc_reviews_count'] ?? 3), 5);
    $duration = intval($options['grc_duration'] ?? 15) * 1000; // ms pour JS

    // Options user
    $bg         = $options['grc_color_bg'] ?? '#fff';
    $color      = $options['grc_color_txt'] ?? '#222';
    $txt_size   = intval($options['grc_text_size'] ?? 16);
    $show_stars = !empty($options['grc_show_stars']);
    $show_photo = !empty($options['grc_show_photo']);
    $adaptive   = !empty($options['grc_adaptive_height']);
    $char_limit = intval($options['grc_text_limit'] ?? 200);
    $stars_color = $options['grc_stars_color'] ?? '#ffc107';
    $card_bg   = $options['grc_card_bg_color'] ?? '#ffffff';


    $reviews = grc_get_google_reviews();
    if (empty($reviews)) {
        return '<em>' . __('Aucun avis Google trouvé ou configuration incomplète.', 'google-reviews-carousel') . '</em>';
    }

    ob_start();

    echo '<style>
    .grc-reviews-carousel .grc-rating {
        color: ' . esc_attr($stars_color) . ' !important;
    }
    .grc-reviews-carousel .grc-review {
        background: ' . esc_attr($card_bg) . ' !important;
    }
    </style>';
?>
    <div class="grc-reviews-carousel"
        style="background: <?php echo esc_attr($bg); ?>; color: <?php echo esc_attr($color); ?>;">
        <?php foreach ($reviews as $review): ?>
            <div class="grc-review" style="font-size: <?php echo $txt_size; ?>px;">
                <?php if ($show_photo && !empty($review['profile_photo_url'])): ?>
                    <img src="<?php echo esc_url($review['profile_photo_url']); ?>" alt="<?php esc_attr_e('Photo Auteur', 'google-reviews-carousel'); ?>" class="grc-photo" style="float:left;margin:0 12px 8px 0;">
                <?php endif; ?>
                <div class="grc-author"><?php echo esc_html($review['author_name']); ?></div>

                <div class="grc-rating" style="<?php echo !$show_stars ? 'visibility:hidden;min-height:24px;' : ''; ?>" aria-label="<?php if($show_stars){printf(esc_attr__('Note : %d sur 5', 'google-reviews-carousel'), $review['rating']);} ?>">
                    <?php if ($show_stars): ?>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php echo ($i <= $review['rating']) ? '★' : '☆'; ?>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>

                <?php
                // Gestion du texte + "lire la suite"
                $review_url = !empty($review['author_url']) ? esc_url($review['author_url']) : '#';
                $review_text = $review['text'];
                $is_truncated = false;
                if ($char_limit > 0 && mb_strlen($review_text) > $char_limit) {
                    $review_text = mb_substr($review_text, 0, $char_limit) . '…';
                    $is_truncated = true;
                }
                ?>
                <div class="grc-text" style="margin:12px 0;">
                    <?php echo esc_html($review_text); ?>
                    <?php if ($is_truncated): ?>
                        <a href="<?php echo $review_url; ?>" target="_blank" rel="noopener" class="grc-read-more"><?php esc_html_e('Lire la suite', 'google-reviews-carousel'); ?></a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($review['time'])): ?>
                    <div class="grc-date"><small><?php echo date_i18n('d/m/Y', $review['time']); ?></small></div>
                <?php endif; ?>
                <div style="clear:both"></div>
            </div>

        <?php endforeach; ?>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.grc-reviews-carousel').slick({
                slidesToShow: <?php echo $show_count; ?>,
                slidesToScroll: 1,
                autoplay: true,
                autoplaySpeed: <?php echo $duration; ?>,
                dots: true,
                arrows: true,
                adaptiveHeight: <?php echo $adaptive ? 'true' : 'false'; ?>
            });
            <?php if (!$adaptive): ?>
                // Ajuste la hauteur de chaque slide à la plus grande (hauteur uniforme)
                setTimeout(function() {
                    var maxHeight = 0;
                    $('.grc-reviews-carousel .grc-review').each(function() {
                        var h = $(this).outerHeight();
                        if (h > maxHeight) maxHeight = h;
                    });
                    $('.grc-reviews-carousel .grc-review').css('min-height', maxHeight + 'px');
                }, 500); // petite attente pour s'assurer que Slick est prêt
            <?php endif; ?>
        });
    </script>
<?php
    return ob_get_clean();
}

// Fonction pour récupérer les avis Google Places avec cache
function grc_get_google_reviews()
{
    $options = get_option('grc_options');
    if (empty($options['grc_place_id']) || empty($options['grc_api_key'])) {
        return [];
    }
    $place_id = $options['grc_place_id'];
    $api_key = $options['grc_api_key'];

    // Création d'une clé unique pour le cache (par Place ID pour éviter les collisions)
    $transient_key = 'grc_reviews_cache_' . md5($place_id);

    // Vérifie si on a déjà un cache
    $cached = get_transient($transient_key);
    if (false !== $cached) {
        // Retourne les avis du cache, si dispo et non expiré
        return $cached;
    }

    // Si pas de cache : appel API Google Places Details
    $url = "https://maps.googleapis.com/maps/api/place/details/json?place_id={$place_id}&fields=reviews,rating,user_ratings_total,name&key={$api_key}";
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        // Retourne un tableau vide en cas d'échec
        return [];
    }

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    if (isset($json['result']['reviews'])) {
        // On stocke les avis dans le cache pour 1 heure (3600s)
        set_transient($transient_key, $json['result']['reviews'], 3600);
        return $json['result']['reviews'];
    }

    return [];
}

// ==== 7. Nettoyage des options à la désinstallation ====
register_uninstall_hook(__FILE__, 'grc_plugin_uninstall');
function grc_plugin_uninstall()
{
    delete_option('grc_options');
    global $wpdb;
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_grc_reviews_cache_%'");
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_grc_reviews_cache_%'");
}

?>