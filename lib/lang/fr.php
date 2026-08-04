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

    'home.app_title' => 'Mon application',
    'home.connected_as' => 'Connecté : {user} — se déconnecter',
    'home.not_connected' => 'Non connecté — se connecter',
    'home.counter_label' => 'Compteur',
    'home.increment' => 'Incrémenter',
    'home.settings_title' => 'Réglages',
    'home.settings_subtitle' => 'Préférences réelles',
    'home.documents_title' => 'Documents',
    'home.documents_subtitle' => 'Étape 3/4 — checklist',
    'home.verification_title' => 'Vérification',
    'home.verification_subtitle' => 'Code OTP',
    'home.product_title' => 'Produit #42',
    'home.product_subtitle' => 'Route multi-paramètres réelle',
    'home.drawer_home' => 'Accueil',
    'home.drawer_device' => 'Device',
    'home.drawer_api' => 'API',
    'home.drawer_widgets' => 'Widgets',

    'forgot_password.title' => 'Mot de passe oublié',
    'forgot_password.hint' => "Entre ton nom d'utilisateur, on te donne un lien de réinitialisation.",
    'forgot_password.username_placeholder' => 'Utilisateur',
    'forgot_password.submit' => 'Envoyer le lien',
    'forgot_password.dev_mode_banner' => 'Mode démo (pas de mailer configuré) — lien : {link}',
    'forgot_password.have_code' => "J'ai mon code — ",
    'forgot_password.reset_link' => 'réinitialiser',

    'reset_password.title' => 'Nouveau mot de passe',
    'reset_password.token_placeholder' => 'Code reçu',
    'reset_password.new_password_placeholder' => 'Nouveau mot de passe (6 caractères min.)',
    'reset_password.confirm_password_placeholder' => 'Confirmer le mot de passe',
    'reset_password.submit' => 'Réinitialiser',
    'reset_password.login' => 'Se connecter',
];
