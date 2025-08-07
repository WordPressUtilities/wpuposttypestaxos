<?php
defined('ABSPATH') || die;

class WPUPostTypeTaxosSitemapProvider extends WP_Sitemaps_Provider {
    public function __construct($args = array()) {
        $this->name = 'wpuposttypestaxosarchives';
    }

    public function get_url_list($page_num, $post_type = '') {
        if ($page_num !== 1) {
            return [];
        }

        $post_types = apply_filters('wputh_get_posttypes', array());

        $urls = array();
        foreach ($post_types as $pt_slug => $pt) {
            $url = $this->wpuposttypetaxosget_post_type_url($pt_slug, $pt);
            if ($url) {
                $urls[] = $url;
            }
        }
        return $urls;
    }

    function wpuposttypetaxosget_post_type_url($pt_slug, $pt) {
        if (isset($pt['has_archive']) && !$pt['has_archive']) {
            return false;
        }
        $latest_post = get_posts(array(
            'post_type' => $pt_slug,
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'fields' => 'ids'
        ));
        $url = false;
        if (!empty($latest_post)) {
            $url = array(
                'loc' => get_post_type_archive_link($pt_slug),
                'lastmod' => get_post_modified_time('c', true, $latest_post[0])
            );
        }
        return $url;
    }

    public function get_max_num_pages($post_type = '') {
        return 1;
    }
}
