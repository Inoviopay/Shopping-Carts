jQuery(document).ready(function () {
	var divError = document.createElement("div");
	var prodrestriction = jQuery('#inovio-product-quantity').val();
	divError.className = "divError";
	divError.innerHTML = "For any single product's quantity should not be greater than " + prodrestriction
						+" <a href='cart?action=show'><button type='button' class='btn btn-danger'>Back to shop</button></a>";
	jQuery("#checkout-payment-step").prepend(divError);
	jQuery(".divError").addClass('alert alert-danger');
});
