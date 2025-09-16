<?php
class EOM_Database {

    public static function setup_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Products table
        $table_products = $wpdb->prefix . 'eom_products';
        $sql_products = "CREATE TABLE $table_products (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            sku varchar(100) NOT NULL UNIQUE,
            brand varchar(100) NULL,
            category varchar(100) NULL,
            price decimal(10,2) NOT NULL,
            is_active boolean DEFAULT TRUE,
            description text NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Orders table
        $table_orders = $wpdb->prefix . 'eom_orders';
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

        // Order items table
        $table_order_items = $wpdb->prefix . 'eom_order_items';
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
        dbDelta($sql_products);
        dbDelta($sql_orders);
        dbDelta($sql_order_items);

        // Insert sample products
        self::insert_sample_products();
    }

    private static function insert_sample_products() {
        global $wpdb;
        $table = $wpdb->prefix . 'eom_products';
        
        // Check if products already exist
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($existing > 0) {
            return;
        }

        $sample_products = array(
            array('Premium Business Cards', 'BC-PREM-001', 'Elixir Premium', 'Business Cards', 89.99, 'High-quality premium business cards'),
            array('Standard Business Cards', 'BC-STD-002', 'Elixir Standard', 'Business Cards', 49.99, 'Standard quality business cards'),
            array('Glossy Flyers A4', 'FLY-GLOSS-003', 'Elixir Premium', 'Flyers', 129.99, 'A4 glossy flyers for promotions'),
            array('Matte Flyers A5', 'FLY-MATTE-004', 'Elixir Standard', 'Flyers', 89.99, 'A5 matte finish flyers'),
            array('Brochures Tri-Fold', 'BR-TF-005', 'Elixir Premium', 'Brochures', 199.99, 'Tri-fold brochures'),
            array('Posters A2', 'POST-A2-006', 'Elixir Premium', 'Posters', 149.99, 'Large A2 posters'),
            array('Posters A3', 'POST-A3-007', 'Elixir Standard', 'Posters', 99.99, 'A3 posters for displays'),
            array('Letterheads', 'LH-008', 'Elixir Premium', 'Stationery', 69.99, 'Professional letterheads'),
            array('Envelopes', 'ENV-009', 'Elixir Standard', 'Stationery', 59.99, 'Matching envelopes')
        );

        foreach ($sample_products as $product) {
            $wpdb->insert(
                $table,
                array(
                    'name' => $product[0],
                    'sku' => $product[1],
                    'brand' => $product[2],
                    'category' => $product[3],
                    'price' => $product[4],
                    'description' => $product[5]
                ),
                array('%s', '%s', '%s', '%s', '%f', '%s')
            );
        }
    }

    // Get all active products
    public static function get_products($active_only = true) {
        global $wpdb;
        $table = $wpdb->prefix . 'eom_products';
        $where = $active_only ? "WHERE is_active = TRUE" : "";
        return $wpdb->get_results("SELECT * FROM $table $where ORDER BY name");
    }

    // Get all products (active and inactive)
    public static function get_all_products() {
        return self::get_products(false);
    }

    // Get a single product by ID
    public static function get_product($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'eom_products';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    // Get product by SKU
    public static function get_product_by_sku($sku) {
        global $wpdb;
        $table = $wpdb->prefix . 'eom_products';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE sku = %s", $sku));
    }

    // Get all brands
    public static function get_brands() {
        global $wpdb;
        $table = $wpdb->prefix . 'eom_products';
        return $wpdb->get_results("SELECT DISTINCT brand FROM $table WHERE brand IS NOT NULL AND brand != '' AND is_active = TRUE ORDER BY brand");
    }

    // Get all categories
    public static function get_categories() {
        global $wpdb;
        $table = $wpdb->prefix . 'eom_products';
        return $wpdb->get_results("SELECT DISTINCT category FROM $table WHERE category IS NOT NULL AND category != '' AND is_active = TRUE ORDER BY category");
    }

    // Get products by filters
    public static function get_products_by_filters($brand = '', $category = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'eom_products';
        
        $where = "WHERE is_active = TRUE";
        $prepare_args = array();
        
        if (!empty($brand)) {
            $where .= " AND brand = %s";
            $prepare_args[] = $brand;
        }
        
        if (!empty($category)) {
            $where .= " AND category = %s";
            $prepare_args[] = $category;
        }
        
        $query = "SELECT * FROM $table $where ORDER BY name";
        
        if (!empty($prepare_args)) {
            return $wpdb->get_results($wpdb->prepare($query, $prepare_args));
        }
        
        return $wpdb->get_results($query);
    }

    // Add new product
    public static function add_product($name, $sku, $price, $brand = '', $category = '', $description = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'eom_products';
        
        // Check if SKU already exists
        $existing = self::get_product_by_sku($sku);
        if ($existing) {
            return false;
        }

        return $wpdb->insert(
            $table,
            array(
                'name' => $name,
                'sku' => $sku,
                'brand' => $brand,
                'category' => $category,
                'price' => $price,
                'description' => $description
            ),
            array('%s', '%s', '%s', '%s', '%f', '%s')
        );
    }

    // Delete product
    public static function delete_product($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'eom_products';
        
        return $wpdb->delete(
            $table,
            array('id' => $id),
            array('%d')
        );
    }

    // CSV Import
    public static function import_products_from_csv($file_path) {
        global $wpdb;
        $table = $wpdb->prefix . 'eom_products';
        
        if (!file_exists($file_path)) {
            return array('success' => false, 'message' => 'File does not exist.');
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return array('success' => false, 'message' => 'Cannot open file.');
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        
        // Skip header row
        fgetcsv($handle);
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < 3) continue;
            
            $name = sanitize_text_field($data[0]);
            $sku = sanitize_text_field($data[1]);
            $price = floatval($data[2]);
            $brand = isset($data[3]) ? sanitize_text_field($data[3]) : '';
            $category = isset($data[4]) ? sanitize_text_field($data[4]) : '';
            $description = isset($data[5]) ? sanitize_text_field($data[5]) : '';
            
            if (empty($name) || empty($sku) || $price <= 0) {
                $skipped++;
                continue;
            }
            
            // Check if SKU exists
            $existing = self::get_product_by_sku($sku);
            
            if ($existing) {
                // Update existing product
                $result = $wpdb->update(
                    $table,
                    array(
                        'name' => $name,
                        'brand' => $brand,
                        'category' => $category,
                        'price' => $price,
                        'description' => $description
                    ),
                    array('id' => $existing->id),
                    array('%s', '%s', '%s', '%f', '%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                // Insert new product
                $result = $wpdb->insert(
                    $table,
                    array(
                        'name' => $name,
                        'sku' => $sku,
                        'brand' => $brand,
                        'category' => $category,
                        'price' => $price,
                        'description' => $description
                    ),
                    array('%s', '%s', '%s', '%s', '%f', '%s')
                );
                
                if ($result) {
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        }
        
        fclose($handle);
        
        return array(
            'success' => true,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => $imported + $updated
        );
    }

    // CSV Export
    public static function export_products_to_csv() {
        $products = self::get_all_products();
        
        if (empty($products)) {
            return false;
        }

        $filename = 'elixir-products-export-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Header row
        fputcsv($output, array('Name', 'SKU', 'Brand', 'Category', 'Price', 'Description', 'Status'));
        
        // Data rows
        foreach ($products as $product) {
            fputcsv($output, array(
                $product->name,
                $product->sku,
                $product->brand,
                $product->category,
                $product->price,
                $product->description,
                $product->is_active ? 'Active' : 'Inactive'
            ));
        }
        
        fclose($output);
        exit;
    }

    // Create order
    public static function create_order($customer_name, $customer_email, $order_date, $total_amount, $products) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'eom_orders';
        $items_table = $wpdb->prefix . 'eom_order_items';

        $wpdb->query('START TRANSACTION');

        // Insert main order
        $order_inserted = $wpdb->insert(
            $orders_table,
            array(
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'order_date' => $order_date,
                'total_amount' => $total_amount
            ),
            array('%s', '%s', '%s', '%f')
        );

        if (!$order_inserted) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $order_id = $wpdb->insert_id;

        // Insert order items
        foreach ($products as $product) {
            $quantity = intval($product['quantity']);
            if ($quantity > 0) {
                $product_details = self::get_product($product['id']);
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
                    $wpdb->query('ROLLBACK');
                    return false;
                }
            }
        }

        $wpdb->query('COMMIT');
        return $order_id;
    }

    // Get all orders
    public static function get_all_orders() {
        global $wpdb;
        $table = $wpdb->prefix . 'eom_orders';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    }

    // Get order with items
    public static function get_order_with_items($order_id) {
        global $wpdb;
        
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
}