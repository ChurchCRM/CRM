(function(factory) {
  if (typeof define === 'function' && define.amd) {
    define(['jquery', 'randomcolor'], factory);
  } else if (typeof module === 'object' && module.exports) {
    module.exports = factory(require('jquery'), require('randomcolor'));
  } else {
    // Browser globals
    factory(jQuery, randomColor);
  }
}(function($, randomColor) {
  $.fn.initial = function(options) {
    return this.each(function() {
      
      var e = $(this);
      if (e.attr("src"))
      {
        //do not process images which already have a SRC attribute
        return;
      }
      var settings = $.extend({
        // Default settings
        name: 'Name',
        seed: 0,
        charCount: 1,
        wordCount: 2,
        textColor: '#ffffff',
        height: 100,
        width: 100,
        fontSize: 60,
        fontWeight: 400,
        fontFamily: 'HelveticaNeue-Light,Helvetica Neue Light,Helvetica Neue,Helvetica, Arial,Lucida Grande, sans-serif',
        radius: 0,
        src: null

      }, options);

      // overriding from data attributes
      settings = $.extend(settings, e.data());
      
      
      if (settings.src) {
        $.ajax({
        method :"HEAD",
        url: settings.src,
        processData: false,
        global:false,
        success: function(d){
          console.log(d);
          e.attr("src", settings.src);
          return;
        },
        error: function(event) {
        }
        });
      }

      // making the text object
      var c = settings.name.split(" ", settings.wordCount).map(function(str) {
        return str.substr(0, settings.charCount).toUpperCase();
      }).join("");
      var cobj = $('<text text-anchor="middle"></text>').attr({
        'y': '50%',
        'x': '50%',
        'dy': '0.35em',
        'pointer-events': 'auto',
        'fill': settings.textColor,
        'font-family': settings.fontFamily
      }).html(c).css({
        'font-weight': settings.fontWeight,
        'font-size': settings.fontSize + 'px'
      });

      var svg = $('<svg></svg>').attr({
        'xmlns': 'http://www.w3.org/2000/svg',
        'pointer-events': 'none',
        'width': settings.width,
        'height': settings.height
      }).css({
        'background-color': randomColor({
          seed: settings.name
        }),
        'width': settings.width + 'px',
        'height': settings.height + 'px',
        'border-radius': settings.radius + 'px',
        '-moz-border-radius': settings.radius + 'px'
      });

      svg.append(cobj);
      // svg.append(group);
      var svgHtml = window.btoa(unescape(encodeURIComponent($('<div>').append(svg.clone()).html())));

      e.attr("src", 'data:image/svg+xml;base64,' + svgHtml);
    });
  };
}));
