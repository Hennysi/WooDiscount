<?php
    /*
    Plugin Name: Test WooDiscount
    Description: A test plugin for ArtMyWeb.
    Version: 1.0
    Author: Rostislav Demenko
    License: GPL2
    */

    class Woo_Discount {
        public function __construct() {
            add_action( 'admin_menu', array( $this, 'add_woo_discount_page' ) );

            add_action( 'wp_enqueue_scripts', array( $this, 'woo_discount_enqueue' ) );

            add_action( 'woocommerce_before_calculate_totals', array( $this, 'woo_discount_cart' ) );
            add_filter( 'woocommerce_cart_item_name', array( $this, 'woo_discount_free_select' ), 10, 3 );
            add_filter( 'woocommerce_cart_updated', array( $this, 'woo_discount_cart_update' ) );
            add_filter( 'woocommerce_cart_item_quantity', array( $this, 'woo_discount_set_max_qty' ), 10, 3 );

            add_action( 'wp_ajax_woo_add', array( $this, 'woo_discount_add_to_cart' ) );
            add_action( 'wp_ajax_nopriv_woo_add', array( $this, 'woo_discount_add_to_cart' ) );
        }

        public function woo_discount_enqueue() {
            wp_enqueue_style( 'woo_discount_style', plugin_dir_url( __FILE__ ) . 'css/style.css' );
            wp_enqueue_script( 'woo_discount_scripts', plugin_dir_url( __FILE__ ) . 'js/script.js', false, false, true );

            wp_localize_script( 'woo_discount_scripts', 'woo_discount', array(
                'url'   => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'project_nonce' )
            ) );
        }

        public function add_woo_discount_page() {
            add_submenu_page( 'edit.php?post_type=product', 'Woo Discount', 'Woo Discount', 'manage_options', 'woo-discount', array(
                $this,
                'woo_discount_callback'
            ) );
        }

        public function woo_discount_callback() {
            if ( isset( $_POST['submit'] ) ) {
                if ( isset( $_POST['woo_discount_category'] ) && isset( $_POST['woo_discount_count'] ) && isset( $_POST['woo_discount_free'] ) && wp_verify_nonce( $_POST['woo_discount_nonce'], 'woo_discount_save_data' ) ) {
                    $discount_category = sanitize_text_field( $_POST['woo_discount_category'] );
                    $discount_count = sanitize_text_field( $_POST['woo_discount_count'] );
                    $discount_free = sanitize_text_field( $_POST['woo_discount_free'] );

                    update_option( 'woo_discount_category', $discount_category );
                    update_option( 'woo_discount_count', $discount_count );
                    update_option( 'woo_discount_free', $discount_free );

                    echo '<div class="notice notice-success"><p>Successfully saved.</p></div>';
                }
            }

            $product_categories = get_terms( 'product_cat' );

            $woo_discount_category = get_option( 'woo_discount_category' );
            $woo_discount_count = get_option( 'woo_discount_count' );
            $woo_discount_free = get_option( 'woo_discount_free' );
            ?>
            <div class="wrap">
                <h2><?php _e( 'Woo Discount', 'woo-discount' ); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field( 'woo_discount_save_data', 'woo_discount_nonce' ); ?>
                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th scope="row">
                                <label for="woo_discount_category"><?php _e( 'Discount Category', 'woo-discount' ); ?></label>
                            </th>
                            <td>
                                <select name="woo_discount_category" id="woo_discount_category">
                                    <?php foreach ( $product_categories as $category ): ?>
                                        <option value="<?php echo $category->slug ?>" <?php selected( $woo_discount_category, $category->slug ); ?>><?php echo $category->name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_discount_count"><?php _e( 'Discount Products Count', 'woo-discount' ); ?></label>
                            </th>
                            <td>
                                <input type="number" name="woo_discount_count" id="woo_discount_count" value="<?php echo $woo_discount_count ? : 1 ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_discount_free"><?php _e( 'Free Product', 'woo-discount' ); ?></label>
                            </th>
                            <td>
                                <select name="woo_discount_free" id="woo_discount_free">
                                    <?php foreach ( $product_categories as $category ): ?>
                                        <option value="<?php echo $category->slug ?>" <?php selected( $woo_discount_free, $category->slug ); ?>><?php echo $category->name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Save Changes', 'woo-discount' ); ?>">
                    </p>
                </form>
            </div>
            <?php
        }

        public function woo_discount_cart() {
            $woo_discount_category = get_option( 'woo_discount_category' );
//            $woo_discount_free = get_option( 'woo_discount_free' );

            $last_product = null;

            $cart = WC()->cart;
            $cart_items = $cart->get_cart();

            $categoryItemCount = 0;

            foreach ( $cart_items as $cart_item_key => $cart_item ) {
                $terms = get_the_terms( $cart_item['data']->id, 'product_cat' );

                foreach ( $terms as $term ) {
                    if ( $term->slug === $woo_discount_category ) {
                        $categoryItemCount += $cart_item['quantity'];
                        $last_product = $cart_item['data'];
                    }
                }
            }

            $woo_discount_free_products = wc_get_products( [
                'category' => $woo_discount_free
            ] );

            $free_products_arr = [];

            if ( $woo_discount_free_products ) {
                foreach ( $woo_discount_free_products as $product ) {
                    $free_products_arr[] = $product->get_id();
                }
            }

            foreach ( $cart_items as $cart_item_key => $cart_item ) {
                $product = $cart_item['data'];

                if ( in_array( $product->get_id(), $free_products_arr ) ) {
                    $cart_item['data']->set_price( 0 );
                }
            }

            $woo_discount_array = [
                'items_count'   => $categoryItemCount,
                'free_products' => $free_products_arr,
                'last_product'  => $last_product
            ];

            return $woo_discount_array;
        }

        public function woo_discount_cart_update() {
            add_filter( 'woocommerce_cart_item_name', array( $this, 'woo_discount_free_select' ), 10, 3 );
        }

        public function woo_discount_free_select( $product_name, $cart_item, $cart_item_key ) {
            $woo_discount_category = get_option( 'woo_discount_category' );
            $woo_discount_count = get_option( 'woo_discount_count' );
            $woo_discount_free = get_option( 'woo_discount_free' );

            $woo_discount_free_products = wc_get_products( [
                'category' => $woo_discount_free
            ] );

            $cart_count = $this->woo_discount_cart();
            $product = $cart_item['data'];

            $cart = WC()->cart;
            $cart_items = $cart->get_cart();
            $product_ids = array();

            foreach ( $cart_items as $cart_item_key => $cart_item ) {
                $product_ids[] = $cart_item['product_id'];
            }

            $is_variable = get_post_type( $product->get_id() ) === 'product_variation';

            if ( $is_variable ) {
                $product_id = wc_get_product( $product->get_id() )->get_parent_id();
            } else {
                $product_id = $product->get_id();
            }

            if ( count( array_intersect( $product_ids, $cart_count['free_products'] ) ) === 0 ) {
                if ( $cart_count['items_count'] >= $woo_discount_count ) {
                    if ( has_term( $woo_discount_category, 'product_cat', $product_id ) ) {
                        if ( $product->get_id() === $cart_count['last_product']->get_id() ) {
                            ob_start();
                            ?>
                            <div class="woo_discount_free_select">
                                <div class="selected-option"><?php _e( "Select Free Product ˅", "woo-discount" ) ?></div>
                                <div class="options">
                                    <?php foreach ( $woo_discount_free_products as $product ): ?>
                                        <div class="option">
                                            <a href="#" class="option-product" data-id="<?php echo $product->get_id() ?>"><?php echo $product->get_name() ?></a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php
                            $select_html = ob_get_clean();
                            $product_name .= '<br>' . $select_html;
                        }
                    }
                }
            }

            return $product_name;
        }

        public function woo_discount_set_max_qty( $product_quantity, $cart_item_key, $cart_item ) {
            $cart_count = $this->woo_discount_cart();

            if ( in_array( $cart_item['product_id'], $cart_count['free_products'] ) ) {
                $product_quantity = 1;
            }

            return $product_quantity;
        }

        public function woo_discount_add_to_cart() {
            $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
            $quantity = 1;

            WC()->cart->add_to_cart( $product_id, $quantity );

            wp_send_json_success( array( 'message' => 'Товар добавлен в корзину.' ) );
        }
    }

    $woo_discount = new Woo_Discount();
?>
