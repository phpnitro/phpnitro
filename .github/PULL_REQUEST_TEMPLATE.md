## Quoi et pourquoi

<!-- Ce que change cette PR, et pourquoi — pas juste "quoi", voir CONTRIBUTING.md#conventions
     sur les commentaires/messages de commit : le "pourquoi" est ce qui ne se lit pas dans le diff. -->

## Vérifié comment

- [ ] `vendor/bin/phpunit` passe
- [ ] `php -l` sur les fichiers modifiés
- [ ] Testé dans le navigateur (`bin/phpx serve`) si ça touche au rendu/JS
- [ ] **Si ça touche à l'Android natif** (`WebAppInterface.kt`, permissions, `AndroidManifest.xml`...) : vérifié sur un device réel, pas seulement compilé — précise le modèle du device et si `armeabi-v7a`/`arm64-v8a`

## Captures d'écran

<!-- Si ça touche au rendu visuel. -->
