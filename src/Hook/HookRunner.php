<?php declare(strict_types = 1);

namespace Shredio\Console\Hook;

use ReflectionAttribute;
use ReflectionClass;
use RuntimeException;
use Shredio\Console\Attribute\ConsoleHook;
use Shredio\Console\Command as ShredioCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class HookRunner
{

	/** @var list<array{callable, HookType}> */
	private array $hooks;

	public function __construct(object $object)
	{
		$this->hooks = $this->getHooks($object);
	}

	public function startup(InputInterface $input, OutputInterface $output): callable
	{
		$callbacks = [];

		foreach ($this->hooks as [$callback, $type]) {
			if ($type !== HookType::Startup) {
				continue;
			}

			$ret = $callback($input, $output);

			if (is_callable($ret)) {
				$callbacks[] = $ret;
			}
		}

		return static function () use ($callbacks, $input, $output): void {
			foreach ($callbacks as $callback) {
				$callback($input, $output);
			}
		};
	}

	public function exception(Throwable $exception, InputInterface $input, OutputInterface $output): ?int
	{
		foreach ($this->hooks as [$callback, $type]) {
			if ($type !== HookType::Exception) {
				continue;
			}

			$code = $callback($exception, $input, $output);

			if ($code !== null) {
				return $code;
			}
		}

		return null;
	}

	/**
	 * @return list<array{callable, HookType}>
	 */
	private function getHooks(object $object): array
	{
		$hooks = [];
		$reflection = new ReflectionClass($object);

		foreach ($reflection->getMethods() as $method) {
			if (in_array($method->getDeclaringClass()->name, [Command::class, ShredioCommand::class], true)) {
				continue;
			}

			if (in_array($method->name, ['__construct', '__destruct', '__invoke'], true)) {
				continue;
			}

			/** @var ConsoleHook|null $attribute */
			$attribute = ($method->getAttributes(ConsoleHook::class)[0] ?? null)?->newInstance();

			if (!$attribute) {
				continue;
			}

			if (!$method->isPublic()) {
				throw new RuntimeException(sprintf('Method %s::%s must be public.', $reflection->name, $method->name));
			}

			$hooks[] = [
				$object->{$method->name}(...),
				$attribute->type,
			];
		}

		foreach ($reflection->getAttributes(ConsoleHookSubscriber::class, ReflectionAttribute::IS_INSTANCEOF) as $attr) {
			/** @var ConsoleHookSubscriber $attribute */
			$attribute = $attr->newInstance();

			foreach ($attribute->getHooks() as $type => $callback) {
				$hooks[] = [
					$callback,
					$type,
				];
			}
		}

		return $hooks;
	}

}
