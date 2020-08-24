jQuery(document).ready(function () {
	var divError = document.createElement("div");
	divError.className = "divError";
	divError.innerHTML = "Something went wrong.Please contact to your service provider.";
	jQuery("#checkout-payment-step").prepend(divError);
	jQuery(".divError").addClass('alert alert-danger');
});
