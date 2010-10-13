<?php
/**
 * Cache data source class.
 *
 * @copyright     Copyright 2010, Jeremy Harris
 * @link          http://42pixels.com
 * @package       cacher
 * @subpackage    cacher.models.behaviors
 */

/**
 * Includes
 */
App::import('Lib', 'Folder');

/**
 * CacheSource datasource
 *
 * Gets find results from cache instead of the original datasource. The cache
 * is stored under CACHE/cacher.
 *
 * @package       cacher
 * @subpackage    cacher.models.datasources
 */
class CacheSource extends DataSource {

/**
 * Stored original datasource for fallback methods
 *
 * @var DataSource
 */
	var $source = null;

/**
 * The name of the cache configuration for this datasource instance
 *
 * @var string
 */
	var $cacheConfig = 'CacherResults';

/**
 * Constructor
 *
 * Sets default options if none are passed when the datasource is created and
 * creates the cache configuration. If a `config` is passed and is a valid
 * Cache configuration, CacheSource uses its settings
 *
 * ### Extra config settings
 * - `original` The name of the original datasource, i.e., 'default' (required)
 * - `config` The name of the Cache configuration to duplicate (optional)
 * - other settings required by DataSource...
 *
 * @param array $config Configure options
 */
	function __construct($config = array()) {
		parent::__construct($config);
		if (!isset($this->config['original'])) {
			trigger_error('Cacher.CacheSource::__construct() :: Missing name of original datasource', E_USER_WARNING);
		}
		$settings = array(
			'engine' => 'File',
			'duration' => '+6 hours',
			'path' => CACHE.'cacher'.DS,
			'prefix' => 'cacher_'
		);
		if (isset($this->config['config']) && Cache::isInitialized($this->config['config'])) {
			$_existingCache = Cache::config($this->config['config']);
			$settings = array_merge($settings, $_existingCache['settings']);
		}

		$this->source =& ConnectionManager::getDataSource($this->config['original']);

		new Folder($settings['path'], true, 0775);
		Cache::config($this->cacheConfig, $settings);
	}

/**
 * Reads from cache if it exists. If not, it falls back to the original
 * datasource to retrieve the data and cache it for later
 *
 * @param Model $Model
 * @param array $queryData
 * @return array Results
 * @see DataSource::read()
 */
	function read($Model, $queryData = array()) {
		$key = $this->_key($Model, $queryData);
		$results = Cache::read($key, $this->cacheConfig);
		if ($results === false) {
			$results = $this->source->read($Model, $queryData);
			Cache::write($key, $results, $this->cacheConfig);
		}
		$this->_resetSource($Model);
		return $results;
	}

/*
 * Clears the cache for a specific model and rewrites the map. Pass query to
 * clear a specific query's cached results
 *
 * @param array $query If null, clears all for this model
 * @param Model $Model The model to clear the cache for
 */
	function clearModelCache($Model, $query = null) {
		$settings = Cache::config($this->cacheConfig);
		$path = $settings['settings']['path'];
		$prefix = $settings['settings']['prefix'];
		$sourceName = ConnectionManager::getSourceName($this->source);

		if ($query !== null) {
			$findKey = $this->_key($Model, $query);
			return Cache::delete($findKey, $this->cacheConfig);
		}

		$files = glob($path.DS.$prefix.$sourceName.'_'.Inflector::underscore($Model->alias).'_*');
		if (is_array($files) && count($files) > 0) {
			foreach ($files AS $file) {
				unlink($file);
			}
		}
		return true;
	}

/**
 * Hashes a query into a unique string and creates a cache key
 *
 * @param Model $Model The model
 * @param array $query The query
 * @return string
 * @access protected
 */
	function _key($Model, $query) {
		$query = array_merge(
			array(
				'conditions' => null, 'fields' => null, 'joins' => array(), 'limit' => null,
				'offset' => null, 'order' => null, 'page' => null, 'group' => null, 'callbacks' => true
			),
			(array)$query
		);
		$queryHash = md5(serialize($query));
		$sourceName = ConnectionManager::getSourceName($this->source);
		return Inflector::underscore($sourceName).'_'.Inflector::underscore($Model->alias).'_'.$queryHash;
	}

/**
 * Resets the model's datasource to the original
 *
 * @param Model $Model The model
 * @return boolean
 */
	function _resetSource($Model) {
		return $Model->setDataSource($sourceName = ConnectionManager::getSourceName($this->source));
	}

}

?>