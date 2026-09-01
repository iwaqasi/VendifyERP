<script type="text/javascript">
    $(document).ready( function() {

        function getTaxonomiesIndexPage () {
            var data = {category_type : $('#category_type').val()};
            $.ajax({
                method: "GET",
                dataType: "html",
                url: '/taxonomies-ajax-index-page',
                data: data,
                async: false,
                success: function(result){
                    $('.taxonomy_body').html(result);
                }
            });
        }

        function initializeTaxonomyDataTable() {
            //Category table
            if ($('#category_table').length) {
                var category_type = $('#category_type').val();
                category_table = $('#category_table').DataTable({
                    processing: true,
                    serverSide: true,
                    fixedHeader:false,
                    ajax: '/taxonomies?type=' + category_type,
                    columns: [
                        { data: 'image', name: 'image', orderable: false, searchable: false, render: function(data) {
                            if (data) {
                                return '<img src="' + data + '" style="max-width:50px;max-height:50px;border-radius:6px;border:1px solid #ddd;">';
                            }
                            return '<span style="display:inline-block;width:50px;height:50px;background:#f0f0f0;border-radius:6px;text-align:center;line-height:50px;color:#aaa;"><i class="fa fa-image"></i></span>';
                        }},
                        { data: 'name', name: 'name', orderable: false, searchable: true },
                        @if($cat_code_enabled)
                            { data: 'short_code', name: 'short_code', orderable: false, searchable: true },
                        @endif
                        { data: 'description', name: 'description', orderable: false, searchable: true },
                        { data: 'action', name: 'action', orderable: false, searchable: false},
                    ],
                });
            }
        }

        @if(empty(request()->get('type')))
            getTaxonomiesIndexPage();
        @endif

        initializeTaxonomyDataTable();
    });
    // Image preview for add form
    $(document).on('change', '#category_image_input', function(e) {
        var reader = new FileReader();
        reader.onload = function(ev) {
            $('#category_preview_img').attr('src', ev.target.result).show();
        };
        reader.readAsDataURL(e.target.files[0]);
    });

    $(document).on('submit', 'form#category_add_form', function(e) {
        e.preventDefault();
        var form = $(this);
        var formData = new FormData(this);

        $.ajax({
            method: 'POST',
            url: $(this).attr('action'),
            dataType: 'json',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) {
                __disable_submit_button(form.find('button[type="submit"]'));
            },
            success: function(result) {
                if (result.success === true) {
                    $('div.category_modal').modal('hide');
                    toastr.success(result.msg);
                    if(typeof category_table !== 'undefined') {
                        category_table.ajax.reload();
                    }

                    var evt = new CustomEvent("categoryAdded", {detail: result.data});
                    window.dispatchEvent(evt);

                    //event can be listened as
                    //window.addEventListener("categoryAdded", function(evt) {}
                } else {
                    toastr.error(result.msg);
                }
            },
        });
    });
    $(document).on('click', 'button.edit_category_button', function() {
        $('div.category_modal').load($(this).data('href'), function() {
            $(this).modal('show');

            // Image preview for edit form
            $(document).on('change', '#category_image_input', function(e) {
                var reader = new FileReader();
                reader.onload = function(ev) {
                    $('#category_preview_img').attr('src', ev.target.result).show();
                };
                reader.readAsDataURL(e.target.files[0]);
            });

            // Remove existing image
            $(document).on('click', '#remove_category_image', function() {
                var categoryId = $(this).data('category-id');
                $.ajax({
                    method: 'DELETE',
                    url: '/products/category/' + categoryId + '/image',
                    dataType: 'json',
                    success: function(result) {
                        if (result.success) {
                            $('#existing_image').remove();
                            toastr.success('Image removed');
                        }
                    },
                });
            });

            $('form#category_edit_form').submit(function(e) {
                e.preventDefault();
                var form = $(this);
                var formData = new FormData(this);

                $.ajax({
                    method: 'POST',
                    url: $(this).attr('action'),
                    dataType: 'json',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function(xhr) {
                        __disable_submit_button(form.find('button[type="submit"]'));
                    },
                    success: function(result) {
                        if (result.success === true) {
                            $('div.category_modal').modal('hide');
                            toastr.success(result.msg);
                            category_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                });
            });
        });
    });

    $(document).on('click', 'button.delete_category_button', function() {
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(willDelete => {
            if (willDelete) {
                var href = $(this).data('href');
                var data = $(this).serialize();

                $.ajax({
                    method: 'DELETE',
                    url: href,
                    dataType: 'json',
                    data: data,
                    success: function(result) {
                        if (result.success === true) {
                            toastr.success(result.msg);
                            category_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                });
            }
        });
    });
</script>