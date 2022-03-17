<?php

declare(strict_types=1);

namespace practice;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonException;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\command\CommandSender;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\EnderPearl;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\item\SplashPotion;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\types\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Internet;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;
use practice\arenas\PracticeArena;
use practice\game\entity\FishingHook;
use practice\misc\PracticeChunkLoader;
use practice\player\PracticePlayer;
use practice\ranks\Rank;
use practice\ranks\RankHandler;
use practice\scoreboard\ScoreboardUtil;

class PracticeUtil
{
    /** @var int */
    public const MOBILE_SEPARATOR_LEN = 27;
    /** @var string */
    public const WIN10_ADDED_SEPARATOR = '-----';
    /** @var string */
    public const PLUGIN_NAME = 'Practice';

    /** @var int */
    public const WINDOWS_10 = 7;
    /** @var int */
    public const IOS = 2;
    /** @var int */
    public const ANDROID = 1;
    /** @var int */
    public const WINDOWS_32 = 8;
    /** @var int */
    public const UNKNOWN = -1;
    /** @var int */
    public const MAC_EDU = 3;
    /** @var int */
    public const FIRE_EDU = 4;
    /** @var int */
    public const GEAR_VR = 5;
    /** @var int */
    public const HOLOLENS_VR = 6;
    /** @var int */
    public const DEDICATED = 9;
    /** @var int */
    public const ORBIS = 10;
    /** @var int */
    public const NX = 11;

    /** @var int */
    public const CONTROLS_UNKNOWN = 0;
    /** @var int */
    public const CONTROLS_MOUSE = 1;
    /** @var int */
    public const CONTROLS_TOUCH = 2;
    /** @var int */
    public const CONTROLS_CONTROLLER = 3;

    # ITEM FUNCTIONS

    /**
     * @param Item $item
     * @param bool $testCount
     * @return bool
     */
    public static function isPotion(Item $item, bool $testCount = false): bool
    {
        return ($testCount === true) ? ($item->getId() === ItemIds::POTION and $item->getCount() > 1) : $item->getId() === ItemIds::POTION;
    }

    /**
     * @param Item $item
     * @return bool
     */
    public static function isSign(Item $item): bool
    {
        $signs = [ItemIds::SIGN, ItemIds::BIRCH_SIGN, ItemIds::SPRUCE_SIGN, ItemIds::JUNGLE_SIGN, ItemIds::DARKOAK_SIGN, ItemIds::ACACIA_SIGN];
        $id = $item->getId();

        return self::arr_contains_value($id, $signs);
    }

    /**
     * @param $needle
     * @param array $haystack
     * @param bool $strict
     * @return bool
     */
    public static function arr_contains_value($needle, array $haystack, bool $strict = TRUE): bool
    {
        return in_array($needle, $haystack, $strict);
    }

    /**
     * @param int $count
     * @return int
     */
    public static function getProperCount(int $count): int
    {
        return ($count <= 0 ? 1 : $count);
    }

    /**
     * @param string $s
     * @return null
     */
    public static function getItemFromString(string $s)
    {
        $enchantsArr = [];

        if (self::str_contains('-', $s)) {
            $arr = explode('-', $s);
            $arrSize = count($arr);
            $itemArr = explode(':', $arr[0]);
            if ($arrSize > 1) $enchantsArr = explode(',', $arr[1]);
        } else $itemArr = explode(':', $s);

        $baseItem = null;

        $len = count($itemArr);

        if ($len >= 1 and $len < 4) {
            $id = intval($itemArr[0]);
            $count = 1;
            $meta = 0;

            if ($len == 2) $meta = intval($itemArr[1]);
            else if ($len == 3) {
                $count = intval($itemArr[2]);
                $meta = intval($itemArr[1]);
            }

            $isGoldenHead = false;

            if ($id === ItemIds::GOLDEN_APPLE and $meta === 1) {
                $isGoldenHead = true;
                $meta = 0;
            }

            $baseItem = (new ItemFactory)->get($id, $meta, $count);

            if ($isGoldenHead === true) $baseItem = $baseItem->setCustomName(self::getName('golden-head'));
        }

        $enchantCount = count($enchantsArr);

        if ($enchantCount > 0 and !is_null($baseItem)) {
            for ($i = 0; $i < $enchantCount; $i++) {
                $enchant = strval($enchantsArr[$i]);
                $enArr = explode(':', $enchant);
                $arrCount = count($enArr);
                if ($arrCount === 2) {
                    $eid = intval($enArr[0]);
                    $elvl = intval($enArr[1]);
                    $e = new EnchantmentInstance(EnchantmentIdMap::getInstance()->fromId($eid), $elvl);
                    $baseItem->addEnchantment($e);
                }
            }
        }

        return $baseItem;
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @param bool $use_mb
     * @return bool
     */
    public static function str_contains(string $needle, string $haystack, bool $use_mb = false): bool
    {
        $result = false;
        $type = ($use_mb === true) ? mb_strpos($haystack, $needle) : strpos($haystack, $needle);
        if (is_bool($type)) {
            $result = $type;
        } elseif (is_int($type)) {
            $result = $type > -1;
        }
        return $result;
    }

    /**
     * @param string $str
     * @return string
     */
    public static function getName(string $str): string
    {
        $cfg = PracticeCore::getInstance()->getNameConfig();

        if (self::str_contains('.', $str)) {

            $arrSplit = explode('.', $str);

            $val = $cfg->get($arrSplit[0]);

            $len = count($arrSplit);

            for ($i = 1; $i < $len; $i++)
                $val = $val[$arrSplit[$i]];


            $obj = strval($val);

        } else $obj = strval($cfg->get($str));

        return $obj;
    }

    /**
     * @param string $s
     * @return int
     */
    public static function getArmorFromKey(string $s): int
    {
        return match ($s) {
            'helmet' => 0,
            'chestplate' => 1,
            'leggings' => 2,
            'boots' => 3,
            default => -1,
        };
    }

    //SERVER CONFIGURATION FUNCTIONS

    /**
     * @param Player $player
     * @param bool $keepAir
     * @return array
     */
    public static function inventoryToArray(Player $player, bool $keepAir = false): array
    {
        $result = [];

        $armor = [];
        $items = [];

        $armorInv = $player->getArmorInventory();
        $itemInv = $player->getInventory();

        $armorSize = $armorInv->getSize();

        $armorVals = ['helmet', 'chestplate', 'boots', 'leggings'];

        for ($i = 0; $i < $armorSize; $i++) {
            $item = $armorInv->getItem($i);
            if (isset($armorVals[$i])) {
                $key = $armorVals[$i];
                $armor[$key] = $item;
            }
        }

        $itemSize = $itemInv->getSize();

        for ($i = 0; $i < $itemSize; $i++) {
            $item = $itemInv->getItem($i);
            $exec = !((!$keepAir and $item->getId() === 0));
            if ($exec === true) $items[] = $item;
        }

        $result['armor'] = $armor;
        $result['items'] = $items;

        return $result;
    }

    /**
     * @param array $arr
     * @return null
     */
    public static function getBlockFromArr(array $arr)
    {
        $result = null;

        if (self::arr_contains_keys($arr, 'id', 'meta')) {
            $id = intval($arr['id']);
            $meta = intval($arr['meta']);

            $result = BlockFactory::getInstance()->get($id, $meta);
        }

        return $result;
    }

    /**
     * @param array $haystack
     * @param ...$needles
     * @return bool
     */
    public static function arr_contains_keys(array $haystack, ...$needles): bool
    {
        $result = true;

        foreach ($needles as $key) {
            if (!isset($haystack[$key])) {
                $result = false;
                break;
            }
        }

        return $result;
    }

    /**
     * @param Block $block
     * @return array
     */
    #[ArrayShape(['id' => "int", 'meta' => "mixed"])] public static function blockToArr(Block $block): array
    {
        return ['id' => $block->getId(), 'meta' => $block->getMeta()];
    }

    /**
     * @return bool
     */
    public static function isChatFilterEnabled(): bool
    {
        $cfg = PracticeCore::getInstance()->getConfig();
        return boolval($cfg->get('enable-chat-filter'));
    }

    /**
     * @return bool
     */
    public static function isTapToPearlEnabled(): bool
    {
        $cfg = PracticeCore::getInstance()->getConfig();
        return boolval($cfg->get('enable-tap-to-pearl'));
    }

    /**
     * @return bool
     */
    public static function isTapToPotEnabled(): bool
    {
        $cfg = PracticeCore::getInstance()->getConfig();
        return boolval($cfg->get('enable-tap-to-pot'));
    }

    /**
     * @return bool
     */
    public static function isTapToRodEnabled(): bool
    {
        $cfg = PracticeCore::getInstance()->getConfig();
        return boolval($cfg->get('enable-tap-to-rod'));
    }

    /**
     * @return bool
     */
    public static function isRanksEnabled(): bool
    {
        $cfg = PracticeCore::getInstance()->getConfig();
        return boolval($cfg->get('enable-ranks'));
    }

    /**
     * @param bool $res
     * @return void
     * @throws JsonException
     */
    public static function setRanksEnabled(bool $res): void
    {
        $cfg = PracticeCore::getInstance()->getConfig();
        $cfg->set('enable-ranks', $res);
        $cfg->save();
    }

    /**
     * @return bool
     */
    public static function isItemFormsEnabled(): bool
    {
        $cfg = PracticeCore::getInstance()->getConfig();
        return boolval($cfg->get('enable-hub-formwindows'));
    }

    /**
     * @param bool $res
     * @return void
     * @throws JsonException
     */
    public static function setItemFormsEnabled(bool $res = true): void
    {
        $cfg = PracticeCore::getInstance()->getConfig();
        $cfg->set('enable-hub-formwindows', $res);
        $cfg->save();
    }

    //TIME FUNCTIONS

    /**
     * @return bool
     */
    public static function isMysqlEnabled(): bool
    {
        $cfg = PracticeCore::getInstance()->getConfig();
        return boolval($cfg->get('enable-mysql'));
    }

    /**
     * @return float
     */
    public static function currentTimeMillis(): float
    {
        $time = microtime(true);
        return $time * 1000;
    }

    /**
     * @param int $seconds
     * @return int
     */
    public static function secondsToTicks(int $seconds): int
    {
        return $seconds * 20;
    }

    /**
     * @param int $minutes
     * @return int
     */
    public static function minutesToTicks(int $minutes): int
    {
        return $minutes * 1200;
    }

    /**
     * @param int $hours
     * @return int
     */
    public static function hoursToTicks(int $hours): int
    {
        return $hours * 72000;
    }

    /**
     * @param int $tick
     * @return int
     */
    public static function ticksToSeconds(int $tick): int
    {
        return intval($tick / 20);
    }

    /**
     * @param int $tick
     * @return int
     */
    public static function ticksToMinutes(int $tick): int
    {
        return intval($tick / 1200);
    }

    /**
     * @param int $tick
     * @return int
     */
    public static function ticksToHours(int $tick): int
    {
        return intval($tick / 72000);
    }

    //PLAYER SPECIFIC FUNCTIONS

    /**
     * @param int $month
     * @param int $year
     * @return int
     * @noinspection PhpParamsInspection => I have no clue what the fuck this is.
     */
    #[Pure] public static function getLastDayOfMonth(int $month, int $year): int
    {
        $result = -1;

        $thirtyOne = [1, 3, 5, 7, 8, 10, 12];

        $thirty = [4, 6, 9, 11];

        $feb = 2;

        if (self::arr_contains_value($month, $thirtyOne)) $result = 31;
        elseif (self::arr_contains_value($month, $thirty)) $result = 30;
        elseif ($month === $feb) $result = intlgregcal_is_leap_year($year) ? 29 : 28;

        return $result;
    }

    /**
     * @return string
     */
    public static function genAnonymousName(): string
    {
        $result = 'anonymous';
        $val = rand(0, 100000);
        return $result . $val;
    }

    /**
     * @param $player
     * @return string|null
     */
    #[Pure] public static function getPlayerName($player): ?string
    {
        $result = null;
        if (isset($player) and !is_null($player)) {
            if ($player instanceof Player) {
                $result = $player->getName();
            } elseif ($player instanceof PracticePlayer) {
                $result = $player->getPlayerName();
            } elseif (is_string($player)) {
                $result = $player;
            }
        }
        return $result;
    }

    /**
     * @param $player
     * @param bool $res
     * @return void
     */
    public static function setCanHit($player, bool $res): void
    {
        $pl = null;

        if (isset($player) and !is_null($player)) {
            if ($player instanceof Player) {
                $pl = $player;
            } elseif ($player instanceof PracticePlayer) {
                if ($player->isOnline())
                    $pl = $player->getPlayer();
            } else if (is_string($player)) {
                $pl = Server::getInstance()->getPlayerExact($player);
            }
        }

        if (!is_null($pl)) {
            $pkt = new AdventureSettingsPacket();
            $pkt->setFlag(AdventureSettingsPacket::NO_PVP, $res);
            $pkt->targetActorUniqueId = $pl->getId();

            if ($pl->handleAdventureSettings($pkt)) $pl->getNetworkSession()->sendDataPacket($pkt);
        }
    }

    /**
     * @param $player
     * @param bool $lobby
     * @return bool
     */
    public static function canUseItems($player, bool $lobby = false): bool
    {
        $result = false;

        $playerHandler = PracticeCore::getPlayerHandler();

        $duelHandler = PracticeCore::getDuelHandler();

        if ($playerHandler->isPlayerOnline($player)) {
            $p = $playerHandler->getPlayer($player);

            $pl = $p->getPlayer();

            $level = $pl->getLevel();

            $execute = false;

            if (self::isLobbyProtectionEnabled()) {
                if ($lobby === false) {

                    if (!self::areWorldEqual($level, self::getDefaultWorld()))
                        $execute = true;
                    else {
                        if ($p->isInArena()) $execute = true;
                        elseif ($p->isInDuel()) {
                            $duel = $duelHandler->getDuel($pl);
                            if (!$duel->isLoadingDuel()) $execute = true;
                        }
                    }
                } else $execute = self::areWorldEqual($level, self::getDefaultWorld()) and !$p->isInDuel() and !$p->isInArena();

            } else $execute = true;

            if ($execute === true) {
                $test = $p->isOnline() and !$p->isInvisible() and !self::isFrozen($p->getPlayer());
                $result = $test === true || $duelHandler->isASpectator($p->getPlayer());
            }
        }
        return $result;
    }

    /**
     * @return bool
     */
    public static function isLobbyProtectionEnabled(): bool
    {
        $cfg = PracticeCore::getInstance()->getConfig();
        return boolval($cfg->get('lobby-protection'));
    }

    /**
     * @param World $a
     * @param World $b
     * @return bool
     */
    #[Pure] public static function areWorldEqual(World $a, World $b): bool
    {
        $aName = $a->getDisplayName();
        $bName = $b->getDisplayName();
        return $aName === $bName;
    }

    /**
     * @return World
     */
    public static function getDefaultWorld(): World
    {
        $server = Server::getInstance();

        $cfg = PracticeCore::getInstance()->getConfig();
        $level = strval($cfg->get('lobby-level'));
        $result = null;

        if (isset($level) and !is_null($level)) {
            $lvl = $server->getWorldManager()->getWorldByName($level);
            if (!is_null($lvl))
                $result = $lvl;
        }

        if (is_null($result)) $result = $server->getWorldManager()->getDefaultWorld();
        return $result;
    }

    /**
     * @param Player $player
     * @return bool
     */
    #[Pure] public static function isFrozen(Player $player): bool
    {
        return $player->isImmobile();
    }

    /**
     * @param SplashPotion $potion
     * @param Player $player
     * @param bool $animate
     * @return void
     */
    public static function throwPotion(SplashPotion $potion, Player $player, bool $animate = false)
    {
        $playerHandler = PracticeCore::getPlayerHandler();

        $use = false;

        if ($playerHandler->isPlayerOnline($player)) {
            $p = $playerHandler->getPlayer($player);

            $pl = $p->getPlayer();

            $use = $p->isOnline() and !self::isFrozen($pl) and !self::isInSpectatorMode($pl);

            if ($use === true and $p->isInDuel()) {
                $duel = PracticeCore::getDuelHandler()->getDuel($p->getPlayerName());
                $use = !$duel->isLoadingDuel();
            }
        }

        if ($use === true) {

            $p = $playerHandler->getPlayer($player);
            $pl = $p->getPlayer();
            $potion->onClickAir($pl, $pl->getDirectionVector());

            if (!$pl->isCreative()) {
                $inv = $pl->getInventory();
                $inv->setItem($inv->getHeldItemIndex(), VanillaItems::AIR());
            }

            if ($animate === true) {
                $pkt = new AnimatePacket();
                $pkt->action = AnimatePacket::ACTION_SWING_ARM;
                $pkt->actorRuntimeId = $pl->getId();
                if ($pl instanceof Player)
                    $pl->getNetworkSession()->getBroadcaster()->broadcastPackets($pl->getWorld()->getPlayers(), [$pkt]);
            }
        }
    }

    /**
     * @param $player
     * @return bool
     */
    public static function isInSpectatorMode($player): bool
    {
        $result = false;

        $playerHandler = PracticeCore::getPlayerHandler();

        if ($playerHandler->isPlayer($player)) {
            $p = $playerHandler->getPlayer($player);
            if ($p->getPlayer()->getGamemode() === 3) {
                $result = true;
            } else $result = $p->isInvisible() and self::canFly($p->getPlayer()) and !$p->canHitPlayer();


        }
        return $result;
    }

    /**
     * @param Player $player
     * @return bool
     */
    #[Pure] public static function canFly(Player $player): bool
    {
        return $player->getAllowFlight();
    }

    /**
     * @param EnderPearl $item
     * @param $player
     * @param bool $animate
     * @return void
     */
    public static function throwPearl(EnderPearl $item, $player, bool $animate = false)
    {
        $exec = false;

        $playerHandler = PracticeCore::getPlayerHandler();

        if ($playerHandler->isPlayerOnline($player)) {

            $p = $playerHandler->getPlayer($player);

            $exec = !self::isEnderpearlCooldownEnabled() || $p->canThrowPearl();

            if ($exec === true)
                $exec = $p->isOnline() and !self::isFrozen($p->getPlayer()) and !self::isInSpectatorMode($p->getPlayer());

            if ($exec === true and $p->isInDuel()) {
                $duel = PracticeCore::getDuelHandler()->getDuel($p->getPlayerName());
                $exec = !$duel->isLoadingDuel();
            }
        }

        if ($exec === true) {
            $p = $playerHandler->getPlayer($player);

            $p->trackThrow();

            $pl = $p->getPlayer();
            $item->onClickAir($pl, $pl->getDirectionVector());

            if (self::isEnderpearlCooldownEnabled())
                $p->setThrowPearl(false);

            if ($animate === true) {
                $pkt = new AnimatePacket();
                $pkt->action = AnimatePacket::ACTION_SWING_ARM;
                $pkt->actorRuntimeId = $pl->getId();
                $pl->getNetworkSession()->getBroadcaster()->broadcastPackets($pl->getWorld()->getPlayers(), [$pkt]);
            }

            if (!$pl->isCreative()) {
                $inv = $pl->getInventory();
                $index = $inv->getHeldItemIndex();
                $count = $item->getCount();
                if ($count > 1) $inv->setItem($index, ItemFactory::getInstance()->get($item->getId(), $item->getMeta(), $count));
                else $inv->setItem($index, VanillaItems::AIR());
            }
        }
    }

    /**
     * @return bool
     */
    public static function isEnderpearlCooldownEnabled(): bool
    {
        $cfg = PracticeCore::getInstance()->getConfig();
        return boolval($cfg->get('enable-enderpearl-cooldown'));
    }

    /**
     * @param Item $item
     * @param $player
     * @param bool $animate
     * @return void
     */
    public static function useRod(Item $item, $player, bool $animate = false)
    {
        $exec = false;

        $playerHandler = PracticeCore::getPlayerHandler();

        $use = false;

        if ($playerHandler->isPlayerOnline($player)) {
            $p = $playerHandler->getPlayer($player);

            $pl = $p->getPlayer();

            $use = $p->isOnline() and !self::isFrozen($pl) and !self::isInSpectatorMode($pl);

            if ($use === true and $p->isInDuel()) {
                $duel = PracticeCore::getDuelHandler()->getDuel($p->getPlayerName());
                $use = !$duel->isLoadingDuel();
            }
        }

        if ($use === true) {
            $p = $playerHandler->getPlayer($player);

            $pl = $p->getPlayer();

            if ($p->isFishing()) {
                $p->stopFishing();
                $exec = true;
            } else {
                $p->startFishing();
            }

            if ($animate === true) {
                $pkt = new AnimatePacket();
                $pkt->action = AnimatePacket::ACTION_SWING_ARM;
                $pkt->actorRuntimeId = $pl->getId();
                $pl->getNetworkSession()->getBroadcaster()->broadcastPackets($pl->getWorld()->getPlayers(), [$pkt]);
            }
        }

        if ($exec === true) {
            $practicePlayer = $playerHandler->getPlayer($player);
            $p = $practicePlayer->getPlayer();
            $inv = $p->getInventory();
            if (!$p->isCreative()) {
                $newItem = ItemFactory::getInstance()->get($item->getId(), $item->getMeta() + 1);
                if ($item->getMeta() > 65)
                    $newItem = VanillaItems::AIR();
                $inv->setItemInHand($newItem);
            }
        }
    }

    /**
     * @param int $action
     * @param int ...$actions
     * @return bool
     */
    #[Pure] public static function checkActions(int $action, int...$actions): bool
    {
        return self::arr_indexOf($action, $actions, true) !== -1;
    }

    /**
     * @param $needle
     * @param array $haystack
     * @param bool $strict
     * @return bool|int|string
     */
    public static function arr_indexOf($needle, array $haystack, bool $strict = false): bool|int|string
    {
        $index = array_search($needle, $haystack, $strict);

        if (is_bool($index) and $index === false)
            $index = -1;

        return $index;
    }

    /**
     * @param Player $p
     * @return bool
     */
    public static function canPlayerChat(Player $p): bool
    {
        $playerHandler = PracticeCore::getPlayerHandler();
        if (PracticeCore::getInstance()->isServerMuted())
            $res = !$playerHandler->isOwner($p) and !$playerHandler->isMod($p) and !$playerHandler->isAdmin($p);
        else
            $res = !$playerHandler->isPlayerMuted($p->getName());

        return $res;
    }

    /**
     * @param Player $sender
     * @param $player
     * @return bool
     */
    public static function canAcceptPlayer(Player $sender, $player): bool
    {
        $result = false;

        $ivsiHandler = PracticeCore::get1vs1Handler();

        if (self::canRequestPlayer($sender, $player)) {
            if ($ivsiHandler->hasPendingRequest($sender, $player)) {
                $request = $ivsiHandler->getRequest($sender, $player);
                $result = $request->canAccept();
            } else self::getMessage('duels.1vs1.no-pending-rqs');
        }

        return $result;
    }

    /**
     * @param Player $sender
     * @param $player
     * @return bool
     */
    public static function canRequestPlayer(Player $sender, $player): bool
    {
        $result = false;

        $playerHandler = PracticeCore::getPlayerHandler();

        if ($playerHandler->isPlayerOnline($player)) {
            $msg = null;
            $requested = $playerHandler->getPlayer($player);
            $rqName = $requested->getPlayerName();

            if ($requested->isInArena())
                $msg = self::str_replace(self::getMessage('duels.misc.arena-msg'), ['%player%' => $rqName]);
            else {
                if (PracticeCore::getDuelHandler()->isWaitingForDuelToStart($rqName) or $requested->isInDuel()) {
                    $msg = self::str_replace(self::getMessage('duels.misc.in-duel'), ['%player%' => $rqName]);
                } else {
                    if ($requested->canSendDuelRequest())
                        $result = true;
                    else {
                        $sec = $requested->getCantDuelSpamSecs();
                        $msg = self::str_replace(self::getMessage('duels.misc.anti-spam'), ['%player%' => $rqName, '%time%' => "$sec"]);
                    }
                }
            }

            if (!is_null($msg)) $sender->sendMessage($msg);
        }
        return $result;
    }

    /**
     * @param string $haystack
     * @param array $values
     * @return string
     */
    public static function str_replace(string $haystack, array $values): string
    {
        $result = $haystack;

        $keys = array_keys($values);

        foreach ($keys as $value) {
            $value = strval($value);
            $replaced = strval($values[$value]);
            if (self::str_contains($value, $haystack)) {
                $result = str_replace($value, $replaced, $result);
            }
        }

        return $result;
    }

    /**
     * @param string $str
     * @return string
     */
    public static function getMessage(string $str): string
    {
        $cfg = PracticeCore::getInstance()->getMessageConfig();

        if (self::str_contains('.', $str)) {

            $arrSplit = explode('.', $str);

            $len = count($arrSplit);

            $val = $cfg->get($arrSplit[0]);

            for ($i = 1; $i < $len; $i++)
                $val = $val[$arrSplit[$i]];


            $obj = strval($val);

        } else $obj = strval($cfg->get($str));

        return $obj;
    }

    /**
     * @param Player $player
     * @param string $permission
     * @return bool
     */
    public static function canExecAcceptCommand(Player $player, string $permission): bool
    {
        return self::canExecDuelCommand($player, $permission, true);
    }

    /**
     * @param Player $player
     * @param string $permission
     * @param bool $isRequesting
     * @return bool
     */
    public static function canExecDuelCommand(Player $player, string $permission, bool $isRequesting = false): bool
    {
        $msg = null;
        $result = false;

        $playerHandler = PracticeCore::getPlayerHandler();

        if ($playerHandler->isPlayerOnline($player)) {

            $p = $playerHandler->getPlayer($player);

            if (self::testPermission($player, $permission)) {

                if ($p->canUseCommands(true)) {

                    if (!$p->isInArena()) {

                        $duelHandler = PracticeCore::getDuelHandler();

                        if (!$p->isInDuel() and !$duelHandler->isWaitingForDuelToStart($p)) {

                            $exec = false;

                            if ($isRequesting) $exec = true;

                            else {
                                if (!$duelHandler->isPlayerInQueue($p))
                                    $exec = true;
                                else $msg = self::getMessage('duels.misc.fail-queue');
                            }

                            $result = $exec;

                            if ($result === true and self::isInSpectatorMode($p->getPlayer())) {
                                $result = false;
                                $msg = self::getMessage('spectator-mode-message');
                            }

                        } else $msg = self::getMessage('duels.misc.fail-match');

                    } else $msg = self::getMessage('duels.misc.fail-arena');

                }
            }
        }

        if (!is_null($msg)) $player->sendMessage($msg);
        return $result;
    }

    /**
     * @param CommandSender $sender
     * @param string $permission
     * @param bool $sendMsg
     * @return bool
     */
    public static function testPermission(CommandSender $sender, string $permission, bool $sendMsg = true): bool
    {
        $msg = null;

        $result = true;

        if ($sender instanceof Player) {
            $result = PracticeCore::getPermissionHandler()->testPermission($permission, $sender);
            if ($result === false and $sendMsg === true) $msg = self::getMessage("permission-msg");
        }

        if (!is_null($msg)) $sender->sendMessage($msg);

        return $result;
    }

    /**
     * @param Player $player
     * @param string $permission
     * @return bool
     */
    public static function canExecSpecCommand(Player $player, string $permission): bool
    {
        $msg = null;
        $result = false;

        $playerHandler = PracticeCore::getPlayerHandler();

        if ($playerHandler->isPlayerOnline($player)) {

            $p = $playerHandler->getPlayer($player);

            if (self::testPermission($player, $permission)) {

                if ($p->canUseCommands(true)) {

                    if (!$p->isInArena()) {

                        if (!$p->isInDuel() and !PracticeCore::getDuelHandler()->isWaitingForDuelToStart($p)) {

                            $result = true;

                            if ($result === true and self::isInSpectatorMode($p->getPlayer())) {
                                $result = false;
                                $msg = self::getMessage('spectator-mode-message');
                            }

                        } else $msg = self::getMessage('duels.misc.fail-match');

                    } else $msg = self::getMessage('duels.misc.fail-arena');
                }
            }
        }

        if (!is_null($msg)) $player->sendMessage($msg);

        return $result;
    }

    /**
     * @param CommandSender $sender
     * @param bool $consoleRunCommand
     * @param bool $canRunInSpec
     * @return bool
     */
    public static function canExecBasicCommand(CommandSender $sender, bool $consoleRunCommand = true, bool $canRunInSpec = false): bool
    {
        $msg = null;

        $playerHandler = PracticeCore::getPlayerHandler();

        if ($sender instanceof Player) {
            $pl = $sender;
            if ($playerHandler->isPlayer($pl)) {
                $p = $playerHandler->getPlayer($pl);
                $exec = true;
                if ($p->canUseCommands(true)) {
                    if (self::isInSpectatorMode($pl)) {
                        $exec = $canRunInSpec;
                        if ($canRunInSpec === false) $msg = self::getMessage('spectator-mode-msg');
                    }

                }
            } else $exec = false;

        } else {
            $exec = $consoleRunCommand;

            if ($exec === false) $msg = self::getMessage('console-usage-command');
        }

        if (!is_null($msg)) $sender->sendMessage($msg);

        return $exec;
    }

    /**
     * @param CommandSender $sender
     * @param string $command
     * @return bool
     */
    public static function canExecutePartyCmd(CommandSender $sender, string $command = 'help'): bool
    {
        $result = false;

        $msg = null;

        $playerHandler = PracticeCore::getPlayerHandler();

        $name = $sender->getName();

        if ($sender instanceof Player and $playerHandler->isPlayerOnline($name)) {
            $p = $playerHandler->getPlayer($name);
            if ($p->canUseCommands(true)) {
                if ($p->isInArena()) $msg = self::getMessage("party.general.fail-lobby");
                else {
                    if (!$p->isInParty()) {
                        $invalidCmds = ['invite' => true, 'kick' => true, 'leave' => true];
                        if (isset($invalidCmds[$command])) $msg = self::getMessage('party.general.fail.no-party');
                        else $result = true;
                    } else {
                        $name = $p->getPlayerName();

                        if (PracticeCore::getPartyManager()->isLeaderOFAParty($name)) {
                            $result = $command !== 'create';
                            if ($result === false)
                                $msg = self::getMessage('party.create.fail-leader');

                        } else {

                            $invalidCmds = ['create' => true, 'invite' => true, 'kick' => true, 'open' => true, 'close' => true, 'accept' => true, 'join' => true];

                            if (isset($invalidCmds[$command]))
                                $msg = ($command === 'create') ? self::getMessage('party.create.fail-leave') : (($command === 'accept' or $command === 'join') ? self::getMessage('party.accept.in-party') : self::getMessage('party.general.fail-manager'));
                            else $result = true;
                        }
                    }
                }
            }
        } else $msg = self::getMessage('console-usage-command');

        if (!is_null($msg)) $sender->sendMessage($msg);

        return $result;
    }

    /**
     * @return void
     */
    public static function transferEveryone(): void
    {
        $server = Server::getInstance();

        $players = $server->getOnlinePlayers();

        $serverIP = Internet::getIP();

        $serverPort = $server->getPort();

        foreach ($players as $player) $player->transfer($serverIP, $serverPort, "Reloading Server");
    }

    /**
     * @param Player|PracticePlayer $player
     * @param Position|null $pos
     */
    public static function teleportPlayer(PracticePlayer|Player $player, Position $pos = null): void
    {
        $p = (($player instanceof PracticePlayer) ? $player->getPlayer() : $player);

        if ($p !== null and $p instanceof Player) {

            $pos = ($pos !== null ? $pos : self::getSpawnPosition());

            $x = $pos->x;
            $z = $pos->z;

            self::onChunkGenerated($pos->world, intval($x) >> 4, intval($z) >> 4, function () use ($p, $pos) {
                $p->teleport($pos);
            });
        }
    }

    /**
     * @return Position
     */
    public static function getSpawnPosition(): Position
    {
        $lvl = self::getDefaultWorld();
        if (is_null($lvl)) $lvl = Server::getInstance()->getWorldManager()->getDefaultWorld();
        $spawnPos = $lvl->getSpawnLocation();
        if (is_null($spawnPos)) $spawnPos = $lvl->getSafeSpawn();
        return $spawnPos;
    }

    //TEXT/MESSAGE FUNCTIONS

    /**
     * @param World $world
     * @param int $x
     * @param int $z
     * @param callable $callable
     * @return void
     */
    public static function onChunkGenerated(World $world, int $x, int $z, callable $callable): void
    {
        if ($world->isChunkPopulated($x, $z)) {
            ($callable)();
            return;
        }
        $world->registerChunkLoader(new PracticeChunkLoader($world, $x, $z, $callable), $x, $z, true);
    }

    /**
     * @param PracticePlayer $player
     * @param bool $clearInv
     * @param bool $reset
     * @return void
     */
    public static function respawnPlayer(PracticePlayer $player, bool $clearInv = false, bool $reset = false): void
    {
        $p = $player->getPlayer();

        if ($reset === true) self::resetPlayer($p, $clearInv, false);
        else {
            if ($player->isOnline()) {
                if (self::isFrozen($p)) self::setFrozen($p, false);
                if (self::isInSpectatorMode($player)) {
                    self::setInSpectatorMode($player, false);
                } else {
                    if (self::canFly($p)) {
                        self::setCanFly($p, false);
                    }

                    $player->setCanHitPlayer(false);
                    if ($player->isInvisible()) $player->setInvisible(false);
                }

                PracticeCore::getItemHandler()->spawnHubItems($player, $clearInv);

                ScoreboardUtil::updateSpawnScoreboards($player);
            }
        }
    }

    /**
     * @param Player $player
     * @param bool $clearInv
     * @param bool $teleport
     * @param bool $disablePlugin
     * @return void
     */
    public static function resetPlayer(Player $player, bool $clearInv = true, bool $teleport = true, bool $disablePlugin = false): void
    {
        $playerHandler = PracticeCore::getPlayerHandler();

        if (!is_null($player) and $player->isOnline()) {
            if ($player->getGamemode()->id() !== GameMode::SURVIVAL) $player->setGamemode(\pocketmine\player\GameMode::SURVIVAL());

            $player->getEffects()->clear();

            if ($player->getHealth() != $player->getMaxHealth()) $player->setHealth($player->getMaxHealth());

            if ($teleport === true) {
                $pos = self::getSpawnPosition();
                if ($disablePlugin === true) {
                    $player->teleport($pos);
                } else {
                    $x = $pos->x;
                    $z = $pos->z;

                    self::onChunkGenerated($pos->world, intval($x) >> 4, intval($z) >> 4, function () use ($player, $pos) {
                        $player->teleport($pos);
                    });
                }
            }

            if ($player->isOnFire()) $player->extinguish();
            if (self::isFrozen($player)) self::setFrozen($player, false);

            if (self::isInSpectatorMode($player)) {
                self::setInSpectatorMode($player, false);
            } else {
                if (self::canFly($player)) {
                    self::setCanFly($player, false);
                }

                if ($playerHandler->isPlayerOnline($player)) {
                    $p = $playerHandler->getPlayer($player);
                    $p->setCanHitPlayer(false);
                    if ($p->isInvisible()) {
                        $p->setInvisible(false);
                    }
                }
            }

            if ($playerHandler->isPlayerOnline($player)) {
                $p = $playerHandler->getPlayer($player);

                if ($p->isInArena()) $p->setCurrentArena(PracticeArena::NO_ARENA);

                if (!$p->canThrowPearl()) $p->setThrowPearl(true);
                if ($p->isInCombat()) $p->setInCombat(false);

                $p->setSpawnScoreboard();
            }

            PracticeCore::getItemHandler()->spawnHubItems($player, $clearInv);
        }
    }

    /**
     * @param Player $player
     * @param bool $freeze
     * @param bool $forDuels
     * @return void
     */
    public static function setFrozen(Player $player, bool $freeze, bool $forDuels = false): void
    {
        if (!is_null($player) and $player->isOnline()) {
            $player->setImmobile($freeze);
            if ($forDuels === false) {
                $msg = ($freeze === true) ? self::getMessage('frozen.active') : self::getMessage('frozen.inactive');
                $player->sendMessage($msg);
            }
        }
    }

    /**
     * @param $player
     * @param bool $spec
     * @param bool $forDuels
     * @return void
     */
    public static function setInSpectatorMode($player, bool $spec = true, bool $forDuels = false): void
    {
        $playerHandler = PracticeCore::getPlayerHandler();

        if ($playerHandler->isPlayer($player)) {
            $p = $playerHandler->getPlayer($player);
            if ($spec === true) {
                if (!$forDuels) {
                    $p->getPlayer()->setGamemode(3);
                } else {
                    $p->setCanHitPlayer(false);
                    $p->setInvisible(true);
                    self::setCanFly($player, true);
                }
            } else {
                $p->getPlayer()->setGamemode(0);
                $p->setCanHitPlayer(true);
                $p->setInvisible(false);
                self::setCanFly($player, false);
            }
        }
    }

    /**
     * @param $player
     * @param bool $res
     * @return void
     */
    public static function setCanFly($player, bool $res): void
    {
        $pl = null;
        if (isset($player) and !is_null($player)) {
            if ($player instanceof Player)
                $pl = $player;
            elseif ($player instanceof PracticePlayer)

                if ($player->isOnline())
                    $pl = $player->getPlayer();

                else if (is_string($player))
                    $pl = Server::getInstance()->getPlayerExact($player);
        }

        if (!is_null($pl)) {
            $pl->setAllowFlight($res);

            if ($res === false and $pl->isFlying()) {
                $pl->setFlying(false);
            }
        }
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function isPlayer(int $id): bool
    {
        return !is_null(self::getPlayerByID($id));
    }

    /**
     * @param int $id
     * @return Player|null
     */
    public static function getPlayerByID(int $id): ?Player
    {
        $result = null;

        $online = Server::getInstance()->getOnlinePlayers();

        foreach ($online as $player) {
            $theID = $player->getId();
            if ($id === $theID) {
                $result = $player;
                break;
            }
        }
        return $result;
    }

    /**
     * @param $player
     * @return void
     */
    public static function kill($player): void
    {
        $playerHandler = PracticeCore::getPlayerHandler();

        if ($playerHandler->isPlayer($player)) {
            $p = $playerHandler->getPlayer($player);

            $pl = $p->getPlayer();

            if ($p->isOnline()) {
                $ev = $pl->getLastDamageCause();
                if ($ev === null) $ev = new EntityDamageEvent($pl, EntityDamageEvent::CAUSE_CUSTOM, 1000);

                $found = false;

                if ($ev instanceof EntityDamageByEntityEvent) {
                    $dmgr = $ev->getDamager();
                    if ($dmgr instanceof Player and $playerHandler->isPlayer($dmgr->getName())) {
                        $name = $dmgr->getName();
                        $attacker = PracticeCore::getPlayerHandler()->getPlayer($name);
                        $differenceSeconds = $attacker->getCurrentSec() - $attacker->getLastSecInCombat();
                        if ($differenceSeconds <= 20) $found = true;
                    }
                }

                if ($found === true) $ev = new EntityDamageEvent($pl, EntityDamageEvent::CAUSE_CUSTOM, 1000);

                $ev->call();
                $pl->setLastDamageCause($ev);
                $pl->setHealth(20);

            } else {
                if (!is_null($p)) {
                    $drops = $pl->getDrops();

                    $ev = new PlayerDeathEvent($pl, $drops, 0, "");
                    $ev->setKeepInventory(false);
                    $ev->call();
                    $level = $pl->getLevel();

                    foreach ($drops as $item) {
                        if ($item instanceof Item) $level->dropItem($pl, $item);
                    }
                }
            }
        }
    }

    /**
     * @param string $killer
     * @param string $killed
     * @return string|null
     */
    public static function getRandomDeathMsg(string $killer, string $killed): ?string
    {
        $arr = PracticeCore::getInstance()->getMessageConfig()->get('duel-death-messages');
        $count = count($arr);
        $msg = null;

        if ($count > 0) {
            $rand = rand(0, $count - 1);
            $msg = strval($arr[$rand]);
            $msg = self::str_replace($msg, ['%killer%' => $killer, '%killed%' => $killed]);
        }

        return $msg;
    }

    /**
     * @param string $msg
     * @return void
     */
    public static function broadcastMsg(string $msg): void
    {
        $server = Server::getInstance();

        $players = $server->getOnlinePlayers();

        foreach ($players as $player) $player->sendMessage($msg);

        $server->getLogger()->info($msg);
    }

    /**
     * @param string $rank
     * @return string
     */
    public static function getRankFormatOf(string $rank): string
    {
        $cfg = PracticeCore::getInstance()->getRankConfig();
        $obj = $cfg->get('format');
        return strval($obj[$rank]['rank']);
    }

    // LEVEL/POSITION FUNCTIONS

    /**
     * @param string $msg
     * @return string
     */
    public static function getUnfilteredChat(string $msg): string
    {
        return PracticeCore::getChatHandler()->getUncensoredMessage($msg);
    }

    /**
     * @param Player $player
     * @param string $msg
     * @return string
     */
    public static function getChatFormat(Player $player, string $msg): string
    {
        $name = self::getNameForChat($player);
        $formatted = PracticeCore::getRankHandler()->getFormattedRanksOf($player->getName());
        $messageFormat = strval(PracticeCore::getInstance()->getRankConfig()->get('chat-format'));
        $uncolored = self::getUncoloredString($formatted);
        $len = strlen($uncolored);

        if ($len === 0) {
            $index = strpos($messageFormat, ']');
            if (is_int($index)) {
                $replaced = substr($messageFormat, 0, $index + 1);
                $messageFormat = str_replace($replaced, '', $messageFormat);
            }
        } else $messageFormat = str_replace('%formatted-ranks%', $formatted, $messageFormat);

        $messageFormat = str_replace('%player-name%', $name, $messageFormat);
        return str_replace('%msg%', $msg, $messageFormat);
    }

    /**
     * @param Player $player
     * @return string
     */
    public static function getNameForChat(Player $player): string
    {
        $cfg = PracticeCore::getInstance()->getRankConfig();

        $rankHandler = PracticeCore::getRankHandler();

        $ranks = ($rankHandler->hasRanks($player)) ? $rankHandler->getRanksOf($player) : [RankHandler::$GUEST];

        $firstRank = $ranks[0];

        $rank = ($firstRank instanceof Rank) ? $firstRank->getLocalizedName() : RankHandler::$GUEST->getLocalizedName();

        $obj = $cfg->get('format');
        $str = TextFormat::RESET . $obj[$rank]['p-name'];
        $str = str_replace('%player%', $player->getName(), $str);
        return $str . TextFormat::RESET;
    }

    /**
     * @param string $str
     * @return string
     */
    public static function getUncoloredString(string $str): string
    {
        return TextFormat::clean($str);
    }

    /**
     * @param Player $player
     * @return string
     */
    public static function getNameTagFormat(Player $player): string
    {
        $name = self::getNameForChat($player);
        $formattedRanks = PracticeCore::getRankHandler()->getFormattedRanksOf($player->getName());
        $tagFormat = strval(PracticeCore::getInstance()->getRankConfig()->get('nametag-format'));
        $uncolored = self::getUncoloredString($formattedRanks);
        $len = strlen($uncolored);

        if ($len === 0) {
            $index = strpos($tagFormat, ']');
            if (is_int($index)) {
                $replaced = substr($tagFormat, 0, $index + 1);
                $tagFormat = str_replace($replaced, '', $tagFormat);
            }
        } else $tagFormat = self::str_replace($tagFormat, ['%formatted-ranks%' => $formattedRanks]);

        $tagFormat = str_replace('%player-name%', $name, $tagFormat);

        $hp = intval($player->getHealth());

        return str_replace('%hp%', "$hp", $tagFormat);
    }

    /**
     * @param string $str
     * @return bool
     */
    public static function isLineSeparator(string $str): bool
    {
        $result = true;
        $uncolored = self::getUncoloredString($str);

        $len = strlen($uncolored);

        for ($i = 0; $i < $len; $i++) {
            $char = $uncolored[$i];
            if ($char !== '-') {
                $result = false;
                break;
            }
        }

        return $result;
    }

    /**
     * @param array $str
     * @param bool $visible
     * @return string
     */
    public static function getLineSeparator(array $str, bool $visible = true): string
    {
        $count = count($str);

        $len = 20;

        $keys = array_keys($str);

        if ($count > 0) {
            $greatest = self::getUncoloredString(strval($str[$keys[0]]));
            foreach ($keys as $key) {
                $current = self::getUncoloredString(strval($str[$key]));

                if (strlen($current) > strlen($greatest)) $greatest = $current;
            }

            $len = strlen($greatest);
        }

        if ($len > self::MOBILE_SEPARATOR_LEN) $len = self::MOBILE_SEPARATOR_LEN;

        $str = '';
        $count = 0;

        while ($count < $len) {
            $character = ($visible === true) ? '-' : ' ';
            $str .= $character;
            $count++;
        }

        return $str;
    }

    /**
     * @param Player $p
     * @return Location
     */
    public static function playerToLocation(Player $p): Location
    {
        return new Location($p->getPosition()->x, $p->getPosition()->y, $p->getPosition()->z, $p->getWorld(), $p->getLocation()->yaw, $p->getLocation()->pitch);
    }

    /**
     * @param World $level
     * @param bool $proj
     * @param bool $all
     * @return void
     */
    public static function clearEntitiesIn(World $level, bool $proj = false, bool $all = false): void
    {
        $entities = $level->getEntities();

        foreach ($entities as $entity) {
            $exec = true;

            if ($entity instanceof Player) $exec = false;
            elseif ($all === false and $entity instanceof FishingHook) $exec = false;
            elseif ($all === false and $entity instanceof \pocketmine\entity\projectile\EnderPearl) $exec = false;
            elseif ($all === false and $entity instanceof \pocketmine\entity\projectile\SplashPotion) $exec = false;
            elseif ($all === false and $entity instanceof Arrow) $exec = $proj;

            if ($exec === true) $entity->close();
        }
    }

    /**
     * @param $pos
     * @return mixed|Location|Vector3|Position
     */
    public static function roundPosition($pos): mixed
    {
        $result = $pos;

        if ($pos instanceof Position) {
            $result = new Position(intval(round($pos->x)), intval(round($pos->y)), intval(round($pos->z)), $pos->getWorld());
        } elseif ($pos instanceof Vector3) {
            $result = new Vector3(intval(round($pos->x)), intval(round($pos->y)), intval(round($pos->z)));
        } elseif ($pos instanceof Location) {
            $result = new Location(intval(round($pos->x)), intval(round($pos->y)), intval(round($pos->z)), $pos->getWorld(), intval(round($pos->yaw)), intval(round($pos->pitch)));
        }

        return $result;
    }

    /**
     * @param $pos
     * @return mixed|Location|Vector3|Position
     */
    public static function absPosition($pos): mixed
    {
        $result = $pos;
        if ($pos instanceof Position) {
            $result = new Position(intval($pos->x), intval($pos->y), intval($pos->z), $pos->getWorld());
        } elseif ($pos instanceof Vector3) {
            $result = new Vector3(intval($pos->x), intval($pos->y), intval($pos->z));
        } elseif ($pos instanceof Location) {
            $result = new Location(intval($pos->x), intval($pos->y), intval($pos->z), $pos->getWorld(), $pos->yaw, $pos->pitch);
        }
        return $result;
    }

    //BLOCK FUNCTIONS

    /**
     * @param string $world
     * @return void
     */
    public static function loadWorld(string $world): void
    {
        $server = Server::getInstance();
        if (!$server->getWorldManager()->isWorldLoaded($world) and !self::str_contains('.', $world))
            $server->getWorldManager()->loadWorld($world);
    }

    //SERVER FUNCTIONS

    /**
     * @param Position $pos
     * @return array
     */
    #[ArrayShape(['x' => "int", 'y' => "int", 'z' => "int", 'pitch' => "int", 'yaw' => "int"])] public static function getPositionToMap(Position $pos): array
    {
        $result = [
            'x' => intval(round($pos->x)),
            'y' => intval(round($pos->y)),
            'z' => intval(round($pos->z)),
        ];

        if ($pos instanceof Location) {
            $result['yaw'] = intval(round($pos->yaw));
            $result['pitch'] = intval(round($pos->pitch));
        }

        return $result;
    }

    /**
     * @param $posArr
     * @param $world
     * @return Location|Position|null
     */
    public static function getPositionFromMap($posArr, $world): Position|Location|null
    {
        $result = null;

        if (!is_null($posArr) and is_array($posArr) and self::arr_contains_keys($posArr, 'x', 'y', 'z')) {
            $x = floatval(intval($posArr['x']));
            $y = floatval(intval($posArr['y']));
            $z = floatval(intval($posArr['z']));

            if (self::isAWorld($world)) {

                $server = Server::getInstance();

                if (self::arr_contains_keys($posArr, 'yaw', 'pitch')) {
                    $yaw = floatval(intval($posArr['yaw']));
                    $pitch = floatval(intval($posArr['pitch']));
                    $result = new Location($x, $y, $z, $server->getWorldManager()->getWorldByName($world), $yaw, $pitch);
                } else
                    $result = new Position($x, $y, $z, $server->getWorldManager()->getWorldByName($world));

            }
        }

        return $result;
    }

    // USEFUL FUNCTIONS

    /**
     * @param string|World $world
     * @param bool $loaded
     * @return bool
     */
    public static function isAWorld(string|World $world, bool $loaded = true): bool
    {
        $server = Server::getInstance();

        $lvl = ($world instanceof World) ? $world : $server->getWorldManager()->getWorldByName($world);

        $result = false;

        if (is_string($world) and $loaded === false) {
            $levels = self::getWorldsFromFolder();

            if (in_array($world, $levels))
                $result = true;

        } elseif ($lvl instanceof World) {
            $name = $lvl->getDisplayName();
            if ($loaded === true)
                $result = $server->getWorldManager()->isWorldLoaded($name);
        }

        return $result;
    }

    /**
     * @param PracticeCore|null $core
     * @return array
     */
    public static function getWorldsFromFolder(PracticeCore $core = null): array
    {
        $core = ($core instanceof PracticeCore) ? $core : PracticeCore::getInstance();

        $index = self::str_indexOf("/plugin_data", $core->getDataFolder());

        $substr = substr($core->getDataFolder(), 0, $index);

        $worlds = $substr . "/worlds";

        if (!is_dir($worlds)) return [];

        return scandir($worlds);
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @param int $len
     * @return int
     */
    public static function str_indexOf(string $needle, string $haystack, int $len = 0): int
    {
        $result = -1;

        $indexes = self::str_indexes($needle, $haystack);

        $length = count($indexes);

        if ($length > 0) {
            $length = $length - 1;
            $indexOfArr = ($len > $length or max($len, 0));
            $result = $indexes[$indexOfArr];
        }

        return $result;
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @return array
     */
    public static function str_indexes(string $needle, string $haystack): array
    {
        $result = [];

        $end = strlen($needle);

        $len = 0;

        while (($len + $end) <= strlen($haystack)) {
            $substr = substr($haystack, $len, $end);
            if ($needle === $substr) $result[] = $len;
            $len++;
        }

        return $result;
    }

    /**
     * @param $block
     * @return bool
     */
    public static function isGravityBlock($block): bool
    {
        $result = false;
        if (is_int($block)) {
            $result = $block === BlockLegacyIds::SAND;
        } elseif ($block instanceof Block) {
            $result = $block->getId() === BlockLegacyIds::SAND or $block->getId() === BlockLegacyIds::GRAVEL;
        }
        return $result;
    }

    /**
     * @param string $msg
     * @return void
     */
    public static function kickAll(string $msg): void
    {
        $players = Server::getInstance()->getOnlinePlayers();

        foreach ($players as $player) $player->kick($msg);
    }

    /**
     * @return void
     */
    public static function reloadPlayers(): void
    {
        $players = Server::getInstance()->getOnlinePlayers();
        $playerSize = count($players);

        if ($playerSize > 0) {
            $playerHandler = PracticeCore::getPlayerHandler();
            foreach ($players as $p) {
                $playerHandler->addPlayer($p);
                self::resetPlayer($p);
            }
        }
    }

    /**
     * @param string $haystack
     * @param string ...$needles
     * @return bool
     */
    #[Pure] public static function str_contains_vals(string $haystack, string...$needles): bool
    {
        $result = true;
        $size = count($needles);

        if ($size > 0) {
            foreach ($needles as $needle) {
                if (!self::str_contains($needle, $haystack)) {
                    $result = false;
                    break;
                }
            }
        } else $result = false;


        return $result;
    }

    /**
     * @param array $arr
     * @param array $values
     * @return array
     */
    #[Pure] public static function arr_replace_values(array $arr, array $values): array
    {
        $valuesKeys = array_keys($values);
        foreach ($valuesKeys as $key) {
            $value = $values[$key];
            if (self::arr_contains_value($key, $arr)) {
                $keys = array_keys($arr);
                foreach ($keys as $editedArrKey) {
                    $origVal = $arr[$editedArrKey];
                    if ($origVal === $key) $arr[$editedArrKey] = $value;
                }
            }
        }

        return $arr;
    }

    /**
     * @param string $input
     * @param string ...$tests
     * @return bool
     */
    public static function equals_string(string $input, string...$tests): bool
    {
        $result = false;

        foreach ($tests as $test) {
            if ($test === $input) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * @param array $arr
     * @return array
     */
    public static function sort_array(array $arr): array
    {
        if (count($arr) === 1)
            return $arr;
        $middle = intval(count($arr) / 2);
        $left = array_slice($arr, 0, $middle, true);
        $right = array_slice($arr, $middle, null, true);
        $left = self::sort_array($left);
        $right = self::sort_array($right);
        return self::merge($left, $right);
    }

    /**
     * @param array $arr1
     * @param array $arr2
     * @return array
     */
    private static function merge(array $arr1, array $arr2): array
    {
        $result = [];

        while (count($arr1) > 0 and count($arr2) > 0) {
            $leftKey = array_keys($arr1)[0];
            $rightKey = array_keys($arr2)[0];
            $leftVal = $arr1[$leftKey];
            $rightVal = $arr2[$rightKey];
            if ($leftVal > $rightVal) {
                $result[$rightKey] = $rightVal;
                $arr2 = array_slice($arr2, 1, null, true);
            } else {
                $result[$leftKey] = $leftVal;
                $arr1 = array_slice($arr1, 1, null, true);
            }
        }

        while (count($arr1) > 0) {
            $leftKey = array_keys($arr1)[0];
            $leftVal = $arr1[$leftKey];
            $result[$leftKey] = $leftVal;
            $arr1 = array_slice($arr1, 1, null, true);
        }

        while (count($arr2) > 0) {
            $rightKey = array_keys($arr2)[0];
            $rightVal = $arr2[$rightKey];
            $result[$rightKey] = $rightVal;
            $arr2 = array_slice($arr2, 1, null, true);
        }

        return $result;
    }

    /**
     * @param $s
     * @param bool $isInteger
     * @return bool
     */
    #[Pure] public static function canParse($s, bool $isInteger): bool
    {
        if (is_string($s)) {
            $abc = 'ABCDEFGHIJKLMNOPQRZTUVWXYZ';
            $invalid = $abc . strtoupper($abc) . "!@#$%^&*()_+={}[]|:;\"',<>?/";

            if ($isInteger === true) $invalid = $invalid . '.';

            $strArr = str_split($invalid);

            $canParse = self::str_contains_from_arr($s, $strArr);

        } else $canParse = ($isInteger === true) ? is_int($s) : is_float($s);

        return $canParse;
    }

    /**
     * @param string $haystack
     * @param array $needles
     * @return bool
     */
    #[Pure] public static function str_contains_from_arr(string $haystack, array $needles): bool
    {
        $result = true;
        $size = count($needles);

        if ($size > 0) {
            foreach ($needles as $needle) {
                if (!self::str_contains($needle, $haystack)) {
                    $result = false;
                    break;
                }
            }
        } else $result = false;

        return $result;
    }
}