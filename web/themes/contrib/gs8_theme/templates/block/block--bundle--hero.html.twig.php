<div class="jumbotron jumbotron-fluid" style="background-image: url({{ file_url(content.field_background_image['#items'].entity.uri.value) }}); background-size: cover; background-position: center" >
  <div class="container">
    <div class="row">
      <div class="col-md-6">
        {{ title_prefix }}
        {% if label %}
          <h2{{ title_attributes.addClass('display-4') }}>{{ label }}</h2>
        {% endif %}
        {{ title_suffix }}
        <div class="lead">{{ content.body }}</div>
        <div><a href="{{ content.field_button[0]['#url'] }}" class="btn btn-primary">{{ content.field_button[0]['#title'] }}</a></div>
      </div>
    </div>
  </div>
</div>