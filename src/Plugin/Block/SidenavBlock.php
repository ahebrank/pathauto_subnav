<?php

namespace Drupal\pathauto_subnav\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a 'Sidenav' block.
 *
 * @Block(
 *  id = "sidenav_block",
 *  admin_label = @Translation("Sidenav block")
 * )
 */
class SidenavBlock extends BlockBase {

  var $menu = null;
  var $parent = null;

  // filter out content types except for these (usually)
  var $included_types = ['generic', 'landing', 'redirect'];


  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, $return_as_object = false) {
    return $account->hasPermission('access content');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $this->makeMenu();
    $items = array_map([$this, 'linkMarkup'], $this->menu);

    $output = [
      '#cache' => [
       'max-age' => 0,
     ],
    ];

    if ($this->parent) {
      $output['parent'] = $this->linkMarkup($this->parent, 'back-link');
      $output['hr'] = [
        '#markup' => '<hr>'
      ];
    }

    $output['nav'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $items,
    ];

    return $output;
  }

  /**
   * construct a navigation list
   * @return hierarchical list
   */
  private function makeMenu() {
    $current_path = \Drupal::service('path.current')->getPath();
    $current = \Drupal::service('path.alias_manager')->getAliasByPath($current_path);

    $slugs = explode('/', $current);
    $n = count($slugs);
    if ($n == 1) {
      $parent = '';
    }
    else {
      array_pop($slugs);
      $parent = implode('/', $slugs);
    }

    $siblings = [];

    $node = \Drupal::routeMatch()->getParameter('node');
    $current_page = [
      'url' => $current,
      'title' => $node->getTitle(),
      'current' => TRUE,
    ];
    $current_page_pid = 0;

    $result = db_query('SELECT * FROM url_alias');
    foreach ($result as $record) {
      if ($record->alias == $current) {
        $current_page_pid = $record->pid;
      }

      if ($record->alias == $parent) {
        $this->parent = [
          'title' => $this->getTitleFromPath($record->source),
          'url' => $record->alias,
        ];
      }

      $record_n = count(explode('/', $record->alias));

      if (strpos($record->alias, $current . '/')===0
            && $record_n == ($n + 1)) {
        if (!isset($current_page['children'])) {
          $current_page['children'] = [];
        }
        $current_page['children'][$record->pid] = [
          'title' => $this->getTitleFromPath($record->source, $this->included_types),
          'url' => $record->alias,
          ];
      }

      if ($n > 1) {
        // second level or below
        if (strpos($record->alias, $parent . '/')===0 && $n == $record_n) {
          $siblings[$record->pid] = [
              'title' => $this->getTitleFromPath($record->source, $this->included_types),
              'url' => $record->alias,
            ];
        }
      }
      else {
        // top level
        if ($record_n == 1) {
          $siblings[$record->pid] = [
              $this->getTitleFromPath($record->source, $this->included_types),
              'url' => $record->alias,
            ];
        }
      }
    }

    if (isset($current_page['children']) && count($current_page['children'])) {
      usort($current_page['children'], [$this, 'sortNav']);
    }
    $siblings[$current_page_pid] = $current_page;
    usort($siblings, [$this, 'sortNav']);

    $this->menu = $siblings;
  }

  
  /**
   * look up a title from a path
   * @param  str $path [description]
   * @param  arr $included_types optionally filter down to certain content types
   * @return [type]       [description]
   */
  private function getTitleFromPath($path, $included_types = null) {
    $params = \Drupal\Core\Url::fromUserInput($path)->getRouteParameters();
    if (isset($params['node'])) {
      $node = \Drupal\node\Entity\Node::load($params['node']);
      if ($node) {
        if (!is_array($included_types) || in_array($node->getType(), $included_types)) {
          return $node->getTitle();
        }
      }
    }
    return null;
  }

  /**
   * sort the nav alphabetically
   * @param  [type] $a [description]
   * @param  [type] $b [description]
   * @return [type]    [description]
   */
  private function sortNav($a, $b) {
    return strnatcmp($a['title'], $b['title']);
  }

  /** 
   * render array for a link
   * @param  arr $item thing with a title and a url
   * @return arr
   */
  private function linkMarkup($item, $classes = '') {
    $markup = '<a class="' . $classes . '" href="' . $item['url'] . '">' . $item['title'] . '</a>';
    $output = ['#markup' => $markup];
    if (isset($item['current']) && $item['current']) {
      $output['#wrapper_attributes']['class'][] = 'active';
    }
    if (isset($item['children']) && count($item['children']) > 0) {
      $output['childnav'] = [
        '#items' => array_map([$this, 'linkMarkup'], $item['children']),
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        ];
      $output['#wrapper_attributes']['class'][] = 'has-children';
    }
    return $output;
  }
}