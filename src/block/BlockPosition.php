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

namespace pocketmine\block;

use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\World;
use function iterator_to_array;

final class BlockPosition implements \Stringable{

	public function __construct(
		public readonly int $x,
		public readonly int $y,
		public readonly int $z,
		public ?World $world //TODO: make this non-nullable (requires Blocks not to reference positions)
	){}

	/**
	 * Returns the position's world if valid. Throws an error if the world is unexpectedly invalid.
	 *
	 * @throws AssumptionFailedError
	 */
	public function getWorld() : World{
		if($this->world === null || !$this->world->isLoaded()){
			throw new AssumptionFailedError("Position world is null or has been unloaded");
		}

		return $this->world;
	}

	/**
	 * Checks if this object has a valid reference to a loaded world
	 */
	public function isValid() : bool{
		if($this->world !== null && !$this->world->isLoaded()){
			$this->world = null;

			return false;
		}

		return $this->world !== null;
	}

	public function asVector3() : Vector3{
		return new Vector3($this->x, $this->y, $this->z);
	}

	public function center() : Vector3{
		return new Vector3($this->x + 0.5, $this->y + 0.5, $this->z + 0.5);
	}

	public static function fromVector3(Vector3 $vector3, World $world) : self{
		return new self($vector3->getFloorX(), $vector3->getFloorY(), $vector3->getFloorZ(), $world);
	}

	public function getSide(int $side, int $step = 1) : BlockPosition{
		$offset = Facing::OFFSET[$side] ?? throw new \InvalidArgumentException("Invalid side $side");

		[$dx, $dy, $dz] = $offset;
		return new BlockPosition($this->x + ($dx * $step), $this->y + ($dy * $step), $this->z + ($dz * $step), $this->world);
	}

	public function add(int $x, int $y, int $z) : BlockPosition{
		return new BlockPosition($this->x + $x, $this->y + $y, $this->z + $z, $this->world);
	}

	/**
	 * @phpstan-return \Generator<int, self, void, void>
	 */
	public function getAllSides() : \Generator{
		foreach(Facing::ALL as $facing){
			yield $this->getSide($facing);
		}
	}

	/**
	 * @phpstan-return list<self>
	 */
	public function getAllSidesArray() : array{
		return iterator_to_array($this->getAllSides(), preserve_keys: false);
	}

	public function __toString() : string{
		$worldName = $this->world?->getFolderName() ?? "???";
		return "BlockPosition(x=$this->x,y=$this->y,z=$this->z,world={$worldName}";
	}
}
