<?php

declare(strict_types=1);

namespace Drupal\goingsolo_ads\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;

/**
 * Serves /ads.txt from the ads settings.
 */
final class AdsTxtController extends ControllerBase {

  /**
   * Returns the ads.txt file.
   */
  public function content(): CacheableResponse {
    $config = $this->config('goingsolo_ads.settings');
    $lines = [];
    $publisher = (string) $config->get('publisher_id');
    if ($publisher !== '') {
      $lines[] = 'google.com, ' . str_replace('ca-', '', $publisher) . ', DIRECT, f08c47fec0942fa0';
    }
    $extra = trim((string) $config->get('ads_txt_extra'));
    if ($extra !== '') {
      $lines[] = $extra;
    }
    $response = new CacheableResponse(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    $response->addCacheableDependency(CacheableMetadata::createFromObject($config));
    return $response;
  }

}
