<?php

App::uses('App', 'Model');
App::uses('ModelBehavior', 'Model');
App::uses('CakeResponse', 'Network');
App::uses('CakeRequest', 'Network');
App::uses('Controller', 'Controller');
App::uses('Paginator', 'Controller/Component');
App::uses('Folder', 'Utility');
App::uses('Cache', 'Cache');

class TestCacherController extends Controller {

	public $uses = false;

	public function index() {
		$this->CacheData = ClassRegistry::init('CacheData');
		$this->CacheData->Behaviors->attach('Cacher.Cache', array('clearOnDelete' => false, 'config' => 'default'));

		$this->paginate = array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			),
			'cacher' => '+1 week',
			'limit' => 1
		);
		$this->set('cacheData', $this->paginate($this->CacheData));
	}

	public function albums($artistId = null) {
		$this->Album = ClassRegistry::init('Album');
		$this->Album->bindModel(array(
			'belongsTo' => array(
				'Artist'
			)
		));
		$this->Album->Behaviors->attach('Cacher.Cache', array('clearOnDelete' => false, 'config' => 'default'));

		$conditions = array();

		if (!empty($artistId)) {
			$conditions = array(
				'Album.artist_id' => $artistId
			);
		}

		$this->paginate = array(
			'contain' => array(
				'Artist'
			),
			'conditions' => $conditions,
			'cacher' => '+1 week',
			'limit' => 1
		);

		$this->set('albums', $this->paginate($this->Album));
	}

}

/**
 * OtherBehavior to call a datasource function if the Cacher.cache source is
 * being used
 */
class OtherBehavior extends ModelBehavior {

	/**
	 * Uses the model datasource _after_ it was set to Cacher.cache
	 *
	 * @param Model $Model
	 * @param array $queryData
	 * @return string
	 */
	function beforeFind(Model $Model, $queryData = array()) {
		$this->_dbconfig = $Model->useDbConfig;
		$ds = $Model->getDataSource($this->_dbconfig);
		$this->_dbfields = $ds->fields($Model);
		$this->_queryData = $queryData;
		$queryData['conditions']['CacheData.name LIKE'] = '%thing%';
		return $queryData;
	}

}

class CacheBehaviorTestCase extends ControllerTestCase {

	var $fixtures = array(
		'plugin.cacher.album',
		'plugin.cacher.artist',
		'plugin.cacher.cache_data',
		'plugin.cacher.cache_data2'
	);

	function setUp() {
		parent::setUp();

		Configure::write('Cache.disable', false);
		// set up default cache config for tests
		Cache::config('default', array(
			'engine' => 'File',
			'duration' => '+6 hours',
			'prefix' => 'cacher_tests_',
			'path' => CACHE
		));

		$this->CacheData = ClassRegistry::init('CacheData');
		$this->CacheData->Behaviors->attach('Cacher.Cache', array('clearOnDelete' => false, 'auto' => true, 'config' => 'default'));
	}

	function tearDown() {
		Cache::clear(false, 'default');
		ConnectionManager::drop('cacher');
		unset($this->CacheData);
		parent::tearDown();
	}

	function testCachePaginateWithContain() {
		$this->testAction('/test_cacher/albums/page:1');

		$result = Set::extract('/Artist/name', $this->vars['albums']);
		$expected = array(
			'Protest The Hero'
		);
		$this->assertEquals($expected, $result);

		$this->testAction('/test_cacher/albums/page:2');

		$result = Set::extract('/Artist/name', $this->vars['albums']);
		$expected = array(
			'The Dear Hunter'
		);
		$this->assertEquals($expected, $result);

		ClassRegistry::init('Album')->delete(1);

		$this->testAction('/test_cacher/albums/page:1');

		$result = Set::extract('/Artist/name', $this->vars['albums']);
		$expected = array(
			'Protest The Hero'
		);
		$this->assertEquals($expected, $result);

		$this->testAction('/test_cacher/albums/2/page:1');

		$result = Set::extract('/Artist/name', $this->vars['albums']);
		$expected = array(
			'The Dear Hunter',
		);
		$this->assertEquals($expected, $result);

		ClassRegistry::init('Album')->deleteAll(array(
			'artist_id' => 2
		), false);

		$this->testAction('/test_cacher/albums/2/page:1');

		$result = Set::extract('/Artist/name', $this->vars['albums']);
		$expected = array(
			'The Dear Hunter',
		);
		$this->assertEquals($expected, $result);

		$this->testAction('/test_cacher/albums/2/page:1/sort:name/direction:asc');

		$result = Set::extract('/Artist/name', $this->vars['albums']);
		$expected = array();
		$this->assertEquals($expected, $result);
	}

	function testCachePaginate() {
		$this->testAction('/test_cacher/index/page:1');

		$result = Set::extract('/CacheData/name', $this->vars['cacheData']);
		$expected = array(
			'A Cached Thing'
		);
		$this->assertEquals($expected, $result);

		$this->testAction('/test_cacher/index/page:2');

		$result = Set::extract('/CacheData/name', $this->vars['cacheData']);
		$expected = array(
			'Cache behavior'
		);
		$this->assertEquals($expected, $result);

		$this->CacheData->delete(1);
		$this->CacheData->delete(2);

		$this->testAction('/test_cacher/index/page:1');

		$result = Set::extract('/CacheData/name', $this->vars['cacheData']);
		$expected = array(
			'A Cached Thing'
		);
		$this->assertEquals($expected, $result);

		$this->testAction('/test_cacher/index/page:1/sort:name/direction:asc');

		$result = Set::extract('/CacheData/name', $this->vars['cacheData']);
		$expected = array();
		$this->assertEquals($expected, $result);
	}

	function testCacheDisable() {
		Cache::drop('config');
		Configure::write('Cache.disable', true);

		$this->CacheData->Behaviors->attach('Other');

		$this->CacheData->find('all', array(
			'cacher' => 'keyexists'
		));

		$result = $this->CacheData->Behaviors->Other->_dbconfig;
		$expected = 'test';
		$this->assertEquals($result, $expected);

		// make sure it doesn't hit the beforeFind callback
		$result = $this->CacheData->Behaviors->Other->_queryData['cacher'];
		$expected = 'keyexists';
		$this->assertEquals($result, $expected);
	}

	function testMissingDatasourceMethods() {
		$this->CacheData->Behaviors->attach('Other');

		$results = $this->CacheData->find('all');
		$this->assertEquals($this->CacheData->Behaviors->Other->_dbconfig, 'cacher');
		$this->assertEquals(count($this->CacheData->Behaviors->Other->_dbfields), 5);
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing'
		);
		$this->assertEquals($results, $expected);

		$this->CacheData->Behaviors->detach('Other');
	}

	function testChangeDurationOnTheFly() {
		$this->CacheData->Behaviors->attach('Cacher.Cache', array(
			'auto' => false
		));

		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			),
			'cacher' => '+42 weeks'
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEquals($results, $expected);

		// test that it's pulling from the cache
		$this->CacheData->delete(1);
		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			),
			'cacher' => true
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEquals($results, $expected);

		$ds = ConnectionManager::getDataSource('cacher');
		$result = Cache::config('default');
		$this->assertEquals($result['settings']['duration'], strtotime('+42weeks') - strtotime('now'));
	}

	function testCacheOnTheFly() {
		$this->CacheData->Behaviors->attach('Cacher.Cache', array(
			'auto' => false
		));

		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			),
			'cacher' => true
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEquals($results, $expected);

		// test that it's pulling from the cache
		$this->CacheData->delete(1);
		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%cache%'
			),
			'cacher' => true
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array(
			'A Cached Thing',
			'Cache behavior'
		);
		$this->assertEquals($results, $expected);
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
		$this->assertEquals($results, $expected);

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
		$this->assertEquals($results, $expected);

		$this->CacheData->Behaviors->attach('Cacher.Cache', array(
			'auto' => true
		));
		// create cached value
		$this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%behav%'
			)
		));

		// test that it's not pulling from the cache
		$this->CacheData->delete(2);
		$results = $this->CacheData->find('all', array(
			'conditions' => array(
				'CacheData.name LIKE' => '%behav%'
			),
			'cacher' => false
		));
		$results = Set::extract('/CacheData/name', $results);
		$expected = array();
		$this->assertEquals($results, $expected);
	}

	function testGzip() {
		// test gzip as a Cacher config param
		$this->CacheData->Behaviors->attach('Cacher.Cache', array(
			'auto' => true,
			'gzip' => true
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
		$this->assertEquals($results, $expected);

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
		$this->assertEquals($results, $expected);
	}

	function testUseDifferentCacheConfig() {
		Cache::config('cacheTest', array(
			'engine' => 'File',
			'duration' => '+20 minutes',
			'path' => CACHE,
			'prefix' => 'different_file_engine_'
		));
		$this->CacheData->Behaviors->attach('Cacher.Cache', array(
			'clearOnDelete' => false,
			'config' => 'cacheTest'
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
		$this->assertEquals($results, $expected);

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
		$this->assertEquals($results, $expected);

		$ds = ConnectionManager::getDataSource('cacher');
		$this->assertEquals($ds->config['config'], 'cacheTest');

		$map = Cache::read('map', 'cacheTest');
		$this->assertTrue($map !== false);
		$this->assertTrue(isset($map[$ds->source->configKeyName][$this->CacheData->alias][0]));
		$cache = Cache::read($map[$ds->source->configKeyName][$this->CacheData->alias][0], 'cacheTest');
		$this->assertTrue($cache !== false);

		Cache::clear(false, 'cacheTest');
	}

	function testUseDifferentCacheEngine() {
		$this->skipIf(!class_exists('Memcache'), 'Memcache is not installed, skipping test');

		Cache::config('cacherMemcache', array(
			'duration' => '+1 days',
			'engine' => 'Memcache',
			'prefix' => Inflector::slug(APP_DIR) . '_cacher_test_',
		));

		$this->CacheData->Behaviors->attach('Cacher.Cache', array(
			'config' => 'cacherMemcache',
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
		$this->assertEquals($results, $expected);

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
		$this->assertEquals($results, $expected);

		$ds = ConnectionManager::getDataSource('cacher');
		$this->assertEquals($ds->config['config'], 'cacherMemcache');

		$map = Cache::read('map', 'cacherMemcache');
		$this->assertTrue($map !== false);
		$this->assertTrue(isset($map[$ds->source->configKeyName][$this->CacheData->alias][0]));
		$cache = Cache::read($map[$ds->source->configKeyName][$this->CacheData->alias][0], 'cacherMemcache');
		$this->assertTrue($cache !== false);

		$this->CacheData->clearCache();

		$map = Cache::read('map', 'cacherMemcache');
		$this->assertTrue(empty($map[$ds->source->configKeyName][$this->CacheData->alias]));

		Cache::drop('cacherMemcache');
	}

	function testSetup() {
		$this->CacheData->Behaviors->attach('Cacher.Cache', array('duration' => '+1 days'));
		$this->assertTrue(in_array('cacher', ConnectionManager::sourceList()));

		$this->assertEquals($this->CacheData->_useDbConfig, 'test');
		$this->assertEquals($this->CacheData->useDbConfig, 'test');
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
		$this->assertEquals($results, $expected);

		$ds = ConnectionManager::getDataSource('cacher');
		$this->CacheData->find('all', array('conditions' => array('CacheData.name LIKE' => '123')));
		$this->CacheData->find('all', array('conditions' => array('CacheData.name LIKE' => '456')));

		$map = Cache::read('map', 'default');
		$results = count($map[$ds->source->configKeyName][$this->CacheData->alias]);
		$this->assertEquals($results, 3);
		foreach ($map[$ds->source->configKeyName][$this->CacheData->alias] as $key) {
			$this->assertTrue(Cache::read($key, 'default') !== false, 'Failed checking key '.$key);
		}

		// test clearing 1 cached query
		$this->CacheData->clearCache(array('conditions' => array('CacheData.name LIKE' => '456')));
		$map = Cache::read('map', 'default');
		$results = count($map[$ds->source->configKeyName][$this->CacheData->alias]);
		$this->assertEquals($results, 2);
		foreach ($map[$ds->source->configKeyName][$this->CacheData->alias] as $key) {
			$this->assertTrue(Cache::read($key, 'default') !== false, 'Failed checking key '.$key);
		}

		// test clearing all
		$this->CacheData->clearCache();
		$map = Cache::read('map', 'default');
		$results = count($map[$ds->source->configKeyName][$this->CacheData->alias]);
		$this->assertEquals($results, 0);
		foreach ($map[$ds->source->configKeyName][$this->CacheData->alias] as $key) {
			$this->assertTrue(Cache::read($key, 'default') !== false, 'Failed checking key '.$key);
		}
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
		$this->assertEquals($results, $expected);

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
		$this->assertEquals($results, $expected);
	}

	function testFindingOnMultipleDbConfigs() {
		$testSuite = ConnectionManager::getDataSource('test');
		ConnectionManager::create('cacher_test1', $testSuite->config);
		ConnectionManager::create('cacher_test2', $testSuite->config);

		$CacheData1 = ClassRegistry::init('CacheData');
		$CacheData1->alias = 'CacheData1';
		$CacheData1->useDbConfig = 'cacher_test1';
		$CacheData1->Behaviors->attach('Cacher.Cache', array('auto' => true, 'clearOnDelete' => false));
		$CacheData2 = ClassRegistry::init('CacheData2');
		$CacheData2->alias = 'CacheData2';
		$CacheData2->useDbConfig = 'cacher_test2';
		$CacheData2->Behaviors->attach('Cacher.Cache', array('auto' => true, 'clearOnDelete' => false));

		$count1 = $CacheData1->find('count');
		$count2 = $CacheData2->find('count');
		$CacheData1->delete(1);
		$CacheData2->delete(1);
		$this->assertEquals($count1, $CacheData1->find('count'));
		$this->assertEquals($count2, $CacheData2->find('count'));

		$this->assertEquals($CacheData1->useDbConfig, 'cacher_test1');
		$this->assertEquals($CacheData2->useDbConfig, 'cacher_test2');

		ConnectionManager::drop('cacher_test1');
		ConnectionManager::drop('cacher_test2');
	}

	function testSave() {
		$data = array(
			'name' => 'Save me'
		);
		$this->CacheData->save($data);
		$results = $this->CacheData->read();
		$expected = 'Save me';
		$this->assertEquals($results['CacheData']['name'], 'Save me');
	}

	function testUpdate() {
		$data = $this->CacheData->read(null, 1);
		$data['CacheData']['name'] = 'Updated';
		$this->CacheData->save($data);
		$this->CacheData->id = 1;
		$this->assertEquals($this->CacheData->field('name'), 'Updated');
	}
}
