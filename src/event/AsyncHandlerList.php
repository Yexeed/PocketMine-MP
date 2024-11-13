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

use function uasort;

/**
 * @phpstan-extends BaseHandlerList<AsyncRegisteredListener, AsyncEvent>
 */
class AsyncHandlerList extends BaseHandlerList{
	protected function sortSamePriorityListeners(array $listeners) : array{
		uasort($listeners, function(AsyncRegisteredListener $left, AsyncRegisteredListener $right) : int{
			//While the system can handle these in any order, it's better for latency if concurrent handlers
			//are processed together. It doesn't matter whether they are processed before or after exclusive handlers.
			if($right->canBeCalledConcurrently()){
				return $left->canBeCalledConcurrently() ? 0 : 1;
			}
			return -1;
		});
		return $listeners;
	}
}
