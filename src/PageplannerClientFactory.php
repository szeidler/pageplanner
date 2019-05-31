<?php

namespace Drupal\pageplanner;

use Drupal\Core\Config\ConfigFactoryInterface;
use szeidler\Pageplanner\PageplannerClient;

/**
 * Class PageplannerClientFactory.
 */
class PageplannerClientFactory {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new ClientFactory instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Creates a Pageplanner client based on the config.
   *
   * @return \szeidler\Pageplanner\PageplannerClient
   *   The pageplanner client.
   */
  public function createFromConfig() {
    $config = $this->configFactory->get('pageplanner.settings');
    $client_configuration = [
      'baseUrl' => $config->get('base_url'),
      'access_token_url' => $config->get('access_token_url'),
      'client_id' => $config->get('client_id'),
      'client_secret' => $config->get('client_secret'),
    ];

    $client = new PageplannerClient($client_configuration);
    return $client;
  }

}
