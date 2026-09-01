{{-- Reusable Product Selector Modal — Table with checkboxes --}}
<div class="modal fade" id="{{ $id ?? 'product_selector_modal' }}" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="width: 90%; max-width: 1200px;">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #1a237e; color: white;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: white; opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><i class="fa fa-search"></i> Select Product(s)</h4>
            </div>
            <div class="modal-body" style="padding: 15px;">
                {{-- Search and Filter --}}
                <div class="row" style="margin-bottom: 10px;">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-search"></i></span>
                            <input type="text" class="form-control" id="ps_search_{{ $id ?? 'product_selector_modal' }}" 
                                   placeholder="Search by name, SKU, or barcode...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-control" id="ps_category_filter_{{ $id ?? 'product_selector_modal' }}">
                            <option value="">All Categories</option>
                            @if(!empty($categories))
                                @if(is_array($categories))
                                    @foreach($categories as $id_val => $cat_name)
                                        <option value="{{ $id_val }}">{{ $cat_name }}</option>
                                    @endforeach
                                @else
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id ?? $cat }}">{{ $cat->name ?? $cat }}</option>
                                    @endforeach
                                @endif
                            @endif
                        </select>
                    </div>
                    <div class="col-md-2 text-right">
                        <span id="ps_selected_count_{{ $id ?? 'product_selector_modal' }}" class="badge" style="background:#1a237e;font-size:13px;padding:5px 10px;">0 selected</span>
                    </div>
                </div>

                {{-- Product Table --}}
                <div style="max-height: 450px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
                    <table class="table table-condensed table-striped table-hover" style="margin-bottom: 0;" id="ps_table_{{ $id ?? 'product_selector_modal' }}">
                        <thead style="position: sticky; top: 0; background: #f5f5f5; z-index: 1;">
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="ps_select_all_{{ $id ?? 'product_selector_modal' }}">
                                </th>
                                <th style="width: 60px;">Image</th>
                                <th>Product Name</th>
                                <th style="width: 120px;">SKU</th>
                                <th style="width: 100px;">Barcode</th>
                                <th style="width: 120px; text-align: right;">Price (KD)</th>
                                <th style="width: 100px;">Category</th>
                                <th style="width: 80px;">Stock</th>
                            </tr>
                        </thead>
                        <tbody id="ps_tbody_{{ $id ?? 'product_selector_modal' }}">
                            <tr>
                                <td colspan="8" class="text-center text-muted" style="padding: 30px;">
                                    <i class="fa fa-spinner fa-spin"></i> Loading products...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="row" style="margin-top: 10px;">
                    <div class="col-md-6">
                        <span id="ps_total_{{ $id ?? 'product_selector_modal' }}" class="text-muted"></span>
                    </div>
                    <div class="col-md-6 text-right">
                        <ul class="pagination pagination-sm" id="ps_pagination_{{ $id ?? 'product_selector_modal' }}"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="ps_ok_btn_{{ $id ?? 'product_selector_modal' }}" style="background-color: #1a237e; border-color: #1a237e;">
                    <i class="fa fa-check"></i> OK
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var modalId = '{{ $id ?? "product_selector_modal" }}';
    var onSelectCallback = '{{ $on_select ?? "" }}';
    var currentPage = 1;
    var perPage = 25;
    var searchQuery = '';
    var categoryFilter = '';
    var searchTimer = null;
    var allProducts = {}; // Store all loaded products by ID
    var selectedProducts = {}; // Track selected products

    function loadProducts() {
        var tbody = $('#ps_tbody_' + modalId);
        tbody.html('<tr><td colspan="8" class="text-center text-muted" style="padding:30px;"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>');

        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        var url = window.location.origin + '/products/get-products-for-selector';

        $.ajax({
            url: url,
            type: 'GET',
            data: { page: currentPage, per_page: perPage, search: searchQuery, category_id: categoryFilter },
            headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            success: function(response) {
                renderTable(response.data || []);
                renderPagination(response.last_page || 1, response.total || 0, response.from || 0, response.to || 0);
            },
            error: function(xhr) {
                console.error('Product selector error:', xhr.status, xhr.responseText);
                tbody.html('<tr><td colspan="8" class="text-center text-danger"><i class="fa fa-exclamation-triangle"></i> Error loading products (Status: ' + xhr.status + ')</td></tr>');
            }
        });
    }

    function renderTable(products) {
        var tbody = $('#ps_tbody_' + modalId);
        if (!products || products.length === 0) {
            tbody.html('<tr><td colspan="8" class="text-center text-muted" style="padding:30px;"><i class="fa fa-box-open fa-2x"></i><p>No products found</p></td></tr>');
            return;
        }

        var html = '';
        for (var i = 0; i < products.length; i++) {
            var p = products[i];
            // Store in lookup
            allProducts[p.id] = p;

            var imageUrl = p.image_url || '/uploads/img/default.png';
            var isChecked = selectedProducts[p.id] ? 'checked' : '';
            var rowClass = selectedProducts[p.id] ? 'ps-row-selected' : '';

            html += '<tr class="ps-product-row ' + rowClass + '" data-pid="' + p.id + '">';
            html += '<td style="text-align:center;"><input type="checkbox" class="ps-checkbox" data-pid="' + p.id + '" ' + isChecked + '></td>';
            html += '<td style="text-align:center;"><img src="' + imageUrl + '" style="width:40px;height:40px;object-fit:contain;border-radius:4px;" onerror="this.src=\'/uploads/img/default.png\'"></td>';
            html += '<td><strong>' + (p.name || '') + '</strong></td>';
            html += '<td style="font-size:11px;color:#666;">' + (p.sku || 'N/A') + '</td>';
            html += '<td style="font-size:11px;color:#666;">' + (p.barcode || 'N/A') + '</td>';
            html += '<td style="text-align:right;font-weight:bold;color:#1a237e;">KD ' + (p.min_price || 0) + '</td>';
            html += '<td style="font-size:11px;">' + (p.category_name || '') + '</td>';
            html += '<td>' + (p.enable_stock ? '<span class="label label-success" style="font-size:10px;">' + (p.current_stock || 0) + '</span>' : '<span class="label label-default" style="font-size:10px;">N/A</span>') + '</td>';
            html += '</tr>';
        }
        tbody.html(html);
        updateSelectedCount();

        // Row click to toggle checkbox
        tbody.find('.ps-product-row').on('click', function(e) {
            if ($(e.target).is('input[type="checkbox"]')) return; // Don't double-toggle
            var checkbox = $(this).find('.ps-checkbox');
            checkbox.prop('checked', !checkbox.is(':checked')).trigger('change');
        });

        // Checkbox change
        tbody.find('.ps-checkbox').on('change', function() {
            var pid = $(this).data('pid');
            var row = $(this).closest('.ps-product-row');
            if ($(this).is(':checked')) {
                selectedProducts[pid] = allProducts[pid];
                row.addClass('ps-row-selected');
            } else {
                delete selectedProducts[pid];
                row.removeClass('ps-row-selected');
            }
            updateSelectedCount();
        });

        // Select All checkbox
        $('#ps_select_all_' + modalId).on('change', function() {
            var isChecked = $(this).is(':checked');
            tbody.find('.ps-checkbox').each(function() {
                var pid = $(this).data('pid');
                $(this).prop('checked', isChecked);
                if (isChecked) {
                    selectedProducts[pid] = allProducts[pid];
                    $(this).closest('.ps-product-row').addClass('ps-row-selected');
                } else {
                    delete selectedProducts[pid];
                    $(this).closest('.ps-product-row').removeClass('ps-row-selected');
                }
            });
            updateSelectedCount();
        });
    }

    function updateSelectedCount() {
        var count = Object.keys(selectedProducts).length;
        $('#ps_selected_count_' + modalId).text(count + ' selected');
        $('#ps_ok_btn_' + modalId).prop('disabled', count === 0);
    }

    function renderPagination(lastPage, total, from, to) {
        var pag = $('#ps_pagination_' + modalId);
        $('#ps_total_' + modalId).text('Showing ' + from + '-' + to + ' of ' + total + ' products');
        if (!lastPage || lastPage <= 1) { pag.html(''); return; }
        var html = '<li class="' + (currentPage === 1 ? 'disabled' : '') + '"><a href="#" data-page="' + (currentPage - 1) + '">&laquo;</a></li>';
        var start = Math.max(1, currentPage - 2);
        var end = Math.min(lastPage, currentPage + 2);
        for (var i = start; i <= end; i++) {
            html += '<li class="' + (i === currentPage ? 'active' : '') + '"><a href="#" data-page="' + i + '">' + i + '</a></li>';
        }
        html += '<li class="' + (currentPage === lastPage ? 'disabled' : '') + '"><a href="#" data-page="' + (currentPage + 1) + '">&raquo;</a></li>';
        pag.html(html);
        pag.find('a').on('click', function(e) {
            e.preventDefault();
            var page = parseInt($(this).data('page'));
            if (page >= 1 && page <= lastPage) { currentPage = page; loadProducts(); }
        });
    }

    // OK button click
    $(document).on('click', '#ps_ok_btn_' + modalId, function() {
        var products = Object.values(selectedProducts);
        if (products.length === 0) return;

        if (onSelectCallback && typeof window[onSelectCallback] === 'function') {
            window[onSelectCallback](products);
        }
        $('#' + modalId).modal('hide');
    });

    // Reset when modal opens
    $(document).on('shown.bs.modal', '#' + modalId, function() {
        currentPage = 1;
        searchQuery = '';
        categoryFilter = '';
        // Keep selectedProducts persistent across pages
        $('#ps_search_' + modalId).val('');
        $('#ps_category_filter_' + modalId).val('');
        loadProducts();
    });

    // Search
    $(document).on('keyup', '#ps_search_' + modalId, function() {
        clearTimeout(searchTimer);
        var query = $(this).val();
        searchTimer = setTimeout(function() { searchQuery = query; currentPage = 1; loadProducts(); }, 300);
    });

    // Category filter
    $(document).on('change', '#ps_category_filter_' + modalId, function() {
        categoryFilter = $(this).val();
        currentPage = 1;
        loadProducts();
    });
})();
</script>

<style>
.ps-row-selected { background-color: #e3f2fd !important; }
.ps-row-selected td { border-bottom-color: #1a237e !important; }
.ps-checkbox { width: 18px; height: 18px; cursor: pointer; }
.ps-product-row { cursor: pointer; }
.ps-product-row:hover { background-color: #f0f4ff !important; }
</style>
