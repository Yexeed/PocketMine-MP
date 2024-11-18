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

namespace pocketmine\block\anvil;

use pocketmine\item\Item;

abstract class AnvilAction{
	/** @phpstan-var int<0, max>  */
	protected int $xpCost = 0;

	final public function __construct(
		protected Item $base,
		protected Item $material,
		protected ?string $customName
	){ }

	/**
	 * Returns the XP cost requested for this action.
	 * This XP cost will be summed up to the total XP cost of the anvil operation.
	 *
	 * @phpstan-return int<0, max>
	 */
	final public function getXpCost() : int{
		return $this->xpCost;
	}

	/**
	 * If only actions marked as free of repair cost is applied, the result item
	 * will not have any repair cost increase.
	 */
	public function isFreeOfRepairCost() : bool {
		return false;
	}

	/**
	 * Processing an action means applying the changes to the result item
	 * and updating the XP cost property of the action.
	 */
	abstract public function process(Item $resultItem) : void;

	/**
	 * Returns whether this action is valid and can be applied.
	 */
	abstract public function canBeApplied() : bool;
}
