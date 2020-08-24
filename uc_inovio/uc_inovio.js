(function ($) {
   Drupal.behaviors.uc_inovio = {
    attach: function (context, settings) {
          // Restrict enter alphabetic
          $('.inovio_credit_card_no, .inovio_cvv_no', context).keypress(function (e) {
            return (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) ? FALSE : TRUE;
          });

          // restrict cust copy paste
          $('.inovio_credit_card_no, .inovio_cvv_no', context).bind('cut copy paste',function (e) {
            e.preventDefault(); //disable cut,copy,paste
          });

           // Restrict right click
          $('.inovio_credit_card_no, .inovio_cvv_no', context).bind('contextmenu',function (e) {
             return FALSE;
          });

    }
  };
})(jQuery);