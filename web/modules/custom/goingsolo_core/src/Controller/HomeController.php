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
   * Hero with county map, shortcuts, deals, pet-friendly stays and guides.
   */
  public function build(): array {
    $deals_url = Url::fromRoute('view.deals.page_deals');
    $build['hero'] = [
      '#theme' => 'goingsolo_hero',
      '#deals_url' => $deals_url->toString(),
      '#guides_url' => Url::fromRoute('view.guides.page_guides')->toString(),
      '#map' => $this->moduleHandler()->moduleExists('goingsolo_maps') ? \Drupal::service('goingsolo_maps.builder')->countyMap() : NULL,
      '#stats' => $this->stats(),
      '#cache' => ['tags' => ['node_list']],
      '#weight' => -10,
    ];
    $build['ways'] = [
      '#theme' => 'goingsolo_ways_in',
      '#items' => $this->waysIn(),
      '#weight' => -5,
    ];
    $build['deals'] = [
      '#theme' => 'goingsolo_home_section',
      '#modifier' => 'deals',
      '#eyebrow' => $this->t('Checked this month'),
      '#title' => $this->t('Latest solo deals'),
      '#content' => views_embed_view('deals', 'block_featured'),
      '#link' => $this->link($this->t('See all solo deals'), $deals_url),
      '#weight' => 10,
    ];
    $build['pets'] = [
      '#theme' => 'goingsolo_home_section',
      '#modifier' => 'pets',
      '#eyebrow' => $this->t('Pet friendly'),
      '#title' => $this->t('Bring the dog'),
      '#intro' => $this->t('Travelling alone is easier with company. These stays welcome dogs and tell you exactly where they can go.'),
      '#content' => views_embed_view('deals', 'block_pets'),
      '#link' => $this->link($this->t('All pet-friendly stays'), Url::fromRoute('view.deals.page_pets')),
      '#weight' => 20,
    ];
    if ($this->moduleHandler()->moduleExists('goingsolo_commerce')) {
      $build['club'] = [
        '#theme' => 'goingsolo_club_band',
        '#join_url' => Url::fromUserInput('/membership')->toString(),
        '#weight' => 30,
      ];
    }
    $build['guides'] = [
      '#theme' => 'goingsolo_home_section',
      '#modifier' => 'guides',
      '#eyebrow' => $this->t('Guides'),
      '#title' => $this->t('Good to know before you go'),
      '#content' => views_embed_view('guides', 'block_latest'),
      '#link' => $this->link($this->t('Read all guides'), Url::fromRoute('view.guides.page_guides')),
      '#weight' => 40,
    ];
    return $build;
  }

  /**
   * Counts shown under the hero.
   */
  private function stats(): array {
    $query = fn() => $this->entityTypeManager()->getStorage('node')->getQuery()->accessCheck(TRUE)->condition('status', 1);
    $deals = (int) $query()->condition('type', 'listing')->count()->execute();
    $guides = (int) $query()->condition('type', 'article')->count()->execute();
    $counties = (int) \Drupal::database()->query(
      "SELECT COUNT(DISTINCT c.field_county_target_id) FROM {node__field_county} c
       INNER JOIN {node_field_data} n ON n.nid = c.entity_id
       WHERE n.status = 1 AND n.type = 'listing'"
    )->fetchField();
    return [
      ['value' => $deals, 'label' => $this->t('checked deals')],
      ['value' => $counties, 'label' => $this->t('counties')],
      ['value' => $guides, 'label' => $this->t('solo guides')],
    ];
  }

  /**
   * Shortcut tiles.
   */
  private function waysIn(): array {
    $deals = Url::fromRoute('view.deals.page_deals');
    $items = [
      [
        'modifier' => 'nosupplement',
        'icon' => '1',
        'title' => $this->t('No single supplement'),
        'text' => $this->t('Pay the same as everyone else'),
        'url' => (clone $deals)->setOption('query', ['supplement' => 'none'])->toString(),
      ],
      [
        'modifier' => 'reduced',
        'icon' => '½',
        'title' => $this->t('Reduced solo rates'),
        'text' => $this->t('A lower price for one'),
        'url' => (clone $deals)->setOption('query', ['supplement' => 'reduced'])->toString(),
      ],
      [
        'modifier' => 'pets',
        'icon' => '🐾',
        'title' => $this->t('Pet friendly'),
        'text' => $this->t('Stays that welcome your dog'),
        'url' => Url::fromRoute('view.deals.page_pets')->toString(),
      ],
    ];
    if (Url::fromUserInput('/activities')->isRouted()) {
      $items[] = [
        'modifier' => 'activities',
        'icon' => '✦',
        'title' => $this->t('Solo activities'),
        'text' => $this->t('Tours and classes to join alone'),
        'url' => Url::fromUserInput('/activities')->toString(),
      ];
    }
    return $items;
  }

  /**
   * A section link.
   */
  private function link($title, Url $url): array {
    return ['#type' => 'link', '#title' => $title, '#url' => $url, '#attributes' => ['class' => ['gs-arrow-link']]];
  }

}
