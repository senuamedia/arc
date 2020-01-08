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

      $(document).on("resize", function() {
        Drupal.arc.mobileMenu();
      });

      $(document).ready(function() {
        Drupal.arc.topicSubTerm();
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
    var sld = $('div.content-top .photos-slider');
    if (slider) { 
      slider.slick({
        infinite: true,
        arrows : true,
        slidesToShow: 4,
        slidesToScroll: 1,
        responsive: [{
           breakpoint: 991,
           settings: {
              touchMove: true,
              slidesToShow: 3,
              slidesToScroll: 1
           }
        },{
          breakpoint: 600,
          settings: {
            slidesToShow: 2,
            slidesToScroll: 1,
            touchMove: true,
          }
        },
        {
           breakpoint: 400,
           settings: {
              arrows: false,
              slidesToShow: 1,
              slidesToScroll: 1,
              touchMove: true,
           }
        }]
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
    var grid = $('.details-gallery div.view-content');
    if (!grid.find("div.grid-sizer").length) {
      grid.prepend('<div class="grid-sizer"></div>');
    }
    var gridItem = grid.find('div[class="grid-item"]');
    
    grid.masonry({
      itemSelector: '.grid-item',
      columnWidth: '.grid-sizer',
      percentPosition: true,
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
      var buttonMenu = $("header .collapse");
      buttonMenu.on("show.bs.collapse", function() {
        header.addClass("mobile-menu");
      });

      buttonMenu.on("hide.bs.collapse", function() {
        header.removeClass("mobile-menu");
      });
    }
    //  else {
    //   header.removeClass("mobile-menu");
    // }
  };

  Drupal.arc.topicSubTerm = function () {
    var rows = $(".research-topic-arc .view-content .views-row").find(".term-relation").filter("[ptid!='']");

    rows.each(function() {
      var ptid = $(this).attr("ptid");
      var currentRow = $(this).closest("div.views-row");
      var content = $(this).closest("div.view-content");
      var parent = content.find(".term-child").filter("[tid=" + ptid + "]");
      $(currentRow).detach().appendTo(parent);
    });
  };

})(jQuery, Drupal);
