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

/**
 * @phpstan-extends BaseHandlerListManager<Event, RegisteredListener>
 */
class HandlerListManager extends BaseHandlerListManager{
	private static ?self $globalInstance = null;

	public static function global() : self{
		return self::$globalInstance ?? (self::$globalInstance = new self());
	}

	protected function getBaseEventClass() : string{
		return Event::class;
	}

	protected function createHandlerList(string $event, ?HandlerList $parentList, RegisteredListenerCache $handlerCache) : HandlerList{
		return new HandlerList($event, $parentList, $handlerCache);
	}
}
