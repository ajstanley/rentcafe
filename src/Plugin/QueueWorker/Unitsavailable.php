<?php

declare(strict_types=1);

namespace Drupal\killam_rentcafe\Plugin\QueueWorker;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\StateInterface;
use Drupal\file\Entity\File;
use Drupal\killam_rentcafe\Yardi;
use Drupal\node\Entity\Node;
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
   * Yardi service instance.
   *
   * @var \Drupal\killam_rentcafe\Yardi
   */
  protected $killamRentcafeYardi;

  /**
   * State variable to hold token.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

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
    Yardi $killamRentcafeYardi,
    FileSystemInterface $fileSystem,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->state = $state;
    $this->killamRentcafeYardi = $killamRentcafeYardi;
    $this->fileSystem = $fileSystem;
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
      $container->get('killam_rentcafe.yardi'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($property): void {
    if ($property->get('status')->getValue()[0]['value'] != 1) {
      return;
    }
    $field_value = $property->get('field_rentcafe_property_code')->getValue();
    $property_code = !empty($field_value) ? $field_value[0]['value'] : NULL;
    if (!$property_code) {
      $this->loggerChannelRentCafe->notice("Property {$property->label()} does not have a property code");
      return;
    }
    // Delete all existing unit nodes related to this Property.
    $property_nid = $property->id();
    $unit_nids = $this->entityTypeManager
      ->getStorage("node")
      ->getQuery()
      ->condition("type", "unit")
      ->condition("field_property", $property_nid)
      ->accessCheck(TRUE)
      ->execute();
    $units = $this->killamRentcafeYardi->getAvailability($property_code);
    $floorplans = $this->killamRentcafeYardi->getFloorplan($property_code);

    foreach ($units as $unit) {
      if (empty($unit['apartmentId']) || empty($unit['minimumRent'])) {
        continue;
      }

      // Get Unit node.  Yardi object is $unit, Drupal Object is $node.
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'unit')
        ->accessCheck(TRUE)
        ->condition('field_rentcafe_apartment_id', $unit['apartmentId']);
      $results = $query->execute();
      if (count($results) === 1) {
        $nid = reset($results);
        $key = array_search($nid, $unit_nids);
        if ($key !== FALSE) {
          unset($unit_nids[$key]);
        }
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
        $current_available_date = NULL;
        $field_value = $node->get('field_available_date')->getValue();
        if (!empty($field_value) && isset($field_value[0]['value'])) {
          $current_available_date = $field_value[0]['value'];
        }
        if ($current_available_date != date('Y-m-d', strtotime($unit['availableDate']))) {
          $node->set('field_available_date_changed', date('Y-m-d g:i:s', time()));
        }
        $node->set('status', 1);
        $node->set('field_available_date', date('Y-m-d', strtotime($unit['availableDate'])));
        $node->set('field_property', $property->id());
        $node->set('field_bedrooms', $unit['beds']);
        $node->set('field_number_of_bedrooms', $unit['beds']);
        $node->set('field_number_of_bathrooms', $unit['baths']);
        $node->set('field_unit_price', $unit['minimumRent']);
        $node->set('field_square_footage', $unit['sqft']);
        $node->set('field_apply_url', $unit['applyOnlineURL']);
        $node->set('field_rentcafe_apartment_id', $unit['apartmentId']);
        $node->set('field_unit_status', $unit['unitStatus']);
        $node->save();
      }
      else {
        $node = Node::create([
          'type' => 'unit',
          'title' => $property->title->value . ' - ' . $unit['floorplanName'] . ' - ' . $unit['propertyId'],
          'status' => 1,
          'promote' => 0,
          'sticky' => 0,
          'comment' => 1,
          'uid' => 1,
          'field_square_footage' => $unit['sqft'],
          'field_property' => $property->id(),
          'field_bedrooms' => $unit['beds'],
          'field_number_of_bedrooms' => $unit['beds'],
          'field_number_of_bathrooms' => $unit['baths'],
          'field_unit_price' => $unit['minimumRent'],
          'field_apply_url' => $unit['applyOnlineURL'],
          'field_unit_status' => $unit['unitStatus'],
          'language' => 'en',
          'field_rentcafe_apartment_id' => $unit['apartmentId'],
        ]);
        $node->set('field_available_date_changed', date('Y-m-d g:i:s', time()));
      }

      if (!empty($unit['floorplanId']) && !empty($floorplans[$unit['floorplanId']]) && !empty($floorplans[$unit['floorplanId']]['floorplanImageURLArray'])) {
        $floorplan_url = $floorplans[$unit['floorplanId']]['floorplanImageURLArray'][0];
        $destination = "public://rentcafefloorplans";
        $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY);
        $context = stream_context_create([
          "http" => [
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
          ],
        ]);
        $file_content = file_get_contents($floorplan_url, FALSE, $context);
        $file_name = basename($floorplan_url);
        $target_uri = $destination . $file_name;
        $file_uri = $this->fileSystem->saveData($file_content, $target_uri, FileSystemInterface::EXISTS_REPLACE);
        $floorplan_file = File::create(['uri' => $file_uri]);
        $floorplan_file->save();
        if ($floorplan_file) {
          $node->set('field_unit_floor_plan', $floorplan_file->id()
          );
        }
      }
      if (!empty($unit['availableDate'])) {
        $node->set(
          'field_available_date', date('Y-m-d', strtotime($unit['availableDate']))
        );
      }
      try {
        $node->save();
      }
      catch (\Exception $e) {
        $this->loggerChannelRentCafe->notice("Exception on node save:" . $e->getMessage());
      }
    }
    if (!empty($unit_nids)) {
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($unit_nids);
      foreach ($nodes as $node) {
        $node->delete();
      }
    }
  }

}
