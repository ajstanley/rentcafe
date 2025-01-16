<?php

declare(strict_types=1);

namespace Drupal\killam_rentcafe;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

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
   * Logger object.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a Yardi object.
   */
  public function __construct(
    private readonly Client $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StateInterface $state,
    LoggerInterface $logger,
  ) {
    $this->config = $this->configFactory->get('killam_rentcafe.settings');
    $this->logger = $logger;
  }

  /**
   * Gets API token, either from State, or mints a fresh one.
   *
   * @return string
   *   Returns Yardi API Token.
   */
  public function getApiToken() {
    $expires_at = $this->state->get('yardi_token_expires_at');
    $current_time = time();
    // Check if token is expired.
    if ($expires_at && $current_time < $expires_at) {
      $token = $this->state->get('yardi_token');
      if ($token) {
        return $token;
      }
    }

    try {
      $client = $this->httpClient;
      $url = $this->config->get('token_endpoint');
      $headers = [
        'Content-Type' => 'application/x-www-form-urlencoded',
      ];
      $form_params = [
        'username' => $this->config->get('username'),
        'password' => $this->config->get('password'),
        'client_id' => $this->config->get('client_id'),
        'grant_type' => 'password',
      ];
      $response = $client->post($url, [
        'headers' => $headers,
        'form_params' => $form_params,
      ]);
      $token = (string) $response->getBody();
      $tokenData = json_decode($token, TRUE);
    }
    catch (RequestException $e) {
      echo 'Error: ' . $e->getMessage();
    }
    $this->storeApiToken($tokenData['access_token']);
    return $this->state->get('api_token');
  }

  /**
   * Stores Yardi auth token.
   *
   * @return void
   *   returns nothing.
   */
  public function storeApiToken($api_token) {
    $expires_at = time() + (8 * 60 * 60);
    $this->state->set('yardi_token', $api_token);
    $this->state->set('yardi_token_expires_at', $expires_at);
  }

  /**
   * Retrieves any Yardi Data.
   */
  public function getYardiData($url, $property_code) {
    $api_token = $this->getApiToken();
    try {
      $url = $this->config->get('basic_endpoint') . $url;
      $options = [
        'headers' => [
          'accept' => 'text/plain',
          'vendor' => 'dev@trampolinebranding.com',
          'Authorization' => "Bearer $api_token",
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'apiToken' => $this->config->get('api_token'),
          'companyCode' => $this->config->get('company_code'),
          'propertyCode' => $property_code,
        ],
      ];
      $response = $this->httpClient->post($url, $options);
      $data = $response->getBody()->getContents();
      return json_decode($data, TRUE);
    }
    catch (RequestException $e) {
      $this->logger->error('Error: @error', ['@error' => $e->getMessage()]);
    }
    return [];
  }

  /**
   * Returns Availaabilty info from Yardi.
   *
   * @param string $property_code
   *   Yardi Property Code.
   *
   * @return array
   *   Availabililty data from Yardi.
   */
  public function getAvailability($property_code) {
    $availablilty_url = '/apartmentavailability/getapartmentavailability';
    $unit_data = $this->getYardiData($availablilty_url, $property_code);
    if (!empty($unit_data['apartmentAvailabilities'])) {
      return $unit_data['apartmentAvailabilities'];
    }
    return [];
  }

  /**
   * Returns Property info from Yardi.
   *
   * @param string $property_code
   *   Yardi Property Code.
   *
   * @return array
   *   Property data from Yardi.
   */
  public function getPropertyInformation($property_code) {
    $details_url = '/property/getmarketingdetails';
    return $this->getYardiData($details_url, $property_code);
  }

  /**
   * Returns Floorplan  from Yardi.
   *
   * @param string $property_code
   *   Yardi Property Code.
   *
   * @return array
   *   Floorplqn data from Yardi.
   */
  public function getFloorplan($property_code) {
    $floorplans = [];
    $availablilty_url = '/floorplan/getfloorplans';
    $floor_data = $this->getYardiData($availablilty_url, $property_code);
    foreach ($floor_data['floorplans'] as $floorplan) {
      $floorplan_id = $floorplan['floorplanId'];
      $floorplans[$floorplan_id] = $floorplan;
    }
    return $floorplans;
  }

}
