<?php

namespace Drupal\varnish_image_purge\Plugin\Purge\Purger;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Utility\Token;
use Drupal\image\Entity\ImageStyle;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Pool;
use Drupal\varnish_purger\Plugin\Purge\Purger\VarnishPurgerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Varnish Image Purger.
 *
 * @PurgePurger(
 *   id = "varnishimage",
 *   label = @Translation("Varnish Image Purger"),
 *   configform = "\Drupal\varnish_image_purge\Form\VarnishImagePurgerForm",
 *   cooldown_time = 0.0,
 *   description = @Translation("Invalidate file URI"),
 *   multi_instance = TRUE,
 *   types = {},
 * )
 */
class VarnishImagePurger extends VarnishPurgerBase {

  const VARNISH_PURGE_CONCURRENCY = 10;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Varnish image purger constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \GuzzleHttp\ClientInterface $http_client
   * @param \Drupal\Core\Utility\Token $token
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client, Token $token, FileSystemInterface $file_system, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $http_client, $token);

    $this->fileSystem = $file_system;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('token'),
      $container->get('file_system'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    // Prepare a generator for the requests that we will be sending out. Use a
    // generator, as the pool implementation will request new item to pass
    // thorough the wire once any of the concurrency slots is free.
    $requests = function () use ($invalidations) {
      $client = $this->client;
      $method = $this->settings->request_method;
      $logger = $this->logger();

      // Get image styles.
      $styles = $this->getImageStyles();

      /** @var \Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface $invalidation */
      foreach ($invalidations as $invalidation) {
        $token_data = ['invalidation' => $invalidation];
        $host_uri = $this->getUri($token_data);
        $options = $this->getOptions($token_data);

        // Get image style URIs.
        $uris = $this->getImageStyleUris($invalidation, $styles);

        // No URIs to invalidate. We can say that it is succeeded.
        if (empty($uris)) {
          $invalidation->setState(InvalidationInterface::SUCCEEDED);
        }

        foreach ($uris as $uri) {
          // Replace the hostname of the URI.
          $uri = $this->replaceHostname($host_uri, $uri);

          yield function () use ($client, $uri, $method, $options, $invalidation, $logger) {
            return $client->requestAsync($method, $uri, $options)->then(
            // Handle the positive case.
              function ($response) use ($invalidation) {
                $invalidation->setState(InvalidationInterface::SUCCEEDED);
              },
              // Handle the negative case.
              function ($reason) use ($invalidation, $uri, $options, $logger) {
                $invalidation->setState(InvalidationInterface::FAILED);

                $message = $reason instanceof \Exception ? $reason->getMessage() : (string) $reason;

                // Log as much useful information as we can.
                $headers = $options['headers'];
                unset($options['headers']);

                $debug = json_encode([
                  'msg' => $message,
                  'uri' => $uri,
                  'method' => $this->settings->request_method,
                  'guzzle_opt' => $options,
                  'headers' => $headers,
                ], JSON_THROW_ON_ERROR);

                $logger->emergency('Item failed due @e, details (JSON): @debug', [
                  '@e' => is_object($reason) ? get_class($reason) : (string) $reason,
                  '@debug' => $debug,
                ]);
              }
            );
          };
        }
      }
    };

    // Prepare a POOL that will make the requests with a given concurrency.
    (new Pool($this->client, $requests(), ['concurrency' => self::VARNISH_PURGE_CONCURRENCY]))
      ->promise()
      ->wait();
  }

  /**
   * Returns the list of image styles.
   *
   * @return \Drupal\image\Entity\ImageStyle[]
   */
  protected function getImageStyles() {
    return ImageStyle::loadMultiple();
  }

  /**
   * Get a list of image style URIs.
   *
   * @param \Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface $invalidation
   * @param array $image_styles
   *
   * @return array
   */
  protected function getImageStyleUris(InvalidationInterface $invalidation, array $image_styles) {
    $result = [];

    foreach ($image_styles as $image_style) {
      // Purge just the image styles that has been created in Drupal.
      // Note: if for some reason the image style is not in Drupal, but it's
      // cache in Varnish it will not be purged.
      $image_uri = $image_style->buildUri($invalidation->getExpression());
      $image_style_path = $this->fileSystem->realpath($image_uri);

      if ($image_style_path) {
        $result[] = $image_style->buildUrl($invalidation->getExpression());
      }
    }

    return $result;
  }

  /**
   * Replaces the scheme and the hostname.
   *
   * @param $host_uri
   * @param $uri
   *
   * @return array|string|string[]
   */
  protected function replaceHostname($host_uri, $uri) {
    return str_replace($this->request->getSchemeAndHttpHost() . '/', $host_uri, $uri);
  }

}
