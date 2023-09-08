<?php

namespace Drupal\fuel_calculator;

/**
 * Fuel calculator service interface.
 */
interface FuelCalculatorInterface {

  /**
   * Calculate fuel spent.
   *
   * @return string
   *   The fuel spent result.
   */
  public function calculateFuelSpent(): string;

  /**
   * Calculate fuel cost.
   *
   * @return string
   *   The fuel cost result.
   */
  public function calculateFuelCost(): string;

  /**
   * Function to log the calculations.
   */
  public function logCalculations(): void;

  /**
   * Function for validating query params.
   *
   * @param float $distance
   *   The distance.
   * @param float $consumption
   *   The fuel consumption.
   * @param float $price
   *   The price.
   *
   * @return bool
   *   Whether the params are valid or not.
   */
  public function validateQueryParams(float $distance, float $consumption, float $price) : bool;

}
