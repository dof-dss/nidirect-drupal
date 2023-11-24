<?php declare(strict_types = 1);

namespace Drupal\nidirect_common\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;

/**
 * Returns responses for NIDirect Common routes.
 */
final class AddPageController extends ControllerBase {

  protected ControllerResolverInterface $controllerResolver;

  /**
   * The controller constructor.
   */
  public function __construct(ControllerResolverInterface $controllerResolver) {
    $this->controllerResolver = $controllerResolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('controller_resolver'),
    );
  }

  /**
   * Builds the response.
   */
  public function addPage(): array {

    $request = new Request([], [], ['_controller' => '\Drupal\node\Controller\NodeController::addPage']);
    $node_controller = $this->controllerResolver->getController($request);

    $build = call_user_func_array($node_controller, []);

    return $build;
  }

}
