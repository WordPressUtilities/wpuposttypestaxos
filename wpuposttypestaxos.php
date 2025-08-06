<?php

/*
Plugin Name: WPU Post types & taxonomies
Plugin URI: https://github.com/WordPressUtilities/wpuposttypestaxos
Update URI: https://github.com/WordPressUtilities/wpuposttypestaxos
Description: Load custom post types & taxonomies
Version: 0.26.2
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpuposttypestaxos
Domain Path: /lang
Requires at least: 6.2
Requires PHP: 8.0
Network: true
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

defined('ABSPATH') or die(':(');

class wputh_add_post_types_taxonomies {
    private $plugin_version = '0.26.2';
    private $plugin_description;
    public $basetoolbox;

    public $post_types;
    public $taxonomies;

    /* Post types */
    private $pt__values_array = array(
        'supports',
        'taxonomies'
    );
    private $pt__values_var = array(
        'rewrite'
    );
    private $pt__values_text = array(
        'show_in_menu',
        'menu_position',
        'menu_icon'
    );
    private $pt__values_bool = array(
        'can_export',
        'exclude_from_search',
        'has_archive',
        'hierarchical',
        'public',
        'publicly_queryable',
        'show_in_nav_menus',
        'query_var',
        'show_ui',
        'with_front'
    );

    /* Taxos */
    private $tax__values_bool = array(
        'meta_box_cb',
        'show_admin_column',
        'hierarchical',
        'public',
        'show_in_rest',
        'show_ui',
        'publicly_queryable'
    );
    private $tax__values_var = array(
        'rewrite'
    );

    private $non_consonants = array(
        'a',
        'e',
        'è',
        'È',
        'é',
        'É',
        'i',
        'o',
        'u',
        'y',
        'h'
    );
    private $post_types_without_adjacent = array();

    public function __construct() {
        add_action('plugins_loaded', array(&$this,
            'load_dependencies'
        ));
        add_action('after_setup_theme', array(&$this,
            'load_plugin_textdomain'
        ));
        add_action('init', array(&$this,
            'add_post_types'
        ), 5);
        add_action('post_updated_messages', array(&$this,
            'post_updated_messages'
        ));
        add_action('init', array(&$this,
            'add_taxonomies'
        ), 5);
        add_action('pre_get_posts', array(&$this,
            'disable_taxonomy_front'
        ));
        add_action('wp', array(&$this,
            'remove_rss_feed_link'
        ));
        add_action('template_redirect', array(&$this,
            'remove_rss_disable_feed'
        ));
        add_action('save_post', array(&$this,
            'clear_cache_posttype'
        ), 10, 1);
        add_action('create_term', array(&$this,
            'clear_cache_taxonomy'
        ), 10, 3);
        add_action('edit_term', array(&$this,
            'clear_cache_taxonomy'
        ), 10, 3);
        add_action('delete_term', array(&$this,
            'clear_cache_taxonomy'
        ), 10, 3);
        add_action('template_redirect', array(&$this,
            'template_redirect'
        ), 10, 3);
        add_action('wp_link_query', array(&$this,
            'wp_link_query'
        ), 10, 2);
        add_action('restrict_manage_posts', array(&$this,
            'restrict_manage_posts'
        ), 10);
        add_filter('wp_sitemaps_post_types', array(&$this,
            'restrict_sitemaps_post_types'
        ));

        if (is_admin()) {
            add_action('admin_menu', array(&$this,
                'edit_admin_menu'
            ), 90);
            add_action('add_meta_boxes', array(&$this,
                'load_gallery_metabox'
            ));
            add_action('dashboard_glance_items', array(&$this,
                'add_dashboard_glance_items'
            ));
            add_action('admin_init', array(&$this,
                'add_editor_styles'
            ));
            add_action('admin_enqueue_scripts', array(&$this,
                'admin_style'
            ));
            add_action('admin_footer', function () {
                $hidden_boxes = array();
                foreach ($this->taxonomies as $slug => $taxo) {
                    if (isset($taxo['wputh__hide_postbox']) && $taxo['wputh__hide_postbox']) {
                        $hidden_boxes[] = '#poststuff #' . $slug . 'div.postbox';
                    }
                }
                if (!empty($hidden_boxes)) {
                    echo '<style>' . implode(',', $hidden_boxes) . '{display:none}</style>';
                }

            });
        }
    }

    public function load_dependencies() {
        require_once __DIR__ . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
        new \wpuposttypestaxos\WPUBaseUpdate(
            'WordPressUtilities',
            'wpuposttypestaxos',
            $this->plugin_version);

        require_once __DIR__ . '/inc/WPUBaseToolbox/WPUBaseToolbox.php';
        $this->basetoolbox = new \wpuposttypestaxos\WPUBaseToolbox(array(
            'need_form_js' => false
        ));
    }

    public function admin_style() {
        wp_register_style('wpuposttypestaxos_style', plugins_url('assets/style.css', __FILE__), false, $this->plugin_version);
        wp_enqueue_style('wpuposttypestaxos_style');
    }

    public function load_plugin_textdomain() {
        $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
        if (strpos(__DIR__, 'mu-plugins') !== false) {
            load_muplugin_textdomain('wpuposttypestaxos', $lang_dir);
        } else {
            load_plugin_textdomain('wpuposttypestaxos', false, $lang_dir);
        }
        $this->plugin_description = __('Load custom post types & taxonomies', 'wpuposttypestaxos');
    }

    /* ----------------------------------------------------------
      Add Post types
    ---------------------------------------------------------- */

    public function add_post_types() {
        $this->post_types = apply_filters('wputh_get_posttypes', array());
        foreach ($this->post_types as $slug => $post_type) {

            $args = array(
                'menu_icon' => 'dashicons-portfolio',
                'exclude_from_search' => false,
                'hierarchical' => false,
                'has_archive' => true,
                'public' => true,
                'publicly_queryable' => true,
                'rewrite' => true,
                'can_export' => true,
                'show_ui' => true,
                'with_front' => true,
                'taxonomies' => array(),
                'supports' => array(
                    'title',
                    'editor',
                    'thumbnail'
                )
            );

            // Hide singular in front
            if (isset($post_type['wputh__hide_singular']) && $post_type['wputh__hide_singular']) {
                $args['exclude_from_search'] = true;
            }

            // Hide only in front
            if (isset($post_type['wputh__hide_front']) && is_bool($post_type['wputh__hide_front']) && $post_type['wputh__hide_front'] !== false) {
                $args['has_archive'] = false;
                $args['public'] = false;
                $args['publicly_queryable'] = false;
                $args['query_var'] = false;
                $args['rewrite'] = false;
                $args['exclude_from_search'] = true;
            }

            if (!isset($post_type['keep_adjacents_links_head'])) {
                $this->post_types_without_adjacent[] = $slug;
            }
            unset($post_type['keep_adjacents_links_head']);

            // Default label: slug
            if (!isset($post_type['name'])) {
                $post_type['name'] = $this->ucfirst($slug);
            }
            $args['name'] = $post_type['name'];

            // Plural
            if (!isset($post_type['plural'])) {
                $post_type['plural'] = $post_type['name'];
            }
            $args['plural'] = $post_type['plural'];

            // Female
            $context = 'female';
            if (!isset($post_type['female']) || $post_type['female'] != 1) {
                $post_type['female'] = 0;
                $this->post_types[$slug]['female'] = 0;
                $context = 'male';
            }

            // Add array values
            foreach ($this->pt__values_array as $val_name) {
                if (isset($post_type[$val_name])) {
                    if (!is_array($post_type[$val_name])) {
                        $post_type[$val_name] = array($post_type[$val_name]);
                    }
                    $args[$val_name] = $post_type[$val_name];
                }
            }

            // Fix supports
            $this->post_types[$slug]['add_media_box'] = false;
            if (is_array($args['supports']) && in_array('media', $args['supports'])) {
                $this->post_types[$slug]['add_media_box'] = true;
            }

            // Add boolean values
            foreach ($this->pt__values_bool as $val_name) {
                if (isset($post_type[$val_name]) && is_bool($post_type[$val_name])) {
                    $args[$val_name] = $post_type[$val_name];
                }
            }

            // Add text values
            foreach ($this->pt__values_text as $val_name) {
                if (isset($post_type[$val_name]) && !empty($post_type[$val_name])) {
                    $args[$val_name] = $post_type[$val_name];
                }
            }

            // Add various values
            foreach ($this->pt__values_var as $val_name) {
                if (isset($post_type[$val_name]) && !empty($post_type[$val_name])) {
                    $args[$val_name] = $post_type[$val_name];
                }
            }

            $post_type_name = mb_strtolower($post_type['name']);
            $post_type_name_u = $this->ucfirst($post_type_name);
            $post_type_plural = mb_strtolower($post_type['plural']);

            // Labels
            $args['labels'] = array(
                'name' => $this->ucfirst($post_type['plural']),
                'singular_name' => $this->ucfirst($post_type['name']),
                'add_new' => __('Add New', 'wpuposttypestaxos'),
                'add_new_item' => sprintf(_x('Add New %s', 'male', 'wpuposttypestaxos'), $post_type_name),
                'edit_item' => sprintf(_x('Edit %s', 'male', 'wpuposttypestaxos'), $post_type_name),
                'new_item' => sprintf(_x('New %s', 'male', 'wpuposttypestaxos'), $post_type_name),
                'all_items' => sprintf(_x('All %s', 'male', 'wpuposttypestaxos'), $post_type_plural),
                'view_item' => sprintf(_x('View %s', 'male', 'wpuposttypestaxos'), $post_type_name),
                'view_items' => sprintf(_x('View %s', 'maleplural', 'wpuposttypestaxos'), $post_type_plural),
                'search_items' => sprintf(_x('Search %s', 'male', 'wpuposttypestaxos'), $post_type_name),
                'not_found' => sprintf(_x('No %s found', 'male', 'wpuposttypestaxos'), $post_type_name),
                'not_found_in_trash' => sprintf(_x('No %s found in Trash', 'male', 'wpuposttypestaxos'), $post_type_name),
                'parent_item_colon' => '',
                'menu_name' => $this->ucfirst($post_type['plural']),
                'item_published' => sprintf(_x('%s published.', 'male', 'wpuposttypestaxos'), $post_type_name_u),
                'item_published_privately' => sprintf(_x('%s published privately.', 'male', 'wpuposttypestaxos'), $post_type_name_u),
                'item_reverted_to_draft' => sprintf(_x('%s reverted to draft.', 'male', 'wpuposttypestaxos'), $post_type_name_u),
                'item_draft_updated' => sprintf(_x('%s draft updated.', 'male', 'wpuposttypestaxos'), $post_type_name),
                'item_scheduled' => sprintf(_x('%s scheduled.', 'male', 'wpuposttypestaxos'), $post_type_name_u),
                'item_updated' => sprintf(_x('%s updated.', 'male', 'wpuposttypestaxos'), $post_type_name_u),
                'item_saved' => sprintf(_x('%s saved.', 'male', 'wpuposttypestaxos'), $post_type_name_u),
                'item_submitted' => sprintf(_x('%s submitted.', 'male', 'wpuposttypestaxos'), $post_type_name_u),
                'item_preview' => sprintf(_x('Preview %s', 'male', 'wpuposttypestaxos'), $post_type_name)
            );

            // Allow correct translations for post types with a name starting with a consonant
            $letters = mb_str_split($post_type_name);
            $first_letter = $letters[0];
            if (!in_array($first_letter, $this->non_consonants)) {
                $args['labels']['edit_item'] = sprintf(_x('Edit %s', 'male_consonant', 'wpuposttypestaxos'), $post_type_name);
                $args['labels']['view_item'] = sprintf(_x('View %s', 'male_consonant', 'wpuposttypestaxos'), $post_type_name);
                $args['labels']['item_preview'] = sprintf(_x('Preview %s', 'male_consonant', 'wpuposttypestaxos'), $post_type_name);
                $args['labels']['item_draft_updated'] = sprintf(_x('%s draft updated', 'male_consonant', 'wpuposttypestaxos'), $post_type_name);
            }

            // I couldn't use the content of $context var inside of _x() calls because of Poedit :(
            if ($context == 'female') {
                $args['labels']['add_new_item'] = sprintf(_x('Add New %s', 'female', 'wpuposttypestaxos'), $post_type_name);
                $args['labels']['edit_item'] = sprintf(_x('Edit %s', 'female', 'wpuposttypestaxos'), $post_type_name);
                $args['labels']['new_item'] = sprintf(_x('New %s', 'female', 'wpuposttypestaxos'), $post_type_name);
                $args['labels']['all_items'] = sprintf(_x('All %s', 'female', 'wpuposttypestaxos'), $post_type_plural);
                $args['labels']['view_item'] = sprintf(_x('View %s', 'female', 'wpuposttypestaxos'), $post_type_name);
                $args['labels']['view_items'] = sprintf(_x('View %s', 'femaleplural', 'wpuposttypestaxos'), $post_type_plural);
                $args['labels']['search_items'] = sprintf(_x('Search %s', 'female', 'wpuposttypestaxos'), $post_type_name);
                $args['labels']['not_found'] = sprintf(_x('No %s found', 'female', 'wpuposttypestaxos'), $post_type_name);
                $args['labels']['not_found_in_trash'] = sprintf(_x('No %s found in Trash', 'female', 'wpuposttypestaxos'), $post_type_name);
                $args['labels']['item_published'] = sprintf(_x('%s published.', 'female', 'wpuposttypestaxos'), $post_type_name_u);
                $args['labels']['item_published_privately'] = sprintf(_x('%s published privately.', 'female', 'wpuposttypestaxos'), $post_type_name_u);
                $args['labels']['item_reverted_to_draft'] = sprintf(_x('%s reverted to draft.', 'female', 'wpuposttypestaxos'), $post_type_name_u);
                $args['labels']['item_draft_updated'] = sprintf(_x('%s draft updated.', 'female', 'wpuposttypestaxos'), $post_type_name);
                $args['labels']['item_scheduled'] = sprintf(_x('%s scheduled.', 'female', 'wpuposttypestaxos'), $post_type_name_u);
                $args['labels']['item_updated'] = sprintf(_x('%s updated.', 'female', 'wpuposttypestaxos'), $post_type_name_u);
                $args['labels']['item_saved'] = sprintf(_x('%s saved.', 'female', 'wpuposttypestaxos'), $post_type_name_u);
                $args['labels']['item_submitted'] = sprintf(_x('%s submitted.', 'female', 'wpuposttypestaxos'), $post_type_name_u);
                $args['labels']['item_preview'] = sprintf(_x('Preview %s', 'female', 'wpuposttypestaxos'), $post_type_name);
                if (in_array($first_letter, $this->non_consonants)) {
                    $args['labels']['item_preview'] = sprintf(_x('Preview %s', 'female_nonconsonant', 'wpuposttypestaxos'), $post_type_name);
                    $args['labels']['item_draft_updated'] = sprintf(_x('%s draft updated', 'female_nonconsonant', 'wpuposttypestaxos'), $post_type_name);
                }
            }

            if (isset($post_type['labels']) && is_array($post_type['labels'])) {
                $args['labels'] = $post_type['labels'];
            }

            register_post_type($slug, $args);
        }

    }

    public function post_updated_messages($messages) {
        foreach ($this->post_types as $slug => $post_type) {
            if (!isset($messages[$slug])) {
                $messages[$slug] = $messages['post'];
                $pt = get_post_type_object($slug);
                foreach ($messages[$slug] as &$message) {
                    if ($message) {
                        $message = str_replace(__('Post published.'), $pt->labels->item_published, $message);
                        $message = str_replace(__('Post updated.'), $pt->labels->item_updated, $message);
                        $message = str_replace(__('Post submitted.'), $pt->labels->item_submitted, $message);
                        $message = str_replace(__('Post saved.'), $pt->labels->item_saved, $message);
                        $message = str_replace(__('Post draft updated.'), $pt->labels->item_draft_updated, $message);
                        $message = str_replace(__('View post'), $pt->labels->view_item, $message);
                        $message = str_replace(__('Post'), $pt->labels->singular_name, $message);
                        $message = str_replace(__('Preview post'), $pt->labels->item_preview, $message);
                        if ($post_type['female']) {
                            $message = str_replace(_x('scheduled', 'male', 'wpuposttypestaxos'), _x('scheduled', 'female', 'wpuposttypestaxos'), $message);
                        }
                    }
                }
            }
        }
        return $messages;
    }

    /* ----------------------------------------------------------
      Add taxonomies
    ---------------------------------------------------------- */

    public function add_taxonomies() {
        $taxonomies = apply_filters('wputh_get_taxonomies', array());
        $this->taxonomies = $this->verify_taxonomies($taxonomies);
        foreach ($this->taxonomies as $slug => $taxo) {

            $singular = $taxo['name'];
            $plural = $singular . 's';

            if (isset($taxo['plural'])) {
                $plural = $taxo['plural'];
            }

            $tax_slug = $slug;
            if (isset($taxo['wpu_tax_slug'])) {
                $tax_slug = $taxo['wpu_tax_slug'];
            }

            $args = array(
                'label' => $plural,
                'public' => true,
                'show_admin_column' => false,
                'show_in_rest' => true,
                'rewrite' => array(
                    'slug' => $tax_slug
                )
            );

            // Add bool values
            foreach ($this->tax__values_bool as $val_name) {
                if (isset($taxo[$val_name]) && is_bool($taxo[$val_name])) {
                    $args[$val_name] = $taxo[$val_name];
                }
            }

            // Add various values
            foreach ($this->tax__values_var as $val_name) {
                if (isset($taxo[$val_name]) && !empty($taxo[$val_name])) {
                    $args[$val_name] = $taxo[$val_name];
                }
            }

            // Hide only in front
            if ($taxo['wputh__hide_front']) {
                $args['public'] = false;
                $args['show_in_rest'] = false;
                $args['show_in_menu'] = true;
                $args['show_ui'] = true;
                $args['rewrite'] = false;
            }

            // Female
            $context = 'female';
            if (!isset($taxo['female']) || $taxo['female'] != 1) {
                $taxo['female'] = 0;
                $context = 'male';
            }

            $args['labels'] = array(
                'name' => $this->ucfirst($plural),
                'singular_name' => $singular,
                'menu_name' => $this->ucfirst($plural)
            );

            $taxo_name = mb_strtolower($singular);
            $taxo_plural = mb_strtolower($plural);

            $args['labels']['search_items'] = sprintf(_x('Search %s', 'male', 'wpuposttypestaxos'), $taxo_name);
            $args['labels']['popular_items'] = $this->ucfirst(mb_strtolower(sprintf(_x('Popular %s', 'male', 'wpuposttypestaxos'), $plural)));
            $args['labels']['all_items'] = sprintf(_x('All %s', 'male', 'wpuposttypestaxos'), $taxo_plural);
            $args['labels']['edit_item'] = sprintf(_x('Edit %s', 'male', 'wpuposttypestaxos'), $taxo_name);
            $args['labels']['update_item'] = sprintf(_x('Update %s', 'male', 'wpuposttypestaxos'), $taxo_name);
            $args['labels']['add_new_item'] = sprintf(_x('Add New %s', 'male', 'wpuposttypestaxos'), $taxo_name);
            $args['labels']['new_item_name'] = sprintf(_x('New %s Name', 'male', 'wpuposttypestaxos'), $taxo_name);
            $args['labels']['separate_items_with_commas'] = sprintf(_x('Separate %s with commas', 'male', 'wpuposttypestaxos'), $taxo_plural);
            $args['labels']['add_or_remove_items'] = sprintf(_x('Add or remove %s', 'male', 'wpuposttypestaxos'), $taxo_plural);
            $args['labels']['choose_from_most_used'] = sprintf(_x('Choose from the most used %s', 'male', 'wpuposttypestaxos'), $taxo_plural);
            $args['labels']['not_found'] = sprintf(_x('No %s found.', 'male', 'wpuposttypestaxos'), $taxo_name);

            // Allow correct translations for post types with a name starting with a consonant
            $first_letter = $taxo_name[0];
            if (!in_array($first_letter, $this->non_consonants)) {
                $args['labels']['edit_item'] = sprintf(_x('Edit %s', 'male_consonant', 'wpuposttypestaxos'), $taxo_name);
                $args['labels']['update_item'] = sprintf(_x('Update %s', 'male_consonant', 'wpuposttypestaxos'), $taxo_name);
            }

            if ($context == 'female') {
                $args['labels']['search_items'] = sprintf(_x('Search %s', 'female', 'wpuposttypestaxos'), $taxo_name);
                $args['labels']['popular_items'] = $this->ucfirst(mb_strtolower(sprintf(_x('Popular %s', 'female', 'wpuposttypestaxos'), $plural)));
                $args['labels']['all_items'] = sprintf(_x('All %s', 'female', 'wpuposttypestaxos'), $taxo_plural);
                $args['labels']['edit_item'] = sprintf(_x('Edit %s', 'female', 'wpuposttypestaxos'), $taxo_name);
                $args['labels']['update_item'] = sprintf(_x('Update %s', 'female', 'wpuposttypestaxos'), $taxo_name);
                $args['labels']['add_new_item'] = sprintf(_x('Add New %s', 'female', 'wpuposttypestaxos'), $taxo_name);
                $args['labels']['new_item_name'] = sprintf(_x('New %s Name', 'female', 'wpuposttypestaxos'), $taxo_name);
                $args['labels']['separate_items_with_commas'] = sprintf(_x('Separate %s with commas', 'female', 'wpuposttypestaxos'), $taxo_plural);
                $args['labels']['add_or_remove_items'] = sprintf(_x('Add or remove %s', 'female', 'wpuposttypestaxos'), $taxo_plural);
                $args['labels']['choose_from_most_used'] = sprintf(_x('Choose from the most used %s', 'female', 'wpuposttypestaxos'), $taxo_plural);
                $args['labels']['not_found'] = sprintf(_x('No %s found.', 'female', 'wpuposttypestaxos'), $taxo_name);
            }

            if (isset($taxo['labels']) && is_array($taxo['labels'])) {
                $args['labels'] = $taxo['labels'];
            }

            register_taxonomy($slug, $taxo['post_type'], $args);
        }
    }

    public function disable_taxonomy_front($query) {
        if (is_admin() || !is_tax()) {
            return;
        }
        foreach ($this->taxonomies as $slug => $taxo) {
            if ($taxo['wputh__hide_front'] && is_tax($slug)) {
                $query->set_404();
            }
        }
    }

    /* ----------------------------------------------------------
      Verify taxonomies
    ---------------------------------------------------------- */

    private function verify_taxonomies($taxonomies) {
        foreach ($taxonomies as $slug => $taxo) {
            $post_type = (isset($taxo['post_type']) ? $taxo['post_type'] : array(
                'post'
            ));
            if (!is_array($post_type)) {
                $post_type = array(
                    $post_type
                );
            }
            if (!isset($taxo['name'])) {
                $taxonomies[$slug]['name'] = isset($taxo['label']) ? $taxo['label'] : $slug;
            }
            $taxonomies[$slug]['post_type'] = $post_type;
            $taxonomies[$slug]['wputh__hide_front'] = (isset($taxo['wputh__hide_front']) && is_bool($taxo['wputh__hide_front'])) ? $taxo['wputh__hide_front'] : false;
            $taxonomies[$slug]['hierarchical'] = isset($taxo['hierarchical']) ? $taxo['hierarchical'] : true;
            $taxonomies[$slug]['show_admin_column'] = isset($taxo['show_admin_column']) ? $taxo['show_admin_column'] : true;
        }
        return $taxonomies;
    }

    /* ----------------------------------------------------------
      Dashboard widget
    ---------------------------------------------------------- */

    public function add_dashboard_glance_items() {
        $args = array(
            'public' => true,
            '_builtin' => false
        );
        $output = 'object';
        $operator = 'and';
        $post_types = get_post_types($args, $output, $operator);
        foreach ($post_types as $id => $post_type) {
            if (!current_user_can($post_type->cap->edit_posts)) {
                continue;
            }
            $female = isset($this->post_types[$id], $this->post_types[$id]['female']) && $this->post_types[$id]['female'];
            $num_posts = $this->wp_count_posts($post_type->name);
            $menu_icon = $post_type->menu_icon;
            if (!$menu_icon) {
                $menu_icon = 'dashicons-admin-post';
            }
            $num = number_format_i18n($num_posts->publish);
            $text = mb_strtolower(_n($post_type->labels->singular_name, $post_type->labels->name, intval($num_posts->publish)));
            if ($num == 0) {
                $num = $female ? _x('No', 'female-none', 'wpuposttypestaxos') : _x('No', 'male-none', 'wpuposttypestaxos');
                $text = mb_strtolower($post_type->labels->singular_name);
            }
            echo '<li class="wpucpt-count"><a href="' . admin_url('edit.php?post_type=' . $post_type->name) . '"><i class="wpucpt-icon dashicons ' . $menu_icon . '"></i>' . $num . ' ' . $text . '</a></li>';
        }
        $taxonomies = get_taxonomies($args, $output, $operator);
        foreach ($taxonomies as $id => $taxonomy) {
            if (!current_user_can($taxonomy->cap->edit_terms)) {
                continue;
            }
            $female = isset($this->taxonomies[$id], $this->taxonomies[$id]['female']) && $this->taxonomies[$id]['female'];
            $num_terms = $this->wp_count_terms($taxonomy->name);
            $num = number_format_i18n($num_terms);
            $text = mb_strtolower(_n($taxonomy->labels->singular_name, $taxonomy->labels->name, intval($num_terms)));
            if ($num == 0) {
                $num = $female ? _x('No', 'female-none', 'wpuposttypestaxos') : _x('No', 'male-none', 'wpuposttypestaxos');
                $text = mb_strtolower($taxonomy->labels->singular_name);
            }
            $linked_post = '';
            if (property_exists($taxonomy, 'object_type') && count($taxonomy->object_type) == 1) {
                $linked_post = '&post_type=' . $taxonomy->object_type[0];
            }
            echo '<li class="post-count"><a href="' . admin_url('edit-tags.php?taxonomy=' . $taxonomy->name . $linked_post) . '">' . $num . ' ' . $text . '</a></li>';
        }
    }

    /* Posts Count
    -------------------------- */

    public function wp_count_posts($post_type_name) {
        $cache_id = 'wpuposttypestaxos_count_posts_' . $post_type_name;
        $result = wp_cache_get($cache_id);
        if ($result === false) {
            $result = wp_count_posts($post_type_name);
            wp_cache_set($cache_id, $result, '', 86400);
        }
        return $result;
    }

    public function clear_cache_posttype($post_id) {
        $post_type = get_post_type($post_id);
        wp_cache_delete('wpuposttypestaxos_count_posts_' . $post_type);
        $this->wp_count_posts($post_type);
    }

    /* Terms Count
    -------------------------- */

    public function wp_count_terms($taxonomy_name) {
        $cache_id = 'wpuposttypestaxos_count_terms_' . $taxonomy_name;
        $result = wp_cache_get($cache_id);
        if ($result === false) {
            $result = wp_count_terms($taxonomy_name);
            wp_cache_set($cache_id, $result, '', 86400);
        }
        return $result;
    }

    public function clear_cache_taxonomy($term_id = false, $tt_id = false, $taxonomy_name = 'category') {
        wp_cache_delete('wpuposttypestaxos_count_terms_' . $taxonomy_name);
        $this->wp_count_terms($taxonomy_name);
    }

    /* ----------------------------------------------------------
      Editor styles
    ---------------------------------------------------------- */

    public function add_editor_styles() {
        $current_post_type = '';

        if (isset($_GET['post']) && is_numeric($_GET['post'])) {
            $current_post_type = get_post_type($_GET['post']);
        }

        foreach ($this->post_types as $id => $post_type) {
            if (isset($post_type['editor_style'])) {

                // Do not load for other post types
                if (!empty($current_post_type) && $id != $current_post_type) {
                    continue;
                }

                if (!is_array($post_type['editor_style'])) {
                    $post_type['editor_style'] = array(
                        $post_type['editor_style']
                    );
                }
                foreach ($post_type['editor_style'] as $css) {
                    add_editor_style(get_stylesheet_directory_uri() . $css);
                }
            }
        }
    }

    /* ----------------------------------------------------------
      Menu Order
    ---------------------------------------------------------- */

    public function edit_admin_menu() {
        global $submenu;

        foreach ($this->post_types as $k => $post_type) {
            if (!isset($post_type['show_in_menu']) || empty($post_type['show_in_menu'])) {
                continue;
            }
            if (!isset($submenu[$post_type['show_in_menu']])) {
                continue;
            }
            if (!isset($post_type['position_in_nav_menu']) || !is_numeric($post_type['position_in_nav_menu'])) {
                continue;
            }
            $this->moveElementToFrom($submenu[$post_type['show_in_menu']], $post_type['position_in_nav_menu']);
        }
    }

    /* https://stackoverflow.com/a/28831998 */
    public function moveElementToFrom(&$array, $to, $from = false) {
        if ($from === false) {
            $from = count($array) - 1;
        }
        $p1 = array_splice($array, $from, 1);
        $p2 = array_splice($array, 0, $to);
        $array = array_merge($p2, $p1, $array);
    }

    /* ----------------------------------------------------------
      Search
    ---------------------------------------------------------- */

    public function wp_link_query($results, $query) {

        $post_types_search = apply_filters('wputh_get_posttypes', array());
        $post_types = get_post_types(array(), 'objects');
        $s = '';
        if (isset($query['s']) && $query['s']) {
            $s = trim(strtolower($query['s']));
            foreach ($post_types as $pt_id => $post_type) {
                if (!array_key_exists($pt_id, $post_types_search) || !$post_type->rewrite['with_front']) {
                    continue;
                }
                $label_tmp = strtolower($post_type->label);
                if (strpos($label_tmp, $s) !== false || $label_tmp == $s) {
                    $results[] = array(
                        'ID' => false,
                        'title' => $post_type->label,
                        'permalink' => get_post_type_archive_link($pt_id),
                        'info' => __('Archive', 'wpuposttypestaxos')
                    );
                }
            }
        }

        $taxonomies_search = apply_filters('wputh_get_taxonomies', array());

        /* All results are displayed at first call so we have to block the paging action */
        if (isset($query['offset']) && $query['offset'] > 0) {
            $taxonomies_search = array();
        }
        foreach ($taxonomies_search as $tax_id => $tax_infos) {
            /* Prevent invalid taxonomies */
            if (!isset($tax_infos['name'], $tax_infos['wpu_public_archive'])) {
                continue;
            }
            /* Prevent non public taxonomies */
            if (!$tax_infos['wpu_public_archive']) {
                continue;
            }
            $terms = get_terms(array(
                'taxonomy' => array($tax_id),
                'hide_empty' => false,
                'fields' => 'all',
                'name__like' => $query['s']
            ));
            foreach ($terms as $term) {
                $results[] = array(
                    'ID' => false,
                    'title' => $term->name,
                    'permalink' => get_term_link($term),
                    'info' => $tax_infos['name']
                );
            }
        }
        return $results;
    }

    /* ----------------------------------------------------------
      Front
    ---------------------------------------------------------- */

    public function template_redirect() {
        if (!is_singular()) {
            return;
        }
        $post_type = get_post_type();
        /* Remove adjacent meta links if useless */
        if (in_array($post_type, $this->post_types_without_adjacent)) {
            remove_action('wp_head', 'wp_shortlink_wp_head');
            remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
        }

        if (isset($this->post_types[$post_type], $this->post_types[$post_type]['wputh__hide_singular']) && $this->post_types[$post_type]['wputh__hide_singular']) {
            $this->basetoolbox->send_404();
        }
    }

    /* ----------------------------------------------------------
      Sitemaps
    ---------------------------------------------------------- */

    public function restrict_sitemaps_post_types($post_types) {
        foreach ($this->post_types as $slug => $post_type) {
            if (isset($post_type['wputh__hide_singular']) && $post_type['wputh__hide_singular']) {
                unset($post_types[$slug]);
            }
        }
        return $post_types;
    }

    /* ----------------------------------------------------------
      Ucfirst
    ---------------------------------------------------------- */

    public function ucfirst($string) {
        return mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
    }

    /* ----------------------------------------------------------
      Filter taxonomies in admin list
    ---------------------------------------------------------- */

    public function restrict_manage_posts() {
        $taxonomies = apply_filters('wputh_get_taxonomies', array());
        $this->taxonomies = $this->verify_taxonomies($taxonomies);
        $pt = get_post_type();
        foreach ($this->taxonomies as $tax_id => $tax) {
            if (!isset($tax['wputh__admin_filter']) || !$tax['wputh__admin_filter']) {
                continue;
            }
            if (!isset($tax['post_type']) || ($tax['post_type'] != $pt && !in_array($pt, $tax['post_type']))) {
                continue;
            }
            $tax_obj = get_taxonomy($tax_id);
            if (!$tax_obj || !is_object($tax_obj)) {
                continue;
            }
            $terms = get_terms(array(
                'taxonomy' => $tax_id,
                'hide_empty' => false
            ));
            if (count($terms) <= 1) {
                continue;
            }
            echo '<select name="' . $tax_id . '" class="">';
            echo '<option value="">' . $tax_obj->labels->all_items . '</option>';
            foreach ($terms as $term) {
                echo '<option ' . (isset($_GET[$tax_id]) && $_GET[$tax_id] == $term->slug ? 'selected="selected"' : '') . ' value="' . esc_attr($term->slug) . '">' . esc_html($term->name) . '</option>';
            }
            echo '</select>';
        }
    }

    /* ----------------------------------------------------------
      RSS Feed
    ---------------------------------------------------------- */

    public function remove_rss_feed_link() {
        foreach ($this->post_types as $pt_id => $post_type) {
            if (!isset($post_type['wpu_disable_feed']) || !$post_type['wpu_disable_feed']) {
                continue;
            }
            if (is_post_type_archive($pt_id)) {
                remove_action('wp_head', 'feed_links_extra', 3);
            }
        }
    }

    public function remove_rss_disable_feed() {
        if (!is_feed()) {
            return;
        }

        $current_post_type = get_post_type();

        foreach ($this->post_types as $pt_id => $post_type) {
            if (!isset($post_type['wpu_disable_feed']) || !$post_type['wpu_disable_feed']) {
                continue;
            }

            /* Force 404 */
            if ($current_post_type == $pt_id) {
                $this->basetoolbox->send_404();
            }
        }
    }

    /* ----------------------------------------------------------
      Load gallery ( Thx to @GeekPress )
    ---------------------------------------------------------- */

    public function load_gallery_metabox() {
        global $post;
        foreach ($this->post_types as $slug => $post_type) {
            if (isset($post_type['add_media_box']) && $post_type['add_media_box']) {
                wp_enqueue_media(array('post' => $post->ID));
                add_meta_box('wpuposttypetaxos_media_upload', ' ', array(&$this,
                    'load_media_upload'
                ), $slug, 'normal', 'high');
            }
        }
    }

    public function load_media_upload() {
        global $post;
        echo '<div class="wpuposttypetaxos-wrapper-addmedia"><a href="media-upload.php?post_id=' . $post->ID . '&TB_iframe=1" class="button insert-media add_media" id="content-add_media" onclick="return false;"><span style="vertical-align:-5px;margin-right:5px;" class="dashicons dashicons-admin-media"></span> ' . __('Add Media') . '</a></div>';
        echo '<script>jQuery(document).ready(function() {
        var postbox = jQuery("#content-add_media").closest(".postbox");
    postbox.removeClass("postbox");
    postbox.find(".handlediv, h3.hndle").remove();
});</script>';
    }
}

new wputh_add_post_types_taxonomies();
