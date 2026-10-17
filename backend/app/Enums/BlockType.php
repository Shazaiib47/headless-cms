<?php

namespace App\Enums;

enum BlockType: string
{
    case Hero = 'hero';
    case RichText = 'rich_text';
    case Image = 'image';
    case Cta = 'cta';
    case CardGrid = 'card_grid';
}
