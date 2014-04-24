<?php

/**
 * Tests policy filters and framework
 * 
 * @author Damian Mooyman <damian@silverstripe.com>
 * @package policyfilter
 */
class PolicyFilterTest extends FunctionalTest {
	
	protected $userIP;

	public function setUp() {
		parent::setUp();
		
		
		$this->userIP = isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : null;

		$cache = SS_Cache::factory('PolicyFilterTest_RateLimited_Factory');
		$cache->clean(Zend_Cache::CLEANING_MODE_ALL);
		$cache = SS_Cache::factory('PolicyFilterTest_Cached_Factory');
		$cache->clean(Zend_Cache::CLEANING_MODE_ALL);
		
		Config::nest();
		// Set default
		Config::inst()->update('CachedContentFilter', 'cache_factoryname', 'CachedContentFilter_Factory');
		Config::inst()->update('CachedContentFilter', 'cache_lifetime', 300);
		Config::inst()->update('RateLimitFilter', 'rate_factoryname', 'RateLimitFilter_Factory');
		Config::inst()->update('RateLimitFilter', 'rate_lock_timeout', 10);
		Config::inst()->update('RateLimitFilter', 'rate_lock_byitem', false);
		Config::inst()->update('RateLimitFilter', 'rate_lock_byuserip', false);
	}
	
	public function tearDown() {
		Config::unnest();
		
		$_SERVER['HTTP_CLIENT_IP'] = $this->userIP;
		
		parent::tearDown();
	}
	
	/**
	 * Test inheritance of policy settings
	 */
	public function testPolicy() {
		
		// Test cached policy
		$cachedController = PolicyFilterTest_Cached::create();
		$this->assertInstanceOf(
			'CachedContentFilter',
			$cacheFilter = $cachedController->getContentFilter()
		);
		$this->assertEquals(300, $cacheFilter->policy('cache_lifetime')); // default
		$this->assertEquals('PolicyFilterTest_Cached_Factory', $cacheFilter->policy('cache_factoryname'));
		
		// Test filtered policy
		$rateLimitedController = PolicyFilterTest_RateLimited::create();
		$this->assertInstanceOf(
			'RateLimitFilter',
			$rateFilter = $rateLimitedController->getContentFilter()
		);
		$this->assertEquals(10, $rateFilter->policy('rate_lock_timeout')); // default
		$this->assertEquals(true, $rateFilter->policy('rate_lock_byitem'));
		$this->assertEquals(false, $rateFilter->policy('rate_lock_byuserip'));
		$this->assertEquals('PolicyFilterTest_RateLimited_Factory', $rateFilter->policy('rate_factoryname'));
		
		// Test change to default doesn't affect overriden values
		Config::inst()->update('CachedContentFilter', 'cache_factoryname', 'NewDefault_Factory');
		Config::inst()->update('RateLimitFilter', 'rate_factoryname', 'NewDefault_Factory');
		$this->assertEquals('PolicyFilterTest_Cached_Factory', $cacheFilter->policy('cache_factoryname'));
		$this->assertEquals('PolicyFilterTest_RateLimited_Factory', $rateFilter->policy('rate_factoryname'));
		
		// Test changes to default does affect non-overridden values
		Config::inst()->update('CachedContentFilter', 'cache_lifetime', 310);
		Config::inst()->update('RateLimitFilter', 'rate_lock_timeout', 20);
		$this->assertEquals(310, $cacheFilter->policy('cache_lifetime'));
		$this->assertEquals(20, $rateFilter->policy('rate_lock_timeout'));
	}
	
	public function testRateLimiting() {
		
		// Artifically set cache lock
		$cache = SS_Cache::factory('PolicyFilterTest_RateLimited_Factory');
		$cache->setOption('automatic_serialization', true);
		$cache->save(time() - 2, 'Rate_'.md5('5'));
		
		// Test item specific lock
		$response1 = $this->get('PolicyFilterTest_RateLimited/test/5');
		$response2 = $this->get('PolicyFilterTest_RateLimited/test/6');
		$this->assertEquals(429, $response1->getStatusCode());
		$this->assertGreaterThan(0, $response1->getHeader('Retry-After'));
		$this->assertEquals(200, $response2->getStatusCode());
		$this->assertEquals('Item ID(6)', $response2->getBody());
		
		// Test non-item specific lock
		$cache->clean(Zend_Cache::CLEANING_MODE_ALL);
		$cache->save(time() - 2, 'Rate');
		Config::inst()->update('PolicyFilterTest_RateLimited', 'filter_policy', array(
			'rate_lock_byitem' => false,
			'rate_lock_byuserip' => false
		));
		$response1 = $this->get('PolicyFilterTest_RateLimited/test/5');
		$response2 = $this->get('PolicyFilterTest_RateLimited/test/6');
		$this->assertEquals(429, $response1->getStatusCode());
		$this->assertGreaterThan(0, $response1->getHeader('Retry-After'));
		$this->assertEquals(429, $response2->getStatusCode());
		$this->assertGreaterThan(0, $response2->getHeader('Retry-After'));
		
		// Test rate limit hit by IP
		$cache->clean(Zend_Cache::CLEANING_MODE_ALL);
		Config::inst()->update('PolicyFilterTest_RateLimited', 'filter_policy', array(
			'rate_lock_byitem' => false,
			'rate_lock_byuserip' => true
		));
		$cache->save(time() - 2, $key = 'Rate_' . md5('127.0.0.1'));
		
		// Test rate limit hits target IP
		$_SERVER['HTTP_CLIENT_IP'] = '127.0.0.1';
		$response1 = $this->get('PolicyFilterTest_RateLimited/test/7');
		$this->assertEquals(429, $response1->getStatusCode());
		$this->assertGreaterThan(0, $response1->getHeader('Retry-After'));
		
		// Test rate limit doesn't hit other IP
		$_SERVER['HTTP_CLIENT_IP'] = '127.0.0.20';
		$response2 = $this->get('PolicyFilterTest_RateLimited/test/7');
		$this->assertEquals(200, $response2->getStatusCode());
		$this->assertEquals('Item ID(7)', $response2->getBody());
	}
	
	/**
	 * Tests cache
	 */
	public function testCache() {
		
		$cache = SS_Cache::factory('PolicyFilterTest_Cached_Factory');
		$cache->setOption('automatic_serialization', true);
		
		// Basic test
		$response = $this->get('PolicyFilterTest_Cached/test/8');
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEquals('Item ID(8)', $response->getBody());
		$response = $this->get('PolicyFilterTest_Cached/test/9');
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEquals('Item ID(9)', $response->getBody());
		
		// Test internal behaviour change
		Config::inst()->update('PolicyFilterTestOperation', 'formatter', 'New Format(%s)');
		$response = $this->get('PolicyFilterTest_Cached/test/8');
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEquals('Item ID(8)', $response->getBody());
		$response = $this->get('PolicyFilterTest_Cached/test/10');
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEquals('New Format(10)', $response->getBody());
		
		// Inject value into cache
		$cache->save('Dummy Content', 8);
		$response = $this->get('PolicyFilterTest_Cached/test/8');
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEquals('Dummy Content', $response->getBody());
		
		// Flush cache
		$cache->clean(Zend_Cache::CLEANING_MODE_ALL);
		$response = $this->get('PolicyFilterTest_Cached/test/8');
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEquals('New Format(8)', $response->getBody());
	}
	
	/**
	 * Test composite filter
	 */
	public function testCompositeFilter() {
		
		// Register composite
		Injector::inst()->load(array(
			'TestCompositeFilter' => array(
				'class' => 'CachedContentFilter',
				'constructor' => array(
					'%$RateLimitFilter'
				)
			)
		));
		$cachedController = PolicyFilterTest_Composite::create();
		$this->assertInstanceOf('CachedContentFilter',
			$parentFilter = $cachedController->getContentFilter()
		);
		$this->assertInstanceOf('RateLimitFilter',
			$parentFilter->getNestedContentFilter()
		);
		
		// Test that composite filter works
		$result = $this->get('PolicyFilterTest_Composite/test/11');
		$this->assertEquals('Item ID(11)', $result->getBody());
	}
	
}

class PolicyFilterTestOperation extends Controller implements TestOnly {
	
	private static $allowed_actions = array(
		'test'
	);
	
	private static $url_handlers = array(
		'test/$ID' => 'test'
	);
	
	private static $extensions = array(
		'FilteredExtension'
	);
	
	private static $formatter = 'Item ID(%s)';
	
	public function test() {
		$id = $this->request->param('ID');
		$method = function() use ($id) {
			return sprintf(PolicyFilterTestOperation::config()->formatter, $id);
		};
		return $this->filterContent($id, $method);
	}
}

class PolicyFilterTest_Cached extends PolicyFilterTestOperation {
	
	private static $dependencies = array(
		'ContentFilter' => '%$CachedContentFilter'
	);
	
	private static $filter_policy = array(
		'cache_factoryname' => 'PolicyFilterTest_Cached_Factory'
	);
	
}

class PolicyFilterTest_RateLimited extends PolicyFilterTestOperation {
	
	private static $dependencies = array(
		'ContentFilter' => '%$RateLimitFilter'
	);
	
	private static $filter_policy = array(
		'rate_lock_byitem' => true,
		'rate_lock_byuserip' => false,
		'rate_factoryname' => 'PolicyFilterTest_RateLimited_Factory'
	);
	
}

class PolicyFilterTest_Composite extends PolicyFilterTestOperation {
	private static $dependencies = array(
		'ContentFilter' => '%$TestCompositeFilter'
	);
}
