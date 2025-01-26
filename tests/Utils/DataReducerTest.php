<?php

namespace PhelixJuma\GUIFlow\Tests\Utils;

use PhelixJuma\GUIFlow\Actions\FunctionAction;
use PhelixJuma\GUIFlow\Utils\ConfigurationValidator;
use PhelixJuma\GUIFlow\Utils\DataJoiner;
use PhelixJuma\GUIFlow\Utils\DataReducer;
use PhelixJuma\GUIFlow\Utils\Filter;
use PHPUnit\Framework\TestCase;

class DataReducerTest extends TestCase
{

    public function _testReduceFunction()
    {
        $data = ["Shop",'Deli',"Deli","Butchery", "Shop"];

//        $dataJReducer = new DataReducer($data, "modal_value", ['priority' => ['Deli' => 1, 'Shop' => 2], "default" => 'Butchery']);
        $dataJReducer = new DataReducer($data, "get_item_at_index", 1);

        $value = $dataJReducer->reduce();

        //print_r($value);

        $expectedData = "";

        //print "\n Reduced value: $value \n";

       // $this->assertEquals($value, $expectedData);
    }

    public function _testMatchAndExcludeReducer()
    {

        $data = [
            ["id" => 1, "name" => "VELVEX TOILET TISSUE EXTRA WHITE 10s UNWRAPPED"],
            ["id" => 2, "name" => "VELVEX TOILET TISSUE EXTRA WHITE 10s WRAPPED"],
            ["id" => 3, "name" => "VELVEX TOILET TISSUE EXTRA WHITE 40s WRAPPED"],
            ["id" => 4, "name" => "VELVEX TOILET TISSUE EXTRA WHITE 4s UNWRAPPED"],
            ["id" => 5, "name" => "VELVEX TOILET TISSUE PRINTED 3PLY 4s UNWRAPPED"],
            ["id" => 6, "name" => "VELVEX TOILET TISSUE PRINTED 3PLY 9s UNWRAPPED"],
            ["id" => 7, "name" => "VELVEX TOILET TISSUE PRINTED PINK 10s UNWRAPPED"],
            ["id" => 8, "name" => "VELVEX TOILET TISSUE PRINTED PINK 40s WRAPPED"],
            ["id" => 9, "name" => "VELVEX TOILET TISSUE PRINTED PINK 4s UNWRAPPED"],
            ["id" => 10, "name" => "VELVEX TOILET TISSUE WHITE 10s UNWRAPPED"],
            ["id" => 11, "name" => "VELVEX TOILET TISSUE WHITE 10s WRAPPED"],
        ];

        $dataJReducer = new DataReducer($data, "match_and_exclude_reducer", "name", ["WRAPPED", "UNWRAPPED"], ["WRAPPED"]);

        $value = $dataJReducer->reduce();

        print_r($value);

        $expectedData = "";

        //print "\n Reduced value: $value \n";

        // $this->assertEquals($value, $expectedData);
    }

}
