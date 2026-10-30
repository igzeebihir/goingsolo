<?php

declare(strict_types=1);

namespace Drupal\goingsolo_content;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;

/**
 * Imports starter content from the module's data folder.
 *
 * Every import is keyed on the title or name, so running it again only adds
 * what is missing.
 */
final class ContentImporter {

  /**
   * Term IDs keyed by vocabulary and name.
   */
  private array $terms = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleExtensionList $moduleList,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Imports everything.
   */
  public function importAll(): array {
    $counts = [];
    $counts['terms'] = $this->importTerms();
    $counts['listings'] = $this->importListings();
    $counts['articles'] = $this->importArticles();
    $counts['pages'] = $this->importPages();
    $counts['activities'] = $this->importActivities();
    $counts['routes'] = $this->importRoutes();
    $counts['events'] = $this->importEvents();
    $this->createContactForm();
    $this->loggerFactory->get('goingsolo_content')->notice('Starter content imported: @c', ['@c' => json_encode($counts)]);
    return $counts;
  }

  /**
   * Imports counties, stay types and topics.
   */
  public function importTerms(): int {
    $data = Yaml::decode(file_get_contents($this->dataPath('terms.yml')));
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $created = 0;
    foreach ($data as $vid => $terms) {
      $weight = 0;
      foreach ($terms as $item) {
        $item = is_string($item) ? ['name' => $item] : $item;
        if ($this->termId($vid, $item['name'])) {
          continue;
        }
        $values = [
          'vid' => $vid,
          'name' => $item['name'],
          'weight' => $weight++,
          'description' => ['value' => $item['description'] ?? '', 'format' => 'basic_html'],
        ];
        if ($vid === 'county') {
          $values['field_province'] = $item['province'];
          $values['field_map_name'] = $item['map_name'] ?? $item['name'];
          $values['path'] = ['alias' => '/county/' . $this->slug($item['name'])];
        }
        $term = $storage->create($values);
        $term->save();
        $this->terms[$vid][$item['name']] = (int) $term->id();
        $created++;
      }
    }
    return $created;
  }

  /**
   * Imports solo deals.
   */
  public function importListings(): int {
    $data = Yaml::decode(file_get_contents($this->dataPath('listings.yml')));
    $created = 0;
    foreach ($data as $item) {
      if ($this->nodeExists('listing', $item['title'])) {
        $this->updatePetDetails($item);
        continue;
      }
      $values = [
        'type' => 'listing',
        'title' => $item['title'],
        'status' => (int) ($item['published'] ?? 1),
        'uid' => 1,
        'body' => ['value' => $this->markdown($item['description']), 'summary' => $item['summary'] ?? '', 'format' => 'basic_html'],
        'field_provider' => $item['provider'],
        'field_town' => $item['town'] ?? NULL,
        'field_county' => $this->termId('county', $item['county']),
        'field_listing_type' => $this->termId('listing_type', $item['type']),
        'field_solo_price' => $item['price'] ?? NULL,
        'field_price_basis' => $item['price_basis'] ?? NULL,
        'field_supplement' => $item['supplement'],
        'field_solo_features' => $item['features'] ?? [],
        'field_offer_link' => ['uri' => $item['url']],
        'field_valid_until' => $item['valid_until'] ?? NULL,
        'field_last_checked' => $item['last_checked'],
        'field_latitude' => $item['lat'] ?? NULL,
        'field_longitude' => $item['lng'] ?? NULL,
        'field_featured' => (int) ($item['featured'] ?? 0),
        'field_members_only' => (int) ($item['members_only'] ?? 0),
        'field_source_note' => $item['source'] ?? NULL,
        'field_pet_policy' => $item['pet_policy'] ?? NULL,
        'path' => ['alias' => '/deals/' . $this->slug($item['county']) . '/' . $this->slug($item['title'])],
      ];
      $this->entityTypeManager->getStorage('node')->create($values)->save();
      $created++;
    }
    return $created;
  }

  /**
   * Adds pet details to a listing imported before they were in the data.
   */
  private function updatePetDetails(array $item): void {
    if (empty($item['pet_policy'])) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'listing')->condition('title', $item['title'])->execute();
    foreach ($storage->loadMultiple($ids) as $node) {
      if (!$node->hasField('field_pet_policy') || !$node->get('field_pet_policy')->isEmpty()) {
        continue;
      }
      $features = array_column($node->get('field_solo_features')->getValue(), 'value');
      $node->set('field_solo_features', array_values(array_unique(array_merge($features, ['dog_friendly']))));
      $node->set('field_pet_policy', $item['pet_policy']);
      $node->save();
    }
  }

  /**
   * Imports solo activities, when the activities module is on.
   */
  public function importActivities(): int {
    if (!$this->entityTypeManager->getStorage('node_type')->load('activity')) {
      return 0;
    }
    $created = 0;
    foreach (Yaml::decode(file_get_contents($this->dataPath('activities.yml'))) as $item) {
      if ($this->nodeExists('activity', $item['title'])) {
        continue;
      }
      $this->entityTypeManager->getStorage('node')->create([
        'type' => 'activity',
        'title' => $item['title'],
        'status' => 1,
        'uid' => 1,
        'body' => ['value' => $this->markdown($item['description']), 'summary' => $item['summary'] ?? '', 'format' => 'basic_html'],
        'field_provider' => $item['provider'],
        'field_activity_type' => $this->termId('activity_type', $item['type']),
        'field_county' => $this->termId('county', $item['county']),
        'field_town' => $item['town'] ?? NULL,
        'field_duration' => $item['duration'] ?? NULL,
        'field_solo_tip' => $item['tip'] ?? NULL,
        'field_solo_price' => $item['price'] ?? NULL,
        'field_price_basis' => $item['price_basis'] ?? NULL,
        'field_offer_link' => ['uri' => $item['url']],
        'field_latitude' => $item['lat'] ?? NULL,
        'field_longitude' => $item['lng'] ?? NULL,
        'field_last_checked' => $item['last_checked'] ?? NULL,
        'field_source_note' => $item['source'] ?? NULL,
        'path' => ['alias' => '/activities/' . $this->slug($item['county']) . '/' . $this->slug($item['title'])],
      ])->save();
      $created++;
    }
    return $created;
  }

  /**
   * Imports festivals and events, when the events content type exists.
   */
  public function importEvents(): int {
    if (!$this->entityTypeManager->getStorage('node_type')->load('event')) {
      return 0;
    }
    $created = 0;
    foreach (Yaml::decode(file_get_contents($this->dataPath('events.yml'))) as $item) {
      if ($this->nodeExists('event', $item['title'])) {
        continue;
      }
      $this->entityTypeManager->getStorage('node')->create([
        'type' => 'event',
        'title' => $item['title'],
        'status' => 1,
        'uid' => 1,
        'body' => ['value' => $this->markdown($item['description']), 'summary' => $item['summary'] ?? '', 'format' => 'basic_html'],
        'field_event_month' => $item['month'],
        'field_event_when' => $item['when'],
        'field_county' => $this->termId('county', $item['county']),
        'field_town' => $item['town'] ?? NULL,
        'field_solo_tip' => $item['tip'] ?? NULL,
        'field_offer_link' => ['uri' => $item['url']],
        'field_latitude' => $item['lat'] ?? NULL,
        'field_longitude' => $item['lng'] ?? NULL,
        'field_last_checked' => '2026-10-30',
        'path' => ['alias' => '/events/' . $this->slug($item['title'])],
      ])->save();
      $created++;
    }
    return $created;
  }

  /**
   * Imports self-guided routes, when the routes module is on.
   */
  public function importRoutes(): int {
    if (!$this->entityTypeManager->getStorage('node_type')->load('route')) {
      return 0;
    }
    $created = 0;
    foreach (Yaml::decode(file_get_contents($this->dataPath('routes.yml'))) as $item) {
      if ($this->nodeExists('route', $item['title'])) {
        continue;
      }
      $counties = array_values(array_filter(array_map(fn($c) => $this->termId('county', $c), $item['counties'] ?? [])));
      $this->entityTypeManager->getStorage('node')->create([
        'type' => 'route',
        'title' => $item['title'],
        'status' => 1,
        'uid' => 1,
        'body' => ['value' => $this->markdown($item['body']), 'format' => 'basic_html'],
        'field_standfirst' => $item['standfirst'],
        'field_counties' => $counties,
        'field_route_mode' => $item['mode'],
        'field_days' => $item['days'],
        'field_distance_km' => $item['distance'] ?? NULL,
        'field_difficulty' => $item['difficulty'] ?? NULL,
        'field_route_stops' => trim($item['stops']),
        'path' => ['alias' => '/routes/' . $this->slug($item['title'])],
      ])->save();
      $created++;
    }
    return $created;
  }

  /**
   * Imports guides from Markdown files with a YAML header.
   */
  public function importArticles(): int {
    $created = 0;
    $files = glob($this->dataPath('articles') . '/*.md');
    sort($files);
    // Interleave topics so the latest guides are a mix, and space the publish
    // dates two days apart.
    $by_topic = [];
    foreach ($files as $file) {
      $parsed = $this->frontMatter(file_get_contents($file));
      $by_topic[$parsed[0]['topic']][] = $parsed;
    }
    $ordered = [];
    while ($by_topic) {
      foreach (array_keys($by_topic) as $topic) {
        $ordered[] = array_shift($by_topic[$topic]);
        if (!$by_topic[$topic]) {
          unset($by_topic[$topic]);
        }
      }
    }
    $time = strtotime('2026-10-28 09:00');
    foreach ($ordered as [$meta, $body]) {
      if ($this->nodeExists('article', $meta['title'])) {
        continue;
      }
      $counties = array_values(array_filter(array_map(fn($c) => $this->termId('county', $c), $meta['counties'] ?? [])));
      $this->entityTypeManager->getStorage('node')->create([
        'type' => 'article',
        'title' => $meta['title'],
        'status' => 1,
        'uid' => 1,
        'created' => $time,
        'field_standfirst' => $meta['standfirst'] ?? '',
        'field_topic' => $this->termId('topic', $meta['topic']),
        'field_counties' => $counties,
        'body' => ['value' => $this->markdown($body), 'format' => 'basic_html'],
        'path' => ['alias' => '/guides/' . $this->slug($meta['title'])],
      ])->save();
      $time -= 86400 * 2;
      $created++;
    }
    return $created;
  }

  /**
   * Imports basic pages and adds them to the menus.
   */
  public function importPages(): int {
    $created = 0;
    foreach (glob($this->dataPath('pages') . '/*.md') as $file) {
      [$meta, $body] = $this->frontMatter(file_get_contents($file));
      if ($this->nodeExists('page', $meta['title'])) {
        continue;
      }
      $node = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'page',
        'title' => $meta['title'],
        'status' => 1,
        'uid' => 1,
        'body' => ['value' => $this->markdown($body), 'format' => 'full_html'],
        'path' => ['alias' => $meta['path']],
      ]);
      $node->save();
      if (!empty($meta['menu'])) {
        $this->entityTypeManager->getStorage('menu_link_content')->create([
          'title' => $meta['menu_title'] ?? $meta['title'],
          'link' => ['uri' => 'entity:node/' . $node->id()],
          'menu_name' => $meta['menu'],
          'weight' => $meta['menu_weight'] ?? 0,
        ])->save();
      }
      $created++;
    }
    return $created;
  }

  /**
   * Creates the "List your solo offer" contact form.
   */
  private function createContactForm(): void {
    $storage = $this->entityTypeManager->getStorage('contact_form');
    if ($storage->load('list_your_offer')) {
      return;
    }
    $site_mail = \Drupal::config('system.site')->get('mail') ?: 'hello@goingsolo.ie';
    $storage->create([
      'id' => 'list_your_offer',
      'label' => 'List your solo offer',
      'recipients' => [$site_mail],
      'message' => 'Thanks. We will check the offer and add it to goingsolo.ie, usually within a few days.',
      'redirect' => '/',
      'weight' => 0,
    ])->save();
    $this->entityTypeManager->getStorage('path_alias')->create([
      'path' => '/contact/list_your_offer',
      'alias' => '/list-your-offer',
    ])->save();
  }

  /**
   * Returns a term ID, loading it if needed.
   */
  private function termId(string $vid, ?string $name): ?int {
    if ($name === NULL || $name === '') {
      return NULL;
    }
    if (!isset($this->terms[$vid][$name])) {
      $ids = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
        ->accessCheck(FALSE)
        ->condition('vid', $vid)
        ->condition('name', $name)
        ->range(0, 1)
        ->execute();
      if (!$ids) {
        return NULL;
      }
      $this->terms[$vid][$name] = (int) reset($ids);
    }
    return $this->terms[$vid][$name];
  }

  /**
   * Checks whether a node with this title already exists.
   */
  private function nodeExists(string $type, string $title): bool {
    return (bool) $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $type)
      ->condition('title', $title)
      ->range(0, 1)
      ->count()
      ->execute();
  }

  /**
   * Splits a YAML header from a Markdown body.
   */
  private function frontMatter(string $text): array {
    if (!preg_match('/^---\R(.*?)\R---\R(.*)$/s', $text, $m)) {
      throw new \RuntimeException('Missing front matter.');
    }
    return [Yaml::decode($m[1]), trim($m[2])];
  }

  /**
   * Converts the small Markdown subset used in the data files to HTML.
   */
  public function markdown(string $text): string {
    $blocks = preg_split('/\R{2,}/', trim($text));
    $html = [];
    foreach ($blocks as $block) {
      $lines = preg_split('/\R/', trim($block));
      if (preg_match('/^(#{2,4})\s+(.*)$/', $lines[0], $m) && count($lines) === 1) {
        $level = strlen($m[1]);
        $html[] = "<h$level>" . $this->inline($m[2]) . "</h$level>";
      }
      elseif (preg_match('/^[-*]\s+/', $lines[0])) {
        $items = array_map(fn($l) => '<li>' . $this->inline(preg_replace('/^[-*]\s+/', '', $l)) . '</li>', $lines);
        $html[] = '<ul>' . implode('', $items) . '</ul>';
      }
      elseif (preg_match('/^\d+\.\s+/', $lines[0])) {
        $items = array_map(fn($l) => '<li>' . $this->inline(preg_replace('/^\d+\.\s+/', '', $l)) . '</li>', $lines);
        $html[] = '<ol>' . implode('', $items) . '</ol>';
      }
      elseif (str_starts_with($lines[0], '> ')) {
        $html[] = '<blockquote><p>' . $this->inline(implode(' ', array_map(fn($l) => ltrim(substr($l, 1)), $lines))) . '</p></blockquote>';
      }
      elseif (str_starts_with($lines[0], '<')) {
        $html[] = implode("\n", $lines);
      }
      else {
        $html[] = '<p>' . $this->inline(implode(' ', $lines)) . '</p>';
      }
    }
    return implode("\n", $html);
  }

  /**
   * Inline Markdown: links, bold and italics.
   */
  private function inline(string $text): string {
    $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
    $text = preg_replace('/\[([^\]]+)\]\(([^)\s]+)\)/', '<a href="$2">$1</a>', $text);
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/', '<em>$1</em>', $text);
    return $text;
  }

  /**
   * Makes a URL-safe slug.
   */
  private function slug(string $text): string {
    $text = \Drupal::service('transliteration')->transliterate($text, 'en');
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
  }

  /**
   * Path inside the data folder.
   */
  private function dataPath(string $name): string {
    return DRUPAL_ROOT . '/' . $this->moduleList->getPath('goingsolo_content') . '/data/' . $name;
  }

}
