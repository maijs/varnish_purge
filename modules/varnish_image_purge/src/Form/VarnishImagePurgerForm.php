<?php

namespace Drupal\varnish_image_purge\Form;

use Drupal\varnish_purger\Form\VarnishPurgerFormBase;

/**
 * Configure site information settings for this site.
 */
class VarnishImagePurgerForm extends VarnishPurgerFormBase {

  /**
   * The token group names this purger supports replacing tokens for.
   *
   * @see purge_tokens_token_info()
   *
   * @var string[]
   */
  protected $tokenGroups = ['invalidation'];

  /**
   * Static listing of all possible requests methods.
   *
   * @var array
   */
  protected $request_methods = ['URIBAN'];

}
