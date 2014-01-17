<?php
/**
 * PHPUnit test cases to accompany the Urban_Airship class
 */



require_once(dirname(__FILE__).'/../../../classes/urban_airship.class.php');



/**
 * Class to test the core functionality of the Urban_Airship class
 */
class UrbanAirshipCoreTest extends PHPUnit_Framework_TestCase
{

	/**
	 * Create and check inheritance and of the Urban_Airship class
	 * @return void
	 */
	public function testCreateObject()
	{
		$class = new Urban_Airship();
        $this->assertInstanceOf('Urban_Airship', $class, 'object does not report to be an Urban_Airship class');
		$this->assertInstanceOf('Mobile_Push', $class, 'object does not report to be a Mobile_Push interface');

	}


}
?>