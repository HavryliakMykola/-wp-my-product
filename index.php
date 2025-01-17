<?php
/**
 * Plugin Name: WP My Product Webspark
 * Plugin URI: #
 * Description: This plugin adds custom functionality to add and edit products if WooCommerce is active.
 * Version: 1.0
 * Author: Mykola Havykiak
 * Author URI: #
 * License: GPL2+
 */



include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
include_once( plugin_dir_path( __FILE__ ) . 'email-on-product-save.php');

//JS AND CSS
function enqueue_custom_media_uploader() {
    wp_enqueue_media();
    wp_register_style('custom-style', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_style('custom-style');

    wp_enqueue_script(
        'custom-media-uploader',
        plugin_dir_url(__FILE__) . 'media.js', 
        array('jquery'),
        '1.0',
        true
    );

}
add_action('wp_enqueue_scripts', 'enqueue_custom_media_uploader');



//ADD TWO Subpages 
function my_custom_account_menu_items($items) {
    $new_items = [
        'add-product' => __('Add Product', 'textdomain'),
        'my-products' => __('My Products', 'textdomain'),
    ];
    $position = array_search('orders', array_keys($items), true) - 1; //BEFORE ORDERS
    $items = array_slice($items, 0, $position, true) + $new_items + array_slice($items, $position, null, true);

    return $items;
}
add_filter('woocommerce_account_menu_items', 'my_custom_account_menu_items');
function my_custom_account_endpoints() {
    add_rewrite_endpoint('add-product', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('my-products', EP_ROOT | EP_PAGES);
}
add_action('init', 'my_custom_account_endpoints');



//MEDIA LIBRARY ONLY BY CURRENT USER
function filter_media_library_by_user($query) {
    if (is_admin() && $query->get('post_type') === 'attachment') {
        $current_user_id = get_current_user_id();
        $query->set('author', $current_user_id);
    }
}
add_action('pre_get_posts', 'filter_media_library_by_user');



// ADD PRODUCT PAGE
function my_custom_account_add_product_content() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product_nonce'])) {
        if (!wp_verify_nonce($_POST['add_product_nonce'], 'add_product_action')) {
            echo '<div class="woocommerce-error">' . __('Nonce verification failed!', 'textdomain') . '</div>';
        } else {
            $product_name = sanitize_text_field($_POST['product_name']);
            $product_price = floatval($_POST['product_price']);
            $product_quantity = intval($_POST['product_quantity']);
            $product_description = $_POST['product_description']; // WYSIWYG
            $product_image_id = intval($_POST['product_image_id']); 

            if (empty($product_name) || $product_price <= 0 || $product_quantity < 0) {
                echo '<div class="woocommerce-error">' . __('Please provide valid product details.', 'textdomain') . '</div>';
            } else {
                $product = new WC_Product_Simple();
                $product->set_name($product_name);
                $product->set_price($product_price);
                $product->set_regular_price($product_price);
                $product->set_status('pending');
                $product->set_catalog_visibility('visible');
                $product->set_stock_quantity($product_quantity);
                $product->set_manage_stock(true);
                $product->set_stock_status('instock');
                $product->set_description($product_description);
                if ($product_image_id) {
                    $product->set_image_id($product_image_id);
                }

                $product_id = $product->save();

                if ($product_id) {
                    echo '<div class="woocommerce-message">' . __('Product added successfully and is pending review!', 'textdomain') . '</div>';
                } else {
                    echo '<div class="woocommerce-error">' . __('Failed to add product.', 'textdomain') . '</div>';
                }
            }
        }
    }

    //FORM
    ?>
    <h3><?php _e('Add New Product', 'textdomain'); ?></h3>
    <form method="post">
        <p>
            <label for="product_name"><?php _e('Name:', 'textdomain'); ?></label>
            <input type="text" name="product_name" id="product_name" required />
        </p>
        <p>
            <label for="product_price"><?php _e('Price:', 'textdomain'); ?></label>
            <input type="number" name="product_price" id="product_price" step="0.5" required />
        </p>
        <p>
            <label for="product_quantity"><?php _e('Quantity:', 'textdomain'); ?></label>
            <input type="number" name="product_quantity" id="product_quantity" step="1" min="0" required />
        </p>
        <p>
            <label for="product_description"><?php _e('Description:', 'textdomain'); ?></label>
            <?php
            //WYSIWYG
            $content = isset($_POST['product_description']) ? $_POST['product_description'] : '';
            $editor_settings = array(
                'textarea_name' => 'product_description',
                'textarea_rows' => 10,
                'editor_class' => 'product-description-editor',
                'editor_height' => 150,
                'media_buttons' => false,
            );
            wp_editor($content, 'product_description', $editor_settings);
            ?>
        </p>
        <p>
            <div>
                <button id="select_image_button" type="button" class="button">Select Image</button>   
                <input type="hidden" id="product_image_id" name="product_image_id" value="">
                <div id="product_image_preview" style="margin-top: 10px;"></div>
            </div>
        </p>
        
        <?php wp_nonce_field('add_product_action', 'add_product_nonce'); ?>
        <p>
            <button type="submit" class="button"><?php _e('Save Product', 'textdomain'); ?></button>
        </p>
    </form>
    <?php
}
add_action('woocommerce_account_add-product_endpoint', 'my_custom_account_add_product_content');

//SAVE IMAGE AS META
function save_custom_product_image($post_id) {
    if (isset($_POST['product_image_id'])) {
        $image_id = intval($_POST['product_image_id']);
        update_post_meta($post_id, '_product_image_id', $image_id);
    }
}
add_action('save_post', 'save_custom_product_image');

function my_custom_account_my_products_content() {
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        echo '<div class="woocommerce-error">' . __('You need to be logged in to view this page.', 'textdomain') . '</div>';
        return;
    }

    $args = [
        'post_type'      => 'product',
        'post_status'    => array('publish', 'pending'),
        'posts_per_page' => -1,
        'author'         => $user_id,
    ];

    $query = new WP_Query($args);
    
    if ($query->have_posts()) {
        echo '<h3>' . __('My Products', 'textdomain') . '</h3>';
        echo '<table class="my-products-table">
                <thead>
                    <tr>
                        <th>' . __('Product Name', 'textdomain') . '</th>
                        <th>' . __('Quantity', 'textdomain') . '</th>
                        <th>' . __('Price', 'textdomain') . '</th>
                        <th>' . __('Status', 'textdomain') . '</th>
                        <th>' . __('Actions', 'textdomain') . '</th>
                    </tr>
                </thead>
                <tbody>';

        while ($query->have_posts()) {
            $query->the_post();
            global $product;
            $product_id = get_the_ID();
            $product_name = get_the_title();
            $product_price = wc_price($product->get_price());
            $product_stock = $product->get_stock_quantity();
            $product_status = get_post_status($product_id);
            $edit_url = get_edit_post_link($product_id);
            echo "<tr>
                    <td><a href='" . get_permalink() . "'>$product_name</a></td>
                    <td>$product_stock</td>
                    <td>$product_price</td>
                    <td>$product_status</td>
                    <td>
                        <a href='$edit_url' class='button edit-btn'>" . __('Edit', 'textdomain') . "</a>
                        <a href='" . wp_nonce_url(admin_url('post.php?action=delete&post=' . $product_id), 'delete-post_' . $product_id) . "' class='button delete-btn' onclick='return confirm(\"" . __('Are you sure you want to delete this product?', 'textdomain') . "\");'>" . __('Delete', 'textdomain') . "</a>
                    </td>
                </tr>";
        }
        echo '</tbody></table>';
    } else {
        echo '<p>' . __('You have not added any products yet.', 'textdomain') . '</p>';
    }
    wp_reset_postdata();
}
add_action('woocommerce_account_my-products_endpoint', 'my_custom_account_my_products_content');





//ACTIVATION WITH CONDITION
function my_product_webspark_activate() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            'This plugin requires WooCommerce to be installed and activated. <br><a href="' . esc_url(admin_url('plugins.php')) . '">Go back</a>',
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }
    my_custom_account_endpoints();
    flush_rewrite_rules();
   
}
register_activation_hook(__FILE__, 'my_product_webspark_activate');

function my_product_webspark_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'my_product_webspark_deactivate');