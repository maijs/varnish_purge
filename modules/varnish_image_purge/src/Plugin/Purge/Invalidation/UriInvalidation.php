<?php

namespace Drupal\varnish_image_purge\Plugin\Purge\Invalidation;

use Drupal\purge\Plugin\Purge\Invalidation\InvalidationBase;

/**
 * Describes URL based invalidation, e.g. "public://file.txt".
 *
 * @PurgeInvalidation(
 *   id = "uri",
 *   label = @Translation("Uri"),
 *   description = @Translation("Invalidates by URI."),
 *   examples = {"public://file.txt"},
 *   expression_required = TRUE,
 *   expression_can_be_empty = FALSE,
 *   expression_must_be_string = TRUE
 * )
 */
class UriInvalidation extends InvalidationBase {

  /**
   * Uri string describing Uri of what needs invalidation.
   *
   * @var string
   */
  protected $expression;

  /**
   * {@inheritdoc}
   */
  public function validateExpression() {
    parent::validateExpression();
    //TODO: Validate that this is an URI.
    return $this->expression;
  }

}
