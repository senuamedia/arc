<?php

namespace Drupal\arc_importer\Form;


use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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

class ImporterForm extends FormBase {
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
  public static function create($container) {
    $form = new static(
      $container->get('database'),
      $container->get('file_system'),
      $container->get('stream_wrapper_manager'),
      $container->get('logger.factory')
    );
    $form->setStringTranslation($container->get('string_translation'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'arc_importer_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // $form['import_file'] = [
    //   '#type'  => 'managed_file',
    //   '#name'  => 'import_file',
    //   '#title' => $this->t('Choose A File To Import'),
    //   '#description' => $this->t('File contains data to import (only *.xlsx allowed)'),
    //   // '#required'    => TRUE,
    //   '#upload_validators' => [
    //     'file_validate_extensions' => ['xlsx']
    //   ],
    //   '#upload_location' => 'public://import-files/',
    // ];

    $form['from'] = [
      '#type'  => 'number',
      '#title' => $this->t('From'),
      '#default_value' => 5,
      '#required' => TRUE,
    ];

    $form['to'] = [
      '#type'  => 'number',
      '#title' => $this->t('To'),
      '#default_value' => 100,
      '#required' => TRUE,
    ];

    $form['actions']['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Import'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $module_path = drupal_get_path('module', 'arc_importer');
    $import_file = $module_path . '/assets/main_import_file.xlsx';
    $spreadsheet = $this->getSheet($import_file);
    $content_data = $spreadsheet
      ->getSheetByName('3. New category mapping')
      ->toArray(null, false, false, true);

    try {
      // $current = $this->database
      //   ->select('arc_importer_articles', 'arc')
      //   ->fields('arc')
      //   ->sort('row', 'DESC')
      //   ->range(0, 1)
      //   ->execute();

      $_from = $form_state->getValue('from', 5);
      $_to   = $form_state->getValue('to', 100);

      if ($_from < 5) {
        $_from = 5;
      }
      if ($_to > 665) {
        $_to = 665;
      }

      for ($index = $_from; $index <= $_to; $index++) {
        if (!isset($content_data[$index])) {
          break;
        }

        $row_data = $content_data[$index];
        
        // Ignore rows with empty title
        if (empty($row_data['A'])) {
          continue;
        }

        $status  = 1;
        $message = '';
  
        $arc_utils = \Drupal::service('arc_importer.utils');
        $post_title = html_entity_decode($row_data['A']); // The spreadsheet data contains html entities

        $node = Node::create([
          'type'  => 'article',
          'title' => $post_title,
          'body'  => $row_data['B'], // for now use url as body
          'field_category_1' => $arc_utils->loadTermByName($row_data['C'], 'categories'),
          'field_category_2' => $arc_utils->loadTermByName($row_data['D'], 'categories'),
          'field_category_3' => $arc_utils->loadTermByName($row_data['E'], 'categories'),

          // For now
          // TODO: remove this
          'field_sample_content' => TRUE,
          'status' => 1
        ]);

        // If there is sub-category to set
        if (!empty($row_data['G'])) {
          $parent = $row_data['F'];
          $child  = $row_data['G'];
          if ($parent == $row_data['C']) {
            $node->set("field_category_1", $arc_utils->loadTermByName($child, 'categories'));
          }
          else if ($parent == $row_data['D']) {
            $node->set("field_category_2", $arc_utils->loadTermByName($child, 'categories'));
          }
          else if ($parent == $row_data['E']) {
            $node->set("field_category_3", $arc_utils->loadTermByName($child, 'categories'));
          } 
        }
        $wp_post_url = $row_data['B'];
        $node->set('field_canonical_url', $wp_post_url);
        // Set author to content_importer
        $node->setOwnerId(42);
        $node->save();

        # Process the body
        try {
          $content = $this->fetchBodyFromUrl($wp_post_url);

          // print_r($content);exit;

          $title = $content['title'];
          $created = trim($content['created']);
          $created = implode("", explode(",", $created));
          $image = $content['image'];
          // $body  = implode("\n", $content['body']);
          $body  = $content['body'];

          if (!empty($image)) {
            $path = parse_url($image, PHP_URL_PATH); 
            $img_name = basename($path);

            $directory = 'public://article-images/';
            \Drupal::service('file_system')
              ->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      
            $image_file = system_retrieve_file($image, $directory . $img_name, TRUE, FILE_EXISTS_REPLACE);
          }
          // print_r($image_file->url());exit;
          if ($image_file) {
            $node->set('field_image', [
              'target_id' => $image_file->id(),
              'alt'   => $title,
              'title' => $title
            ]);
          }

          // $node->set('title', $title);
          $node->set('created', strtotime($created));
          $node->set('body', [
            'format' => 'full_html',
            'value'  => $body
          ]);


          $blog_path = parse_url($wp_post_url, PHP_URL_PATH);
          $blog_path = rtrim($blog_path, '/');
          // print($blog_path);exit;
          $path = \Drupal::service('path.alias_storage')
            ->save('/node/'. $node->id(), $blog_path);

          $node->set('path', [
            'pathauto' => FALSE,
            'alias' => $blog_path,
            // 'pid' => 1,
            // 'langcode' => 'en',
          ]);
          $node->save();
        }
        catch (\Exception $e) {
          $status  = 0;
          $message = $e->getMessage();
          $this->logger
            ->error($this->t('Error at @row: @err', [
              '@row' => $index,
              '@err' => $e->getMessage()
            ]));
        }

        $this->database->merge('arc_importer_articles')
          ->key(['node_id' => $node->id()])
          ->fields([
            'node_id' => $node->id(),
            'url'     => $wp_post_url,
            'row'     => $index,
            'status'  => $status,
            'message' => $message ? $message : ''
          ])
          ->execute();
        
        usleep(100000);
      }
    }
    catch (\Exception $e) {
      $this->logger
        ->error('An error ocurred while processing: ' . $e->getMessage());
    }

    $this->logger
      ->info("Successfully imported from {$_from} to {$_to}");

    return;
    // $import_file_ids = $form_state->getValue('import_file', '');

    // if (empty($import_file_ids) || empty($import_file_ids[0])) {
    //   $this->messenger()
    //     ->addError('Please choose a file to import!');
    //   return;
    // }
    // else {
    //   $fid = reset($import_file_ids);
    //   try {
    //     \Drupal::service('arc_importer.import')
    //       ->doBatchImport($fid);
    //   }
    //   catch (\Exception $e) {
    //     $this->messenger()
    //       ->addError("Error when importing: " . $e->getMessage());
    //   }
    // }
    
    // $this->messenger()
    //   ->addMessage("Import Successfully!");
  }

  private function getSheet($file_path) {
    try {
      $spreadsheet = IOFactory::load($file_path);
    }
    catch (\Exception $e) {
      $this->logger()
        ->error($e->getMessage());
      return NULL;
    }
    return $spreadsheet;
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
      \Drupal::service('file_system')
        ->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

      $image_file = system_retrieve_file($img_source, $directory . $img_name, TRUE, FILE_EXISTS_REPLACE);
      if ($image_file) {
        $dom_elem = $node->getNode(0);
        $dom_elem->removeAttribute('srcset');
        $dom_elem->setAttribute('src', $image_file->url());
      }
    }

    return $image_file;
  }

  /**
   * @return string
   *  the markup
   * @throws \Exception
   *  if there is an error
   */
  protected function fetchBodyFromUrl($url) {
    $result = array();

  
    try {
      $raw_content = $this->getContentViaUrl($url);
      if (empty($raw_content)) {
        return NULL;
      }

      $crawler  = new Crawler($raw_content);
      $articles = $crawler->filter('article');

      $result['title'] = $articles->filter('div.et_post_meta_wrapper h1.entry-title')
        ->html();
      $result['created'] = $articles->filter('div.et_post_meta_wrapper p.post-meta span.published')
        ->html();
      $result['image'] = $articles->filter('div.et_post_meta_wrapper img')
        ->attr('src');
      $body = $articles->filter('div.entry-content')
        ->html();
      $parts = explode('<div class="sharedaddy sd-sharing-enabled">', $body);
      $result['body'] = $parts[0];
        // ->children()
        // ->reduce(function (Crawler $node, $i) {
        //   return $node->attr('class') != 'sharedaddy sd-sharing-enabled';
        // })
        // ->each(function (Crawler $node, $i) {
        //   return $node->html();
        // });
    
      // First meta is post header(date, feature image), second is post footer (comments, etc.)
      // $meta_filtered = $articles
      //   ->filter('div.et_post_meta_wrapper')
      //   ->first();
  
      // $meta_filtered->filter('img')
      //   ->each(function (Crawler $node, $i) {
      //     $this->processImgTag($node);
      //   });

      // // Article content
      // $article_filtered = $articles
      //   // ->filter('div.entry-content > p,blockquote');
      //   ->filter('div.entry-content')
      //   ->children()
      //   ->reduce(function (Crawler $node, $i) {
      //     return $node->attr('class') != 'sharedaddy sd-sharing-enabled';
      //   });
      // ->children('p,blockquote');
  
      // $article_filtered->filter('img')
      //   ->each(function (Crawler $node, $i) {
      //     $this->processImgTag($node);
      //   });
  
      // try {
      //   $urls = $article_filtered
      //     ->filter('a')
      //     ->each(function (Crawler $node, $i) {
      //       $url_from_child = $node
      //         ->children('img')
      //         ->attr('src');

      //       if ($url_from_child) {
      //         $node->getNode(0)
      //           ->setAttribute('href', $url_from_child);
      //       } else {

      //       }
      //       return $node->attr('href');
      //     });
      //   // print_r($urls);exit;
      // }
      // catch (\InvalidArgumentException $e) {
      //   $this->logger
      //     ->warning(
      //       $this->t('An exeption throwed while fetching from @url: @err', [
      //         '@url' => $url,
      //         '@err' => $e->getMessage()
      //       ])
      //     );
      // }
      return $result;

      $html = '';
      // foreach ($meta_filtered as $element) {
      //   $html .= $element->ownerDocument->saveHTML($element);
      // };
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

      throw $e;
    }
  }
}
