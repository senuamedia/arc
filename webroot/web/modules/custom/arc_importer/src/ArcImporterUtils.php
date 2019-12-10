<?php

namespace Drupal\arc_importer;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface ;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;

/**
 ** @class ArcImporterUtils
 */
class ArcImporterUtils extends ServiceProviderBase {
  /**
   * The *Watchdog* logger for ARC Importer module
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;
  /**
   * The entity type manager.
   * 
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface 
  */
  protected $entityTypeManager;

  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->logger = $logger_factory
      ->get('ARC Importer');
    $this->entityTypeManager = $entity_type_manager;
  }

  protected function entityTypeManager() {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = $this
        ->container()
        ->get('entity_type.manager');
    }
    return $this->entityTypeManager;
  }

  public function loadTermByName($term_name, $vocabulary = 'all') {
    if (!empty($term_name)) {
      $term = $this->entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadByProperties(['name' => $term_name]);

        // \Drupal::logger('Acr')->warning("term loaded: " . $term_name);
    }
    return isset($term) ? $term : NULL;
  }
}
