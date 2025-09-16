<?php
class EOM_Admin_Menu {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_init', array($this, 'handle_product_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    public function add_menu_pages() {
        add_menu_page(
            'Elixir Orders',
            'Elixir Orders',
            'manage_options',
            'elixir-orders',
            array($this, 'render_orders_page'),
            'dashicons-cart',
            30
        );

        add_submenu_page(
            'elixir-orders',
            'Manage Products',
            'Products',
            'manage_options',
            'elixir-products',
            array($this, 'render_products_page')
        );

        add_submenu_page(
            'elixir-orders',
            'Import Products',
            'Import CSV',
            'manage_options',
            'elixir-import',
            array($this, 'render_import_page')
        );
    }

    public function enqueue_styles($hook) {
        if (strpos($hook, 'elixir-') !== false) {
            wp_enqueue_style('eom-admin-css', EOM_PLUGIN_URL . 'admin/css/admin.css', array(), EOM_PLUGIN_VERSION);
        }
    }

    public function handle_product_actions() {
        // Handle Add Product
        if (isset($_POST['add_product']) && check_admin_referer('eom_add_product', 'eom_nonce')) {
            $name = sanitize_text_field($_POST['product_name']);
            $sku = sanitize_text_field($_POST['product_sku']);
            $brand = sanitize_text_field($_POST['product_brand']);
            $category = sanitize_text_field($_POST['product_category']);
            $price = floatval($_POST['product_price']);
            $description = sanitize_text_field($_POST['product_description']);

            if (!empty($name) && !empty($sku) && $price > 0) {
                $result = EOM_Database::add_product($name, $sku, $price, $brand, $category, $description);
                if ($result) {
                    $this->add_notice('Product added successfully!', 'success');
                } else {
                    $this->add_notice('Error adding product. SKU might already exist.', 'error');
                }
            } else {
                $this->add_notice('Please fill all required fields correctly.', 'error');
            }
        }

        // Handle Delete Product
        if (isset($_POST['delete_product']) && check_admin_referer('eom_delete_product', 'eom_nonce')) {
            $product_id = intval($_POST['product_id']);
            if ($product_id > 0) {
                $result = EOM_Database::delete_product($product_id);
                if ($result) {
                    $this->add_notice('Product deleted successfully!', 'success');
                } else {
                    $this->add_notice('Error deleting product.', 'error');
                }
            }
        }

        // Handle CSV Import
        if (isset($_POST['import_products']) && check_admin_referer('eom_import_nonce', 'eom_nonce')) {
            if (!empty($_FILES['csv_file']['tmp_name'])) {
                $result = EOM_Database::import_products_from_csv($_FILES['csv_file']['tmp_name']);
                if ($result['success']) {
                    $this->add_notice(
                        "Import completed! {$result['imported']} new products imported, {$result['updated']} products updated, {$result['skipped']} rows skipped.",
                        'success'
                    );
                } else {
                    $this->add_notice('Error importing products: ' . $result['message'], 'error');
                }
            } else {
                $this->add_notice('Please select a CSV file to import.', 'error');
            }
        }

        // Handle CSV Export
        if (isset($_POST['export_products'])) {
            EOM_Database::export_products_to_csv();
        }
    }

    private function add_notice($message, $type = 'info') {
        add_action('admin_notices', function() use ($message, $type) {
            echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . $message . '</p></div>';
        });
    }

    public function render_orders_page() {
        $orders = EOM_Database::get_all_orders();
        ?>
        <div class="wrap">
            <h1>Orders - Elixir Graphic</h1>
            
            <div class="eom-section">
                <h2>All Customer Orders</h2>
                
                <?php if ($orders && count($orders) > 0) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer Name</th>
                                <th>Email</th>
                                <th>Order Date</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order) : ?>
                                <tr>
                                    <td><strong>#<?php echo $order->id; ?></strong></td>
                                    <td><?php echo esc_html($order->customer_name); ?></td>
                                    <td><?php echo esc_html($order->customer_email); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($order->order_date)); ?></td>
                                    <td>$<?php echo number_format($order->total_amount, 2); ?></td>
                                    <td>
                                        <span class="eom-status <?php echo esc_attr($order->status); ?>">
                                            <?php echo ucfirst($order->status); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <div class="notice notice-info">
                        <p>No orders found yet. Orders will appear here when customers submit them.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function render_products_page() {
        $products = EOM_Database::get_all_products();
        ?>
        <div class="wrap">
            <h1>Manage Products - Elixir Graphic</h1>
            
            <div class="eom-section">
                <h2>Add New Product</h2>
                <form method="post" class="eom-product-form">
                    <?php wp_nonce_field('eom_add_product', 'eom_nonce'); ?>
                    <div class="eom-form-row">
                        <input type="text" name="product_name" placeholder="Product Name *" required>
                        <input type="text" name="product_sku" placeholder="SKU Code *" required>
                        <input type="text" name="product_brand" placeholder="Brand">
                        <input type="text" name="product_category" placeholder="Category">
                        <input type="number" name="product_price" step="0.01" min="0.01" placeholder="Price ($) *" required>
                    </div>
                    <div class="eom-form-row">
                        <textarea name="product_description" placeholder="Product Description" rows="3"></textarea>
                        <input type="submit" name="add_product" class="button button-primary" value="Add Product">
                    </div>
                </form>
            </div>

            <div class="eom-section">
                <h2>Existing Products</h2>
                <?php if ($products) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Brand</th>
                                <th>Category</th>
                                <th>Price ($)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product) : ?>
                                <tr>
                                    <td><?php echo $product->id; ?></td>
                                    <td><?php echo esc_html($product->name); ?></td>
                                    <td><code><?php echo esc_html($product->sku); ?></code></td>
                                    <td><?php echo esc_html($product->brand); ?></td>
                                    <td><?php echo esc_html($product->category); ?></td>
                                    <td>$<?php echo number_format($product->price, 2); ?></td>
                                    <td>
                                        <span class="eom-status <?php echo $product->is_active ? 'active' : 'inactive'; ?>">
                                            <?php echo $product->is_active ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" class="eom-delete-form">
                                            <?php wp_nonce_field('eom_delete_product', 'eom_nonce'); ?>
                                            <input type="hidden" name="product_id" value="<?php echo $product->id; ?>">
                                            <input type="submit" name="delete_product" class="button button-small" 
                                                   value="Delete" onclick="return confirm('Are you sure?');">
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>No products found. Add your first product above.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function render_import_page() {
        ?>
        <div class="wrap">
            <h1>Import Products - Elixir Graphic</h1>
            
            <div class="eom-section">
                <h2>Import from CSV</h2>
                <p>Upload a CSV file with the following columns:</p>
                <ol>
                    <li><strong>Name</strong> (required)</li>
                    <li><strong>SKU</strong> (required, unique)</li>
                    <li><strong>Price</strong> (required, number)</li>
                    <li><strong>Brand</strong> (optional)</li>
                    <li><strong>Category</strong> (optional)</li>
                    <li><strong>Description</strong> (optional)</li>
                </ol>
                
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('eom_import_nonce', 'eom_nonce'); ?>
                    <p>
                        <label for="csv_file">Select CSV File:</label>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </p>
                    <p>
                        <input type="submit" name="import_products" class="button button-primary" value="Import Products">
                    </p>
                </form>
            </div>

            <div class="eom-section">
                <h2>Export Products</h2>
                <p>Export all products to a CSV file.</p>
                <form method="post">
                    <input type="submit" name="export_products" class="button button-primary" value="Export to CSV">
                </form>
            </div>
        </div>
        <?php
    }
}