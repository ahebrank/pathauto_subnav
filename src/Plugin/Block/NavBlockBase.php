<?php

namespace Drupal\pathauto_subnav\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Inheritable nav block
 *
**/
abstract class NavBlockBase extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
   return $account->hasPermission('access content')? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * figure out the current node
   * @return [type] [description]
   */
  protected function getNode() {
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node) {
      return $node;
    }
    return FALSE;
  }
  
  /**
   * look up a title from a path
   * @param  str $path [description]
   * @param  arr $included_types optionally filter down to certain content types
   * @return [type]       [description]
   */
  protected function getTitleFromPath($path, $included_types = null) {
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
  protected function sortNav($a, $b) {
    return strnatcmp($a['title'], $b['title']);
  }

  /**
   * render array for a link
   * @param  arr $item thing with a title and a url
   * @return arr
   */
  protected function linkMarkup($item, $classes = '') {
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
