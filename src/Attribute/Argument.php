<?php declare(strict_types = 1);

namespace Shredio\Console\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Argument
{

	/**
	 * @param ?non-empty-string $name Argument name. Property name by default
	 * @param ?non-empty-string $description Argument description
	 * @param string[] $suggestedValues Argument suggested values
	 */
	public function __construct(
		public ?string $name = null,
		public ?string $description = null,
		public array $suggestedValues = [],
	) {
	}

}
