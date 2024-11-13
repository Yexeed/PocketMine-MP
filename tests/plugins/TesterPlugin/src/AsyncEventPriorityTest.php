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
use pocketmine\event\AsyncHandlerListManager;
use pocketmine\event\EventPriority;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;

final class AsyncEventPriorityTest extends Test{
	/**
	 * @var PromiseResolver[]
	 * @phpstan-var list<PromiseResolver<null>>
	 */
	private array $resolvers = [];

	private bool $firstHandlerCompleted = false;

	public function getName() : string{
		return "Async Event Handler Priority Lock";
	}

	public function getDescription() : string{
		return "Tests that async events do not call handlers from the next priority until all promises from the current priority are resolved";
	}

	public function run() : void{
		AsyncHandlerListManager::global()->unregisterAll();

		$main = $this->getPlugin();
		$pluginManager = $main->getServer()->getPluginManager();
		$pluginManager->registerAsyncEvent(
			GrandchildAsyncEvent::class,
			function(GrandchildAsyncEvent $event) use ($main) : Promise{
				$resolver = new PromiseResolver();
				$this->resolvers[] = $resolver;

				$resolver->getPromise()->onCompletion(function() : void{
					$this->firstHandlerCompleted = true;
				}, function() use ($main) : void{
					$main->getLogger()->error("Not expecting this to be rejected");
					$this->setResult(Test::RESULT_ERROR);
				});

				return $resolver->getPromise();
			},
			EventPriority::LOW, //anything below NORMAL is fine
			$main
		);
		$pluginManager->registerAsyncEvent(
			GrandchildAsyncEvent::class,
			function(GrandchildAsyncEvent $event) use ($main) : ?Promise{
				if(!$this->firstHandlerCompleted){
					$main->getLogger()->error("This shouldn't run until the previous priority is done");
					$this->setResult(Test::RESULT_FAILED);
				}else{
					$this->setResult(Test::RESULT_OK);
				}
				return null;
			},
			EventPriority::NORMAL,
			$main
		);

		(new GrandchildAsyncEvent())->call();
	}

	public function tick() : void{
		foreach($this->resolvers as $k => $resolver){
			$resolver->resolve(null);
			unset($this->resolvers[$k]);
		}
	}
}
