jQuery(document).ready(function($) {
    // Calculate total amount
    function calculateTotal() {
        var total = 0;
        $('.eom-product-row').each(function() {
            var price = parseFloat($(this).data('price'));
            var quantity = parseInt($(this).find('.eom-quantity').val()) || 0;
            if (quantity > 0) {
                total += price * quantity;
            }
        });
        $('#eom-total-amount').text('$' + total.toFixed(2));
    }

    // Calculate total when quantity changes
    $(document).on('input', '.eom-quantity', function() {
        calculateTotal();
    });

    // Handle form submission
    $('#eom-order-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $('#eom-submit-order');
        var $message = $('#eom-form-message');
        
        // Basic validation
        var customerName = $('#eom-customer-name').val().trim();
        var customerEmail = $('#eom-customer-email').val().trim();
        
        if (!customerName) {
            showMessage('Please enter your name.', 'error');
            $('#eom-customer-name').focus();
            return;
        }
        
        if (!customerEmail || !isValidEmail(customerEmail)) {
            showMessage('Please enter a valid email address.', 'error');
            $('#eom-customer-email').focus();
            return;
        }
        
        // Collect product data
        var products = [];
        var hasProducts = false;
        
        $('.eom-product-row').each(function() {
            var quantity = parseInt($(this).find('.eom-quantity').val());
            if (quantity > 0) {
                hasProducts = true;
                products.push({
                    id: $(this).data('product-id'),
                    sku: $(this).data('sku'),
                    price: $(this).data('price'),
                    quantity: quantity
                });
            }
        });
        
        if (!hasProducts) {
            showMessage('Please select at least one product.', 'error');
            $('.eom-quantity').first().focus();
            return;
        }
        
        // Disable submit button during processing
        $submitBtn.prop('disabled', true).text('Submitting...');
        $message.hide();
        
        // Submit via AJAX
        $.ajax({
            url: eom_ajax.url,
            type: 'POST',
            data: {
                action: 'submit_elixir_order',
                nonce: eom_ajax.nonce,
                customer_name: customerName,
                customer_email: customerEmail,
                order_date: $('#eom-order-date').val(),
                products: products
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data, 'success');
                    $form[0].reset();
                    calculateTotal(); // Reset total to 0
                    
                    // Clear form after success
                    setTimeout(function() {
                        $message.fadeOut();
                    }, 5000);
                } else {
                    showMessage(response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('An error occurred. Please try again.', 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).text('Submit Order');
            }
        });
    });
    
    // Show message function
    function showMessage(message, type) {
        var $message = $('#eom-form-message');
        $message.removeClass('success error')
               .addClass(type)
               .html(message)
               .show();
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $message.offset().top - 100
        }, 500);
    }
    
    // Email validation function
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Initialize total calculation
    calculateTotal();
});