<?php

declare(strict_types=1);

namespace practice\game\entity;

use JetBrains\PhpStorm\Pure;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Projectile;
use pocketmine\item\ItemIds;
use pocketmine\math\RayTraceResult;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\types\ActorEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\Server;
use practice\player\PracticePlayer;

class FishingHook extends Projectile{
	/** @var float */
	protected $gravity = 0.08;
	/** @var float */
	protected $drag = 0.05;
	/** @var bool */
	protected bool $caught = false;
	/** @var Entity|null */
	protected ?Entity $attachedEntity = null;

	/**
	 * @param Location         $location
	 * @param Entity|null      $shootingEntity
	 * @param CompoundTag|null $nbt
	 */
	public function __construct(Location $location, ?Entity $shootingEntity, ?CompoundTag $nbt = null){
		parent::__construct($location, $shootingEntity, $nbt);
		$this->motion->x = -sin(deg2rad($location->yaw)) * cos(deg2rad($location->pitch));
		$this->motion->y = -sin(deg2rad($location->pitch));
		$this->motion->z = cos(deg2rad($location->yaw)) * cos(deg2rad($location->pitch));
	}

	/**
	 * @return string
	 */
	public static function getNetworkTypeId() : string{
		return EntityIds::FISHING_HOOK;
	}

	/**
	 * @param int $currentTick
	 *
	 * @return bool
	 */
	public function onUpdate(int $currentTick) : bool{
		if($this->isFlaggedForDespawn() || !$this->isAlive()){
			return false;
		}
		$update = parent::onUpdate($currentTick);
		if(!$this->isCollidedVertically){
			$this->motion->x *= 1.13;
			$this->motion->z *= 1.13;
			$this->motion->y -= $this->gravity * -0.04;
			if($this->isUnderwater()){
				$this->motion->z = 0;
				$this->motion->x = 0;
				$difference = (float) ($this->getWaterHeight() - $this->getPosition()->y);
				if($difference > 0.15){
					$this->motion->y += 0.1;
				}else{
					$this->motion->y += 0.01;
				}
			}
			$update = true;
		}elseif($this->isCollided && $this->keepMovement){
			$this->motion->x = 0;
			$this->motion->y = 0;
			$this->motion->z = 0;
			$this->keepMovement = false;
			$update = true;
		}
		if($this->isOnGround()){
			$this->motion->y = 0;
		}
		if(($owner = $this->getOwningEntity()) != null && $owner instanceof Human){
			$itemInHand = $owner->getInventory()->getItemInHand();
			if($owner->getPosition()->distance($this->getPosition()) > 35 || $itemInHand->getId() !== ItemIds::FISHING_ROD || $this->attachedEntity !== null){
				$this->close();
				if($owner instanceof PracticePlayer){
					$owner->stopFishing();
				}
			}
		}
		return $update;
	}

	/**
	 * @return int
	 */
	public function getWaterHeight() : int{
		$pos = $this->getPosition();
		$floorY = $pos->getFloorY();
		for($y = $pos->getFloorY(); $y < 256; $y++){
			if($this->getWorld()->getBlockAt($pos->getFloorX(), $y, $pos->getFloorZ())->getId() === 0){
				return $y;
			}
		}
		return $floorY;
	}

	/**
	 * @return void
	 */
	public function reelLine() : void{
		$owner = $this->getOwningEntity();
		if($owner instanceof Human && $this->caught){
			Server::getInstance()->broadcastPackets($owner->getViewers(), [ActorEventPacket::create($this->getId(), ActorEvent::FISH_HOOK_TEASE, 0)]);
		}
		if(!$this->closed){
			$this->close();
		}
	}

	/**
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	public function canCollideWith(Entity $entity) : bool{
		$player = $this->getOwningEntity();
		if($player instanceof Player && $entity instanceof Player && $player->getName() !== $entity->getName()){
			return false;
		}
		return parent::canCollideWith($entity);
	}

	/**
	 * @return EntitySizeInfo
	 */
	#[Pure] protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.25, 0.25);
	}

	/**
	 * @param Entity         $entityHit
	 * @param RayTraceResult $hitResult
	 *
	 * @return void
	 */
	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void{
		parent::onHitEntity($entityHit, $hitResult);
		$this->attachedEntity = $entityHit;
	}
}