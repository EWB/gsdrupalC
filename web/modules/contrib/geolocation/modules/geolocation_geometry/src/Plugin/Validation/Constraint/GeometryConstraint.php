<?php

namespace Drupal\geolocation_geometry\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validation constraint for geospatial values.
 *
 * @Constraint(
 *   id = "GeometryType",
 *   label = @Translation("Geometry data valid for geofield type.", context = "Validation"),
 * )
 */
class GeometryConstraint extends Constraint {
  public $messageType = '"@value" is not a valid @type.';
  public $messageGeom = '"@value" is not a valid geospatial content for @geom_type geometry.';
  public $type = 'wkt';
  public $geomType = 'geometry';

}
