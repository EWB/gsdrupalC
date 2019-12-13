(function($, Drupal, drupalSettings) {

  Drupal.behaviors.geocode_origin_autocomplete = {
    attach: function(context, settings) {

      function query_url_serialize(obj, prefix) {
        var str = [], p;
        for (p in obj) {
          if (obj.hasOwnProperty(p)) {
            var k = prefix ? prefix + "[" + p + "]" : p,
              v = obj[p];
            str.push((v !== null && typeof v === "object") ?
              Drupal.geoFieldMap.query_url_serialize(v, k) :
              encodeURIComponent(k) + "=" + encodeURIComponent(v));
          }
        }
        return str.join("&");
      };

      function geocode (address, providers, options) {
        var base_url = drupalSettings.path.baseUrl;
        var geocode_path = base_url + 'geocoder/api/geocode';
        options = query_url_serialize(options);
        return $.ajax({
          url: geocode_path + '?address=' +  encodeURIComponent(address) + '&geocoder=' + providers + '&' + options,
          type:"GET",
          contentType:"application/json; charset=utf-8",
          dataType: "json",
        });
      }

      var latitudeInput, longitudeInput, latitudeSpan, longitudeSpan = '';

      $('.origin-address-autocomplete', context).once('autocomplete-enabled').each(function () {
        latitudeInput = $(this).find('.geofield-lat').first();
        longitudeInput = $(this).find('.geofield-lon').first();
        latitudeSpan = $(this).find('.geofield-lat-summary').first();
        longitudeSpan = $(this).find('.geofield-lon-summary').first();
      });

      // Run filters on page load if state is saved by browser.
      $('.origin-address-autocomplete .address-input', context).once('autocomplete-enabled').each(function () {
        var providers = settings.geocode_origin_autocomplete.providers.toString();
        var options = settings.geocode_origin_autocomplete.options;
        $(this).autocomplete({
          autoFocus: true,
          minLength: settings.geocode_origin_autocomplete.minTerms || 4,
          delay: settings.geocode_origin_autocomplete.delay || 800,
          // This bit uses the geocoder to fetch address values.
          source: function (request, response) {
            // Execute the geocoder.
            $.when(geocode(request.term, providers, options).then(
              // On Resolve/Success.
              function (results) {
                response($.map(results, function (item) {
                  return {
                    // the value property is needed to be passed to the select.
                    value: item.formatted_address,
                    lat: item.geometry.location.lat,
                    lng: item.geometry.location.lng
                  };
                }));
              },
              // On Reject/Error.
              function() {
                response(function(){
                  return false;
                });
              }));
          },
          // This bit is executed upon selection of an address.
          select: function (event, ui) {
            latitudeInput.val(ui.item.lat);
            longitudeInput.val(ui.item.lng);
            latitudeSpan.text(ui.item.lat);
            longitudeSpan.text(ui.item.lng);
          }
        });

        // Geocode and Fill the Lat / Lng parameters in case of default geocode
        // address.
        if($(this).val().length && latitudeInput.val().length === 0 && longitudeInput.val().length === 0) {
          $.when(geocode($(this).val(), providers).then(
            // On Resolve/Success.
            function (results) {
              latitudeInput.val(results[0].geometry.location.lat);
              longitudeInput.val(results[0].geometry.location.lng);
              latitudeSpan.text(results[0].geometry.location.lat);
              longitudeSpan.text(results[0].geometry.location.lng);
            },
            // On Reject/Error.
            function() {
              return false;
            }));
        }
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
