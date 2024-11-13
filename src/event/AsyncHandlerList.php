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

namespace pocketmine\event;

use pocketmine\plugin\Plugin;
use function array_merge;
use function krsort;
use function spl_object_id;
use function uasort;
use const SORT_NUMERIC;

class AsyncHandlerList{
	//TODO: we can probably deduplicate most of this code with the sync side if we throw in some generics

	/** @var AsyncRegisteredListener[][] */
	private array $handlerSlots = [];

	/**
	 * @var RegisteredListenerCache[]
	 * @phpstan-var array<int, RegisteredListenerCache<AsyncRegisteredListener>>
	 */
	private array $affectedHandlerCaches = [];

	/**
	 * @phpstan-param class-string<covariant AsyncEvent> $class
	 * @phpstan-param RegisteredListenerCache<AsyncRegisteredListener> $handlerCache
	 */
	public function __construct(
		private string $class,
		private ?AsyncHandlerList $parentList,
		private RegisteredListenerCache $handlerCache = new RegisteredListenerCache()
	){
		for($list = $this; $list !== null; $list = $list->parentList){
			$list->affectedHandlerCaches[spl_object_id($this->handlerCache)] = $this->handlerCache;
		}
	}

	/**
	 * @throws \Exception
	 */
	public function register(AsyncRegisteredListener $listener) : void{
		if(isset($this->handlerSlots[$listener->getPriority()][spl_object_id($listener)])){
			throw new \InvalidArgumentException("This listener is already registered to priority {$listener->getPriority()} of event {$this->class}");
		}
		$this->handlerSlots[$listener->getPriority()][spl_object_id($listener)] = $listener;
		$this->invalidateAffectedCaches();
	}

	/**
	 * @param AsyncRegisteredListener[] $listeners
	 */
	public function registerAll(array $listeners) : void{
		foreach($listeners as $listener){
			$this->register($listener);
		}
		$this->invalidateAffectedCaches();
	}

	public function unregister(AsyncRegisteredListener|Plugin|Listener $object) : void{
		if($object instanceof Plugin || $object instanceof Listener){
			foreach($this->handlerSlots as $priority => $list){
				foreach($list as $hash => $listener){
					if(($object instanceof Plugin && $listener->getPlugin() === $object)
						|| ($object instanceof Listener && (new \ReflectionFunction($listener->getHandler()))->getClosureThis() === $object) //this doesn't even need to be a listener :D
					){
						unset($this->handlerSlots[$priority][$hash]);
					}
				}
			}
		}else{
			unset($this->handlerSlots[$object->getPriority()][spl_object_id($object)]);
		}
		$this->invalidateAffectedCaches();
	}

	public function clear() : void{
		$this->handlerSlots = [];
		$this->invalidateAffectedCaches();
	}

	/**
	 * @return AsyncRegisteredListener[]
	 */
	public function getListenersByPriority(int $priority) : array{
		return $this->handlerSlots[$priority] ?? [];
	}

	public function getParent() : ?AsyncHandlerList{
		return $this->parentList;
	}

	/**
	 * Invalidates all known caches which might be affected by this list's contents.
	 */
	private function invalidateAffectedCaches() : void{
		foreach($this->affectedHandlerCaches as $cache){
			$cache->list = null;
		}
	}

	/**
	 * @return AsyncRegisteredListener[]
	 * @phpstan-return list<AsyncRegisteredListener>
	 */
	public function getListenerList() : array{
		if($this->handlerCache->list !== null){
			return $this->handlerCache->list;
		}

		$handlerLists = [];
		for($currentList = $this; $currentList !== null; $currentList = $currentList->parentList){
			$handlerLists[] = $currentList;
		}

		$listenersByPriority = [];
		foreach($handlerLists as $currentList){
			foreach($currentList->handlerSlots as $priority => $listeners){
				uasort($listeners, function(AsyncRegisteredListener $left, AsyncRegisteredListener $right) : int{
					//While the system can handle these in any order, it's better for latency if concurrent handlers
					//are processed together. It doesn't matter whether they are processed before or after exclusive handlers.
					if($right->canBeCalledConcurrently()){
						return $left->canBeCalledConcurrently() ? 0 : 1;
					}
					return -1;
				});
				$listenersByPriority[$priority] = array_merge($listenersByPriority[$priority] ?? [], $listeners);
			}
		}

		//TODO: why on earth do the priorities have higher values for lower priority?
		krsort($listenersByPriority, SORT_NUMERIC);

		return $this->handlerCache->list = array_merge(...$listenersByPriority);
	}
}
