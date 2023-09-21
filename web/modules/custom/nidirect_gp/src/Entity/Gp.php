<?php

namespace Drupal\nidirect_gp\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * Defines the GP entity.
 *
 * @ingroup nidirect_gp
 *
 * @ContentEntityType(
 *   id = "gp",
 *   label = @Translation("GP"),
 *   label_collection = @Translation("GPs"),
 *   label_singular = @Translation("GP"),
 *   label_plural = @Translation("GPs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count GP",
 *     plural = "@count GPs",
 *   ),
 *   label_callback = "gp_format_name",
 *   handlers = {
 *     "storage" = "Drupal\nidirect_gp\GpStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\nidirect_gp\GpListBuilder",
 *     "views_data" = "Drupal\nidirect_gp\Entity\GpViewsData",
 *     "translation" = "Drupal\nidirect_gp\GpTranslationHandler",
 *     "form" = {
 *       "default" = "Drupal\nidirect_gp\Form\GpForm",
 *       "add" = "Drupal\nidirect_gp\Form\GpForm",
 *       "edit" = "Drupal\nidirect_gp\Form\GpForm",
 *       "delete" = "Drupal\nidirect_gp\Form\GpDeleteForm",
 *     },
 *     "access" = "Drupal\nidirect_gp\GpAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\nidirect_gp\GpHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "gp",
 *   data_table = "gp_field_data",
 *   revision_table = "gp_revision",
 *   revision_data_table = "gp_field_revision",
 *   translatable = TRUE,
 *   admin_permission = "administer gp entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message",
 *   },
 *   links = {
 *     "canonical" = "/gp/{gp}",
 *     "add-form" = "/node/add/gp",
 *     "edit-form" = "/gp/{gp}/edit",
 *     "delete-form" = "/gp/{gp}/delete",
 *     "version-history" = "/gp/{gp}/revisions",
 *     "revision" = "/gp/{gp}/revisions/{gp_revision}/view",
 *     "revision_revert" = "/gp/{gp}/revisions/{gp_revision}/revert",
 *     "revision_delete" = "/gp/{gp}/revisions/{gp_revision}/delete",
 *     "translation_revert" = "/gp/{gp}/revisions/{gp_revision}/revert/{langcode}",
 *     "collection" = "/admin/content/gp",
 *   },
 *   field_ui_base_route = "gp.settings",
 *   constraints = {"GpUniqueCypher" = {}}
 * )
 */
class Gp extends RevisionableContentEntityBase implements GpInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getDisplayName() {
    $name = $this->label();
    return $name;
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    if ($rel === 'revision_revert' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }
    elseif ($rel === 'revision_delete' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      /** @var \Drupal\nidirect_gp\Entity\Gp $translation */
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the anonymous user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(0);
      }
    }

    // If no revision author has been set explicitly, make the gp owner the
    // revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return sprintf("Dr %s %s", $this->getFirstName(), $this->getLastName());
  }

  /**
   * {@inheritdoc}
   */
  public function getFirstName() {
    return $this->get('first_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setFirstName($name) {
    $this->set('first_name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastName() {
    return $this->get('last_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastName($name) {
    $this->set('last_name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCypher() {
    return $this->get('cypher')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCypher($name) {
    $this->set('cypher', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['first_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('First name'))
      ->setDescription(t('The first name of the GP.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['last_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Last name'))
      ->setDescription(t('The last name of the GP.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['cypher'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Cypher'))
      ->setDescription(t('The cypher of the GP or practice.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the GP is published.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -2,
      ]);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the GP entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Revision translation affected'))
      ->setDescription(t('Indicates if the last edit of a translation belongs to current revision.'))
      ->setReadOnly(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    return $fields;
  }

}
