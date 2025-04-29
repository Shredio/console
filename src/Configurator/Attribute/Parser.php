<?php declare(strict_types = 1);

namespace Shredio\Console\Configurator\Attribute;

use BackedEnum;
use LogicException;
use ReflectionClass;
use ReflectionProperty;
use Shredio\Console\Attribute\Argument;
use Shredio\Console\Attribute\CommandHelp;
use Shredio\Console\Attribute\Option;
use Shredio\Console\Command;
use Shredio\Console\Configurator\CommandDefinition;
use Shredio\Console\Exception\ConfiguratorException;
use Shredio\Core\Common\Reflection\ReflectionHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

final class Parser
{

	/**
	 * @param ReflectionClass<object> $reflection
	 * @return CommandDefinition
	 */
	public function parse(ReflectionClass $reflection): CommandDefinition
	{
		$attribute = $this->getAttribute($reflection, AsCommand::class);

		if (!$attribute) {
			throw new ConfiguratorException('Command must have `AsCommand` attribute!');
		}

		if ($attribute->name === '') {
			throw new ConfiguratorException('Command name cannot be empty!');
		}

		$commandHelp = $this->getAttribute($reflection, CommandHelp::class);

		return new CommandDefinition(
			name: $attribute->name,
			arguments: $this->parseArguments($reflection),
			options: $this->parseOptions($reflection),
			description: $attribute->description === '' ? null : $attribute->description,
			help: $commandHelp?->help
		);
	}

	public function fillProperties(Command $command, InputInterface $input): void
	{
		$reflection = new ReflectionClass($command);

		foreach ($reflection->getProperties() as $property) {
			$attribute = $this->getAttribute($property, Argument::class);
			if ($attribute === null) {
				continue;
			}

			if ($input->hasArgument($attribute->name ?? $property->getName())) {
				$property->setValue(
					$command,
					$this->typecast($input->getArgument($attribute->name ?? $property->getName()), $property)
				);
			}
		}

		foreach ($reflection->getProperties() as $property) {
			$attribute = $this->getAttribute($property, Option::class);
			if ($attribute === null) {
				continue;
			}

			if ($input->hasOption($attribute->name ?? $property->getName())) {
				$value = $this->typecast($input->getOption($attribute->name ?? $property->getName()), $property);

				if ($value !== null || $this->getPropertyType($property)->allowsNull()) {
					$property->setValue($command, $value);
				}
			}
		}
	}

	/**
	 * @param ReflectionClass<object> $reflection
	 * @return mixed[]
	 */
	private function parseArguments(ReflectionClass $reflection): array
	{
		$result = [];
		$arrayArgument = null;
		foreach ($reflection->getProperties() as $property) {
			$attribute = $this->getAttribute($property, Argument::class);
			if ($attribute === null) {
				continue;
			}

			$type = $this->getPropertyType($property);

			$isOptional = $property->hasDefaultValue() || $type->allowsNull();
			$isArray = $type->getName() === 'array';
			$mode = match (true) {
				$isArray && !$isOptional => InputArgument::IS_ARRAY | InputArgument::REQUIRED,
				$isArray && $isOptional => InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
				$isOptional => InputArgument::OPTIONAL,
				default => InputArgument::REQUIRED
			};

			$argument = new InputArgument(
				name: $attribute->name ?? $property->getName(),
				mode: $mode,
				description: (string) $attribute->description,
				default: $property->hasDefaultValue() ? $this->normalizeInputValue($property->getDefaultValue()) : null,
				suggestedValues: $attribute->suggestedValues
			);

			if ($arrayArgument !== null && $isArray) {
				throw new ConfiguratorException('There must be only one array argument!');
			}

			// It must be used at the end of the argument list.
			if ($isArray) {
				$arrayArgument = $argument;
				continue;
			}
			$result[] = $argument;
		}

		if ($arrayArgument !== null) {
			$result[] = $arrayArgument;
		}

		return $result;
	}

	/**
	 * @param ReflectionClass<object> $reflection
	 * @return mixed[]
	 */
	private function parseOptions(ReflectionClass $reflection): array
	{
		$result = [];
		foreach ($reflection->getProperties() as $property) {
			$attribute = $this->getAttribute($property, Option::class);
			if ($attribute === null) {
				continue;
			}

			$type = $this->getPropertyType($property);
			$mode = $attribute->mode;

			if ($mode === null) {
				$mode = $this->guessOptionMode($type, $property);
			}

			if ($mode === InputOption::VALUE_NONE || $mode === InputOption::VALUE_NEGATABLE) {
				if ($type->getName() !== 'bool') {
					throw new ConfiguratorException(
						'Options properties with mode `VALUE_NONE` or `VALUE_NEGATABLE` must be bool!'
					);
				}
			}

			$hasDefaultValue = $attribute->mode !== InputOption::VALUE_NONE && $property->hasDefaultValue();

			$result[] = new InputOption(
				name: $attribute->name ?? $property->getName(),
				shortcut: $attribute->shortcut,
				mode: $mode,
				description: (string) $attribute->description,
				default: $hasDefaultValue ? $this->normalizeInputValue($property->getDefaultValue()) : null,
				suggestedValues: $attribute->suggestedValues
			);
		}

		return $result;
	}

	/**
	 * @return array<string|int|float|bool|null>|string|int|float|bool|null
	 */
	private function normalizeInputValue(mixed $value): array|string|int|float|bool|null
	{
		if ($value instanceof BackedEnum) {
			return $value->value;
		}

		if (is_array($value)) {
			return array_map( // @phpstan-ignore return.type (Recursive array type)
				fn (mixed $item): array|string|int|float|bool|null => $this->normalizeInputValue($item),
				$value
			);
		}

		if (is_scalar($value)) {
			return $value;
		}

		if ($value === null) {
			return null;
		}

		if ($value instanceof \DateTimeInterface) {
			return $value->format('c');
		}

		throw new LogicException(sprintf('Invalid value type: %s', get_debug_type($value)));
	}

	private function typecast(mixed $value, ReflectionProperty $property): mixed
	{
		$type = $property->hasType() ? $property->getType() : null;

		if (!$type instanceof \ReflectionNamedType || $value === null) {
			return $value;
		}

		if (!$type->isBuiltin() && \enum_exists($type->getName())) {
			/** @var class-string<\BackedEnum> $enum */
			$enum = $type->getName();

			try {
				return $enum::from($value);
			} catch (\Throwable) {
				throw new ConfiguratorException(\sprintf('Wrong option value. Allowed options: `%s`.', \implode(
					'`, `',
					\array_map(static fn (\BackedEnum $item): string => (string) $item->value, $enum::cases())
				)));
			}
		}

		return match ($type->getName()) {
			'int' => (int) $value,
			'string' => (string) $value,
			'bool' => (bool) $value,
			'float' => (float) $value,
			'array' => (array) $value,
			default => $value
		};
	}

	private function getPropertyType(ReflectionProperty $property): \ReflectionNamedType
	{
		if (!$property->hasType()) {
			throw new ConfiguratorException(
				\sprintf('Please, specify the type for the `%s` property!', $property->getName())
			);
		}

		$type = $property->getType();

		if ($type instanceof \ReflectionIntersectionType) {
			throw new ConfiguratorException(\sprintf('Invalid type for the `%s` property.', $property->getName()));
		}

		if ($type instanceof \ReflectionUnionType) {
			foreach ($type->getTypes() as $type) {
				if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
					return $type;
				}
			}
		}

		if ($type instanceof \ReflectionNamedType && !$type->isBuiltin() && \enum_exists($type->getName())) {
			return $type;
		}

		if ($type instanceof \ReflectionNamedType && $type->isBuiltin() && $type->getName() !== 'object') {
			return $type;
		}

		throw new ConfiguratorException(\sprintf('Invalid type for the `%s` property.', $property->getName()));
	}

	/**
	 * @return int<0, 31>
	 */
	private function guessOptionMode(\ReflectionNamedType $type, ReflectionProperty $property): int
	{
		$isOptional = $type->allowsNull() || $property->hasDefaultValue();

		return match (true) {
			$type->getName() === 'bool' => InputOption::VALUE_NEGATABLE,
			$type->getName() === 'array' && $isOptional => InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
			$type->getName() === 'array' && !$isOptional => InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
			$type->allowsNull() || $property->hasDefaultValue() => InputOption::VALUE_OPTIONAL,
			default => InputOption::VALUE_REQUIRED
		};
	}

	/**
	 * @template T of object
	 * @param ReflectionClass<object>|ReflectionProperty $reflection
	 * @param class-string<T> $class
	 * @return T|null
	 */
	private function getAttribute(ReflectionClass|ReflectionProperty $reflection, string $class): ?object
	{
		return ($reflection->getAttributes($class)[0] ?? null)?->newInstance();
	}

}
