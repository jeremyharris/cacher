<?php
/**
 * All Cacher plugin tests
 */
class AllCacherTest extends CakeTestCase {

/**
 * Suite define the tests for this plugin
 *
 * @return void
 */
	public static function suite() {
		$suite = new CakeTestSuite('All Cacher test');

		$path = CakePlugin::path('Cacher') . 'Test' . DS . 'Case' . DS;
		$suite->addTestDirectoryRecursive($path);

		return $suite;
	}

}
