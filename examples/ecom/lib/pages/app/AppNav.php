<?php

namespace Engine\App;

use Engine\Icon;

final class AppNav
{
    /**
     * @return array<int, array{label: string, href: string, icon: string}>
     */
    public static function items(): array
    {
        return [
            ['label' => 'Accueil', 'href' => '/', 'icon' => Icon::home()],
            ['label' => 'Panier', 'href' => '/cart', 'icon' => Icon::cart()],
            ['label' => 'Compte', 'href' => '/account', 'icon' => Icon::user()],
        ];
    }
}
