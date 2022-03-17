<?php

declare(strict_types=1);

namespace practice\game\items;

use practice\PracticeCore;
use practice\PracticeUtil;

class ItemTextures
{
    /** @var array */
    private array $textures;

    /**
     * @param PracticeCore $core
     */
    public function __construct(PracticeCore $core)
    {
        $path = $core->getResourcesFolder();
        $contents = file($path . "items.txt");

        $this->textures = [];

        foreach ($contents as $content) {
            $content = trim($content);
            $index = PracticeUtil::str_indexOf(': ', $content);
            $itemName = substr($content, 0, $index);
            $itemTexture = trim(substr($content, $index + 2));
            $png = PracticeUtil::str_indexOf('.png', $itemTexture);
            $itemTexture = trim(substr($itemTexture, 0, $png));
            $this->textures[$itemName] = $itemTexture;
        }
    }

    /**
     * @param string $item
     * @return string
     */
    public function getTexture(string $item): string
    {
        $result = "apple";
        if (isset($this->textures[$item]))
            $result = $this->textures[$item];
        return 'textures/items/' . $result;
    }
}