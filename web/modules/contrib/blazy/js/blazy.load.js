/**
 * @file
 * Provides Intersection Observer API or bLazy loader.
 */

(function (Drupal, drupalSettings, _db, window, document) {

  'use strict';

  /**
   * Blazy public methods.
   *
   * @namespace
   */
  Drupal.blazy = Drupal.blazy || {
    init: null,
    windowWidth: 0,
    blazySettings: drupalSettings.blazy || {},
    ioSettings: drupalSettings.blazyIo || {},
    options: {},
    globals: function () {
      var me = this;
      var commons = {
        success: me.clearing.bind(me),
        error: me.clearing.bind(me),
        selector: '.b-lazy',
        errorClass: 'b-error',
        successClass: 'b-loaded'
      };

      return _db.extend(me.blazySettings, me.ioSettings, commons);
    },

    clearing: function (el) {
      var me = this;
      var ie = el.classList.contains('b-responsive') && el.hasAttribute('data-pfsrc');

      // The .b-lazy element can be attached to IMG, or DIV as CSS background.
      el.className = el.className.replace(/(\S+)loading/, '');

      // The .is-loading can be .grid, .slide__content, .box__content, etc.
      var loaders = [
        _db.closest(el, '.is-loading'),
        _db.closest(el, '[class*="loading"]')
      ];

      // Also cleans up closest containers containing loading class.
      _db.forEach(loaders, function (wrapEl) {
        if (wrapEl !== null) {
          wrapEl.className = wrapEl.className.replace(/(\S+)loading/, '');
        }
      });

      // @see http://scottjehl.github.io/picturefill/
      if (window.picturefill && ie) {
        window.picturefill({
          reevaluate: true,
          elements: [el]
        });
      }

      // Provides event listeners for easy overrides without full overrides.
      _db.trigger(el, 'blazy.done', {options: me.options});
    },

    isIo: function () {
      return this.ioSettings && this.ioSettings.enabled && 'IntersectionObserver' in window;
    },

    isBlazy: function () {
      return !this.isIo() && 'Blazy' in window;
    },

    run: function (opts) {
      return this.isIo() ? new BioMedia(opts) : new Blazy(opts);
    }
  };

  /**
   * Initialize the default blazy instance.
   */
  var initBlazyDefault = function () {
    var me = Drupal.blazy;
    me.options = me.globals();
    me.init = me.run(me.options);
  };

  /**
   * Setup all blazy elements.
   */
  function doBlazyDefault(context) {
    var me = Drupal.blazy;
    initBlazyDefault();
    if (typeof me.init.options.selector !== 'undefined' && me.init.options.selector !== null) {
      var blazies = context.querySelectorAll(me.init.options.selector + ':not(.' + me.init.options.successClass + ')');
      if (blazies.length > 0) {
        _db.once(_db.forEach(blazies, doBlazy));
      }
    }
  }

  /**
   * Blazy utility functions.
   *
   * @param {HTMLElement} elm
   *   The Blazy HTML element.
   */
  function doBlazy(elm) {
    var me = Drupal.blazy;
    var dataAttr = elm.getAttribute('data-blazy');
    var hasDefaultOptions = !dataAttr || dataAttr === '1';
    var data = hasDefaultOptions ? {} : _db.parse(dataAttr);
    var opts = _db.extend({}, me.globals(), data);
    // Set docroot in case we are in an iframe.
    // @see Blazy.toArray
    var documentElement = _db.closest(elm, 'html');
    if (!document.documentElement.isSameNode(documentElement)) {
      opts.root = documentElement;
      hasDefaultOptions = false;
    }
    var ratios = elm.querySelectorAll('[data-dimensions]');
    var loopRatio = ratios.length > 0;
    var fallbackRatios = elm.querySelectorAll('[data-ratio]');
    var loopFallbackRatio = fallbackRatios.length > 0;

    /**
     * Updates the dynamic multi-breakpoint aspect ratio.
     *
     * This only applies to multi-serving images with aspect ratio fluid if
     * each element contains [data-dimensions] attribute.
     * Static single aspect ratio, e.g. `media--ratio--169`, will be ignored,
     * and will use CSS instead.
     *
     * @param {HTMLElement} el
     *   The .media--ratio--fluid|enforced HTML element.
     */
    function updateRatio(el) {
      var dimensions = !el.getAttribute('data-dimensions') ? false : _db.parse(el.getAttribute('data-dimensions'));

      if (!dimensions) {
        return;
      }

      var keys = Object.keys(dimensions);
      var xs = keys[0];
      var xl = keys[keys.length - 1];
      var mw = function (w) {
        return w >= me.windowWidth;
      };
      var pad = keys.filter(mw).map(function (v) {
        return dimensions[v];
      }).shift();

      if (pad === 'undefined') {
        pad = dimensions[me.windowWidth >= xl ? xl : xs];
      }

      if (pad !== 'undefined') {
        el.style.paddingBottom = pad + '%';
      }

      el.removeAttribute('data-ratio');
    }

    /**
     * Fix for Twig inline_template and Views rewrite striping out style.
     *
     * @param {HTMLElement} el
     *   The .media--ratio--fluid|enforced HTML element.
     */
    function updateFallbackRatio(el) {
      // Only rewrites if the style is indeed stripped out by Twig, and not set.
      if (!el.hasAttribute('style')) {
        el.style.paddingBottom = el.getAttribute('data-ratio') + '%';
      }
      el.removeAttribute('data-ratio');
    }

    // Initializes IntersectionObserver or Blazy instance.
    if (hasDefaultOptions) {
      initBlazyDefault();
    }
    else {
      me.options = opts;
      me.init = me.run(opts);
    }

    // Reacts on resizing per 200ms, and the magic () also does it on page load.
    _db.resize(function () {
      me.windowWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth || window.screen.width;

      if (loopRatio) {
        _db.forEach(ratios, updateRatio, elm);
      }
      else if (loopFallbackRatio) {
        _db.forEach(fallbackRatios, updateFallbackRatio, elm);
      }

      // BC with bLazy, IO doesn't need to revalidate, Slick multiple-view does.
      if (me.isBlazy() || elm.classList.contains('blazy--revalidate')) {
        me.init.revalidate(true);
      }
    })();

    elm.classList.add('blazy--on');
  }

  /**
   * Attaches blazy behavior to HTML element identified by [data-blazy].
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazy = {
    attach: function (context) {
      // Drupal.attachBehaviors already does this so if this is necessary, someone
      // does an invalid call. But let's be robust here.
      context = context || document;
      var el = context.querySelector('[data-blazy]');

      // Runs basic Blazy if no [data-blazy] found, probably a single image or
      // a theme that does not use field attributes.
      // Cannot use .contains(), as IE11 doesn't support method 'contains'.
      if (el === null) {
        doBlazyDefault(context);
        return;
      }

      // Runs Blazy with multi-serving images, and aspect ratio supports.
      var blazies = context.querySelectorAll('.blazy:not(.blazy--on)');
      _db.once(_db.forEach(blazies, doBlazy));
    }
  };

}(Drupal, drupalSettings, dBlazy, this, this.document));
