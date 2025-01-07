<?php

declare(strict_types=1);

namespace Drupal\killam_rentcafe\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Killam Rentcafe Integration settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'killam_rentcafe_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['killam_rentcafe.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('killam_rentcafe.settings');

    $fields = [
      'basic_endpoint' => 'Basic Endpoint',
      'token_endpoint' => 'Token Endpoint',
      'api_token' => 'API Token',
      'company_code' => 'Company Code',
      'client_id' => 'Client ID',
      'username' => 'Username',
      'password' => 'Password',
    ];

    foreach ($fields as $key => $label) {
      $form[$key] = [
        '#type' => 'textfield',
        '#title' => $this->t($label),
        '#default_value' => $config->get($key),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('killam_rentcafe.settings');
    $fields = [
      'basic_endpoint',
      'token_endpoint',
      'api_token',
      'company_code',
      'client_id',
      'username',
      'password',
    ];

    foreach ($fields as $key) {
      $config->set($key, $form_state->getValue($key));
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
