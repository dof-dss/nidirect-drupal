<?php

namespace Drupal\nidirect_gp\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a GP revision.
 *
 * @ingroup nidirect_gp
 */
final class GpRevisionDeleteForm extends ConfirmFormBase {

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The GP revision.
   *
   * @var \Drupal\nidirect_gp\Entity\GpInterface
   */
  protected $revision;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new GpRevisionDeleteForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Drupal date formatter service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Drupal messenger service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $connection, DateFormatterInterface $date_formatter, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
    $this->messenger = $messenger;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('date.formatter'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gp_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete the revision from %revision-date?', ['%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime())]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.gp.version_history', ['gp' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $gp_revision = NULL) {
    $this->revision = $this->getGpStorage()->loadRevision($gp_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->getGpStorage()->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')->notice('GP: deleted %title revision %revision.', [
      '%title' => $this->revision->label(),
      '%revision' => $this->revision->getRevisionId(),
    ]);
    $this->messenger->addMessage(t('Revision from %revision-date of GP %title has been deleted.', [
      '%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()),
      '%title' => $this->revision->label(),
    ]));
    $form_state->setRedirect(
      'entity.gp.canonical',
       ['gp' => $this->revision->id()]
    );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {gp_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.gp.version_history',
         ['gp' => $this->revision->id()]
      );
    }
  }

  /**
   * Gets the revisionable GP storage.
   */
  private function getGpStorage(): RevisionableStorageInterface {
    $storage = $this->entityTypeManager->getStorage('gp');
    assert($storage instanceof RevisionableStorageInterface);
    return $storage;
  }

}
