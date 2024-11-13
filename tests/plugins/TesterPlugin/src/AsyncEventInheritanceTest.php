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

use pmmp\TesterPlugin\event\ChildAsyncEvent;
use pmmp\TesterPlugin\event\GrandchildAsyncEvent;
use pmmp\TesterPlugin\event\ParentAsyncEvent;
use pocketmine\event\AsyncEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\HandlerListManager;
use pocketmine\promise\Promise;
use function implode;
use function shuffle;

final class AsyncEventInheritanceTest extends Test{
	private const EXPECTED_ORDER = [
		GrandchildAsyncEvent::class,
		ChildAsyncEvent::class,
		ParentAsyncEvent::class
	];
	private array $callOrder = [];

	public function getName() : string{
		return "Async Event Inheritance";
	}

	public function getDescription() : string{
		return "Test that async events deliver events to parent handlers correctly in all conditions";
	}

	public function run() : void{
		HandlerListManager::global()->unregisterAll();

		$plugin = $this->getPlugin();
		$classes = self::EXPECTED_ORDER;
		shuffle($classes);
		foreach($classes as $event){
			$plugin->getServer()->getPluginManager()->registerAsyncEvent(
				$event,
				function(AsyncEvent $event) : ?Promise{
					$this->callOrder[] = $event::class;
					return null;
				},
				EventPriority::NORMAL,
				$plugin
			);
		}

		$event = new GrandchildAsyncEvent();
		$promise = $event->call();
		$promise->onCompletion(onSuccess: $this->collectResults(...), onFailure: $this->collectResults(...));
	}

	private function collectResults() : void{
		if($this->callOrder === self::EXPECTED_ORDER){
			$this->setResult(Test::RESULT_OK);
		}else{
			$this->getPlugin()->getLogger()->error("Expected order: " . implode(", ", self::EXPECTED_ORDER) . ", got: " . implode(", ", $this->callOrder));
			$this->setResult(Test::RESULT_FAILED);
		}
	}
}
