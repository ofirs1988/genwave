<?php

namespace GenWavePlugin\Handlers;

use WP_Query;

/**
 * ProductHandler - Handles product and post listing/filtering
 *
 * Responsibilities:
 * - Get all products
 * - Get all posts
 * - Filter products by various criteria
 * - Get post data
 */
class ProductHandler
{
    /**
     * Get all products (WooCommerce)
     *
     * @return void
     */
    public function getAllProducts()
    {
        global $wpdb;

        // SECURITY: Verify user capabilities (admin only)
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'gen-wave'), 403);
            return;
        }

        // Query to fetch all products
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1, // Fetch all products
        ];

        $query = new WP_Query($args);
        $products = [];
        $categories = []; // Collect all categories

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                global $product;

                $post_id = $product->get_id();
                $image_id = $product->get_image_id();
                $image_url = wp_get_attachment_url($image_id);
                $description = $product->get_description();
                $stock_status = $product->get_stock_status();

                // Get product categories
                $terms = get_the_terms($post_id, 'product_cat');
                $product_categories = [];
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $product_categories[] = $term->name;
                        $categories[] = $term->name; // Collect all categories
                    }
                }

                $products[] = [
                    'id' => $post_id,
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'stock_status' => $stock_status,
                    'image' => $image_url,
                    'description' => $description,
                    'categories' => $product_categories,
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for plugin data
                    'generated' => $wpdb->get_var(
                            $wpdb->prepare("SELECT COUNT(*) FROM wp_gen_requests_posts WHERE post_id = %d AND status = 'completed'", $post_id)
                        ) > 0,
                ];
            }
            wp_reset_postdata();
        }

        // Remove duplicate categories and sort them
        $categories = array_unique($categories);
        sort($categories);

        wp_send_json_success([
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    /**
     * Get all posts
     *
     * @return void
     */
    public function getAllPosts()
    {
        global $wpdb;

        // SECURITY: Verify user capabilities (admin only)
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Insufficient permissions', 'gen-wave'), 403);
            return;
        }

        // Query to fetch all posts
        $args = [
            'post_type' => 'post',
            'posts_per_page' => -1, // Fetch all posts
            'post_status' => 'publish'
        ];

        $query = new WP_Query($args);
        $posts = [];
        $categories = []; // Collect all categories
        $authors = []; // Collect all authors

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                global $post;

                $post_id = $post->ID;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for plugin data
                $is_converted = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT is_converted
                     FROM wp_gen_requests_posts
                     WHERE post_id = %d",
                        $post_id
                    )
                );

                // Skip posts that are already converted
                if ($is_converted === '1') {
                    continue;
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for plugin data
                $is_generated = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*)
                     FROM wp_gen_requests_posts
                     WHERE post_id = %d AND status = 'completed'",
                        $post_id
                    )
                );

                $featured_image_id = get_post_thumbnail_id($post_id);
                $featured_image_url = wp_get_attachment_url($featured_image_id);
                $excerpt = get_the_excerpt($post_id);
                $content = get_the_content('', false, $post_id);

                // Get author info
                $author_id = $post->post_author;
                $author_name = get_the_author_meta('display_name', $author_id);

                // Get post categories
                $terms = get_the_terms($post_id, 'category');
                $post_categories = [];
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $post_categories[] = $term->name;
                        $categories[] = $term->name;
                    }
                }

                // Add author to authors list
                $authors[$author_id] = $author_name;

                $posts[] = [
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    'status' => $post->post_status,
                    'author_id' => $author_id,
                    'author' => $author_name,
                    'date' => get_the_date('Y-m-d H:i:s', $post_id),
                    'featured_image' => $featured_image_url,
                    'excerpt' => $excerpt,
                    'content' => $content,
                    'categories' => $post_categories,
                    'generated' => $is_generated > 0,
                ];
            }
            wp_reset_postdata();
        }

        // Remove duplicate categories and sort them
        $categories = array_unique($categories);
        sort($categories);

        // Convert authors array to indexed array for frontend
        $authors_list = [];
        foreach ($authors as $author_id => $author_name) {
            $authors_list[] = [
                'id' => $author_id,
                'name' => $author_name
            ];
        }

        wp_send_json_success([
            'posts' => $posts,
            'categories' => $categories,
            'authors' => $authors_list,
        ]);
    }

    /**
     * Get post data by ID
     *
     * @return void
     */
    public function handle_get_post_data()
    {
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Get Post Data AJAX: Function called!');
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r,WordPress.Security.NonceVerification.Missing -- Debug mode only, display-only operation
                error_log('Get Post Data AJAX: POST data: ' . print_r($_POST, true));
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Display-only, read operation
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            if (!$post_id) {
                wp_send_json_error('Post ID is required');
                return;
            }

            // Get the post
            $post = get_post($post_id);
            if (!$post) {
                return;
            }

            // Get post data
            $post_data = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'type' => $post->post_type,
                'status' => $post->post_status
            ];

            // If it's a product, get additional WooCommerce data
            if ($post->post_type === 'product' && function_exists('wc_get_product')) {
                $product = wc_get_product($post_id);
                if ($product) {
                    $post_data['short_description'] = $product->get_short_description();
                    $post_data['description'] = $product->get_description();
                    $post_data['price'] = $product->get_price();
                    $post_data['sku'] = $product->get_sku();
                }
            }

            wp_send_json_success([
                'data' => $post_data,
            ]);

        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Get Post Data AJAX: Exception: ' . $e->getMessage());
            }
            wp_send_json_error('Error retrieving post data: ' . $e->getMessage());
        }
    }
}
