<?php
/**
 * Memcache wrapper class.
 *
 * @package    Elgg.Core
 * @subpackage Memcache
 *
 * @deprecated 1.10
 */
class ElggMemcache extends \ElggSharedMemoryCache {

	/**
	 * TTL of saved items (default timeout after a day to prevent anything getting too stale)
	 */
	private $ttl = 86400;

	/**
	 * @var \Stash\Pool
	 */
	private $stash_pool;

	/**
	 * Constructor
	 *
	 * @internal Use _elgg_get_memcache() instead of direct construction.
	 *
	 * @param string $namespace The namespace for this cache to write to -
	 * note, namespaces of the same name are shared!
	 *
	 * @throws ConfigurationException
	 *
	 * @see _elgg_get_memcache()
	 */
	public function __construct($namespace = 'default') {
		$this->stash_pool = _elgg_services()->memcacheStashPool;
		if (!$this->stash_pool) {
			throw new \ConfigurationException('No memcache servers defined, please populate the $this->CONFIG->memcache_servers variable');
		}

		$this->setNamespace($namespace);

		// Set some defaults
		$expires = _elgg_services()->config->get('memcache_expires');
		if (isset($expires)) {
			$this->ttl = $expires;
		}
	}

	/**
	 * Set the default TTL.
	 *
	 * @param int $expires The TTL in seconds from now. Default is no expiration.
	 *
	 * @return void
	 */
	public function setDefaultExpiry($expires = 0) {
		$this->ttl = $expires;
	}

	/**
	 * Combine a key with the namespace.
	 * Memcache can only accept <250 char key. If the given key is too long it is shortened.
	 *
	 * @param string $key The key
	 *
	 * @return string The new key.
	 */
	private function makeMemcacheKey($key) {
		$prefix = $this->getNamespace() . ":";

		if (strlen($prefix . $key) > 250) {
			$key = md5($key);
		}

		return $prefix . $key;
	}

	/**
	 * Saves a name and value to the cache
	 *
	 * @param string  $key  Name
	 * @param string  $data Value
	 * @param integer $ttl  TTL of cache item (seconds), 0 for no expiration, null for default.
	 *
	 * @return bool
	 */
	public function save($key, $data, $ttl = null) {
		$key = $this->makeMemcacheKey($key);

		if ($ttl === null) {
			$ttl = $this->expires;
		}

		$item = $this->stash_pool->getItem($key);
		$result = $item->set($data, $ttl);

		if ($result) {
			_elgg_services()->logger->info("MEMCACHE: SAVE SUCCESS $key");
		} else {
			_elgg_services()->logger->error("MEMCACHE: SAVE FAIL $key");
		}

		return $result;
	}

	/**
	 * Retrieves data.
	 *
	 * @param string $key    Name of data to retrieve
	 * @param int    $offset Unused
	 * @param int    $limit  Unused
	 *
	 * @return mixed
	 */
	public function load($key, $offset = 0, $limit = null) {
		$key = $this->makeMemcacheKey($key);

		$item = $this->stash_pool->getItem($key);
		$value = $item->get();

		if ($item->isMiss()) {
			_elgg_services()->logger->info("MEMCACHE: LOAD MISS $key");
			return false;
		}

		_elgg_services()->logger->info("MEMCACHE: LOAD HIT $key");

		return $value;
	}

	/**
	 * Delete data
	 *
	 * @param string $key Name of data
	 *
	 * @return bool
	 */
	public function delete($key) {
		$key = $this->makeMemcacheKey($key);

		return $this->stash_pool->getItem($key)->clear();
	}

	/**
	 * Clears the entire cache (does not work)
	 *
	 * @return true
	 *
	 * @deprecated 1.10 This functionality is not available
	 */
	public function clear() {
		elgg_deprecated_notice(__METHOD__ . ' no longer works and will be removed.', '1.10');

		return true;
	}
}
