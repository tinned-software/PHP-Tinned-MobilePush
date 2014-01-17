<?php
/**
 * PHPUnit test cases to accompany the GCM class
 */



require_once(dirname(__FILE__).'/../../../classes/gcm.class.php');



/**
 * Class to test the core functionality of the GCM class
 */
class GcmCoreTest extends PHPUnit_Framework_TestCase
{

	/**
	 * Create and check inheritance and of the GCM class
	 * @return void
	 */
	public function testCreateObject()
	{
		$class = new GCM();
        $this->assertInstanceOf('GCM', $class, 'object does not report to be an GCM class');
		$this->assertInstanceOf('Mobile_Push', $class, 'object does not report to be a Mobile_Push interface');

	}


}
?>