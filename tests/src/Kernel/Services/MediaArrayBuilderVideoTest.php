<?php

namespace Drupal\Tests\custom_components\Kernel\Services;

use Drupal\Tests\custom_components\Kernel\MediaArrayBuilderKernelTestBase;
use Drupal\media\Entity\Media;

/**
 * @coversDefaultClass \Drupal\custom_components\Services\MediaArrayBuilder
 * @group custom_components
 */
class MediaArrayBuilderVideoTest extends MediaArrayBuilderKernelTestBase {

  /**
   * @covers ::buildVideo
   */
  public function testBuildVideoReturnsFileMetadata(): void {
    // Use a 'file' media bundle (generic file source) so we can host
    // a faux MP4 without needing an actual video codec.
    $file = $this->createTestFile('clip.mp4', 'not-really-an-mp4', 'video/mp4');
    $type = $this->createMediaType('file', ['id' => 'video']);
    $source_field = $type->getSource()->getSourceFieldDefinition($type)->getName();

    $media = Media::create([
      'bundle' => 'video',
      'name' => $file->getFilename(),
      $source_field => ['target_id' => $file->id()],
    ]);
    $media->save();

    $data = $this->builder->buildVideo($media);

    $this->assertSame('clip.mp4', $data['title']);
    $this->assertSame('video/mp4', $data['type']);
    $this->assertArrayHasKey('src', $data);
    $this->assertArrayHasKey('size', $data);
  }

  /**
   * @covers ::buildRemoteVideo
   */
  public function testBuildRemoteVideoExtractsYouTubeEmbed(): void {
    $media = $this->mockRemoteVideoMedia('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    $data = $this->builder->buildRemoteVideo($media);

    $this->assertSame(
      'https://www.youtube.com/embed/dQw4w9WgXcQ',
      $data['iframe'],
    );
  }

  /**
   * @covers ::buildRemoteVideo
   */
  public function testBuildRemoteVideoPassesNonYouTubeUrlThrough(): void {
    $url = 'https://vimeo.com/12345';
    $media = $this->mockRemoteVideoMedia($url);

    $data = $this->builder->buildRemoteVideo($media);

    $this->assertSame($url, $data['iframe']);
  }

  /**
   * @covers ::buildRemoteVideo
   */
  public function testBuildRemoteVideoCallsImageFieldResolverWhenProvided(): void {
    $media = $this->mockRemoteVideoMedia(
      'https://www.youtube.com/watch?v=ABC12345678',
      hasImageField: TRUE,
    );

    $called_with = NULL;
    $resolver = function ($m, $field_name) use (&$called_with) {
      $called_with = [$m, $field_name];
      return [['src' => '/resolver/result.png']];
    };

    $data = $this->builder->buildRemoteVideo($media, $resolver);

    $this->assertNotNull($called_with);
    $this->assertSame('media_image', $called_with[1]);
    $this->assertSame([['src' => '/resolver/result.png']], $data['image']);
  }

  /**
   * Build a stub MediaInterface with a remote-video source.
   */
  protected function mockRemoteVideoMedia(string $url, bool $hasImageField = FALSE) {
    $media = $this->createMock(\Drupal\media\MediaInterface::class);
    $source = $this->createMock(\Drupal\media\MediaSourceInterface::class);
    $source->method('getSourceFieldValue')->willReturn($url);
    $media->method('getSource')->willReturn($source);
    $media->method('hasField')->willReturnCallback(
      fn ($name) => $hasImageField && $name === 'field_media_image',
    );
    if ($hasImageField) {
      $field = $this->createMock(\Drupal\Core\Field\FieldItemListInterface::class);
      $field->method('isEmpty')->willReturn(FALSE);
      $media->method('__get')->willReturnCallback(
        fn ($name) => $name === 'field_media_image' ? $field : NULL,
      );
    }
    return $media;
  }

}
