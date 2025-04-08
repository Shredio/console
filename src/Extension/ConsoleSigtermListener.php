<?php declare(strict_types = 1);

namespace Shredio\Console\Extension;

use RuntimeException;
use Shredio\Console\Attribute\ConsoleHook;
use Shredio\Console\Time\Stopwatch;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait ConsoleSigtermListener // @phpstan-ignore trait.unused
{

	private bool $isTerminating = false;

	#[ConsoleHook]
	public function __hookForSigterm(InputInterface $input, OutputInterface $output): callable
	{
		if (!function_exists('pcntl_signal')) {
			throw new RuntimeException('pcntl_signal function is not available.');
		}

		$stopwatch = new Stopwatch();

		pcntl_async_signals(true);
		pcntl_signal(SIGTERM, function () use ($output, $stopwatch): void {
			$this->isTerminating = true;

			$output->writeln('Received SIGTERM signal. Terminating gracefully...');
			$stopwatch->start();
		});

		return static function (InputInterface $input, OutputInterface $output) use ($stopwatch): void {
			if ($stopwatch->isStarted()) {
				$time = $stopwatch->lap();
				$output->writeln(sprintf('Graceful termination took <info>%s</info> seconds.', $time->inSeconds()));
			}
		};
	}

}
