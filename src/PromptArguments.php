<?php declare(strict_types = 1);

namespace Shredio\Console;

use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Shredio\Console\Attribute\Question;
use Shredio\Core\Common\Reflection\ReflectionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class PromptArguments
{

	public function promptMissedArguments(Command $command, InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);

		foreach ($command->getDefinition()->getArguments() as $argument) {
			// Skip default argument "the command to execute"
			if ($argument->getName() === 'command') {
				continue;
			}

			if ($argument->isRequired() && $input->getArgument($argument->getName()) === null) {
				$input->setArgument(
					$argument->getName(),
					$io->ask($this->getQuestion($command, $argument))
				);
			}
		}
	}

	private function getQuestion(Command $command, InputArgument $argument): string
	{
		$reflection = new \ReflectionClass($command);

		foreach ($reflection->getAttributes(Question::class) as $questionAttribute) {
			/** @var Question $question */
			$question = $questionAttribute->newInstance();
			if ($question->argument === null) {
				throw new RuntimeException(
					'When using a `Question` attribute on a console command class, the argument parameter is required.'
				);
			}

			if ($argument->getName() === $question->argument) {
				return $question->question;
			}
		}

		foreach ($reflection->getProperties() as $property) {
			$question = $this->getAttribute($property, Question::class);
			if ($question === null) {
				continue;
			}

			if ($argument->getName() === ($question->argument ?? $property->getName())) {
				return $question->question;
			}
		}

		return \sprintf('Please provide a value for the `%s` argument', $argument->getName());
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
