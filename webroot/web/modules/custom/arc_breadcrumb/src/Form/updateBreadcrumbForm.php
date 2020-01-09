<?php
namespace Drupal\arc_breadcrumb\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Query\EntityFieldQuery;

class updateBreadcrumbForm extends FormBase {
    /**
   * {@inheritdoc}
   */

  public function getFormId() {
    return 'arc_breadcrumb_update';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['option'] = [
      '#title' => 'Option',
      '#type' => 'select',
      '#options' => [
        'update_breadcrumb' => 'Update Breadcrumb',
      ]
    ];

    $form['content_type'] = [
      '#title' => 'Content type',
      '#type' => 'select',
      '#options' => [
        'all' => 'All',
        'article' => 'Article',
        'page' => 'Basic page',
      ]
    ];

  	$form['submit'] = [
  		'#type'  => 'submit',
  		'#value' => $this->t('Apply'),
    ];

  	return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  	switch ($form['option']['#value']){
      case 'update_breadcrumb':
        {
          switch ($form['content_type']['#value']){
            case 'all':{
              // kint(EntityFieldQuery::$fieldConditions); exit;
              $nodes = \Drupal::entityQuery('node')
                ->execute();
              foreach ($nodes as $value){
                $node = Node::load($value);
                if ($node->hasField('field_breadcrumbs')){
                  _arc_breadcrumb_update_breadcrumb($node);
                  $node->save();
                }
              }
            }
            case 'article':{
              $nodes = \Drupal::entityQuery('node')
                ->condition('type', 'article')
                ->execute();
              foreach ($nodes as $value){
                $node = Node::load($value);
                if ($node->hasField('field_breadcrumbs')){
                  _arc_breadcrumb_update_breadcrumb($node);
                  $node->save();
                }
              }
              
            }
            case 'page':{
              $nodes = \Drupal::entityQuery('node')
                ->condition('type', 'page')
                ->execute();
              foreach ($nodes as $value){
                $node = Node::load($value);
                if ($node->hasField('field_breadcrumbs')){
                  _arc_breadcrumb_update_breadcrumb($node);
                  $node->save();
                }
              }
              
            }
            default:{
            
            }
          }
          break;
        }
      default:
      {

      }
    }
  }
}