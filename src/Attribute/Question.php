<?php declare(strict_types = 1);

namespace Shredio\Console\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class Question
{

	public function __construct(
		public string $question,
		public ?string $argument = null
	) {
	}

}
