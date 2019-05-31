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
      'baseUrl' => 'https://preportal.pageplanner.no/api-demo/api/',
      'access_token_url' => 'https://login.microsoftonline.com/pageplannersolutions.com/oauth2/token',
      'client_id' => '5500358d-11d9-4ebe-8c78-1df551d120d7',
      'client_secret' => 'c}#7;TJflVbR)qg({#5j}_!QfubU+E(9r!gS#^}%3V]a.2ojz',
    ];

    $client = new PageplannerClient($client_configuration);
    return $client;
  }

}
