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

    public function testReduceFunction()
    {
        $data = ["Shop",'Deli',"Deli","Butchery", "Shop"];

        //$dataJReducer = new DataReducer($data, "modal_value", ['priority' => ['Deli' => 1, 'Shop' => 2], "default" => 'Butchery']);
        $dataJReducer = new DataReducer($data, "get_item_at_index", 1);

        $value = $dataJReducer->reduce();

        print_r($value);

        $expectedData = "";

        //print "\n Reduced value: $value \n";

       // $this->assertEquals($value, $expectedData);
    }

}
