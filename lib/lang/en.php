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
];
