<?php

declare(strict_types=1);

namespace Drupal\goingsolo_core\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Copyright line and disclosure for the footer.
 */
#[Block(
  id: 'goingsolo_footer_note',
  admin_label: new TranslatableMarkup('Footer note'),
  category: new TranslatableMarkup('Going Solo'),
)]
final class FooterNoteBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [
      '#markup' => '<p class="gs-footer-note">' . $this->t('© @year goingsolo.ie. Prices and availability change, so always confirm the solo rate with the provider. Some links may earn us a commission.', ['@year' => date('Y')]) . '</p>',
      '#cache' => ['max-age' => 86400],
    ];
  }

}
