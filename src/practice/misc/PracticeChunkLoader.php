<?php

declare(strict_types=1);

namespace practice\misc;

use pocketmine\math\Vector3;
use pocketmine\world\ChunkListener;
use pocketmine\world\ChunkLoader;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;

class PracticeChunkLoader implements ChunkListener, ChunkLoader{
	/** @var Position */
	private Position $position;
	/** @var int */
	private int $chunkX;
	/** @var int */
	private int $chunkZ;
	/** @var World */
	private World $world;

	/** @var mixed|callable */
	private mixed $callable;

	/**
	 * @param World    $level
	 * @param int      $chunkX
	 * @param int      $chunkZ
	 * @param callable $callable
	 */
	public function __construct(World $level, int $chunkX, int $chunkZ, callable $callable){
		$this->position = Position::fromObject(new Vector3($chunkX << 4, 0, $chunkZ << 4), $level);
		$this->chunkX = $chunkX;
		$this->chunkZ = $chunkZ;
		$this->world = $level;
		$this->callable = $callable;
	}

	/**
	 * @return bool
	 */
	public function isLoaderActive() : bool{
		return true;
	}

	/**
	 * @return Position
	 */
	public function getPosition() : Position{
		return $this->position;
	}

	/**
	 * @return World
	 */
	public function getWorld() : World{
		return $this->world;
	}

	/**
	 * @param int   $chunkX
	 * @param int   $chunkZ
	 * @param Chunk $chunk
	 *
	 * @return void
	 */
	public function onChunkChanged(int $chunkX, int $chunkZ, Chunk $chunk) : void{
	}

	/**
	 * @param int   $chunkX
	 * @param int   $chunkZ
	 * @param Chunk $chunk
	 *
	 * @return void
	 */
	public function onChunkLoaded(int $chunkX, int $chunkZ, Chunk $chunk) : void{
		if(!$chunk->isPopulated()){
			return;
		}

		$this->world->unregisterChunkLoader($this, $this->chunkX, $this->chunkZ);
		$this->world->unregisterChunkListener($this, $this->chunkX, $this->chunkZ);
		($this->callable)();
	}

	/**
	 * @param int   $chunkX
	 * @param int   $chunkZ
	 * @param Chunk $chunk
	 *
	 * @return void
	 */
	public function onChunkUnloaded(int $chunkX, int $chunkZ, Chunk $chunk) : void{
	}

	/**
	 * @param int   $chunkX
	 * @param int   $chunkZ
	 * @param Chunk $chunk
	 *
	 * @return void
	 */
	public function onChunkPopulated(int $chunkX, int $chunkZ, Chunk $chunk) : void{
		$this->world->unregisterChunkLoader($this, intval($this->getX()), intval($this->getZ()));
		$this->world->unregisterChunkListener($this, $this->chunkX, $this->chunkZ);
		($this->callable)();
	}

	/**
	 * @return float|int
	 */
	public function getX() : float|int{
		return $this->chunkX;
	}

	/**
	 * @return float|int
	 */
	public function getZ() : float|int{
		return $this->chunkZ;
	}

	/**
	 * @param Vector3 $block
	 *
	 * @return void
	 */
	public function onBlockChanged(Vector3 $block) : void{
	}
}