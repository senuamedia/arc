(function ($, Drupal) {
  Drupal.arc = Drupal.arc || {};

  Drupal.behaviors.arcBehavior = {
    attach: function (context, settings) {
      $(window).once().on("load", function () {
        Drupal.arc.placeholder();
        Drupal.arc.arcSlider();
        Drupal.arc.arcPhotosSlider();
        Drupal.arc.whatOurVolunteerSay(); 
        Drupal.arc.masonryPhotography();
        Drupal.arc.attachmentBanner();
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
    slider.slick({
      infinite: true,
      arrows : true,
      slidesToShow: 4,
      slidesToScroll: 1,
    });
  };

  Drupal.arc.whatOurVolunteerSay = function () {
    var header = $('.content-top #block-views-block-what-our-volunteer-say-block-1 div.form-group div div.view-header');
    var select = header.find('div[class="what-our-volunteer-say-text"]');
    var showElement = header.parent().find('div[class="view-content"]');
    if (!showElement.hasClass('show')) {
      showElement.addClass('show');
      select.addClass('show');
    }

    select.once().click(function () {
      if (showElement.hasClass('show')) {
        showElement.removeClass('show');
        select.removeClass('show');
      }
      else {
        showElement.addClass('show');
        select.addClass('show');
      }
    });
  };

  Drupal.arc.masonryPhotography = function () {
    var grid = $('#block-views-block-gallery-block-1 .views-field-field-photos div');
    var gridItem = grid.find('div[class="grid-item"]');
    
    grid.masonry({
      itemSelector: '.grid-item',
      columnWidth: 300
    });
  };

  Drupal.arc.attachmentBanner = function () {
    var divContainer = $('.layout-main-wrapper.layout-container div.container div.region.region-content div.views-element-container div');
    var hasAttachment= divContainer.find('div').hasClass('attachment-before');

    if (hasAttachment) {
      if ($('body').removeClass('attachment-banner')){
        $('body').addClass('attachment-banner');
      }
    }
  };

})(jQuery, Drupal);
