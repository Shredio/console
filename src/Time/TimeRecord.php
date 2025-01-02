<?php declare(strict_types = 1);

namespace Shredio\Console\Time;

final readonly class TimeRecord
{

	public function __construct(
		public float $value,
	)
	{
	}

	public function inSeconds(int $decimals = 2): string
	{
		return number_format($this->value, $decimals) . 's';
	}

}
