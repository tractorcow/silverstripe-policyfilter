# Abandoned Module

This module was made as a proof of concept. Unfortunately, all I managed to do was prove it couldn't be done effectively. You can refer to this module or use it at your own risk, but this module will not be maintained any further.





# Policy Filter

## Overview

This module provides a framework for the handling of resource intensive tasks,
allowing both caching of content and rate usage limits to be enforced.

## Requirements

 * SilverStripe 3.1+

## Installation

Install with composer by running:

	composer require silverstripe/policyfilter:*

in the root of your SilverStripe project.

Or just clone/download the git repository into a subfolder (usually called
"policyfilter") of your SilverStripe project.

## Basic setup

For any service or task to which a filter may be applied, add the
`FilteredExtension` and apply an appropriate `ContentFilter` dependency as
necessary. E.g.

```yaml
---
Name: myfilterconfig
---
Page_Controller:
  extensions:
    - FilteredExtension
  dependencies:
    ContentFilter: %$RateLimitFilter
```

In your Page_Controller the current filter can be given the task of processing
any given callback using the below code. Each callback also has an ID so that
filterpolicy can distinguish between elements which should be cached if necessary.

```php
public function getNewData() {
	return $this->filterContent($this->ID, function() {
		// Do some long-running task here
	});
}
```

If developing a module which should still work without policyfilter you can
rewrite this using `extend`

```php
public function getNewData() {
	$callback = function() {
		// Do some long-running task here
	};
	$result = $this->owner->extend('filterContent', $this->ID, $callback);
	return reset($result) ?: $callback();
}
```

This module includes the following filters:
 * `CachedContentFilter` which provides caching of results
 * `RateLimitFilter` which provides limits to maximum concurrent execution
 * `ContentFilter` which is a shortcut to provide both caching and rate
   limiting on a single task


## Configuring object policies

A policy can be applied to determine the behaviour of filters on any consuming
service. Note, that the `filter_policy` config is applied to the object itself.

```yaml
Page_Controller
  filter_policy:
    rate_lock_timeout: 10 # Rate limit this process for 10 seconds
    rate_lock_byitem: true # Rate limit requests only on an item-by-item basis
    rate_lock_byuserip: true # Rate limit requests only locked to a single IP
    cache_lifetime: 3600 # Cache the results of this for one hour
    cache_factoryname: 'PageData_Cache' # Name of the cache store
```
