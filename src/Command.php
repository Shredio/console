<?php declare(strict_types = 1);

namespace Shredio\Console;

use ReflectionClass;
use Shredio\Console\Configurator\Attribute\Parser;
use Shredio\Console\Exception\TerminateCommandException;
use Shredio\Console\Hook\HookRunner;
use Shredio\Console\Time\Stopwatch;
use Shredio\Console\Time\TimeRecord;
use Shredio\Console\Trait\HelpersTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

abstract class Command extends \Symfony\Component\Console\Command\Command
{

	use HelpersTrait;

	protected function configure(): void
	{
		$this->getDefinition()->addOption(
			new InputOption('diagnostics', null, InputOption::VALUE_NONE, 'Enable diagnostics'),
		);

		$result = (new Parser())->parse(new ReflectionClass($this));

		$this->setName($result->name);
		$this->setHelp((string) $result->help);
		if ($result->description) {
			$this->setDescription($result->description);
		}

		foreach ($result->options as $option) {
			$this->getDefinition()->addOption($option);
		}

		foreach ($result->arguments as $argument) {
			$this->getDefinition()->addArgument($argument);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$hookRunner = new HookRunner($this);
		$shutdown = $hookRunner->startup($input, $output);

		$stopwatch = new Stopwatch();
		$stopwatch->start();

		$this->input = $input;
		$this->output = new SymfonyStyle($input, $output);

		(new Parser())->fillProperties($this, $input);

		try {
			$code = $this->invoke($input, $output);
		} catch (TerminateCommandException $exception) {
			$code = $exception->exitCode;
		} catch (Throwable $exception) {
			$code = $hookRunner->exception($exception, $input, $output);

			if ($code === null) {
				throw $exception;
			}
		}

		$shutdown();

		$this->printExecutionTime($stopwatch->lap());
		$this->printMemoryUsage(memory_get_peak_usage(true));

		if (is_int($code)) {
			return $code;
		}

		if (is_bool($code)) {
			return $code ? self::SUCCESS : self::FAILURE;
		}

		return self::SUCCESS;
	}

	protected function interact(InputInterface $input, OutputInterface $output)
	{
		parent::interact($input, $output);

		(new PromptArguments())->promptMissedArguments($this, $input, $output);
	}

	protected function printMemoryUsage(float $memoryUsage, ?string $section = null): void
	{
		if (!$this->canPrintDiagnostics()) {
			return;
		}

		$str = '';

		if ($section) {
			$str .= sprintf('[%s] ', $section);
		}

		$str .= 'Memory usage: ';
		$str .= number_format($memoryUsage / 1024 / 1024, 2) . ' MB';

		$this->output?->writeln($str);
	}

	protected function printExecutionTime(TimeRecord $record, ?string $section = null): void
	{
		if (!$this->canPrintDiagnostics()) {
			return;
		}

		$str = '';

		if ($section) {
			$str .= sprintf('[%s] ', $section);
		}

		$str .= 'Execution time: ';
		$str .= $record->inSeconds();

		$this->output?->writeln($str);
	}

	private function canPrintDiagnostics(): bool
	{
		$enabledByUser = (bool) $this->input?->getOption('diagnostics');

		if ($enabledByUser) {
			return true;
		}

		return $this->isDiagnosticsEnabledByDefault();
	}

	protected function isDiagnosticsEnabledByDefault(): bool
	{
		return false;
	}

	/**
	 * @return int|bool|void
	 */
	abstract protected function invoke(InputInterface $input, OutputInterface $output);

}
