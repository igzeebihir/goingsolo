<?php

declare(strict_types=1);

namespace Drupal\goingsolo_maps\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * County map of Ireland.
 */
#[Block(
  id: 'goingsolo_county_map',
  admin_label: new TranslatableMarkup('Solo deals county map'),
  category: new TranslatableMarkup('Going Solo'),
)]
final class CountyMapBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return \Drupal::service('goingsolo_maps.builder')->countyMap();
  }

}
