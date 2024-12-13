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

namespace pocketmine\block\utils;

enum WoodType{
	case OAK;
	case SPRUCE;
	case BIRCH;
	case JUNGLE;
	case ACACIA;
	case DARK_OAK;
	case MANGROVE;
	case CRIMSON;
	case WARPED;
	case CHERRY;
	case PALE_OAK;

	public function getDisplayName() : string{
		return match($this){
			self::OAK => "Oak",
			self::SPRUCE => "Spruce",
			self::BIRCH => "Birch",
			self::JUNGLE => "Jungle",
			self::ACACIA => "Acacia",
			self::DARK_OAK => "Dark Oak",
			self::MANGROVE => "Mangrove",
			self::CRIMSON => "Crimson",
			self::WARPED => "Warped",
			self::CHERRY => "Cherry",
			self::PALE_OAK => "Pale Oak",
		};
	}

	public function isFlammable() : bool{
		return $this !== self::CRIMSON && $this !== self::WARPED;
	}

	public function getStandardLogSuffix() : ?string{
		return $this === self::CRIMSON || $this === self::WARPED ? "Stem" : null;
	}

	public function getAllSidedLogSuffix() : ?string{
		return $this === self::CRIMSON || $this === self::WARPED ? "Hyphae" : null;
	}
}
