(function ($, Drupal) {
  Drupal.arc = Drupal.arc || {};

  Drupal.behaviors.arcBehavior = {
    attach: function (context, settings) {
      $(window).once().on("load", function () {
        Drupal.arc.placeholder();
        Drupal.arc.arcSlider();
      });
    }
  };

  Drupal.arc.placeholder = function () {
    $('#edit-mail-0-value').once('addPlaceHolder').attr('placeholder', 'Your email');
  };

  Drupal.arc.arcSlider = function () {
    var slider = $('.banner-home-page .view-rows');
    if (slider.length) {
      slider.slick({
        dots: true,
        arrows : true,
        infinite: true,
      });
    }
  };

})(jQuery, Drupal);
