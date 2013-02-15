<?php

/**
 * WARNING: API IN FLUX. DO NOT USE DIRECTLY.
 * 
 * Use the elgg_* versions instead.
 *
 * @todo 1.10 remove deprecated view injections
 * @todo inject/remove dependencies: $CONFIG, hooks, site_url
 * 
 * @access private
 * @since 1.9.0
 */
class ElggViewService {

	protected $config_wrapper;
	protected $site_url_wrapper;
	protected $user_wrapper;
	protected $user_wrapped;
	
	private $views = array();

	/** @var ElggPluginHookService */
	private $hooks;
	
	/** @var ElggLogger */
	private $logger;


	public function __construct(ElggPluginHookService $hooks, ElggLogger $logger, ElggSite $site) {
		$this->hooks = $hooks;
		$this->logger = $logger;
		$this->site = $site;
	}

	protected function getUserWrapper() {
		$user = elgg_get_logged_in_user_entity();
		if ($user) {
			if ($user !== $this->user_wrapped) {
				$warning = 'Use elgg_get_logged_in_user_entity() rather than assuming elgg_view() '
						 . 'populates $vars["user"]';
				$this->user_wrapper = new ElggDeprecationWrapper($user, $warning, 1.8);
			}
			$user = $this->user_wrapper;
		}
		return $user;
	}
	
	/**
	 * Walks a directory of viewtypes and registers all viewtypes and their views
	 * for rendering.
	 * 
	 * @param string $directory Path to a directory on this filesystem.
	 */
	public function registerViews($directory) {
		$dirs = scandir($directory);
		
		foreach ($dirs as $viewtype) {
			if (strpos($viewtype, '.') !== 0 && is_dir("$directory/$viewtype")) {
				$this->registerViewtypeViews($viewtype, "$directory/$viewtype");
			}
		}
	}
	
	/**
	 * Registers all the views in a directory as belonging to the given viewtype.
	 * 
	 * @param string $viewtype  The viewtype to register for.
	 * @param string $base_dir  The directory to recursively walk.
	 * @param string $base_view The view subdirectory that we're walking.
	 */
	private function registerViewtypeViews($viewtype, $base_dir, $base_view = '') {
		if (strpos($base_view, '.') === 0) {
			return;
		}
		
		$files = scandir($base_dir);		
		
		foreach ($files as $file) {
			if (strpos($file, '.') === 0) {
				continue;
			}

			// Get the full path to this view
			$path = "$base_dir/$file";
			
			if (is_dir($path)) {
				$new_base_view = empty($base_view) ? $file : "$base_view/$file";
				$this->registerViewtypeViews($viewtype, $path, $new_base_view);
			} else {
				$basename = basename($file, '.php');
				$full_view_name = empty($base_view) ? $basename : "$base_view/$basename";
				$this->registerView($viewtype, $full_view_name, $path);
			}
		}
	}
	
	private function registerView($viewtype, $view, $path) {
		$this->views[$viewtype][$view] = $path;
	}
	
	public function getViewLocation($view, $viewtype) {
		$path = realpath($this->views[$viewtype][$view]);
		
		if (!$path && $this->viewtypeFallsBack($viewtype)) {
			$path = $this->views['default'][$view];
		}
		
		if (!$path || !file_exists($path)) {
			throw new Exception("View '$view' not found in '$viewtype' or 'default' viewtypes.");
		}
		
		return $path;
	}

	/**
	 * @access private
	 * @since 1.9.0
	 */
	public function renderView($view, array $vars = array(), $bypass = false, $viewtype = '') {
		global $CONFIG;

		if (!is_string($view) || !is_string($viewtype)) {
			$this->logger->log("View and Viewtype in views must be a strings: $view", 'NOTICE');
			return '';
		}
		// basic checking for bad paths
		if (strpos($view, '..') !== false) {
			return '';
		}

		if (!is_array($vars)) {
			$this->logger->log("Vars in views must be an array: $view", 'ERROR');
			$vars = array();
		}

		// Get the current viewtype
		if ($viewtype === '' || !_elgg_is_valid_viewtype($viewtype)) {
			$viewtype = elgg_get_viewtype();
		}
	
		$view_orig = $view;
	
		// Trigger the pagesetup event
		if (!isset($CONFIG->pagesetupdone) && $CONFIG->boot_complete) {
			$CONFIG->pagesetupdone = true;
			elgg_trigger_event('pagesetup', 'system');
		}
	
		// @warning - plugin authors: do not expect user, config, and url to be
		// set by elgg_view() in the future. Instead, use elgg_get_logged_in_user_entity(),
		// elgg_get_config(), and elgg_get_site_url() in your views.
		if (!isset($vars['user'])) {
			$vars['user'] = $this->getUserWrapper();
		}
		if (!isset($vars['config'])) {
			if (!$this->config_wrapper) {
				$warning = 'Use elgg_get_config() rather than assuming elgg_view() populates $vars["config"]';
				$this->config_wrapper = new ElggDeprecationWrapper($CONFIG, $warning, 1.8);
			}
			$vars['config'] = $this->config_wrapper;
		}
		if (!isset($vars['url'])) {
			if (!$this->site_url_wrapper) {
				$warning = 'Use elgg_get_site_url() rather than assuming elgg_view() populates $vars["url"]';
				$this->site_url_wrapper = new ElggDeprecationWrapper($this->site->getURL(), $warning, 1.8);
			}
			$vars['url'] = $this->site_url_wrapper;
		}
	
		// full_view is the new preferred key for full view on entities @see elgg_view_entity()
		// check if full_view is set because that means we've already rewritten it and this is
		// coming from another view passing $vars directly.
		if (isset($vars['full']) && !isset($vars['full_view'])) {
			elgg_deprecated_notice("Use \$vars['full_view'] instead of \$vars['full']", 1.8, 2);
			$vars['full_view'] = $vars['full'];
		}
		if (isset($vars['full_view'])) {
			$vars['full'] = $vars['full_view'];
		}
	
		// internalname => name (1.8)
		if (isset($vars['internalname']) && !isset($vars['__ignoreInternalname']) && !isset($vars['name'])) {
			elgg_deprecated_notice('You should pass $vars[\'name\'] now instead of $vars[\'internalname\']', 1.8, 2);
			$vars['name'] = $vars['internalname'];
		} elseif (isset($vars['name'])) {
			if (!isset($vars['internalname'])) {
				$vars['__ignoreInternalname'] = '';
			}
			$vars['internalname'] = $vars['name'];
		}
	
		// internalid => id (1.8)
		if (isset($vars['internalid']) && !isset($vars['__ignoreInternalid']) && !isset($vars['name'])) {
			elgg_deprecated_notice('You should pass $vars[\'id\'] now instead of $vars[\'internalid\']', 1.8, 2);
			$vars['id'] = $vars['internalid'];
		} elseif (isset($vars['id'])) {
			if (!isset($vars['internalid'])) {
				$vars['__ignoreInternalid'] = '';
			}
			$vars['internalid'] = $vars['id'];
		}
	
		// If it's been requested, pass off to a template handler instead
		if ($bypass == false && isset($CONFIG->template_handler) && !empty($CONFIG->template_handler)) {
			$template_handler = $CONFIG->template_handler;
			if (is_callable($template_handler)) {
				return call_user_func($template_handler, $view, $vars);
			}
		}
	
		// Set up any extensions to the requested view
		if (isset($CONFIG->views->extensions[$view])) {
			$viewlist = $CONFIG->views->extensions[$view];
		} else {
			$viewlist = array(500 => $view);
		}
	
		// Start the output buffer, find the requested view file, and execute it
	
		$content = '';
		foreach ($viewlist as $priority => $view) {
			try {
				$view_file = $this->getViewLocation($view, $viewtype);
			}  catch (Exception $e) {
				$this->logger->log($e->getMessage(), 'ERROR');
				continue;
			}
			
			if (basename($view_file) == basename($view_file, '.php')) {
				$content .= file_get_contents($view_file);
			} else {
				ob_start();
				include($view_file);
				$content .= ob_get_clean();
			}
		}
	
		// Plugin hook
		$params = array('view' => $view_orig, 'vars' => $vars, 'viewtype' => $viewtype);
		$content = $this->hooks->trigger('view', $view_orig, $params, $content);
	
		// backward compatibility with less granular hook will be gone in 2.0
		$content_tmp = $this->hooks->trigger('display', 'view', $params, $content);
	
		if ($content_tmp !== $content) {
			$content = $content_tmp;
			elgg_deprecated_notice('The display:view plugin hook is deprecated by view:view_name', 1.8);
		}
	
		return $content;
	}
	
	/**
	 * @access private
	 * @since 1.9.0
	 */
	public function viewExists($view, $viewtype = '', $recurse = true) {
		global $CONFIG;

		// Detect view type
		if ($viewtype === '' || !_elgg_is_valid_viewtype($viewtype)) {
			$viewtype = elgg_get_viewtype();
		}
	
		try {
			$location = $this->getViewLocation($view, $viewtype);
			return true;
		} catch (Exception $e) {}
	
		// If we got here then check whether this exists as an extension
		// We optionally recursively check whether the extended view exists also for the viewtype
		if ($recurse && isset($CONFIG->views->extensions[$view])) {
			foreach ($CONFIG->views->extensions[$view] as $view_extension) {
				// do not recursively check to stay away from infinite loops
				if ($this->viewExists($view_extension, $viewtype, false)) {
					return true;
				}
			}
		}
	
		return false;
	}

	/**
	 * @access private
	 * @since 1.9.0
	 */
	public function extendView($view, $view_extension, $priority = 501, $viewtype = '') {
		global $CONFIG;

		if (!isset($CONFIG->views)) {
			$CONFIG->views = (object) array(
				'extensions' => array(),
			);
			$CONFIG->views->extensions[$view][500] = (string) $view;
		} else {
			if (!isset($CONFIG->views->extensions[$view])) {
				$CONFIG->views->extensions[$view][500] = (string) $view;
			}
		}

		// raise priority until it doesn't match one already registered
		while (isset($CONFIG->views->extensions[$view][$priority])) {
			$priority++;
		}
	
		$CONFIG->views->extensions[$view][$priority] = (string) $view_extension;
		ksort($CONFIG->views->extensions[$view]);

	}
	
	/**
	 * @access private
	 * @since 1.9.0
	 */
	public function unextendView($view, $view_extension) {
		global $CONFIG;
	
		if (!isset($CONFIG->views)) {
			return FALSE;
		}
	
		if (!isset($CONFIG->views->extensions)) {
			return FALSE;
		}
	
		if (!isset($CONFIG->views->extensions[$view])) {
			return FALSE;
		}
	
		$priority = array_search($view_extension, $CONFIG->views->extensions[$view]);
		if ($priority === FALSE) {
			return FALSE;
		}
	
		unset($CONFIG->views->extensions[$view][$priority]);
	
		return TRUE;
	}
}