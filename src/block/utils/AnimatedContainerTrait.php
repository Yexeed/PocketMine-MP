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

use pocketmine\inventory\InventoryHolder;
use pocketmine\world\Position;
use pocketmine\world\sound\Sound;
use function count;

trait AnimatedContainerTrait{

	protected function getContainerViewerCount() : int{
		$position = $this->getPosition();
		$tile = $position->getWorld()->getTile($position);
		if($tile instanceof InventoryHolder){
			return count($tile->getInventory()->getViewers());
		}
		return 0;
	}

	abstract protected function getContainerOpenSound() : Sound;

	abstract protected function getContainerCloseSound() : Sound;

	abstract protected function doContainerAnimation(Position $position, bool $isOpen) : void;

	protected function playContainerSound(Position $position, bool $isOpen) : void{
		$position->getWorld()->addSound($position->add(0.5, 0.5, 0.5), $isOpen ? $this->getContainerOpenSound() : $this->getContainerCloseSound());
	}

	abstract protected function getPosition() : Position;

	protected function doContainerEffects(bool $isOpen) : void{
		$position = $this->getPosition();
		$this->doContainerAnimation($position, $isOpen);
		$this->playContainerSound($position, $isOpen);
	}

	public function onContainerOpen() : void{
		if($this->getContainerViewerCount() === 1){
			$this->doContainerEffects(true);
		}
	}

	public function onContainerClose() : void{
		if($this->getContainerViewerCount() === 1){
			$this->doContainerEffects(false);
		}
	}
}
