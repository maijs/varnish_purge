<?php

namespace Drupal\varnish_image_purge;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\file\Entity\File;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface;
use Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface;
use Drupal\purge\Plugin\Purge\Queuer\QueuersServiceInterface;

/**
 * Varnish image queuer instance.
 */
class VarnishImageQueuer implements VarnishImageQueuerInterface {

  /**
   * Image queue plugin ID.
   *
   * @var string
   */
  protected $imageQueuer = 'varnish_image';

  /**
   * Image field types.
   *
   * @var string[]
   */
  protected $imageFieldTypes = [
    'image',
  ];

  /**
   * Configuration
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Invalidation service.
   *
   * @var \Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface
   */
  protected $invalidationService;

  /**
   * Purge queuers service.
   *
   * @var \Drupal\purge\Plugin\Purge\Queuer\QueuersServiceInterface
   */
  protected $purgeQueuers;

  /**
   * Purge queue service.
   *
   * @var \Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface
   */
  protected $purgeQueue;

  /**
   * Varnish image queuer constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   * @param \Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface $invalidation_service
   * @param \Drupal\purge\Plugin\Purge\Queuer\QueuersServiceInterface $purge_queuers
   * @param \Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface $purge_queue
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityFieldManagerInterface $entity_field_manager, InvalidationsServiceInterface $invalidation_service, QueuersServiceInterface $purge_queuers, QueueServiceInterface $purge_queue) {
    $this->config = $config_factory->get('varnish_image_purge.configuration');
    $this->entityFieldManager = $entity_field_manager;
    $this->invalidationService = $invalidation_service;
    $this->purgeQueuers = $purge_queuers;
    $this->purgeQueue = $purge_queue;
  }

  /**
   * Invalidates entity images.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return void
   */
  public function invalidate(EntityInterface $entity) {
    // Do not proceed if queuer plugin is not available.
    if (!$this->getQueuer()) {
      return;
    }

    if ($this->validateEntity($entity)) {
      // Get image URIs.
      if ($uris = $this->getUris($entity)) {
        // Invalidate image URIs.
        $this->invalidateUris($uris);
      }
    }
  }

  /**
   * Return TRUE if entity type ID of the entity is in the entity type list.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function validateEntity(EntityInterface $entity) {
    if (!($entity instanceof FieldableEntityInterface)) {
      return FALSE;
    }

    $bundle = $entity->bundle();
    $entity_type = $entity->getEntityTypeId();

    // Get entity types to purge.
    $entity_types_to_purge = $this->config->get('entity_types');

    // If entity type of the entity is not in the entity type list,
    // return FALSE.
    if (!(empty($entity_types_to_purge) || (isset($entity_types_to_purge[$entity_type]) && in_array($bundle, $entity_types_to_purge[$entity_type])))) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Returns image URIs.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return string[]
   */
  public function getUris(EntityInterface $entity) {
    $result = [];

    // Get image fields.
    $image_fields = $this->getImageFields($entity);

    foreach ($image_fields as $field_name) {
      foreach ($entity->get($field_name) as $delta => $field_item) {
        if ($file = File::load($field_item->target_id)) {
          $result[] = $file->getFileUri();
        }
      }
    }

    return array_unique($result);
  }

  /**
   * Adds image URIs to the purge queue.
   *
   * @param array $uris
   *
   * @return void
   */
  public function invalidateUris(array $uris) {
    if ($invalidations = $this->getInvalidations($uris)) {
      // Get varnish image queuer.
      $image_queuer = $this->getQueuer();
      // Add to purge queue.
      $this->purgeQueue->add($image_queuer, $invalidations);
    }
  }

  /**
   * Returns a list of field names that represent an image field type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return string[]
   */
  protected function getImageFields(EntityInterface $entity) {
    $result = [];

    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    if ($field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle)) {
      foreach ($field_definitions as $field_definition) {
        if (in_array($field_definition->getType(), $this->imageFieldTypes)) {
          $result[] = $field_definition->getName();
        }
      }
    }

    return $result;
  }

  /**
   * Returns a list of invalidation instances.
   *
   * @param array $image_uris
   *
   * @return \Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface[]
   */
  protected function getInvalidations(array $image_uris) {
    $result = [];

    try {
      foreach ($image_uris as $image_uri) {
        $result[] = $this->invalidationService->get('uri', $image_uri);
      }
    }
    catch (\Exception $e) {
    }

    return $result;
  }

  /**
   * Returns image queue.
   *
   * @return \Drupal\purge\Plugin\Purge\Queuer\QueuerInterface|false
   */
  protected function getQueuer() {
    return $this->purgeQueuers->get($this->imageQueuer);
  }

}
