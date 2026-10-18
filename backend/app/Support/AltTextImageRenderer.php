<?php

namespace App\Support;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/** Renders Markdown images as their alt text instead of an <img> tag. */
final class AltTextImageRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (! $node instanceof Image) {
            throw new \InvalidArgumentException('Incompatible node type: '.get_debug_type($node));
        }

        return $childRenderer->renderNodes($node->children());
    }
}
