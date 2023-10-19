<?php
/**
* Plugin Name: Dokan Duplicate Product
* Plugin URI: https://www.linkedin.com/in/qasimzahoor/
* Description: Click to Copy product in ADD Product Form.
* Version: 1.0
* Author: Qasim Zahoor
* Author URI: https://www.linkedin.com/in/qasimzahoor/
**/

function add_copy_button()
{
    global $wp;
    ?>
        <a href="<?php echo home_url($wp->request);?>/?dokan_get_all" class="dokan-btn dokan-btn-theme">
            <i class="fa fa-product-hunt">&nbsp;</i>
            <?php esc_html_e( 'Copy Products', 'dokan-lite' ); ?>
        </a>    
    <?php

}

add_action('dokan_after_add_product_btn' , 'add_copy_button');

add_filter( 'dokan_pre_product_listing_args', 'dokan_listing_args' ,10, 2);

function dokan_listing_args($args, $get_data )
{
    if ( isset($_GET['dokan_get_all'])) :
        unset($args['author']);
        $args['post_status'] = 'publish';

    endif;

    return $args;
}
 
add_filter( 'dokan_product_row_actions', 'dokan_row_actions' ,15, 2);

function dokan_row_actions($row_action, $post)
{
    if ( isset($_GET['dokan_get_all'])) :
        unset($row_action['edit']);
        unset($row_action['delete']);
        unset($row_action['quick-edit']);
        unset($row_action['view']);

        $row_action['duplicate']['title'] = 'Copy Product';
        
    echo '
        <script>
            var field = document.getElementById("bulk-product-action-selector");
            field.setAttribute("style", "display:none;");
            var btn = document.getElementById("bulk-product-action");
            btn.setAttribute("style", "display:none;");
        </script>
    ';
    endif;

    return $row_action;
}

add_filter('dokan_get_template_part','qz_template_path',10 , 3);

function qz_template_path($template, $slug, $name )
{
    if(isset($_GET['dokan_get_all']) AND ($slug == 'products/listing-filter')) return;
    return $template;
}

add_action( 'template_redirect', 'handle_duplicate_product', 10 );

function handle_duplicate_product() {

    if ( ! is_user_logged_in() ) {
        return;
    }

    if ( dokan_get_option( 'vendor_duplicate_product', 'dokan_selling', 'on' ) == 'off' ) {
        return;
    }

    if ( ! dokan_is_user_seller( dokan_get_current_user_id() ) ) {
        return;
    }

    if ( ! apply_filters( 'dokan_vendor_can_duplicate_product', true ) ) {
        return;
    }

    if ( isset( $_GET['action'] ) && $_GET['action'] == 'dokan-duplicate-product' ) {
        $product_id = isset( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0;

        if ( !$product_id ) {
            wp_redirect( add_query_arg( array( 'message' => 'error' ), dokan_get_navigation_url( 'products' ) ) );
            return;
        }

        if ( !wp_verify_nonce( $_GET['_wpnonce'], 'dokan-duplicate-product' ) ) {
            wp_redirect( add_query_arg( array( 'message' => 'error' ), dokan_get_navigation_url( 'products' ) ) );
            return;
        }

        $wo_dup = new \WC_Admin_Duplicate_Product();

        // Compatibility for WC 3.0+
        if ( version_compare( WC_VERSION, '2.7', '>' ) ) {
            $product = wc_get_product( $product_id );
            $clone_product =  $wo_dup->product_duplicate( $product );
            $clone_product_id =  $clone_product->get_id();
        } else {
            $post = get_post( $product_id );
            $clone_product_id =  $wo_dup->duplicate_product( $post );
        }

        // If vendor is disabled, make product status pending
        if ( ! dokan_is_seller_enabled( dokan_get_current_user_id() ) ) {
            $product_status = 'pending';
        } else {
            $product_status = 'Draft';
        }

        wp_update_post( array( 'ID' => intval( $clone_product_id ), 'post_status' => $product_status ) );

        do_action( 'dokan_product_duplicate_after_save', $clone_product, $product );

        $redirect = apply_filters( 'dokan_redirect_after_product_duplicating', dokan_get_navigation_url( 'products' ), $product_id, $clone_product_id );
        wp_redirect( add_query_arg( array( 'message' => 'product_duplicated' ),  $redirect ) );
        exit;
    }
}