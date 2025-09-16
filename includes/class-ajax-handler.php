<?php
class EOM_Ajax_Handler {

    public function __construct() {
        add_action('wp_ajax_submit_elixir_order', array($this, 'handle_order_submission'));
        add_action('wp_ajax_nopriv_submit_elixir_order', array($this, 'handle_order_submission'));
    }

    public function handle_order_submission() {
        // Check nonce first
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'eom_order_nonce')) {
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            wp_die();
        }

        // Sanitize and validate input data
        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
        $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
        $order_date = isset($_POST['order_date']) ? sanitize_text_field($_POST['order_date']) : current_time('mysql');
        $products = isset($_POST['products']) ? $_POST['products'] : array();

        // Validate required fields
        if (empty($customer_name)) {
            wp_send_json_error('Please enter your name.');
            wp_die();
        }

        if (empty($customer_email) || !is_email($customer_email)) {
            wp_send_json_error('Please enter a valid email address.');
            wp_die();
        }

        // Validate products
        if (empty($products) || !is_array($products)) {
            wp_send_json_error('Please select at least one product.');
            wp_die();
        }

        // Calculate total amount
        $total_amount = 0;
        $valid_products = array();
        
        foreach ($products as $product) {
            $quantity = intval($product['quantity']);
            $price = floatval($product['price']);
            
            if ($quantity > 0 && $price > 0) {
                $total_amount += $price * $quantity;
                $valid_products[] = array(
                    'id' => intval($product['id']),
                    'sku' => sanitize_text_field($product['sku']),
                    'price' => $price,
                    'quantity' => $quantity
                );
            }
        }

        if ($total_amount <= 0) {
            wp_send_json_error('Please select valid products and quantities.');
            wp_die();
        }

        // Save the order
        $order_id = EOM_Orders::create_order($customer_name, $customer_email, $order_date, $total_amount, $valid_products);
        
        if ($order_id) {
            // Send email notification
            EOM_Emails::send_admin_notification($order_id, $customer_name, $customer_email, $order_date, $valid_products, $total_amount);
            
            wp_send_json_success('Your order has been submitted successfully! Order ID: #' . $order_id . '. We will contact you soon.');
        } else {
            wp_send_json_error('Error saving your order. Please try again.');
        }

        wp_die();
    }
}