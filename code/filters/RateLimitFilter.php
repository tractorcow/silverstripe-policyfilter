<?php

/**
 * Provides rate limiting of execution of a callback
 * 
 * @author Damian Mooyman <damian@silverstripe.com>
 * @package policyfilter
 */
class RateLimitFilter extends ContentFilter {
	
	/**
	 * Time duration (in second) to allow for generation of cached results. Requests to 
	 * pages that within this time period that do not hit the cache (and would otherwise trigger
	 * a version query) will be presented with a 429 (rate limit) HTTP error
	 *
	 * @config
	 * @var int
	 */
	private static $rate_lock_timeout = 10;
	
	/**
	 * Determine if the cache generation should be locked on a per-item basis. If true, concurrent processing
	 * may be performed on different objects without rate interference.
	 * 
	 * Suggested to turn this to true on small sites that will not have many concurrent views of page versions
	 *
	 * @config
	 * @var bool
	 */
	private static $rate_lock_byitem = true;
	
	/**
	 * Determine if rate limiting should be applied independently to each IP address. This method is not
	 * reliable, as most DDoS attacks use multiple IP addresses.
	 *
	 * @config
	 * @var bool
	 */
	private static $rate_lock_byuserip = false;
	
	/**
	 * Cache key prefix
	 */
	private static $rate_factoryname = 'RateLimitFilter_Factory';
	
	/**
	 * Gets the cache to use
	 * 
	 * @return Zend_Cache_Frontend
	 */
	protected function getCache() {
		$factory = $this->policy('rate_factoryname');
		$lifetime = $this->policy('rate_lock_timeout') + 60; // As long as it's longer than the actual timeout
		$cache = SS_Cache::factory($factory);
		$cache->setOption('automatic_serialization', true);
		$cache->setOption('lifetime', $lifetime);
		return $cache;
	}
	
	/**
	 * Determines the key to use for saving the current rate
	 * 
	 * @param string $itemkey Input key
	 * @return string Result key
	 */
	protected function getCacheKey($itemkey) {
		$key = 'Rate';
		
		// Add global identifier
		if($this->policy('rate_lock_byitem')) {
			$key .= '_' . md5($itemkey);
		}
		
		// Add user-specific identifier
		if($this->policy('rate_lock_byuserip') && Controller::has_curr()) {
			$ip = Controller::curr()->getRequest()->getIP();
			$key .= '_' . md5($ip);
		}
		
		return $key;
	}

	public function FilterContent($key, $callback) {
		// Bypass rate limiting if flushing, or timeout isn't set
		$timeout = $this->policy('rate_lock_timeout');
		if(isset($_GET['flush']) || !$timeout) {
			return parent::FilterContent($key, $callback);
		}
		
		// Generate result with rate limiting enabled
		$limitKey = $this->getCacheKey($key);
		$cache = $this->getCache();
		if($cacheBegin = $cache->load($limitKey)) {
			if(time() - $cacheBegin < $timeout) {
				// Politely inform visitor of limit
				$response = new SS_HTTPResponse_Exception('Too Many Requests.', 429);
				$response->getResponse()->addHeader('Retry-After', 1 + time() - $cacheBegin);
				throw $response;
			}
		}
		
		// Generate result with rate limit locked
		$cache->save(time(), $limitKey);
		$result = parent::FilterContent($key, $callback);
		$cache->remove($limitKey);
		return $result;
	}
}
