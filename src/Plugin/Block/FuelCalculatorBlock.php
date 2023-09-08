<?php

namespace Drupal\fuel_calculator\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Fuel calculator' Block.
 *
 * @Block(
 *   id = "fuel_calculator_block",
 *   admin_label = @Translation("Fuel calculator block"),
 * )
 */
class FuelCalculatorBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    // Render the configuration form.
    /** @var \Drupal\Core\Render\Element\Form $formCalculator */
    $formCalculator = \Drupal::formBuilder()->getForm('Drupal\fuel_calculator\Form\FuelCalculatorConfigForm');
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config */
    $config = \Drupal::config('fuel_calculator.settings');
    $formCalculator['input']['distance']['#value'] = $config->get('distance');
    $formCalculator['input']['consumption']['#value'] = $config->get('consumption');
    $formCalculator['input']['price']['#value'] = $config->get('price');

    return $formCalculator;
  }

}
