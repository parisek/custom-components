<?php

namespace Drupal\Tests\custom_components\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\custom_components\Services\EntityHelper;

/**
 * Verifies the kernel-test pipeline can boot the module.
 *
 * Acts as the canary for the CI workflow: passes only when composer has
 * scaffolded Drupal, the module is symlinked into web/modules/contrib, and
 * PHPUnit can bootstrap from web/core/tests/bootstrap.php with sqlite
 * in-memory.
 *
 * @group custom_components
 */
class SmokeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['custom_components'];

  /**
   * The module is discoverable and its primary service is wired up.
   */
  public function testEntityHelperServiceIsAvailable(): void {
    $this->assertTrue(
      $this->container->get('module_handler')->moduleExists('custom_components'),
      'custom_components module is enabled in the kernel container.',
    );
    $this->assertInstanceOf(
      EntityHelper::class,
      $this->container->get('custom_components.entity_helper'),
    );
  }

}
