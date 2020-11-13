/*
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$(function () {
    $(document).ready(function () {
        $("#terminalNature").hide();
        let index = $("#terminalName").prop('selectedIndex');
        $('#terminalNature option:eq(' + index + ')').prop('selected', true);
        check();
    });

    $("#terminalName").change(function () {
        $("#terminalNature").hide();
        let index = $("#terminalName").prop('selectedIndex');
        $('#terminalNature option:eq(' + index + ')').prop('selected', true);
        check();
    });

    function check() {
        var val = $("#terminalNature").val();

        if (val === "CreditCard") {
            $("#ccTokenControl_").prop("disabled", false);
            return;
        }
        $("#ccTokenControl_").prop("checked", false);
        $("#ccTokenControl_").prop("disabled", true);
    }
});
