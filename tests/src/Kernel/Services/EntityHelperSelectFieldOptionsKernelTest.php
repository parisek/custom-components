<?php

namespace Drupal\Tests\custom_components\Kernel\Services;

use Drupal\Tests\custom_components\Kernel\EntityHelperFieldsKernelTestBase;

/**
 * Behavioural coverage for EntityHelper::getSelectFieldOptions.
 *
 * Reads the `allowed_values` map off a `list_string` field storage
 * config via the config factory. Verified end-to-end through the real
 * field + config stack rather than mocked.
 *
 * @coversDefaultClass \Drupal\custom_components\Services\EntityHelper
 * @group custom_components
 */
class EntityHelperSelectFieldOptionsKernelTest extends EntityHelperFieldsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'custom_components',
    'system',
    'user',
    'field',
    'node',
    'text',
    'filter',
    // list_string field type ships in core's options module.
    'options',
    // ConfigurableLanguageManager — getSelectFieldOptions calls
    // languageManager->getLanguageConfigOverride() which only exists on
    // the configurable variant.
    'language',
  ];

  /**
   * @covers ::getSelectFieldOptions
   *
   * `list_string` storage uses the `key => label` map shape (the schema
   * in options.schema.yml).
   */
  public function testReturnsAllowedValuesMapForListStringField(): void {
    $this->attachField('size', 'list_string', [
      'allowed_values' => ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large'],
    ]);

    $options = $this->entityHelper->getSelectFieldOptions('en', 'field_size');

    $this->assertSame(['s' => 'Small', 'm' => 'Medium', 'l' => 'Large'], $options);
  }

  /**
   * @covers ::getSelectFieldOptions
   *
   * `field_name` arg is normalized — `size` and `field_size` both
   * resolve to the same field storage config (the documented behaviour
   * of `normalizeFieldName`).
   */
  public function testFieldNameIsNormalizedWithFieldPrefix(): void {
    $this->attachField('flavor', 'list_string', [
      'allowed_values' => ['sweet' => 'Sweet', 'sour' => 'Sour'],
    ]);

    $without_prefix = $this->entityHelper->getSelectFieldOptions('en', 'flavor');
    $with_prefix = $this->entityHelper->getSelectFieldOptions('en', 'field_flavor');

    $this->assertSame(['sweet' => 'Sweet', 'sour' => 'Sour'], $without_prefix);
    $this->assertSame($without_prefix, $with_prefix);
  }

  /**
   * @covers ::getSelectFieldOptions
   *
   * Missing field storage returns an empty options map without errors.
   */
  public function testMissingFieldReturnsEmptyOptionsMap(): void {
    $this->assertSame(
      [],
      $this->entityHelper->getSelectFieldOptions('en', 'nonexistent_field'),
    );
  }

}
