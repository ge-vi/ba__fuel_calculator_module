<?php

namespace Drupal\fuel_calculator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Fuel calculator service.
 */
class FuelCalculator implements FuelCalculatorInterface {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'fuel_calculator.settings';

  /**
   * Config Factory var.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $config;

  /**
   * The immutable config of the calculator form.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $formConfig;

  /**
   * The logger channel factory var.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $loggerChannel;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $request;

  /**
   * The account interface.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Fuel calculator service constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannel
   *   The logger channel service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The request stack service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The account interface service.
   */
  public function __construct(
    ConfigFactoryInterface $config,
    LoggerChannelFactoryInterface $loggerChannel,
    RequestStack $request,
    AccountInterface $currentUser,
  ) {
    $this->config = $config;
    $this->loggerChannel = $loggerChannel->get('fuel_calculator');
    $this->formConfig = $this->config->get(static::SETTINGS);
    $this->request = $request;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('request_stack'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function calculateFuelSpent(): string {
    return number_format($this->formConfig->get('distance') / 100 * $this->formConfig->get('consumption'),
    1, '.', ' ');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateFuelCost(): string {
    $fuel_spent = $this->formConfig->get('fuel_spent');
    return number_format($fuel_spent * $this->formConfig->get('price'),
      1, '.', ' ');
  }

  /**
   * {@inheritdoc}
   */
  public function logCalculations(): void {
    $ip_address = $this->request->getCurrentRequest()->getClientIp();
    $username = $this->currentUser->getAccountName();

    $this->loggerChannel->notice(t('A fuel calculation was detected that was made by the client with ip: @ip
    <br>Username: @username <br><strong>Input values:</strong><br>Distance traveled: @distance<br>Fuel consumption: @consumption
    <br>Price per liter: @price <br><strong>Calculation results:</strong><br>Fuel spent: @fuel_spent<br>Fuel cost: @fuel_cost', [
      '@ip' => $ip_address,
      '@username' => $username,
      '@distance' => $this->formConfig->get('distance'),
      '@consumption' => $this->formConfig->get('consumption'),
      '@price' => $this->formConfig->get('price'),
      '@fuel_spent' => $this->formConfig->get('fuel_spent'),
      '@fuel_cost' => $this->formConfig->get('fuel_cost'),
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function validateQueryParams($distance, $consumption, $price) : bool {

    $valid = TRUE;

    if (str_contains($distance, ',') || str_contains($consumption, ',') || str_contains($price, ',')) {
      return FALSE;
    }

    // Check if the query param is a number and is greater than 0.
    // What other validations to cover?
    if (!is_numeric($distance) || $distance < 0) {
      $valid = FALSE;
    }
    if (!is_numeric($consumption) || $consumption < 0) {
      $valid = FALSE;
    }
    if (!is_numeric($price) || $price < 0) {
      $valid = FALSE;
    }

    return $valid;
  }

}
