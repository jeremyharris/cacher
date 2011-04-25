<?php
/**
 * Cache behavior class.
 *
 * @copyright     Copyright 2010, Jeremy Harris
 * @link          http://42pixels.com
 * @package       cacher
 * @subpackage    cacher.models.behaviors
 */

/**
 * Cache Behavior
 *
 * Auto-caches find results into the cache. Running an exact find again will
 * pull from the cache. Requires the CacheSource datasource.
 *
 * @package       cacher
 * @subpackage    cacher.models.behaviors
 */
class CacheBehavior extends ModelBehavior {

/**
 * Whether or not to cache this call's results
 *
 * @var boolean
 */
	var $cacheResults = false;

/**
 * Settings
 *
 * @var array
 */
	var $settings;

/**
 * The original cache configuration
 *
 * @var string
 */
	var $_originalCacheConfig = null;

/**
 * Sets up a connection using passed settings
 *
 * ### Config
 * - `config` The name of an existing Cache configuration to duplicate
 * - `clearOnSave` Whether or not to delete the cache on saves
 * - `clearOnDelete` Whether or not to delete the cache on deletes
 * - `auto` Automatically cache or look for `'cache'` in the find conditions
 *		where the key is `true` or a duration
 * - any options taken by Cache::config() will be used if `config` is not defined
 *
 * @param Model $Model The calling model
 * @param array $config Configuration settings
 * @see Cache::config()
 */
	function setup(&$Model, $config = array()) {
		$_defaults = array(
			'config' => null,
			'engine' => 'File',
			'duration' => '+6 hours',
			'clearOnDelete' => true,
			'clearOnSave' => true,
			'auto' => false
		);
		$settings = array_merge($_defaults, $config);

		$Model->_useDbConfig = $Model->useDbConfig;
		if (!in_array('cache', ConnectionManager::sourceList())) {
			$settings['original'] = $Model->useDbConfig;
			$settings['datasource'] = 'Cacher.cache';
			ConnectionManager::create('cache', $settings);
		}

		if (!isset($this->settings[$Model->alias])) {
			$this->settings[$Model->alias] = $settings;
		}
		$this->settings[$Model->alias] = array_merge($this->settings[$Model->alias], $settings);
	}

/**
 * Intercepts find to use the caching datasource instead
 *
 * If `$queryData['cache']` is true, it will cache based on the setup settings
 * If `$queryData['cache']` is a duration, it will cache using the setup settings
 * and the new duration.
 *
 * @param Model $Model The calling model
 * @param array $queryData The query
 */
	function beforeFind(&$Model, $queryData) {
		$this->cacheResults = false;
		if (isset($queryData['cache'])) {
			if (is_string($queryData['cache'])) {
				$ds = ConnectionManager::getDataSource('cache');
				Cache::config($ds->cacheConfig, array('duration' => $queryData['cache']));
				$this->cacheResults = true;
			} else {
				$this->cacheResults = (boolean)$queryData['cache'];
			}			
			unset($queryData['cache']);
		}
		$this->cacheResults = $this->cacheResults || $this->settings[$Model->alias]['auto'];
		
		if ($this->cacheResults) {
			$Model->setDataSource('cache');
		}
		return $queryData;
	}

/**
 * Intercepts delete to use the caching datasource instead
 *
 * @param Model $Model The calling model
 */	
	function beforeDelete(&$Model) {
		if ($this->settings[$Model->alias]['clearOnDelete']) {
			$this->clearCache($Model);
		}
		return true;
	}

/**
 * Intercepts save to use the caching datasource instead
 *
 * @param Model $Model The calling model
 */
	function beforeSave(&$Model) {
		if ($this->settings[$Model->alias]['clearOnSave']) {
			$this->clearCache($Model);
		}
		return true;
	}

/**
 * Clears all of the cache for this model's find queries. Optionally, pass
 * `$queryData` to just clear a specific query
 *
 * @param Model $Model The calling model
 * @return boolean
 */
	function clearCache(&$Model, $queryData = null) {
		if ($queryData !== null) {
			$queryData = $this->_prepareFind($Model, $queryData);
		}
		$cache = Cache::getInstance();
		$this->_originalCacheConfig = $cache->__name;
		$ds = ConnectionManager::getDataSource('cache');
		$success = $ds->clearModelCache($Model, $queryData);
		Cache::config($this->_originalCacheConfig);
		return $success;
	}

/*
 * Prepares a query by adding missing data. This function is needed because
 * reads on the database typically bypass Model::find() which is where the query
 * is changed.
 *
 * @param array $query The query
 * @return array The modified query
 * @access protected
 * @see Model::find()
 */
	function _prepareFind($Model, $query = array()) {
		$query = array_merge(
			array(
				'conditions' => null, 'fields' => null, 'joins' => array(), 'limit' => null,
				'offset' => null, 'order' => null, 'page' => null, 'group' => null, 'callbacks' => true
			),
			(array)$query
		);
		if (!is_numeric($query['page']) || intval($query['page']) < 1) {
			$query['page'] = 1;
		}
		if ($query['page'] > 1 && !empty($query['limit'])) {
			$query['offset'] = ($query['page'] - 1) * $query['limit'];
		}
		if ($query['order'] === null && $Model->order !== null) {
			$query['order'] = $Model->order;
		}
		$query['order'] = array($query['order']);

		return $query;
	}
}

?>