# Followersexpress Ultra Indexer Ultimate

Plugin WordPress pour automatiser l'indexation et la création de backlinks à grande échelle.

## Installation
1. Copier tout le dossier dans `wp-content/plugins/followersexpress-indexer`.
2. Activer le plugin depuis l'administration WordPress.
3. Éditer les fichiers de listes si nécessaire.

## Fonctionnalités
- Ping de plus de 300 moteurs de recherche.
- Soumission à plus de 200 annuaires et indexeurs.
- Création de backlinks sur plus de 200 plateformes dédiées.
- Génération et enrichissement automatique de `robots.txt`.
- Déclenchement par mots‑clés via WP‑Cron ou en temps réel.
- Ping et soumissions envoyés dans un ordre aléatoire avec rotation d'user‑agents (200 bots) pour contourner les filtres.

## Stack technique
- PHP 7+ / WordPress.
- WP‑Cron pour l'automatisation.
- Listes de services stockées dans des fichiers texte.

## Structure des dossiers
```
/ (racine du plugin)
├── seoautomatique.php   # cœur du plugin
├── moteur.php           # script CLI de ping
├── moteurlist.txt       # services de ping
├── directory_sites.txt  # annuaires
├── backlink_sites.txt   # plateformes de backlinks
└── robotindex.txt       # user‑agents additionnels
```

## Scripts de démarrage
- `php moteur.php https://votre-site.com` : ping manuel de tous les services.

## Outils recommandés
- Éditeur de texte pour personnaliser les fichiers de listes.
- cURL ou équivalent pour vérifier les pings.
- Proxy/VPN pour diversifier les adresses IP lors des campagnes massives.

## Déploiement
- Charger le plugin sur votre serveur WordPress.
- Activer dans l'interface d'administration.
- Vérifier les journaux générés dans le dossier `uploads`.

## Configuration des listes
Les fichiers `moteurlist.txt`, `directory_sites.txt` et `backlink_sites.txt` contiennent uniquement des services réels et sont préremplis avec 300 moteurs de recherche différents (sans doublons Google), 200 annuaires et 200 plateformes de backlinks couvrant de nombreux pays et langues. Ajoutez vos propres URL, une par ligne, sans exemples fictifs. Le fichier `robotindex.txt` regroupe désormais 200 user‑agents de robots d’indexation qui seront ajoutés à `robots.txt` pour multiplier les opportunités de crawl.
Ces user‑agents sont également utilisés pour faire varier l'`User-Agent` lors des pings afin de maximiser l'indexation.
