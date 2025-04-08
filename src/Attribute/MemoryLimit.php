<?php declare(strict_types = 1);

namespace Shredio\Console\Attribute;

use Attribute;
use Shredio\Console\Hook\ConsoleHookSubscriber;
use Shredio\Console\Hook\HookType;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class MemoryLimit implements ConsoleHookSubscriber
{

	public function __construct(
		public string $limit,
	)
	{
	}

	public function getHooks(): iterable
	{
		yield HookType::Startup => function (): void {
			ini_set('memory_limit', $this->limit);
		};
	}

}
