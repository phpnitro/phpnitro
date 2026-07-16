# assets/

Images, polices, et autres fichiers statiques de ton app. Référence-les depuis tes widgets avec un chemin `/assets/...` :

```php
Image::make('/assets/logo.png');
```

Ce dossier est automatiquement copié dans `ui/public/assets/` par `phpx serve` et `phpx bundle:android` — tu n'as rien d'autre à faire.
