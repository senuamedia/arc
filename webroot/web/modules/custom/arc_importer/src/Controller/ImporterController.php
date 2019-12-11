<?php

namespace Drupal\arc_importer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Queue\QueueFactory;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

use GuzzleHttp\Client;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;

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

  /**
   * The constants to use while verifying spreadsheet
   */
  const SHEET_VERIFY_SUCCESS = 0;
  const SHEET_VERIFY_FAILED  = 1;

  /**
   * @param int $file_id The fid of the import file
   * @return \PhpOffice\PhpSpreadsheet\Spreadsheet|NULL
   *   Spreadsheet on success, NULL on failure
   */
  private function getSheetFromFileID($file_id) {
    try {
      $file = File::load($file_id);
      $file_path = $this->streamWrapperManager
        ->getViaUri($file->getFileUri())
        ->realpath();

      $spreadsheet = IOFactory::load($file_path);
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
            10
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
      try {
        $spreadsheet = $this->getSheetFromFileID($file_id);
        $raw_data    = $spreadsheet
          ->getSheetByName('3. New category mapping')
          ->toArray(null, false, false, true);
          // ->toArray(null, true, true, true);
      }
      catch (\Exception $e) {
        $this->logger
          ->error('An error ocurred while processing: ' . $e->getMessage());
      }

      $sandbox = [
        'raw_data' => $raw_data,
        // Real data start from row 5
        'progress' => 5,
        'total'    => 100//count($raw_data)
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
        $post_title = html_entity_decode($row_data['A']); // The spreadsheet data contains html entities

        $node = Node::create([
          'type'  => 'article',
          'title' => $post_title,
          'body'  => $row_data['B'], // for now use url as body
          'field_category_1' => $arc_utils->loadTermByName($row_data['C']),
          'field_category_2' => $arc_utils->loadTermByName($row_data['D']),
          'field_category_3' => $arc_utils->loadTermByName($row_data['E']),

          // For now
          // TODO: remove this
          'field_sample_content' => TRUE,
          'status' => 1
        ]);

        // If there is sub-category to set
        if (!empty($row_data['G'])) {
          $parent = $row_data['F'];
          $child  = $row_data['G'];
          for ($_fid = 1; $_fid <= 3; $_fid++) {
            if ($check = $node->{"field_category_{$_fid}"}) {
              if ($parent == $check->getString()) {
                \Drupal::logger("arc")
                  ->warning("Parent match at: " . $_fid);
                $node->set("field_category_{$_fid}", $arc_utils->loadTermByName($child));
              }
            }
          }
        }
        // Set author to content_importer
        $node->setOwnerId(42);
        $node->save();

        # Process the body
        $wp_post_url = $row_data['B'];
        $raw_body = $this->fetchBodyFromUrl($wp_post_url);

        # Completed
        if (isset($raw_body)) {
          $node->set('body', [
            'format' => 'full_html',
            'value'  => $raw_body
          ]);
          // $node->set('body', $raw_content);
          $node->save();
        }

        $context['results']['imported']++;
        $sandbox['progress']++;
      }
    }
    catch (\Exception $e) {
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

  protected function getContentViaUrl($url, $limit = 2000000) {
    if(strpos($url, "http") !== 0) {
      $url = "https://" . $url;
    }
    $url = trim($url);
    $domain_params = parse_url($url);
    if(!isset($domain_params['path'])) {
      $domain_params['path'] = '';
    }
  
    $content = "";
  
    try {
      $client = new Client([
        'timeout' => 5,
      ]);
      $response = $client->get($url, array(
        'read_timeout' => 5,
      ));
  
      $body = $response->getBody();
      $content = $body->read($limit);
    }
    catch (\Exception $e) {
      $this->logger
        ->error(
          $this->t('Error while fetching url @url: @err', [
            '@url' => $url,
            '@err' => $e->getMessage()
          ])
        );
      return NULL;
    }
  
    return $content;
  }

  protected function processImgTag(Crawler $node) {
    # Fetch the image
    // Priority: href (parent <a> node) > srcset > src
    $img_source = $node->attr('srcset');

    // if ($node->closest('a')) {}

    if (!empty($img_source)) {
      /**
       * Match structure:
       *  [
       *    0 => https://example.com/wp-content/uploads/2016/02/logo-home.png 793w
       *    1 => png
       *    2 => 793
       * ]
      */
      preg_match_all('/[^"\'=\s]+\.(jpe?g|png|gif) ([0-9]*)w/', $img_source, $matches, PREG_SET_ORDER);
      // Only use the largest file from srcset
      usort ($matches, function ($x, $y) {
        return $x[2] < $y[2];	
      });
      $img_source = explode(" ", $matches[0][0]);
      $img_source = $img_source[0];
      $img_source = trim($img_source);
    }

    if (empty($img_source)) {
      $img_source = $node->attr('src');
    }

    $path = parse_url($img_source, PHP_URL_PATH); 
    $img_name = basename($path);

    if (!empty($img_source)) {
      $directory = 'public://article-images/';
      $this->fileSystem
        ->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      $image_file = system_retrieve_file($img_source, $directory . $img_name, TRUE, FILE_EXISTS_REPLACE);
      if ($image_file) {
        $dom_elem = $node->getNode(0);
        $dom_elem->removeAttribute('srcset');
        $dom_elem->setAttribute('src', $image_file->url());
      }
    }
  }

  protected function fetchBodyFromUrl($url) {
    try {
      $raw_content = $this->getContentViaUrl($url);
      if (empty($raw_content)) {
        return NULL;
      }

      $crawler  = new Crawler($raw_content);
      $articles = $crawler->filter('article');

      // First meta is post header(date, feature image), second is post footer (comments, etc.)
      $meta_filtered = $articles
        ->filter('div.et_post_meta_wrapper')
        ->first();
  
      $meta_filtered->filter('img')
        ->each(function (Crawler $node, $i) {
          $this->processImgTag($node);
        });

      // Article content
      $article_filtered = $articles->filter('div.entry-content > p,blockquote');
      // ->children('p,blockquote');
  
      $article_filtered->filter('img')
        ->each(function (Crawler $node, $i) {
          $this->processImgTag($node);
        });
  
      try {
        $urls = $article_filtered
          ->filter('a')
          ->each(function (Crawler $node, $i) {
            $url_from_child = $node
              ->children('img')
              ->attr('src');

            if ($url_from_child) {
              $node->getNode(0)
                ->setAttribute('href', $url_from_child);
            } else {

            }
            return $node->attr('href');
          });
        // print_r($urls);exit;
      }
      catch (\InvalidArgumentException $e) {
        $this->logger
          ->warning(
            $this->t('An exeption throwed while fetching from @url: @err', [
              '@url' => $url,
              '@err' => $e->getMessage()
            ])
          );
      }

      $html = '';
      foreach ($meta_filtered as $element) {
        $html .= $element->ownerDocument->saveHTML($element);
      };
      foreach ($article_filtered as $element) {
        $html .= $element->ownerDocument->saveHTML($element);
      };

      return $html;
    }
    catch (\Exception $e) {
      $this->logger
        ->error(
          $this->t('Error while fetching from @url: @err', [
            '@url' => $url,
            '@err' => $e->getMessage()
          ])
        );
      return NULL;
    }
  }

  public function test() {
    // $wp_post_url = 'http://arc.parracity.nsw.gov.au/blog/2017/02/03/cambria-hall-a-hidden-cornerstone-of-epping-history';
    $wp_post_url = 'http://arc.parracity.nsw.gov.au/blog/2014/11/26/captain-henry-mance-the-prince-of-parramatta-river/';

    return [
      '#markup' => $this->fetchBodyFromUrl($wp_post_url)
    ];
  }

  public function status() {
    $header = [
      'row' => [
        'data'  => $this->t('Row'), 
        'field' => 'row',
        'specifier'  => 'row'
        // 'sort'  => 'desc'
      ],
      'url' => [
        'data'      => $this->t('Url'),
        'type'      => 'link',
        'field'     => 'url',
        // 'specifier' => 'url'
      ],
      'status' => [
        'data'      => $this->t('Status'),
        'field'     => 'status',
        'specifier' => 'status'
      ],
      'message' => [
        'data'      => $this->t('Message'),
        'field'     => 'message',
        'specifier' => 'message'
      ]
    ];
  
    $results = $this->database
      ->select('arc_importer_articles', 'arc')
      ->fields('arc')
      // ->sort('row', 'DESC')
      ->range(1, 50)
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
        '#caption' => $this->t('Importing Status'),
        '#header'  => $header,
        '#rows'    => $data,
        '#empty'   => $this->t('No data found.'),
      ],
      // 'pager' => [
      //   '#type' => 'pager',
      // ],
    ];
  }
}