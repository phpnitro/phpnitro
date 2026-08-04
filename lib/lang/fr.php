<?php

// Baseline locale — every key used anywhere in lib/pages/ should exist
// here first; other locale files (en.php, ...) only need to override
// the keys they actually translate, but fr.php is the one Translator::t()
// silently falls back to the raw key for if IT is missing an entry
// (there's no locale beneath fr.php to fall back to further).
return [
    'login.title' => 'Connexion',
    'login.demo_hint' => 'Compte de démo : demo / demo',
    'login.username' => 'Utilisateur',
    'login.password' => 'Mot de passe',
    'login.remember_me' => 'Se souvenir de moi',
    'login.submit' => 'Se connecter',
    'login.forgot_password' => 'Mot de passe oublié ?',
    'login.forgot_password_link' => 'Réinitialiser',
    'login.no_account' => 'Pas de compte ?',
    'login.create_account' => 'En créer un',
    'login.invalid_credentials' => 'Identifiants invalides.',

    'register.title' => 'Créer un compte',
    'register.password_hint' => 'Mot de passe (6 caractères min.)',
    'register.confirm_password' => 'Confirmer le mot de passe',
    'register.submit' => 'Créer le compte',
    'register.has_account' => 'Déjà un compte ?',
    'register.login_link' => 'Se connecter',
];
