<?php

declare(strict_types=1);

namespace Drupal\goingsolo_commerce\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Features a listing once its featured listing order is paid.
 */
final class OrderPaidSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return ['commerce_order.order.paid' => 'onPaid'];
  }

  /**
   * Sets the featured flag and end date on each listing in the order.
   */
  public function onPaid(OrderEvent $event): void {
    foreach ($event->getOrder()->getItems() as $item) {
      if ($item->bundle() !== 'featured_listing' || !$item->hasField('field_listing')) {
        continue;
      }
      $listing = $item->get('field_listing')->entity;
      $variation = $item->getPurchasedEntity();
      if (!$listing || !$variation) {
        continue;
      }
      $days = (int) ($variation->get('field_feature_days')->value ?: 30) * (int) $item->getQuantity();
      $start = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Dublin'));
      $current = $listing->get('field_featured_until')->value;
      if ($listing->get('field_featured')->value && $current && $current >= $start->format('Y-m-d')) {
        $start = new \DateTimeImmutable($current, new \DateTimeZone('Europe/Dublin'));
      }
      $listing->set('field_featured', 1);
      $listing->set('field_featured_until', $start->modify("+$days days")->format('Y-m-d'));
      $listing->setNewRevision(TRUE);
      $listing->setRevisionLogMessage('Featured by order ' . $event->getOrder()->getOrderNumber());
      $listing->save();
      $this->loggerFactory->get('goingsolo_commerce')->notice('Listing @title featured until @date.', [
        '@title' => $listing->label(),
        '@date' => $listing->get('field_featured_until')->value,
      ]);
    }
  }

}
