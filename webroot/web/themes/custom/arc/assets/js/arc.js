(function ($, Drupal) {
  Drupal.arc = Drupal.arc || {};

  Drupal.behaviors.arcBehavior = {
    attach: function (context, settings) {
      $(window).once().on("load", function () {
        Drupal.arc.placeholder();
        Drupal.arc.arcSlider();
        Drupal.arc.arcPhotosSlider();
        Drupal.arc.whatOurVolunteerSay(); 
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

  Drupal.arc.whatOurVolunteerSay = function () {
    var header = $('.content-top #block-views-block-what-our-volunteer-say-block-1 div.form-group div div.view-header');
    var select = header.find('div[class="what-our-volunteer-say"]');
    var showElement = header.parent().find('div[class="view-content"]');

    select.once().click(function () {
      if (showElement.hasClass('show')) {
        showElement.removeClass('show');
      }
      else {
        showElement.addClass('show');
      }
    });
  };

})(jQuery, Drupal);
