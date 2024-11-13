<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pmmp\TesterPlugin;

use pmmp\TesterPlugin\event\GrandchildAsyncEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;

final class AsyncEventConcurrencyTest extends Test{
	/**
	 * @var PromiseResolver[]
	 * @phpstan-var list<PromiseResolver<null>>
	 */
	private array $resolvers = [];

	private bool $activeExclusiveHandler = false;
	private bool $activeConcurrentHandler = false;

	private int $done = 0;

	public function getName() : string{
		return "Async Event Concurrency Lock";
	}

	public function getDescription() : string{
		return "Test that exclusive lock on async event handlers works correctly";
	}

	public function run() : void{
		HandlerListManager::global()->unregisterAll();

		$main = $this->getPlugin();
		$pluginManager = $main->getServer()->getPluginManager();

		$pluginManager->registerAsyncEvent(
			GrandchildAsyncEvent::class,
			function(GrandchildAsyncEvent $event) use ($main) : ?Promise{
				if($this->activeExclusiveHandler){
					$main->getLogger()->error("Concurrent handler can't run while exclusive handlers are waiting to complete");
					$this->setResult(Test::RESULT_FAILED);
					return null;
				}
				$this->activeConcurrentHandler = true;
				$resolver = new PromiseResolver();
				$this->resolvers[] = $resolver;
				$resolver->getPromise()->onCompletion(
					fn() => $this->complete($this->activeConcurrentHandler, "concurrent"),
					fn() => $main->getLogger()->error("Not expecting this to be rejected")
				);
				return $resolver->getPromise();
			},
			EventPriority::NORMAL,
			$main,
			//non-exclusive - this must be completed before any exclusive handlers are run (or run after them)
		);
		$pluginManager->registerAsyncEvent(
			GrandchildAsyncEvent::class,
			function(GrandchildAsyncEvent $event) use ($main) : ?Promise{
				$main->getLogger()->info("Entering exclusive handler 1");
				if($this->activeExclusiveHandler || $this->activeConcurrentHandler){
					$main->getLogger()->error("Can't run multiple exclusive handlers at once");
					$this->setResult(Test::RESULT_FAILED);
					return null;
				}
				$this->activeExclusiveHandler = true;
				$resolver = new PromiseResolver();
				$this->resolvers[] = $resolver;
				$resolver->getPromise()->onCompletion(
					fn() => $this->complete($this->activeExclusiveHandler, "exclusive 1"),
					fn() => $main->getLogger()->error("Not expecting this to be rejected")
				);
				return $resolver->getPromise();
			},
			EventPriority::NORMAL,
			$main,
			exclusiveCall: true
		);

		$pluginManager->registerAsyncEvent(
			GrandchildAsyncEvent::class,
			function(GrandchildAsyncEvent $event) use ($main) : ?Promise{
				$this->getPlugin()->getLogger()->info("Entering exclusive handler 2");
				if($this->activeExclusiveHandler || $this->activeConcurrentHandler){
					$main->getLogger()->error("Exclusive lock handlers must not run at the same time as any other handlers");
					$this->setResult(Test::RESULT_FAILED);
					return null;
				}
				$this->activeExclusiveHandler = true;
				/** @phpstan-var PromiseResolver<null> $resolver */
				$resolver = new PromiseResolver();
				$this->resolvers[] = $resolver;
				$resolver->getPromise()->onCompletion(
					function() use ($main) : void{
						$main->getLogger()->info("Exiting exclusive handler asynchronously");
						$this->complete($this->activeExclusiveHandler, "exclusive 2");
					},
					function() use ($main) : void{
						$main->getLogger()->error("Not expecting this promise to be rejected");
						$this->setResult(Test::RESULT_ERROR);
					}
				);
				return $resolver->getPromise();
			},
			EventPriority::NORMAL,
			$main,
			exclusiveCall: true
		);

		(new GrandchildAsyncEvent())->call();
	}

	private function complete(bool &$flag, string $what) : void{
		$this->getPlugin()->getLogger()->info("Completing $what");
		$flag = false;
		if(++$this->done === 3){
			$this->setResult(Test::RESULT_OK);
		}
	}

	public function tick() : void{
		foreach($this->resolvers as $k => $resolver){
			$resolver->resolve(null);
			//don't clear the array here - resolving this will trigger adding the next resolver
			unset($this->resolvers[$k]);
		}
	}
}
