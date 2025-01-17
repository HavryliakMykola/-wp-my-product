<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function send_product_admin_email_on_save($post_id) {

    if (get_post_type($post_id) !== 'product') {
        return;
    }


    $post_status = get_post_status($post_id);
    if ($post_status !== 'publish') {
        return;
    }


    $product = wc_get_product($post_id);
    $product_name = $product->get_name();
    $product_url = get_permalink($post_id);
    $author_id = get_post_field('post_author', $post_id);
    $author_url = get_edit_user_link($author_id);

    $subject = 'New Product Added: ' . $product_name;
    $message = "
        <p>A new product has been added:</p>
        <p><strong>Product Name:</strong> $product_name</p>
        <p><strong>Product URL:</strong> <a href='$product_url'>$product_url</a></p>
        <p><strong>Product Author:</strong> <a href='$author_url' target='_blank'>View Author in Admin Panel</a></p>
        <p><strong>Product URL in Admin Panel:</strong> <a href='$product_url' target='_blank'>View Product</a></p>
    ";

    wp_mail(get_option('admin_email'), $subject, $message);
}

add_action('save_post', 'send_product_admin_email_on_save');
