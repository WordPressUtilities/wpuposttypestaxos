WPU Post types & Taxos
=================

[![PHP workflow](https://github.com/WordPressUtilities/wpuposttypestaxos/actions/workflows/php.yml/badge.svg 'PHP workflow')](https://github.com/WordPressUtilities/wpuposttypestaxos/actions)

## Add a post type

```php
add_filter('wputh_get_posttypes', 'wputh_set_theme_posttypes');
function wputh_set_theme_posttypes($post_types) {
    $post_types['work'] = array(
        'wpu_disable_feed' => true,
        'menu_icon' => 'dashicons-portfolio',
        'name' => __('Work', 'wputh') ,
        'plural' => __('Works', 'wputh') ,
        'female' => 0
    );
    return $post_types;
}
```

## Add a taxonomy

```php
add_filter('wputh_get_taxonomies', 'wputh_set_theme_taxonomies');
function wputh_set_theme_taxonomies($taxonomies) {
    $taxonomies['work-type'] = array(
        'wpu_public_archive' => true,
        'name' => __( 'Work type', 'wputh' ),
        'post_type' => 'work'
    );
    return $taxonomies;
}
```
