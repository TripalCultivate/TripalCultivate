<?php

namespace Drupal\Tests\trpcultivate\Kernel;

use Drupal\Core\Routing\RouteMatch;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests our implementation of specific hooks.
 *
 * @group Hooks
 */
#[Group('Hooks')]
class ImplementedHooksTest extends ChadoTestKernelBase {

  /**
   * Theme used in the test environment.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'path',
    'path_alias',
    'views',
    'field',
    'field_ui',
    'markup',
    'field_group',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
  ];

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Initialize the chado instance with all the records that would be present
    // after running prepare.
    $this->chado_connection = $this->getTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
  }

  /**
   * Provides scenarios to the testPreprocessPage test.
   *
   * @return array
   *   An array of scenarios where each one contains a 'route_name' to set
   *   the mock routeMatch to return and an expectations array consisting of:
   *     - contains_library: TRUE|FALSE indicates whether the
   *       trpcultivate/tripal_entity_type should be attached to the page.
   */
  public static function provideTestRoutes() {
    $scenarios = [];

    $scenarios[] = [
      'route_name' => 'entity.tripal_entity.add_page',
      'expectations' => [
        'contains_library' => TRUE,
      ],
    ];

    $scenarios[] = [
      'route_name' => 'entity.tripal_entity_type.collection',
      'expectations' => [
        'contains_library' => TRUE,
      ],
    ];

    $scenarios[] = [
      'route_name' => '<front>',
      'expectations' => [
        'contains_library' => FALSE,
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests trpcultivate_preprocess_page() in our .module file.
   *
   * @param string $route_name
   *   The name of the route that should be returned by RouteMatch.
   * @param array $expectations
   *   An array describing what we should expect.
   *     - contains_library: TRUE|FALSE indicates whether the
   *       trpcultivate/tripal_entity_type should be attached to the page.
   */
  #[DataProvider('provideTestRoutes')]
  public function testPreprocessPage(string $route_name, array $expectations) {

    // Firs mock the RouteMatch object. This service is called by our preprocess
    // function to determine which page we are on.
    $routeMatchMock = $this->getMockBuilder(RouteMatch::class)
      ->disableOriginalConstructor()
      ->getMock();
    $routeMatchMock->expects($this->any())
      ->method('getRouteName')
      ->will($this->returnValue($route_name));
    $this->container->set('current_route_match', $routeMatchMock);

    // Now call our preprocess hook.
    $variables = [
      '#attached' => [
        'library' => [],
      ],
    ];
    trpcultivate_preprocess_page($variables);

    // And assert that the library was added if it is expected to have been.
    if ($expectations['contains_library'] === TRUE) {
      $this->assertContains(
        'trpcultivate/tripal_entity_type',
        $variables['#attached']['library'],
        "Our tripal_entity_type library should have been added to $route_name the preprocess hook."
      );
    }
    else {
      $this->assertNotContains(
        'trpcultivate/tripal_entity_type',
        $variables['#attached']['library'],
        "Our tripal_entity_type library should NOT have been added to $route_name the preprocess hook."
      );
    }
  }

}
