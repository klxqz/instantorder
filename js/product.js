function Product(form, options) {
    this.form = $(form);
    this.add2cart = this.form.find(".add2cart");
    this.modal = this.form.closest('#modal-window');
    this.button = this.modal.find(".modal-buttons .modal-btn:eq(1)");
    for (var k in options) {
        this[k] = options[k];
    }
    var self = this;
    // add to cart block: services
    this.form.find(".services input[type=checkbox]").click(function () {
        var obj = $('select[name="service_variant[' + $(this).val() + ']"]');
        if (obj.length) {
            if ($(this).is(':checked')) {
                obj.removeAttr('disabled');
            } else {
                obj.attr('disabled', 'disabled');
            }
        }
        self.updatePrice();
    });

    this.form.find(".services .service-variants").on('change', function () {
        self.updatePrice();
    });


    this.form.find(".skus input[type=radio]").click(function () {
        if ($(this).data('disabled')) {
            self.button.addClass('btn-disabled');
        } else {
            self.button.removeClass('btn-disabled');
        }
        var sku_id = $(this).val();
        self.updateSkuServices(sku_id);
        self.updatePrice();
    });
    var $initial_cb = this.form.find(".skus input[type=radio]:checked:not(:disabled)");
    if (!$initial_cb.length) {
        $initial_cb = this.form.find(".skus input[type=radio]:not(:disabled):first").prop('checked', true).click();
    }
    $initial_cb.click();

    this.form.find(".sku-feature").change(function () {
        var key = "";
        self.form.find(".sku-feature").each(function () {
            key += $(this).data('feature-id') + ':' + $(this).val() + ';';
        });
        var sku = self.features[key];
        if (sku) {
            self.updateSkuServices(sku.id);
            if (sku.available) {
                self.button.removeClass('btn-disabled');
            } else {
                self.form.find("div.stocks div").hide();
                self.form.find(".sku-no-stock").show();
                self.button.addClass('btn-disabled');
            }
            self.add2cart.find(".price").data('price', sku.price);
            self.updatePrice(sku.price, sku.compare_price);
        } else {
            self.form.find("div.stocks div").hide();
            self.form.find(".sku-no-stock").show();
            self.button.addClass('btn-disabled');
            self.add2cart.find(".compare-at-price").hide();
            self.add2cart.find(".price").empty();
        }
    });
    this.form.find(".sku-feature:first").change();

    if (!this.form.find(".skus input:radio:checked").length) {
        this.form.find(".skus input:radio:enabled:first").attr('checked', 'checked');
    }
}

Product.prototype.currencyFormat = function (number, no_html) {
    // Format a number with grouped thousands
    //
    // +   original by: Jonas Raoni Soares Silva (http://www.jsfromhell.com)
    // +   improved by: Kevin van Zonneveld (http://kevin.vanzonneveld.net)
    // +	 bugfix by: Michael White (http://crestidg.com)

    var i, j, kw, kd, km;
    var decimals = this.currency.frac_digits;
    var dec_point = this.currency.decimal_point;
    var thousands_sep = this.currency.thousands_sep;

    // input sanitation & defaults
    if (isNaN(decimals = Math.abs(decimals))) {
        decimals = 2;
    }
    if (dec_point == undefined) {
        dec_point = ",";
    }
    if (thousands_sep == undefined) {
        thousands_sep = ".";
    }

    i = parseInt(number = (+number || 0).toFixed(decimals)) + "";

    if ((j = i.length) > 3) {
        j = j % 3;
    } else {
        j = 0;
    }

    km = (j ? i.substr(0, j) + thousands_sep : "");
    kw = i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + thousands_sep);
    //kd = (decimals ? dec_point + Math.abs(number - i).toFixed(decimals).slice(2) : "");
    kd = (decimals && (number - i) ? dec_point + Math.abs(number - i).toFixed(decimals).replace(/-/, 0).slice(2) : "");


    var number = km + kw + kd;
    var s = no_html ? this.currency.sign : this.currency.sign_html;
    if (!this.currency.sign_position) {
        return s + this.currency.sign_delim + number;
    } else {
        return number + this.currency.sign_delim + s;
    }
};


Product.prototype.serviceVariantHtml = function (id, name, price) {
    return $('<option data-price="' + price + '" value="' + id + '"></option>').text(name + ' (+' + this.currencyFormat(price, 1) + ')');
};

Product.prototype.updateSkuServices = function (sku_id) {
    this.form.find("div.stocks div").hide();
    this.form.find(".sku-" + sku_id + "-stock").show();
    for (var service_id in this.services[sku_id]) {
        var v = this.services[sku_id][service_id];
        if (v === false) {
            this.form.find(".service-" + service_id).hide().find('input,select').attr('disabled', 'disabled').removeAttr('checked');
        } else {
            this.form.find(".service-" + service_id).show().find('input').removeAttr('disabled');
            if (typeof (v) == 'string') {
                this.form.find(".service-" + service_id + ' .service-price').html(this.currencyFormat(v));
                this.form.find(".service-" + service_id + ' input').data('price', v);
            } else {
                var select = this.form.find(".service-" + service_id + ' .service-variants');
                var selected_variant_id = select.val();
                for (var variant_id in v) {
                    var obj = select.find('option[value=' + variant_id + ']');
                    if (v[variant_id] === false) {
                        obj.hide();
                        if (obj.attr('value') == selected_variant_id) {
                            selected_variant_id = false;
                        }
                    } else {
                        if (!selected_variant_id) {
                            selected_variant_id = variant_id;
                        }
                        obj.replaceWith(this.serviceVariantHtml(variant_id, v[variant_id][0], v[variant_id][1]));
                    }
                }
                this.form.find(".service-" + service_id + ' .service-variants').val(selected_variant_id);
            }
        }
    }
};
Product.prototype.updatePrice = function (price, compare_price) {
    if (price === undefined) {
        var input_checked = this.form.find(".skus input:radio:checked");
        if (input_checked.length) {
            var price = parseFloat(input_checked.data('price'));
            var compare_price = parseFloat(input_checked.data('compare-price'));
        } else {
            var price = parseFloat(this.add2cart.find(".price").data('price'));
        }
    }
    if (compare_price) {
        if (!this.add2cart.find(".compare-at-price").length) {
            this.add2cart.prepend('<span class="compare-at-price nowrap"></span>');
        }
        this.add2cart.find(".compare-at-price").html(this.currencyFormat(compare_price)).show();
    } else {
        this.add2cart.find(".compare-at-price").hide();
    }
    var self = this;
    this.form.find(".services input:checked").each(function () {
        var s = $(this).val();
        if (self.form.find('.service-' + s + '  .service-variants').length) {
            price += parseFloat(self.form.find('.service-' + s + '  .service-variants :selected').data('price'));
        } else {
            price += parseFloat($(this).data('price'));
        }
    });
    this.add2cart.find(".price").html(this.currencyFormat(price));
}