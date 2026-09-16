<?php

$header = <<<'EOF'
    This file is part of the kongtent bundle.

    (c) krausgebaut von Marcel Kraus <mail@krausgebaut.de>

    For the full copyright and license information, please view the LICENSE
    file that was distributed with this source code.
    EOF;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'header_comment' => ['header' => $header],
    ])
    ->setFinder((new PhpCsFixer\Finder())->in([__DIR__.'/src', __DIR__.'/tests']));
