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

use pocketmine\world\Position;
use pocketmine\world\sound\Sound;
use function count;

abstract class AnimatedBlockInventoryWindow extends BlockInventoryWindow{

	protected function getViewerCount() : int{
		return count($this->inventory->getViewers());
	}

	abstract protected function getOpenSound() : Sound;

	abstract protected function getCloseSound() : Sound;

	abstract protected function animateBlock(Position $position, bool $isOpen) : void;

	protected function playSound(Position $position, bool $isOpen) : void{
		$position->getWorld()->addSound($position->add(0.5, 0.5, 0.5), $isOpen ? $this->getOpenSound() : $this->getCloseSound());
	}

	protected function doBlockEffects(bool $isOpen) : void{
		$position = $this->holder;
		$this->animateBlock($position, $isOpen);
		$this->playSound($position, $isOpen);
	}

	public function onOpen() : void{
		parent::onOpen();
		if($this->getViewerCount() === 1){
			$this->doBlockEffects(true);
		}
	}

	public function onClose() : void{
		if($this->getViewerCount() === 1){
			$this->doBlockEffects(false);
		}
		parent::onClose();
	}
}
