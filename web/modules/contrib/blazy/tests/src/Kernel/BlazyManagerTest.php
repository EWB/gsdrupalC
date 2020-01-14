<?php

namespace Drupal\Tests\blazy\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;

/**
 * Tests the Blazy manager methods.
 *
 * @coversDefaultClass \Drupal\blazy\BlazyManager
 * @requires module media
 *
 * @group blazy
 */
class BlazyManagerTest extends BlazyKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $bundle = $this->bundle;

    $settings['fields']['field_text_multiple'] = 'text';

    $this->setUpContentTypeTest($bundle, $settings);
    $this->setUpContentWithItems($bundle);
    $this->setUpRealImage();
  }

  /**
   * Tests BlazyManager image.
   *
   * @param array $settings
   *   The settings being tested.
   * @param bool $expected_has_responsive_image
   *   Has the responsive image style ID.
   *
   * @covers ::preRenderImage
   * @covers \Drupal\blazy\BlazyLightbox::build
   * @covers \Drupal\blazy\BlazyLightbox::buildCaptions
   * @dataProvider providerTestPreRenderImage
   */
  public function testPreRenderImage(array $settings = [], $expected_has_responsive_image = FALSE) {
    $build             = $this->data;
    $settings['count'] = $this->maxItems;
    $settings['uri']   = $this->uri;
    $build['settings'] = array_merge($build['settings'], $settings);
    $switch_css        = str_replace('_', '-', $settings['media_switch']);

    $element = $this->doPreRenderImage($build);

    if ($settings['media_switch'] == 'content') {
      $this->assertEquals($settings['content_url'], $element['#url']);
      $this->assertArrayHasKey('#url', $element);
    }
    else {
      $this->assertArrayHasKey('data-' . $switch_css . '-trigger', $element['#url_attributes']);
      $this->assertArrayHasKey('#url', $element);
    }

    $this->assertEquals($expected_has_responsive_image, !empty($element['#image']['#responsive_image_style_id']));
  }

  /**
   * Provide test cases for ::testPreRenderImage().
   *
   * @return array
   *   An array of tested data.
   */
  public function providerTestPreRenderImage() {
    $data[] = [
      [
        'content_url'  => 'node/1',
        'media_switch' => 'content',
      ],
      FALSE,
    ];
    $data[] = [
      [
        'lightbox'               => TRUE,
        'media_switch'           => 'photobox',
        'resimage'               => TRUE,
        'responsive_image_style' => 'blazy_responsive_test',
      ],
      TRUE,
    ];
    $data[] = [
      [
        'box_style'          => 'blazy_crop',
        'box_media_style'    => 'large',
        'box_caption'        => 'custom',
        'box_caption_custom' => '[node:field_text_multiple]',
        'embed_url'          => '//www.youtube.com/watch?v=E03HFA923kw',
        'lightbox'           => TRUE,
        'media_switch'       => 'blazy_test',
        'scheme'             => 'youtube',
        'type'               => 'video',
      ],
      FALSE,
    ];

    return $data;
  }

  /**
   * Tests building Blazy attributes.
   *
   * @param array $settings
   *   The settings being tested.
   * @param bool $use_uri
   *   Whether to provide image URI, or not.
   * @param object $item
   *   Whether to provide image item, or not.
   * @param bool $iframe
   *   Whether to expect an iframe, or not.
   * @param mixed|bool|int $expected
   *   The expected output.
   *
   * @covers \Drupal\blazy\Blazy::buildAttributes
   * @covers \Drupal\blazy\Blazy::buildBreakpointAttributes
   * @covers \Drupal\blazy\Blazy::buildUrlAndDimensions
   * @covers \Drupal\blazy\Dejavu\BlazyDefault::entitySettings
   * @dataProvider providerBuildAttributes
   */
  public function testBuildAttributes(array $settings, $use_uri, $item, $iframe, $expected) {
    $variables = ['attributes' => []];
    $settings = array_merge($this->getFormatterSettings(), $settings);
    $settings += BlazyDefault::itemSettings();

    $settings['blazy']           = TRUE;
    $settings['lazy']            = 'blazy';
    $settings['image_style']     = 'blazy_crop';
    $settings['thumbnail_style'] = 'thumbnail';
    $settings['uri']             = $use_uri ? $this->uri : '';

    if (!empty($settings['embed_url'])) {
      $settings = array_merge(BlazyDefault::entitySettings(), $settings);
    }

    $variables['element']['#item'] = $item ? $this->testItem : NULL;
    $variables['element']['#settings'] = $settings;

    Blazy::buildAttributes($variables);

    $image = $expected == TRUE ? !empty($variables['image']) : empty($variables['image']);
    $iframe = $iframe == TRUE ? !empty($variables['iframe_attributes']) : empty($variables['iframe_attributes']);

    $this->assertTrue($image);
    $this->assertTrue($iframe);
  }

  /**
   * Provider for ::testBuildAttributes.
   */
  public function providerBuildAttributes() {
    $breakpoints = $this->getDataBreakpoints();

    $data[] = [
      [
        'background' => FALSE,
        'breakpoints' => [],
      ],
      FALSE,
      NULL,
      FALSE,
      FALSE,
    ];
    $data[] = [
      [
        'background' => FALSE,
        'breakpoints' => [],
      ],
      FALSE,
      TRUE,
      FALSE,
      TRUE,
    ];
    $data[] = [
      [
        'background' => TRUE,
        'breakpoints' => $breakpoints,
        'ratio' => 'fluid',
        'sizes' => '100w',
        'width' => 640,
        'height' => 360,
      ],
      TRUE,
      TRUE,
      FALSE,
      FALSE,
    ];

    return $data;
  }

  /**
   * Tests responsive image integration.
   *
   * @param string $responsive_image_style_id
   *   The responsive_image_style_id.
   * @param bool $expected
   *   The expected output_image_tag.
   *
   * @dataProvider providerResponsiveImage
   */
  public function testPreprocessResponsiveImage($responsive_image_style_id, $expected) {
    $variables = [
      'item' => $this->testItem,
      'uri' => $this->uri,
      'responsive_image_style_id' => $responsive_image_style_id,
    ];

    template_preprocess_responsive_image($variables);

    $variables['img_element']['#uri'] = $this->uri;

    Blazy::preprocessResponsiveImage($variables);

    $this->assertEquals($expected, $variables['output_image_tag']);
  }

  /**
   * Provider for ::testPreprocessResponsiveImage.
   */
  public function providerResponsiveImage() {
    return [
      'Responsive image with picture 8.x-3' => [
        'blazy_picture_test',
        FALSE,
      ],
      'Responsive image without picture 8.x-3' => [
        'blazy_responsive_test',
        TRUE,
      ],
    ];
  }

  /**
   * Tests isCrop.
   *
   * @covers ::isCrop
   * @dataProvider providerIsCrop
   */
  public function testIsCrop($image_style_id, $expected) {
    $is_cropped = $this->blazyManager->isCrop($image_style_id);

    $this->assertEquals($expected, !empty($is_cropped));
  }

  /**
   * Provider for ::testIsCrop.
   */
  public function providerIsCrop() {
    return [
      'Cropped image style' => [
        'blazy_crop',
        TRUE,
      ],
      'Non-cropped image style' => [
        'large',
        FALSE,
      ],
    ];
  }

  /**
   * Tests cases for various methods.
   *
   * @covers ::attach
   * @covers ::buildDataBlazy
   * @covers ::getLightboxes
   * @covers ::setLightboxes
   * @covers ::buildSkins
   * @covers ::getCache
   */
  public function testBlazyManagerMethods() {
    // Tests Blazy attachments.
    $attach = ['blazy' => TRUE, 'media_switch' => 'blazy_test'];

    $attachments = $this->blazyManager->attach($attach);
    $this->assertArrayHasKey('blazy', $attachments['drupalSettings']);

    // Tests Blazy [data-blazy] attributes.
    $build     = $this->data;
    $settings  = &$build['settings'];
    $settings += BlazyDefault::itemSettings();
    $item      = $build['item'];

    $settings['first_item']  = $item;
    $settings['first_uri']   = $this->uri;
    $settings['blazy_data']  = [];
    $settings['background']  = TRUE;
    $settings['breakpoints'] = $this->getDataBreakpoints();

    // Ensure Blazy can be activated by breakpoints.
    $this->blazyManager->buildDataBlazy($settings, $build);
    $this->assertNotEmpty($settings['blazy']);

    // Tests Blazy lightboxes.
    $this->blazyManager->setLightboxes('blazy_test');
    $lightboxes = $this->blazyManager->getLightboxes();

    $this->assertFalse(in_array('nixbox', $lightboxes));
    $this->assertTrue(in_array('blazy_test', $lightboxes));

    // Tests for skins.
    // Tests skins with a single expected method BlazySkinTest::skins().
    $skins = $this->blazyManager->buildSkins('blazy_test', '\Drupal\blazy_test\BlazySkinTest');

    // Verify we have cached skins.
    $cid = 'blazy_test:skins';
    $cached_skins = $this->blazyManager->getCache()->get($cid);
    $this->assertEquals($cid, $cached_skins->cid);
    $this->assertEquals($skins, $cached_skins->data);

    // Verify multiple skin methods are respected.
    Cache::invalidateTags([$cid]);
    drupal_flush_all_caches();
    $this->assertFalse($this->blazyManager->getCache()->get($cid));

    $skins = $this->blazyManager->buildSkins('blazy_test', '\Drupal\blazy_test\BlazySkinTest', ['skins', 'features']);

    $this->assertArrayHasKey('features', $skins);

    $cached_skins = $this->blazyManager->getCache()->get($cid);
    $this->assertEquals($skins, $cached_skins->data);
  }

}
