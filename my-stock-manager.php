<?php
/**
 * Plugin Name: My Stock Manager
 * Description: Enhanced stock management for WooCommerce with SKU-based stock adjustment, history tracking including product titles, and restricted access for Admins and Shop Managers.
 * Version: 1.0
 * Author: Chris Kotza
 * License: GPLv3 or later
*  License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */


 function my_stock_manager_enqueue_styles() {
    wp_enqueue_style('my-stock-manager-styles', plugin_dir_url(__FILE__) . 'my-stock-manager-styles.css');
}
add_action('admin_enqueue_scripts', 'my_stock_manager_enqueue_styles');


// Hook for adding admin menus
add_action('admin_menu', 'my_stock_manager_menu');

// Action function for the above hook with user capability check
function my_stock_manager_menu() {
    if (current_user_can('manage_options') || current_user_can('manage_woocommerce')) {
        add_submenu_page('woocommerce', 'Stock Manager', 'Stock Manager', 'manage_options', 'stock-manager', 'my_stock_manager_page');
    }
}

// Display the stock manager page
function my_stock_manager_page() {
    ?>
    <div class="wrap">
        <h2>Stock Manager</h2>
        <form method="post" action="">
            <?php wp_nonce_field('my_stock_manager_action', 'my_stock_manager_nonce'); ?>
            <table class="form-table" id="stock_manager_table">
                <thead>
                    <tr>
                        <th>Product SKU</th>
                        <th>Quantity to Remove</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input type="text" name="product_sku[]" required /></td>
                        <td><input type="number" name="stock_quantity[]" value="1" required /></td>
                        <td><button type="button" class="button button-secondary" onclick="addRow()">Add More</button></td>
                    </tr>
                </tbody>
            </table>
            <input type="submit" class="button-primary" value="Update Stock"/>
        </form>
        <?php display_stock_update_history(); ?>
    </div>
    <script type="text/javascript">
    function addRow() {
        var table = document.getElementById("stock_manager_table").getElementsByTagName('tbody')[0];
        var newRow = table.insertRow(table.rows.length);
        var cell1 = newRow.insertCell(0);
        var cell2 = newRow.insertCell(1);
        var cell3 = newRow.insertCell(2);

        cell1.innerHTML = '<input type="text" name="product_sku[]" required>';
        cell2.innerHTML = '<input type="number" name="stock_quantity[]" required>';
        cell3.innerHTML = '<button type="button" class="button button-secondary" onclick="removeRow(this)">Remove</button>';
    }

    function removeRow(button) {
        button.parentElement.parentElement.remove();
    }
    </script>
    <style>
        .form-table th, .form-table td {
            padding: 15px;
        }
        .form-table {
            border-collapse: separate;
            border-spacing: 0 10px; /* Adjust spacing here */
        }
        .history-table th, .history-table td {
            padding: 10px;
        }
        .history-table {
            margin-top: 20px;
            border-collapse: separate;
            border-spacing: 0 15px; /* Spacing between history rows */
        }
    </style>
    <?php
}

// Function to display stock update history
function display_stock_update_history() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_manager_history';

    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC LIMIT 10");
    if ($results) {
        echo '<h3>Stock Update History</h3>';
        echo '<table class="history-table">';
        echo '<thead><tr><th>Date</th><th>Time</th><th>SKU</th><th>Product Title</th><th>Quantity</th></tr></thead>';
        foreach ($results as $row) {
            $datetime = new DateTime($row->time);
            $product = wc_get_product(wc_get_product_id_by_sku($row->sku));
            $product_title = $product ? $product->get_name() : 'N/A';
            echo '<tr><td>' . $datetime->format('d/m/Y') . '</td><td>' . $datetime->format('H:i') . '</td><td>' . $row->sku . '</td><td>' . $product_title . '</td><td>-' . $row->quantity . '</td></tr>';
        }
        echo '</table>';
    }
}

// Handle form submission
add_action('admin_init', 'handle_stock_update');
function handle_stock_update() {
    if (!isset($_POST['my_stock_manager_nonce'], $_POST['product_sku'], $_POST['stock_quantity']) 
        || !wp_verify_nonce($_POST['my_stock_manager_nonce'], 'my_stock_manager_action')) {
        return;
    }

    $skus = $_POST['product_sku'];
    $quantities = $_POST['stock_quantity'];

    foreach ($skus as $index => $sku) {
        $product_id = wc_get_product_id_by_sku(sanitize_text_field($sku));
        $quantity = intval($quantities[$index]);

        if ($product_id) {
            $product = wc_get_product($product_id);
            $current_stock = $product->get_stock_quantity();
            $new_stock = $current_stock - $quantity;
            $new_stock = $new_stock > 0 ? $new_stock : 0;

            $product->set_stock_quantity($new_stock);
            $product->save();

            // Record this action
            global $wpdb;
            $table_name = $wpdb->prefix . 'stock_manager_history';
            $wpdb->insert($table_name, array(
                'time' => current_time('mysql'),
                'sku' => $sku,
                'quantity' => $quantity
            ));
        }
    }
}

// Create table for history on plugin activation
register_activation_hook(__FILE__, 'my_stock_manager_install');
function my_stock_manager_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'stock_manager_history';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        sku varchar(100) NOT NULL,
        quantity mediumint(9) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
