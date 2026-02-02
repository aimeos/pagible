<?php
namespace potrans;

// Disable deprecated warnings
error_reporting(E_ALL & ~E_DEPRECATED);

use potrans\commands\DeepLTranslatorCommand;
use potrans\commands\GoogleTranslatorCommand;
use Symfony\Component\Console\Application;

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
	require_once __DIR__ . '/../../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../../autoload.php')) {
	require_once __DIR__ . '/../../../../autoload.php';
}

$application = new Application();
$application->addCommands(
	[
		new GoogleTranslatorCommand('google'),
		new DeepLTranslatorCommand('deepl'),
	]
);
$application->run();