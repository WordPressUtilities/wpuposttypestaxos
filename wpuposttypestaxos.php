<?php

/*
Plugin Name: WPU Post types & taxonomies
Description: Load custom post types & taxonomies
Version: 0.8
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class wputh_add_post_types_taxonomies
{
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

        // Load lang
        load_plugin_textdomain('wpuposttypestaxos', false, dirname(plugin_basename(__FILE__)) . '/lang/');

        add_action('init', array(&$this,
            'add_post_types'
        ));
        add_action('init', array(&$this,
            'add_taxonomies'
        ));

        if (is_admin()) {
            add_filter('manage_posts_columns', array(&$this,
                'columns_head_taxo'
            ) , 10);
            add_action('manage_posts_custom_column', array(&$this,
                'columns_content_taxo'
            ) , 10, 2);
            add_action('pre_get_posts', array(&$this,
                'add_editor_styles'
            ));
        }
    }

    /* ----------------------------------------------------------
      Add Post types
    ---------------------------------------------------------- */

    public function add_post_types() {
        $post_types = apply_filters('wputh_get_posttypes', array());
        foreach ($post_types as $slug => $post_type) {

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
        $taxonomies = $this->verify_taxonomies($taxonomies);
        foreach ($taxonomies as $slug => $taxo) {

            $args = array(
                'label' => $taxo['name'],
                'rewrite' => array(
                    'slug' => $slug
                ) ,
                'hierarchical' => $taxo['hierarchical']
            );

            $singular = $taxo['name'];
            $plural = $singular . 's';
            if (isset($taxo['plural'])) {
                $plural = $taxo['plural'];
            }

            $args['labels'] = array(
                'name' => $plural,
                'singular_name' => $singular,
                'search_items' => sprintf(__('Search %s', 'wpuposttypestaxos') , strtolower($plural)) ,
                'popular_items' => ucfirst(strtolower(sprintf(__('Popular %s', 'wpuposttypestaxos') , $plural))) ,
                'all_items' => sprintf(__('All %s', 'wpuposttypestaxos') , strtolower($plural)) ,
                'edit_item' => sprintf(__('Edit %s', 'wpuposttypestaxos') , strtolower($singular)) ,
                'update_item' => sprintf(__('Update %s', 'wpuposttypestaxos') , strtolower($singular)) ,
                'add_new_item' => sprintf(__('Add New %s', 'wpuposttypestaxos') , strtolower($singular)) ,
                'new_item_name' => sprintf(__('New %s Name', 'wpuposttypestaxos') , strtolower($singular)) ,
                'separate_items_with_commas' => sprintf(__('Separate %s with commas', 'wpuposttypestaxos') , strtolower($plural)) ,
                'add_or_remove_items' => sprintf(__('Add or remove %s', 'wpuposttypestaxos') , strtolower($plural)) ,
                'choose_from_most_used' => sprintf(__('Choose from the most used %s', 'wpuposttypestaxos') , strtolower($plural)) ,
                'not_found' => sprintf(__('No %s found.', 'wpuposttypestaxos') , strtolower($plural)) ,
                'menu_name' => $plural,
            );

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

        $taxonomies = apply_filters('wputh_get_taxonomies', array());
        $taxonomies = $this->verify_taxonomies($taxonomies);

        foreach ($taxonomies as $slug => $taxo) {

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
        $taxonomies = apply_filters('wputh_get_taxonomies', array());
        $taxonomies = $this->verify_taxonomies($taxonomies);

        foreach ($taxonomies as $slug => $taxo) {
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
      Editor styles
    ---------------------------------------------------------- */

    public function add_editor_styles() {
        $post_types = apply_filters('wputh_get_posttypes', array());
        foreach ($post_types as $post_type) {
            if (isset($post_type['editor_style'])) {
                if (!is_array($post_type['editor_style'])) {
                    $post_type['editor_style'] = array(
                        $post_type['editor_style']
                    );
                }
                foreach ($post_type['editor_style'] as $css) {
                    add_editor_style(get_template_directory_uri() . $css);
                }
            }
        }
    }
}

new wputh_add_post_types_taxonomies();
