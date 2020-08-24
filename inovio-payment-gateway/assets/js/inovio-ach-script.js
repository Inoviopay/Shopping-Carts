/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


jQuery(document).ready(function () {

    //Restrict to enter Character

    jQuery(document).on('keypress', '#ach_inovio_routing_number', enter_numberic_with_hyphen);
    jQuery(document).on('cut copy paste', "#ach_inovio_routing_number", restrict_cut_copy_paste);
    jQuery(document).on('keyup', '#ach_inovio_routing_number', check_valid_routing_number);

    jQuery(document).on('keypress', '#ach_inovio_account_number', ach_enter_numeric);
    jQuery(document).on('cut copy paste', '#ach_inovio_account_number', restrict_cut_copy_paste);

    jQuery(document).on('keypress', '#ach_inovio_confirm_account_number', ach_enter_numeric);
    jQuery(document).on('cut copy paste', '#ach_inovio_confirm_account_number', restrict_cut_copy_paste);

    jQuery(document).on('keyup', '#ach_inovio_account_number, #ach_inovio_confirm_account_number', match_account_number);

    jQuery(document).on('click', "#place_order", inovio_place_order);

    // add loader after clicked on place order
    jQuery('form.checkout').on('submit', function () {

        jQuery('.woocommerce-checkout-review-order-table').block({
            message: null,
            overlayCSS: {
                'background': '#fff',
                'background-image': achInovioPlugindir + "/assets/img/FhHRx.gif",
                'background-repeat': 'no-repeat',
                'background-position': 'center',
                'opacity': 0.6
            }
        });
    });
});
let check_status = false;
let routing_no_status = false;


let check_valid_routing_number = function () {

    let routing_number = jQuery('#ach_inovio_routing_number').val();

    let validate_routing_url = ach_ajax_scripts.ach_validate_routing_url;


    if ((validate_routing_url = !null && validate_routing_url != undefined) || routing_number != null && routing_number != undefined) {
        jQuery.ajax({
            type: "GET",
            url: ach_ajax_scripts.ach_validate_routing_url + routing_number,
            success: function (data) {
                if (data.message == "OK") {
                    routing_no_status = true;
                    jQuery("#ach_routing_number_message").html(
                        `<span class="dashicons dashicons-yes"></span>${data.customer_name}`).css("color", "green");
                } else {
                    routing_no_status = false;
                    jQuery("#ach_routing_number_message").html("<span class='dashicons dashicons-no-alt'></span>please enter valid routing number").css("color", "#a00");
                }

            },
            error: function (jqXHR, textStatus, errorThrown) {
                routing_no_status = false;
                jQuery("#ach_routing_number_message").html("<span class='dashicons dashicons-no-alt'></span>please enter valid routing number").css("color", "#a00");

            }

        });
    } else {
        routing_no_status = false;
        jQuery("#ach_routing_number_message").html("<span class='dashicons dashicons-no-alt'></span>please enter valid routing number").css("color", "#a00");
    }
}

let inovio_place_order = function (e) {

    if (jQuery("#payment_method_achinoviomethod").is(":checked")) {
        check_valid_routing_number();
        match_account_number();
        if (check_status === true && routing_no_status == true) {
            return true;
        } else {
            return false;

        }
    }
}


let match_account_number = function () {

    let account_number = jQuery("#ach_inovio_account_number").val();
    let confirm_number = jQuery("#ach_inovio_confirm_account_number").val();
    if (account_number.length < 6 || confirm_number < 6) {
        check_status = false;
        jQuery("#account_matched_message").html("account number should be 6 digit").css("color", "#a00");
    } else if (account_number == confirm_number && account_number.length > 0 && confirm_number > 0) {
        check_status = true;
        jQuery("#account_matched_message").html("account number matched").css("color", "green");
    } else {
        check_status = false;
        jQuery("#account_matched_message").html("account number not matched").css("color", "#a00");
    }

}

// Restrict to enter any character 
let ach_enter_numeric = function (e) {
    return (e.which < 48 || e.which > 57) ? false : true;
};

// Restrict to enter any character 
let enter_numberic_with_hyphen = function (e) {
    return (e.which != 45 && e.which < 48 || e.which > 57) ? false : true;
};

// Restrict cut copy past
let restrict_cut_copy_paste = function (e) {
    e.preventDefault()
}