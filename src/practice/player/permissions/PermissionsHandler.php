<?php

declare(strict_types=1);

namespace practice\player\permissions;

use JetBrains\PhpStorm\Pure;
use JsonException;
use pocketmine\permission\Permission;
use pocketmine\permission\PermissionManager;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use practice\player\PracticePlayer;
use practice\PracticeCore;
use practice\PracticeUtil;

class PermissionsHandler
{
    /** @var string */
    public const PERMISSION_PLACE_BREAK = 'practice.permission.build-break';

    /* @var Config */
    private Config $config;

    private string $configPath;

    /* @var string[]|array */
    private array $builderPerms;

    /* @var string[]|array */
    private array $contentCreatorPerms;

    /* @var string[]|array */
    private array $modPermissions;

    /* @var string[]|array */
    private array $adminPermissions;

    /* @var string[]|array */
    private array $ownerPermissions;

    /**
     * @param PracticeCore $core
     */
    #[Pure] public function __construct(PracticeCore $core)
    {
        $path = $core->getDataFolder();
        $this->configPath = $path . '/permissions.yml';
        $this->builderPerms = [];
        $this->contentCreatorPerms = [];
        $this->modPermissions = [];
        $this->adminPermissions = [];
        $this->ownerPermissions = [];
    }

    /**
     * @return void
     * @throws JsonException
     */
    public function initPermissions(): void
    {
        $this->initConfig();
    }

    /**
     * @return void
     * @throws JsonException
     */
    private function initConfig(): void
    {

        $array = [
            'content-creators' => [],
            'builders' => [],
            'mods' => [],
            'admins' => [],
            'owners' => []
        ];

        $permissionsManager = PermissionManager::getInstance();

        $perms = $permissionsManager->getPermissions();

        $ownerPerms = [];

        // TODO: Hi, I ain't updating this. Sorry lolz.
        foreach ($perms as $perm) {
            $defaultValue = $perm->getDefault();
            if ($defaultValue === Permission::DEFAULT_OP)
                $ownerPerms[] = $perm->getName();
        }

        $ownerPerms[] = self::PERMISSION_PLACE_BREAK;

        $array['owners'] = $ownerPerms;

        $this->config = new Config($this->configPath, Config::YAML, $array);
        $this->config->save();

        if ($this->config->exists('builders'))
            $this->builderPerms = $this->config->get('builders');

        if ($this->config->exists('content-creators'))
            $this->contentCreatorPerms = $this->config->get('content-creators');

        if ($this->config->exists('mods')) {
            $this->modPermissions = $this->config->get('mods');
            $this->modPermissions = array_merge($this->modPermissions, $this->contentCreatorPerms);
            $this->modPermissions = array_merge($this->modPermissions, $this->builderPerms);
        }

        if ($this->config->exists('admins')) {
            $this->adminPermissions = $this->config->get('admins');
            $this->adminPermissions = array_merge($this->adminPermissions, $this->modPermissions);
        }

        if ($this->config->exists('owners')) {
            $this->ownerPermissions = $this->config->get('owners');
            $this->ownerPermissions = array_merge($this->ownerPermissions, $this->adminPermissions);
        }
    }

    public function updatePermissions(PracticePlayer $p): void
    {

        $permissions = [];

        $name = $p->getPlayerName();

        $playerHandler = PracticeCore::getPlayerHandler();

        if ($playerHandler->isMod($name))
            $permissions = $this->getModPermissions();
        elseif ($playerHandler->isAdmin($name))
            $permissions = $this->getAdminPermissions();
        elseif ($playerHandler->isContentCreator($name))
            $permissions = $this->getCCPermissions();
        elseif ($playerHandler->isOwner($name))
            $permissions = $this->getOwnerPermissions();
        elseif ($playerHandler->isBuilder($name))
            $permissions = $this->getBuilderPermissions();

        $size = count($permissions);

        $player = $p->getPlayer();

        if ($size > 0 and $p->isOnline()) {

            foreach ($permissions as $perm) {

                if ($this->isPermission($perm)) {

                    $permission = $this->getPermission($perm);

                    $player->addAttachment(PracticeCore::getInstance(), $permission->getName(), true);
                }
            }

            $effectivePermissions = $player->getEffectivePermissions();

            $keys = array_keys($effectivePermissions);

            foreach ($keys as $key) {

                $perm = $effectivePermissions[$key];

                $permName = $perm->getPermission();

                $attachment = $perm->getAttachment();

                if (!PracticeUtil::arr_contains_value($permName, $permissions) and !is_null($attachment))
                    $player->removeAttachment($attachment);
            }
        }
    }

    /**
     * @return string[]
     */
    public function getModPermissions(): array
    {
        return $this->modPermissions;
    }

    /**
     * @return string[]
     */
    public function getAdminPermissions(): array
    {
        return $this->adminPermissions;
    }

    /**
     * @return string[]
     */
    public function getCCPermissions(): array
    {
        return $this->contentCreatorPerms;
    }

    /**
     * @return string[]
     */
    public function getOwnerPermissions(): array
    {
        return $this->ownerPermissions;
    }

    /**
     * @return string[]
     */
    public function getBuilderPermissions(): array
    {
        return $this->builderPerms;
    }

    /**
     * @param string $name
     * @return bool
     */
    private function isPermission(string $name): bool
    {
        return !is_null($this->getPermission($name));
    }

    /**
     * @param string $name
     * @return Permission|null
     */
    private function getPermission(string $name): ?Permission
    {
        $permManager = PermissionManager::getInstance();
        return $permManager->getPermission($name);
    }

    /**
     * @param string $permission
     * @param Player $player
     * @return bool
     */
    public function testPermission(string $permission, Player $player): bool
    {
        $result = false;

        $playerHandler = PracticeCore::getPlayerHandler();

        if ($playerHandler->isAdmin($player)) {
            $adminPerms = $this->getAdminPermissions();
            $result = PracticeUtil::arr_contains_value($permission, $adminPerms);
        } elseif ($playerHandler->isMod($player)) {
            $modPerms = $this->getModPermissions();
            $result = PracticeUtil::arr_contains_value($permission, $modPerms);
        } elseif ($playerHandler->isContentCreator($player)) {
            $ccPerms = $this->getCCPermissions();
            $result = PracticeUtil::arr_contains_value($permission, $ccPerms);
        } elseif ($playerHandler->isOwner($player)) {
            $ownerPerms = $this->getOwnerPermissions();
            $result = PracticeUtil::arr_contains_value($permission, $ownerPerms);
        } elseif ($playerHandler->isBuilder($player)) {
            $builderPerms = $this->getBuilderPermissions();
            $result = PracticeUtil::arr_contains_value($permission, $builderPerms);
        }

        if ($result === false) $result = $player->hasPermission($permission);
        return $result;
    }
}