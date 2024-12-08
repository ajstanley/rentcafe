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
    $form['basic_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Basic Endpoint'),
      '#default_value' => $this->config('killam_rentcafe.settings')
        ->get('basic_endpoint'),
    ];
    $form['token_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token Endpoint'),
      '#default_value' => $this->config('killam_rentcafe.settings')
        ->get('token_endpoint'),
    ];
    $form['api_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Token'),
      '#default_value' => $this->config('killam_rentcafe.settings')
        ->get('api_token'),
    ];
    $form['company_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company Code'),
      '#default_value' => $this->config('killam_rentcafe.settings')
        ->get('company_code'),
    ];
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $this->config('killam_rentcafe.settings')
        ->get('client_id'),
    ];
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $this->config('killam_rentcafe.settings')
        ->get('username'),
    ];
    $form['password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $this->config('killam_rentcafe.settings')
        ->get('password'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('killam_rentcafe.settings')
      ->set('basic_endpoint', $form_state->getValue('basic_endpoint'))
      ->set('token_endpoint', $form_state->getValue('token_endpoint'))
      ->set('company_code', $form_state->getValue('company_code'))
      ->set('api_token', $form_state->getValue('api_token'))
      ->set('username', $form_state->getValue('username'))
      ->set('client_id', $form_state->getValue('client_id'))
      ->set('password', $form_state->getValue('password'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
