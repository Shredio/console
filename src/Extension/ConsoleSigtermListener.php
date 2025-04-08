<?php declare(strict_types = 1);

namespace Shredio\Console\Extension;

use RuntimeException;
use Shredio\Console\Attribute\ConsoleHook;

trait ConsoleSigtermListener // @phpstan-ignore trait.unused
{

	private bool $isTerminating = false;

	#[ConsoleHook]
	public function __hookForSigterm(): void
	{
		if (!function_exists('pcntl_signal')) {
			throw new RuntimeException('pcntl_signal function is not available.');
		}

		pcntl_async_signals(true);
		pcntl_signal(SIGTERM, function (): void {
			$this->isTerminating = true;
		});
	}

}
