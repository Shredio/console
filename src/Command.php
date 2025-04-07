<?php declare(strict_types = 1);

namespace Shredio\Console;

use ReflectionClass;
use RuntimeException;
use Shredio\Console\Attribute\SigtermListener;
use Shredio\Console\Configurator\Attribute\Parser;
use Shredio\Console\Time\Stopwatch;
use Shredio\Console\Time\TimeRecord;
use Shredio\Console\Trait\HelpersTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class Command extends \Symfony\Component\Console\Command\Command
{

	public static bool $defaultDiagnostics = true;

	use HelpersTrait;

	private bool $isTerminating = false;

	private bool $sigtermListener = false;

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
		if ($this->getAttribute(SigtermListener::class)) {
			$this->listenToSigterm();
		}

		$stopwatch = new Stopwatch();

		$this->input = $input;
		$this->output = new SymfonyStyle($input, $output);

		(new Parser())->fillProperties($this, $input);

		$code = $this->invoke($input, $output);

		$this->printExecutionTime($stopwatch->lap());
		$this->printMemoryUsage(memory_get_peak_usage(true));

		return is_int($code) ? $code : self::SUCCESS;
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
		if (self::$defaultDiagnostics) {
			return true;
		}

		return (bool) $this->input?->getOption('diagnostics');
	}

	/**
	 * @return mixed
	 */
	abstract protected function invoke(InputInterface $input, OutputInterface $output);

	/**
	 * @template T of object
	 * @param class-string<T> $attribute
	 * @return T|null
	 */
	private function getAttribute(string $attribute): ?object
	{
		$reflection = new ReflectionClass($this);

		foreach ($reflection->getAttributes($attribute) as $attribute) {
			/** @var T */
			return $attribute->newInstance();
		}

		return null;
	}

	private function listenToSigterm(): void
	{
		if (!function_exists('pcntl_signal')) {
			throw new RuntimeException('pcntl_signal function is not available.');
		}

		pcntl_async_signals(true);
		pcntl_signal(SIGTERM, function (): void {
			$this->isTerminating = true;
		});

		$this->sigtermListener = true;
	}

	protected function isTerminating(): bool
	{
		if (!$this->sigtermListener) {
			throw new RuntimeException(sprintf('Use attribute %s to enable SIGTERM listener', SigtermListener::class));
		}

		return $this->isTerminating;
	}

}
