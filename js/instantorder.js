(function ($) {
    $.instantorder = {
        options: {},
        init: function (options) {
            this.options = options;
            this.initModal();
        },
        initModal: function () {
            if (!this.options.instantorder_btn_selector) {
                console.log('Не задан селектор кнопки быстрого заказа');
                return false;
            }
            self = this;
            $(document).off('click', this.options.instantorder_btn_selector).on('click', this.options.instantorder_btn_selector, function () {
                var btn = $(this);
                var mode = btn.data('mode');
                if (!mode) {
                    console.log('У кнопки быстрого заказа не указан атрибут data-mode: product, products-list, cart');
                    return false;
                }
                var post_data = null
                if (mode == 'product') {
                    if (!self.options.product_form_selector) {
                        console.log('Не указан селектор формы добавления товара в корзину');
                        return false;
                    }
                    if (!$(self.options.product_form_selector).length) {
                        console.log('Селектор формы добавления товара в корзину указан неверно');
                        return false;
                    }
                    post_data = $(self.options.product_form_selector).serialize();
                } else if (mode == 'products-list') {
                    if (!btn.data('product-id')) {
                        console.log('Не указан идентификатор товара');
                        return false;
                    }
                    post_data = 'product_id=' + btn.data('product-id');
                    if (btn.data('sku-id')) {
                        post_data += '&sku_id=' + btn.data('sku-id');
                    }
                } else if (mode == 'cart') {
                    post_data = 'cart=1';
                }
                modal({
                    animate: true,
                    type: 'confirm',
                    title: self.options.modal_title,
                    text: '<i class="icon16 loading"></i> Пожалуйста, подождите...',
                    buttons: [
                        {
                            addClass: ''
                        },
                        {
                            addClass: self.options.order_btn_class
                        }
                    ],
                    buttonText: {
                        yes: self.options.order_btn_text,
                        cancel: self.options.close_btn_text
                    },
                    onShow: function (r) {
                        var u = r.getModal();
                        var order_btn = u.find('.modal-buttons .modal-btn:eq(1)');
                        order_btn.addClass('btn-disabled');
                        $.ajax({
                            type: 'POST',
                            url: self.options.url,
                            dataType: 'html',
                            data: post_data,
                            success: function (html, textStatus, jqXHR) {
                                if ($('<div></div>').append(html).find('[name=action][value=selectSku]').length) {
                                    self.selectSkuAction(r, html);
                                } else {
                                    self.cartAction(r, html);
                                }
                            },
                            error: function (jqXHR, errorText) {
                                r.setContent('<div class="errors">' + jqXHR.responseText + '</div>');
                                $(window).resize();
                            }
                        });
                    },
                    callback: function (a, u, r) {
                        if (a) {
                            self.order(r);
                            return false;
                        }
                        if (u.find('input[name=cart_mode][value=1]').length) {
                            window.location.reload();
                        }
                    }
                });
                return false;
            });
        },
        selectSkuAction: function (r, html) {
            var u = r.getModal();
            var order_btn = u.find('.modal-buttons .modal-btn:eq(1)');
            order_btn.text(this.options.select_btn_text);
            if (!$(html).find('.errors').length) {
                order_btn.removeClass('btn-disabled');
            }
            r.setContent(html);
            $(window).resize();
        },
        cartAction: function (r, html) {
            var u = r.getModal();
            var order_btn = u.find('.modal-buttons .modal-btn:eq(1)');
            order_btn.text(this.options.order_btn_text);
            if (!$(html).find('.errors').length) {
                order_btn.removeClass('btn-disabled');
            }
            r.setContent(html);
            $(window).resize();
            this.initQuantity(u);
            this.initServices(u);
            this.initValide(u);
            this.initMaskedInput(u);
            this.checkItems(u);
        },
        checkItems: function (u) {
            u.find('.instantorder-items-table .qty_block .quantity').each(function () {
                if (parseInt($(this).val()) < 1) {
                    var tr = $(this).closest('tr');
                    tr.addClass('disabled');
                    tr.find('input,select').attr('disabled', true);
                    tr.find('.minus,.plus').addClass('btn-disabled');
                }
            });
            if (!u.find('.instantorder-items-table tbody tr:not(.disabled)').length) {
                u.find('.modal-buttons .modal-btn:eq(1)').addClass('btn-disabled');
            }
        },
        initMaskedInput: function (u) {
            if (this.options.phone_mask.length) {
                u.find('#instantorder-order-form input[name="customer[phone]"]').mask(this.options.phone_mask);
            }
        },
        initValide: function (u) {
            u.find('#instantorder-order-form').validate({
                rules: {
                    terms: {
                        required: true
                    }
                },
                messages: {
                    terms: "Это поле обязательное для заполнения"
                }
            });
            u.find('#instantorder-order-form .wa-required').find('input:visible, textarea:visible').each(function () {
                $(this).rules('add', {
                    required: true,
                    messages: {
                        required: "Это поле обязательное для заполнения"
                    }
                });
            });
        },
        initServices: function (u) {
            var self = this;
            u.on('change', '.services input:checkbox', function () {
                if ($(this).is(':checked')) {
                    $(this).closest('.service').find('.service-data').removeAttr('disabled');
                } else {
                    $(this).closest('.service').find('.service-data').attr('disabled', 'disabled');
                }
                if ($(this).closest('.service').find('select').length) {
                    if ($(this).is(':checked')) {
                        $(this).closest('.service').find('select').removeAttr('disabled');
                    } else {
                        $(this).closest('.service').find('select').attr('disabled', 'disabled');
                    }
                }
                self.recalculate(u);
            });

            u.on('change', '.services select', function () {
                self.recalculate(u);
            });
        },
        initQuantity: function (u) {
            var self = this;
            u.on('click', '.minus', function () {
                var input = $(this).siblings('.quantity');
                var quantity = parseInt(input.val());
                if (quantity < 2) {
                    return false;
                }
                $(this).closest('tr').find('.quantity').val(quantity - 1);
                self.recalculate(u);
                return false;
            });
            u.on('click', '.plus', function () {
                var input = $(this).siblings('.quantity');
                var quantity = parseInt(input.val());
                $(this).closest('tr').find('.quantity').val(quantity + 1);
                self.recalculate(u);
                return false;
            });
            u.on('change', '.quantity', function () {
                var input = $(this);
                var quantity = parseInt(input.val());
                $(this).closest('tr').find('.quantity').val(quantity);
                if (isNaN(quantity) || quantity < 1) {
                    $(this).closest('tr').find('.quantity').val(1);
                }
                self.recalculate(u);
            });
            u.on('click', '.delete', function () {
                $(this).closest('tr').remove();
                if (u.find('.instantorder-items-table tbody tr').length < 2) {
                    u.find('.instantorder-items-table tbody tr .delete').hide();
                }
                self.recalculate(u);
                return false;
            });
        },
        recalculate: function (u) {
            var loading = $('<i class="icon16 loading"></i>');
            u.find('.instantorder-items-table .total td:first').prepend(loading);
            var f = $('#instantorder-order-form');
            $.ajax({
                type: 'POST',
                url: this.options.recalculate_url,
                dataType: 'json',
                data: f.serialize(),
                success: function (data, textStatus, jqXHR) {
                    loading.remove();
                    if (data.status == 'ok') {
                        u.find('.instantorder-items-table .cart-total').html(data.data.total);
                        u.find('.instantorder-items-table .cart-discount').html(data.data.discount);
                        for (var item_id in data.data.order.items) {
                            var item = data.data.order.items[item_id];
                            if (item.error) {
                                alert(item.error);
                            }
                            u.find('.instantorder-items-table tr[data-id=' + item.id + '] .quantity').val(item.quantity);
                            u.find('.instantorder-items-table tr[data-id=' + item.id + '] .item-full-price').html(item.full_price_html);
                            if (item.quantity < 1) {
                                var tr = u.find('.instantorder-items-table tr[data-id=' + item.id + ']');
                                tr.addClass('disabled');
                                tr.find('input,select').attr('disabled', true);
                                tr.find('.minus,.plus').addClass('btn-disabled');
                            }
                            if (!u.find('.instantorder-items-table tbody tr:not(.disabled)').length) {
                                u.find('.modal-buttons .modal-btn:eq(1)').addClass('btn-disabled');
                            }
                        }
                    } else {
                        alert(data.errors.join(', '));
                    }
                },
                error: function (jqXHR, errorText) {
                    loading.remove();
                    console.log(jqXHR.responseText);
                }
            });
        },
        order: function (r) {
            var self = this;
            var u = r.getModal();
            if (u.find('[name=action][value=selectSku]').length) {
                var post_data = u.find('#cart-form-dialog').serialize();
                $.ajax({
                    type: 'POST',
                    url: self.options.url,
                    dataType: 'html',
                    data: post_data,
                    success: function (html, textStatus, jqXHR) {
                        self.cartAction(r, html);
                    },
                    error: function (jqXHR, errorText) {
                        r.setContent('<div class="errors">' + jqXHR.responseText + '</div>');
                        $(window).resize();
                    }
                });
            } else {
                var f = $('#instantorder-order-form');
                var order_btn = u.find('.modal-buttons .modal-btn:eq(1)');
                if (!f.valid()) {
                    return false;
                }
                order_btn.addClass('btn-disabled');
                $.ajax({
                    type: 'POST',
                    url: this.options.order_url,
                    dataType: 'json',
                    data: f.serialize(),
                    success: function (data, textStatus, jqXHR) {
                        if (data.status == 'ok') {
                            r.setContent(data.data.html);
                            $(window).resize();
                            order_btn.hide();
                        } else {
                            alert(data.errors.join(', '));
                            order_btn.removeClass('btn-disabled');
                        }
                    },
                    error: function (jqXHR, errorText) {
                        alert(jqXHR.responseText);
                        console.log(jqXHR.responseText);
                        order_btn.removeClass('btn-disabled');
                    }
                });
            }
        }
    };
})(jQuery);