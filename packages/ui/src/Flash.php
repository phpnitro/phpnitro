<?php

/*
 * This file is part of the PhpNitro package.
 *
 * (c) Ronaldo AWADEME <awademeronaldoo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Engine;

/**
 * One-shot session message ("Ajouté au panier", "Erreur de connexion"...)
 * set from an onXxx() handler before a redirect, displayed once by
 * FlashMessage on the next page and cleared immediately after being read.
 */
final class Flash
{
    public static function set(string $message, string $type = 'success'): void
    {
        $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
    }

    /**
     * @return array{message: string, type: string}|null
     */
    public static function consume(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        return $flash;
    }
}
