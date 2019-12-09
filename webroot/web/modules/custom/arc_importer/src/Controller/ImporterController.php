<?php

namespace Drupal\arc_importer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Queue\QueueFactory;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 ** Class ImporterController.
 ** @package Drupal\arc_importer\Controller
 */
class ImporterController extends ControllerBase {
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
    StreamWrapperManagerInterface $stream_wrapper_manager,
    LoggerChannelFactoryInterface $logger_factory)
  {
    $this->database = $database;
    $this->streamWrapperManager = $stream_wrapper_manager;

    $this->logger = $logger_factory->get('ARC:Import');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('stream_wrapper_manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * The constants to use while verifying spreadsheet
   */
  const SHEET_VERIFY_SUCCESS = 0;
  const SHEET_VERIFY_FAILED  = 1;

  /**
   * @param int $file_id The fid of the import file
   * @return mixed Spreadsheet on success, NULL on failure
   */
  private function getSheetFromFileID($file_id) {
    try {
      $file = File::load($file_id);
      $file_path = $this->streamWrapperManager
        ->getViaUri($file->getFileUri())
        ->realpath();

      $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
    }
    catch (\Exception $e) {
      $this->logger()
        ->error($e->getMessage());
      return NULL;
    }
    return $spreadsheet;
  }

  /**
   * The import function assumes the file is verified,
   * hence no check is perfomed here
   * @param int $file_id The fid of the import file
   * @return boolean
   *   TRUE on import success
   *   FALSE otherwise
   */

  public function doBatchImport($file_id) {
    $batch = [
      'title' => $this->t('Processing spreadsheet'),
      'init_message'  => $this->t('Start Importing'),
      'error_message' => $this->t('An error occurred while importing'),
      'operations' => [
        [
          [$this, 'doProcessBatch'],
          [
            $file_id,
            1
          ],
        ]
      ],
      'finished' => [$this, 'doFinishBatch']
    ];
    batch_set($batch);
  }

  /**
   * @param int $file_id ID of the spreadsheet file to import
   * @param int $process_limit The rows to process each batch
   */
  public function doProcessBatch($file_id, int $process_limit, &$context) {
    $sandbox = &$context['sandbox'];
    # Reading data from sheet
    # Assumming spreadsheet structure:
    // Data sheet name: "3. New category mapping"
    // Line 4: Table Heading
    // Line 5 and up: the data
    // Column mapping:
    // A - Title
    // B - URLs
    // C, D, E - New Category 
    // F, G - Parent & Child
    // H and up: Ignore

    if (empty($sandbox)) {
      $spreadsheet = $this->getSheetFromFileID($file_id);
      $raw_data    = $spreadsheet
        ->getSheetByName('3. New category mapping')
        ->toArray(null, true, true, true);

      $sandbox = [
        'raw_data' => $raw_data,
        // Real data start from row 5
        'progress' => 5,
        'total'    => 5//count($raw_data)
      ];
      $context['results']['imported'] = 0;
    }

    $content_data = $sandbox['raw_data'];

    try {
      $_from = $sandbox['progress'];
      $_to   = $_from + $process_limit;

      for ($index = $_from; $index <= $_to; $index++) {
        if (!isset($content_data[$index])) {
          break;
        }

        $row_data = $content_data[$index];
        
        // Ignore rows with empty title
        if (empty($row_data['A'])) {
          $sandbox['progress']++;
          continue;
        }

        $arc_utils = \Drupal::service('arc_importer.utils');          

        $node = Node::create([
          'type'  => 'article',
          'title' => html_entity_decode($row_data['A']), // The spreadsheet data has html entities
          'body'  => $row_data['B'], // for now use url as body
          'field_category_1' => $arc_utils->loadTermByName($row_data['C']),
          'field_category_2' => $arc_utils->loadTermByName($row_data['D']),
          'field_category_3' => $arc_utils->loadTermByName($row_data['E']),

          // For now
          // TODO: remove this
          'field_sample_content' => TRUE,
          'status' => 0, // Unpublished, to filter out
        ]);

        // If there is sub-category to set
        if (!empty($row_data['G'])) {
          $parent = $row_data['F'];
          for ($_fid = 1; $_fid <= 3; $_fid++) {
            if ($check = $node->{"field_category_{$_fid}"}) {
              if ($parent == $check->getString()) {
                \Drupal::logger("arc")
                  ->warning("Parent match at: " . $_fid);
              }
            }
          }
        }
        $node->save();

        $context['results']['imported']++;
        $sandbox['progress']++;
      }
    }
    catch (Exception $e) {
      $this->logger
        ->error('An error ocurred while processing: ' . $e->getMessage());
    }

    $context['message'] = '<h2>' . $this->t('Processing data...') . '</h2>';
    $context['message'] .= $this->t('Processed @c/@r rows.', [
      '@c' => $sandbox['progress'],
      '@r' => $sandbox['total'],
    ]);

    if ($sandbox['total']) {
      $context['finished'] = $sandbox['progress'] / $sandbox['total'];
    }
  }

  public function doImportBatch(int $process_limit, &$context) {
    $sandbox = &$context['sandbox'];

    if (empty($sandbox)) {
      $sandbox = [
        'progress' => 0,
        'total'    => 100000
      ];
      $context['results']['queued'] = 0;
    }

    usleep(5000);
    $sandbox['progress'] += 100;

    $context['message'] = '<h2>' . $this->t('Importing data...') . '</h2>';
    $context['message'] .= $this->t('Processed @c/@r rows.', [
      '@c' => $sandbox['progress'],
      '@r' => $sandbox['total'],
    ]);

    if ($sandbox['total']) {
      $context['finished'] = $sandbox['progress'] / $sandbox['total'];
    }

    $this->messenger()
      ->addMessage($process_limit);
  }

  /**
   * Reports the results of the data import operations.
   *
   * @param bool  $success
   * @param array $results
   * @param array $operations
   */
  public function doFinishBatch($success, $results, $operations) {
    if ($success) {
      $success_msg = $this->formatPlural(
        $results['imported'],
        'One post imported.',
        '@count posts imported.'
      );
      $this->messenger()
        ->addMessage($success_msg);
    }

    $url = Url::fromRoute('dblog.overview',
      [
        'attributes' => [
          'target' => '_blank',
        ],
      ]
    );

    $pass_link = Link::fromTextAndUrl($this->t('log'), $url)
      ->toString();

    $this->messenger()
      ->addWarning(
        $this->t(
          'All data imported. Check @log for any issue that may happenned during import process.',
          [
            '@log' => $pass_link,
          ]
        )
      );
  }
}
