<?php declare(strict_types = 1);

namespace Shredio\Console\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Option
{

	/**
	 * @param non-empty-string|null $name Option name. Property name by default
	 * @param non-empty-string|mixed[]|null $shortcut Option shortcut
	 * @param non-empty-string|null $description Option description
	 * @param int<0, 31>|null $mode Option mode, {@see InputOption} constants
	 * @param string[] $suggestedValues Option suggested values
	 */
	public function __construct(
		public ?string $name = null,
		public string|array|null $shortcut = null,
		public ?string $description = null,
		public ?int $mode = null,
		public array $suggestedValues = []
	) {
	}

}
