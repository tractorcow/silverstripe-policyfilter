<?php

/**
 * Provides content filtering services to an object
 * 
 * @author Damian Mooyman <damian@silverstripe.com>
 * @package policyfilter
 */
class FilteredExtension extends Extension {
	
	/**
	 * Get the content filter
	 * 
	 * @return ContentFilter
	 */
	public function getContentFilter() {
		return isset($this->owner->contentfilter) ? $this->owner->contentfilter : null;
	}
	
	/**
	 * Assigns this object the necessary filter
	 * 
	 * @param ContentFilter $filter
	 * @dependency
	 */
	public function setContentFilter(ContentFilter $filter) {
		$this->owner->contentfilter = $filter;
		$filter->setOwner($this->owner);
	}
	
	/**
	 * Extension point for filtering of content
	 * 
	 * @param string $key Unique key for this
	 * @param callable $callback Callback for evaluating the content
	 * @return mixed Result of $callback()
	 */
	public function filterContent($key, $callback) {
		if($filter = $this->getContentFilter()) {
			return $filter->FilterContent($key, $callback);
		} else {
			return call_user_func($callback);
		}
	}
}
