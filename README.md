# Fuel calculator module

## What does this do

This module provides a method to calculate fuel consumption and output the price based on the input values.

1. There is a config form implemented at /admin/config/fuel-calculator. Here you can provide input values and upon submission the results are
saved in a config and displayed on the form results section.
2. There is the possibility to add parameters in the route directly and to get the result upon accessing the route
e.g admin/config/fuel-calculator/250/6.5/1.49 - which translates to distance = 250, consumption = 6.5 and price = 1.49
3. A Rest API endpoint is available at /api/fuel-calculator. Here you can retrieve the latest stored results from the config.
4. There is the option to call the rest api endpoint with query parameters (e.g /api/fuel-calculator?distance=250&consumption=6.5&price=1.49)
and to get the result instantly.
5. There is the option to place a block Fuel calculator Block.
Placing the block will display the previously mentioned form on a block in a node.
6. You can do calculations in the block/config form/rest api query params/call drupal route with params and while navigating through the
calculator locations, the results will always be the latest.
7. A method to log the username, ip, input values, results was created and it logs for every calculation made, either it being executed
from rest api endpoint, config form, block or drupal route with params.
8. Menu link is provided for accessibility.

Note: The rest resource is set up for authenticated users using cookies, in order to test the rest resource,
log in in your drupal site or if using tools like Postman get you session cookie from Drupal and place it for your domain.

## How to install

1. Clone this repo in your custom/modules with the name "fuel_calculator"
2. Run drush en -y fuel_calculator or enable from the interface.


Final note: This is the first version of the module and a POC. Refactoring may be recommended for future development.
