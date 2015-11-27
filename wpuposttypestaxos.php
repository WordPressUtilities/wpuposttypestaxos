<?php

/*
Plugin Name: WPU Post types & taxonomies
Description: Load custom post types & taxonomies
Version: 0.10.2
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class wputh_add_post_types_taxonomies {
    private $values_array = array(
        'supports',
        'taxonomies'
    );
    private $values_text = array(
        'menu_icon'
    );
    private $values_bool = array(
        'can_export',
        'exclude_from_search',
        'has_archive',
        'public',
        'publicly_queryable',
        'query_var',
        'rewrite',
        'show_ui',
        'with_front'
    );
    private $non_consonants = array(
        'a',
        'e',
        'i',
        'o',
        'u',
        'y',
        'h'
    );

    function __construct() {

        add_action('plugins_loaded', array(&$this,
            'load_plugin_textdomain'
        ));
        add_action('init', array(&$this,
            'add_post_types'
        ));
        add_action('init', array(&$this,
            'add_taxonomies'
        ));

        if (is_admin()) {
            add_action('add_meta_boxes', array(&$this,
                'load_gallery_metabox'
            ));
            add_action('dashboard_glance_items', array(&$this,
                'add_dashboard_glance_items'
            ));
            add_filter('manage_posts_columns', array(&$this,
                'columns_head_taxo'
            ) , 10);
            add_action('manage_posts_custom_column', array(&$this,
                'columns_content_taxo'
            ) , 10, 2);
            add_action('admin_init', array(&$this,
                'add_editor_styles'
            ));
        }
    }

    function load_plugin_textdomain() {
        load_plugin_textdomain('wpuposttypestaxos', false, dirname(plugin_basename(__FILE__)) . '/lang/');
    }

    /* ----------------------------------------------------------
      Add Post types
    ---------------------------------------------------------- */

    public function add_post_types() {
        $this->post_types = apply_filters('wputh_get_posttypes', array());
        foreach ($this->post_types as $slug => $post_type) {

            $args = array(
                'menu_icon' => '',
                'exclude_from_search' => false,
                'has_archive' => true,
                'public' => true,
                'publicly_queryable' => true,
                'rewrite' => true,
                'can_export' => true,
                'show_ui' => true,
                'with_front' => true,
                'taxonomies' => array() ,
                'supports' => array(
                    'title',
                    'editor',
                    'thumbnail'
                )
            );

            // Hide only in front
            if (isset($post_type['wputh__hide_front']) && is_bool($post_type['wputh__hide_front']) && $post_type['wputh__hide_front'] !== false) {
                $args['has_archive'] = false;
                $args['public'] = false;
                $args['publicly_queryable'] = false;
                $args['query_var'] = false;
                $args['rewrite'] = false;
                $args['exclude_from_search'] = true;
            }

            // Default label: slug
            if (!isset($post_type['name'])) {
                $post_type['name'] = ucfirst($slug);
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
                $context = 'male';
            }

            // Add array values
            foreach ($this->values_array as $val_name) {
                if (isset($post_type[$val_name]) && is_array($post_type[$val_name])) {
                    $args[$val_name] = $post_type[$val_name];
                }
            }

            // Fix supports
            $this->post_types[$slug]['add_media_box'] = false;
            if (is_array($args['supports']) && isset($args['supports']['media'])) {
                $this->post_types[$slug]['add_media_box'] = true;
            }

            // Add boolean values
            foreach ($this->values_bool as $val_name) {
                if (isset($post_type[$val_name]) && is_bool($post_type[$val_name])) {
                    $args[$val_name] = $post_type[$val_name];
                }
            }

            // Add text values
            foreach ($this->values_text as $val_name) {
                if (isset($post_type[$val_name]) && !empty($post_type[$val_name])) {
                    $args[$val_name] = $post_type[$val_name];
                }
            }

            $post_type_name = strtolower($post_type['name']);
            $post_type_plural = strtolower($post_type['plural']);

            // Labels
            $args['labels'] = array(
                'name' => ucfirst($post_type['plural']) ,
                'singular_name' => ucfirst($post_type['name']) ,
                'add_new' => __('Add New', 'wpuposttypestaxos') ,
                'add_new_item' => sprintf(_x('Add New %s', 'male', 'wpuposttypestaxos') , $post_type_name) ,
                'edit_item' => sprintf(_x('Edit %s', 'male', 'wpuposttypestaxos') , $post_type_name) ,
                'new_item' => sprintf(_x('New %s', 'male', 'wpuposttypestaxos') , $post_type_name) ,
                'all_items' => sprintf(_x('All %s', 'male', 'wpuposttypestaxos') , $post_type_plural) ,
                'view_item' => sprintf(_x('View %s', 'male', 'wpuposttypestaxos') , $post_type_name) ,
                'search_items' => sprintf(_x('Search %s', 'male', 'wpuposttypestaxos') , $post_type_name) ,
                'not_found' => sprintf(_x('No %s found', 'male', 'wpuposttypestaxos') , $post_type_name) ,
                'not_found_in_trash' => sprintf(_x('No %s found in Trash', 'male', 'wpuposttypestaxos') , $post_type_name) ,
                'parent_item_colon' => '',
                'menu_name' => ucfirst($post_type['plural'])
            );

            // Allow correct translations for post types with a name starting with a consonant
            $first_letter = $post_type_name[0];
            if (!in_array($first_letter, $this->non_consonants)) {
                $args['labels']['edit_item'] = sprintf(_x('Edit %s', 'male_consonant', 'wpuposttypestaxos') , $post_type_name);
                $args['labels']['view_item'] = sprintf(_x('View %s', 'male_consonant', 'wpuposttypestaxos') , $post_type_name);
            }

            // I couldn't use the content of $context var inside of _x() calls because of Poedit :(
            if ($context == 'female') {
                $args['labels']['add_new_item'] = sprintf(_x('Add New %s', 'female', 'wpuposttypestaxos') , $post_type_name);
                $args['labels']['edit_item'] = sprintf(_x('Edit %s', 'female', 'wpuposttypestaxos') , $post_type_name);
                $args['labels']['new_item'] = sprintf(_x('New %s', 'female', 'wpuposttypestaxos') , $post_type_name);
                $args['labels']['all_items'] = sprintf(_x('All %s', 'female', 'wpuposttypestaxos') , $post_type_plural);
                $args['labels']['view_item'] = sprintf(_x('View %s', 'female', 'wpuposttypestaxos') , $post_type_name);
                $args['labels']['search_items'] = sprintf(_x('Search %s', 'female', 'wpuposttypestaxos') , $post_type_name);
                $args['labels']['not_found'] = sprintf(_x('No %s found', 'female', 'wpuposttypestaxos') , $post_type_name);
                $args['labels']['not_found_in_trash'] = sprintf(_x('No %s found in Trash', 'female', 'wpuposttypestaxos') , $post_type_name);
            }

            register_post_type($slug, $args);
        }
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

            $args = array(
                'label' => $plural,
                'rewrite' => array(
                    'slug' => $slug
                ) ,
                'hierarchical' => $taxo['hierarchical']
            );

            // Female
            $context = 'female';
            if (!isset($taxo['female']) || $taxo['female'] != 1) {
                $taxo['female'] = 0;
                $context = 'male';
            }

            $args['labels'] = array(
                'name' => ucfirst($plural) ,
                'singular_name' => $singular,
                'menu_name' => ucfirst($plural) ,
            );

            $args['labels']['search_items'] = sprintf(_x('Search %s', 'male', 'wpuposttypestaxos') , strtolower($singular));
            $args['labels']['popular_items'] = ucfirst(strtolower(sprintf(_x('Popular %s', 'male', 'wpuposttypestaxos') , $plural)));
            $args['labels']['all_items'] = sprintf(_x('All %s', 'male', 'wpuposttypestaxos') , strtolower($plural));
            $args['labels']['edit_item'] = sprintf(_x('Edit %s', 'male', 'wpuposttypestaxos') , strtolower($singular));
            $args['labels']['update_item'] = sprintf(_x('Update %s', 'male', 'wpuposttypestaxos') , strtolower($singular));
            $args['labels']['add_new_item'] = sprintf(_x('Add New %s', 'male', 'wpuposttypestaxos') , strtolower($singular));
            $args['labels']['new_item_name'] = sprintf(_x('New %s Name', 'male', 'wpuposttypestaxos') , strtolower($singular));
            $args['labels']['separate_items_with_commas'] = sprintf(_x('Separate %s with commas', 'male', 'wpuposttypestaxos') , strtolower($plural));
            $args['labels']['add_or_remove_items'] = sprintf(_x('Add or remove %s', 'male', 'wpuposttypestaxos') , strtolower($plural));
            $args['labels']['choose_from_most_used'] = sprintf(_x('Choose from the most used %s', 'male', 'wpuposttypestaxos') , strtolower($plural));
            $args['labels']['not_found'] = sprintf(_x('No %s found.', 'male', 'wpuposttypestaxos') , strtolower($singular));

            if ($context == 'female') {
                $args['labels']['search_items'] = sprintf(_x('Search %s', 'female', 'wpuposttypestaxos') , strtolower($singular));
                $args['labels']['popular_items'] = ucfirst(strtolower(sprintf(_x('Popular %s', 'female', 'wpuposttypestaxos') , $plural)));
                $args['labels']['all_items'] = sprintf(_x('All %s', 'female', 'wpuposttypestaxos') , strtolower($plural));
                $args['labels']['edit_item'] = sprintf(_x('Edit %s', 'female', 'wpuposttypestaxos') , strtolower($singular));
                $args['labels']['update_item'] = sprintf(_x('Update %s', 'female', 'wpuposttypestaxos') , strtolower($singular));
                $args['labels']['add_new_item'] = sprintf(_x('Add New %s', 'female', 'wpuposttypestaxos') , strtolower($singular));
                $args['labels']['new_item_name'] = sprintf(_x('New %s Name', 'female', 'wpuposttypestaxos') , strtolower($singular));
                $args['labels']['separate_items_with_commas'] = sprintf(_x('Separate %s with commas', 'female', 'wpuposttypestaxos') , strtolower($plural));
                $args['labels']['add_or_remove_items'] = sprintf(_x('Add or remove %s', 'female', 'wpuposttypestaxos') , strtolower($plural));
                $args['labels']['choose_from_most_used'] = sprintf(_x('Choose from the most used %s', 'female', 'wpuposttypestaxos') , strtolower($plural));
                $args['labels']['not_found'] = sprintf(_x('No %s found.', 'female', 'wpuposttypestaxos') , strtolower($singular));
            }

            register_taxonomy($slug, $taxo['post_type'], $args);
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
            $taxonomies[$slug]['post_type'] = $post_type;
            $taxonomies[$slug]['hierarchical'] = isset($taxo['hierarchical']) ? $taxo['hierarchical'] : true;
            $taxonomies[$slug]['admin_column'] = isset($taxo['admin_column']) ? $taxo['admin_column'] : true;
        }
        return $taxonomies;
    }

    /* ----------------------------------------------------------
      Add taxonomy columns
    ---------------------------------------------------------- */

    public function columns_head_taxo($defaults) {
        global $post;

        // Isolate latest value
        $last_key = key(array_slice($defaults, -1, 1, TRUE));
        $last_value = $defaults[$last_key];
        unset($defaults[$last_key]);

        foreach ($this->taxonomies as $slug => $taxo) {

            // Add keys
            if ($taxo['admin_column'] && isset($post->post_type) && in_array($post->post_type, $taxo['post_type'])) {
                $defaults[$slug] = $taxo['name'];
            }
        }

        // Add latest value
        $defaults[$last_key] = $last_value;
        return $defaults;
    }

    public function columns_content_taxo($column_name, $post_id) {
        global $post;
        if (!isset($post->post_type)) {
            return;
        }

        foreach ($this->taxonomies as $slug => $taxo) {
            if ($column_name == $slug && in_array($post->post_type, $taxo['post_type'])) {
                $terms = wp_get_post_terms($post_id, $slug);
                $content_term = array();
                if (is_array($terms)) {
                    foreach ($terms as $term) {
                        $content_term[] = '<a href="' . admin_url('edit.php?post_type=' . $post->post_type . '&' . $slug . '=' . $term->slug) . '">' . $term->name . '</a>';
                    }
                }
                if (empty($content_term)) {
                    $content_term = array(
                        '-'
                    );
                }
                echo implode(', ', $content_term);
            }
        }
    }

    /* ----------------------------------------------------------
      Dashboard widget
    ---------------------------------------------------------- */

    function add_dashboard_glance_items() {
        $args = array(
            'public' => true,
            '_builtin' => false
        );
        $output = 'object';
        $operator = 'and';
        $post_types = get_post_types($args, $output, $operator);
        foreach ($post_types as $post_type) {
            $num_posts = wp_count_posts($post_type->name);
            $num = number_format_i18n($num_posts->publish);
            $text = strtolower(_n($post_type->labels->singular_name, $post_type->labels->name, intval($num_posts->publish)));
            if (current_user_can('edit_posts')) {
                $cpt_name = $post_type->name;
            }
            echo '<li class="page-count"><tr><a href="' . admin_url('edit.php?post_type=' . $cpt_name) . '"><td class="first b b-' . $post_type->name . '"></td>' . $num . ' <td class="t ' . $post_type->name . '">' . $text . '</td></a></tr></li>';
        }
        $taxonomies = get_taxonomies($args, $output, $operator);
        foreach ($taxonomies as $taxonomy) {
            $num_terms = wp_count_terms($taxonomy->name);
            $num = number_format_i18n($num_terms);
            $text = strtolower(_n($taxonomy->labels->singular_name, $taxonomy->labels->name, intval($num_terms)));
            if (current_user_can('manage_categories')) {
                $cpt_tax = $taxonomy->name;
            }
            echo '<li class="post-count"><tr><a href="' . admin_url('edit-tags.php?taxonomy=' . $cpt_tax) . '"><td class="first b b-' . $taxonomy->name . '"></td>' . $num . ' <td class="t ' . $taxonomy->name . '">' . $text . '</td></a></tr></li>';
        }
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
      Load gallery ( Thx to @GeekPress )
    ---------------------------------------------------------- */

    function load_gallery_metabox() {
        foreach ($this->post_types as $slug => $post_type) {
            if (isset($this->post_types[$slug]['add_media_box'])) {
                wp_enqueue_media();
                add_meta_box('post_meta', 'Title', array(&$this,
                    'load_media_upload'
                ) , $slug, 'normal', 'high');
            }
        }
    }

    function load_media_upload() {
        global $post;
        echo '<div style="margin-bottom:20px;"><a href="media-upload.php?post_id=' . $post->ID . '&TB_iframe=1" class="button insert-media add_media" id="content-add_media" onclick="return false;"><span style="vertical-align:-5px;margin-right:5px;" class="dashicons dashicons-admin-media"></span> ' . __('Add Media') . '</a></div>';
        echo '<script>jQuery(document).ready(function() {
        var postbox = jQuery("#content-add_media").closest(".postbox");
    postbox.removeClass("postbox");
    postbox.find(".handlediv, h3.hndle").remove();
});</script>';
    }
}

new wputh_add_post_types_taxonomies();
