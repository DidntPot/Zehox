<?php

declare(strict_types=1);

namespace practice\game\entity;

use pocketmine\block\BlockLegacyIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\color\Color;
use pocketmine\entity\effect\InstantEffect;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\SplashPotion as PMPotion;
use pocketmine\event\entity\ProjectileHitBlockEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\item\PotionType;
use pocketmine\world\particle\PotionSplashParticle;
use pocketmine\world\sound\PotionSplashSound;

class SplashPotion extends PMPotion
{
    /**
     * @param ProjectileHitEvent $event
     * @return void
     */
    protected function onHit(ProjectileHitEvent $event): void
    {

        $effects = $this->getPotionEffects();
        $hasEffects = true;

        if (empty($effects)) {
            $colors = [
                new Color(0x38, 0x5d, 0xc6)
            ];
            $hasEffects = false;
        } else {
            $colors = [];
            foreach ($effects as $effect) {
                $level = $effect->getEffectLevel();
                for ($j = 0; $j < $level; ++$j) {
                    $colors[] = $effect->getColor();
                }
            }
        }

        $this->broadcastSound(new PotionSplashSound());
        $this->getWorld()->addParticle($this->location, new PotionSplashParticle(Color::mix(...$colors)));

        if ($hasEffects) {
            if (!$this->willLinger()) {
                foreach ($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(4.125, 2.125, 4.125), $this) as $entity) {
                    if ($entity instanceof Living and $entity->isAlive()) {
                        $distanceSquared = $entity->getPosition()->add(0, $entity->getEyeHeight(), 0)->distanceSquared($this);
                        if ($distanceSquared > 16) { //4 blocks
                            continue;
                        }

                        $distanceMultiplier = 1.45 - (sqrt($distanceSquared) / 4);
                        if ($event instanceof ProjectileHitEntityEvent and $entity === $event->getEntityHit()) {
                            $distanceMultiplier = 1.0;
                        }

                        foreach ($this->getPotionEffects() as $effect) {
                            //getPotionEffects() is used to get COPIES to avoid accidentally modifying the same effect instance already applied to another entity

                            if (!$effect->getType() instanceof InstantEffect) {
                                $newDuration = (int)round($effect->getDuration() * 0.75 * $distanceMultiplier);
                                if ($newDuration < 20) {
                                    continue;
                                }
                                $effect->setDuration($newDuration);
                                $entity->getEffects()->add($effect);
                            } else {
                                $effect->getType()->applyEffect($entity, $effect, $distanceMultiplier, $this);
                            }
                        }
                    }
                }
            }
        } elseif ($event instanceof ProjectileHitBlockEvent and $this->getPotionType()->equals(PotionType::WATER())) {
            $blockIn = $event->getBlockHit()->getSide($event->getRayTraceResult()->getHitFace());

            if ($blockIn->getId() === BlockLegacyIds::FIRE) {
                $this->getWorld()->setBlock($blockIn, VanillaBlocks::AIR());
            }
            foreach ($blockIn->getHorizontalSides() as $horizontalSide) {
                if ($horizontalSide->getId() === BlockLegacyIds::FIRE) {
                    $this->getWorld()->setBlock($horizontalSide, VanillaBlocks::AIR());
                }
            }
        }
    }
}