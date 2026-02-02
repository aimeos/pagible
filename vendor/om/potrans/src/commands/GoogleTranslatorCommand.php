<?php

namespace potrans\commands;

use Google\Cloud\Translate\V3\Client\TranslationServiceClient;
use potrans\PoTranslator;
use potrans\translator\GoogleTranslator;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class GoogleTranslatorCommand extends Command {

	protected function configure(): void {
		$this->addArgument('input', InputArgument::REQUIRED, 'Input PO file path')
			->addArgument('output', InputArgument::OPTIONAL, 'Output PO, MO files directory')
			->addOption('from', null, InputOption::VALUE_REQUIRED, 'Source language (default: en)', 'en')
			->addOption('to', null, InputOption::VALUE_REQUIRED, 'Target language (default: derived from input file name)')
			->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Root directory (default: current working directory)')
			->addOption('force', null, InputOption::VALUE_NONE, 'Force re-translate including translated sentences')
			->addOption('only', null, InputOption::VALUE_NONE, 'Create only PO file, no MO file')
			->addOption('wait', null, InputOption::VALUE_REQUIRED, 'Wait between translations in milliseconds', false)
			->addOption('credentials', null, InputOption::VALUE_REQUIRED, 'Path to Google Credentials file', './credentials.json')
			->addOption('project', null, InputOption::VALUE_REQUIRED, 'Google Cloud Project ID <comment>[default: project_id from credentials.json]</comment>')
			->addOption('location', null, InputOption::VALUE_REQUIRED, 'Google Cloud Location', 'global')
			->addOption('translator', null, InputOption::VALUE_OPTIONAL, 'Path to custom translator instance', null)
			->addOption('cache', null, InputOption::VALUE_NEGATABLE, 'Load from cache or not', true);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {

		try {

			$dir = $input->getOption('dir') ?? getcwd();

			// Input PO file
			$inputFile = $input->getArgument('input');
			if ($inputFile[0] !== '/') {
				$inputFile = $dir . '/' . $inputFile;
			}

			if (!file_exists($inputFile)) {
				throw new RuntimeException(sprintf('Input file "%s" not found', $inputFile));
			}

			// Output directory
			$outputDir = $input->getArgument('output') ?? dirname($inputFile);
			if ($outputDir[0] !== '/') {
				$outputDir = $dir . '/' . $outputDir;
			}
			$outputDir = rtrim(realpath($outputDir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

			if (!is_dir($outputDir)) {
				throw new InvalidOptionException('Invalid directory path: ' . $outputDir);
			}

			// Read credentials file
			$credentials = $input->getOption('credentials');
			if (!is_file($credentials)) {
				throw new InvalidOptionException('Missing credentials or API key');
			}

			// Crete new Google translator
			$customTranslatorPath = $input->getOption('translator');
			if ($customTranslatorPath && file_exists($customTranslatorPath)) {
				$translator = require_once $customTranslatorPath;

				if (!$translator instanceof \potrans\translator\Translator) {
					throw new InvalidOptionException('Invalid translator instance: ' . $customTranslatorPath);
				}
			} else {
				$translator = new GoogleTranslator(
					new TranslationServiceClient([
						'credentials' => $credentials,
					]),
					$input->getOption('project') ?? json_decode(file_get_contents($credentials))->project_id,
					$input->getOption('location')
				);
			}

			// Setup caching
			$cache = $input->getOption('cache') ?
				new FilesystemAdapter('google', 3600, '.potrans/cache') :
				new NullAdapter();

			// Read params
			$from = (string) $input->getOption('from');
			$to = (string) $input->getOption('to');
			$force = (bool) $input->getOption('force');
			$wait = (int) $input->getOption('wait');

			$potrans = new PoTranslator($translator, $cache);
			$translations = $potrans->loadFile($inputFile);

			$progress = new ProgressBar($output, count($translations));

			// translator
			$output->writeln(
				[
					'------------------------------',
					' PO trans translator',
					'------------------------------',
					'<comment>Input</comment>: ' . $inputFile,
					'<comment>Translate</comment>: from ' . $from . ' to ' . $to,
					'<comment>Output dir</comment>: ' . $outputDir,
					'<comment>Translating progress</comment>:',
				]
			);

			$translated = 0; // counter
			foreach ($potrans->translate($from, $to, $force) as $sentence) {
				// verbose mode show everything
				if ($output->isVeryVerbose()) {
					$output->writeln(
						[
							'-------------------------------------------------------------------------',
							' > <info>' . $sentence->getOriginal() . '</info>',
							' > <comment>' . $sentence->getTranslation() . '</comment>',
						]
					);
				}

				// progress
				if (!$output->isVeryVerbose()) {
					$progress->advance();
				}

				$translated++;

				if ($wait) usleep($wait);
			}

			// translation done
			if (!$output->isVeryVerbose()) {
				$progress->finish();
				$output->writeln('');
			}

			$output->writeln('<comment>Translated :</comment> ' . $translated . ' sentences');

			// MO file output
			if (!$input->getOption('only')) {
				$moOutputFile = $outputDir . pathinfo($inputFile, PATHINFO_FILENAME) . '.mo';
				if ($output->isVeryVerbose()) {
					$output->writeln('<comment>Writing new MO File</comment>: ' . $moOutputFile);
				}
				$potrans->saveMoFile($moOutputFile);
			}

			// PO file output
			$poOutputFile = $outputDir . pathinfo($inputFile, PATHINFO_FILENAME) . '.po';
			if ($output->isVeryVerbose()) {
				$output->writeln('<comment>Writing new PO File</comment>: ' . $poOutputFile);
			}
			$potrans->savePoFile($poOutputFile);

			// done!
			$output->writeln('<info>DONE!</info>');
		} catch (Throwable $error) {
			$output->writeln(
				[
					'',
					sprintf("<error>ERROR: %s on %s at %s</error>", $error->getMessage(), $error->getFile(), $error->getLine()),
				]
			);
			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}

	public function getDescription(): string {
		return 'Translate PO file with Google Translator API';
	}

}
