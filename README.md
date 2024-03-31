# PHELIXJUMA's PHP DATA TRANSFORMER

This is a workflow-oriented data transformer implemented in PHP.


# REQUIREMENTS

* PHP >= 8
* justinrainbow/json-schema
* wyndow/fuzzywuzzy
* From v2.*, we have additional requirements:
  * [Swoole](https://github.com/swoole/swoole-src): This package only works after you have installed Swoole extension and
    enabled the extension in your php.ini configuration file.
  * [PHP DAG](https://github.com/jumaphelix/php-dag)  

# INSTALLATION

```
composer require phelixjuma/php-data-transformer
```

# USAGE

```php

$data = [
    'customer_name' => 'Naivas',
    'delivery_location' => 'Kilimani',
    'items' => [
        ['name' => 'Capon Chicken', 'quantity' => 2,'uom' => 'KGS', 'unit_price' => 100]
    ],
    "delivery_date" => "2023-09-04"
];

$config = json_decode('[{
    "rule": "Split orders for different brands",
    "skip": "0",
    "description": "",
    "stage": "split_orders",
    "dependencies": [],
    "condition": {
      "path": "items.*.matched_value.PrincipalCode",
      "operator": "exists"
    },
    "actions": [
      {
        "description": "",
        "stage": "split_items",
        "dependencies": [],
        "action": "function",
        "path": "",
        "function": "split",
        "args": {
          "split_path": "items",
          "criteria_path": "items.*.matched_value.PrincipalCode"
        }
      }
    ]
  }]');
  
class functionsClass {
    // class that defines some of the functions in the workflow eg split() function
}

$functionsObj = new functionClass();

$dataTransformer = new DataTransformer($config, $functionsObj);
$dataTransformer->transform($data, true); // set second parameter to true for parallel execution

print_r($data); // this will show the modified data

```
