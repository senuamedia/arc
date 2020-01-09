<?php

namespace Drupal\arc_breadcrumb\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\Query\TableSortExtender;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\TableSort;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

use GuzzleHttp\Client;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 ** Class BreadcrumbStatusController.
 ** @package Drupal\arc_importer\Controller
 */
class BreadcrumbStatusController extends ControllerBase {
  /**
   * This is a missing trait of core's ControllerBase that
   * causes error when injecting database connection,
   * @see https://www.drupal.org/project/drupal/issues/2893029
   */
  use DependencySerializationTrait;

  /**
   * The current database connection
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;
  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;
  /**
   * @var \Drupal\Core\StreamWrapper\StreamWrapperInterface
   */
  protected $streamWrapperManager;
  /**
   * The *Watchdog* logger for Training Import module
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs an TrainingImportController object
   * 
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger service.
   */
  public function __construct(
    Connection $database,
    FileSystemInterface $file_system,
    StreamWrapperManagerInterface $stream_wrapper_manager,
    LoggerChannelFactoryInterface $logger_factory)
  {
    $this->database = $database;
    $this->fileSystem = $file_system;
    $this->streamWrapperManager = $stream_wrapper_manager;

    $this->logger = $logger_factory->get('ARC:Import');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('file_system'),
      $container->get('stream_wrapper_manager'),
      $container->get('logger.factory')
    );
  }

  public function status() {
    $header = [
      'row' => [
        'data'  => $this->t('Row'), 
        'field' => 'row',
        'specifier'  => 'row'
      ],
      'url' => [
        'data'      => $this->t('Url'),
        'type'      => 'link',
      ],
      'status' => [
        'data'      => $this->t('Status'),
        'field'     => 'status',
        'specifier' => 'status'
      ],
      'message' => [
        'data'      => $this->t('Message'),
        'specifier' => 'message'
      ],
    ];
  
    $results = $this->database
      ->select('arc_importer_articles', 'arc')
      ->fields('arc')
      ->extend(PagerSelectExtender::class)
      ->limit(50)
      ->extend(TableSortExtender::class)
      ->orderByHeader($header)
      ->execute();

    $data = [];
    foreach ($results as $id => $row) {
      $data[] = [
        $row->row,
        $row->url,
        $row->status,
        $row->message
      ];
    }

    return [
      'results' => [
        '#type'    => 'table',
        '#header'  => $header,
        '#rows'    => $data,
        '#empty'   => $this->t('No data found.'),
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];
  }
}