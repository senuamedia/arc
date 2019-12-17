(function ($, Drupal) {
  Drupal.arc = Drupal.arc || {};

  Drupal.behaviors.arcBehavior = {
    attach: function (context, settings) {
      $(window).once().on("load", function () {
        Drupal.arc.placeholder();
        Drupal.arc.arcSlider();
        Drupal.arc.arcPhotosSlider();
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

  Drupal.arc.arcPhotosSlider = function () {
    var slider = $('.photos-slider .view-content');
    if (slider.length) {
      slider.slick({
        infinite: true,
        arrows : true,
        slidesToShow: 4,
        slidesToScroll: 4,
      });
    }
  };

})(jQuery, Drupal);
