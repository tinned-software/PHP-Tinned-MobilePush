<?php
/**
 * PHPUnit test cases to accompany the PyAPNS class
 */



require_once(dirname(__FILE__).'/../../../classes/pyapns.class.php');



/**
 * Class to test the core functionality of the PyAPNS class
 */
class PyApnsCoreTest extends PHPUnit_Framework_TestCase
{

	/**
	 * Create and check inheritance and of the PyAPNS class
	 * @return void
	 */
	public function testCreateObject()
	{
		$class = new PyAPNS();
        $this->assertInstanceOf('PyAPNS', $class, 'object does not report to be an PyAPNS class');
		$this->assertInstanceOf('Mobile_Push', $class, 'object does not report to be a Mobile_Push interface');

	}


}
?>