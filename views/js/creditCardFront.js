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
        // alert("hello");
        var savecard = 0;
        if($('.savecard').is(":checked")) {
            savecard = 1;
        }
        console.log("savecard "+ savecard);
        Cookies.set('savecard', savecard);
    });
    // console.log("savecard " + savecard);
    // Cookies.set('savecard', savecard);
    // var savecard = 0;
    // $("#savecard").change(function () {
    //     if ($(this).is(':checked')) {
    //         savecard = 1;
    //     }

    // console.log("savecard " + savecard);
        // var selectedCreditCard = $(this).children("option:selected").val();
        // Cookies.set('selectedCreditCard', selectedCreditCard);
    // });

    // $("#savecard").on('change', function() {
    //     if ($(this).is(':checked')) {
    //         savecard = 1;
    //     }
    //   });


    // var savecard = 0;
    // var $checkbox1 = $('#savecard');
    // if($checkbox1.prop('checked', $checkbox1.val() === 'true')){
    //     savecard  = 1;
    // }

    // if ($(".payment-options .savecard-checkbox input[name='savecard']").prop("checked") == true) {
    //     savecard  = 1;
    // }
    // console.log("savecard " + savecard);
    // Cookies.set('savecard', savecard);
});