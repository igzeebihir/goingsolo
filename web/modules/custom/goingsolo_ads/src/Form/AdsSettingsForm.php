<?php

declare(strict_types=1);

namespace Drupal\goingsolo_ads\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings for Google AdSense.
 */
final class AdsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'goingsolo_ads_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['goingsolo_ads.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('goingsolo_ads.settings');
    $form['help'] = [
      '#markup' => '<p>' . $this->t('Sign up at <a href=":url" target="_blank">Google AdSense</a>, add this site, then paste your publisher ID here. Ads are never shown to Solo Club members or site editors. In AdSense, turn on Auto ads for the most earnings with the least setup, and turn on the Privacy &amp; messaging consent message for visitors in the EEA and UK.', [':url' => 'https://adsense.google.com']) . '</p>',
    ];
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show ads'),
      '#default_value' => $config->get('enabled'),
    ];
    $form['publisher_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AdSense publisher ID'),
      '#description' => $this->t('Looks like ca-pub-1234567890123456.'),
      '#default_value' => $config->get('publisher_id'),
      '#placeholder' => 'ca-pub-0000000000000000',
    ];
    $form['slots'] = [
      '#type' => 'details',
      '#title' => $this->t('Fixed ad units'),
      '#description' => $this->t('Create a display ad unit in AdSense for each slot and paste its data-ad-slot number. Empty slots show nothing. Place the "Ad slot" blocks in the block layout to choose where they appear.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    foreach (self::slotLabels() as $key => $label) {
      $form['slots'][$key] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#default_value' => $config->get("slots.$key"),
        '#size' => 20,
      ];
    }
    $form['ads_txt_extra'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Extra ads.txt lines'),
      '#description' => $this->t('The AdSense line is added automatically from the publisher ID. Add lines here for other ad networks.'),
      '#default_value' => $config->get('ads_txt_extra'),
      '#rows' => 3,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $id = trim((string) $form_state->getValue('publisher_id'));
    if ($id !== '' && !preg_match('/^ca-pub-\d{10,20}$/', $id)) {
      $form_state->setErrorByName('publisher_id', $this->t('The publisher ID should look like ca-pub-1234567890123456.'));
    }
    if ($form_state->getValue('enabled') && $id === '') {
      $form_state->setErrorByName('publisher_id', $this->t('Add a publisher ID before turning ads on.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $slots = array_map('trim', (array) $form_state->getValue('slots'));
    $this->config('goingsolo_ads.settings')
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('publisher_id', trim((string) $form_state->getValue('publisher_id')))
      ->set('slots', $slots)
      ->set('ads_txt_extra', (string) $form_state->getValue('ads_txt_extra'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Slot machine names and labels.
   */
  public static function slotLabels(): array {
    return [
      'header' => t('Header (leaderboard)'),
      'in_content' => t('In content'),
      'sidebar' => t('Sidebar'),
      'footer' => t('Footer'),
    ];
  }

}
