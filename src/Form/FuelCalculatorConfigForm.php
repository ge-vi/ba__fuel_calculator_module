<?php

namespace Drupal\fuel_calculator\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\fuel_calculator\FuelCalculator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure fuel calculator settings.
 */
class FuelCalculatorConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'fuel_calculator.settings';

  /**
   * The entity type manager.
   *
   * @var \Drupal\fuel_calculator\FuelCalculator
   */
  protected $fuelCalculator;

  /**
   * The route match var.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Config form constructor.
   *
   * @param \Drupal\fuel_calculator\FuelCalculator $fuelCalculator
   *   The fuel calculator service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $routeMatch
   *   The route match service.
   */
  public function __construct(
    FuelCalculator $fuelCalculator,
    ConfigFactoryInterface $config_factory,
    CurrentRouteMatch $routeMatch,
  ) {
    parent::__construct($config_factory);
    $this->fuelCalculator = $fuelCalculator;
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ConfigFormBase|FuelCalculatorConfigForm|static {
    return new static(
      $container->get('fuel_calculator.calculator'),
      $container->get('config.factory'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'fuel_calculator_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $has_errors = FALSE;

    // If we have parameters in the route we use those for prefilling the form.
    $parameterBag = $this->routeMatch->getParameters();
    // In case the route is from the calculator block (node in params)
    // skip this.
    if ($parameterBag->count() > 0 && !$parameterBag->has('node')) {
      $distance = $parameterBag->get('distance');
      $consumption = $parameterBag->get('consumption');
      $price = $parameterBag->get('price');

      // Validate query params.
      if ($this->fuelCalculator->validateQueryParams($distance, $consumption, $price)) {
        // Set in form state to use on submit.
        $form_state->set('route_input', [
          'distance' => $distance,
          'consumption' => $consumption,
          'price' => $price,
        ]);

        $this->config(static::SETTINGS)
          ->set('distance', $distance)
          ->set('consumption', $consumption)
          ->set('price', $price)
          ->save();

        // Calculate with the input from the route params.
        // Save the input values from the route to the config.
        $fuel_spent = $this->fuelCalculator->calculateFuelSpent();
        $this->config(static::SETTINGS)
          ->set('fuel_spent', $fuel_spent)
          ->save();

        $fuel_cost = $this->fuelCalculator->calculateFuelCost();
        $this->config(static::SETTINGS)
          ->set('fuel_cost', $fuel_cost)
          ->save();

        // Log the most recent calculation.
        $this->fuelCalculator->logCalculations();
      }
      else {
        \Drupal::messenger()->addError('Cannot use "," on input values while using input values from route parameters, please use "."');
        $has_errors = TRUE;
      }
    }

    if (!$has_errors) {
      $form['input'] = [
        '#type' => 'fieldset',
        '#title' => 'Input',
      ];
      $form['output'] = [
        '#type' => 'fieldset',
        '#title' => 'Result',
      ];

      $form['input']['distance'] = [
        '#type' => 'number',
        '#title' => $this->t('Distance traveled'),
        '#default_value' => $form_state->getUserInput()['distance'] ?? $config->get('distance'),
        '#step' => '0.01',
        '#min' => 0,
        '#field_suffix' => 'km',
        '#required' => TRUE,
      ];
      $form['input']['consumption'] = [
        '#type' => 'number',
        '#title' => $this->t('Fuel consumption'),
        '#default_value' => $form_state->getUserInput()['consumption'] ?? $config->get('consumption'),
        '#step' => '0.01',
        '#min' => 0,
        '#field_suffix' => 'l/100km',
        '#required' => TRUE,
      ];
      $form['input']['price'] = [
        '#type' => 'number',
        '#title' => $this->t('Price per Liter'),
        '#default_value' => $form_state->getUserInput()['price'] ?? $config->get('price'),
        '#min' => 0,
        '#step' => '0.01',
        '#field_suffix' => 'EUR',
        '#required' => TRUE,
      ];

      $fuel_spent = str_replace('.', ',', $config->get('fuel_spent'));
      $form['output']['fuel_spent'] = [
        '#type' => 'textfield',
        '#disabled' => TRUE,
        '#description' => 'Values are automatically calculated.',
        '#title' => $this->t('Fuel spent'),
        '#default_value' => $fuel_spent,
        '#field_suffix' => 'liters',
      ];

      $fuel_cost = str_replace('.', ',', $config->get('fuel_cost'));
      $form['output']['fuel_cost'] = [
        '#type' => 'textfield',
        '#description' => 'Values are automatically calculated.',
        '#disabled' => TRUE,
        '#title' => $this->t('Fuel cost'),
        '#default_value' => $fuel_cost,
        '#field_suffix' => 'EUR',
      ];

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => 'Calculate',
      ];

      $form['reset'] = [
        '#type' => 'submit',
        '#value' => 'Reset',
        '#submit' => [[$this, 'resetForm']],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit function for reset form.
   */
  public function resetForm($form, $form_state) {
    // @todo Should I set all values to zero? I am keeping the initial config values.
    \Drupal::messenger()->addStatus('Form was reset. Displayed values are from currently stored results.');
    $form_state->setRebuild(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // @todo What other validation beside what is covered by the Number API?
    // No need to call validateQueryParams here,
    // as the checks performed by it are already made.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Check if user added other inputs than what was provided in the params
    // route, then redirect to the route without params and calculate there.
    if (array_key_exists('route_input', $form_state->getStorage())) {
      $route_input = $form_state->getStorage()['route_input'];
      foreach ($route_input as $key => $value) {
        if ($value != $form_state->getUserInput()[$key]) {
          $this->config(static::SETTINGS)
            // Set the submitted configuration setting.
            ->set('distance', (float) $form_state->getValue('distance'))
            ->set('consumption', (float) $form_state->getValue('consumption'))
            ->set('price', (float) $form_state->getValue('price'))
            ->save();
          $form_state->setRedirect('fuel_calculator.settings');
        }
      }
    }

    $this->config(static::SETTINGS)
      // Set the submitted configuration setting.
      ->set('distance', $form_state->getUserInput()['distance'])
      ->set('consumption', $form_state->getUserInput()['consumption'])
      ->set('price', $form_state->getUserInput()['price'])
      ->save();

    // Calculate results.
    $fuel_spent = $this->fuelCalculator->calculateFuelSpent();
    $this->config(static::SETTINGS)
      ->set('fuel_spent', $fuel_spent)
      ->save();

    $fuel_cost = $this->fuelCalculator->calculateFuelCost();
    $this->config(static::SETTINGS)
      ->set('fuel_cost', $fuel_cost)
      ->save();

    $form_state->setValue('fuel_spent', $fuel_spent);
    $form_state->setValue('fuel_cost', $fuel_cost);

    // Log the calculations with the latest results.
    $this->fuelCalculator->logCalculations();

    parent::submitForm($form, $form_state);
  }

}
