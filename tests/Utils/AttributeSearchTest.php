<?php

namespace PhelixJuma\GUIFlow\Tests\Utils;

use PhelixJuma\GUIFlow\Utils\AttributeSearch\TreeSearch;
use PhelixJuma\GUIFlow\Utils\Utils;
use PHPUnit\Framework\TestCase;

class AttributeSearchTest extends TestCase
{

    public function _testAttributeSearch() {

        $data_json = '';

        $attributes = array(
            array(
                "name" => "brand_name",
                "description" => "Brand of the product",
                "type" => "string",
                "category" => "primary",
                "order" => 1
            ),
            array(
                "name" => "product_category",
                "description" => "Category of the product",
                "type" => "string",
                "category" => "primary",
                "order" => 2
            ),
            array(
                "name" => "product_type",
                "description" => "Type of the product",
                "type" => "string",
                "category" => "primary",
                "order" => 3
            ),
            array(
                "name" => "flavor",
                "description" => "Flavor of the product",
                "type" => "string",
                "category" => "primary",
                "order" => 4
            ),
            array(
                "name" => "pack_size",
                "description" => "Pack size of the product",
                "type" => "string",
                "category" => "primary",
                "order" => 5
            ),
            array(
                "name" => "is_value_pack",
                "description" => "Whether the product is a value pack",
                "type" => "string",
                "category" => "primary",
                "order" => 6
            ),
            array(
                "name" => "promotional_add_on_product",
                "description" => "Promotional add-on product",
                "type" => "string",
                "category" => "primary",
                "order" => 7
            )
        );


        $data = json_decode($data_json, true);

        $searchItem = "Hazelnut 4ltr";
        //$searchItem = "DAIRYLAND WHITE COMPOUND CHOCOLATE 4 X 2.5KGS";
        //$searchItem = "DAIRYLAND BUBBLEGUM 18 X 100ML";
        //$searchItem = "ORANGE CRUNCH 12 X 80G";
        //$searchItem = "Vanilla Choc Flakes 1Ltr with free 800ml";
        //$searchItem = "Java Strawberry 24 x 150gms";
        //$searchItem = "Dairyland Vanilla with Pods 12 X 100GMS";
        //$searchItem = "AMORE SALTED CARAMEL 1.5LTR";
        //$searchItem = "DAIRYLAND CHOCOLATE ALMOND AND RAISIN 12 X 80G";
        //$searchItem = "DAIRYLAND 100ML ICE CREAM CORNETS - HAZELNUT";

        $nodePathConfidenceCalculatorFunction = function($searchItem, $nodesAndBranchingOptions) {

            return json_decode('', true);
        };

        $extracted_entities = TreeSearch::extractMatchingAttributes($searchItem, $attributes, [], $data, $nodePathConfidenceCalculatorFunction);

        echo "\nExtracted Entities:\n";
        echo json_encode($extracted_entities, JSON_PRETTY_PRINT);

        //$this->assertEquals($mergedData, $expectedData);
    }

    public function _testAtteibuteExtractionFromCorpusFields() {

        $data_json = '';

        $attributes = array(
            array(
                "name" => "brand_name",
                "description" => "Brand of the product",
                "type" => "string",
                "category" => "primary",
                "order" => 1
            ),
            array(
                "name" => "product_category",
                "description" => "Category of the product",
                "type" => "string",
                "category" => "primary",
                "order" => 2
            ),
            array(
                "name" => "product_type",
                "description" => "Type of the product",
                "type" => "string",
                "category" => "primary",
                "order" => 3
            ),
            array(
                "name" => "flavor",
                "description" => "Flavor of the product",
                "type" => "string",
                "category" => "primary",
                "order" => 4
            ),
            array(
                "name" => "pack_size",
                "description" => "Pack size of the product",
                "type" => "string",
                "category" => "primary",
                "order" => 5
            ),
            array(
                "name" => "is_value_pack",
                "description" => "Whether the product is a value pack",
                "type" => "string",
                "category" => "primary",
                "order" => 6
            ),
            array(
                "name" => "promotional_add_on_product",
                "description" => "Promotional add-on product",
                "type" => "string",
                "category" => "primary",
                "order" => 7
            )
        );


        $corpus = json_decode('[{"no":"FPRDF31A","description":"Dairyland  Chocolate Ripple & Vanilla 500ml","baseUnitOfMeasure":null,"salesUnitOfMeasure":"PCS","type":"Inventory","blocked":"","inventoryPostingGroup":"FINISHED","inventory":"126","Active":"TRUE","":null,"brand_name":"DAIRYLAND","product_category":"ICE CREAM","product_type":"RIPPLE","flavor":"CHOCOLATE","pack_size":"500 ML","is_value_pack":"YES","promotional_add_on_product":"","InvoicingGroup":null,"search_field":"DAIRYLAND  CHOCOLATE RIPPLE & VANILLA 500ML","brand_classification":"Chocolate","pack_size_or_dimension":"500ML","packaging_details":{"unit_count":1,"unit_size":"500","unit_measurement":"ml"}}]', true);


        $extracted_entities = TreeSearch::getAttributesFromCorpusFields($corpus, $attributes);

        echo "\nExtracted Entities:\n";
        echo json_encode($extracted_entities, JSON_PRETTY_PRINT);

        //$this->assertEquals($mergedData, $expectedData);
    }

}
