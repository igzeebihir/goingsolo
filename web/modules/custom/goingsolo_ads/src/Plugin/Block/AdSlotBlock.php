<?php

declare(strict_types=1);

namespace Drupal\goingsolo_ads\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\goingsolo_ads\Form\AdsSettingsForm;

/**
 * One AdSense ad unit.
 */
#[Block(
  id: 'goingsolo_ad_slot',
  admin_label: new TranslatableMarkup('Ad slot'),
  category: new TranslatableMarkup('Going Solo'),
)]
final class AdSlotBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['slot' => 'in_content', 'label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['slot'] = [
      '#type' => 'select',
      '#title' => $this->t('Slot'),
      '#options' => AdsSettingsForm::slotLabels(),
      '#default_value' => $this->configuration['slot'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['slot'] = $form_state->getValue('slot');
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    $config = \Drupal::config('goingsolo_ads.settings');
    $slot_id = (string) $config->get('slots.' . $this->configuration['slot']);
    return AccessResult::allowedIf($config->get('enabled') && $config->get('publisher_id') && $slot_id !== '' && !$account->hasPermission('browse without ads'))
      ->addCacheableDependency($config)
      ->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = \Drupal::config('goingsolo_ads.settings');
    return [
      '#type' => 'html_tag',
      '#tag' => 'ins',
      '#attributes' => [
        'class' => ['adsbygoogle', 'gs-ad', 'gs-ad--' . $this->configuration['slot']],
        'style' => 'display:block',
        'data-ad-client' => $config->get('publisher_id'),
        'data-ad-slot' => $config->get('slots.' . $this->configuration['slot']),
        'data-ad-format' => 'auto',
        'data-full-width-responsive' => 'true',
      ],
      '#attached' => ['library' => ['goingsolo_ads/slot']],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['config:goingsolo_ads.settings']);
  }

}
