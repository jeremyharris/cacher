<?php

App::import('Model', 'App');
App::import('Lib', 'Folder');
if (!class_exists('Cache')) {
	require LIBS . 'cache.php';
}

class CacheBehaviorTestCase extends CakeTestCase {

	var $fixtures = array('plugin.cacher.cache_data', 'plugin.cacher.cache_data2');

	function startTest() {
		$this->_cacheDisable = Configure::read('Cache.disable');
		$this->_originalCacheConfig = Cache::config('default');
		Configure::write('Cache.disable', false);
		$this->CacheData =& ClassRegistry::init('CacheData');
		$this->CacheData->Behaviors->attach('Cacher.Cache', array('clearOnDelete' => false, 'auto' => true));
		$ds = ConnectionManager::getDataSource('cache');
		// set up default cache config for tests
		Cache::config('default', array(
			'engine' => 'File',
			'duration' => '+6 hours',
			'prefix' => 'cacher_tests_',
			'path' => CACHE
		));
		Cache::clear(false, 'default');
	}

	function endTest() {			
		Configure::write('Cache.disable', $this->_cacheDisable);
		Cache::config('default', $this->_originalCacheConfig['settings']);
		unset($this->CacheData);
		ClassRegistry::flush();
	}

	function testChangeDurationOnTheFly() {
		$this->CacheData->Behaviors->attach('Cacher.Cache', array(
			'auto' => false
		));

		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			),
			'cache' => '+42 weeks'
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEqual($results, $expected);

		// test that it's pulling from the cache
		$this->CacheData->delete(1);
		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			),
			'cache' => true
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEqual($results, $expected);

		$ds = ConnectionManager::getDataSource('cache');
		$result = Cache::config('default');
		$this->assertEqual($result['settings']['duration'], strtotime('+42weeks') - strtotime('now'));
	}

	function testCacheOnTheFly() {
		$this->CacheData->Behaviors->attach('Cacher.Cache', array(
			'auto' => false
		));

		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			),
			'cache' => true
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEqual($results, $expected);

		// test that it's pulling from the cache
		$this->CacheData->delete(1);
		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			),
			'cache' => true
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEqual($results, $expected);
	}

	function testAuto() {
		$this->CacheData->Behaviors->attach('Cacher.Cache', array(
			'auto' => false
		));

		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			)
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEqual($results, $expected);

		// test that it's not pulling from the cache
		$this->CacheData->delete(1);
		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			)
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'Cache behavior'
		);
		$this->assertEqual($results, $expected);
		
		$this->CacheData->Behaviors->attach('Cacher.Cache', array(
			'auto' => true
		));
		
		// test that it's not pulling from the cache
		$this->CacheData->delete(2);
		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			),
			'cache' => false
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array();
		$this->assertEqual($results, $expected);
	}

	function testUseDifferentCacheEngine() {
		$this->CacheData->Behaviors->attach('Cacher.Cache', array(
			'duration' => '+1 days',
			'engine' => 'File',
			'clearOnDelete' => false
		));

		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			)
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEqual($results, $expected);

		// test that it's pulling from the cache
		$this->CacheData->delete(1);
		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			)
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEqual($results, $expected);
	}

	function testRememberCache() {
		$settings = Cache::config('default');
		$oldPath = $settings['settings']['path'];

		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			)
		));

		$settings = Cache::config();
		$result = $settings['settings']['path'];
		$this->assertEqual($result, $oldPath);
	}

	function testSetup() {
		$this->CacheData->Behaviors->attach('Cacher.Cache', array('duration' => '+1 days'));
		$this->assertTrue(in_array('cache', ConnectionManager::sourceList()));

		$this->assertEqual($this->CacheData->_useDbConfig, 'test_suite');
		$this->assertEqual($this->CacheData->useDbConfig, 'test_suite');
	}

	function testClearCache() {
		$this->CacheData->Behaviors->attach('Cacher.Cache', array('auto' => true));
		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			)
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEqual($results, $expected);
		
		$ds = ConnectionManager::getDataSource('cache');
		$this->CacheData->find('all', array('conditions' => array('CacheData.name LIKE' => '123')));
		$this->CacheData->find('all', array('conditions' => array('CacheData.name LIKE' => '456')));

		$settings = Cache::config('default');
		$results = glob($settings['settings']['path'].DS.$settings['settings']['prefix'].'*');
		$this->assertEqual(count($results), 3);

		// test clearing 1 cached query
		$results = $this->CacheData->clearCache(array('conditions' => array('CacheData.name LIKE' => '456')));
		$this->assertTrue($results);
		$settings = Cache::config('default');
		$results = glob($settings['settings']['path'].$settings['settings']['prefix'].'*');
		$this->assertEqual(count($results), 2);

		// test clearing all
		$this->assertTrue($this->CacheData->clearCache());
		$settings = Cache::config('default');
		$results = glob($settings['settings']['path'].$settings['settings']['prefix'].'*');
		$this->assertEqual(count($results), 0);
	}

	function testFind() {
		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			)
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEqual($results, $expected);

		// test that it's pulling from the cache
		$this->CacheData->delete(1);
		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			)
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEqual($results, $expected);
	}

	function testFindingOnMultipleDbConfigs() {
		$testSuite = ConnectionManager::getDataSource('test_suite');
		ConnectionManager::create('test1', $testSuite->config);
		ConnectionManager::create('test2', $testSuite->config);

		$CacheData1 = ClassRegistry::init('CacheData');
		$CacheData1->alias = 'CacheData1';
		$CacheData1->useDbConfig = 'test1';
		$CacheData1->Behaviors->attach('Cacher.Cache', array('auto' => true, 'clearOnDelete' => false));
		$CacheData2 = ClassRegistry::init('CacheData2');
		$CacheData2->alias = 'CacheData2';
		$CacheData2->useDbConfig = 'test2';
		$CacheData2->Behaviors->attach('Cacher.Cache', array('auto' => true, 'clearOnDelete' => false));

		$count1 = $CacheData1->find('count');
		$count2 = $CacheData2->find('count');
		$CacheData1->delete(1);
		$CacheData2->delete(1);
		$this->assertEqual($count1, $CacheData1->find('count'));
		$this->assertEqual($count2, $CacheData2->find('count'));

		$this->assertEqual($CacheData1->useDbConfig, 'test1');
		$this->assertEqual($CacheData2->useDbConfig, 'test2');
	}

	function testSave() {
		$data = array(
			'name' => 'Save me'
		);
		$this->CacheData->save($data);
		$results = $this->CacheData->read();
		$expected = 'Save me';
		$this->assertEqual($results['CacheData']['name'], 'Save me');
	}

	function testUpdate() {
		$data = $this->CacheData->read(null, 1);
		$data['CacheData']['name'] = 'Updated';
		$this->CacheData->save($data);
		$this->CacheData->id = 1;
		$this->assertEqual($this->CacheData->field('name'), 'Updated');
	}
}

?>
