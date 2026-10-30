<?php

declare(strict_types=1);

namespace Drupal\goingsolo_explore\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves a route's stops as a GPX file for phone map apps.
 */
final class GpxController extends ControllerBase {

  /**
   * GPX download.
   */
  public function download(NodeInterface $node): Response {
    if ($node->bundle() !== 'route') {
      throw new NotFoundHttpException();
    }
    $stops = goingsolo_explore_stops($node);
    $esc = fn(string $text) => htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<gpx version="1.1" creator="goingsolo.ie" xmlns="http://www.topografix.com/GPX/1/1">' . "\n";
    $xml .= '  <metadata><name>' . $esc($node->label()) . '</name><link href="' . $esc($node->toUrl('canonical', ['absolute' => TRUE])->toString()) . '"/></metadata>' . "\n";
    foreach ($stops as $stop) {
      $xml .= sprintf('  <wpt lat="%.6F" lon="%.6F"><name>%s</name><desc>%s</desc></wpt>', $stop['lat'], $stop['lng'], $esc($stop['name']), $esc('Day ' . $stop['day'])) . "\n";
    }
    $xml .= '  <rte><name>' . $esc($node->label()) . "</name>\n";
    foreach ($stops as $stop) {
      $xml .= sprintf('    <rtept lat="%.6F" lon="%.6F"><name>%s</name></rtept>', $stop['lat'], $stop['lng'], $esc($stop['name'])) . "\n";
    }
    $xml .= "  </rte>\n</gpx>\n";
    $filename = preg_replace('/[^a-z0-9]+/', '-', strtolower($node->label())) . '.gpx';
    return new Response($xml, 200, [
      'Content-Type' => 'application/gpx+xml; charset=utf-8',
      'Content-Disposition' => 'attachment; filename="' . trim($filename, '-') . '"',
    ]);
  }

}
