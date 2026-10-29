<?php

declare(strict_types=1);

namespace Drupal\goingsolo_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Builds the homepage.
 */
final class HomeController extends ControllerBase {

  /**
   * Hero, county map, latest deals and latest guides.
   */
  public function build(): array {
    $build['hero'] = [
      '#theme' => 'goingsolo_hero',
      '#deals_url' => Url::fromRoute('view.deals.page_deals')->toString(),
      '#guides_url' => Url::fromRoute('view.guides.page_guides')->toString(),
      '#weight' => -10,
    ];
    if ($this->moduleHandler()->moduleExists('goingsolo_maps')) {
      $build['map'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['home-section', 'home-section--map']],
        'title' => ['#markup' => '<h2>' . $this->t('Find solo deals by county') . '</h2>'],
        'map' => \Drupal::service('goingsolo_maps.builder')->countyMap(),
        '#weight' => 0,
      ];
    }
    $build['deals'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['home-section', 'home-section--deals']],
      'title' => ['#markup' => '<h2>' . $this->t('Latest solo deals') . '</h2>'],
      'view' => views_embed_view('deals', 'block_featured'),
      'more' => [
        '#type' => 'link',
        '#title' => $this->t('See all solo deals'),
        '#url' => Url::fromRoute('view.deals.page_deals'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      '#weight' => 10,
    ];
    $build['guides'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['home-section', 'home-section--guides']],
      'title' => ['#markup' => '<h2>' . $this->t('Guides for travelling solo') . '</h2>'],
      'view' => views_embed_view('guides', 'block_latest'),
      'more' => [
        '#type' => 'link',
        '#title' => $this->t('Read all guides'),
        '#url' => Url::fromRoute('view.guides.page_guides'),
        '#attributes' => ['class' => ['button']],
      ],
      '#weight' => 20,
    ];
    return $build;
  }

}
