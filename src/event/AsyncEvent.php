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

use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\timings\Timings;
use function array_shift;
use function count;

/**
 * This class is used to permit asynchronous event handling.
 *
 * When an event is called asynchronously, the event handlers are called by priority level.
 * When all the promises of a priority level have been resolved, the next priority level is called.
 */
abstract class AsyncEvent{
	/** @var array<class-string<AsyncEvent>, int> $delegatesCallDepth */
	private static array $delegatesCallDepth = [];
	private const MAX_EVENT_CALL_DEPTH = 50;

	/**
	 * @phpstan-return Promise<static>
	 */
	final public function call() : Promise{
		if(!isset(self::$delegatesCallDepth[$class = static::class])){
			self::$delegatesCallDepth[$class] = 0;
		}

		if(self::$delegatesCallDepth[$class] >= self::MAX_EVENT_CALL_DEPTH){
			//this exception will be caught by the parent event call if all else fails
			throw new \RuntimeException("Recursive event call detected (reached max depth of " . self::MAX_EVENT_CALL_DEPTH . " calls)");
		}

		$timings = Timings::getAsyncEventTimings($this);
		$timings->startTiming();

		++self::$delegatesCallDepth[$class];
		try{
			/** @phpstan-var PromiseResolver<static> $globalResolver */
			$globalResolver = new PromiseResolver();

			$this->asyncEachPriority(HandlerListManager::global()->getAsyncListFor(static::class), EventPriority::ALL, $globalResolver);

			return $globalResolver->getPromise();
		}finally{
			--self::$delegatesCallDepth[$class];
			$timings->stopTiming();
		}
	}

	/**
	 * TODO: this should use EventPriority constants for the list type but it's inconvenient with the current design
	 * @phpstan-param list<int> $remaining
	 * @phpstan-param PromiseResolver<static> $globalResolver
	 */
	private function asyncEachPriority(AsyncHandlerList $handlerList, array $remaining, PromiseResolver $globalResolver) : void{
		while(true){
			$nextPriority = array_shift($remaining);
			if($nextPriority === null){
				$globalResolver->resolve($this);
				break;
			}

			$promise = $this->callPriority($handlerList, $nextPriority);
			if($promise !== null){
				$promise->onCompletion(
					onSuccess: fn() => $this->asyncEachPriority($handlerList, $remaining, $globalResolver),
					onFailure: $globalResolver->reject(...)
				);
				break;
			}
		}
	}

	/**
	 * @phpstan-return Promise<null>
	 */
	private function callPriority(AsyncHandlerList $handlerList, int $priority) : ?Promise{
		$handlers = $handlerList->getListenersByPriority($priority);
		if(count($handlers) === 0){
			return null;
		}

		/** @phpstan-var PromiseResolver<null> $resolver */
		$resolver = new PromiseResolver();

		$concurrentPromises = [];
		$nonConcurrentHandlers = [];
		foreach($handlers as $registration){
			if($registration->canBeCalledConcurrently()){
				$result = $registration->callAsync($this);
				if($result !== null) {
					$concurrentPromises[] = $result;
				}
			}else{
				$nonConcurrentHandlers[] = $registration;
			}
		}

		Promise::all($concurrentPromises)->onCompletion(
			onSuccess: fn() => $this->processExclusiveHandlers($nonConcurrentHandlers, $resolver),
			onFailure: $resolver->reject(...)
		);

		return $resolver->getPromise();
	}

	/**
	 * @param AsyncRegisteredListener[] $handlers
	 * @phpstan-param PromiseResolver<null> $resolver
	 */
	private function processExclusiveHandlers(array $handlers, PromiseResolver $resolver) : void{
		while(true){
			$handler = array_shift($handlers);
			if($handler === null){
				$resolver->resolve(null);
				break;
			}
			$result = $handler->callAsync($this);
			if($result instanceof Promise){
				//wait for this promise to resolve before calling the next handler
				$result->onCompletion(
					onSuccess: fn() => $this->processExclusiveHandlers($handlers, $resolver),
					onFailure: $resolver->reject(...)
				);
				break;
			}

			//this handler didn't return a promise - continue directly to the next one
		}
	}
}
