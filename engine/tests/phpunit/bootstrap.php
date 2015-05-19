<?php

use Zend\Mail\Transport\InMemory as InMemoryTransport;

require_once __DIR__ . '/../../../autoloader.php';

date_default_timezone_set('America/Los_Angeles');

error_reporting(E_ALL | E_STRICT);

/**
 * Get/set an Application for testing purposes
 *
 * @param \Elgg\Application $app Elgg Application
 * @return \Elgg\Application
 */
function _elgg_testing_application(\Elgg\Application $app = null) {
	static $inst;
	if ($app) {
		$inst = $app;
	}
	return $inst;
}

/**
 * This is here as a temporary solution only. Instead of adding more global
 * state to this file as we migrate tests, try to refactor the code to be
 * testable without global state.
 */
global $CONFIG;
$CONFIG = (object)[
	'dbprefix' => 'elgg_',
	'boot_complete' => false,
	'wwwroot' => 'http://localhost/',
	'dataroot' => __DIR__ . '/test_files/dataroot/',
	'site_guid' => 1,
	'AutoloaderManager_skip_storage' => true,
];

// PHPUnit will serialize globals between tests, so let's not introduce any globals here.
call_user_func(function () use ($CONFIG) {
	$sp = new \Elgg\Di\ServiceProvider(new \Elgg\Config($CONFIG));
	$sp->setValue('mailer', new InMemoryTransport());

	$app = new \Elgg\Application($sp);
	$app->loadCore();
	_elgg_testing_application($app);
});
