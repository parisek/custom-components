<?php

namespace Drupal\Tests\custom_components\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the runtime requirements reported on the status page.
 *
 * The kernel container never carries menu.language_tree_manipulator
 * (it ships via a core patch), so the tests cover the missing-service
 * side: no entry on a monolingual site, a warning on a multilingual
 * one.
 *
 * @group custom_components
 */
class RequirementsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_components',
    'system',
    'user',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    include_once $this->root . '/core/includes/install.inc';
    include_once __DIR__ . '/../../../custom_components.install';
  }

  /**
   * Non-runtime phases report nothing.
   */
  public function testInstallPhaseReportsNothing(): void {
    $this->assertSame([], custom_components_requirements('install'));
  }

  /**
   * A monolingual site gets no requirement entry.
   */
  public function testMonolingualSiteReportsNothing(): void {
    $this->assertArrayNotHasKey(
      'custom_components_language_tree_manipulator',
      custom_components_requirements('runtime'),
    );
  }

  /**
   * A multilingual site without the service gets a warning.
   */
  public function testMultilingualSiteWithoutServiceWarns(): void {
    ConfigurableLanguage::createFromLangcode('cs')->save();

    $requirements = custom_components_requirements('runtime');

    $this->assertArrayHasKey('custom_components_language_tree_manipulator', $requirements);
    $requirement = $requirements['custom_components_language_tree_manipulator'];
    $this->assertSame(REQUIREMENT_WARNING, $requirement['severity']);
    $this->assertStringContainsString(
      'menu.language_tree_manipulator',
      (string) $requirement['description'],
    );
  }

}
