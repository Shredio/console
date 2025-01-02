<?php declare(strict_types = 1);

namespace Shredio\Console\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class CommandHelp
{

	/**
	 * @param non-empty-string $help Command help
	 */
	public function __construct(
		public string $help,
	)
	{
	}

}
