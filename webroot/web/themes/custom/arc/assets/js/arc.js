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
        Drupal.arc.backToTop();
        Drupal.arc.soundCollections();
        Drupal.arc.mobileMenu();
      });

      // $(window).on('resize', function() {
      //   Drupal.arc.mobileMenu();
      // });
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
    var sld = $('div.content-top .photos-slider');
    if (slider) { 
      slider.slick({
        infinite: true,
        arrows : true,
        slidesToShow: 4,
        slidesToScroll: 1,
        centerMode: true,
      });

      if (sld.hasClass("photos-slider")){
        $("body").addClass("photos-detail");
      }
    }
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
    var grid = $('.details-gallery');
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

  Drupal.arc.backToTop = function () {
    $(window).scroll(function() {
      if ($(this).scrollTop()) {
          $('.backtotop-wrapper #back-to-top').fadeIn();
      } else {
          $('.backtotop-wrapper #back-to-top').fadeOut();
      }
    });

    $(".backtotop-wrapper #back-to-top").click(function() {
      $("html, body").animate({scrollTop: 0}, 1000);
    });
  };

  Drupal.arc.soundCollections = function () {
    var rows = $("div.sound-collections .view-content .views-row");

    rows.on("click", ".views-field-field-image", function() {
      var row = $(this).closest(".views-row");
      var audio = row.find("audio");

      audio.on('ended', function() {
        audio.removeClass("playing");
      });
      
      if (!audio.hasClass("playing") || audio.hasClass("paused")) {
        audio.get(0).play();
        audio.removeClass("paused");
        audio.addClass("playing");
      } else {
        audio.get(0).pause();
        audio.removeClass("playing");
        audio.addClass("paused");
      }
    });
  };

  Drupal.arc.mobileMenu = function () {
    var width = $(window).width();
    var header = $("header");
    if (width <= 991) {
      var buttonMenu = $("header div.navbar-header button.navbar-toggle");
      buttonMenu.click(function() {
        if (header.hasClass("mobile-menu")) {
          header.removeClass("mobile-menu");
        } else {
          header.addClass("mobile-menu");
        }
      });
    }
    //  else {
    //   header.removeClass("mobile-menu");
    // }
  };

})(jQuery, Drupal);
