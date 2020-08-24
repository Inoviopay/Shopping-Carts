(function ($) {
    // restrict to copy and paste for cvv
    $(document).bind('cut copy paste', '.inovio-cvv-number', function (e) {
        e.preventDefault();
    });

    // restrict to copy and paste for credit card number
    $(document).bind('cut copy paste', '.inovio-card-number', function (e) {
        e.preventDefault();
    });

    //Restrict to enter Character
    $(document).on('keypress', '.inovio-card-number', function (e) {
        return (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) ? false : true;
    });
    $(document).on('keypress', '.inovio-cvv-number', function (e) {
        return (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) ? false : true;
    });

})(jQuery);
