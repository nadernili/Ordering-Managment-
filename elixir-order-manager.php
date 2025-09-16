<?php
/**
 * Plugin Name: Elixir Order Manager
 * Plugin URI:  https://elixirgraphic.com
 * Description: Product and order management system for Elixir Graphic
 * Version:     1.0.0
 * Author:      Nader Nilizadeh - Elixir Graphic
 * Author URI:  https://elixirgraphic.com
 * License:     GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: elixir-order-manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EOM_PLUGIN_VERSION', '1.0.0');
define('EOM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EOM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('EOM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class ElixirOrderManager {

    private $shortcode;

    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_action('plugins_loaded', array($this, 'init'));
    }

    public function activate() {
        // Load and setup database tables
        $this->load_file('includes/class-database.php');
        EOM_Database::setup_tables();
    }

    public function init() {
        load_plugin_textdomain('elixir-order-manager', false, dirname(EOM_PLUGIN_BASENAME) . '/languages');
        
        // Load all required files
        $this->load_dependencies();
        
        // Initialize components
        if (is_admin()) {
            new EOM_Admin_Menu();
        }
        
        // Initialize shortcode - THIS IS THE KEY FIX
        $this->shortcode = new EOM_Shortcode();
    }

    private function load_dependencies() {
        // Load core classes
        $this->load_file('includes/class-database.php');
        $this->load_file('includes/class-orders.php');
        $this->load_file('includes/class-emails.php');
        $this->load_file('includes/class-ajax-handler.php');
        
        // Load admin classes
        if (is_admin()) {
            $this->load_file('admin/class-admin-menu.php');
        }
        
        // Load public classes
        $this->load_file('public/class-shortcode.php');
        
        // Load AJAX handler
        new EOM_Ajax_Handler();
    }

    private function load_file($file_path) {
        $full_path = EOM_PLUGIN_PATH . $file_path;
        if (file_exists($full_path)) {
            require_once $full_path;
        }
    }
}

// Initialize the plugin
new ElixirOrderManager();