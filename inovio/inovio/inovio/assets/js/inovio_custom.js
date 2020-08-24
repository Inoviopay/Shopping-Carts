/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

jQuery(document).ready(function(){
    
    // restrict to copy and paste for cvv
    jQuery(document).bind('cut copy paste', '#cc_cvv_2', function(e) {
      e.preventDefault();
    });

    jQuery(document).bind('cut copy paste', '#cc_number_2', function(e) {
      e.preventDefault();
    });
    
    //Restrict to enter Character
    jQuery(document).on('keypress', '#cc_number_2', enterNumeric);
    jQuery(document).on('keypress', '#cc_cvv_2', enterNumeric);
}); 

// Restrict to enter any character 
var enterNumeric = function(e) {
    return (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) ? false : true;
};