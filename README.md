# PolitiMatch

Application web d'aide au vote destinée aux jeunes électeurs francophones belges. L'utilisateur répond à une série de questions sur des enjeux politiques belges actuels et découvre, à l'issue du quiz, quel parti politique correspond le mieux à ses propres positions, ainsi qu'un profil politique personnalisé.

## Contexte du projet

PolitiMatch est le travail de fin d'études (épreuve intégrée) de Randy Koke Mpuki, réalisé dans le cadre du bachelier en informatique, option développement d'applications. Le projet couvre l'ensemble du cycle de développement d'une application web, de l'élaboration du cahier des charges jusqu'à la mise en production.

Le contenu politique de l'application (formulation des questions, positionnement de chaque parti, fiches descriptives) a été rédigé et validé par un expert externe en sciences politiques. L'auteur du projet n'est intervenu que sur l'intégration technique de ce contenu, jamais sur son fond, afin de garantir la neutralité de l'outil.

## Fonctionnement

1. L'utilisateur répond à un questionnaire de 30 questions, réparties en 5 thématiques : Économie, Environnement, Social, Immigration et Société.
2. Chaque réponse est comparée à la position documentée de chaque parti sur la même question.
3. L'application calcule, pour chaque parti, un score de compatibilité fondé sur une distance de Manhattan pondérée entre les réponses de l'utilisateur et les positions du parti.
4. En parallèle, l'application calcule le positionnement de l'utilisateur sur deux axes idéologiques (économique et sociétal), à partir d'un sous-ensemble de questions classées par thème selon leur pertinence sur chaque axe.
5. Un profil politique textuel est généré automatiquement à partir des scores obtenus par thématique.

Le quiz peut être commencé sans création de compte (Guest Flow). Les réponses et le résultat sont conservés sous un identifiant de session anonyme, puis rattachés au compte de l'utilisateur s'il choisit de s'inscrire par la suite.

## Fonctionnalités

- Quiz de 30 questions avec échelle de réponse à 5 niveaux et possibilité de passer une question.
- Explication contextuelle disponible sous chaque question.
- Calcul du score de compatibilité avec les 6 partis politiques belges francophones couverts par l'application.
- Positionnement graphique sur les deux axes idéologiques, aux côtés des 6 partis.
- Génération d'un profil politique textuel personnalisé.
- Comparateur détaillé, question par question, entre les réponses de l'utilisateur et la position de chaque parti.
- Fiches descriptives des partis politiques.
- Partage du résultat par lien public ou par image téléchargeable.
- Création de compte classique ou connexion via Google, avec migration automatique d'une session invité existante.
- Tableau de bord personnel avec historique des quiz réalisés.
- Suppression, à la demande de l'utilisateur, d'un résultat ou de l'ensemble de son compte.

## Architecture technique

**Backend**
- PHP 8.4, Laravel 13
- API REST
- Authentification par Laravel Sanctum en mode SPA (cookie de session), avec connexion Google via Laravel Socialite
- Base de données PostgreSQL 16

**Frontend**
- Vue.js 3 (API de composition)
- Pinia pour la gestion d'état, Vue Router pour la navigation
- Application monopage (SPA), compilée par Vite
- Tailwind CSS pour les styles, Chart.js pour le graphique de positionnement idéologique

**Déploiement**
- Service unique sur Railway : le backend Laravel sert directement les fichiers statiques compilés du frontend Vue, plutôt que de déployer deux services distincts. Cette architecture élimine toute configuration CORS en production, le frontend et l'API étant servis depuis la même origine.

**Tâches planifiées**
- Un correctif automatique remet en échec, toutes les dix minutes, tout calcul de résultat resté bloqué anormalement longtemps.
- Une purge quotidienne supprime les sessions invitées expirées.

## Protection des données

- Le démarrage du quiz requiert un consentement explicite, dont l'octroi et la version sont enregistrés en base de données.
- Un utilisateur connecté peut supprimer définitivement un résultat de quiz ou son compte, ce qui entraîne la suppression de l'ensemble des données associées.
- Le contenu politique et les résultats des utilisateurs sont strictement dissociés du profil de l'expert ayant rédigé ce contenu.

## Structure du projet

```
app/
  Http/Controllers/     Contrôleurs de l'API (authentification, quiz, résultats, partis, comparateur, partage)
  Models/                Modèles Eloquent (User, QuizResult, Question, Party, PartyPosition, Answer, ...)
  Services/              Logique métier (calcul du matching, des axes idéologiques, du profil, migration de compte, ...)
database/
  migrations/            Historique du schéma de la base de données
  seeders/                Chargement du contenu politique validé par l'expert
resources/
  js/views/               Écrans de l'application Vue (accueil, quiz, résultats, comparateur, tableau de bord, ...)
  js/stores/              Stores Pinia (authentification, quiz, résultats)
routes/
  api.php                 Points d'entrée de l'API REST
  web.php                 Route de secours servant l'application monopage
tests/                    Tests PHPUnit (backend) et Vitest (frontend)
```

## Installation locale

**Prérequis**
- PHP 8.4 avec l'extension `gd`
- Composer
- Node.js et npm
- PostgreSQL 16

**Étapes**

```bash
git clone <url-du-depot>
cd PolitiMatch

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Renseigner dans `.env` les informations de connexion à la base de données PostgreSQL (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) ainsi que, si la connexion Google doit être testée, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` et `GOOGLE_REDIRECT_URI`.

```bash
php artisan migrate --seed
```

Pour le développement, avec rechargement automatique :

```bash
npm run dev
php artisan serve
```

Pour une version compilée, servie directement par Laravel :

```bash
npm run build
php artisan serve
```

## Tests

```bash
php artisan test
```

Exécute la suite de tests backend (PHPUnit), qui s'appuie sur une base PostgreSQL dédiée aux tests plutôt que sur SQLite, afin de vérifier réellement les contraintes propres à PostgreSQL utilisées dans le schéma.

```bash
npm run test
```

Exécute la suite de tests frontend (Vitest).

## Licence

Ce dépôt correspond à un travail académique réalisé dans le cadre d'une épreuve intégrée. Le contenu politique qu'il contient a été fourni par un expert externe et ne peut être réutilisé indépendamment de ce projet sans son accord.
