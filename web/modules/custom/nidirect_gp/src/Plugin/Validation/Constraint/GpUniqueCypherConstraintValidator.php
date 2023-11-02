<?php

namespace Drupal\nidirect_gp\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\nidirect_gp\GpUniqueCypher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a GP has a unique cypher.
 */
class GpUniqueCypherConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {


  /**
   * The GP unique cypher service.
   *
   * @var \Drupal\nidirect_gp\GpUniqueCypher
   */
  protected $uniqueCypherService;

  /**
   * Class constructor.
   *
   * @param \Drupal\nidirect_gp\GpUniqueCypher $gp_unique_cypher_service
   *   The GP unique cypher service.
   */
  public function __construct(GpUniqueCypher $gp_unique_cypher_service) {
    $this->uniqueCypherService = $gp_unique_cypher_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('nidirect_gp.unique_cypher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if (!isset($entity) ||  !$cypher = $entity->getCypher()) {
      return;
    }

    /** @var \Drupal\nidirect_gp\Plugin\Validation\Constraint\GpUniqueCypherConstraint $constraint */
    if ($this->uniqueCypherService->isCypherUnique($cypher, [$entity->id()]) === FALSE) {
      $this->context->addViolation($constraint->getMessage(), ['@cypher' => $cypher]);
    }
  }

}
