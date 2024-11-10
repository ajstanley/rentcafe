<?php

namespace Drupal\killam_rentcafe\Plugin\QueueWorker;

use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Updates a properties available units.
 *
 * @QueueWorker(
 *   id = "available_units",
 *   title = @Translation("Available Units Rentcafe refresh"),
 *   cron = {"time" = 60}
 * )
 */
class AvailableUnits extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructor.
   */
  public function __construct() {}

  /**
   *
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static();
  }

  /**
   * Processes a single item of Queue.
   */
  public function processItem($property_arg) {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    $property = $node_storage->load($property_arg->id());

    if (empty($property)) {
      \Drupal::logger('available_units_worker')->notice(
        'UAU: property does not exist : (@nid) @title',
        [
          '@nid' => $property->nid,
          '@title' => $property->title,
        ]
      );
      return;
    }

    if ($property->get('status')->getValue()[0]['value'] != 1) {
      \Drupal::logger('available_units_worker')->notice(
        'UAU: property is not published : (@nid) @title',
        [
          '@nid' => $property->nid,
          '@title' => $property->title,
        ]
      );
      return;
    }

    if (empty($property->get('field_building_code')->getValue()[0]['value'])) {
      \Drupal::logger('available_units_worker')->notice(
        'UAU: property has no building code : (@nid) @title',
        [
          '@nid' => $property->nid,
          '@title' => $property->title,
        ]
      );
      return;
    }

    $building_code = $property->get('field_building_code')
      ->getValue()[0]['value'];

    /*
     * Query should look like this.
     *
     * https://api.rentcafe.com/rentcafeapi.aspx
     *    ?requestType=apartmentavailability
     *    &companyCode=C00000047679
     *    &apiToken=15ad4b86-6853-45c2-9777-01498102d754
     *    &VoyagerPropertyCode=k2
     */
    $unit_data = [
      'requestType' => 'apartmentavailability',
      'companyCode' => 'C00000047679',
      'apiToken' => '15ad4b86-6853-45c2-9777-01498102d754',
      'VoyagerPropertyCode' => $building_code,
    ];

    $floorplan_data = [
      'requestType' => 'floorplan',
      'apiToken' => '15ad4b86-6853-45c2-9777-01498102d754',
      // 'companyCode' => 'C00000047679',
      'VoyagerPropertyCode' => $building_code,
    ];

    $property_data = [
      'requestType' => 'property',
      'type' => 'propertyData',
      'companyCode' => 'C00000047679',
      'apiToken' => '15ad4b86-6853-45c2-9777-01498102d754',
      'VoyagerPropertyCode' => $building_code,
    ];

    $property_url = Url::fromUri('https://api.rentcafe.com/rentcafeapi.aspx', ['query' => $property_data]);

    try {
      $property_response = \Drupal::httpClient()->get(
        $property_url->toString(),
        [
          'headers' => ['Accept' => 'application/json'],
        ]
      );
    }
    catch (ClientException $e) {
      \Drupal::logger('available_units_api_call_error')->notice(
        'Error getting data from yardi : (@nid) @title - @message',
        [
          '@nid' => $property->id(),
          '@title' => $property->getTitle(),
          '@message' => $e->getMessage(),
        ]
      );

      // Delete all exiting units for this building
      // if (count($units) && empty($units[0]['Error'])) {
      // still need to clear units. no availability gives an error
      // $this->deleteUnits($property->id());
      // }
      return;
    }

    $property_stuff = Json::decode($property_response->getBody());

    /*\Drupal::logger('rentcafe_property')->notice(
    '<pre>' . print_r($property_stuff, true) . '</pre>'
    );*/

    $url = Url::fromUri('https://api.rentcafe.com/rentcafeapi.aspx', ['query' => $unit_data]);

    $response = \Drupal::httpClient()->get(
      $url->toString(),
      [
        'headers' => ['Accept' => 'application/json'],
      ]
    );
    $units = Json::decode($response->getBody());


    $floorplan_url = Url::fromUri('https://api.rentcafe.com/rentcafeapi.aspx', ['query' => $floorplan_data]);
    $floorplan_response = \Drupal::httpClient()->get(
      $floorplan_url->toString(),
      [
        'headers' => ['Accept' => 'application/json'],
      ]
    );
    $floorplans_all = Json::decode($floorplan_response->getBody());

    $floorplans = [];
    foreach ($floorplans_all as $floorplan) {
      if (empty($floorplan['FloorplanImageURL'])) {
        continue;
      }
      $floorplan_imgs = explode(',', $floorplan['FloorplanImageURL']);
      $floorplans[$floorplan['FloorplanId']] = $floorplan_imgs[0];
    }

    /*\Drupal::logger('floorplans all')->notice(
    'floorplan_url: %id',
    [
    '%id' => var_export($floorplans, true)
    ]
    );*/

    // Delete all exiting units for this building.
    if (count($units)) {
      $this->deleteUnits($property->id());
    }

    $max_rent = 0;
    $min_rent = 0;
    $min_beds = 10;
    $max_beds = 0;
    $min_baths = 0;
    $max_baths = 0;
    $min_avail = 0;
    $max_avail = 0;

    foreach ($units as $unit) {
      if (empty($unit['ApartmentId'])) {
        \Drupal::logger('propqueue_worker_units')->notice(
          'Item skipped because no ApartmentId. Query: %query Result: %result',
          [
            '%query' => $url->toString(),
            '%result' => var_export($unit, TRUE),
          ]
        );
        continue;
      }

      if (empty($unit['MinimumRent'])) {
        \Drupal::logger('propqueue_worker_units')->notice(
          'ApartmentId:%id skipped because no MinimumRent. Query: %query',
          [
            '%id' => $unit['ApartmentId'],
            '%query' => $url->toString(),
          ]
        );
        continue;
      }

      $query = \Drupal::entityQuery('node')
        ->condition('type', 'unit')
        ->condition('field_rentcafe_apartment_id', $unit['ApartmentId']);
      $results = $query->execute();

      \Drupal::logger('rentcafe_unit_status')->notice(
        '<pre>' . print_r($unit['UnitStatus'], TRUE) . '</pre>'
      );

      if (!empty($results)) {
        if (sizeof($results) == 1) {
          $nid = reset($results);

          $node = Node::load($nid);

          $current_available_date = $node->get('field_available_date')
            ->getValue()[0]['value'];

          if ($current_available_date != date('Y-m-d', strtotime($unit['AvailableDate']))) {
            $node->set('field_available_date_changed', date('Y-m-d g:i:s', time()));
          }

          $node->set('status', 1);
          $node->set('field_available_date', date('Y-m-d', strtotime($unit['AvailableDate'])));
          $node->set('field_property', $property->id());
          $node->set('field_bedrooms', $unit['Beds']);
          $node->set('field_number_of_bedrooms', $unit['Beds']);
          $node->set('field_number_of_bathrooms', $unit['Baths']);
          $node->set('field_unit_price', $unit['MinimumRent']);
          $node->set('field_square_footage', $unit['SQFT']);
          $node->set('field_apply_url', $unit['ApplyOnlineURL']);
          $node->set('field_rentcafe_apartment_id', $unit['ApartmentId']);
          $node->set('field_unit_status', $unit['UnitStatus']);
          $node->save();

          $unit_rent = $unit['MinimumRent'];
          $unit_beds = (int) $unit['Beds'];
          $unit_avail_date = strtotime($unit['AvailableDate']);

          if ($unit_rent > $max_rent || $max_rent == 0) {
            $max_rent = $unit_rent;
          }
          if ($unit_rent < $min_rent || $min_rent == 0) {
            $min_rent = $unit_rent;
          }

          // Availability.
          if ($unit_avail_date > $max_avail || $max_avail == 0) {
            $max_avail = $unit_avail_date;
          }
          if ($unit_avail_date < $min_avail || $min_avail == 0) {
            $min_avail = $unit_avail_date;
          }

          // Bedrooms.
          if ($unit_beds == 0) {
            $unit_beds = 1;
          }
          if ($unit_beds > $max_beds || $max_beds == 0) {
            $max_beds = $unit_beds;
          }
          if ($unit_beds < $min_beds || $min_beds == 0) {
            $min_beds = $unit_beds;
          }
        }
        else {
          \Drupal::logger('rentcafe_unit_check too_many_results')->notice(
            '<pre>' . print_r($results, TRUE) . '</pre>'
          );
        }
      }
      else {
        $node = Node::create([
          'type' => 'unit',
          'title' => $property->title->value . ' - ' . $unit['FloorplanName'] . ' - ' . $unit['ApartmentId'],
          'status' => 1,
          'promote' => 0,
          'sticky' => 0,
          'comment' => 1,
          'uid' => 1,
          'field_quare_footage' => $unit['SQFT'],
          'field_property' => $property->id(),
          'field_bedrooms' => $unit['Beds'],
          'field_number_of_bedrooms' => $unit['Beds'],
          'field_number_of_bathrooms' => $unit['Baths'],
          'field_unit_price' => $unit['MinimumRent'],
          'field_apply_url' => $unit['ApplyOnlineURL'],
          'field_unit_status' => $unit['UnitStatus'],
          'language' => 'en',
          'field_rentcafe_apartment_id' => $unit['ApartmentId'],
        ]);
        $node->set('field_available_date_changed', date('Y-m-d g:i:s', time()));
      }

      /*\Drupal::logger('after node stuff')->notice(
      '<pre>' . print_r($property->id(), true) . '</pre>'
      );*/

      if (!empty($unit['FloorplanId']) && !empty($floorplans[$unit['FloorplanId']])) {
        $floorplan_url = $floorplans[$unit['FloorplanId']];
        $floorplan_file = system_retrieve_file($floorplan_url, "public://rentcafefloorplans", TRUE, FileSystemInterface::EXISTS_REPLACE);

        if ($floorplan_file) {
          \Drupal::logger('floorplan_file stuff')->notice(
            '<pre>' . print_r($floorplan_file->id(), TRUE) . '</pre>'
          );
          $node->set('field_unit_floor_plan', $floorplan_file->id()
          );
        }
      }

      /*\Drupal::logger('after floorplan stuff')->notice(
      '<pre>' . print_r($property->id(), true) . '</pre>'
      );*/

      if (!empty($unit['AvailableDate'])) {
        $node->set(
          'field_available_date', date('Y-m-d', strtotime($unit['AvailableDate']))
        /*[
          'value' => date('Y-m-d H:i:s', strtotime($unit['AvailableDate'])),
          'timezone' => 'UTC',
          'timezone_db' => 'UTC',
        ]*/
        );
      }
      else {
        // Skip any item without an AvailableDate but log it.
        \Drupal::logger('propqueue_worker_units')->notice(
          'ApartmentId:%id skipped because no AvailableDate value. Query: %query Result: %result',
          [
            '%id' => $unit['ApartmentId'],
            '%query' => $url->toString(),
            '%result' => var_export($unit, TRUE),
          ]
        );
        // continue;.
      }

      /*\Drupal::logger('after date stuff')->notice(
      '<pre>' . print_r($property->id(), true) . '</pre>'
      );*/

      // Save the node.
      try {
        // node_save($node);
        $node->save();
      }
      catch (Exception $e) {
        \Drupal::logger('propqueue_worker_units')->notice(
          "Exception on node save:" . $e->getMessage()
        );
      }
    }

    // Save min/max fields on the property.
    if (count($units) && empty($units[0]['Error'])) {
      $property->set('field_yardi_minimum_rent', $min_rent);
      $property->set('field_yardi_maximum_rent', $max_rent);

      $property->set('field_yardi_minimum_beds', $min_beds);
      $property->set('field_yardi_maximum_beds', $max_beds);

      $property->set('field_yardi_min_available_date', date('Y-m-d', $min_avail));
      $property->set('field_yardi_max_available_date', date('Y-m-d', $max_avail));

      $property->set('field_has_available_units', TRUE);

      $property->save();
    }
    else {
      $property->set('field_has_available_units', FALSE);
      $property->save();
    }
  }

  /**
   *
   */
  private function deleteUnits($property_id) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'unit')
      ->condition('field_property', $property_id);
    $results = $query->execute();

    foreach ($results as $nid) {
      $node = Node::load($nid);
      $node->set('status', 0);
      $node->save();
      // $node->delete();
    }
  }

}
