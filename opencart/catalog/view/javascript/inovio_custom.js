$(document).ready(function () {
        
        $(document).on("change", "#inovio_input-cc-cvv2", function(){
            if ($(this).val().length > 4) {
                $(this).val("");
            }
        });
        // Restrict enter numeric only
        $(document).on("keypress", "#inovio-nput-cc-number", function (e) {
            return (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) ? false : true;
        });
        // Restrict enter numeric only
        $(document).on("keypress", "#inovio_input-cc-cvv2", function (e) {
            return (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) ? false : true;
        });
        // Restrict right click
        $(document).on("contextmenu", "#inovio_input-cc-cvv2", function () {
            return false;
        });
        // Restrict right click
        $(document).on("contextmenu", "#inovio-nput-cc-number", function () {
            return false;
        });
        
        // Restrict copy paste
        $(document).bind("cut copy paste", "#inovio-nput-cc-number", function(e) {
                      e.preventDefault();
        });
        // Restrict copy paste
        $(document).bind("cut copy paste", "#inovio_input-cc-cvv2", function(e) {
                      e.preventDefault();
        });
        
});

    // On click place order process call inovio api method
    $('#inovio-button-confirm').on('click', function () {
        $.ajax({
            url: 'index.php?route=extension/payment/inovio_pay/inovio_checkout',
            type: 'post',
            data: $('#payment :input'),
            dataType: 'json',
            cache: false,
            beforeSend: function () {
                $('#inovio-button-confirm').button('loading');
            },
            complete: function () {
                $('#inovio-button-confirm').button('reset');
            },
            success: function (json) {

                if (json['error']) {
                    $('#inovioErrormessage').addClass(
                            "alert alert-danger alert-dismissible text-center"
                            ).html("<strong>"
                                +json['error']+"</strong>"
                            );
                    return false;
                }
                $('#inovioErrormessage').remove();
                if (json['redirect']) {
                    location = json['redirect'];
                }
            }
        });
    });