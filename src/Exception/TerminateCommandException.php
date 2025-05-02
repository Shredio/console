<?php declare(strict_types = 1);

namespace Shredio\Console\Exception;

use Exception;

final class TerminateCommandException extends Exception
{

	public function __construct(
		public readonly int $exitCode = 0,
	)
	{
		parent::__construct('Command was terminated, but exception was not caught.');
	}

}
