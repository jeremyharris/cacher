<?php

App::import('Model', 'App');
if (!class_exists('Cache')) {
	require LIBS . 'cache.php';
}

class CacheSourceTestCase extends CakeTestCase {

	var $fixtures = array('plugin.cacher.cache_data', 'plugin.cacher.cache_data2');

	function startTest() {
		$this->_cacheDisable = Configure::read('Cache.disable');
		$this->_originalCacheConfig = Cache::config('default');
		Configure::write('Cache.disable', false);
		$this->CacheData =& ClassRegistry::init('CacheData');
		if (!in_array('cache', ConnectionManager::sourceList())) {
			 ConnectionManager::create('cache', array(
				'original' => $this->CacheData->useDbConfig,
				'datasource' => 'Cacher.cache'
			));
		}
		$this->dataSource =& ConnectionManager::getDataSource('cache');
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
		Cache::clear(false, 'default');
		Configure::write('Cache.disable', $this->_cacheDisable);
		Cache::config('default', $this->_originalCacheConfig['settings']);
		unset($this->CacheData);
		unset($this->dataSource);
		ClassRegistry::flush();
	}

	function testMultipleDbConfigs() {
		$testSuite = ConnectionManager::getDataSource('test_suite');
		ConnectionManager::create('test1', $testSuite->config);
		ConnectionManager::create('test2', $testSuite->config);

		$CacheData1 = ClassRegistry::init('CacheData');
		$CacheData1->_useDbConfig = 'test1';
		$CacheData1->useDbConfig = 'cache';
		$CacheData1->alias = 'CacheData1';
		$CacheData2 = ClassRegistry::init('CacheData2');
		$CacheData2->_useDbConfig = 'test2';
		$CacheData2->useDbConfig = 'cache';
		$CacheData2->alias = 'CacheData2';

		$conditions = array('conditions' => array('CacheData1.id' => 1));
		$this->dataSource->read($CacheData1, $conditions);
		$key = $this->dataSource->_key($CacheData1, $conditions);
		$this->assertTrue(strpos($key, 'test1') >= 0);
		$this->assertTrue(Cache::read($key, 'default'));
		$this->assertEqual($CacheData1->useDbConfig, 'test1');

		// read from ds1, ds2, then read cache
		$conditions = array('conditions' => array('CacheData1.id' => 1));
		$this->dataSource->read($CacheData1, $conditions);
		$key1 = $this->dataSource->_key($CacheData1, $conditions);		
		$conditions = array('conditions' => array('CacheData2.id' => 1));
		$this->dataSource->read($CacheData2, $conditions);
		$key2 = $this->dataSource->_key($CacheData2, $conditions);
		// test that it wrote to the correct cache key (based on the datasource)
		$this->assertTrue(strpos($key1, 'test1') >= 0);
		$this->assertTrue(strpos($key2, 'test2') >= 0);
		$this->assertTrue(Cache::read($key1, 'default'));
		$this->assertTrue(Cache::read($key2, 'default'));
		// and make sure it reset the datasource
		$this->assertEqual($CacheData1->useDbConfig, 'test1');
		$this->assertEqual($CacheData2->useDbConfig, 'test2');
	}

	function testUseExistingConfig() {
		Cache::config('cacheTest', array(
			'engine' => 'File',
			'duration' => '+1 days',
			'prefix' => 'cacher_tests_different_config_',
			'path' => CACHE
		));

		ConnectionManager::create('newCache', array(
			'config' => 'cacheTest',
			'original' => $this->CacheData->useDbConfig,
			'datasource' => 'Cacher.cache'
		));
		$this->dataSource =& ConnectionManager::getDataSource('newCache');

		$key = $this->dataSource->_key($this->CacheData, array());
		
		$this->dataSource->read($this->CacheData, array());

		$result = Cache::config('cacheTest');
		$result = $result['settings']['duration'];
		$expected = strtotime('+1 days') - strtotime('now');
		$this->assertEqual($result, $expected);

		$map = Cache::read('map', 'cacheTest');
		$results = count($map[$this->dataSource->source->configKeyName][$this->CacheData->alias]);
		$this->assertEqual($results, 1);

		$results = Cache::read($key, 'cacheTest');
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEqual($results, $expected);

		Cache::clear(false, 'cacheTest');
	}

	function testRead() {
		$conditions = array(
			'conditions' => array(
				'CacheData.id' => 1
			)
		);
		$key = $this->dataSource->_key($this->CacheData, $conditions);
		$results = $this->dataSource->read($this->CacheData, $conditions);
		// test that we get the correct data
		$this->assertEqual(Set::extract('/CacheData/name', $results), array('A Cached Thing'));
		// test that we wrote to the cache
		$this->assertTrue(Cache::read($key, 'default'));
		// test that it wrote to the map
		$map = Cache::read('map', 'default');
		$this->assertTrue(in_array($key, array_values($map[$this->dataSource->source->configKeyName][$this->CacheData->alias])));
		// test that the cache results match the results
		$this->assertEqual(Cache::read($key, 'default'), $results);
		
		// test multiple cached results
		$moreConditions = array(
			'conditions' => array(
				'CacheData.name' => 'non-existent'
			)
		);
		$key2 = $this->dataSource->_key($this->CacheData, $conditions);
		$results = $this->dataSource->read($this->CacheData, $moreConditions);
		$this->assertEqual($results, array());

		// delete from the db and make sure we read from the cache
		$this->CacheData->delete(1);
		$results = $this->dataSource->read($this->CacheData, $conditions);
		$this->assertEqual(Set::extract('/CacheData/name', $results), array('A Cached Thing'));

		// make sure both are in the map
		$map = Cache::read('map', 'default');
		$results = count($map[$this->dataSource->source->configKeyName][$this->CacheData->alias]);
		$this->assertEqual($results, 2);
		// make sure both exist
		$this->assertTrue(Cache::read($key, 'default') !== false);
		$this->assertTrue(Cache::read($key2, 'default') !== false);

		Cache::clear(false, 'default');
	}

	function testClearModelCache() {
		$conditions = array(
			'conditions' => array(
				'CacheData.id' => 1
			)
		);
		$results = $this->dataSource->read($this->CacheData, $conditions);

		$moreConditions = array(
			'conditions' => array(
				'CacheData.name' => 'non-existent'
			)
		);
		$results = $this->dataSource->read($this->CacheData, $moreConditions);

		$map = Cache::read('map', 'default');
		$results = count($map[$this->dataSource->source->configKeyName][$this->CacheData->alias]);
		$this->assertEqual($results, 2);

		// test that clearing only clears this model's data
		$this->CacheData->alias = 'DifferentModel';
		$this->dataSource->clearModelCache($this->CacheData);
		$map = Cache::read('map', 'default');
		$results = count($map[$this->dataSource->source->configKeyName]['CacheData']);
		$this->assertEqual($results, 2);

		$this->CacheData->alias = 'CacheData';
		$this->dataSource->clearModelCache($this->CacheData);
		$map = Cache::read('map', 'default');
		$results = count($map[$this->dataSource->source->configKeyName][$this->CacheData->alias]);
		$this->assertEqual($results, 0);
	}

	function testHash() {
		$query = array(
			'conditions' => array(
				'SomeModel.name' => 'CakePHP'
			)
		);
		$this->assertTrue(is_string($this->dataSource->_key($this->CacheData, $query)));

		$anotherQuery = array(
			'conditions' => array(
				'SomeModel.name' => 'CakePHP'
			),
			'order' => 'SomeModel.name'
		);
		$this->assertNotEqual($this->dataSource->_key($this->CacheData, $anotherQuery), $this->dataSource->_key($this->CacheData, $query));
	}
	
	function testMap() {
		$this->dataSource->_map($this->CacheData, 'test');
		$results = Cache::read('map', 'default');
		$expected = array(
			$this->dataSource->source->configKeyName => array(
				$this->CacheData->alias => array(
					'test'
				)
			)
		);
		$this->assertEqual($results, $expected);
		
		$this->dataSource->_map($this->CacheData, 'another_key');
		$results = Cache::read('map', 'default');
		$expected = array(
			$this->dataSource->source->configKeyName => array(
				$this->CacheData->alias => array(
					'test',
					'another_key'
				)
			)
		);
		$this->assertEqual($results, $expected);
	}
}

?>