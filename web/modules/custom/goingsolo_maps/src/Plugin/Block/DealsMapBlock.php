<?php

declare(strict_types=1);

namespace Drupal\goingsolo_maps\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Map of all solo deal locations.
 */
#[Block(
  id: 'goingsolo_deals_map',
  admin_label: new TranslatableMarkup('Solo deals location map'),
  category: new TranslatableMarkup('Going Solo'),
)]
final class DealsMapBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return \Drupal::service('goingsolo_maps.builder')->dealsMap();
  }

}
