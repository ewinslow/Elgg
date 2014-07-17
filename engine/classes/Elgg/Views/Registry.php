<?php
namespace Elgg\Views;

use Elgg\EventsService as Events;
use Elgg\Filesystem\File;
use Elgg\Filesystem\Directory;
use Elgg\Logger;
use Elgg\PluginHooksService as Hooks;
use Elgg\Http\Input;
use Elgg\Views\Exception;


/**
 * WARNING: API IN FLUX. DO NOT USE DIRECTLY.
 *
 * Use the elgg_* versions instead.
 *
 * @access private
 * @since 2.0.0
 */
class Registry {
	/** @var Hooks */
	private $hooks;
	
	/** @var Events */
	private $events;
	
	/** @var Logger */
	private $logger;
	
	/** @var Input */
	private $input;
	
	/** @var \stdClass */
	private $config;
	
	/** @var Viewtype[] */
	private $viewtypes = array();
	
	/** @var View[] */
	private $views = array();

	/** @var Viewtype */
	private $currentViewtype = null;
	
	/** @var callable */
	private $template_handler = NULL;

	/**
	 * Constructor
	 *
	 * @param \stdClass $config  The global Elgg config
	 * @param Events    $events  The events service
	 * @param Hooks     $hooks   The hooks service
	 * @param Input     $input   The HTTP Input
	 * @param Logger    $logger  Logger
	 */
	public function __construct(
			\stdClass $config,
			Events $events,
			Hooks $hooks,
			Input $input,
			Logger $logger) {
		$this->config = $config;
		$this->events = $events;
		$this->hooks = $hooks;
		$this->input = $input;
		$this->logger = $logger;
	}

	
	/**
	 * Manually set the viewtype.
	 *
	 * View types are detected automatically.  This function allows
	 * you to force subsequent views to use a different viewtype.
	 * 
	 * @param string $viewtype The new viewtype
	 * 
	 * @return void
	 */
	public function setCurrentViewtype($viewtype = '') {
		$this->currentViewtype = $this->getOrCreateViewtype($viewtype);
	}
	
	/**
	 * Return the current view type.
	 *
	 * Viewtypes are automatically detected and can be set with
	 * $_REQUEST['view'] or {@link elgg_set_viewtype()}.
	 *
	 * @internal Viewtype is determined in this order:
	 *  - $CURRENT_SYSTEM_VIEWTYPE Any overrides by {@link elgg_set_viewtype()}
	 *  - $CONFIG->view  The default view as saved in the DB.
	 *
	 * @return Viewtype
	 */
	public function getCurrentViewtype() {
		if ($this->currentViewtype != null) {
			return $this->currentViewtype;
		}

		try {
			$viewtypeInput = $this->input->get('view', '', false);
			return $this->getOrCreateViewtype($viewtypeInput);
		} catch (\Exception $e) {}
		
		try {
			return $this->getOrCreateViewtype($this->config->view);
		} catch (\Exception $e) {}
		
		return $this->getOrCreateViewtype('default');
	}

	/**
	 * Auto-registers views from a location.
	 *
	 * @note Views in plugin/views/ are automatically registered for active plugins.
	 * Plugin authors would only need to call this if optionally including
	 * an entire views structure.
	 *
	 * @param string    $view_base The base of the view name without the view type
	 * @param Directory $folder    The folder to begin looking in
	 * @param string    $viewtype  The type of view we're looking at (default, rss, etc)
	 * 
	 * @return void
	 * @access private
	 */
	public function registerViews($view_base, Directory $folder, $viewtype) {
		$viewtype = $this->getOrCreateViewtype($viewtype);
		
		foreach ($folder->getFiles() as $file) {
			if (!$file->isPrivate()) {
				$this->registerView($view_base, $file, $viewtype);
			}
		}
	}
	
	/**
	 * @param string   $base
	 * @param File     $file
	 * @param Viewtype $viewtype
	 * 
	 * @return View
	 */
	private function registerView($base, File $file, Viewtype $viewtype) {
		$name = '';
		
		$base = trim($base, '/');
		if (!empty($base)) {
			$name .= "$base/";
		}
		
		$dirname = trim($file->getDirname(), "/");
		if (!empty($dirname)) {
			$name .= "$dirname/";
		}
		
		$name .= $file->getBasename('.php');
		
		return $this->getView($name)->setLocation($viewtype, $file);
	}
	
	/**
	 * @param string $name String ID for the viewtype.
	 * 
	 * @return Viewtype
	 */
	public function getOrCreateViewtype($name) {
		if (isset($this->viewtypes[$name])) {
			$viewtype = $this->viewtypes[$name];
		} else {
			$viewtype = Viewtype::create($name);
			$this->viewtypes[$name] = $viewtype;
		}

		return $viewtype;
	}
	
	
	/**
	 * @param string $name The viewtype to check for.
	 * 
	 * @return bool
	 */
	public function isRegisteredViewtype($name) {
		return isset($this->viewtypes[$name]);
	}
	
	/**
	 * @param Viewtype $viewtype
	 * 
	 * @access private
	 */
	public function registerViewtypeFallback(Viewtype $viewtype) {
		$default = $this->getOrCreateViewtype('default');

		$viewtype->setFallback($default);
	}

	/**
	 * Display a view with a deprecation notice. No missing view NOTICE is logged
	 *
	 * @see elgg_view()
	 *
	 * @param string  $view       The name and location of the view to use
	 * @param array   $vars       Variables to pass to the view
	 * @param string  $suggestion Suggestion with the deprecation message
	 * @param string  $version    Human-readable *release* version: 1.7, 1.8, ...
	 *
	 * @return string The parsed view
	 * @access private
	 */
	public function renderDeprecatedView($view, array $vars, $suggestion, $version) {
		$rendered = $this->renderView($view, $vars, false, '', false);
		if ($rendered) {
			elgg_deprecated_notice("The $view view has been deprecated. $suggestion", $version, 3);
		}
		return $rendered;
	}
	
	/**
	 * @param string $name
	 * 
	 * @return View
	 */
	public function getView($name) {
		if (!isset($this->views[$name])) {
			$this->views[$name] = new View();
		}
		
		return $this->views[$name];
	}
	
	/**
	 * @param string  $view
	 * @param array   $vars
	 * @param string  $viewtype
	 * @param boolean $bypass
	 * @param boolean $issue_missing_notice
	 * 
	 * @access private
	 */
	public function renderView($view, array $vars = array(), $viewtype = '', $bypass = false, $issue_missing_notice = true) {
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
		
		if (empty($viewtype)) {
			$viewtype = $this->getCurrentViewtype();
		} else {
			$viewtype = $this->getOrCreateViewtype($viewtype);
		}

		// Trigger the pagesetup event
		if (!isset($this->config->pagesetupdone) && empty($this->config->boot_complete)) {
			$this->config->pagesetupdone = true;
			$this->events->trigger('pagesetup', 'system');
		}
		
		// If it's been requested, pass off to a template handler instead
		if (!$bypass && isset($this->template_handler)) {
			return call_user_func($this->template_handler, $view, $vars);
		}

		$content = $this->getView($view)->render($vars, $viewtype);
		
		$params = array('view' => "$view", 'vars' => $vars, 'viewtype' => "$viewtype");
		return $this->hooks->trigger('view', "$view", $params, $content);
	}

	/**
	 * Configure a custom template handler besides renderView
	 * 
	 * @param string $function_name The custom callback for handling rendering.
	 * 
	 * @return boolean whether the template handler was accepted
	 */
	public function setTemplateHandler($function_name) {
		if (!is_callable($function_name)) {
			return false;
		}
		
		$this->template_handler = $function_name;
		return true;
	}
	
	/**
	 * Register a plugin's views
	 *
	 * @param Directory $dir Base path of the plugin's views
	 *
	 * @access private
	 */
	public function registerViewsDirectory(Directory $dir) {
		// plugins don't have to have views.
		if (!$dir->isDirectory('/')) {
			return;
		}
		
		// but if they do, they have to be readable
		$handle = opendir("$dir");
		if (!$handle) {
			throw new Exception\UnreadableDirectory("$dir");
		}
		
		// TODO(ewinslow): Add a directory method that returns shallow list of folders as a collection
		$view_type_names = [];
		while (($view_type_name = readdir($handle)) !== false) {
			// ignore private-directories and non-directories
			if (substr($view_type_name, 0, 1) !== '.' && $dir->isDirectory($view_type_name)) {
				try {
					$view_type_dir = $dir->chroot("/$view_type_name/");
					$this->registerViews('', $view_type_dir, $view_type_name);
				} catch (\Exception $e) {
					throw new Exception\UnreadableDirectory("$view_type_dir");
				}
			}
		}
		
	}
	
	/**
	 * Get views overridden by setViewLocation() calls.
	 *
	 * @return array
	 *
	 * @access private
	 */
	public function getOverriddenLocations() {
		return $this->overriden_locations;
	}
	
	/**
	 * Set views overridden by setViewLocation() calls.
	 *
	 * @param array $locations
	 * @return void
	 *
	 * @access private
	 */
	public function setOverriddenLocations(array $locations) {
		$this->overriden_locations = $locations;
	}
}
