/**
 * @file
 * Provides Intersection Observer API loader for media.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 * @see https://developers.google.com/web/updates/2016/04/intersectionobserver
 */

/* global window, document, define, module */
(function (root, factory) {

  'use strict';

  // Inspired by https://github.com/addyosmani/memoize.js/blob/master/memoize.js
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define([window.dBlazy, window.Bio], factory);
  }
  else if (typeof exports === 'object') {
    // Node. Does not work with strict CommonJS, but only CommonJS-like
    // environments that support module.exports, like Node.
    module.exports = factory(window.dBlazy, window.Bio);
  }
  else {
    // Browser globals (root is window).
    root.BioMedia = factory(window.dBlazy, window.Bio);
  }
})(this, function (dBlazy, Bio) {

  'use strict';

  /**
   * Private variables.
   */
  var _db = dBlazy;
  var _bio = Bio;
  var _src = 'src';
  var _srcSet = 'srcset';
  var _bgSrc = 'data-src';
  var _dataSrc = 'data-src';
  var _dataSrcset = 'data-srcset';
  var _bgSources = [_src];
  var _imgSources = [_srcSet, _src];

  /**
   * Constructor for BioMedia, Blazy IntersectionObserver for media.
   *
   * @param {object} options
   *   The BioMedia options.
   *
   * @return {object}
   *   The BioMedia instance.
   *
   * @namespace
   */
  function BioMedia(options) {
    return _bio.apply(this, arguments);
  }

  // Inherits Bio prototype.
  var _proto = BioMedia.prototype = Object.create(Bio.prototype);
  _proto.constructor = BioMedia;

  _proto.removeAttrs = function (el, attrs) {
    _db.forEach(attrs, function (attr) {
      el.removeAttribute('data-' + attr);
    });
  };

  _proto.setAttrs = function (el, attrs) {
    var me = this;

    _db.forEach(attrs, function (src) {
      me.setAttr(el, src);
    });
  };

  _proto.setAttr = function (el, attr, remove) {
    if (el.hasAttribute('data-' + attr)) {
      var dataAttr = el.getAttribute('data-' + attr);
      if (attr === _src) {
        el.src = dataAttr;
      }
      else {
        el.setAttribute(attr, dataAttr);
      }

      if (remove) {
        el.removeAttribute('data-' + attr);
      }
    }
  };

  _proto.prepare = (function (_bio) {
    return function () {
      var me = this;

      // DIV elements with multi-serving CSS background images.
      if (me.options.breakpoints) {
        var _bgSrcs = [];

        _db.forEach(me.options.breakpoints, function (object) {
          _bgSources.push(object.src.replace('data-', ''));

          // We have several values here, the last wins, but not good.
          // The original bLazy uses max-width, stick to it. The custom aspect
          // ratio works were also already based on this decision.
          if (object.width >= me.windowWidth) {
            _bgSrc = object.src;
            _bgSrcs.push(_bgSrc);
            return false;
          }
        });

        // This part is the betterment to the original bLazy.
        // Fetches the nearest to window width, not the farthest/ largest.
        // Not always available when the window is larger than the last item.
        // In such cases, this is easily fixed via configuration UI.
        if (_bgSrcs.length > 0) {
          _bgSrc = _bgSrcs[0];
        }
      }

      return _bio.call(this);
    };
  })(_proto.prepare);

  _proto.lazyLoad = (function (_bio) {
    return function (el) {
      // Image may take time to load after being hit, and it may be intersected
      // several times till marked loaded. Ensures it is hit once regardless
      // of being loaded, or not. No real issue with normal images on the page,
      // until having VIS alike which may spit out new images on AJAX request.
      if (el.hasAttribute('data-bio-hit')) {
        return;
      }

      var me = this;
      var parent = el.parentNode;
      var isImage = me.equal(el, 'img');
      var isBg = typeof el.src === 'undefined' && el.classList.contains(me.options.bgClass);
      var isPicture = parent && me.equal(parent, 'picture');
      var isVideo = me.equal(el, 'video');

      // PICTURE elements.
      if (isPicture) {
        _db.forEach(parent.getElementsByTagName('source'), function (source) {
          me.setAttr(source, _srcSet, true);
        });
        // Tiny controller image inside picture element won't get preloaded.
        me.setAttr(el, _src, true);
        me.loaded(el, me._ok);
      }
      // VIDEO elements.
      else if (isVideo) {
        _db.forEach(el.getElementsByTagName('source'), function (source) {
          me.setAttr(source, _src, true);
        });
        el.load();
        me.loaded(el, me._ok);
      }
      else {
        // IMG or DIV/ block elements got preloaded for better UX with loading.
        if (isImage || isBg) {
          me.setImage(el, isBg);
        }
        // IFRAME elements, etc.
        else {
          if (el.getAttribute(_dataSrc) && el.hasAttribute(_src)) {
            me.setAttr(el, _src, true);
            me.loaded(el, me._ok);
          }
        }
      }

      // Marks it hit/ requested. Not necessarily loaded.
      el.setAttribute('data-bio-hit', 1);

      return _bio.apply(this, arguments);
    };
  })(_proto.lazyLoad);

  _proto.promise = function (el, isBg) {
    var me = this;

    return new Promise(function (resolve, reject) {
      var img = new Image();

      // Preload `img` to have correct event handlers.
      img.src = el.getAttribute(isBg ? _bgSrc : _dataSrc);
      if (el.hasAttribute(_dataSrcset)) {
        img.srcset = el.getAttribute(_dataSrcset);
      }

      // Applies attributes regardless, will re-observe if any error.
      var applyAttrs = function () {
        if (isBg) {
          me.setBg(el);
        }
        else {
          me.setAttrs(el, _imgSources);
        }
      };

      // Handle onload event.
      img.onload = function () {
        applyAttrs();
        resolve(me._ok);
      };

      // Handle onerror event.
      img.onerror = function () {
        applyAttrs();
        reject(me._er);
      };
    });
  };

  _proto.setImage = function (el, isBg) {
    var me = this;

    return me.promise(el, isBg)
      .then(function (status) {
        me.loaded(el, status);
        me.removeAttrs(el, isBg ? _bgSources : _imgSources);
      })
      .catch(function (status) {
        me.loaded(el, status);

        el.removeAttribute('data-bio-hit');
      })
      .finally(function () {
        // Be sure to throttle, or debounce your method when calling this.
        _db.trigger(el, 'bio.finally', {options: me.options});
      });
  };

  _proto.setBg = function (el) {
    if (el.hasAttribute(_bgSrc)) {
      el.style.backgroundImage = 'url("' + el.getAttribute(_bgSrc) + '")';
      el.removeAttribute(_src);
    }
  };

  return BioMedia;

});
