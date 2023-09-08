<?php

namespace Drupal\fuel_calculator\Plugin\rest\resource;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\fuel_calculator\FuelCalculator;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a Fuel Calculator Resource.
 *
 * @RestResource(
 *   id = "fuel_calculator_resource",
 *   label = @Translation("Fuel calculator"),
 *   uri_paths = {
 *     "canonical" = "/api/fuel-calculator"
 *   }
 * )
 */
class FuelCalculatorResource extends ResourceBase {

  /**
   * The fuel calculator var.
   *
   * @var \Drupal\fuel_calculator\FuelCalculator
   */
  protected FuelCalculator $fuelCalculator;

  /**
   * The request stack var.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The config factory var.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $configFactory;

  /**
   * The resource object constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Resource plugin id.
   * @param array $plugin_definition
   *   Plugin def.
   * @param array $serializer_formats
   *   Serializer.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger interface.
   * @param \Drupal\fuel_calculator\FuelCalculator $fuelCalculator
   *   Fuel calculator.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    FuelCalculator $fuelCalculator,
    RequestStack $requestStack,
    ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->fuelCalculator = $fuelCalculator;
    $this->requestStack = $requestStack;
    $this->configFactory = $configFactory->getEditable('fuel_calculator.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('fuel_calculator'),
      $container->get('fuel_calculator.calculator'),
      $container->get('request_stack'),
      $container->get('config.factory'),
    );
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The resource response.
   */
  public function get(): ResourceResponse {
    $currentRequest = $this->requestStack->getCurrentRequest();

    $distance = $currentRequest->query->get('distance') ?? NULL;
    $consumption = $currentRequest->query->get('consumption') ?? NULL;
    $price = $currentRequest->query->get('price') ?? NULL;

    // If all values are given save to config.
    if (isset($distance) && isset($consumption) && isset($price)) {
      if ($this->fuelCalculator->validateQueryParams($distance, $consumption, $price)) {
        // Format  the input in order to
        // Calculate with the first two decimals only.
        $distance = number_format($distance, '2', '.', '');
        $price = number_format($price, '2', '.', '');
        $consumption = number_format($consumption, '2', '.', '');

        $this->configFactory
          ->set('distance', $distance)
          ->set('consumption', $consumption)
          ->set('price', $price)
          ->save();

        $this->configFactory
          ->set('fuel_spent', $this->fuelCalculator->calculateFuelSpent())
          ->save();
        $this->configFactory
          ->set('fuel_cost', $this->fuelCalculator->calculateFuelCost())
          ->save();

        $response = [
          'fuel_spent' => $this->configFactory->get('fuel_spent'),
          'fuel_cost' => $this->configFactory->get('fuel_cost'),
        ];
        // Log the calculations with the latest results.
        $this->fuelCalculator->logCalculations();
        return new ResourceResponse($response);
      }
    }
    // In case no query params are specified return the current stored results.
    else {
      $response = [
        'fuel_spent' => $this->configFactory->get('fuel_spent'),
        'fuel_cost' => $this->configFactory->get('fuel_cost'),
      ];
      return new ResourceResponse($response);
    }

    return new ResourceResponse('Query parameters are not valid for calculations.');
  }

}
