<?php

namespace Drupal\pathauto_subnav\Plugin\Block;

use Drupal\pathauto_subnav\Plugin\Block\NavBlockBase;

/**
 * Provides a 'Sidenav' block.
 *
 * @Block(
 *  id = "sidenav_block",
 *  admin_label = @Translation("Sidenav block")
 * )
 */
class SidenavBlock extends NavBlockBase {

  // filter out content types except for these (usually)
  var $parent = null;

  /**
   * {@inheritdoc}
   */
  public function build() {
    $menu = $this->makeMenu();
    $items = array_map([$this, 'linkMarkup'], $menu);

    $output = [
      '#cache' => [
       'max-age' => 0,
     ],
    ];

    if ($this->parent) {
      $output['parent'] = $this->linkMarkup($this->parent, 'back-link');
      $output['top_hr'] = [
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
    $title = '';

    $node = $this->getNode();
    if ($node) {
      $title = $node->get('title');
      if ($title) {
        $title = $title->value;
      }
    }

    $current_page = [
      'url' => $current,
      'title' => $title,
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
          'title' => $this->getTitleFromPath($record->source),
          'url' => $record->alias,
          ];
      }

      if ($n > 1) {
        // second level or below
        if (strpos($record->alias, $parent . '/')===0 && $n == $record_n) {
          $siblings[$record->pid] = [
              'title' => $this->getTitleFromPath($record->source),
              'url' => $record->alias,
            ];
        }
      }
      else {
        // top level
        if ($record_n == 1) {
          $siblings[$record->pid] = [
              $this->getTitleFromPath($record->source),
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

    return $siblings;
  }

}