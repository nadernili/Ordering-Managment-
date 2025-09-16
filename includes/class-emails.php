<?php
class EOM_Emails {

    public static function send_admin_notification($order_id, $customer_name, $customer_email, $order_date, $products, $total_amount) {
        $to = get_option('admin_email');
        $subject = 'New Order Received - Elixir Graphic #' . $order_id;
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Build the email body
        $message = "<h2>New Order Submission</h2>";
        $message .= "<p><strong>Order ID:</strong> #$order_id</p>";
        $message .= "<p><strong>Customer Name:</strong> $customer_name</p>";
        $message .= "<p><strong>Customer Email:</strong> $customer_email</p>";
        $message .= "<p><strong>Order Date:</strong> $order_date</p>";
        $message .= "<h3>Order Details:</h3>";
        $message .= "<table style='width: 100%; border-collapse: collapse;'>";
        $message .= "<tr style='background-color: #f8f9fa;'>";
        $message .= "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>Product</th>";
        $message .= "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>SKU</th>";
        $message .= "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: right;'>Price</th>";
        $message .= "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: center;'>Qty</th>";
        $message .= "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: right;'>Total</th>";
        $message .= "</tr>";
        
        foreach ($products as $product) {
            if ($product['quantity'] > 0) {
                $line_total = floatval($product['price']) * intval($product['quantity']);
                $message .= "<tr>";
                $message .= "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . esc_html($product['sku']) . "</td>";
                $message .= "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . esc_html($product['sku']) . "</td>";
                $message .= "<td style='padding: 10px; border: 1px solid #dee2e6; text-align: right;'>$" . number_format($product['price'], 2) . "</td>";
                $message .= "<td style='padding: 10px; border: 1px solid #dee2e6; text-align: center;'>" . intval($product['quantity']) . "</td>";
                $message .= "<td style='padding: 10px; border: 1px solid #dee2e6; text-align: right;'>$" . number_format($line_total, 2) . "</td>";
                $message .= "</tr>";
            }
        }
        
        $message .= "</table>";
        $message .= "<p style='font-size: 18px; font-weight: bold; margin-top: 20px;'>";
        $message .= "Total Amount: $" . number_format($total_amount, 2);
        $message .= "</p>";

        return wp_mail($to, $subject, $message, $headers);
    }
}