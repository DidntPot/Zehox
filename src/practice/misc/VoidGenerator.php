<?php

namespace practice\misc;

use pocketmine\block\BlockLegacyIds;
use pocketmine\block\BlockTypeIds;
use pocketmine\world\ChunkManager;
use pocketmine\world\generator\Generator;

class VoidGenerator extends Generator{

	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		if($chunkX == 16 && $chunkZ == 16){
			$world->getChunk($chunkX, $chunkZ)?->setBlockStateId(0, 64, 0, BlockTypeIds::GRASS << 4);
		}
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{ }
}