<?php

namespace PhelixJuma\GUIFlow\Tests\Utils;

use PhelixJuma\GUIFlow\Actions\FunctionAction;
use PhelixJuma\GUIFlow\Utils\ArrayDiff;
use PhelixJuma\GUIFlow\Utils\ConfigurationValidator;
use PhelixJuma\GUIFlow\Utils\DataJoiner;
use PhelixJuma\GUIFlow\Utils\Filter;
use PHPUnit\Framework\TestCase;

class ArrayDiffTest extends TestCase
{

    public function _testArrayDiff()
    {

        $originalArray = [
            [
                'item_code' => 'ITEM001',
                'item_quantity' => 10,
                'item_description' => 'Product A',
                'warehouse_code' => 'WH01'
            ],
            [
                'item_code' => 'ITEM002',
                'item_quantity' => 20,
                'item_description' => 'Product B',
                'warehouse_code' => 'WH01'
            ],
            [
                'item_code' => 'ITEM003',
                'item_quantity' => 30,
                'item_description' => 'Product C',
                'warehouse_code' => 'WH01'
            ],
        ];

        $modifiedArray = [
            [
                'item_code' => 'ITEM002', // Swapped with ITEM001
                'item_quantity' => 20,
                'item_description' => 'Product B',
                'warehouse_code' => 'WH01'
            ],
            [
                'item_code' => 'ITEM001', // Swapped with ITEM002
                'item_quantity' => 15, // Quantity changed
                'item_description' => 'Product A',
                'warehouse_code' => 'WH01'
            ],
            [
                'item_code' => 'ITEM003',
                'item_quantity' => 30,
                'item_description' => 'Product C',
                'warehouse_code' => 'WH01'
            ],
        ];

        $primaryKey = 'item_code';
        $searchKey = 'item_description';
        $arrayComparer = new ArrayDiff($primaryKey, $searchKey);

        $differences = $arrayComparer->compareArrays($originalArray, $modifiedArray);

        echo json_encode($differences, JSON_PRETTY_PRINT);

        //$this->assertEquals($mergedData, $expectedData);
    }

}
