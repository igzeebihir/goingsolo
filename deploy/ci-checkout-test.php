<?php

/**
 * @file
 * CI check: buy a Solo Club membership and a featured listing with the test
 * gateway, then confirm the role and the featured flag were applied.
 *
 * Run with: drush php:script deploy/ci-checkout-test.php
 */

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentMethod;
use Drupal\user\Entity\User;

$etm = \Drupal::entityTypeManager();
$fail = function (string $message) {
  fwrite(STDERR, "FAIL: $message\n");
  exit(1);
};

$user = User::create(['name' => 'ci-member', 'mail' => 'ci@example.com', 'status' => 1]);
$user->save();

$store = $etm->getStorage('commerce_store')->loadDefault() ?: $fail('No default store.');
$gateway = $etm->getStorage('commerce_payment_gateway')->load('test_payments') ?: $fail('No test gateway.');

$payment_method = PaymentMethod::create([
  'type' => 'credit_card',
  'payment_gateway' => 'test_payments',
  'uid' => $user->id(),
  'remote_id' => 'ci-card',
  'card_type' => 'visa',
  'card_number' => '1111',
  'card_exp_month' => 12,
  'card_exp_year' => (int) date('Y') + 2,
  'reusable' => TRUE,
  'expires' => strtotime('+2 years'),
]);
$payment_method->save();

$variations = $etm->getStorage('commerce_product_variation');
$monthly = $variations->loadBySku('SOLO-CLUB-MONTHLY') ?: $fail('No monthly membership.');
$featured = $variations->loadBySku('FEATURED-30') ?: $fail('No featured listing product.');

$listing_ids = $etm->getStorage('node')->getQuery()->accessCheck(FALSE)->condition('type', 'listing')->condition('status', 1)->range(0, 1)->execute();
$listing_id = reset($listing_ids) ?: $fail('No listing to feature.');

$items = [
  OrderItem::create(['type' => 'default', 'purchased_entity' => $monthly, 'quantity' => 1, 'unit_price' => $monthly->getPrice()]),
  OrderItem::create(['type' => 'featured_listing', 'purchased_entity' => $featured, 'quantity' => 1, 'unit_price' => $featured->getPrice(), 'field_listing' => $listing_id]),
];
foreach ($items as $item) {
  $item->save();
}
$order = Order::create([
  'type' => 'default',
  'store_id' => $store->id(),
  'uid' => $user->id(),
  'mail' => $user->getEmail(),
  'order_items' => $items,
  'state' => 'draft',
  'payment_gateway' => 'test_payments',
  'payment_method' => $payment_method->id(),
]);
$order->save();
$order->getState()->applyTransitionById('place');
$order->save();

$payment = Payment::create([
  'state' => 'new',
  'amount' => $order->getBalance(),
  'payment_gateway' => 'test_payments',
  'payment_method' => $payment_method->id(),
  'order_id' => $order->id(),
]);
$gateway->getPlugin()->createPayment($payment, TRUE);
// The order's paid total is normally updated at the end of the request.
\Drupal::service('commerce_payment.order_updater')->updateOrders();

$order = $etm->getStorage('commerce_order')->loadUnchanged($order->id());
echo 'Order ' . $order->id() . ' state ' . $order->getState()->getId() . ', paid: ' . ($order->isPaid() ? 'yes' : 'no') . "\n";

$subscriptions = $etm->getStorage('commerce_subscription')->loadByProperties(['uid' => $user->id()]);
echo 'Subscriptions: ' . count($subscriptions) . "\n";
foreach ($subscriptions as $subscription) {
  echo ' - ' . $subscription->label() . ' ' . $subscription->getState()->getId() . "\n";
}
$subscriptions ?: $fail('No subscription was created.');

$user = $etm->getStorage('user')->loadUnchanged($user->id());
$user->hasRole('solo_club_member') ?: $fail('Member role not granted.');
echo "Member role granted.\n";

$listing = $etm->getStorage('node')->loadUnchanged($listing_id);
$listing->get('field_featured')->value ?: $fail('Listing not featured.');
echo 'Listing featured until ' . $listing->get('field_featured_until')->value . "\n";

// Cancel and check the role is removed.
foreach ($subscriptions as $subscription) {
  $subscription->getState()->applyTransitionById('cancel');
  $subscription->save();
}
$user = $etm->getStorage('user')->loadUnchanged($user->id());
$user->hasRole('solo_club_member') && $fail('Member role kept after cancelling.');
echo "Role removed after cancelling.\nAll commerce checks passed.\n";
