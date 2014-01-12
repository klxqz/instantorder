    $(function() {
        function load_regions() {
            var country = $('.select_countries').val();
            if (!country) {
                return;
            }
            $('.region_block').append('<div id="instantorder-loading"><i class="icon16 loading"></i>Загрузка</div>');
            $.ajax({
                type: 'POST',
                url: $wa_app_url+'instantorder/regions/',
                dataType: 'json',
                data: {
                    'country': country
                },
                success: function(data, textStatus, jqXHR) {
                    if (data.status == 'ok') {
                        if (Object.keys(data.data.options).length) {
                            $('.region_block select.select_region').html('<option value="">Выберите регион</option>');
                            for (key in data.data.options) {
                                $('.region_block select.select_region').append('<option value="' + key + '">' + data.data.options[key] + '</option>');
                            }
                            $(".region_block input").prop('disabled', true);
                            $('.region_block input').hide();
                            $(".region_block select.select_region").prop('disabled', false);
                            $('.region_block select.select_region').show();
                        } else {
                            $(".region_block select.select_region").prop('disabled', true);
                            $('.region_block select.select_region').hide();
                            $(".region_block input").prop('disabled', false);
                            $('.region_block input').show();
                        }

                    }
                    $('#instantorder-loading').remove();
                }
            });
        }

        if ($('input[name="fields[address.region]"]')) {
            load_regions();
            $('.select_countries').change(load_regions);
        }
        $('#instantorder_dialog').dialog({
            draggable: instantorder_draggable,
            resizable: instantorder_resizable,
            title: instantorder_title,
            width: instantorder_width,
            height: instantorder_height,
            modal: true,
            autoOpen: false
        });

        $('.instantorder_button').click(function() {
            $('#instantorder_dialog').dialog('open');
            return false;
        });

        $('#instantorder_form').submit(function(event) {
            event.preventDefault();
            var required = false;
            $('#instantorder_form .required_field').each(function() {
                if ($(this).prop('disabled') == false && !$(this).val().length) {
                    required = true;
                    $(this).css('border', '2px solid red');
                }
            });
            if (required) {
                $('.response').html('Заполните обязательные поля');
                $('.response').css('color', 'red');
                $('.response').show();

                setTimeout(function() {
                    $('.required_field').css('border', '');
                    $('.response').hide();
                }, 5000);
            } else {
                $.ajax({
                    type: 'POST',
                    url: $(this).attr('action'),
                    dataType: 'json',
                    data: $(this).serialize() + '&' + $('#cart-form').serialize(),
                    success: function(data, textStatus, jqXHR) {
                        if (data.status == 'ok') {
                            $('.response').css('color', 'green');
                            $('.response').html(data.data.message);

                        } else {
                            $('.response').css('color', 'red');
                            $('.response').html(data.errors);
                        }
                        $('.response').show();
                        setTimeout(function() {
                            $('.response').hide();
                        }, 10000);

                    }
                });
            }
        });
        
        $('#product-skus input[type="radio"]').click(function(){        
                if ($(this).data('disabled') == 1) {
                    $('.instantorder_button').hide();
                } else {
                    $('.instantorder_button').show();
                }
        });
        $("#product-skus input[type=radio]:checked").click();
        $("select.sku-feature").change(function () {
            var key = "";
            $("select.sku-feature").each(function () {
                key += $(this).data('feature-id') + ':' + $(this).val() + ';';
            });
            var sku = sku_features[key];
            if (sku) {
                if (sku.available) {
                    $('.instantorder_button').show();
                } else {
                    $('.instantorder_button').hide();
                }
                $(".add2cart .price").data('price', sku.price);
            } else {
                $('.instantorder_button').hide();
            }
        });
        $("select.sku-feature:first").change();

    });