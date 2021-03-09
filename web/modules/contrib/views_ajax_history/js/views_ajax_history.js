/**
 * @file
 * Contains views_ajax_history.js.
 */

(function ($, Drupal, drupalSettings) {

  // Need to keep this to check if there are extra parameters in the original URL.
  var original = {
    path: window.location.protocol + "//" + window.location.hostname + (window.location.port ? ':' + window.location.port : '') + window.location.pathname,
    // @TODO integrate #1359798 without breaking history.js
    query: window.location.search || ''
  };

  /**
   * Keep the original beforeSubmit method to use it later.
   */
  var beforeSubmit = Drupal.Ajax.prototype.beforeSubmit;

  /**
   * Keep the original beforeSerialize method to use it later.
   */
  var beforeSerialize = Drupal.Ajax.prototype.beforeSerialize;

  /**
   * Keep the original beforeSend method to use it later.
   */
  var beforeSend = Drupal.Ajax.prototype.beforeSend;

  Drupal.behaviors.viewsAjaxHistory = {
    attach: function (context, drupalSettings) {
      // Init the current page too, because the first loaded pager element do
      // not have loadable history and will not work the back button.
      var $body = $('body').once('views-ajax-history-first-page-load');
      if ($body.length) {
        drupalSettings.viewsAjaxHistory.onloadPageItem = drupalSettings.viewsAjaxHistory.renderPageItem;
      }
    }
  };

  /**
   * Modification of Drupal.Views.parseQueryString() to allow extracting multivalues fields.
   *
   * @param query
   *   String, either a full url or just the query string.
   */
  var parseQueryString = function (query) {
    var args = {};
    var pos = query.indexOf('?');
    if (pos != -1) {
      query = query.substring(pos + 1);
    }
    var pairs = query.split('&');
    var pair, key, value;
    for (var i in pairs) {
      if (typeof(pairs[i]) == 'string') {
        pair = pairs[i].split('=');
        // Ignore the 'q' path argument, if present.
        if (pair[0] != 'q' && pair[1]) {
          key = decodeURIComponent(pair[0].replace(/\+/g, ' '));
          value = decodeURIComponent(pair[1].replace(/\+/g, ' '));
          // Field name ends with [], it's multivalues.
          if (/\[\]$/.test(key)) {
            if (!(key in args)) {
              args[key] = [value];
            }
            // Don't duplicate values.
            else if (!$.inArray(value, args[key]) !== -1) {
              args[key].push(value);
            }
          }
          else {
            args[key] = value;
          }
        }
      }
    }
    return args;
  };

  /**
   * Strip views values and duplicates from URL.
   *
   * @param url
   *   String with the full URL to clean up.
   * @param viewArgs
   *   Object containing field values from views.
   *
   * @return url
   *   String URL with views values and reduced duplicates.
   */
  var cleanURL = function (url, viewArgs) {
    var args = parseQueryString(url);
    var query = [];

    // With clean urls off we need to add the 'q' parameter.
    if (/\?/.test(drupalSettings.views.ajax_path)) {
      query.push('q=' + Drupal.Views.getPath(url));
    }

    $.each(args, function (name, value) {
      // Use values from viewArgs if they exists.
      if (name in viewArgs) {
        value = viewArgs[name];
      }
      if ($.isArray(value)) {
        $.merge(query, $.map(value, function (sub) {
          return name + '=' + encodeURIComponent(sub);
        }));
      }
      else {
        query.push(name + '=' + encodeURIComponent(value));
      }
    });

    url = window.location.href.split('?');
    return url[0] + (query.length ? '?' + query.join('&') : '');
  };

  /**
   * Parse a URL query string.
   *
   * @param queryString
   *   String containing the query to parse.
   */
  var parseQuery = function (queryString) {
    var query = {};
    $.map(queryString.split('&'), function (val) {
      var s = val.split('=');
      query[s[0]] = s[1];
    });
    return query;
  };

  /**
   * Unbind 'popstate' when adding a new state to avoid an infinite loop.
   *
   * We only use the 'popstate' event to trigger refresh on back of forward click.
   *
   * @param options
   *   Object containing the values from views' AJAX call.
   * @param url
   *   String with the current URL to be cleaned up.
   */
  var addState = function (options, url) {
    // The data in the history state must be serializable.
    var historyOptions = $.extend({}, options)
    delete historyOptions.beforeSend;
    delete historyOptions.beforeSerialize;
    delete historyOptions.beforeSubmit;
    delete historyOptions.complete;
    delete historyOptions.success;

    // Store the actual view's dom id.
    drupalSettings.viewsAjaxHistory.lastViewDomID = options.data.view_dom_id;
    $(window).unbind('popstate', loadView);
    history.pushState(historyOptions, document.title, cleanURL(url, options.data));
    $(window).bind('popstate', loadView);
  };

  /**
   * Make an AJAX request to update the view when navigating back and forth.
   */
  var loadView = function () {
    var options;

    // This should be the first loaded page, so init the options object.
    if (history.state === null) {
      var viewsAjaxSettingsKey = 'views_dom_id:' + drupalSettings.viewsAjaxHistory.lastViewDomID;
      if (drupalSettings.views.ajaxViews.hasOwnProperty(viewsAjaxSettingsKey)) {
        var viewsAjaxSettings = drupalSettings.views.ajaxViews[viewsAjaxSettingsKey];
        viewsAjaxSettings.page = drupalSettings.viewsAjaxHistory.onloadPageItem;
        options = {
          data: viewsAjaxSettings,
          url: drupalSettings.views.ajax_path
        };
      }
    }
    else {
      options = history.state;
    }

    // Drupal's AJAX options.
    var settings = $.extend({
      submit: options.data,
      setClick: true,
      event: 'click',
      selector: '.view-dom-id-' + options.data.view_dom_id,
      progress: { type: 'throbber' }
    }, options);

    var viewsAjaxSubmit = Drupal.ajax(settings);
    // Trigger ajax call.
    viewsAjaxSubmit.execute();
  };

  /**
   * Override beforeSerialize to handle click on pager links.
   *
   * @param $element
   *   jQuery DOM element
   * @param options
   */
  Drupal.Ajax.prototype.beforeSerialize = function (element, options) {
    // Check that we handle a click on a link, not a form submission.
    if (options.data.view_name && element && $(element).is('a')) {
      addState(options, $(element).attr('href'));
    }

    // Call the original Drupal method with the right context.
    beforeSerialize.apply(this, arguments);
  };

  /**
   * Override beforeSubmit to handle exposed form submissions.
   *
   * @param form_values
   *   Object with all field values.
   * @param element
   *   jQuery DOM form element.
   * @param options
   *   Object containing AJAX options.
   */
  Drupal.Ajax.prototype.beforeSubmit = function (form_values, element, options) {
    if (options && options.data && options.data.view_name) {
      var url = original.path + '?' + element.formSerialize();
      var currentQuery = parseQueryString(window.location.href);

      // Remove the page number from the query string, as a new filter has been
      // applied and should return new results.
      if ($.inArray("page", Object.keys(currentQuery)) !== -1) {
        delete currentQuery.page;
      }

      // Copy selected values in history state.
      $.each(form_values, function () {
        // Field name ending with [] is a multi value field.
        if (/\[\]$/.test(this.name)) {
          if (!options.data[this.name]) {
            options.data[this.name] = [];
          }
          options.data[this.name].push(this.value);
        }
        // Regular field.
        else {
          options.data[this.name] = this.value;
        }
      });
      // Remove exposed data from the current query to leave behind any
      // non exposed form related query vars.
      element.find('[name]').each(function () {
        if (currentQuery[this.name]) {
          delete currentQuery[this.name];
        }
      });

      // If the exposed form has checkboxes, we need to check if these are
      // unchecked and if so, remove them from the url
      element.find('input[type="checkbox"]').each(function (key, value) {
        if (!form_values[this.name]) {
          if (currentQuery[this.name]) {
            delete currentQuery[this.name];
          }
          else if (options.data[this.name]) {
            delete options.data[this.name];
          }
        }
      });

      // If the exposed form has a multiple select.
      element.find('.form-select[multiple=multiple]').each(function (key, value) {
        if ($(value).val().length === 0) {
          delete options.data[this.name];
          delete currentQuery[this.name];
        }
      });

      url += (/\?/.test(url) ? '&' : '?') + $.param(currentQuery);
      addState(options, url);
    }
    // Call the original Drupal method with the right context.
    beforeSubmit.apply(this, arguments);
  };

  /**
   * Override beforeSend to clean up the Ajax submission URL.
   *
   * @param {XMLHttpRequest} xmlhttprequest
   *   Native Ajax object.
   * @param {object} options
   *   jQuery.ajax options.
   */
  Drupal.Ajax.prototype.beforeSend = function (xmlhttprequest, options) {
    var data = (typeof options.data === 'string') ? parseQuery(options.data) : {};

    if (data.view_name && options.type !== 'GET') {
      // Override the URL to not contain any fields that were submitted.
      var delimiter = drupalSettings.views.ajax_path.indexOf('?') === -1 ? '?' : '&';
      options.url = drupalSettings.views.ajax_path + delimiter + Drupal.ajax.WRAPPER_FORMAT + '=drupal_ajax';
    }
    // Call the original Drupal method with the right context.
    beforeSend.apply(this, arguments);
  }

})(jQuery, Drupal, drupalSettings);
