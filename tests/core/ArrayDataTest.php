<?php

class ArrayDataTest extends SapphireTest {

	function testViewabledataItemsInsideArraydataArePreserved() {
		/* ViewableData objects will be preserved, but other objects will be converted */
		$arrayData = new ArrayData(array(
			"A" => new Varchar("A"),
			"B" => new stdClass(),
		));
		$this->assertEquals("Varchar", get_class($arrayData->A));
		$this->assertEquals("ArrayData", get_class($arrayData->B));
	}
      
	function testWrappingANonEmptyObjectWorks() {
		$object = new ArrayDataTest_NonEmptyObject();
		$this->assertTrue(is_object($object));

		$arrayData = new ArrayData($object);

		$this->assertEquals("Apple", $arrayData->getField('a'));
		$this->assertEquals("Banana", $arrayData->getField('b'));
		$this->assertFalse($arrayData->hasField('c'));
	}

	function testWrappingAnEmptyObjectWorks() {
		$object = (object) array();
		$this->assertTrue(is_object($object));

		$arrayData = new ArrayData($object);

		$this->assertEquals(null, $arrayData->TotalItems()); // (tobych) Shouldn't we get 0?
	}

	function testWrappingAnAssociativeArrayWorks() {
		$array = array("A" => "Alpha", "B" => "Beta");
		$this->assertTrue(ArrayLib::is_associative($array));

		$arrayData = new ArrayData($array);

		$this->assertTrue($arrayData->hasField("A"));
		$this->assertEquals("Alpha", $arrayData->getField("A"));
		$this->assertEquals("Beta", $arrayData->getField("B"));
	}

	function testWrappingAnEmptyArrayWorks() {
		$arrayData = new ArrayData(array());

		$this->assertEquals(null, $arrayData->TotalItems()); // (tobych) Shouldn't we get 0?
	}

	function testRefusesToWrapAnIndexedArray() {
		$array = array(0 => "One", 1 => "Two");
		$this->assertFalse(ArrayLib::is_associative($array));

		/*
		 * Expect user_error() to be called below, if enabled
		 * (tobych) That should be an exception. Something like:
		 * $this->setExpectedException('InvalidArgumentException');
		 */

		// $arrayData = new ArrayData($array);
	}

	function testForTemplateWorks() {
		$arrayData = new ArrayData(array('b' => 'bunny'));
		$this->assertEquals("array (\n  'b' => 'bunny',\n)", $arrayData->forTemplate());
	}

	function testSetField() {
		$arrayData = new ArrayData(array());
		
		$arrayData->setField('d', 'Delta');
		
		$this->assertTrue($arrayData->hasField('d'));
		$this->assertEquals('Delta', $arrayData->getField('d'));
	}
	
	function testGetArray() {
		$originalDeprecation = Deprecation::dump_settings();
		Deprecation::notification_version('2.4');

		$array = array(
			'Foo' => 'Foo',
			'Bar' => 'Bar',
			'Baz' => 'Baz'
		);
		
		$arrayData = new ArrayData($array);
		
		$this->assertEquals($arrayData->getArray(), $array);

		Deprecation::restore_settings($originalDeprecation);
	}

	function testArrayToObject() {
		$arr = array("test1" => "result1","test2"=>"result2");
		$obj = ArrayData::array_to_object($arr);
		$objExpected = new stdClass();
		$objExpected->test1 = "result1";
		$objExpected->test2 = "result2";
		$this->assertEquals($obj,$objExpected, "Two objects match");
	}

}

class ArrayDataTest_NonEmptyObject {

	static $c = "Cucumber";

	public function __construct() {
		$this->a = "Apple";
		$this->b = "Banana";
	}

}


