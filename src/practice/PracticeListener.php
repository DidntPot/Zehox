<?php

declare(strict_types=1);

namespace practice;

use pocketmine\block\Liquid;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\block\BlockFormEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockSpreadEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerBucketFillEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\plugin\PluginDisableEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\item\Bucket;
use pocketmine\item\EnderPearl;
use pocketmine\item\FlintSteel;
use pocketmine\item\Food;
use pocketmine\item\ItemBlock;
use pocketmine\item\ItemIds;
use pocketmine\item\MushroomStew;
use pocketmine\item\Potion;
use pocketmine\item\SplashPotion;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use practice\anticheat\AntiCheatUtil;
use practice\arenas\PracticeArena;
use practice\game\FormUtil;
use practice\game\inventory\InventoryUtil;
use practice\game\items\PracticeItem;
use practice\player\permissions\PermissionsHandler;
use practice\player\PlayerSpawnTask;
use practice\player\RespawnTask;
use practice\scoreboard\ScoreboardUtil;
use practice\scoreboard\UpdateScoreboardTask;

class PracticeListener implements Listener
{
    /** @var PracticeCore */
    private PracticeCore $core;

    /** @var array */
    private static array $clientInfo = [];

    /**
     * @param PracticeCore $c
     */
    public function __construct(PracticeCore $c)
    {
        $this->core = $c;
    }

    /**
     * @param PlayerPreLoginEvent $event
     * @return void
     */
    public function onPreLogin(PlayerPreLoginEvent $event): void
    {
        $pInfo = $event->getPlayerInfo();
        unset(self::$clientInfo[$pInfo->getUsername()]);
    }

    /**
     * @param PlayerJoinEvent $event
     * @return void
     */
    public function onJoin(PlayerJoinEvent $event): void
    {
        $p = $event->getPlayer();

        $playerHandler = PracticeCore::getPlayerHandler();

        if (!is_null($p)) {
            $data = self::$clientInfo;

            $playerHandler->putPendingPInfo(
                $event->getPlayer()->getName(),
                (isset($data['DeviceOS'])) ? intval($data['DeviceOS']) : -1,
                (isset($data['CurrentInputMode'])) ? intval($data['CurrentInputMode']) : -1,
                (isset($data['ClientRandomId'])) ? intval($data['ClientRandomId']) : -1,
                (isset($data['DeviceId'])) ? strval($data['DeviceId']) : '',
                (isset($data['DeviceModel'])) ? strval($data['DeviceModel']) : ''
            );

            $pl = $playerHandler->addPlayer($p);
            $nameTag = PracticeUtil::getNameTagFormat($p);
            $p->setNameTag($nameTag);
            $this->core->getScheduler()->scheduleDelayedTask(new PlayerSpawnTask($pl), 10);
            $event->setJoinMessage(PracticeUtil::str_replace(PracticeUtil::getMessage('join-msg'), ['%player%' => $p->getName()]));
        }
    }

    /**
     * @param PlayerLoginEvent $event
     * @return void
     */
    public function onLogin(PlayerLoginEvent $event): void
    {
        $p = $event->getPlayer();

        if ($p->getGamemode()->id() !== 0) $p->setGamemode(GameMode::SURVIVAL());

        $p->getEffects()->clear();

        $maxHealth = $p->getMaxHealth();

        if ($p->getHealth() != $maxHealth) $p->setHealth($maxHealth);

        if ($p->isOnFire()) $p->extinguish();

        if (PracticeUtil::isFrozen($p)) PracticeUtil::setFrozen($p, false);

        if (PracticeUtil::isInSpectatorMode($p))
            PracticeUtil::setInSpectatorMode($p, false);

        $p->teleport(PracticeUtil::getSpawnPosition());
    }

    /**
     * @param PlayerQuitEvent $event
     * @return void
     */
    public function onLeave(PlayerQuitEvent $event): void
    {
        $p = $event->getPlayer();

        $playerHandler = PracticeCore::getPlayerHandler();

        $duelHandler = PracticeCore::getDuelHandler();

        if (!is_null($p) and $playerHandler->isPlayer($p)) {
            $pracPlayer = $playerHandler->getPlayer($p);
            if ($pracPlayer->isFishing()) $pracPlayer->stopFishing(false);
            if ($pracPlayer->isInParty()) {
                $party = PracticeCore::getPartyManager()->getPartyFromPlayer($pracPlayer->getPlayerName());
                $party->removeFromParty($pracPlayer->getPlayerName());
            }
            if ($duelHandler->isPlayerInQueue($p)) $duelHandler->removePlayerFromQueue($p);
            if ($pracPlayer->isInCombat()) PracticeUtil::kill($p);

            $playerHandler->removePlayer($p);
            $msg = PracticeUtil::str_replace(PracticeUtil::getMessage('leave-msg'), ['%player%' => $p->getName()]);
            $event->setQuitMessage($msg);
            $this->core->getScheduler()->scheduleDelayedTask(new UpdateScoreboardTask($pracPlayer), 1);
        }
    }

    /**
     * @param PlayerDeathEvent $event
     * @return void
     */
    public function onDeath(PlayerDeathEvent $event): void
    {
        $p = $event->getPlayer();
        $playerHandler = PracticeCore::getPlayerHandler();
        $level = $p->getWorld();
        $duelHandler = PracticeCore::getDuelHandler();

        if ($playerHandler->isPlayerOnline($p)) {
            $player = $playerHandler->getPlayer($p);
            if ($player->isFishing()) $player->stopFishing(false);
            $lastDamageCause = $p->getLastDamageCause();
            $addToStats = $player->isInArena() and ($player->getCurrentArenaType() === PracticeArena::FFA_ARENA);
            $diedFairly = true;
            if ($lastDamageCause != null) {
                if ($lastDamageCause->getCause() === EntityDamageEvent::CAUSE_VOID) {
                    $diedFairly = false;
                } elseif ($lastDamageCause->getCause() === EntityDamageEvent::CAUSE_SUICIDE) {
                    $diedFairly = false;
                } elseif ($lastDamageCause->getCause() === EntityDamageEvent::CAUSE_SUFFOCATION) {
                    if ($p->isInsideOfSolid()) {
                        $pos = $p->getPosition();
                        $block = $level->getBlock($pos);
                        if (PracticeUtil::isGravityBlock($block)) $diedFairly = false;
                    }
                }
            }

            if ($addToStats === true) {
                if ($diedFairly === true) {
                    if ($lastDamageCause instanceof EntityDamageByEntityEvent) {
                        $damgr = $lastDamageCause->getDamager();
                        if ($playerHandler->isPlayerOnline($damgr)) {
                            $attacker = $playerHandler->getPlayer($damgr);
                            $p = $attacker->getPlayer();
                            if (!$attacker->equals($player)) {
                                $arena = $attacker->getCurrentArena();
                                if ($arena->doesHaveKit()) {
                                    $event->setDrops([]);
                                    $kit = $arena->getFirstKit();
                                    $kit->giveTo($p);
                                }
                                $p->setHealth($p->getMaxHealth());
                                $kills = $playerHandler->addKillFor($attacker->getPlayerName());
                                $killsStr = PracticeUtil::str_replace(PracticeUtil::getName('scoreboard.arena-ffa.kills'), ['%num%' => $kills]);
                                $attacker->updateLineOfScoreboard(4, ' ' . $killsStr);
                            }
                        }
                    }

                    $playerHandler->addDeathFor($player->getPlayerName());
                }
            } else {
                if ($player->isInDuel()) {
                    $duel = $duelHandler->getDuel($p);
                    $msg = $event->getDeathMessage();
                    $winner = ($duel->isPlayer($p) ? $duel->getOpponent()->getPlayerName() : $duel->getPlayer()->getPlayerName());
                    $loser = $p->getName();
                    $randMsg = PracticeUtil::getRandomDeathMsg($winner, $loser);
                    $msg = (!is_null($randMsg)) ? $randMsg : $msg;
                    $duel->broadcastMsg($msg, true);

                    if ($diedFairly === true) $duel->setResults($winner, $loser);
                    else $duel->setResults();

                    $event->setDrops([]);
                    $event->setDeathMessage('');
                }
            }
        }
    }

    /**
     * @param PlayerRespawnEvent $event
     * @return void
     */
    public function onRespawn(PlayerRespawnEvent $event): void
    {
        $p = $event->getPlayer();

        $nameTag = PracticeUtil::getNameTagFormat($p);
        $p->setNameTag($nameTag);

        $spawnPos = PracticeUtil::getSpawnPosition();
        $prevSpawnPos = $event->getRespawnPosition();

        if ($prevSpawnPos !== $spawnPos) $event->setRespawnPosition($spawnPos);

        $player = PracticeCore::getPlayerHandler()->getPlayer($p);

        if ($player !== null) {
            if ($player->isInArena()) $player->setCurrentArena(PracticeArena::NO_ARENA);
            if (!$player->canThrowPearl()) $player->setThrowPearl(true);
            if ($player->isInCombat()) $player->setInCombat(false);

            $player->setSpawnScoreboard();
            $this->core->getScheduler()->scheduleDelayedTask(new RespawnTask($player), 10);
        }
    }

    /**
     * @param EntityDamageEvent $event
     * @return void
     */
    public function onEntityDamaged(EntityDamageEvent $event): void
    {
        $cancel = false;
        $e = $event->getEntity();

        $playerHandler = PracticeCore::getPlayerHandler();

        $duelHandler = PracticeCore::getDuelHandler();

        $cause = $event->getCause();


        if ($e instanceof Player) {
            $name = $e->getName();
            if ($cause === EntityDamageEvent::CAUSE_FALL) $cancel = true;
            else {
                if ($playerHandler->isPlayerOnline($name)) {
                    $player = $playerHandler->getPlayer($name);
                    $lvl = $player->getPlayer()->getWorld();
                    if (PracticeUtil::areWorldEqual($lvl, PracticeUtil::getDefaultWorld())) {
                        if ($cause === EntityDamageEvent::CAUSE_VOID) {
                            if ($duelHandler->isASpectator($name)) {
                                $duel = $duelHandler->getDuelFromSpec($name);
                                $center = $duel->getArena()->getSpawnPosition();
                                PracticeUtil::teleportPlayer($player, $center);
                            } else
                                PracticeUtil::teleportPlayer($player);

                            $event->cancel();
                            return;
                        }

                        $cancel = PracticeUtil::isLobbyProtectionEnabled();
                    }

                    if ($cancel === true) {
                        $cancel = !$player->isInDuel() and !$player->isInArena();
                    }

                } else $cancel = true;

                if (PracticeUtil::isInSpectatorMode($name)) $cancel = true;
            }
        }

        if ($cancel === true) $event->cancel();
    }

    /**
     * @param EntityDamageByEntityEvent $event
     * @return void
     */
    public function onEntityDamagedByEntity(EntityDamageByEntityEvent $event): void
    {
        $entity = $event->getEntity();
        $damager = $event->getDamager();

        $playerHandler = PracticeCore::getPlayerHandler();

        $duelHandler = PracticeCore::getDuelHandler();

        $kitHandler = PracticeCore::getKitHandler();

        $trackHit = false;

        if ($event->getCause() !== EntityDamageEvent::CAUSE_PROJECTILE
            and $entity instanceof Player and $damager instanceof Player) {

            if (AntiCheatUtil::canDamage($entity->getName()) and !$event->isCancelled()) {
                AntiCheatUtil::checkForReach($entity, $damager);
                $trackHit = true;
            }
        }

        $cancel = false;

        if ($playerHandler->isPlayerOnline($damager->getName()) and $playerHandler->isPlayerOnline($entity->getName())) {
            $attacker = $playerHandler->getPlayer($damager->getName());
            $attacked = $playerHandler->getPlayer($entity->getName());

            if (!$attacker->canHitPlayer() or !$attacked->canHitPlayer()) $cancel = true;

            if ($cancel === false) {
                $kb = $event->getKnockBack();
                $attackDelay = $event->getAttackCooldown();
                if ($attacker->isInDuel() and $attacked->isInDuel()) {
                    $duel = $duelHandler->getDuel($attacker->getPlayerName());
                    $kit = $duel->getQueue();
                    if ($kitHandler->hasKitSetting($kit)) {
                        $pvpData = $kitHandler->getKitSetting($kit);
                        $kb = $pvpData->getKB();
                        $attackDelay = $pvpData->getAttackDelay();
                    }
                } elseif ($attacker->isInArena() and $attacked->isInArena()) {
                    $arena = $attacker->getCurrentArena();
                    if ($arena->doesHaveKit()) {
                        $kit = $arena->getFirstKit();
                        $name = $kit->getName();
                        if ($kitHandler->hasKitSetting($name)) {
                            $pvpData = $kitHandler->getKitSetting($name);
                            $kb = $pvpData->getKB();
                            $attackDelay = $pvpData->getAttackDelay();
                        }
                    }
                }

                $event->setAttackCooldown($attackDelay);
                $event->setKnockBack($kb);

                if (AntiCheatUtil::canDamage($attacked->getPlayerName()) and !$event->isCancelled()) {
                    $attacked->setNoDamageTicks($event->getAttackCooldown());
                    if ($trackHit === true) {
                        if ($attacker->isSwitching()) {
                            $attacker->sendMessage(TextFormat::RED . 'Switching is not allowed.');
                            return;
                        }
                        $attacker->trackHit();
                    }

                    if (!$attacker->isInDuel() and !$attacked->isInDuel()) {
                        $attacker->setInCombat(true);
                        $attacked->setInCombat(true);
                    } else {
                        if ($attacker->isInDuel() and $attacked->isInDuel()) {
                            $duel = $duelHandler->getDuel($attacker->getPlayer());
                            if ($duel->isSpleef())
                                $cancel = true;
                            else $duel->addHitFrom($attacked->getPlayer());
                        }
                    }

                    if ($cancel === false and $attacked->isInArena()) {
                        $p = $attacked->getPlayer();
                        $nameTag = PracticeUtil::getNameTagFormat($p);
                        $p->setNameTag($nameTag);
                    }
                }
            }
        }

        if ($cancel === true) $event->cancel();
    }

    /**
     * @param EntityDamageByChildEntityEvent $event
     * @return void
     */
    public function onEntityDamagedByChildEntity(EntityDamageByChildEntityEvent $event): void
    {
        $child = $event->getChild();
        $damaged = $event->getEntity();

        if (!$event->isCancelled() and $child instanceof \pocketmine\entity\projectile\EnderPearl and $damaged instanceof Player) {
            $throwerEntity = $child->getOwningEntity();
            $playerHandler = PracticeCore::getPlayerHandler();
            if ($throwerEntity !== null and $throwerEntity instanceof Player and $playerHandler->isPlayerOnline($throwerEntity->getName())) {
                $thrower = $playerHandler->getPlayer($throwerEntity->getName());
                $thrower->checkSwitching();
            }
        }
    }

    /**
     * @param PlayerItemConsumeEvent $event
     * @return void
     */
    public function onPlayerConsume(PlayerItemConsumeEvent $event): void
    {
        $item = $event->getItem();
        $p = $event->getPlayer();

        $cancel = false;

        $inv = $p->getInventory();

        if (PracticeUtil::canUseItems($p)) {
            if ($item instanceof Food) {
                $isGoldenHead = false;
                if ($item->getId() === ItemIds::GOLDEN_APPLE) $isGoldenHead = ($item->getMeta() === 1 or $item->getName() === PracticeUtil::getName('golden-head'));

                if ($isGoldenHead === true) {
                    /* @var $effects EffectInstance[] */
                    $effects = $item->getAdditionalEffects();

                    $eightSeconds = PracticeUtil::secondsToTicks(8);

                    $twoMin = PracticeUtil::minutesToTicks(2);

                    $keys = array_keys($effects);

                    foreach ($keys as $key) {
                        $effect = $effects[$key];
                        $ef = $effect;

                        $regen = VanillaEffects::REGENERATION();
                        $absorption = VanillaEffects::ABSORPTION();

                        if ($ef instanceof $regen)
                            $effect = $effect->setDuration($eightSeconds)->setAmplifier(1);
                        elseif ($ef instanceof $absorption)
                            $effect = $effect->setDuration($twoMin);
                        $effects[$key] = $effect;
                    }

                    foreach ($effects as $effect) $p->getEffects()->add($effect->setVisible(false));
                    $heldItem = $inv->getHeldItemIndex();
                    $item = $item->setCount($item->getCount() - 1);
                    $inv->setItem($heldItem, $item);
                    $cancel = true;
                } else {
                    if ($item->getId() === ItemIds::MUSHROOM_STEW)
                        $cancel = true;
                }
            } elseif ($item instanceof Potion) {
                $slot = $inv->getHeldItemIndex();
                $effects = $item->getAdditionalEffects();
                $inv->setItem($slot, VanillaItems::AIR());
                foreach ($effects as $effect) {
                    if ($effect instanceof EffectInstance)
                        $p->getEffects()->add($effect->setVisible(false));
                }

                $cancel = true;
            }
        } else $cancel = true;

        if ($cancel === true) $event->cancel();
    }

    /**
     * @param PlayerInteractEvent $event
     * @return void
     * @noinspection PhpParamsInspection
     */
    public function onPlayerInteract(PlayerInteractEvent $event): void
    {
        $item = $event->getItem();
        $player = $event->getPlayer();
        $action = $event->getAction();
        $level = $player->getWorld();
        $cancel = false;

        $playerHandler = PracticeCore::getPlayerHandler();
        $itemHandler = PracticeCore::getItemHandler();
        $duelHandler = PracticeCore::getDuelHandler();

        if ($playerHandler->isPlayer($player)) {
            $p = $playerHandler->getPlayer($player);

            $exec = PracticeUtil::checkActions($action, PlayerInteractEvent::RIGHT_CLICK_BLOCK);
            if (($p->getDevice() !== PracticeUtil::WINDOWS_10 or $p->getInput() !== PracticeUtil::CONTROLS_MOUSE) and $exec === true)
                $p->addCps(false);

            if ($itemHandler->isPracticeItem($item)) {

                if (PracticeUtil::checkActions($action, PlayerInteractEvent::RIGHT_CLICK_BLOCK)) {

                    $practiceItem = $itemHandler->getPracticeItem($item);

                    if ($practiceItem instanceof PracticeItem and $itemHandler->canUseItem($p, $practiceItem)) {

                        $name = $practiceItem->getLocalizedName();
                        $exec = (!$practiceItem->canOnlyUseInLobby() || PracticeUtil::areWorldEqual($level, PracticeUtil::getDefaultWorld()));

                        if ($exec === true) {

                            if (PracticeUtil::str_contains('hub.', $name)) {
                                if (PracticeUtil::str_contains('unranked-duels', $name)) {
                                    if (PracticeUtil::isItemFormsEnabled()) {
                                        $form = FormUtil::getMatchForm();
                                        $p->sendForm($form, ['ranked' => false]);
                                    } else InventoryUtil::sendMatchInv($player);
                                } elseif (PracticeUtil::str_contains('ranked-duels', $name)) {
                                    if (PracticeUtil::isItemFormsEnabled()) {
                                        $form = FormUtil::getMatchForm(true);
                                        $p->sendForm($form, ['ranked' => true]);
                                    } else InventoryUtil::sendMatchInv($player, true);
                                } elseif (PracticeUtil::str_contains('ffa', $name)) {
                                    if (PracticeUtil::isItemFormsEnabled()) {
                                        $form = FormUtil::getFFAForm();
                                        $p->sendForm($form);
                                    } else InventoryUtil::sendFFAInv($player);
                                } elseif (PracticeUtil::str_contains('duel-inv', $name)) {
                                    $p->spawnResInvItems();
                                } elseif (PracticeUtil::str_contains('settings', $name)) {
                                    $op = PracticeUtil::testPermission($player, PermissionsHandler::PERMISSION_PLACE_BREAK, false);
                                    $form = FormUtil::getSettingsForm($p->getPlayerName(), $op);
                                    $p->sendForm($form);
                                } elseif (PracticeUtil::str_contains('leaderboard', $name))
                                    InventoryUtil::sendLeaderboardInv($player);

                            } elseif ($name === 'exit.inventory') {

                                $itemHandler->spawnHubItems($player, true);

                            } elseif ($name === 'exit.queue') {

                                $duelHandler->removePlayerFromQueue($player, true);
                                $p->setSpawnScoreboard();
                                ScoreboardUtil::updateSpawnScoreboards($p);

                            } elseif ($name === 'exit.spectator') {

                                if ($duelHandler->isASpectator($player)) {
                                    $duel = $duelHandler->getDuelFromSpec($player);
                                    $duel->removeSpectator($player->getName(), true);
                                } else PracticeUtil::resetPlayer($player);

                                $msg = PracticeUtil::getMessage('spawn-message');
                                $player->sendMessage($msg);

                            } elseif (PracticeUtil::str_contains('party.', $name)) {

                                $partyManager = PracticeCore::getPartyManager();

                                if (!PracticeUtil::str_contains('leader.', $name)) {
                                    if ($name === 'party.general.leave') {
                                        if (!$partyManager->removePlayerFromParty($player->getName())) {
                                            $msg = TextFormat::RED . 'You are not in a party!';
                                            $player->sendMessage($msg);
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $cancel = true;
                }
            } else {
                if ($p->isDuelHistoryItem($item)) {
                    if (PracticeUtil::canUseItems($player, true)) {
                        $name = $item->getName();
                        InventoryUtil::sendResultInv($player, $name);
                    }
                    $cancel = true;

                } else {
                    $checkPlaceBlock = $item->getId() < 255 or PracticeUtil::isSign($item) or $item instanceof ItemBlock or $item instanceof Bucket or $item instanceof FlintSteel;
                    if (PracticeUtil::canUseItems($player)) {
                        if ($checkPlaceBlock === true) {
                            if ($p->isInArena())
                                $cancel = !$p->getCurrentArena()->canBuild();
                            else {
                                if ($p->isInDuel()) {
                                    $duel = $duelHandler->getDuel($p);
                                    if ($duel->isDuelRunning() and $duel->canBuild()) {
                                        $cancel = false;
                                    } else $cancel = true;
                                } else {
                                    $cancel = true;
                                    if (!PracticeUtil::isInSpectatorMode($player) and PracticeUtil::testPermission($player, PermissionsHandler::PERMISSION_PLACE_BREAK, false))
                                        $cancel = !$playerHandler->canPlaceNBreak($player->getName());
                                }
                            }
                            if ($cancel === true) $event->cancel();
                            return;
                        }

                        if ($item->getId() === ItemIds::FISHING_ROD) {

                            $use = false;

                            $checkActions = PracticeUtil::checkActions($action, PlayerInteractEvent::RIGHT_CLICK_BLOCK);

                            if (PracticeUtil::isTapToRodEnabled()) {
                                if ($checkActions === true) {
                                    if ($p->getDevice() === PracticeUtil::WINDOWS_10 or $p->getInput() === PracticeUtil::CONTROLS_MOUSE) {
                                        $use = $action !== PlayerInteractEvent::RIGHT_CLICK_BLOCK;
                                    } else $use = true;
                                }
                            }

                            if ($use === true) PracticeUtil::useRod($item, $player);
                            else $cancel = true;

                        } elseif ($item->getId() === ItemIds::ENDER_PEARL and $item instanceof EnderPearl) {

                            $use = false;

                            $checkActions = PracticeUtil::checkActions($action, PlayerInteractEvent::RIGHT_CLICK_BLOCK);

                            if (PracticeUtil::isTapToPearlEnabled()) {
                                if ($checkActions === true) {
                                    if ($p->getDevice() === PracticeUtil::WINDOWS_10 or $p->getInput() === PracticeUtil::CONTROLS_MOUSE) {
                                        $use = $action !== PlayerInteractEvent::RIGHT_CLICK_BLOCK;
                                    } else $use = true;
                                }
                            }

                            if ($use === true) PracticeUtil::throwPearl($item, $player);

                            $cancel = true;

                        } elseif ($item->getId() === ItemIds::SPLASH_POTION and $item instanceof SplashPotion) {

                            $use = false;

                            $checkActions = PracticeUtil::checkActions($action, PlayerInteractEvent::RIGHT_CLICK_BLOCK);

                            if (PracticeUtil::isTapToPotEnabled()) {
                                if ($checkActions === true) {
                                    if ($p->getDevice() === PracticeUtil::WINDOWS_10 or $p->getInput() === PracticeUtil::CONTROLS_MOUSE) {
                                        $use = $action !== PlayerInteractEvent::RIGHT_CLICK_BLOCK;
                                    } else $use = true;
                                }
                            }

                            if ($use === true) PracticeUtil::throwPotion($item, $player);

                            $cancel = true;

                        } elseif ($item->getId() === ItemIds::MUSHROOM_STEW and $item instanceof MushroomStew) {

                            $inv = $player->getInventory();

                            $inv->setItemInHand(VanillaItems::AIR());

                            $newHealth = $player->getHealth() + 7.0;

                            if ($newHealth > $player->getMaxHealth()) $newHealth = $player->getMaxHealth();

                            $player->setHealth($newHealth);

                            $cancel = true;
                        }

                    } else {

                        $cancel = true;

                        if ($checkPlaceBlock === true) {
                            if ($p->isInArena()) {
                                $cancel = !$p->getCurrentArena()->canBuild();
                            } else {
                                if ($p->isInDuel()) {
                                    $duel = $duelHandler->getDuel($p);
                                    if ($duel->isDuelRunning() and $duel->canBuild()) {
                                        $cancel = false;
                                    }
                                } else {
                                    if (!PracticeUtil::isInSpectatorMode($player) and PracticeUtil::testPermission($player, PermissionsHandler::PERMISSION_PLACE_BREAK, false))
                                        $cancel = !$playerHandler->canPlaceNBreak($player->getName());
                                }
                            }
                            if ($cancel === true) $event->cancel();
                            return;
                        }
                    }
                }
            }
        }

        if ($cancel === true) $event->cancel();

    }

    /**
     * @param BlockPlaceEvent $event
     * @return void
     */
    public function onBlockPlace(BlockPlaceEvent $event): void
    {
        $item = $event->getItem();
        $player = $event->getPlayer();
        $cancel = false;

        $playerHandler = PracticeCore::getPlayerHandler();
        $itemHandler = PracticeCore::getItemHandler();
        $duelHandler = PracticeCore::getDuelHandler();

        if ($playerHandler->isPlayer($player)) {
            $p = $playerHandler->getPlayer($player);
            $name = $player->getName();

            if ($itemHandler->isPracticeItem($item)) $cancel = true;
            else {
                if ($p->isInArena()) {
                    $cancel = !$p->getCurrentArena()->canBuild();
                } else {
                    if ($p->isInDuel()) {
                        $duel = $duelHandler->getDuel($name);
                        if ($duel->isDuelRunning() and $duel->canBuild()) {
                            $blockAgainst = $event->getBlockAgainst();
                            $blockReplaced = $event->getBlockReplaced();
                            $place = $duel->canPlaceBlock($blockAgainst);
                            if ($place === true)
                                $duel->addBlock($blockReplaced);
                            else $cancel = true;
                        } else $cancel = true;
                    } else {
                        $cancel = true;
                        if (!PracticeUtil::isInSpectatorMode($player) and PracticeUtil::testPermission($player, PermissionsHandler::PERMISSION_PLACE_BREAK, false))
                            $cancel = !$playerHandler->canPlaceNBreak($name);
                    }
                }
            }
        }

        if ($cancel === true) $event->cancel();
    }

    /**
     * @param BlockBreakEvent $event
     * @return void
     */
    public function onBlockBreak(BlockBreakEvent $event): void
    {
        $item = $event->getItem();
        $player = $event->getPlayer();

        $cancel = false;

        $playerHandler = PracticeCore::getPlayerHandler();
        $itemHandler = PracticeCore::getItemHandler();
        $duelHandler = PracticeCore::getDuelHandler();

        if ($playerHandler->isPlayer($player)) {
            $p = $playerHandler->getPlayer($player);
            if ($itemHandler->isPracticeItem($item))
                $cancel = true;
            else {
                if ($p->isInArena()) {
                    $cancel = !$p->getCurrentArena()->canBuild();
                } else {
                    if ($p->isInDuel()) {
                        $duel = $duelHandler->getDuel($player->getName());
                        if ($duel->isDuelRunning() and $duel->canBreak())
                            $cancel = !$duel->removeBlock($event->getBlock());
                        else $cancel = true;
                    } else {
                        $cancel = true;
                        if (!PracticeUtil::isInSpectatorMode($player) and PracticeUtil::testPermission($player, PermissionsHandler::PERMISSION_PLACE_BREAK, false))
                            $cancel = !$playerHandler->canPlaceNBreak($player->getName());

                    }
                }
            }
        }

        if ($cancel === true) $event->cancel();
    }

    /**
     * @param BlockFormEvent $event
     * @return void
     */
    public function onBlockReplace(BlockFormEvent $event): void
    {
        $arenaHandler = PracticeCore::getArenaHandler();

        $duelHandler = PracticeCore::getDuelHandler();

        $arena = $arenaHandler->getArenaClosestTo($event->getBlock()->getPosition());
        $cancel = false;
        if (!is_null($arena) and ($arena->getArenaType() === PracticeArena::DUEL_ARENA)) {
            if ($duelHandler->isArenaInUse($arena->getName())) {
                $duel = $duelHandler->getDuel($arena->getName(), true);
                if ($duel->isDuelRunning()) {
                    if ($event->getNewState() instanceof Liquid)
                        $duel->addBlock($event->getBlock());
                    else $cancel = true;
                } else $cancel = true;
            } else {
                $cancel = true;
            }
        } else {
            $cancel = true;
        }

        if ($cancel === true) $event->cancel();
    }

    /**
     * @param BlockSpreadEvent $event
     * @return void
     */
    public function onBlockSpread(BlockSpreadEvent $event): void
    {
        $arenaHandler = PracticeCore::getArenaHandler();
        $duelHandler = PracticeCore::getDuelHandler();

        $arena = $arenaHandler->getArenaClosestTo($event->getBlock()->getPosition());
        $cancel = false;
        if (!is_null($arena) and ($arena->getArenaType() === PracticeArena::DUEL_ARENA)) {
            if ($duelHandler->isArenaInUse($arena->getName())) {
                $duel = $duelHandler->getDuel($arena->getName(), true);
                if ($duel->isDuelRunning()) {
                    if ($event->getNewState() instanceof Liquid)
                        $duel->addBlock($event->getBlock());
                    else $cancel = true;
                } else $cancel = true;

            } else $cancel = true;

        } else $cancel = true;

        if ($cancel === true) $event->cancel();
    }

    /**
     * @param PlayerBucketFillEvent $event
     * @return void
     */
    public function onBucketFill(PlayerBucketFillEvent $event): void
    {
        $item = $event->getItem();
        $player = $event->getPlayer();

        $playerHandler = PracticeCore::getPlayerHandler();
        $itemHandler = PracticeCore::getItemHandler();
        $duelHandler = PracticeCore::getDuelHandler();

        $cancel = false;

        if ($playerHandler->isPlayer($player)) {
            $p = $playerHandler->getPlayer($player);
            if ($itemHandler->isPracticeItem($item)) {
                $cancel = true;
            } else {
                if ($p->isInArena()) $cancel = !$p->getCurrentArena()->canBuild();
                else {
                    if ($p->isInDuel()) {
                        $duel = $duelHandler->getDuel($player->getName());
                        if ($duel->isDuelRunning() and $duel->canBuild())
                            $cancel = !$duel->removeBlock($event->getBlockClicked());
                        else $cancel = true;
                    } else {
                        $cancel = true;
                        if (!PracticeUtil::isInSpectatorMode($player) and PracticeUtil::testPermission($player, PermissionsHandler::PERMISSION_PLACE_BREAK, false))
                            $cancel = !$playerHandler->canPlaceNBreak($player->getName());

                        if (PracticeUtil::areWorldEqual($player->getWorld(), PracticeUtil::getDefaultWorld()))
                            $cancel = true;
                    }
                }
            }
        }

        if ($cancel === true) $event->cancel();
    }

    /**
     * @param PlayerBucketEmptyEvent $event
     * @return void
     */
    public function onBucketEmpty(PlayerBucketEmptyEvent $event): void
    {
        $item = $event->getBucket();
        $player = $event->getPlayer();
        $cancel = false;

        $playerHandler = PracticeCore::getPlayerHandler();
        $itemHandler = PracticeCore::getItemHandler();
        $duelHandler = PracticeCore::getDuelHandler();

        if ($playerHandler->isPlayer($player)) {
            $p = $playerHandler->getPlayer($player);
            if ($itemHandler->isPracticeItem($item)) $cancel = true;
            else {
                if ($p->isInArena()) {
                    $cancel = !$p->getCurrentArena()->canBuild();
                } else {
                    if ($p->isInDuel()) {
                        $duel = $duelHandler->getDuel($player->getName());
                        if ($duel->isDuelRunning() and $duel->canBuild()) {
                            $duel->addBlock($event->getBlockClicked());
                        } else $cancel = true;
                    } else {

                        $cancel = true;

                        if (!PracticeUtil::isInSpectatorMode($player) and PracticeUtil::testPermission($player, PermissionsHandler::PERMISSION_PLACE_BREAK, false))
                            $cancel = !$playerHandler->canPlaceNBreak($player->getName());

                        if (PracticeUtil::areWorldEqual($player->getWorld(), PracticeUtil::getDefaultWorld()))
                            $cancel = true;
                    }
                }
            }
        }

        if ($cancel === true) $event->cancel();
    }

    /**
     * @param BlockBurnEvent $event
     * @return void
     */
    public function onFireSpread(BlockBurnEvent $event)
    {
        $event->cancel();
    }

    /**
     * @param PlayerCommandPreprocessEvent $event
     * @return void
     */
    public function onCommandPreprocess(PlayerCommandPreprocessEvent $event): void
    {
        $p = $event->getPlayer();
        $cancel = false;

        $playerHandler = PracticeCore::getPlayerHandler();

        if ($playerHandler->isPlayer($p)) {
            $player = $playerHandler->getPlayer($p);
            $message = $event->getMessage();
            $firstChar = $message[0];
            $testInAntiSpam = false;
            if ($firstChar === '/') {

                $usableCommandsInCombat = ['ping', 'tell', 'say'];

                $tests = ['/ping', '/tell', '/say', '/w'];

                if (PracticeUtil::str_contains('/me', $message)) {
                    $event->cancel();
                    return;
                }

                $sendMsg = PracticeUtil::str_contains_from_arr($message, $tests);

                if (!$player->canUseCommands(!$sendMsg)) {
                    $use = false;
                    foreach ($usableCommandsInCombat as $value) {
                        $test = '/' . $value;
                        if (PracticeUtil::str_contains($test, $message)) {
                            $use = true;
                            if ($value === 'say') $testInAntiSpam = true;
                            break;
                        }
                    }

                    if ($use === false) $cancel = true;
                }
            } else $testInAntiSpam = true;

            if ($testInAntiSpam === true) {
                if (PracticeUtil::canPlayerChat($p)) {
                    if ($player->isInAntiSpam()) {
                        $player->sendMessage(PracticeUtil::getMessage('antispam-msg'));
                        $cancel = true;
                    }
                } else $cancel = true;
            }
        }

        if ($cancel === true) $event->cancel();
    }

    /**
     * @param PlayerChatEvent $event
     * @return void
     */
    public function onChat(PlayerChatEvent $event): void
    {
        $p = $event->getPlayer();
        $cancel = false;

        $playerHandler = PracticeCore::getPlayerHandler();

        if (PracticeUtil::isRanksEnabled()) {
            $message = $event->getMessage();
            $event->setFormat(PracticeUtil::getChatFormat($p, $message));
        }

        if (!PracticeUtil::canPlayerChat($p)) $cancel = true;
        else {
            $player = $playerHandler->getPlayer($p);
            if ($player !== null) {
                $player = $playerHandler->getPlayer($p);
                if (!$player->isInAntiSpam())
                    $player->setInAntiSpam(true);
                else {
                    $player->sendMessage(PracticeUtil::getMessage('antispam-msg'));
                    $cancel = true;
                }
            }
        }

        if ($cancel === true) $event->cancel();
    }

    /**
     * @param DataPacketSendEvent $event
     * @return void
     */
    public function onPacketSend(DataPacketSendEvent $event): void
    {
        $packets = $event->getPackets();
        foreach($packets as $packet){
            if ($packet->pid() == LevelSoundEventPacket::NETWORK_ID) {
                switch ($packet->sound) {
                    case LevelSoundEvent::ATTACK:
                    case LevelSoundEvent::ATTACK_NODAMAGE:
                    case LevelSoundEvent::ATTACK_STRONG:
                        $event->cancel();
                        break;
                }
            }
        }
    }

    /**
     * @param DataPacketReceiveEvent $event
     * @return void
     */
    public function onPacketReceive(DataPacketReceiveEvent $event): void
    {
        $pkt = $event->getPacket();
        $player = $event->getOrigin()->getPlayer();

        $playerHandler = PracticeCore::getPlayerHandler();

        if ($pkt instanceof LevelSoundEventPacket) {
            if ($pkt->sound == LevelSoundEvent::ATTACK_NODAMAGE) {
                if ($playerHandler->isPlayer($player)) {
                    $p = $playerHandler->getPlayer($player);
                    $p->addCps(true);
                }

                $event->cancel();
            }
        }
    }

    /**
     * @param InventoryTransactionEvent $event
     * @return void
     */
    public function onItemMoved(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $p = $transaction->getSource();
        $lvl = $p->getWorld();
        $cancel = false;

        $playerHandler = PracticeCore::getPlayerHandler();

        if ($playerHandler->isPlayerOnline($p)) {
            $player = $playerHandler->getPlayer($p);

            if (PracticeUtil::areWorldEqual($lvl, PracticeUtil::getDefaultWorld())) {
                if (PracticeUtil::isLobbyProtectionEnabled()) {
                    $cancel = !$player->isInDuel() and !$player->isInArena();

                    if ($cancel === true and PracticeUtil::testPermission($p, PermissionsHandler::PERMISSION_PLACE_BREAK, false)) $cancel = !$playerHandler->canPlaceNBreak($p->getName());
                }

            }
        }

        if ($cancel === true) $event->cancel();
    }

    /**
     * @param PlayerDropItemEvent $event
     * @return void
     */
    public function onItemDropped(PlayerDropItemEvent $event): void
    {
        $p = $event->getPlayer();
        $cancel = false;

        $playerHandler = PracticeCore::getPlayerHandler();

        if ($playerHandler->isPlayer($p)) {
            $player = $playerHandler->getPlayer($p);
            $level = $p->getWorld();

            if (PracticeUtil::isLobbyProtectionEnabled()) $cancel = PracticeUtil::areWorldEqual($level, PracticeUtil::getDefaultWorld()) or $player->isInDuel();

            if ($cancel === false) $cancel = PracticeUtil::isInSpectatorMode($p);
        }

        if ($cancel === true) $event->cancel();
    }

    /**
     * @param PluginDisableEvent $event
     * @return void
     */
    public function onPluginDisabled(PluginDisableEvent $event): void
    {
        $plugin = $event->getPlugin();

        $playerHandler = PracticeCore::getPlayerHandler();

        $duelHandler = PracticeCore::getDuelHandler();

        $server = $plugin->getServer();

        if ($plugin->getName() === PracticeUtil::PLUGIN_NAME) {
            $onlinePlayers = $server->getOnlinePlayers();
            foreach ($onlinePlayers as $player) {
                if ($playerHandler->isPlayerOnline($player)) {
                    $p = $playerHandler->getPlayer($player);
                    if (!$p->isInDuel()) {
                        PracticeUtil::resetPlayer($player);
                    } else {
                        $duel = $duelHandler->getDuel($player);
                        if (!$duel->didDuelEnd()) $duel->endDuelPrematurely(true);
                    }
                }
            }
        }

        $worlds = $server->getWorldManager()->getWorlds();

        foreach ($worlds as $world) PracticeUtil::clearEntitiesIn($world, false, true);

        if ($server->isRunning()) PracticeUtil::kickAll('Restarting Server');
    }
}