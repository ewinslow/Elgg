<?php
namespace Elgg\Views;

use Elgg\Filesystem\File;
use Elgg\DeprecationWrapper;

/**
 * @access private
 * @since 2.0.0
 */
class View {
	/** @var bool */
	private $is_cacheable = false;
	
	/** @var File[] */
	private $locations = array();
	
	/** @var View[] */
	private $prepends = array();
	
	/** @var View[] */
	private $appends = array();

	/**
	 * Whether the view has been registered/extended.
	 * 
	 * @param string  $viewtype
	 * @param boolean $recurse
	 * 
	 * @return boolean
	 */
	public function exists(Viewtype $viewtype, $recurse = true) {
		$location = $this->getLocation($viewtype);

		if (!empty($location)) {
			return true;
		}

		// If we got here then check whether this exists as an extension
		if ($recurse) {
			$extensions = array_merge($this->prepends, $this->appends);
			/* @var View[] $extensions */

			foreach ($extensions as $extension) {
				// do not recursively check to stay away from infinite loops
				if ($extension->exists($viewtype, false)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param View $extension
	 * @param int  $priority
	 * 
	 * @access private
	 */
	public function prepend(View $extension, $priority) {
		// raise priority until it doesn't match one already registered
		while (isset($this->prepends[$priority])) {
			$priority++;
		}

		$this->prepends[$priority] = $extension;
		
		ksort($this->prepends);
	}
	
	/**
	 * @param View $extension
	 * @param int  $priority
	 * 
	 * @access private
	 */
	public function append(View $extension, $priority) {
		// raise priority until it doesn't match one already registered
		while (isset($this->appends[$priority])) {
			$priority++;
		}

		$this->appends[$priority] = $extension;
		
		ksort($this->appends);
	}

	/**
	 * @param string $extension
	 * 
	 * @return boolean
	 * @access private
	 */
	public function unextend(View $extension = null) {
		$priority = array_search($extension, $this->prepends, true);
		
		if ($priority !== false) {
			unset($this->prepends[$priority]);
			return true;
		}
		
		$priority = array_search($extension, $this->appends, true);
		if ($priority !== false) {
			unset($this->appends[$priority]);
			return true;
		}

		return false;
	}
	
	/**
	 * @param Viewtype $viewtype
	 * @param File     $location
	 * 
	 * @return View
	 */
	public function setLocation(Viewtype $viewtype, File $location) {
		$this->locations["$viewtype"] = $location;
		
		if ($location->getExtension() != 'php') {
			$this->setCacheable(true);
		}
		
		return $this;
	}
	
	/**
	 * @param Viewtype $viewtype The format of the view
	 * 
	 * @return File|null The path to the view, taking into account viewtype fallbacks,
	 *                   or null if the view is not registered.
	 */
	public function getLocation(Viewtype $viewtype) {
		while ($viewtype && !isset($this->locations["$viewtype"])) {
			$viewtype = $viewtype->getFallback();
		}

		if (empty($viewtype)) {
			return null;
		}
		
		return $this->locations["$viewtype"];
	}
	
	/**
	 * @param array    $vars
	 * @param Viewtype $viewtype
	 * 
	 * @return string
	 */
	public function render(array $vars, Viewtype $viewtype) {
		$content = '';

		foreach ($this->prepends as $extension) {
			$content .= $extension->render($vars, $viewtype);
		}
		
		$content .= $this->renderContent($vars, $viewtype);
		
		foreach ($this->appends as $extension) {
		    $content .= $extension->render($vars, $viewtype);
		}
		
		return $content;
	}
	
	/**
	 * Includes view PHP or static file
	 * 
	 * @param array    $vars     Variables passed to view
	 * @param Viewtype $viewtype The viewtype
	 *
	 * @return string output generated by view file inclusion or false
	 */
	private function renderContent(array $vars, Viewtype $viewtype) {
		$location = $this->getLocation($viewtype);

		if (empty($location)) {
			return '';
		}

		if ($location->getExtension() != 'php') {
			return $location->getContents();
		}
	
		ob_start();
		include $location->getFullPath();
		return ob_get_clean();
	}

	/**
	 * @param bool $cacheable
	 */
	public function setCacheable($cacheable) {
		$this->is_cacheable = $cacheable;
	}
	
	/**
	 * @return bool 
	 */
	public function isCacheable() {
		return $this->is_cacheable;
	}
}