<?php

namespace Drupal\arc_breadcrumb\Plugin\Block;

// use Drupal\Core\Block\BlockBase;
// use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
// use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
// use Drupal\Core\Routing\RouteMatchInterface;
// use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\Views;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\TitleResolverInterface;

/**
 * Provides a block to display the breadcrumbs.
 *
 * @Block(
 *   id = "arc_breadcrumb_block",
 *   admin_label = @Translation("Arc Breadcrumbs")
 * )
 */
class ArcBreadcrumbBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The breadcrumb manager.
   *
   * @var \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface
   */
  protected $breadcrumbManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new SystemBreadcrumbBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface $breadcrumb_manager
   *   The breadcrumb manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, BreadcrumbBuilderInterface $breadcrumb_manager, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->breadcrumbManager = $breadcrumb_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('breadcrumb'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $menu_name = 'main';
    $menu_tree = \Drupal::menutree();
    $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($menu_name);
    $tree = $menu_tree->load($menu_name, $parameters);
    $manipulators = array(
      array('callable' => 'menu.default_tree_manipulators:checkAccess'),
      array('callable' => 'menu.default_tree_manipulators:generateIndexAndSort'),
    );
    $tree = $menu_tree->transform($tree, $manipulators);
  
    // Finally, build a renderable array from the transformed tree.
    $menu_tmp = $menu_tree->build($tree);
    $menu = array();

    $breadcrumb = $this->breadcrumbManager->build($this->routeMatch)->toRenderable();

    //Case 1: Node
    $node = \Drupal::routeMatch()->getParameter('node');
    if (isset($node)){
      if ($node->hasField('field_breadcrumbs')){
        $breadcrumbs = $node->get('field_breadcrumbs')->getValue();
        if (isset($breadcrumbs)){
          $breadcrumb = $breadcrumbs[0]['value'];
          // // kint($breadcrumb);
          // $renderable = [
          //   '#theme' => 'arc_breadcrumb',
          //   '#breadcrumb' => $breadcrumb,
          // ];
          // $rendered = \Drupal::service('renderer')->render($renderable);
          return [
            '#markup' => $breadcrumb,
          ];
        }
      }
    }
    else{
      //Case 2: Term
      $term = \Drupal::routeMatch()->getParameter('taxonomy_term');
      if (isset($term)){
        $breadcrumb_arr_local = [
          ['url' => '/', 'text' => 'Home'],
        ];

        if($term->hasField('field_related_categories')){
          $first_properties_obj = $term->get('field_related_categories')->first();
          if (isset($first_properties_obj)){

            $tid_group_obj = $first_properties_obj->getValue();
            $tid_group = $tid_group_obj['target_id'];
            $term_group = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid_group);
            $term_group_name = $term_group->getName();
            $term_group_url = $term_group->url();
            
            //Research isn't follow the rule => can't synchronize
            if (strtolower($term_group_name) == strtolower('Research')){
              foreach ($menu_tmp['#items'] as $item){
                if (strtolower($item['title']) == strtolower($term_group_name)){
                  $menu = ['url' => $item['url']->toString(), 'text' => $item['title']];
                  break;
                }
              }
            }
            else{
              foreach ($menu_tmp['#items'] as $item) {
                if (count($item['below'])){
                  foreach ($item['below'] as $children){
                    if ($children['title'] == $term_group_name) {
                      $menu = ['url' => $item['url']->toString(), 'text' => $item['title']];
                      $menu_child = ['url' => $children['url']->toString(), 'text' => $term_group_name];
                    };
                  }
                };
              };
            }
            
            if (count($menu)){
              array_push($breadcrumb_arr_local, $menu);
            };                          
            if (count($menu_child)){
              array_push($breadcrumb_arr_local, $menu_child);
            };
          }
          else{ //IF don't have related categories => set parent link to Research Topics Page.
            foreach ($menu_tmp['#items'] as $item) {
              if ($children['title'] == 'Research') {
                $menu = ['url' => $item['url']->toString(), 'text' => $item['title']];
              };
            };
            if (count($menu))
              array_push($breadcrumb_arr_local, $menu);
          }
        };

        $term_name = $term->getName();
        $term_url = $term->url();
        
        array_push($breadcrumb_arr_local, ['text' => $term_name]);
        
        $renderable = [
          '#theme' => 'arc_breadcrumb',
          '#breadcrumb' => $breadcrumb_arr_local,
        ];
        $rendered = \Drupal::service('renderer')->render($renderable);
        
        return [
          '#markup' => $rendered,
        ];
      }
      else{
        //Case 3: View
        $breadcrumb_arr_for_view = [
          ['url' => '/', 'text' => 'Home'],
        ];
        $route = \Drupal::routeMatch()->getRouteObject();
        if ($route) {
          $view_id = $route->getDefault('view_id');
          $display_id = $route->getDefault('display_id');

          //Case 3.1: Page view filter by Categroy Group
          if ($view_id == 'community_archives' || $view_id == 'council_archives' 
          || $view_id == 'research_published' || $view_id == 'research_people'
          ){
            $display_id = 'default';
            if (!empty($view_id) && !empty($display_id)) {
              $view = Views::getView($view_id);
              $rows = views_get_view_result($view_id, $display_id);
              $display_view = $view->storage->get('display');
              if(count($display_view[$display_id])){
                //Did it need to check display has element: 'filters'?
                $tid_filters = $display_view[$display_id]['display_options']['filters']['field_related_categories_target_id']['value'];
                
                $first_key = array_keys($tid_filters)[0];
                $tid_filter = $tid_filters[$first_key];
                if (!empty($tid_filter)){
                  $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid_filter);
                  if (isset($term)){
                    $term_name = $term->getName();
                    foreach ($menu_tmp['#items'] as $item) {
                      if (count($item['below'])){
                        foreach ($item['below'] as $children){
                          if (strtolower($children['title']) == strtolower($term_name)) {
                            $menu = ['url' => $item['url']->toString(), 'text' => $item['title']];
                            $menu_child = ['text' => $term_name];
                            break;
                          };
                        }
                      };
                    };
                    if (count($menu)){
                      array_push($breadcrumb_arr_for_view, $menu);
                    };                          
                    if (count($menu_child)){
                      array_push($breadcrumb_arr_for_view, $menu_child);
                    };
                  };
                };
              };
            }
          };//Done Breadcrumb

          //Case 3.2: Block View or Page View filter by Categories 1 or 2 or 3.  (Categories element)
          if (
            $view_id == 'published_contents'
            || $view_id == 'archaeology_collections' || $view_id == 'art_works_collections' 
            || $view_id == 'civic_collections' || $view_id == 'cultural_collections'
            || $view_id == 'video_collection' || $view_id == 'sound_collection' || $view_id == 'photography_collection'
          ){
            if (!empty($view_id) && !empty($display_id)) {
              $view = Views::getView($view_id);
              $display_view = $view->storage->get('display');
              if (!array_key_exists('filters', $display_view[$display_id]['display_options'])){
                $display_id = 'default';
              };
              if(count($display_view[$display_id])){
                $tid_filters = $display_view[$display_id]['display_options']['filters']['tid']['value'];
                
                $first_key = array_keys($tid_filters)[0];
                $tid_filter = $tid_filters[$first_key];
                if (!empty($tid_filter)){
                  $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid_filter);
                  if (isset($term)){
                    if($term->hasField('field_related_categories')){
                      $first_properties_obj = $term->get('field_related_categories')->first();
                      if (isset($first_properties_obj)){
            
                        $tid_group_obj = $first_properties_obj->getValue();
                        $tid_group = $tid_group_obj['target_id'];
                        $term_group = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid_group);
                        $term_group_name = $term_group->getName();
                        $term_group_url = $term_group->url();
                      
                        foreach ($menu_tmp['#items'] as $item) {
                          if (count($item['below'])){
                            foreach ($item['below'] as $children){
                              if (strtolower($children['title']) == strtolower($term_group_name)) {
                                $menu = ['url' => $item['url']->toString(), 'text' => $item['title']];
                                $menu_child = ['url' => $children['url']->toString(), 'text' => $term_group_name];
                                break;
                              };
                            }
                          };
                        };

                        if (count($menu)){
                          array_push($breadcrumb_arr_for_view, $menu);
                        };                          
                        if (count($menu_child)){
                          array_push($breadcrumb_arr_for_view, $menu_child);
                        };
                      };
                    };
                    array_push($breadcrumb_arr_for_view, ['text' => $term->getName()]);
                  };
                };
              };
            }
          };//Done If, Case 3.2

          //Case 3.3: Page view filter don't follow format.
              //Example: research topics page.  ($view_id = research_topics);
          if (
            $view_id == 'research_topics'
          ){
            if (!empty($view_id) && !empty($display_id)) {
              $view = Views::getView($view_id);
              $display_view = $view->storage->get('display');
              $display_id = 'default';
              $display_options = $display_view['default']['display_options'];
              array_push($breadcrumb_arr_for_view, ['text' => $display_view[$display_id]['display_options']['title']]);
            };
          };//Done if, Case 3.3
        };

        $renderable = [
          '#theme' => 'arc_breadcrumb',
          '#breadcrumb' => $breadcrumb_arr_for_view,
        ];
        $rendered = \Drupal::service('renderer')->render($renderable);
        // kint($rendered);
        return [
          '#markup' => $rendered,
        ];
      }
    }
    return $breadcrumb;
  }

  /**
   * Rendered breadcrumb
   */
  function arc_breadcrumb_theme($existing, $type, $theme, $path){
    return  [
      'arc_breadcrumb' => [
        'variables' => [
          'breadcrumb' => NULL,
        ],
      ],
    ];
  }

  /**
   * Clear cache
   */
  public function getCacheMaxAge() {
    return 0;
  }
}
