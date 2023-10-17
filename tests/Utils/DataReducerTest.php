<?php

namespace PhelixJuma\DataTransformer\Tests\Utils;

use PhelixJuma\DataTransformer\Actions\FunctionAction;
use PhelixJuma\DataTransformer\Utils\ConfigurationValidator;
use PhelixJuma\DataTransformer\Utils\DataJoiner;
use PhelixJuma\DataTransformer\Utils\DataReducer;
use PhelixJuma\DataTransformer\Utils\Filter;
use PHPUnit\Framework\TestCase;

class DataReducerTest extends TestCase
{

    public function _testReduceFunction()
    {
        $data = ["Shop",'Deli',"Deli","Butchery", "Shop"];

        $dataJReducer = new DataReducer($data, "modal_value", ['priority' => ['Deli' => 1, 'Shop' => 2], "default" => 'Butchery']);

        $value = $dataJReducer->reduce();

        $expectedData = "";

        //print "\n Reduced value: $value \n";

        $this->assertEquals($value, $expectedData);
    }

}
