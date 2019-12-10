<?php

namespace Drupal\arc_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ImporterForm extends FormBase {
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
    // $this->database = \Drupal::database();
    // $query = $this->database
    //   ->select('wp_2_posts', 'wp')
    //   ->fields('wp')
    //   ->range(2, 1);
    // $results = $query->execute();

    // foreach ($results as $id => $row) {
    //   print_r($id);
    //   print_r($row->ID);
    //   // print_r($row->post_content);
    //   echo '<br>';
    // }
    // exit;
    
  
    $form['import_file'] = [
      '#type'  => 'managed_file',
      '#name'  => 'import_file',
      '#title' => $this->t('Choose A File To Import'),
      '#description' => $this->t('File contains data to import (only *.xlsx allowed)'),
      // '#required'    => TRUE,
      '#upload_validators' => [
        'file_validate_extensions' => ['xlsx']
      ],
      '#upload_location' => 'public://import-files/',
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
    $import_file_ids = $form_state->getValue('import_file', '');

    if (empty($import_file_ids) || empty($import_file_ids[0])) {
      $this->messenger()
        ->addError('Please choose a file to import!');
      return;
    }
    else {
      $fid = reset($import_file_ids);
      try {
        \Drupal::service('arc_importer.import')
          ->doBatchImport($fid);
      }
      catch (\Exception $e) {
        $this->messenger()
          ->addError("Error when importing: " . $e->getMessage());
      }
    }
    
    $this->messenger()
      ->addMessage("Import Successfully!");
  }
}
