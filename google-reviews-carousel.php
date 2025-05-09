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
// Charger le CSS/JS Slick et les styles de l'aperçu uniquement sur la page de réglages du plugin
add_action('admin_enqueue_scripts', function($hook) {
    if ( $hook === 'settings_page_google-reviews-carousel') { // Ce HOOK correspond à ta page d’options
        // Charger Slick Carousel et ton CSS preview
        wp_enqueue_style('slick-carousel', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css');
        wp_enqueue_script('slick-carousel', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', ['jquery'], '1.8.1', true);

        // CSS rapide pour l’aperçu admin
        wp_add_inline_style('slick-carousel', "
            #grc-admin-preview-wrap { border:1px solid #ddd; padding:20px 30px 24px 30px; background:#fafbfd; border-radius:9px; margin-bottom:35px; max-width:650px; }
            #grc-admin-carousel-preview { padding:14px 10px; border-radius:9px; min-height:120px; }
            .grc-review { border-radius:7px; margin: 0px 8px; padding:16px 19px; display:flex; flex-direction:column; align-items:left; }
            .grc-author { font-weight:bold; margin-bottom: 4px;}
            .grc-rating { font-size:21px; letter-spacing:1px; }
            .grc-text { font-size:inherit; margin:8px 0 ;}
            .grc-date { color:#999;font-size:12px; }
        ");
        // JS qui initialise le carousel preview
        wp_add_inline_script('slick-carousel', "
            jQuery(function( $ ) {
                $('#grc-admin-carousel-preview').not('.slick-initialized').slick({
                    slidesToShow: 1,
                    autoplay: true,
                    dots: true,
                    arrows: false
                });
            });
        ");
    }
});


// ==== 3. Init & sanitization des paramètres et champs admin ====

// SANITIZE : gère la sauvegarde sécurisée et logique des cases à cocher
function grc_options_sanitize($options)
{
    $options['grc_show_stars']       = !empty($options['grc_show_stars'])       ? 1 : 0;
    $options['grc_show_photo']       = !empty($options['grc_show_photo'])       ? 1 : 0;
    $options['grc_adaptive_height']  = !empty($options['grc_adaptive_height'])  ? 1 : 0;
    $options['grc_text_size']        = isset($options['grc_text_size']) ? max(12, min(32, intval($options['grc_text_size']))) : 16;
    $options['grc_text_limit']       = isset($options['grc_text_limit']) ? max(30, intval($options['grc_text_limit'])) : 200;
    $options['grc_card_bg_color'] = isset($options['grc_card_bg_color']) && preg_match('/^#[a-f0-9]{6}$/i', $options['grc_card_bg_color']) ? $options['grc_card_bg_color'] : '#ffffff';
    $options['grc_stars_color'] = isset($options['grc_stars_color']) && preg_match('/^#[a-f0-9]{6}$/i', $options['grc_stars_color']) ? $options['grc_stars_color'] : '#ffc107';
    $options['grc_min_rating'] = isset($options['grc_min_rating']) ? min(5, max(1, intval($options['grc_min_rating']))) : 1;
    $options['grc_card_border_color'] = isset($options['grc_card_border_color']) && preg_match('/^#[a-f0-9]{6}$/i', $options['grc_card_border_color']) ? $options['grc_card_border_color'] : '#e0e0e0';
    $options['grc_card_border_width'] = isset($options['grc_card_border_width']) ? max(0, min(10, intval($options['grc_card_border_width']))) : 1;
    $options['grc_card_border_style'] = isset($options['grc_card_border_style']) ? sanitize_text_field($options['grc_card_border_style']) : 'solid';
    $options['grc_card_shadow'] = isset($options['grc_card_shadow']) ? sanitize_text_field($options['grc_card_shadow']) : '0 2px 12px rgba(0,0,0,0.12)';
    if (isset($options['grc_card_shadow']) && $options['grc_card_shadow'] === 'custom' && !empty($options['grc_card_shadow_custom'])) {
        $options['grc_card_shadow'] = sanitize_text_field($options['grc_card_shadow_custom']);
        unset($options['grc_card_shadow_custom']);
    }
    $options['grc_dots_color'] = isset($options['grc_dots_color']) && preg_match('/^#[a-f0-9]{6}$/i', $options['grc_dots_color']) ? $options['grc_dots_color'] : '#000000';
    $options['grc_dots_size'] = isset($options['grc_dots_size']) ? max(5, min(20, intval($options['grc_dots_size']))) : 10;
    $options['grc_active_dots_color'] = isset($options['grc_active_dots_color']) && preg_match('/^#[a-f0-9]{6}$/i', $options['grc_active_dots_color']) ? $options['grc_active_dots_color'] : '#0073aa';
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

    // Couleur de fond du carousel
    add_settings_field(
        'grc_color_bg',
        __('Couleur de fond du carousel', 'google-reviews-carousel'),
        'grc_color_bg_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Couleur de fond des cartes
    add_settings_field(
        'grc_card_bg_color',
        __('Couleur de fond des cartes', 'google-reviews-carousel'),
        'grc_card_bg_color_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Couleur du texte
    add_settings_field(
        'grc_color_txt',
        __('Couleur du texte', 'google-reviews-carousel'),
        'grc_color_txt_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Couleur des points
    add_settings_field(
        'grc_dots_color',
        __('Couleur des points', 'google-reviews-carousel'),
        'grc_dots_color_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Couleur active des points
    add_settings_field(
        'grc_active_dots_color',
        __('Couleur active des points', 'google-reviews-carousel'),
        'grc_active_dots_color_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );

    // Taille des points
    add_settings_field(
        'grc_dots_size',
        __('Taille des points (px)', 'google-reviews-carousel'),
        'grc_dots_size_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Couleur des étoiles
    add_settings_field(
        'grc_stars_color',
        __('Couleur des étoiles', 'google-reviews-carousel'),
        'grc_stars_color_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Afficher les étoiles
    add_settings_field(
        'grc_show_stars',
        __('Afficher les étoiles', 'google-reviews-carousel'),
        'grc_show_stars_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Note minimale à afficher
    add_settings_field(
        'grc_min_rating',
        __('Note minimale à afficher', 'google-reviews-carousel'),
        'grc_min_rating_field_cb',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Afficher la photo de l'auteur
    add_settings_field(
        'grc_show_photo',
        __('Afficher la photo de l\'auteur', 'google-reviews-carousel'),
        'grc_show_photo_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Couleur du contour des cartes
    add_settings_field(
        'grc_card_border_color',
        __('Couleur du contour des cartes', 'google-reviews-carousel'),
        'grc_card_border_color_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Épaisseur du contour
    add_settings_field(
        'grc_card_border_width',
        __('Épaisseur du contour (px)', 'google-reviews-carousel'),
        'grc_card_border_width_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Style du contour
    add_settings_field(
        'grc_card_border_style',
        __('Style du contour', 'google-reviews-carousel'),
        'grc_card_border_style_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Ombre
    add_settings_field(
        'grc_card_shadow',
        __('Ombre de la carte (CSS box-shadow)', 'google-reviews-carousel'),
        'grc_card_shadow_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Hauteur adaptative du carousel
    add_settings_field(
        'grc_adaptive_height',
        __('Hauteur adaptative du carousel (si NON coché, les avis auront tous la même hauteur)', 'google-reviews-carousel'),
        'grc_adaptive_height_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Taille du texte des avis
    add_settings_field(
        'grc_text_size',
        __('Taille du texte des avis (px)', 'google-reviews-carousel'),
        'grc_text_size_render',
        'google-reviews-carousel',
        'grc_settings_section'
    );
    // Limiter le texte des avis à (X caractères)
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
function grc_min_rating_field_cb()
{
    $options = get_option('grc_options');
    $min_rating = isset($options['grc_min_rating']) ? intval($options['grc_min_rating']) : 1;
?>
    <select name="grc_options[grc_min_rating]">
        <?php for ($i = 1; $i <= 5; $i++): ?>
            <option value="<?php echo $i; ?>" <?php selected($min_rating, $i); ?>>
                <?php echo $i; ?> ★
            </option>
        <?php endfor; ?>
    </select>
    <p class="description"><?php _e('Seuls les avis ayant au moins cette note seront affichés dans le carrousel.'); ?></p>
<?php
}



// == CHAMPS DE CUSTOMISATION ==
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
function grc_card_border_color_render()
{
    $options = get_option('grc_options'); ?>
    <input type="color" name="grc_options[grc_card_border_color]" value="<?php echo esc_attr($options['grc_card_border_color'] ?? '#e0e0e0'); ?>" />
<?php }

function grc_card_border_width_render()
{
    $options = get_option('grc_options'); ?>
    <input type="number" name="grc_options[grc_card_border_width]" value="<?php echo esc_attr($options['grc_card_border_width'] ?? 1); ?>" min="0" max="10" /> px
<?php }

function grc_card_border_style_render()
{
    $options = get_option('grc_options');
    $current = $options['grc_card_border_style'] ?? 'solid';
?>
    <select name="grc_options[grc_card_border_style]">
        <option value="solid" <?php selected($current, 'solid'); ?>>Solid</option>
        <option value="dashed" <?php selected($current, 'dashed'); ?>>Dashed</option>
        <option value="dotted" <?php selected($current, 'dotted'); ?>>Dotted</option>
        <option value="double" <?php selected($current, 'double'); ?>>Double</option>
        <option value="none" <?php selected($current, 'none'); ?>>None</option>
    </select>
<?php }

function grc_card_shadow_render()
{
    $options = get_option('grc_options');
    $shadows = [
        'none'                        => __('Aucune', 'google-reviews-carousel'),
        '0 1px 4px rgba(0,0,0,0.07)'  => __('Douce', 'google-reviews-carousel'),
        '0 2px 12px rgba(0,0,0,0.12)' => __('Moyenne', 'google-reviews-carousel'),
        '0 4px 24px rgba(0,0,0,0.18)' => __('Forte', 'google-reviews-carousel'),
        'custom'                      => __('Personnalisée…', 'google-reviews-carousel'),
    ];
    $current  = $options['grc_card_shadow'] ?? '0 2px 12px rgba(0,0,0,0.12)';
    $custom   = (!isset($shadows[$current]) && $current !== 'none') ? $current : '';
?>
    <select id="grc_card_shadow_select" name="grc_options[grc_card_shadow]">
        <?php foreach ($shadows as $val => $label): ?>
            <option value="<?php echo esc_attr($val); ?>" <?php selected(($val == $current) || (($val == 'custom') && $custom)); ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <input type="text" id="grc_card_shadow_custom" name="grc_options[grc_card_shadow_custom]"
        value="<?php echo esc_attr($custom); ?>"
        placeholder="ex: 0 4px 24px rgba(0,0,0,0.18)"
        style="width:220px;display:<?php echo $custom ? 'inline-block' : 'none'; ?>" />
    <small id="grc_card_shadow_preview" style="margin-left:12px;vertical-align:middle;display:inline-block;padding:8px 24px;border-radius:8px;box-shadow:<?php echo esc_attr($current && $current !== 'custom' ? $current : $custom); ?>"></small>
    <script>
        (function() {
            var select = document.getElementById('grc_card_shadow_select');
            var custom = document.getElementById('grc_card_shadow_custom');
            var preview = document.getElementById('grc_card_shadow_preview');

            function update() {
                if (select.value === 'custom') {
                    custom.style.display = 'inline-block';
                    preview.style.boxShadow = custom.value;
                } else {
                    custom.style.display = 'none';
                    preview.style.boxShadow = select.value;
                }
            }
            select.addEventListener('change', update);
            custom.addEventListener('input', function() {
                preview.style.boxShadow = custom.value;
            });
            update();
        })();
    </script>
    <br>
    <small><?php _e('Prédéfini ou personnalisé. Aperçu direct à droite.', 'google-reviews-carousel'); ?></small>
<?php
}
function grc_dots_color_render()
{
    $options = get_option('grc_options');
?>
    <input type="color" name="grc_options[grc_dots_color]" value="<?php echo esc_attr($options['grc_dots_color'] ?? '#000000'); ?>" />
<?php
}

function grc_dots_size_render()
{
    $options = get_option('grc_options'); ?>
    <input type="number" name="grc_options[grc_dots_size]" value="<?php echo esc_attr($options['grc_dots_size'] ?? 10); ?>" min="5" max="20" style="width: 60px;" /> px
<?php
}
function grc_active_dots_color_render()
{
    $options = get_option('grc_options');
?>
    <input type="color" name="grc_options[grc_active_dots_color]" value="<?php echo esc_attr($options['grc_active_dots_color'] ?? '#0073aa'); ?>" />
<?php
}


// ==== 4. Génération de la page d’options ====
function grc_options_page() {
    // 1. Récupérer les options
    $options = get_option('grc_options');

    // 2. Données d’aperçu fictives (pour visualiser le rendu du carousel même sans avis réel)
    $dummy_reviews = [
        [
            'author_name' => 'Alice',
            'profile_photo_url' => '',
            'rating' => 5,
            'text' => __('Super expérience, je recommande vivement !', 'google-reviews-carousel'),
            'time' => time(),
        ],
        [
            'author_name' => 'Bob',
            'profile_photo_url' => '',
            'rating' => 4,
            'text' => __('Bon accueil, avis positif !', 'google-reviews-carousel'),
            'time' => time() - 86400,
        ],
        [
            'author_name' => 'Charlie',
            'profile_photo_url' => '',
            'rating' => 3,
            'text' => __('Service correct, un peu d\'attente.', 'google-reviews-carousel'),
            'time' => time() - 172800,
        ],
    ];

    // 3. Préparer les variables
    $txt_size      = intval($options['grc_text_size'] ?? 16);
    $show_stars    = !empty($options['grc_show_stars']);
    $show_photo    = !empty($options['grc_show_photo']);
    $bg            = $options['grc_color_bg'] ?? '#fff';
    $color         = $options['grc_color_txt'] ?? '#222';
    $stars_color   = $options['grc_stars_color'] ?? '#ffc107';
    $card_bg_color = $options['grc_card_bg_color'] ?? '#fff';
    $card_border   = ($options['grc_card_border_width'] ?? 1) . 'px '
                   . ($options['grc_card_border_style'] ?? 'solid') . ' '
                   . ($options['grc_card_border_color'] ?? '#e0e0e0');
    $card_shadow   = $options['grc_card_shadow'] ?? '0 2px 12px rgba(0,0,0,0.12)';
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Google Reviews Carousel', 'google-reviews-carousel'); ?></h1>

        <!-- Formulaire options WP -->
        <form method="post" action="options.php">
            <?php
                settings_fields('grc_settings');
                do_settings_sections('google-reviews-carousel');
                submit_button();
            ?>
        </form>

        <hr>
        <h2><?php esc_html_e('Aperçu du carousel', 'google-reviews-carousel'); ?></h2>
        <div id="grc-admin-preview-wrap">
            <div id="grc-admin-carousel-preview" class="grc-reviews-carousel" style="background: <?php echo esc_attr($bg); ?>;">
                <?php foreach ($dummy_reviews as $review): ?>
                    <div class="grc-review"
                        style="
                            font-size: <?php echo esc_attr($txt_size); ?>px;
                            background: <?php echo esc_attr($card_bg_color); ?>;
                            color: <?php echo esc_attr($color); ?>;
                            border: <?php echo esc_attr($card_border); ?>;
                            box-shadow: <?php echo esc_attr($card_shadow); ?>;
                            margin-bottom:16px;
                        ">
                        <div class="grc-author"><?php echo esc_html($review['author_name']); ?></div>
                        <?php if ($show_stars): ?>
                            <div class="grc-rating" style="color:<?php echo esc_attr($stars_color); ?>">
                                <?php for ($i = 1; $i <= 5; $i++) { echo ($i <= $review['rating']) ? '★' : '☆'; } ?>
                            </div>
                        <?php endif; ?>
                        <div class="grc-text"><?php echo esc_html($review['text']); ?></div>
                        <div class="grc-date"><small><?php echo date_i18n('d/m/Y', $review['time']); ?></small></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <small><?php esc_html_e('Aperçu instantané selon vos réglages actuels.', 'google-reviews-carousel'); ?></small>
    </div>
    <?php
}

add_action('admin_init', 'grc_handle_reset_options');

function grc_handle_reset_options()
{
    if (isset($_POST['grc_reset_options'])) {
        grc_reset_options();
        wp_redirect(admin_url('options-general.php?page=google-reviews-carousel&settings-updated=true'));
        exit;
    }
}
function grc_reset_options()
{
    $current_options = get_option('grc_options');

    // Valeurs par défaut
    $default_options = [
        'grc_reviews_count' => 3,
        'grc_duration' => 15,
        'grc_color_bg' => '#ffffff',
        'grc_card_bg_color' => '#ffffff',
        'grc_color_txt' => '#333333',
        'grc_stars_color' => '#ffc107',
        'grc_show_stars' => 1,
        'grc_min_rating' => 1,
        'grc_show_photo' => 1,
        'grc_card_border_color' => '#e0e0e0',
        'grc_card_border_width' => 1,
        'grc_card_border_style' => 'solid',
        'grc_card_shadow' => '0 2px 12px rgba(0,0,0,0.12)',
        'grc_adaptive_height' => 1,
        'grc_text_size' => 16,
        'grc_text_limit' => 200,
        'grc_dots_color' => '#000000',
        'grc_dots_size' => 10,
        'grc_active_dots_color' => '#0073aa',
    ];
    $new_options = array_merge(
        [
            'grc_place_id' => $current_options['grc_place_id'] ?? '',
            'grc_api_key' => $current_options['grc_api_key'] ?? ''
        ],
        $default_options
    );

    update_option('grc_options', $new_options);
    add_settings_error('grc_options', 'grc_options_reset', __('Options réinitialisées aux valeurs par défaut (hors ID de fiche et clé API).', 'google-reviews-carousel'), 'updated');
}


// ==== 5. Chargement CSS/JS uniquement sur le front ====
add_action('wp_enqueue_scripts', 'grc_maybe_enqueue_scripts');
function grc_maybe_enqueue_scripts()
{
    wp_enqueue_style('slick-carousel', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css');
    wp_enqueue_script('slick-carousel', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array('jquery'), '1.8.1', true);
    wp_add_inline_style('slick-carousel', '.grc-reviews-carousel { margin: 26px auto; } .grc-review { outline: none; border-radius: 8px; box-shadow:0 2px 12px #0001; margin: 8px; padding: 16px 24px;} .grc-rating{font-size:18px;color:#ffc107} .grc-author{font-weight:bold;} .grc-photo{border-radius:50%;width:40px;height:40px;object-fit:cover;} .grc-read-more{display:inline-block;margin-top:6px;font-size:90%;color:#1558fb;text-decoration:underline;}');
}


// ==== 6. Shortcode ====
add_shortcode('grc_google_reviews', 'grc_google_reviews_shortcode');
function grc_google_reviews_shortcode($atts)
{
    $options = get_option('grc_options');
    $show_count = min(intval($options['grc_reviews_count'] ?? 3), 5);
    $duration = intval($options['grc_duration'] ?? 15) * 1000; // temps d'appel de l'api

    // --- Récupération & filtrage des avis ---
    $reviews = grc_get_google_reviews();
    $min_rating = isset($options['grc_min_rating']) ? intval($options['grc_min_rating']) : 1;
    $reviews = array_filter($reviews, function ($review) use ($min_rating) {
        return isset($review['rating']) && $review['rating'] >= $min_rating;
    });

    // --- Affichage message si aucun avis filtré ---
    if (empty($reviews)) {
        return '<em>' . __('Aucun avis Google trouvé ou configuration incomplète.', 'google-reviews-carousel') . '</em>';
    }

    // --- Récupération des options ---
    $bg         = $options['grc_color_bg'] ?? '#fff';
    $color      = $options['grc_color_txt'] ?? '#222';
    $txt_size   = intval($options['grc_text_size'] ?? 16);
    $show_stars = !empty($options['grc_show_stars']);
    $show_photo = !empty($options['grc_show_photo']);
    $adaptive   = !empty($options['grc_adaptive_height']);
    $char_limit = intval($options['grc_text_limit'] ?? 200);
    $stars_color = $options['grc_stars_color'] ?? '#ffc107';
    $card_bg_color = $options['grc_card_bg_color'] ?? '#fff';
    $dots_color = esc_attr($options['grc_dots_color'] ?? '#000000');
    $dots_size = intval($options['grc_dots_size'] ?? 10);
    $active_dots_color = esc_attr($options['grc_active_dots_color'] ?? '#0073aa'); // Ajouter cette ligne

    ob_start();


    echo '<style>
        .grc-reviews-carousel .grc-rating {
            color: ' . esc_attr($stars_color) . ' !important;
        }
        .grc-reviews-carousel .grc-review {
            background: ' . esc_attr($card_bg_color) . ';
            border: ' . esc_attr($options['grc_card_border_width'] ?? 1) . 'px ' .
        esc_attr($options['grc_card_border_style'] ?? 'solid') . ' ' .
        esc_attr($options['grc_card_border_color'] ?? '#e0e0e0') . ';
            box-shadow: ' . esc_attr($options['grc_card_shadow'] ?? '0 2px 12px rgba(0,0,0,0.12)') . ';
        }
        .slick-dots,
        .slick-dots li {
            list-style: none !important;
        }
        .slick-dots {
            display: flex !important;
            justify-content: center;
            padding: 16px 0 0 0;
        }
        .slick-dots li {
            margin: 0 6px;
        }
        .slick-dots li button {
            font-size: 0;
            border: none;
            background: ' . $dots_color . ';
            border-radius: 50%;
            width: width: auto !important;
            height: ' . $dots_size . 'px; 
            cursor: pointer;
            transition: background 0.2s;
            outline: none;
        }
        .slick-dots li.slick-active button {
            background: ' . esc_attr($active_dots_color) . '; /* Couleur active */
            box-shadow: 0 0 0 2px ' . esc_attr($active_dots_color) . '22;
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

                <div class="grc-rating" style="<?php echo !$show_stars ? 'visibility:hidden;min-height:24px;' : ''; ?>" aria-label="<?php if ($show_stars) {
                                                                                                                                        printf(esc_attr__('Note : %d sur 5', 'google-reviews-carousel'), $review['rating']);
                                                                                                                                    } ?>">
                    <?php if ($show_stars): ?>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php echo ($i <= $review['rating']) ? '★' : '☆'; ?>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>

                <?php
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
                    <div class="grc-date"><small><?php echo date_i18n('d/m/Y',  $review['time']); ?></small></div>
                <?php endif; ?>
                <div style="clear:both"></div>
            </div>

        <?php endforeach; ?>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            function setMaxReviewHeight() {
                $('.grc-reviews-carousel .grc-review').css('min-height', '');
                var maxHeight = 0;
                $('.grc-reviews-carousel .grc-review').each(function() {
                    var h = $(this).outerHeight();
                    if (h > maxHeight) maxHeight = h;
                });
                $('.grc-reviews-carousel .grc-review').css('min-height', maxHeight + 'px');
            }

            $('.grc-reviews-carousel').slick({
                slidesToShow: <?php echo $show_count; ?>,
                slidesToScroll: 1,
                autoplay: true,
                autoplaySpeed: <?php echo $duration; ?>,
                dots: true,
                arrows: false,
                adaptiveHeight: <?php echo $adaptive ? 'true' : 'false'; ?>,
                responsive: [{
                        breakpoint: 1024,
                        settings: {
                            slidesToShow: 2
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 1
                        }
                    }
                ]
            });

            <?php if (!$adaptive): ?>
                setMaxReviewHeight();
                $(window).on('resize orientationchange', function() {
                    setTimeout(setMaxReviewHeight, 50);
                });
                $('.grc-reviews-carousel').on('setPosition', setMaxReviewHeight);
                $('.grc-reviews-carousel .grc-review img').on('load', setMaxReviewHeight);
                setTimeout(setMaxReviewHeight, 800);
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
        // stocke les avis dans le cache pour 1 heure (3600s)
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