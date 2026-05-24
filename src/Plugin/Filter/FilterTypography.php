<?php

namespace Drupal\custom_components\Plugin\Filter;

use Drupal\Component\Utility\Html;
use PHP_Typography\Settings;
use PHP_Typography\PHP_Typography;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides a filter to format typography.
 *
 * @Filter(
 *   id = "filter_typography",
 *   title = @Translation("Typography Filter"),
 *   description = @Translation("Help format typography"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 * )
 */
class FilterTypography extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    $typo = new PHP_Typography();
    $use_defaults = TRUE;
    $settings = new Settings($use_defaults);

    $current_theme_name = \Drupal::service('theme.manager')->getActiveTheme()->getName();
    $file_path = \Drupal::service('extension.path.resolver')->getPath('theme', $current_theme_name) . '/static/typography.yml';
    $arguments = [];
    if (file_exists($file_path)) {
      $arguments = Yaml::parse(file_get_contents($file_path));
    }
    // Process the arguments and add them to the settings object.
    foreach ($arguments as $setting => $value) {
      $settings->{$setting}($value);
    }

    $text = $typo->process($text, $settings);

    $html_dom = Html::load($text);
    $blockquote = $html_dom->getElementsByTagName('blockquote');
    foreach ($blockquote as $b) {
      $classes = $b->getAttribute('class');
      $classes = (strlen($classes) > 0) ? explode(' ', $classes) : [];
      if (!in_array('blockquote', $classes)) {
        $classes[] = 'blockquote';
      }
      $b->setAttribute('class', implode(' ', $classes));
    }

    $text = Html::serialize($html_dom);

    $result->setProcessedText($text);
    return $result;
  }

}
