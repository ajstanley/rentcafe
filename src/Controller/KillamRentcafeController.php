<?php

declare(strict_types=1);

namespace Drupal\killam_rentcafe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\killam_rentcafe\Yardi;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Killam Rentcafe Integration routes.
 */
final class KillamRentcafeController extends ControllerBase {

  /**
   * The controller constructor.
   *
   * @param \Drupal\killam_rentcafe\Yardi $killamRentcafeYardi
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queueWorkerManager
   */
  public function __construct(
    private readonly Yardi $killamRentcafeYardi,
    private readonly QueueWorkerManagerInterface $queueWorkerManager,
    EntityTypeManager $entityTypeManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('killam_rentcafe.yardi'),
      $container->get('plugin.manager.queue_worker'),
      $container->get('entity_type.manager'),

    );
  }

  /**
   * Sole purpose of this function is to test the queue worker.
   */
  public function __invoke(): array {
    $property_code = 'p0510278';
    $property_nid = '522';
    $node = $this->entityTypeManager->getStorage('node')->load($property_nid);
    $plugin_id = 'killam_rentcafe_unitsavailable';
    $queue_worker = $this->queueWorkerManager->createInstance($plugin_id);
    $queue_worker->processItem($node);

    $build['content'] = [
      '#type' => 'item',
      '#markup' => "Processed {$property_code}",
    ];

    return $build;
  }

}
