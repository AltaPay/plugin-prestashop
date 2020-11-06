/*
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
// Util
var altapay = {
    // Perform capture
    capture: function (element) {
        var namespace = this;
        var dataArray = [];
        if ($("#ap-allow-orderlines").attr("checked") === "checked") {
            dataArray = $('.ap-orderlines input').serializeArray();
        }
        dataArray.push({name: 'action', value: 'capture'});
        dataArray.push({name: 'payment_id', value: $(element).data('payment-id')});
        dataArray.push({name: 'amount', value: $('#capture-amount').val()});

        $.ajax({
            type: 'POST',
            url: $(element).data('url'),
            data: dataArray,
            success: function (result) {
                namespace.handleResponse(result);
            }
        });
    },

    // Perform refund
    refund: function (element) {
        var namespace = this;
        var goodwillrefund = 'no';
        var dataArray = [];
        if ($("#ap-allow-orderlines").attr("checked") === "checked") {
            dataArray = $('.ap-orderlines input').serializeArray();
        }
        dataArray.push({name: 'action', value: 'refund'});
        dataArray.push({name: 'payment_id', value: $(element).data('payment-id')});
        dataArray.push({name: 'amount', value: $('#refund-amount').val()});

        if ($("#ap-goodwill-refund").attr("checked") === "checked") {
            goodwillrefund = 'yes';
        }
        dataArray.push({name: 'goodwillrefund', value: goodwillrefund});
        $.ajax({
            type: 'POST',
            url: $(element).data('url'),
            data: dataArray,
            success: function (result) {
                namespace.handleResponse(result);
            }
        });
    },

    // Perform release
    release: function (element) {
        var namespace = this;
        $.ajax({
            type: 'POST',
            url: $(element).data('url'),
            data: {
                action: 'release',
                payment_id: $(element).data('payment-id')
            },
            success: function (result) {
                namespace.handleResponse(result);
            }
        });
    },

    // Recalculation of the amount for capture/refund depending on selected order lines
    recalculateAmount: function (element) {
        var sum = 0.0000;
        $.each($('tr.ap-orderlines'), function (key, value) {
            var ordered = parseInt($('.ap-orderline-max-quantity', value).text());
            var unitprice = parseFloat($('.ap-orderline-unit-price', value).text());
            var quantity = parseInt($('.ap-order-modify', value).val());
            var price = parseFloat($('.ap-total-amount', value).text());
            if ((quantity > ordered) || quantity < 0) {
                jAlert("Quantity cannot be negative or more than ordered!", 'ALTAPAY');
                if (quantity > ordered) {
                    quantity = ordered;
                } else if (quantity < 0) {
                    quantity = 0;
                }
                $('.ap-order-modify', value).val(quantity);
            }
            sum = parseFloat(((sum + (price / ordered) * quantity).toFixed(2)));

        });

        $('#capture-amount').val(sum);
        $('#refund-amount').val(sum);
    },

    // Handle response
    handleResponse: function (result) {
        if (result.status === 'success') {
            jAlert(result.message, 'ALTAPAY', function () {
                location.reload();
            });
        } else {
            jAlert(result.message, 'ERROR', function () {
                document.location.reload();
            });
        }
    }
};

// Attach event handlers
$(document).ready(function () {
    $('#btn-capture').click(function (e) {
        e.preventDefault();
        var element = this;
        var amount = parseFloat($('#capture-amount').val());
        if (isNaN(amount)) {
            return;
        }

        jConfirm('Are you sure you want to capture <b>' + amount.toFixed(2) + '</b>?', 'Capture', function (r) {
            if (r === true) {
                altapay.capture(element);
            }
        });
    });

    $('#btn-refund').click(function (e) {
        e.preventDefault();
        var element = this;
        var amount = parseFloat($('#refund-amount').val());
        if (isNaN(amount)) {
            return;
        }

        jConfirm('Are you sure you want to refund <b>' + amount.toFixed(2) + '</b>?', 'Refund', function (r) {
            if (r === true) {
                altapay.refund(element);
            }
        });
    });

    $('#btn-release').click(function (e) {
        e.preventDefault();
        var element = this;

        jConfirm('Are you sure you want to release this payment?<br>This cannot be undone!', 'Release', function (r) {
            if (r === true) {
                altapay.release(element);
            }
        });
    });

    $('.ap-order-modify').click(function (e) {
        e.preventDefault();
        var element = this;
        return altapay.recalculateAmount(element);
    });
});