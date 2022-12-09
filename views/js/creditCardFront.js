/*
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$(function () {
    $("select.selectCreditCard").change(function () {
        var selectedCreditCard = $(this).children("option:selected").val();
        Cookies.set('selectedCreditCard', selectedCreditCard);
    });
    $(".savecard").change(function () {
        var savecard = 0;
        if($('.savecard').is(":checked")) {
            savecard = 1;
        }
        Cookies.set('savecard', savecard);
    });
});