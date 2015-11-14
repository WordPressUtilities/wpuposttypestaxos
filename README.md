WPU Post types & Taxos
=================

## Add a post type

add_filter('wputh_get_posttypes', 'wputh_set_theme_posttypes');
function wputh_set_theme_posttypes($post_types) {
    $post_types['work'] = array(
        'menu_icon' => 'dashicons-portfolio',
        'name' => __('Work', 'wputh') ,
        'plural' => __('Works', 'wputh') ,
        'female' => 0
    );
    return $post_types;
}

## Add a taxonomy

add_filter('wputh_get_taxonomies', 'wputh_set_theme_taxonomies');
function wputh_set_theme_taxonomies($taxonomies) {
    $taxonomies['work-type'] = array(
        'name' => __( 'Work type', 'wputh' ),
        'post_type' => 'work'
    );
    return $taxonomies;
}

