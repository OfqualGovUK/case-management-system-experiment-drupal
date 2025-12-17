<?php

namespace Drupal\external_entities_suitecrm\Plugin\ExternalEntities\StorageClient;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Utility\Token;
use Drupal\external_entities\StorageClient\StorageClientBase;
use Drupal\external_entities\Entity\ExternalEntityInterface;
use Drupal\entra_jwt_token\EntraJwtTokenService;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @StorageClient(
 *   id = "suitecrm_rest",
 *   label = @Translation("SuiteCRM REST (Entra Authenticated)")
 * )
 */
class SuiteCrmStorageClient extends StorageClientBase implements PluginFormInterface {

  protected $httpClient;
  protected $tokenService;
  protected $cacheBackend;
  protected $casesCache = NULL;

  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    TranslationInterface $string_translation,
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    Token $token,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    $external_entity_type,
    ClientInterface $http_client,
    EntraJwtTokenService $token_service,
    $cache_backend
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $string_translation,
      $logger_factory,
      $entity_type_manager,
      $entity_field_manager,
      $token,
      $entity_type_bundle_info,
      $external_entity_type
    );
    $this->httpClient = $http_client;
    $this->tokenService = $token_service;
    $this->cacheBackend = $cache_backend;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('token'),
      $container->get('entity_type.bundle.info'),
      $configuration['external_entity_type'] ?? NULL,
      $container->get('http_client'),
      $container->get('entra_jwt_token.token_service'),
      $container->get('cache.default')
    );
  }

  public function defaultConfiguration() {
    return [
      'list_endpoint' => '',
      'single_endpoint' => '',
      'format' => 'json',
      'parameters' => [],
      'response_data_path' => 'data',
    ];
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['list_endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('List Endpoint'),
      '#description' => $this->t('API endpoint for loading multiple entities (e.g., /Cases)'),
      '#default_value' => $this->configuration['list_endpoint'] ?? '',
      '#required' => TRUE,
    ];

    $form['single_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Single Entity Endpoint Pattern'),
      '#description' => $this->t('API endpoint pattern for loading a single entity. Use {id} as placeholder (e.g., /Cases?filter[case_number][eq]={id})'),
      '#default_value' => $this->configuration['single_endpoint'] ?? '',
      '#required' => TRUE,
    ];

    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Response Format'),
      '#options' => ['json' => $this->t('JSON'), 'xml' => $this->t('XML')],
      '#default_value' => $this->configuration['format'] ?? 'json',
    ];

    $form['parameters'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Query Parameters'),
      '#description' => $this->t('One parameter per line in format: key=value'),
      '#default_value' => $this->formatParameters($this->configuration['parameters'] ?? []),
      '#rows' => 5,
    ];

    $form['response_data_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Response Data Path'),
      '#description' => $this->t('Path to data in JSON response (e.g., "data" for responses like {"data": [...]}'),
      '#default_value' => $this->configuration['response_data_path'] ?? 'data',
    ];

    $form['apim_key_info'] = [
      '#type' => 'item',
      '#title' => $this->t('APIM Subscription Key'),
      '#markup' => $this->t('The APIM subscription key is configured via the <code>APIM_SUBSCRIPTION_KEY</code> environment variable.'),
    ];

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['list_endpoint'] = $form_state->getValue('list_endpoint');
    $this->configuration['single_endpoint'] = $form_state->getValue('single_endpoint');
    $this->configuration['format'] = $form_state->getValue('format');
    $this->configuration['parameters'] = $this->parseParameters($form_state->getValue('parameters'));
    $this->configuration['response_data_path'] = $form_state->getValue('response_data_path');
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Validate that endpoints are provided
    $list_endpoint = $form_state->getValue('list_endpoint');
    $single_endpoint = $form_state->getValue('single_endpoint');

    if (empty($list_endpoint)) {
      $form_state->setErrorByName('list_endpoint', $this->t('List endpoint is required.'));
    }

    if (empty($single_endpoint)) {
      $form_state->setErrorByName('single_endpoint', $this->t('Single entity endpoint pattern is required.'));
    }

    // Validate that single endpoint has {id} placeholder
    if (!empty($single_endpoint) && strpos($single_endpoint, '{id}') === FALSE) {
      $form_state->setErrorByName('single_endpoint', $this->t('Single entity endpoint must contain {id} placeholder.'));
    }
  }

  protected function getHttpHeaders() {
    $headers = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
    ];

    // Get JWT token
    $jwt_token = $this->tokenService->getAccessToken();
    if ($jwt_token) {
      $headers['Authorization'] = 'Bearer ' . $jwt_token;
    } else {
      \Drupal::logger('external_entities_suitecrm')->warning('JWT token not available for API request');
    }

    // Get APIM subscription key from environment
    $apim_key = getenv('APIM_SUBSCRIPTION_KEY');
    if ($apim_key) {
      $headers['Ocp-Apim-Subscription-Key'] = $apim_key;
    } else {
      \Drupal::logger('external_entities_suitecrm')->warning('APIM_SUBSCRIPTION_KEY environment variable not set');
    }

    return $headers;
  }

  protected function makeRequest($method, $endpoint, array $params = [], $body = NULL) {
    if (empty($endpoint)) {
      \Drupal::logger('external_entities_suitecrm')->error('Endpoint is empty');
      return [];
    }

    $options = ['headers' => $this->getHttpHeaders()];
    if (!empty($params)) {
      $options['query'] = $params;
    }
    if ($body !== NULL) {
      $options['json'] = $body;
    }

    try {
      $response = $this->httpClient->request($method, $endpoint, $options);
      $body = (string) $response->getBody();

      $data = json_decode($body, TRUE);

      // Extract data from response path if configured
      if (!empty($this->configuration['response_data_path']) && isset($data[$this->configuration['response_data_path']])) {
        return $data[$this->configuration['response_data_path']];
      }

      return $data;
    } catch (\Exception $e) {
      \Drupal::logger('external_entities_suitecrm')->error('API request failed: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  public function query(array $parameters = [], array $sorts = [], ?int $start = NULL, ?int $length = NULL): array {
    // Check cache first (5 minute TTL)
    $cache_key = 'suitecrm_cases_all';
    $cached = $this->cacheBackend->get($cache_key);

    if ($cached && !empty($cached->data)) {
      \Drupal::logger('external_entities_suitecrm')->info('Using cached data (expires: @expires)', [
        '@expires' => date('Y-m-d H:i:s', $cached->expire)
      ]);
      $transformed = $cached->data;
    } else {
      // Cache miss - fetch from API

      $endpoint = $this->configuration['list_endpoint'] ?? '';

      $config_params = $this->configuration['parameters'] ?? [];
      if (!is_array($config_params)) {
        $config_params = [];
      }

      // Filter out parameters that SuiteCRM API doesn't support
      $filtered_params = [];
      foreach ($parameters as $key => $value) {
        // Skip offset, limit, and sort parameters
        if (!in_array($key, ['offset', 'limit']) && strpos($key, 'sort') !== 0) {
          $filtered_params[$key] = $value;
        }
      }

      $params = array_merge($config_params, $filtered_params);

      $result = $this->makeRequest('GET', $endpoint, $params);

      // Transform JSON:API format to flat structure
      $transformed = [];
      if (is_array($result)) {
        foreach ($result as $item) {
          if (isset($item['attributes']) && isset($item['id'])) {
            // Flatten: merge id from attributes (case_number) and all attributes
            $flat = ['id' => $item['attributes']['case_number']] + $item['attributes'];
            // Use case_number as key so load() can find it
            $key = $item['attributes']['case_number'];
            $transformed[$key] = $flat;
          }
        }

        // Cache for 5 minutes (300 seconds)
        $this->cacheBackend->set($cache_key, $transformed, time() + 300);
      }
    }

    // Apply sorting in PHP since API doesn't support it
    if (!empty($sorts)) {
      $sort_field = array_key_first($sorts);
      $sort_direction = $sorts[$sort_field];
      usort($transformed, function($a, $b) use ($sort_field, $sort_direction) {
        $cmp = ($a[$sort_field] ?? '') <=> ($b[$sort_field] ?? '');
        return $sort_direction === 'DESC' ? -$cmp : $cmp;
      });
    }

    // Apply pagination in PHP
    if ($start !== NULL || $length !== NULL) {
      $start = $start ?? 0;
      $length = $length ?? count($transformed);
      $transformed = array_slice($transformed, $start, $length, true);
    }

    return $transformed;
  }

  public function querySource(array $parameters = [], array $sorts = [], ?int $start = NULL, ?int $length = NULL): array {
    return $this->query($parameters, $sorts, $start, $length);
  }

  /**
   * {@inheritdoc}
   */
  public function countQuery(array $parameters = []): int {
    // Get all entities and count them
    $all_entities = $this->query($parameters);
    return count($all_entities);
  }

  public function transliterateDrupalFilters(array $parameters, array $context = []): array {
    $translated = [];
    foreach ($parameters as $filter) {
      $field = $filter['field'] ?? '';
      $value = $filter['value'] ?? '';
      $operator = $filter['operator'] ?? '=';

      switch ($operator) {
        case '=':
          $translated['filter[' . $field . '][eq]'] = $value;
          break;
        default:
          $translated['filter[' . $field . '][eq]'] = $value;
      }
    }
    return $translated;
  }

  public function load(string|int $id): ?array {
    // Use cached data from loadMultiple to avoid rate limiting
    if ($this->casesCache === NULL) {
      $this->casesCache = $this->query();
    }

    // Look for the case by ID in the cache
    foreach ($this->casesCache as $case) {
      if (isset($case['id']) && $case['id'] == $id) {
        return $case;
      }
    }

    return NULL;
  }

  public function loadMultiple(?array $ids = NULL): array {
    // Always fetch all cases in one API call to avoid rate limiting
    $all_cases = $this->query();

    // If no specific IDs requested, return all
    if ($ids === NULL) {
      return $all_cases;
    }

    // Filter to only requested IDs
    $filtered = [];
    foreach ($ids as $id) {
      if (isset($all_cases[$id])) {
        $filtered[$id] = $all_cases[$id];
      }
    }
    return $filtered;
  }

  public function save(ExternalEntityInterface $entity): int {
    // Clear cache when saving
    $this->clearCache();
    return SAVED_NEW;
  }

  public function delete(ExternalEntityInterface $entity): void {
    // Clear cache when deleting
    $this->clearCache();
  }

  /**
   * Clear the cached cases data.
   */
  public function clearCache() {
    $cache_key = 'suitecrm_cases_all';
    $this->cacheBackend->delete($cache_key);
  }

  protected function parseParameters($text) {
    if (empty($text)) {
      return [];
    }

    $parameters = [];
    $lines = explode("\n", $text);

    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line) || strpos($line, '#') === 0) {
        continue;
      }

      if (strpos($line, '=') !== FALSE) {
        list($key, $value) = explode('=', $line, 2);
        $parameters[trim($key)] = trim($value);
      }
    }

    return $parameters;
  }

  protected function formatParameters($parameters) {
    // Handle string input (from config)
    if (is_string($parameters)) {
      return $parameters;
    }

    if (empty($parameters) || !is_array($parameters)) {
      return '';
    }

    $lines = [];
    foreach ($parameters as $key => $value) {
      $lines[] = $key . '=' . $value;
    }

    return implode("\n", $lines);
  }

}
