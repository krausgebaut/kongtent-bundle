<?php

/*
 * This file is part of the kongtent bundle.
 *
 * (c) krausgebaut von Marcel Kraus <mail@krausgebaut.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Krausgebaut\KongtentBundle\Tests;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The bundle's templates under `@Kongtent`, without a container.
 */
final class Templates
{
    public static function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(self::directory(), 'Kongtent');

        return new Environment($loader);
    }

    public static function directory(): string
    {
        return \dirname(__DIR__).'/templates';
    }
}
