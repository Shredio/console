<?php declare(strict_types = 1);

namespace Shredio\Console\Configurator;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

final readonly class CommandDefinition
{

	public function __construct(
		/** @var non-empty-string */
		public string $name,
		/** @var InputArgument[] */
		public array $arguments = [],
		/** @var InputOption[] */
		public array $options = [],
		/** @var ?non-empty-string */
		public ?string $description = null,
		/** @var ?non-empty-string */
		public ?string $help = null
	) {
	}

}
