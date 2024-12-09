<?php

declare(strict_types=1);

namespace Drupal\killam_rentcafe\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'killam_rentcafe_unitsavailable' queue worker.
 *
 * @QueueWorker(
 *   id = "killam_rentcafe_unitsavailable",
 *   title = @Translation("UnitsAvailable"),
 *   cron = {"time" = 60},
 * )
 */
final class Unitsavailable extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Guzzle\Client instance.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * State variable to hold token.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new Unitsavailable instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly LoggerChannelInterface $loggerChannelRentCafe,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    ClientInterface $http_client,
    StateInterface $state,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.rent_cafe'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('state'),
    );
  }



  /**
   *
   */


  /**
   * {@inheritdoc}
   */
  public function processItem($candidate): void {
    $api_calls = [];
    $config = $this->configFactory->get('killam_rentcafe.settings');
    $company_code = $config->get('company_code');
    $api_token = $config->get('api_token');
    $base_url = $config->get('basic_endpoint');
    $node_storage = $this->entityTypeManager->getStorage('node');
    $property = $node_storage->load($candidate->id());
    if (!$this->_validate($property)) {
      return;
    }
    $property_code = $property->get('field_building_code')
      ->getValue()[0]['value'];
    $responses = [];
    $form_params = [
      'apiToken' => $api_token,
      'companyCode' => $company_code,
      'propertyCode' => $property_code,
    ];
    $api_calls['availability'] = ['/apartmentavailability/getapartmentavailability'];
    $api_calls['floorplan'] = ['/floorplan/getfloorplans'];
    $api_calls['property_data'] = ['/property/getpropertydetails'];
    foreach ($api_calls as $call_type => $call) {
      $property_url = Url::fromUri("{$base_url}{$call}");
      $responses[$call_type] = $this->httpClient->request('post', $property_url->toString(), [
        'form_params' => $form_params,
      ]);
    }
  }

  /**
   * Validates input.
   *
   * @param \Drupal\node\Entity\Node $property
   *   Property evaluated.
   *
   * @return bool
   *   Pass or fail.
   */
  private function _validate($property) {
    $retval = TRUE;
    if (empty($property)) {
      $this->loggerChannelRentCafe->notice("{$property} not found");
      $retval = FALSE;
    }
    if ($property->get('status')->getValue()[0]['value'] != 1) {
      $this->loggerChannelRentCafe->notice("UAU: property is not published: {$property->nid} {$property->title}");
      $retval = FALSE;
    }
    if (empty($property->get('field_building_code')->getValue()[0]['value'])) {
      $this->loggerChannelRentCafe->notice("UAU: property has no building code: {$property->nid} {$property->title}");
      $retval = FALSE;
    }
    return $retval;
  }

  /**
   *
   */
  private function _get_auth_key() {
    $config = $this->configFactory->get('killam_rentcafe.settings');
    $token_endpoint = $config->get('token_endpoint');
    $form_params = [
      'grant_type' => "password",
      'client_id' => $config->get('client_id'),
      'username' => $config->get('username'),
      'password' => $config->get('password'),
    ];
    $response = $this->httpClient->request('post', $token_endpoint, [
      'form_params' => $form_params,
      'headers' => ['Accept' => 'application/json'],
    ]);
  }

}
