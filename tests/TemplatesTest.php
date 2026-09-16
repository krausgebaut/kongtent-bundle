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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

final class TemplatesTest extends TestCase
{
    public function testEveryTemplateCompiles(): void
    {
        $twig = Templates::twig();
        $files = iterator_to_array((new Finder())->files()->in(Templates::directory())->name('*.twig'), false);

        self::assertNotSame([], $files);

        foreach ($files as $file) {
            $twig->load('@Kongtent/'.$file->getRelativePathname());
        }
    }
}
