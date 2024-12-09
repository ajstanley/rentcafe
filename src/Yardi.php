<?php

declare(strict_types=1);

namespace Drupal\killam_rentcafe;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Psr\Http\Client\ClientInterface;

/**
 * Class Yardi.
 *
 * Provides provides access to Yardi API.
 */
final class Yardi {

  /**
   * The loaded configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs a Yardi object.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StateInterface $state,
  ) {
    $this->config = $this->configFactory->get('killam_rentcafe.settings');
  }

  public function getApiToken() {
    $expires_at = $this->state->get('yardi_token_expires_at');
    $current_time = time();
    // Check if token is expired.
    if ($expires_at && $current_time < $expires_at) {
      return $this->state->get('yardi_token');
    }
    else {
      $client = $this->httpClient;
      $url = $this->config->get('token_endpoint');
      $body = [
        'grant_type' => 'password',
        'client_id' => $this->config->get('company_code'),
        'username' => $this->config->get('username'),
        'password' => $this->config->get('password'),
      ];
      $response = $client->post($url, [
        'form_params' => $body,
        'headers' => [
          'Accept' => 'application/json',
        ],
      ]);
      $stop = 'here';
      $this->storeApiToken($response);
      return $response;

      $this->store_api_token();
      return $this->state->get('api_token');
    }
  }

  /**
   * Stores Yardi auth token.
   *
   * @return void
   */
  public function storeApiToken() {
    $api_token = '1234-5678';
    $expires_at = time() + (8 * 60 * 60);
    $this->state->set('yardi_token', $api_token);
    $this->state->set('yardi_token_expires_at', $expires_at);
  }

}
