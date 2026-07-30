# assets/

Images, polices, audio, et autres fichiers statiques de ton app. Référence-les depuis tes écrans natifs avec un chemin `/assets/...` :

```php
new RenderImage('/assets/logo.png', 64, 64);
```

Ce dossier est automatiquement copié dans `ui/public/assets/` par `phpx serve` et `phpx bundle:android` — tu n'as rien d'autre à faire.
