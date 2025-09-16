<?php
class EOM_Orders {

    public static function create_order($customer_name, $customer_email, $order_date, $total_amount, $products) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'eom_orders';
        $items_table = $wpdb->prefix . 'eom_order_items';

        // First, create the orders table if it doesn't exist
        self::create_orders_table();

        $wpdb->query('START TRANSACTION');

        // Insert main order record
        $order_data = array(
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'order_date' => $order_date,
            'total_amount' => $total_amount,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );

        $order_inserted = $wpdb->insert(
            $orders_table,
            $order_data,
            array('%s', '%s', '%s', '%f', '%s', '%s')
        );

        if (!$order_inserted) {
            error_log('EOM Order Error: Failed to insert order. ' . $wpdb->last_error);
            $wpdb->query('ROLLBACK');
            return false;
        }

        $order_id = $wpdb->insert_id;

        // Insert order items
        foreach ($products as $product) {
            $quantity = intval($product['quantity']);
            if ($quantity > 0) {
                // Get product details for the name
                $product_details = EOM_Database::get_product($product['id']);
                $product_name = $product_details ? $product_details->name : 'Unknown Product';
                
                $item_data = array(
                    'order_id' => $order_id,
                    'product_id' => intval($product['id']),
                    'product_sku' => sanitize_text_field($product['sku']),
                    'product_name' => $product_name,
                    'quantity' => $quantity,
                    'price' => floatval($product['price']),
                    'total' => floatval($product['price']) * $quantity,
                    'created_at' => current_time('mysql')
                );

                $item_inserted = $wpdb->insert(
                    $items_table,
                    $item_data,
                    array('%d', '%d', '%s', '%s', '%d', '%f', '%f', '%s')
                );

                if (!$item_inserted) {
                    error_log('EOM Order Error: Failed to insert order item. ' . $wpdb->last_error);
                    $wpdb->query('ROLLBACK');
                    return false;
                }
            }
        }

        $wpdb->query('COMMIT');
        return $order_id;
    }

    private static function create_orders_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_orders = $wpdb->prefix . 'eom_orders';
        $table_order_items = $wpdb->prefix . 'eom_order_items';

        // Check if tables already exist
        $orders_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_orders'") === $table_orders;
        $items_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_order_items'") === $table_order_items;

        if ($orders_table_exists && $items_table_exists) {
            return; // Tables already exist, no need to create
        }

        $sql_orders = "CREATE TABLE $table_orders (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            order_date datetime NOT NULL,
            total_amount decimal(10,2) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            notes text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $sql_order_items = "CREATE TABLE $table_order_items (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id mediumint(9) NOT NULL,
            product_id mediumint(9) NOT NULL,
            product_sku varchar(100) NOT NULL,
            product_name varchar(255) NOT NULL,
            quantity mediumint(9) NOT NULL,
            price decimal(10,2) NOT NULL,
            total decimal(10,2) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY product_id (product_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_orders);
        dbDelta($sql_order_items);
    }

    public static function get_all_orders() {
        global $wpdb;
        
        // Ensure table exists
        self::create_orders_table();
        
        $table = $wpdb->prefix . 'eom_orders';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    }

    public static function get_order_with_items($order_id) {
        global $wpdb;
        
        // Ensure tables exist
        self::create_orders_table();
        
        $orders_table = $wpdb->prefix . 'eom_orders';
        $items_table = $wpdb->prefix . 'eom_order_items';
        
        // Get order
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE id = %d", 
            $order_id
        ));

        if ($order) {
            // Get order items
            $order->items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $items_table WHERE order_id = %d ORDER BY id",
                $order_id
            ));
        }

        return $order;
    }

    public static function update_order_status($order_id, $status) {
        global $wpdb;
        
        // Ensure table exists
        self::create_orders_table();
        
        $table = $wpdb->prefix . 'eom_orders';
        
        $allowed_statuses = array('pending', 'processing', 'completed', 'cancelled');
        if (!in_array($status, $allowed_statuses)) {
            return false;
        }

        return $wpdb->update(
            $table,
            array('status' => $status),
            array('id' => $order_id),
            array('%s'),
            array('%d')
        );
    }

    public static function delete_order($order_id) {
        global $wpdb;
        
        // Ensure tables exist
        self::create_orders_table();
        
        $orders_table = $wpdb->prefix . 'eom_orders';
        $items_table = $wpdb->prefix . 'eom_order_items';

        $wpdb->query('START TRANSACTION');

        // Delete order items first
        $items_deleted = $wpdb->delete(
            $items_table,
            array('order_id' => $order_id),
            array('%d')
        );

        // Delete the order
        $order_deleted = $wpdb->delete(
            $orders_table,
            array('id' => $order_id),
            array('%d')
        );

        if ($order_deleted === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $wpdb->query('COMMIT');
        return true;
    }

    public static function get_order_stats() {
        global $wpdb;
        
        // Ensure table exists
        self::create_orders_table();
        
        $orders_table = $wpdb->prefix . 'eom_orders';
        
        $stats = array(
            'total_orders' => $wpdb->get_var("SELECT COUNT(*) FROM $orders_table"),
            'total_revenue' => $wpdb->get_var("SELECT COALESCE(SUM(total_amount), 0) FROM $orders_table WHERE status = 'completed'"),
            'pending_orders' => $wpdb->get_var("SELECT COUNT(*) FROM $orders_table WHERE status = 'pending'"),
            'completed_orders' => $wpdb->get_var("SELECT COUNT(*) FROM $orders_table WHERE status = 'completed'"),
            'cancelled_orders' => $wpdb->get_var("SELECT COUNT(*) FROM $orders_table WHERE status = 'cancelled'")
        );

        return $stats;
    }

    public static function get_recent_orders($limit = 5) {
        global $wpdb;
        
        // Ensure table exists
        self::create_orders_table();
        
        $table = $wpdb->prefix . 'eom_orders';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }

    public static function get_orders_by_status($status) {
        global $wpdb;
        
        // Ensure table exists
        self::create_orders_table();
        
        $table = $wpdb->prefix . 'eom_orders';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = %s ORDER BY created_at DESC",
            $status
        ));
    }

    public static function search_orders($search_term) {
        global $wpdb;
        
        // Ensure table exists
        self::create_orders_table();
        
        $table = $wpdb->prefix . 'eom_orders';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE customer_name LIKE %s 
                OR customer_email LIKE %s 
                OR id LIKE %s 
             ORDER BY created_at DESC",
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%'
        ));
    }

    public static function get_orders_by_date_range($start_date, $end_date) {
        global $wpdb;
        
        // Ensure table exists
        self::create_orders_table();
        
        $table = $wpdb->prefix . 'eom_orders';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE DATE(created_at) BETWEEN %s AND %s 
             ORDER BY created_at DESC",
            $start_date,
            $end_date
        ));
    }

    public static function get_total_revenue_by_date_range($start_date, $end_date) {
        global $wpdb;
        
        // Ensure table exists
        self::create_orders_table();
        
        $table = $wpdb->prefix . 'eom_orders';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_amount), 0) 
             FROM $table 
             WHERE status = 'completed' 
             AND DATE(created_at) BETWEEN %s AND %s",
            $start_date,
            $end_date
        ));
    }

    public static function get_order_items($order_id) {
        global $wpdb;
        
        // Ensure table exists
        self::create_orders_table();
        
        $table = $wpdb->prefix . 'eom_order_items';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE order_id = %d ORDER BY id",
            $order_id
        ));
    }

    public static function get_orders_by_product($product_id) {
        global $wpdb;
        
        // Ensure tables exist
        self::create_orders_table();
        
        $items_table = $wpdb->prefix . 'eom_order_items';
        $orders_table = $wpdb->prefix . 'eom_orders';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT o.* 
             FROM $orders_table o 
             INNER JOIN $items_table i ON o.id = i.order_id 
             WHERE i.product_id = %d 
             ORDER BY o.created_at DESC",
            $product_id
        ));
    }

    public static function get_customer_orders($customer_email) {
        global $wpdb;
        
        // Ensure table exists
        self::create_orders_table();
        
        $table = $wpdb->prefix . 'eom_orders';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE customer_email = %s ORDER BY created_at DESC",
            $customer_email
        ));
    }

    public static function get_top_products($limit = 10) {
        global $wpdb;
        
        // Ensure table exists
        self::create_orders_table();
        
        $items_table = $wpdb->prefix . 'eom_order_items';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, product_name, product_sku, 
                    SUM(quantity) as total_quantity, 
                    SUM(total) as total_revenue,
                    AVG(price) as avg_price
             FROM $items_table 
             GROUP BY product_id, product_name, product_sku 
             ORDER BY total_quantity DESC 
             LIMIT %d",
            $limit
        ));
    }

    public static function check_tables_exist() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'eom_orders',
            $wpdb->prefix . 'eom_order_items'
        );

        foreach ($tables as $table) {
            $result = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s", 
                $table
            ));
            
            if ($result !== $table) {
                return false;
            }
        }

        return true;
    }
}