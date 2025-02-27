<?php
/**
 * Plugin Name: External Link Checker
 * Plugin URI: https://github.com/effi10/external-links-checker
 * Description: Un plugin pour compter les liens sortants dans les posts et les afficher dans une colonne personnalisée, avec la possibilité de filtrer les liens sortants ayant une certaine classe CSS (permet de ne pas comptabiliser les liens obfusqués).
 * Version: 1.2.0
 * Author: Cédric GIRARD - effi10
 * Author URI: https://www.effi10.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: external-links-checker
 */

/**
 * Classe principale du plugin External Link Checker.
 */
class ExternalLinkChecker {
    /**
     * Constructeur : Initialise les hooks nécessaires.
     */
    public function __construct() {
        // Hook pour analyser les liens lors de la sauvegarde d'un article
        add_action('save_post', array($this, 'analyze_links_on_save'), 10, 3);
        // Hook pour ajouter la page de configuration dans "Outils"
        add_action('admin_menu', array($this, 'add_admin_menu'));
        // Hooks pour ajouter une colonne dans la liste des articles
        add_filter('manage_posts_columns', array($this, 'add_external_links_column'));
        add_action('manage_posts_custom_column', array($this, 'fill_external_links_column'), 10, 2);
        // Hook pour rendre la colonne triable
        add_filter('manage_edit-post_sortable_columns', array($this, 'make_external_links_column_sortable'));
        // Hook pour le tri personnalisé
        add_action('pre_get_posts', array($this, 'external_links_orderby'));
        // Hook pour ajouter un widget au dashboard
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        // Hook pour ajouter les styles et scripts nécessaires
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        // Hook pour traiter l'export CSV
        add_action('admin_init', array($this, 'handle_csv_export'));
    }

    /**
     * Enregistre les styles et scripts pour l'admin.
     */
    public function enqueue_admin_scripts($hook) {

        if ($hook !== 'tools_page_external-link-checker') {
            return;
        }

		// Enregistrer le script AJAX pour l'analyse
		// wp_enqueue_script('elc-ajax-analysis', plugin_dir_url(__FILE__) . 'js/ajax-analysis.js', array('jquery'), '1.0.0', true);

		// Localiser le script pour passer l'URL AJAX
		// wp_localize_script('elc-ajax-analysis', 'ajaxurl', admin_url('admin-ajax.php'));
		
        // Style pour les onglets
        // wp_enqueue_style('elc-admin-style', plugin_dir_url(__FILE__) . 'css/admin-style.css', array(), '1.0.0');
        
        // Script pour les onglets et le tableau
        // wp_enqueue_script('elc-admin-script', plugin_dir_url(__FILE__) . 'js/admin-script.js', array('jquery'), '1.0.0', true);
        
        // Styles WordPress pour les tableaux
        wp_enqueue_style('list-tables');
    }

	/**
	 * Récupère le contenu HTML rendu d'un article.
	 *
	 * @param int $post_id ID de l'article.
	 * @return string|false Contenu HTML ou false en cas d'erreur.
	 */
	private function get_rendered_content($post_id) {
		$post_url = get_permalink($post_id);
		$response = wp_remote_get($post_url);

		if (is_wp_error($response)) {
			return false;
		}

		$body = wp_remote_retrieve_body($response);

		// Utiliser DOMDocument pour extraire le contenu de l'article
		$dom = new DOMDocument();
		@$dom->loadHTML($body);
		$xpath = new DOMXPath($dom);

		// Remplacez '.entry-content' par la classe ou l'ID utilisée par votre thème pour le contenu principal
		$content_node = $xpath->query("//*[contains(@class, 'entry-content')]")->item(0);

		if ($content_node) {
			return $dom->saveHTML($content_node);
		}

		return false;
	}



    /**
     * Analyse les liens dans le contenu d'un article lors de sa sauvegarde.
     *
     * @param int     $post_id ID de l'article.
     * @param WP_Post $post    Objet de l'article.
     * @param bool    $update  Indique si c'est une mise à jour ou une création.
     */
	public function analyze_links_on_save($post_id, $post, $update) {
		// Ne traiter que les articles (post_type = 'post')
		if ($post->post_type !== 'post' || wp_is_post_revision($post_id)) {
			return;
		}

		$rendered_content = $this->get_rendered_content($post_id);
		if (!$rendered_content) {
			return;
		}

		$link_counts = $this->count_links($rendered_content);

		// Mise à jour des champs personnalisés
		update_post_meta($post_id, 'total_links', $link_counts['total_links']);
		update_post_meta($post_id, 'external_links', $link_counts['external_links']);
	}

    /**
     * Compte les liens totaux et sortants dans le contenu d'un article.
     *
     * @param string $content Contenu de l'article.
     * @return array Tableau avec 'total_links' et 'external_links'.
     */
    private function count_links($content) {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $content); // Ajout encoding pour éviter warning
        $links = $dom->getElementsByTagName('a');
        $total_links = 0;
        $external_links = 0;
        $excluded_class = get_option('elc_excluded_class', ''); // Classe à exclure

        foreach ($links as $link) {
            if ($link->hasAttribute('href')) {
                $href = $link->getAttribute('href');
                $class = $link->getAttribute('class');

                // Exclure les liens avec la classe spécifiée
                if (!empty($excluded_class) && strpos($class, $excluded_class) !== false) {
                    continue;
                }

                $total_links++;
                if ($this->is_external_link($href)) {
                    $external_links++;
                }
            }
        }

        return array('total_links' => $total_links, 'external_links' => $external_links);
    }

    /**
     * Vérifie si un lien est sortant (domaine différent du site).
     *
     * @param string $url URL du lien.
     * @return bool True si le lien est sortant, false sinon.
     */
    private function is_external_link($url) {
        $site_url = parse_url(get_site_url(), PHP_URL_HOST);
        $link_url = parse_url($url, PHP_URL_HOST);
        return $link_url && $link_url !== $site_url;
    }

    /**
     * Ajoute la page de configuration dans l'onglet "Outils".
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'External Link Checker',
            'External Link Checker',
            'manage_options',
            'external-link-checker',
            array($this, 'settings_page')
        );
    }

    /**
     * Affiche la page de configuration avec formulaire, tableau et graphique.
     */
    public function settings_page() {
        // Sauvegarde de la classe à exclure
        if (isset($_POST['elc_excluded_class']) && check_admin_referer('elc_settings')) {
            update_option('elc_excluded_class', sanitize_text_field($_POST['elc_excluded_class']));
        }

        // Analyse des articles existants
        if (isset($_POST['analyze_existing_posts']) && check_admin_referer('elc_settings')) {
            $this->analyze_all_posts();
        }

        // Récupérer l'onglet actif
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'preferences';

        $stats = $this->get_statistics();
        ?>
        <div class="wrap">
            <h1>External Link Checker</h1>

            <!-- Navigation des onglets -->
            <h2 class="nav-tab-wrapper">
                <a href="?page=external-link-checker&tab=preferences" class="nav-tab <?php echo $active_tab == 'preferences' ? 'nav-tab-active' : ''; ?>">Préférences</a>
                <a href="?page=external-link-checker&tab=export" class="nav-tab <?php echo $active_tab == 'export' ? 'nav-tab-active' : ''; ?>">Exportation</a>
            </h2>

            <?php if ($active_tab == 'preferences') : ?>
            <!-- Onglet des préférences -->
            <div id="preferences-tab" class="tab-content">
                <form method="post" action="">
                    <?php wp_nonce_field('elc_settings'); ?>
                    <p>
                        <label for="elc_excluded_class">Classe à exclure (liens obfusqués) :</label><br>
                        <input type="text" name="elc_excluded_class" id="elc_excluded_class" value="<?php echo esc_attr(get_option('elc_excluded_class', '')); ?>" />
                    </p>
                    <p>
                        <input type="submit" name="submit" class="button button-primary" value="Enregistrer" />
                        <input type="submit" id="analyze-existing-posts" name="analyze_existing_posts" class="button" value="Analyser les articles existants" />
                    </p>
                </form>

                <!-- Tableau des statistiques -->
				<h2>Statistiques par catégorie</h2>
				<table class="widefat">
					<thead>
						<tr>
							<th>Catégorie</th>
							<th>Nombre d'articles</th>
							<th>Avec liens sortants</th>
							<th>Pourcentage</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($stats['categories'] as $cat_name => $data): ?>
							<?php
							// Obtenez l'ID de la catégorie à partir du nom
							$category = get_term_by('name', $cat_name, 'category');
							$category_id = $category ? $category->term_id : 0;
							$category_link = admin_url('edit.php?s&post_status=all&post_type=post&cat=' . $category_id . '&filter_action=Filtrer&paged=1');
							?>
							<tr>
								<td><a href="<?php echo esc_url($category_link); ?>"><?php echo esc_html($cat_name); ?></a></td>
								<td><?php echo $data['posts']; ?></td>
								<td><?php echo $data['with_external']; ?></td>
								<td><?php echo round($data['percentage'], 2); ?>%</td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<th>Totaux</th>
							<th><?php echo $stats['total']['posts']; ?></th>
							<th><?php echo $stats['total']['with_external']; ?></th>
							<th><?php echo round($stats['total']['percentage'], 2); ?>%</th>
						</tr>
					</tbody>
				</table>



                <!-- Graphique -->
                <h2>Graphique des pourcentages par catégorie</h2>
                <canvas id="elcChart" width="400" height="200"></canvas>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script>
                    const ctx = document.getElementById('elcChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: [<?php echo "'" . implode("','", array_keys($stats['categories'])) . "'"; ?>],
                            datasets: [{
                                label: 'Avec liens sortants',
                                data: [<?php echo implode(',', array_column($stats['categories'], 'with_external')); ?>],
                                backgroundColor: 'red'
                            }, {
                                label: 'Sans liens sortants',
                                data: [<?php echo implode(',', array_map(function($data) { return $data['posts'] - $data['with_external']; }, $stats['categories'])); ?>],
                                backgroundColor: 'green'
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            scales: { x: { stacked: true }, y: { stacked: true } }
                        }
                    });
                </script>
            </div>
            <?php endif; ?>

            <?php if ($active_tab == 'export') : ?>
            <!-- Onglet d'exportation -->
            <div id="export-tab" class="tab-content">
                <h2>Liste des articles avec liens sortants</h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field('elc_csv_export'); ?>
                    <p>
                        <input type="submit" name="elc_export_csv" class="button button-primary" value="Exporter au format CSV" />
                    </p>
                </form>

                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Post ID</th>
                            <th>Titre</th>
                            <th>URL de l'article</th>
                            <th>Nombre de liens sortants</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Récupérer les articles avec des liens sortants
                        $posts_with_links = $this->get_posts_with_external_links();
                        
                        foreach ($posts_with_links as $post) :
                            $post_url = get_permalink($post->ID);
                            $external_links = get_post_meta($post->ID, 'external_links', true);
                        ?>
                            <tr>
                                <td><?php echo $post->ID; ?></td>
                                <td><?php echo esc_html($post->post_title); ?></td>
                                <td><a href="<?php echo esc_url($post_url); ?>" target="_blank"><?php echo esc_url($post_url); ?></a></td>
                                <td><?php echo intval($external_links); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Récupère les articles qui ont des liens sortants.
     *
     * @return array Liste des articles avec des liens sortants.
     */
    private function get_posts_with_external_links() {
        global $wpdb;
        
        $query = "
            SELECT p.*, pm.meta_value AS external_links_count
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'external_links'
            AND pm.meta_value > 0
            ORDER BY pm.meta_value DESC
        ";
        
        return $wpdb->get_results($query);
    }

    /**
     * Gère l'exportation CSV des articles avec liens sortants.
     */
    public function handle_csv_export() {
        if (isset($_POST['elc_export_csv']) && check_admin_referer('elc_csv_export')) {
            $posts = $this->get_posts_with_external_links();
            
            // En-têtes pour le téléchargement
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=external-links-' . date('Y-m-d') . '.csv');
            
            // Ouvrir le flux de sortie
            $output = fopen('php://output', 'w');
            
            // BOM UTF-8 pour Excel
            fputs($output, "\xEF\xBB\xBF");
            
            // En-têtes du CSV
            fputcsv($output, array('Post ID', 'Titre', 'URL de l\'article', 'Nombre de liens sortants'), ';');
            
            // Lignes de données
            foreach ($posts as $post) {
                $post_url = get_permalink($post->ID);
                $external_links = get_post_meta($post->ID, 'external_links', true);
                
                fputcsv($output, array(
                    $post->ID,
                    $post->post_title,
                    $post_url,
                    $external_links
                ), ';');
            }
            
            fclose($output);
            exit;
        }
    }

	/**
	 * Analyse tous les articles existants pour mettre à jour leurs champs personnalisés.
	 */
	private function analyze_all_posts() {
		$posts = get_posts(array('post_type' => 'post', 'numberposts' => -1));
		foreach ($posts as $post) {
			$rendered_content = $this->get_rendered_content($post->ID);
			if (!$rendered_content) {
				continue;
			}

			$link_counts = $this->count_links($rendered_content);
			update_post_meta($post->ID, 'total_links', $link_counts['total_links']);
			update_post_meta($post->ID, 'external_links', $link_counts['external_links']);
		}
	}


    /**
     * Récupère les statistiques par catégorie et pour l'ensemble du site.
     *
     * @return array Statistiques avec détails par catégorie et totaux.
     */
    private function get_statistics() {
        $categories = get_categories();
        $stats = array('categories' => array());
        $total_posts = 0;
        $total_with_external = 0;

        foreach ($categories as $category) {
            $posts = get_posts(array('category' => $category->term_id, 'numberposts' => -1));
            $count_posts = count($posts);
            $count_with_external = 0;

            foreach ($posts as $post) {
                $external_links = (int) get_post_meta($post->ID, 'external_links', true);
                if ($external_links > 0) {
                    $count_with_external++;
                }
            }

            $percentage = $count_posts > 0 ? ($count_with_external / $count_posts) * 100 : 0;
            $stats['categories'][$category->name] = array(
                'posts' => $count_posts,
                'with_external' => $count_with_external,
                'percentage' => $percentage
            );
            $total_posts += $count_posts;
            $total_with_external += $count_with_external;
        }

        $total_percentage = $total_posts > 0 ? ($total_with_external / $total_posts) * 100 : 0;
        $stats['total'] = array(
            'posts' => $total_posts,
            'with_external' => $total_with_external,
            'percentage' => $total_percentage
        );
        return $stats;
    }

    /**
     * Ajoute une colonne "Liens sortants" dans la liste des articles.
     *
     * @param array $columns Colonnes existantes.
     * @return array Colonnes mises à jour.
     */
    public function add_external_links_column($columns) {
        $columns['external_links'] = 'Liens sortants';
        return $columns;
    }

    /**
     * Remplit la colonne "Liens sortants" avec le nombre de liens sortants.
     *
     * @param string $column  Nom de la colonne.
     * @param int    $post_id ID de l'article.
     */
    public function fill_external_links_column($column, $post_id) {
        if ($column === 'external_links') {
            $external_links = (int) get_post_meta($post_id, 'external_links', true);
            if ($external_links > 0) {
                echo '<span style="color:red; font-weight:bold;">' . $external_links . '</span>';
            } else {
                echo '-';
            }
        }
    }

    /**
     * Rend la colonne "Liens sortants" triable dans la liste des articles.
     *
     * @param array $columns Colonnes triables existantes.
     * @return array Colonnes triables mises à jour.
     */
    public function make_external_links_column_sortable($columns) {
        $columns['external_links'] = 'external_links';
        return $columns;
    }

    /**
     * Gère le tri personnalisé de la colonne "Liens sortants".
     *
     * @param WP_Query $query L'objet de requête WordPress.
     */
    public function external_links_orderby($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');
        
        if ('external_links' === $orderby) {
            $query->set('meta_key', 'external_links');
            $query->set('orderby', 'meta_value_num');
        }
    }

    /**
     * Ajoute un widget au dashboard WordPress.
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'elc_dashboard_widget',
            'External Link Checker',
            array($this, 'dashboard_widget_content')
        );
    }

    /**
     * Affiche le contenu du widget sur le dashboard.
     */
    public function dashboard_widget_content() {
        $stats = $this->get_statistics();
        ?>
        <p><strong>Nombre total d'articles :</strong> <?php echo $stats['total']['posts']; ?></p>
        <p><strong>Articles avec liens sortants :</strong> <?php echo $stats['total']['with_external']; ?> (<?php echo round($stats['total']['percentage'], 2); ?>%)</p>
        <?php
    }

    /**
     * Méthode statique pour le hook d'activation
     *
     * @param string $content Contenu de l'article.
     * @return array Tableau avec 'total_links' et 'external_links'.
     */
    public static function static_count_links($content) {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $content);
        $links = $dom->getElementsByTagName('a');
        $total_links = 0;
        $external_links = 0;
        $excluded_class = get_option('elc_excluded_class', '');
        
        foreach ($links as $link) {
            if ($link->hasAttribute('href')) {
                $href = $link->getAttribute('href');
                $class = $link->getAttribute('class');
                
                if (!empty($excluded_class) && strpos($class, $excluded_class) !== false) {
                    continue;
                }
                
                $total_links++;
                $site_url = parse_url(get_site_url(), PHP_URL_HOST);
                $link_url = parse_url($href, PHP_URL_HOST);
                if ($link_url && $link_url !== $site_url) {
                    $external_links++;
                }
            }
        }
        
        return array('total_links' => $total_links, 'external_links' => $external_links);
    }
}

// Instancier la classe
$external_link_checker = new ExternalLinkChecker();

// Hook d'activation
register_activation_hook(__FILE__, 'elc_activation_hook');

function elc_activation_hook() {
    // Définir une valeur par défaut pour l'option elc_excluded_class
    if (!get_option('elc_excluded_class')) {
        update_option('elc_excluded_class', '');
    }

    // Analyser tous les articles existants pour initialiser les champs personnalisés
    $posts = get_posts(array('post_type' => 'post', 'numberposts' => -1));
    foreach ($posts as $post) {
        $content = $post->post_content;
        $link_counts = ExternalLinkChecker::static_count_links($content);
        update_post_meta($post->ID, 'total_links', $link_counts['total_links']);
        update_post_meta($post->ID, 'external_links', $link_counts['external_links']);
    }
}

/**
 * Créer les répertoires et fichiers nécessaires lors de l'activation
 */
function elc_create_plugin_files() {
    // Créer le répertoire CSS si nécessaire
    $css_dir = plugin_dir_path(__FILE__) . 'css';
    if (!file_exists($css_dir)) {
        mkdir($css_dir, 0755, true);
    }
    
    // Créer le fichier CSS
    $css_file = $css_dir . '/admin-style.css';
    if (!file_exists($css_file)) {
        file_put_contents($css_file, '
/* Styles pour les onglets */
.nav-tab-wrapper {
    margin-bottom: 20px;
}
.tab-content {
    margin-top: 20px;
}
');
    }
    
    // Créer le répertoire JS si nécessaire
    $js_dir = plugin_dir_path(__FILE__) . 'js';
    if (!file_exists($js_dir)) {
        mkdir($js_dir, 0755, true);
    }
    
    // Créer le fichier JS
    $js_file = $js_dir . '/admin-script.js';
    if (!file_exists($js_file)) {
        file_put_contents($js_file, '
jQuery(document).ready(function($) {
    // Code JavaScript pour le plugin
});
');
    }
}

// Ajouter la création des fichiers au hook d'activation
register_activation_hook(__FILE__, 'elc_create_plugin_files');
