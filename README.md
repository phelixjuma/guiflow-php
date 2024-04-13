# GUIFlow 

GUIFlow is a no-code workflow builder. 

Check the [documentation](docs/documentation.md) for more details


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
composer require phelixjuma/guiflow-php
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
        "stage": "split_items",
        "description": "",
        "dependencies": [],
        "skip":"0",
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
  
class userDefinedFunctionsClass {
    // class that defines all user defined functions outside the package ecosystem. 
}

$udfObj = new userDefinedFunctionsClass();

$workflow = new Workflow($config, $udfObj);
$workflow->run($data, true); // set second parameter to true for parallel execution        

print_r($data); // this will show the modified data

```
