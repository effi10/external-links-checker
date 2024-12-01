<?php
/**
 * Plugin Name: External Link Checker
 * Plugin URI: https://github.com/effi10/external-links-checker
 * Description: Un plugin pour compter les liens sortants dans les posts et les afficher dans une colonne personnalisée, avec la possibilité de filtrer les liens sortants ayant une certaine classe CSS (permet de ne pas comptabiliser les liens obfusqués).
 * Version: 1.1.0
 * Author: Cédric GIRARD - effi10
 * Author URI: https://www.effi10.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: external-links-checker
 * Domain Path: /languages
 */


// Calcul du nombre de liens sortants avec filtrage de notre classe d'exception (par ex. liens obfusqués)
function elc_count_external_links( $post_id, $content ) {
    // Récupérer l'exception de la classe CSS depuis les options du plugin
    $exception_class = get_option( 'elc_exception_class' );

    // Compter les liens sortants
    $links_count = 0;
    $dom = new DOMDocument();
    @$dom->loadHTML( $content );
    $links = $dom->getElementsByTagName( 'a' );
    foreach ( $links as $link ) {
        if ( ! empty( $exception_class ) && $link->getAttribute( 'class' ) === $exception_class ) {
            continue;
        }
        if ( parse_url( $link->getAttribute( 'href' ), PHP_URL_HOST ) !== $_SERVER['HTTP_HOST'] ) {
            $links_count++;
        }
    }

    // Stocker le nombre de liens sortants dans un custom field
    update_post_meta( $post_id, '_elc_external_links_count', $links_count );
}

// Mise à jour du nombre de liens sur les updates de posts
function elc_update_post( $post_id ) {
    $post = get_post( $post_id );
    elc_count_external_links( $post_id, $post->post_content );
}
add_action( 'save_post', 'elc_update_post' );

// Initialisation du plugin et précalcul des valeurs de chaque post
function elc_activation() {
    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    );
    $posts = get_posts( $args );
    foreach ( $posts as $post ) {
        elc_count_external_links( $post->ID, $post->post_content );
    }
}
register_activation_hook( __FILE__, 'elc_activation' );

// Ajout de la colonne à la liste des posts
function elc_add_custom_column( $columns ) {
    $columns['external_links'] = __( 'Liens externes', 'external-links-checker' );
    return $columns;
}
add_filter( 'manage_posts_columns', 'elc_add_custom_column' );


// Alimentation de la colonne par le nombre de liens sortants
function elc_custom_column_content( $column_name, $post_id ) {
    if ( $column_name === 'external_links' ) {
        $external_links_count = get_post_meta( $post_id, '_elc_external_links_count', true );
        $count = intval( $external_links_count );

        // Appliquer des styles CSS en ligne si le nombre de liens sortants est supérieur à zéro
        $style = '';
		$affichage = '';
        if ( $count > 0 ) {
            $style = ' style="color: red; font-weight: bold;"';
			$affichage = 'OUI (' . $count . ')';
        }
		else
		{
			$affichage = '-';
		}
        echo '<span' . $style . '>' . $affichage . '</span>';
    }
}
add_action( 'manage_posts_custom_column', 'elc_custom_column_content', 10, 2 );

// Colonne triable
function elc_sortable_columns( $columns ) {
    $columns['external_links'] = 'external_links';
    return $columns;
}
add_filter( 'manage_edit-post_sortable_columns', 'elc_sortable_columns' );

function elc_pre_get_posts( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    $orderby = $query->get( 'orderby' );
    if ( 'external_links' === $orderby ) {
        $query->set( 'meta_key', '_elc_external_links_count' );
        $query->set( 'orderby', 'meta_value_num' );
    }
}
add_action( 'pre_get_posts', 'elc_pre_get_posts' );

// Création de la page spécifique du plugin dans le back-office WP
function elc_add_settings_page() {
    add_options_page(
        __( 'Liens externes', 'external-links-checker' ),
        __( 'Liens externes', 'external-links-checker' ),
        'manage_options',
        'external-links-checker',
        'elc_settings_page_content'
    );
}
add_action( 'admin_menu', 'elc_add_settings_page' );

// Création du contenu des pages de paramètres / export
function elc_settings_page_content() {
    // Vérifier les permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Traitement de la soumission du formulaire de l'onglet Paramètres
    if ( isset( $_POST['elc_settings_submit'] ) ) {
        check_admin_referer( 'elc_settings' );
        update_option( 'elc_exception_class', sanitize_text_field( $_POST['elc_exception_class'] ) );
		elc_activation();  // Mise à jour du décompte
    }

    // Afficher les onglets
    $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'settings';
    ?>
    <div class="wrap">
        <h1><?php _e( 'Liens externes', 'external-links-checker' ); ?></h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=external-links-checker&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Paramètres', 'external-links-checker' ); ?></a>
            <a href="?page=external-links-checker&tab=export" class="nav-tab <?php echo $active_tab === 'export' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Export', 'external-links-checker' ); ?></a>
        </h2>

        <?php if ( $active_tab === 'settings' ) : ?>
            <form method="post">
                <?php wp_nonce_field( 'elc_settings' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="elc_exception_class"><?php _e( 'Exception (classe CSS de liens)', 'external-links-checker' ); ?></label></th>
                        <td><input type="text" id="elc_exception_class" name="elc_exception_class" value="<?php echo esc_attr( get_option( 'elc_exception_class' ) ); ?>" /></td>
                    </tr>
                </table>
                <input type="submit" name="elc_settings_submit" class="button-primary" value="<?php _e( 'Enregistrer les modifications', 'external-links-checker' ); ?>" />
            </form>
        <?php endif; ?>

        <?php if ( $active_tab === 'export' ) : ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e( 'Post ID', 'external-links-checker' ); ?></th>
                        <th><?php _e( 'Titre', 'external-links-checker' ); ?></th>
                        <th><?php _e( 'URL', 'external-links-checker' ); ?></th>
                        <th><?php _e( 'Liens sortants', 'external-links-checker' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $args = array(
                        'post_type'      => 'post',
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'meta_query'     => array(
                            array(
                                'key'     => '_elc_external_links_count',
                                'value'   => 1,
                                'compare' => '>=',
                                'type'    => 'numeric',
                            ),
                        ),
                    );
                    $posts = get_posts( $args );
                    foreach ( $posts as $post ) {
                        $post_id = $post->ID;
                        $title = get_the_title( $post_id );
                        $url = get_permalink( $post_id );
                        $external_links_count = get_post_meta( $post_id, '_elc_external_links_count', true );
                        ?>
                        <tr>
                            <td><?php echo $post_id; ?></td>
                            <td><?php echo esc_html( $title ); ?></td>
                            <td><?php echo esc_url( $url ); ?></td>
                            <td><?php echo intval( $external_links_count ); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
            <a href="<?php echo admin_url( 'admin-post.php?action=elc_export_csv' ); ?>" class="button-primary" style="margin-top:20px;"><?php _e( 'Exporter la liste', 'external-links-checker' ); ?></a>
        <?php endif; ?>
    </div>
    <?php
}

// Fonction d'export de la liste des posts avec des liens sortants
function elc_export_csv() {
    // Vérifier les permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Vous n\'êtes pas autorisé à effectuer cette action.', 'external-links-checker' ) );
    }

    // Générer le fichier CSV
    $filename = 'external_links_export_' . date( 'YmdHis' ) . '.csv';
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $filename );

    $output = fopen( 'php://output', 'w' );

    // En-tête du fichier CSV
    fputcsv( $output, array( 'Post ID', 'Titre', 'URL', 'Liens sortants' ) );

    // Contenu du fichier CSV
    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => '_elc_external_links_count',
                'value'   => 1,
                'compare' => '>=',
                'type'    => 'numeric',
            ),
        ),
    );
    $posts = get_posts( $args );
    foreach ( $posts as $post ) {
        $post_id = $post->ID;
        $title = get_the_title( $post_id );
        $url = get_permalink( $post_id );
        $external_links_count = get_post_meta( $post_id, '_elc_external_links_count', true );

        fputcsv( $output, array( $post_id, $title, $url, $external_links_count ) );
    }

    fclose( $output );
    exit;
}
add_action( 'admin_post_elc_export_csv', 'elc_export_csv' );

function elc_register_dashboard_widget() {
    wp_add_dashboard_widget(
        'elc_dashboard_widget', // Widget ID
        __( 'Ratio de posts avec des liens externes', 'external-links-counter' ), // Titre du widget
        'elc_display_dashboard_widget' // Fonction de rendu
    );
}
add_action( 'wp_dashboard_setup', 'elc_register_dashboard_widget' );

function elc_display_dashboard_widget() {
    // Récupérer tous les posts publiés
    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    );
    $all_posts = get_posts( $args );
    $total_posts = count( $all_posts );

    // Récupérer les posts ayant au moins un lien externe
    $args_with_links = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => '_elc_external_links_count',
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC',
            ),
        ),
    );
    $posts_with_links = get_posts( $args_with_links );
    $total_with_links = count( $posts_with_links );

    // Calculer le ratio
    $ratio = $total_posts > 0 ? ( $total_with_links / $total_posts ) * 100 : 0;

    // Afficher le ratio
    echo '<p>';
    echo sprintf(
        __( 'Il y a <strong>%d</strong> posts au total, dont <strong>%d</strong> (%0.2f%%) ont au moins un lien externe.', 'external-links-counter' ),
        $total_posts,
        $total_with_links,
        $ratio
    );
    echo '</p>';
}


