<?php
/**
 * PHPUnit test cases to accompany the APNS class
 */



require_once(dirname(__FILE__).'/../../../classes/apns.class.php');



/**
 * Class to test the core functionality of the APNS class
 */
class ApnsCoreTest extends PHPUnit_Framework_TestCase
{

	/**
	 * Create and check inheritance and of the APNS class
	 * @return void
	 */
	public function testCreateObject()
	{
		$class = new APNS();
		$this->assertInstanceOf('APNS', $class, 'object does not report to be an APNS class');
		$this->assertInstanceOf('Mobile_Push', $class, 'object does not report to be a Mobile_Push interface');
	
		ereg_split();

	}


}
?>