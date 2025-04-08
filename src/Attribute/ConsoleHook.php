<?php declare(strict_types = 1);

namespace Shredio\Console\Attribute;

use Attribute;
use Shredio\Console\Hook\HookType;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ConsoleHook
{

	public function __construct(
		public HookType $type = HookType::Startup,
	)
	{
	}

}
