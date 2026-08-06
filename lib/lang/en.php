<?php

// Only needs to cover keys actually used by the screens wired to
// Translator::t() so far (NativeLoginScreen/NativeRegisterScreen) — see
// fr.php's own docblock for the fallback contract. Extend this file key
// by key as more screens get converted, same as fr.php grew.
return [
    'login.title' => 'Sign in',
    'login.demo_hint' => 'Demo account: demo / demo',
    'login.username' => 'Username',
    'login.password' => 'Password',
    'login.remember_me' => 'Remember me',
    'login.submit' => 'Sign in',
    'login.forgot_password' => 'Forgot your password?',
    'login.forgot_password_link' => 'Reset it',
    'login.no_account' => "Don't have an account?",
    'login.create_account' => 'Create one',
    'login.invalid_credentials' => 'Invalid credentials.',

    'register.title' => 'Create an account',
    'register.password_hint' => 'Password (6 characters min.)',
    'register.confirm_password' => 'Confirm password',
    'register.submit' => 'Create account',
    'register.has_account' => 'Already have an account?',
    'register.login_link' => 'Sign in',

    'home.app_title' => 'My application',
    'home.connected_as' => 'Signed in: {user} — sign out',
    'home.not_connected' => 'Not signed in — sign in',
    'home.counter_label' => 'Counter',
    'home.increment' => 'Increment',
    'home.settings_title' => 'Settings',
    'home.settings_subtitle' => 'Real preferences',
    'home.documents_title' => 'Documents',
    'home.documents_subtitle' => 'Step 3/4 — checklist',
    'home.verification_title' => 'Verification',
    'home.verification_subtitle' => 'OTP code',
    'home.product_title' => 'Product #42',
    'home.product_subtitle' => 'Real multi-param route',
    'home.drawer_home' => 'Home',
    'home.drawer_device' => 'Device',
    'home.drawer_api' => 'API',
    'home.drawer_widgets' => 'Widgets',

    'forgot_password.title' => 'Forgot password',
    'forgot_password.hint' => "Enter your username and we'll give you a reset link.",
    'forgot_password.username_placeholder' => 'Username',
    'forgot_password.submit' => 'Send link',
    'forgot_password.dev_mode_banner' => 'Demo mode (no mailer configured) — link: {link}',
    'forgot_password.have_code' => 'I have my code — ',
    'forgot_password.reset_link' => 'reset it',

    'reset_password.title' => 'New password',
    'reset_password.token_placeholder' => 'Code received',
    'reset_password.new_password_placeholder' => 'New password (6 characters min.)',
    'reset_password.confirm_password_placeholder' => 'Confirm password',
    'reset_password.submit' => 'Reset',
    'reset_password.login' => 'Sign in',
];
