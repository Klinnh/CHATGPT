# Structure de la Base de Données HeuresSupp

## Tables Principales

### users
- `id` (INT, Primary Key, Auto Increment)
- `nom` (VARCHAR)
- `prenom` (VARCHAR)
- `email` (VARCHAR)
- `password` (VARCHAR)
- `role` (ENUM: 'admin', 'user')
- `actif` (BOOLEAN)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

### heures_supplementaires
- `id` (INT, Primary Key, Auto Increment)
- `user_id` (INT, Foreign Key → users.id)
- `client_id` (INT, Foreign Key → clients.id)
- `date_jour` (DATE)
- `heure_debut` (TIME)
- `heure_fin` (TIME)
- `duree_calculee` (DECIMAL(5,2))
- `type_temps` (ENUM: 'heure_supplementaire', 'recuperation')
- `commentaire` (TEXT)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

### clients
- `id` (INT, Primary Key, Auto Increment)
- `nom` (VARCHAR)
- `actif` (BOOLEAN)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

## Relations

1. heures_supplementaires → users
   - `user_id` référence `users.id`
   - Un utilisateur peut avoir plusieurs enregistrements d'heures supplémentaires
   - Suppression en cascade

2. heures_supplementaires → clients
   - `client_id` référence `clients.id`
   - Un client peut être associé à plusieurs enregistrements d'heures supplémentaires
   - Suppression en cascade

## Règles Métier

### Calcul des Majorations
1. Heures Supplémentaires :
   - Jusqu'à 4 heures : majoration de 25%
   - Au-delà de 4 heures : majoration de 50%

### Semaine de Travail
- Semaine standard : 39 heures
- Début de semaine : Lundi (WEEK(date, 3))
- Les heures sont calculées par semaine

### Types de Temps
- `heure_supplementaire` : Heures travaillées au-delà des 39h
- `recuperation` : Heures récupérées sur le cumul des heures supplémentaires

### Contraintes
1. Un utilisateur doit être actif pour enregistrer des heures
2. Les heures sont enregistrées par jour
3. La durée calculée est en format décimal (ex: 1h30 = 1.50)
4. Les dates et heures sont en UTC

## Indexes
1. heures_supplementaires :
   - Index sur `date_jour`
   - Index sur `user_id`
   - Index sur `type_temps`
   - Index composé sur (user_id, date_jour)

## Requêtes Principales

### Récupération des Heures par Semaine
```sql
SELECT 
    hs.id,
    hs.user_id,
    hs.date_jour,
    hs.type_temps,
    hs.duree_calculee,
    u.nom,
    u.prenom,
    WEEK(hs.date_jour, 3) as semaine,
    DATE_FORMAT(hs.date_jour, '%Y-%m-%d') as date_formatee,
    (
        SELECT MIN(date_jour) 
        FROM heures_supplementaires 
        WHERE WEEK(date_jour, 3) = WEEK(hs.date_jour, 3) 
        AND user_id = hs.user_id
        AND YEAR(date_jour) = YEAR(hs.date_jour)
    ) as debut_semaine,
    (
        SELECT MAX(date_jour) 
        FROM heures_supplementaires 
        WHERE WEEK(date_jour, 3) = WEEK(hs.date_jour, 3) 
        AND user_id = hs.user_id
        AND YEAR(date_jour) = YEAR(hs.date_jour)
    ) as fin_semaine
FROM heures_supplementaires hs
JOIN users u ON u.id = hs.user_id
WHERE u.actif = 1
AND hs.type_temps IN ('heure_supplementaire', 'recuperation')
```

### Vérification des Données
```sql
SELECT 
    hs.type_temps,
    COUNT(*) as total,
    MIN(hs.date_jour) as premiere_date,
    MAX(hs.date_jour) as derniere_date
FROM heures_supplementaires hs
WHERE 1=1
[conditions dynamiques selon les filtres]
GROUP BY hs.type_temps
``` 