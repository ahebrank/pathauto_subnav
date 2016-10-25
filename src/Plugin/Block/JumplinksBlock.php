<?php

namespace Drupal\pathauto_subnav\Plugin\Block;

use Drupal\pathauto_subnav\Plugin\Block\NavBlockBase;

/**
 * Provides a 'Jumplinks' block.
 *
 * @Block(
 *  id = "jumplinks_block",
 *  admin_label = @Translation("Jumplinks block")
 * )
 */
class JumplinksBlock extends NavBlockBase {

  // TODO: add configuration for selection of these bundles
  private $allowed_bundles = ['titled_content'];

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->getNode();

    if (!$node) {
      return [];
    }

    $jumplinks = $this->jumpLinks($node);
    if (empty($jumplinks)) {
      return [];
    }

    $output = [
      '#cache' => [
       'max-age' => 0,
     ],
    ];

    $jumplinks = array_map([$this, 'linkMarkup'], $jumplinks);
    $output['jumplinks'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $jumplinks,
      '#prefix' => '<h3 class="space-below">Jump to:</h3>',
    ];

    return $output;
  }

  /**
   * get a bunch of titled sections from a node, for jumplinks
   * @param  [type] $node [description]
   * @return [type]       array of title/url arrays
   */
  private function jumpLinks($node) {
    if (!$node) return null;

    $items = [];
    $fields = $node->getFieldDefinitions();
    foreach ($fields as $key => $f) {
      if (strpos($key, 'field_')!==0) continue;
      if ($f->getType() == 'entity_reference_revisions') {
        // paragraph
        foreach($node->$key->referencedEntities() as $delta => $p) {
          if (!in_array($p->bundle(), $this->allowed_bundles)) continue;
          // look for a title field
          if (!isset($p->field_title)) continue;
          $title = $p->field_title[0]->value;
          if (empty($title)) continue;
          $id = \Drupal\Component\Utility\Html::getId($title);
          $items[] = [
            'title' => $title,
            'url' => '#'.$id,
          ];
        }
      }
    }
    
    return $items;
  }

}