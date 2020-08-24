jQuery(document).ready(function () {

	// Restrict to copy and paste for cvv
	jQuery(document).bind('cut copy paste', '#inovio-card-cvc', function (e) {
		e.preventDefault();
	});

	jQuery(document).bind('cut copy paste', '#inovio-card-number', function (e) {
		e.preventDefault();
	});

	// Restrict to enter Character
	jQuery(document).on('keypress', '#inovio-card-number', enterNumeric);
	jQuery(document).on('keypress', '#inovio-card-cvc', enterNumeric);

	// On click payment option
	jQuery('#payment-confirmation > .ps-shown-by-js > button').click(function () {

		if (jQuery('#inovio-card-number').val() == "" || jQuery('#inovio-card-number').val() == null) {
			if (jQuery('#card-number-span span').length < 1) {
				jQuery('#card-number-span').text('Please enter credit card number');
				jQuery('#card-number-span').css({"color": "red"});

			}
			return false;

		}
		// Check credit card length
		if (jQuery('#inovio-card-number').val().length < 13) {
			jQuery('#card-number-span').text('Invalid credit card number');
			jQuery('#card-number-span').css({"color": "red"});
			return false;
		}

		if (jQuery('#inovio-card-number').val() != "" || jQuery('#inovio-card-number').val() != null) {
			jQuery('#card-number-span').remove();
		}

		if (jQuery('#inovio-card-cvc').val().length < 3) {
			if (jQuery('#card-number-span span').length < 1) {
				jQuery('#cvv-number-span').text('Please enter valid CVV number');
				jQuery('#cvv-number-span').css({"color": "red"});
			}
			return false;
		}
		if (jQuery('#inovio-card-cvc').val() != "" || jQuery('#inovio-card-cvc').val() != null) {
			jQuery('#cvv-number-span').remove();
		}
		if (jQuery('#inovio-card-cvc').val() != "" || jQuery('#inovio-card-cvc').val() != null) {
			jQuery('#cvv-number-span').remove();
		}
		if (validateDate(jQuery('#card-expiry-year').val() + '-' + jQuery('#card-expiry-month').val()) == false) {
			console.log(jQuery('#card-expiry-year').val() + '-' + jQuery('#card-expiry-month').val());
			jQuery('#dateExpiry-span').text('Please check credit card expiry');
			jQuery('#dateExpiry-span').css({'color': 'red'});
			return false;
		}
		return true;
	});
});
// Restrict to enter any character
var enterNumeric = function (e) {
	return (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) ? false : true;
};
// Validate Credit card expiry date
validateDate = function (expiryDate) {

	var compareDate = new Date(expiryDate);
	var currentYear = new Date();
	if (compareDate <= currentYear) {
		return false;
	}
	return true;
}