<?php declare(strict_types = 1);

namespace Shredio\Console\Time;

final class Stopwatch
{

	private float $start;

	public function __construct()
	{
		$this->start = microtime(true);
	}

	public function lap(): TimeRecord
	{
		return new TimeRecord(microtime(true) - $this->start);
	}

}
