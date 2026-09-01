/**
 * Booking Services - Handles service line management in booking modal
 * Supports search for products and staff
 */
$(document).ready(function() {
    var serviceLineIndex = 0;
    
    // Read data lazily from window (set by blade view)
    function getProducts() {
        return (typeof window.bookingServiceProducts !== 'undefined') ? window.bookingServiceProducts : [];
    }
    
    function getStaff() {
        return (typeof window.bookingServiceStaff !== 'undefined') ? window.bookingServiceStaff : [];
    }
    
    // Add new service line
    $(document).on('click', '#add_service_line', function() {
        addServiceLine();
    });
    
    // Remove service line
    $(document).on('click', '.remove-service-line', function() {
        if ($('.service-line-row').length > 1) {
            $(this).closest('tr').remove();
            updateLineNumbers();
            calculateTotal();
        } else {
            toastr.warning('At least one service line is required.');
        }
    });
    
    // Product selection change - update price
    $(document).on('change', '.service-product', function() {
        var row = $(this).closest('tr');
        var productId = $(this).val();
        var products = getProducts();
        
        if (productId) {
            var product = products.find(p => p.id == productId);
            if (product) {
                var price = parseFloat(product.default_sell_price) || 0;
                row.find('.service-price').val(price.toFixed(2));
                row.find('.is_flexible_input').val(product.is_flexible_price);
                
                // Make price editable only if flexible
                if (product.is_flexible_price == 1) {
                    row.find('.service-price').removeAttr('readonly').removeClass('readonly');
                } else {
                    row.find('.service-price').attr('readonly', true).addClass('readonly');
                }
                
                updateLineTotal(row);
            }
        }
    });
    
    // Quantity or price change - update line total
    $(document).on('input', '.service-qty, .service-price', function() {
        var row = $(this).closest('tr');
        updateLineTotal(row);
    });
    
    // Add service line
    function addServiceLine() {
        var products = getProducts();
        var staff = getStaff();
        
        var template = $('#service_line_template').html();
        var newIndex = serviceLineIndex++;
        
        // Replace INDEX with actual index
        var newRow = template.replace(/INDEX/g, newIndex);
        
        // Add to table
        $('#service_lines_table tbody').append(newRow);
        
        // Get the new row
        var $newRow = $('#service_lines_table tbody tr:last');
        
        // Populate product dropdown
        var $productSelect = $newRow.find('.service-product');
        $productSelect.append('<option value="">Search and select service...</option>');
        
        if (products && products.length > 0) {
            products.forEach(function(product) {
                var price = parseFloat(product.default_sell_price) || 0;
                $productSelect.append(
                    $('<option>', {
                        value: product.id,
                        text: product.name + ' (' + price.toFixed(2) + ')',
                        'data-price': price,
                        'data-flexible': product.is_flexible_price
                    })
                );
            });
        }
        
        // Populate staff dropdown
        var $staffSelect = $newRow.find('.service-staff');
        $staffSelect.append('<option value="">Select Staff</option>');
        
        if (staff && staff.length > 0) {
            staff.forEach(function(staffMember) {
                if (staffMember && staffMember.id) {
                    $staffSelect.append(
                        $('<option>', {
                            value: staffMember.id,
                            text: staffMember.name || staffMember.full_name || ''
                        })
                    );
                }
            });
        }
        
        // Initialize select2 with search
        $productSelect.select2({
            width: '100%',
            dropdownParent: $('#add_booking_modal'),
            placeholder: 'Search and select service...',
            allowClear: true,
            language: {
                noResults: function() { return 'No services found'; },
                searching: function() { return 'Searching...'; }
            }
        });
        
        $staffSelect.select2({
            width: '100%',
            dropdownParent: $('#add_booking_modal'),
            placeholder: 'Select Staff',
            allowClear: true
        });
        
        updateLineNumbers();
    }
    
    // Update line numbers
    function updateLineNumbers() {
        $('.service-line-row').each(function(index) {
            $(this).find('.line-number').text(index + 1);
        });
    }
    
    // Update line total
    function updateLineTotal(row) {
        var qty = parseFloat(row.find('.service-qty').val()) || 0;
        var price = parseFloat(row.find('.service-price').val()) || 0;
        var total = qty * price;
        
        row.find('.line-total').text(total.toFixed(2));
        row.find('.service-line-total').val(total.toFixed(2));
        
        calculateTotal();
    }
    
    // Calculate total amount
    function calculateTotal() {
        var total = 0;
        $('.service-line-total').each(function() {
            total += parseFloat($(this).val()) || 0;
        });
        $('#booking_total_amount').text(total.toFixed(2));
    }
    
    // Initialize with one service line if empty
    if ($('.service-line-row').length === 0) {
        addServiceLine();
    }
});
