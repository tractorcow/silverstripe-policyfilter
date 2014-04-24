<?php

/**
 * Caches results of a callback
 * 
 * @author Damian Mooyman <damian@silverstripe.com>
 * @package policyfilter
 */
class CachedContentFilter extends ContentFilter {
	
	/**
	 * Default cache lifetime for results
	 *
	 * @config
	 * @var int
	 */
	private static $cache_lifetime = 300;
	
	/**
	 * Identifying name of this cache factory
	 *
	 * @var string
	 * @config
	 */
	protected static $cache_factoryname = 'CachedContentFilter_Factory';
	
	/**
	 * Gets the cache to use
	 * 
	 * @return Zend_Cache_Frontend
	 */
	protected function getCache() {
		$factory = $this->policy('cache_factoryname');
		$lifetime = $this->policy('cache_lifetime');
		$cache = SS_Cache::factory($factory);
		$cache->setOption('automatic_serialization', true);
		$cache->setOption('lifetime', $lifetime);
		return $cache;
	}
	
	public function FilterContent($key, $callback) {
		$cache = $this->getCache();
		
		// Return cached value if available
		$result = isset($_GET['flush'])
			? null
			: $cache->load($key);
		
		if($result) return $result;
		
		// Fallback to generate result
		$result = parent::FilterContent($key, $callback);
		$cache->save($result, $key);
		return $result;
	}
}
