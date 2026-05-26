<?php

namespace Drupal\custom_components\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TaxonomyTermController view alter.
 *
 * @phpstan-consistent-constructor
 */
class TaxonomyTermController extends ControllerBase {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor for our class.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    LanguageManagerInterface $language_manager,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * View function.
   */
  public function view(RouteMatchInterface $route_match) {

    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    $term = $route_match->getParameter('taxonomy_term');
    if ($term instanceof TermInterface) {
      $entity_type = $term->getEntityTypeId();
      return $this->entityTypeManager->getViewBuilder($entity_type)->view($term, 'full', $langcode);
    }

  }

}
