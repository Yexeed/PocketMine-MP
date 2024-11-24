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

namespace pocketmine\block\inventory\window;

use pocketmine\block\Barrel;
use pocketmine\world\Position;
use pocketmine\world\sound\BarrelCloseSound;
use pocketmine\world\sound\BarrelOpenSound;
use pocketmine\world\sound\Sound;

final class BarrelInventoryWindow extends AnimatedBlockInventoryWindow{

	protected function getOpenSound() : Sound{
		return new BarrelOpenSound();
	}

	protected function getCloseSound() : Sound{
		return new BarrelCloseSound();
	}

	protected function animateBlock(Position $position, bool $isOpen) : void{
		$world = $position->getWorld();
		$block = $world->getBlock($position);
		if($block instanceof Barrel){
			$world->setBlock($position, $block->setOpen($isOpen));
		}
	}
}
