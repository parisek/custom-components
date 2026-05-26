<?php

namespace Drupal\Tests\custom_components\Kernel\Services;

use Drupal\Tests\custom_components\Kernel\MediaArrayBuilderKernelTestBase;

/**
 * @coversDefaultClass \Drupal\custom_components\Services\MediaArrayBuilder
 * @group custom_components
 */
class MediaArrayBuilderLinkTest extends MediaArrayBuilderKernelTestBase {

  /**
   * @covers ::buildImageLink
   */
  public function testBuildImageLinkReturnsFileUrlWithoutStyle(): void {
    $file = $this->createTestPngFile('link-source.png');
    $media = $this->createTestImageMedia($file);

    $url = $this->builder->buildImageLink($media);

    $this->assertIsString($url);
    $this->assertStringContainsString('link-source.png', $url);
  }

  /**
   * @covers ::buildImageLink
   */
  public function testBuildImageLinkAppliesImageStyle(): void {
    $file = $this->createTestPngFile('link-styled.png');
    $media = $this->createTestImageMedia($file);
    $this->createImageStyle('link_thumb', 50);

    $url = $this->builder->buildImageLink($media, 'link_thumb');

    $this->assertStringContainsString('link_thumb', $url);
  }

  /**
   * @covers ::buildImageLink
   */
  public function testBuildImageLinkReturnsNullForNonMedia(): void {
    $this->assertNull($this->builder->buildImageLink(NULL));
    $this->assertNull($this->builder->buildImageLink('string-not-media'));
  }

  /**
   * @covers ::buildFileImageLink
   */
  public function testBuildFileImageLinkReturnsFileUrlWithoutStyle(): void {
    $file = $this->createTestPngFile('file-link.png');

    $url = $this->builder->buildFileImageLink($file);

    $this->assertIsString($url);
    $this->assertStringContainsString('file-link.png', $url);
  }

  /**
   * @covers ::buildFileImageLink
   */
  public function testBuildFileImageLinkAppliesImageStyle(): void {
    $file = $this->createTestPngFile('file-link-styled.png');
    $this->createImageStyle('file_link_thumb', 50);

    $url = $this->builder->buildFileImageLink($file, 'file_link_thumb');

    $this->assertStringContainsString('file_link_thumb', $url);
  }

  /**
   * @covers ::buildFileImageLink
   */
  public function testBuildFileImageLinkReturnsNullForNonFile(): void {
    $this->assertNull($this->builder->buildFileImageLink(NULL));
  }

}
