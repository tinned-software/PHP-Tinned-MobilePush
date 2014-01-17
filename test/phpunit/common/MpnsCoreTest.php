<?php
/**
 * PHPUnit test cases to accompany the MPNS class
 */



require_once(dirname(__FILE__).'/../../../classes/mpns.class.php');



/**
 * Class to test the core functionality of the MPNS class
 */
class MpnsCoreTest extends PHPUnit_Framework_TestCase
{

	/**
	 * Create and check inheritance and of the MPNS class
	 * @return void
	 */
	public function testCreateObject()
	{
		$class = new MPNS();
        $this->assertInstanceOf('MPNS', $class, 'object does not report to be an MPNS class');
		$this->assertInstanceOf('Mobile_Push', $class, 'object does not report to be a Mobile_Push interface');

	}


}
?>