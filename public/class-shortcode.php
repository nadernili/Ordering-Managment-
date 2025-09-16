<?php
class EOM_Shortcode {

    public function __construct() {
        add_shortcode('elixir_order_form', array($this, 'render_order_form'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        if (!is_admin()) {
            wp_enqueue_style('eom-public-css', EOM_PLUGIN_URL . 'public/css/public.css', array(), EOM_PLUGIN_VERSION);
            wp_enqueue_script('eom-public-js', EOM_PLUGIN_URL . 'public/js/public.js', array('jquery'), EOM_PLUGIN_VERSION, true);
            
            wp_localize_script('eom-public-js', 'eom_ajax', array(
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('eom_order_nonce')
            ));
        }
    }

    public function render_order_form() {
        if (!class_exists('EOM_Database')) {
            return '<div class="eom-error">Error: Database class not loaded. Please contact administrator.</div>';
        }
        
        ob_start();
        try {
            // Get filters from URL parameters
            $selected_brand = isset($_GET['brand']) ? sanitize_text_field($_GET['brand']) : '';
            $selected_category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
            
            // Get products with filters
            $products = EOM_Database::get_products_by_filters($selected_brand, $selected_category);
            $brands = EOM_Database::get_brands();
            $categories = EOM_Database::get_categories();
            ?>
            <div class="eom-order-form-container">
                <h2>Place Your Order - Elixir Graphic</h2>
                
                <!-- Filter Section -->
                <div class="eom-filters">
                    <h3>Filter Products</h3>
                    <form method="get" class="eom-filter-form">
                        <div class="eom-filter-row">
                            <div class="eom-form-group">
                                <label for="eom-filter-brand">Brand</label>
                                <select id="eom-filter-brand" name="brand" onchange="this.form.submit()">
                                    <option value="">All Brands</option>
                                    <?php foreach ($brands as $brand) : ?>
                                        <option value="<?php echo esc_attr($brand->brand); ?>" 
                                            <?php selected($selected_brand, $brand->brand); ?>>
                                            <?php echo esc_html($brand->brand); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="eom-form-group">
                                <label for="eom-filter-category">Category</label>
                                <select id="eom-filter-category" name="category" onchange="this.form.submit()">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category) : ?>
                                        <option value="<?php echo esc_attr($category->category); ?>" 
                                            <?php selected($selected_category, $category->category); ?>>
                                            <?php echo esc_html($category->category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="eom-form-group">
                                <label>&nbsp;</label>
                                <a href="?" class="button">Clear Filters</a>
                            </div>
                        </div>
                    </form>
                </div>

                <form id="eom-order-form">
                    <div class="eom-customer-info">
                        <h3>Customer Information</h3>
                        
                        <div class="eom-form-group">
                            <label for="eom-customer-name">Full Name *</label>
                            <input type="text" id="eom-customer-name" name="customer_name" required>
                        </div>
                        
                        <div class="eom-form-group">
                            <label for="eom-customer-email">Email Address *</label>
                            <input type="email" id="eom-customer-email" name="customer_email" required>
                        </div>
                        
                        <div class="eom-form-group">
                            <label for="eom-order-date">Order Date</label>
                            <input type="date" id="eom-order-date" name="order_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="eom-order-items">
                        <h3>Order Items</h3>
                        
                        <?php if ($products && count($products) > 0) : ?>
                            <table id="eom-order-table">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Brand</th>
                                        <th>Category</th>
                                        <th>SKU</th>
                                        <th>Price ($)</th>
                                        <th>Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product) : ?>
                                        <tr class="eom-product-row" 
                                            data-product-id="<?php echo $product->id; ?>" 
                                            data-sku="<?php echo esc_attr($product->sku); ?>" 
                                            data-price="<?php echo $product->price; ?>">
                                            <td><?php echo esc_html($product->name); ?></td>
                                            <td><?php echo esc_html($product->brand); ?></td>
                                            <td><?php echo esc_html($product->category); ?></td>
                                            <td><?php echo esc_html($product->sku); ?></td>
                                            <td class="eom-price">$<?php echo number_format($product->price, 2); ?></td>
                                            <td>
                                                <input type="number" class="eom-quantity" 
                                                       name="quantity[<?php echo $product->id; ?>]" 
                                                       value="0" min="0" step="1">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" class="eom-total-label">
                                            <strong>Total Amount:</strong>
                                        </td>
                                        <td id="eom-total-amount">$0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php else : ?>
                            <p class="eom-no-products">No products found matching your filters. 
                                <a href="?">Clear filters</a> to see all products.
                            </p>
                        <?php endif; ?>
                    </div>

                    <button type="submit" id="eom-submit-order" class="eom-submit-btn">
                        Submit Order
                    </button>
                    
                    <div id="eom-form-message"></div>
                </form>
            </div>
            <?php
        } catch (Exception $e) {
            return '<div class="eom-error">Error loading order form: ' . $e->getMessage() . '</div>';
        }
        return ob_get_clean();
    }
}