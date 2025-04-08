<?php declare(strict_types = 1);

namespace Shredio\Console\Extension;

use Shredio\Console\Attribute\ConsoleHook;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait ConsoleItemCounter // @phpstan-ignore trait.unused
{

	private int $processedItems = 0;

	#[ConsoleHook]
	public function __hookForItemCounter(InputInterface $input, OutputInterface $output): callable
	{
		$this->processedItems = 0;

		return function () use ($output): void {
			if ($this->processedItems > 0) {
				$output->writeln(sprintf('Processed <info>%d</info> items.', $this->processedItems));
			}
		};
	}

	private function incrementProcessedItem(): void
	{
		$this->processedItems++;
	}

}
