<?php
/**
 * Null cache
 *
 * @package    Elgg.Core
 * @subpackage Memcache
 *
 * @access private
 */
class ElggNullCache extends \ElggSharedMemoryCache {

	/**
	 * Saves a name and value to the cache
	 *
	 * @param string  $key  Unused
	 * @param string  $data Unused
	 * @param integer $ttl  Unused
	 *
	 * @return bool
	 */
	public function save($key, $data, $ttl = null) {
		return true;
	}

	/**
	 * Retrieves data.
	 *
	 * @param string $key    Unused
	 * @param int    $offset Unused
	 * @param int    $limit  Unused
	 *
	 * @return mixed
	 */
	public function load($key, $offset = 0, $limit = null) {
		return false;
	}

	/**
	 * Delete data
	 *
	 * @param string $key Unused
	 *
	 * @return bool
	 */
	public function delete($key) {
		return true;
	}

	/**
	 * Clears the entire cache (does not work)
	 *
	 * @return true
	 */
	public function clear() {
		return true;
	}
}
