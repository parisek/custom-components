<?php

namespace Drupal\Tests\custom_components\Kernel\Services;

use Drupal\Tests\custom_components\Kernel\MediaArrayBuilderKernelTestBase;

/**
 * @coversDefaultClass \Drupal\custom_components\Services\MediaArrayBuilder
 * @group custom_components
 */
class MediaArrayBuilderSvgTest extends MediaArrayBuilderKernelTestBase {

  /**
   * @covers ::getSvgViewBoxDimensions
   */
  public function testGetSvgViewBoxDimensionsParsesViewBox(): void {
    $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect width="24" height="24"/></svg>';
    $file = $this->createTestFile('icon.svg', $svg, 'image/svg+xml');

    $dimensions = $this->builder->getSvgViewBoxDimensions($file->getFileUri());

    $this->assertSame(['width' => 24, 'height' => 24], $dimensions);
  }

  /**
   * @covers ::getSvgViewBoxDimensions
   */
  public function testGetSvgViewBoxDimensionsHandlesCommaSeparatedViewBox(): void {
    $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0,0,100,50"><rect width="100" height="50"/></svg>';
    $file = $this->createTestFile('comma.svg', $svg, 'image/svg+xml');

    $dimensions = $this->builder->getSvgViewBoxDimensions($file->getFileUri());

    $this->assertSame(['width' => 100, 'height' => 50], $dimensions);
  }

  /**
   * @covers ::getSvgViewBoxDimensions
   */
  public function testGetSvgViewBoxDimensionsReadsExplicitWidthHeight(): void {
    $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"><rect width="48" height="48"/></svg>';
    $file = $this->createTestFile('explicit.svg', $svg, 'image/svg+xml');

    $dimensions = $this->builder->getSvgViewBoxDimensions($file->getFileUri());

    $this->assertSame(['width' => 48, 'height' => 48], $dimensions);
  }

  /**
   * @covers ::getSvgViewBoxDimensions
   */
  public function testGetSvgViewBoxDimensionsReturnsNullForMalformedSvg(): void {
    $file = $this->createTestFile('broken.svg', 'this is not xml', 'image/svg+xml');

    $dimensions = $this->builder->getSvgViewBoxDimensions($file->getFileUri());

    $this->assertNull($dimensions);
  }

  /**
   * @covers ::getSvgViewBoxDimensions
   */
  public function testGetSvgViewBoxDimensionsReturnsNullWhenNoDimensionInfo(): void {
    $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>';
    $file = $this->createTestFile('nodim.svg', $svg, 'image/svg+xml');

    $dimensions = $this->builder->getSvgViewBoxDimensions($file->getFileUri());

    $this->assertNull($dimensions);
  }

  /**
   * @covers ::getSvgViewBoxDimensions
   */
  public function testGetSvgViewBoxDimensionsRejectsZeroOrNegativeDimensions(): void {
    $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 0 0"><rect/></svg>';
    $file = $this->createTestFile('zero.svg', $svg, 'image/svg+xml');

    $dimensions = $this->builder->getSvgViewBoxDimensions($file->getFileUri());

    $this->assertNull($dimensions);
  }

}
