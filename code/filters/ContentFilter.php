<?php

/**
 * Conditionally executes a given callback, attempting to return the desired results
 * of its execution.
 * 
 * @author Damian Mooyman <damian@silverstripe.com>
 * @package policyfilter
 */
abstract class ContentFilter {
	
	/**
	 * Nested content filter
	 *
	 * @var ContentFilter
	 */
	protected $nestedContentFilter;
	
	/**
	 * Owner of this filter
	 * Used for determination of policy filter parameters
	 *
	 * @var Object
	 */
	protected $owner;
	
	public function __construct($nestedContentFilter = null) {
		$this->nestedContentFilter = $nestedContentFilter;
	}
	
	/**
	 * Gets the nested filter
	 * 
	 * @return ContentFilter
	 */
	public function getNestedContentFilter() {
		return $this->nestedContentFilter;
	}
	
	/**
	 * Assigns this filter to a specific owner
	 * 
	 * @param Object $owner Owner of this filter
	 * @return self Self reference
	 */
	public function setOwner($owner) {
		$this->owner = $owner;
		if($this->nestedContentFilter) {
			$this->nestedContentFilter->setOwner($owner);
		}
		return $this;
	}
	
	/**
	 * Evaluates the result of the given callback
	 * 
	 * @param string $key Unique key for this
	 * @param callable $callback Callback for evaluating the content
	 * @return mixed Result of $callback()
	 */
	public function FilterContent($key, $callback) {
		if($this->nestedContentFilter) {
			return $this->nestedContentFilter->FilterContent($key, $callback);
		} else {
			return call_user_func($callback);
		}
	}
	
	/**
	 * Get the value of a specific policy value
	 * 
	 * @param string $name
	 * @retun mixed Policy value
	 */
	public function policy($name) {
		// Check owner policy
		if($this->owner) {
			$policy = Config::inst()->get(get_class($this->owner), 'filter_policy');
			if($policy && isset($policy[$name])) return $policy[$name];
		}
		
		// Default policy
		return Config::inst()->get(get_class($this), $name);
	}
}
