<?php declare(strict_types = 1);

namespace Shredio\Console\Hook;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface ConsoleHookSubscriber
{

	/**
	 * @return iterable<HookType, callable(OutputInterface, InputInterface): (void|callable)>
	 */
	public function getHooks(): iterable;

}
