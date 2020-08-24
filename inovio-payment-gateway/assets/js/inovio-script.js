jQuery(document).ready(function () {
    //Restrict to enter Character
    jQuery(document).on('keypress', '#inoviodirectmethod_gate_card_numbers', enter_numeric);
    jQuery(document).on('keypress', '#inoviodirectmethod_gate_card_expiration', enter_numeric);
    jQuery(document).on('keypress', '#inoviodirectmethod_gate_card_cvv', enter_numeric);

    // add loader after clicked on place order
    jQuery('form.checkout').on('submit', function () {
        jQuery('.woocommerce-checkout-review-order-table').block({
            message: null,
            overlayCSS: {
                'background': '#fff',
                'background-image': inovioPlugindir + "/assets/img/FhHRx.gif",
                'background-repeat': 'no-repeat',
                'background-position': 'center',
                'opacity': 0.6
            }
        });
    });
});

// Restrict to enter any character 
var enter_numeric = function (e) {
    return (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) ? false : true;
};