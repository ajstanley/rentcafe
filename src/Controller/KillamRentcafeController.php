<?php

declare(strict_types=1);

namespace Drupal\killam_rentcafe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\killam_rentcafe\Yardi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Killam Rentcafe Integration routes.
 */
final class KillamRentcafeController extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly Yardi $killamRentcafeYardi,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('killam_rentcafe.yardi'),
    );
  }

  /**
   * Builds the response.
   */
  public function __invoke(): array {
    $token = $this->killamRentcafeYardi->getApiToken();

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
