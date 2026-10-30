<?php

declare(strict_types=1);

namespace Drupal\goingsolo_maps;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Builds render arrays for the county map and the deal location maps.
 */
final class MapBuilder {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly ModuleExtensionList $moduleList,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Map of Ireland with each county shaded by its number of solo deals.
   */
  public function countyMap(): array {
    $counts = $this->database->query(
      "SELECT c.field_county_target_id AS tid, COUNT(DISTINCT n.nid) AS total
       FROM {node__field_county} c
       INNER JOIN {node_field_data} n ON n.nid = c.entity_id
       WHERE n.status = 1 AND n.type = 'listing'
       GROUP BY c.field_county_target_id"
    )->fetchAllKeyed();

    $counties = [];
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => 'county']);
    foreach ($terms as $term) {
      $map_name = $term->get('field_map_name')->value ?: $term->label();
      $counties[$map_name] = [
        'label' => $term->label(),
        'url' => $term->toUrl()->toString(),
        'count' => (int) ($counts[$term->id()] ?? 0),
      ];
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['gs-map', 'gs-map--counties'],
        'data-gs-map' => 'counties',
        'role' => 'region',
        'aria-label' => (string) t('Map of Ireland showing solo deals by county'),
      ],
      '#attached' => [
        'library' => ['goingsolo_maps/maps'],
        'drupalSettings' => [
          'goingsoloMaps' => [
            'geojson' => $this->geojsonUrl(),
            'counties' => $counties,
          ],
        ],
      ],
      '#cache' => ['tags' => ['node_list:listing', 'taxonomy_term_list:county']],
    ];
  }

  /**
   * Map of deal locations, optionally limited to one county or feature.
   */
  public function dealsMap(?int $county_tid = NULL, ?string $feature = NULL): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'listing')
      ->condition('status', 1)
      ->exists('field_latitude')
      ->exists('field_longitude')
      ->range(0, 500);
    if ($county_tid) {
      $query->condition('field_county', $county_tid);
    }
    if ($feature) {
      $query->condition('field_solo_features', $feature);
    }
    $points = [];
    foreach ($storage->loadMultiple($query->execute()) as $node) {
      $price = $node->get('field_solo_price')->value;
      $points[] = [
        'lat' => (float) $node->get('field_latitude')->value,
        'lng' => (float) $node->get('field_longitude')->value,
        'title' => $node->label(),
        'url' => $node->toUrl()->toString(),
        'price' => $price !== NULL ? '€' . number_format((float) $price, 0) : '',
        'basis' => (string) $node->get('field_price_basis')->value,
        'featured' => (bool) $node->get('field_featured')->value,
      ];
    }
    if (!$points) {
      return ['#cache' => ['tags' => ['node_list:listing']]];
    }
    $id = 'deals-' . ($county_tid ?: 'all') . ($feature ? '-' . $feature : '');
    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['gs-map', 'gs-map--deals'],
        'data-gs-map' => 'deals',
        'data-gs-map-id' => $id,
        'role' => 'region',
        'aria-label' => (string) t('Map of solo deal locations'),
      ],
      '#attached' => [
        'library' => ['goingsolo_maps/maps'],
        'drupalSettings' => ['goingsoloMaps' => ['points' => [$id => $points]]],
      ],
      '#cache' => ['tags' => ['node_list:listing']],
    ];
  }

  /**
   * URL of the county outlines file.
   */
  private function geojsonUrl(): string {
    return $this->fileUrlGenerator->generateString($this->moduleList->getPath('goingsolo_maps') . '/data/counties.geojson');
  }

}
