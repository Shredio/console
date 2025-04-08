<?php declare(strict_types = 1);

namespace Shredio\Console\Extension;

use Shredio\Console\Attribute\ConsoleHook;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait ConsoleItemCounter // @phpstan-ignore trait.unused
{

	/** @var array<string, int> */
	private array $processedItems = [];

	#[ConsoleHook]
	public function __hookForItemCounter(InputInterface $input, OutputInterface $output): callable
	{
		$this->processedItems = [];

		return function () use ($output): void {
			foreach ($this->processedItems as $section => $count) {
				$output->writeln(sprintf('Processed <info>%d</info> <comment>%s</comment>.', $count, $section));
			}
		};
	}

	private function incrementProcessedItem(string $section = 'items'): void
	{
		$this->processedItems[$section] ??= 0;
		$this->processedItems[$section]++;
	}

}
