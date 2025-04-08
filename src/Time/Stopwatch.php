<?php declare(strict_types = 1);

namespace Shredio\Console\Time;

use RuntimeException;

final class Stopwatch
{

	private ?float $start = null;

	public function start(): void
	{
		$this->start = microtime(true);
	}

	public function isStarted(): bool
	{
		return $this->start !== null;
	}

	public function lap(): TimeRecord
	{
		$start = $this->start;

		if ($start === null) {
			throw new RuntimeException('Stopwatch has not been started.');
		}

		$end = microtime(true);
		$time = $end - $start;
		$this->start = $end;

		return new TimeRecord($time);
	}

}
