# Roadmap 2.0 - Système de Gestion des Heures Supplémentaires

## 1. Structure de la Base de Données

### A. Tables Principales

#### users
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    role ENUM('admin', 'user', 'manager'),
    actif BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);
```

#### configuration
```sql
CREATE TABLE configuration (
    id INT PRIMARY KEY AUTO_INCREMENT,
    date_debut_validite DATE NOT NULL,
    date_fin_validite DATE,
    heures_contractuelles DECIMAL(5,2) NOT NULL,
    seuil_majoration_n1 DECIMAL(5,2) NOT NULL,
    seuil_majoration_n2 DECIMAL(5,2) NOT NULL,
    taux_majoration_n1 DECIMAL(3,2) NOT NULL,
    taux_majoration_n2 DECIMAL(3,2) NOT NULL,
    libelle_majoration_n1 VARCHAR(50) NOT NULL,
    libelle_majoration_n2 VARCHAR(50) NOT NULL,
    actif BOOLEAN DEFAULT true,
    commentaire TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

#### declarations
```sql
CREATE TABLE declarations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    client_id INT,
    date_jour DATE,
    semaine INT GENERATED ALWAYS AS (WEEK(date_jour, 3)) STORED,
    annee INT GENERATED ALWAYS AS (YEAR(date_jour)) STORED,
    heure_debut TIME,
    heure_fin TIME,
    temps_pause DECIMAL(4,2) DEFAULT 0.00,
    duree_calculee DECIMAL(5,2) GENERATED ALWAYS AS (
        TIMESTAMPDIFF(MINUTE, heure_debut, heure_fin)/60 - temps_pause
    ) STORED,
    type_temps ENUM('heure_supplementaire', 'recuperation'),
    motif ENUM('surcharge', 'urgence', 'autre'),
    statut ENUM('en_attente', 'validé', 'refusé'),
    commentaire TEXT,
    validateur_id INT,
    date_validation TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (validateur_id) REFERENCES users(id),
    FOREIGN KEY (client_id) REFERENCES clients(id),
    INDEX idx_semaine_annee (semaine, annee)
);
```

#### cumuls_semaine
```sql
CREATE TABLE cumuls_semaine (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    semaine INT,
    annee INT,
    configuration_id INT,
    -- Configuration appliquée (historisation)
    config_heures_contractuelles DECIMAL(5,2) NOT NULL,
    config_seuil_maj_n1 DECIMAL(5,2) NOT NULL,
    config_seuil_maj_n2 DECIMAL(5,2) NOT NULL,
    config_taux_maj_n1 DECIMAL(3,2) NOT NULL,
    config_taux_maj_n2 DECIMAL(3,2) NOT NULL,
    config_libelle_maj_n1 VARCHAR(50) NOT NULL,
    config_libelle_maj_n2 VARCHAR(50) NOT NULL,
    -- Heures calculées
    total_heures_semaine DECIMAL(5,2) DEFAULT 0.00,
    heures_normales DECIMAL(5,2) DEFAULT 0.00,
    heures_sup_maj_n1 DECIMAL(5,2) DEFAULT 0.00,
    heures_sup_maj_n2 DECIMAL(5,2) DEFAULT 0.00,
    heures_recup DECIMAL(5,2) DEFAULT 0.00,
    -- Montants calculés
    montant_maj_n1 DECIMAL(8,2) DEFAULT 0.00,
    montant_maj_n2 DECIMAL(8,2) DEFAULT 0.00,
    -- Statut et dates
    statut ENUM('en_cours', 'calculé', 'recalculé') DEFAULT 'en_cours',
    date_calcul TIMESTAMP NULL,
    date_recalcul TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (configuration_id) REFERENCES configuration(id),
    UNIQUE KEY unique_semaine_user (user_id, semaine, annee)
);
```

#### cumuls_utilisateur
```sql
CREATE TABLE cumuls_utilisateur (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    total_heures_sup DECIMAL(6,2) DEFAULT 0.00,
    total_heures_maj_n1 DECIMAL(6,2) DEFAULT 0.00,
    total_heures_maj_n2 DECIMAL(6,2) DEFAULT 0.00,
    total_heures_recup DECIMAL(6,2) DEFAULT 0.00,
    solde_actuel DECIMAL(6,2) GENERATED ALWAYS AS 
        (total_heures_sup - total_heures_recup) STORED,
    derniere_semaine_calculee INT,
    derniere_annee_calculee INT,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user (user_id)
);
```

### B. Tables d'Historisation

#### historique_calculs
```sql
CREATE TABLE historique_calculs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cumul_semaine_id INT,
    configuration_id INT,
    date_calcul TIMESTAMP NOT NULL,
    type_operation ENUM('calcul_initial', 'recalcul'),
    config_heures_contractuelles DECIMAL(5,2),
    config_seuil_maj_n1 DECIMAL(5,2),
    config_seuil_maj_n2 DECIMAL(5,2),
    config_taux_maj_n1 DECIMAL(3,2),
    config_taux_maj_n2 DECIMAL(3,2),
    total_heures_semaine DECIMAL(5,2),
    heures_sup_maj_n1 DECIMAL(5,2),
    heures_sup_maj_n2 DECIMAL(5,2),
    heures_recup DECIMAL(5,2),
    montant_maj_n1 DECIMAL(8,2),
    montant_maj_n2 DECIMAL(8,2),
    created_by INT,
    FOREIGN KEY (cumul_semaine_id) REFERENCES cumuls_semaine(id),
    FOREIGN KEY (configuration_id) REFERENCES configuration(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

## 2. Processus Métier

### A. Déclaration des Heures
1. Saisie de la déclaration
2. Validation automatique des données
3. Calcul automatique de la durée
4. Attribution à la semaine correspondante

### B. Validation des Déclarations
1. Workflow de validation
2. Notification aux validateurs
3. Historisation des validations
4. Déclenchement des calculs

### C. Calcul des Heures
1. Calcul hebdomadaire automatique
2. Application des taux selon configuration
3. Historisation des calculs
4. Mise à jour des cumuls

### D. Gestion des Configurations
1. Gestion des périodes de validité
2. Non-chevauchement des configurations
3. Conservation historique des taux
4. Impact sur les calculs futurs

## 3. Points Clés de l'Architecture

### A. Sécurité
1. Validation des données
2. Gestion des droits d'accès
3. Historisation des modifications
4. Protection contre les modifications non autorisées

### B. Performance
1. Index optimisés
2. Calculs en arrière-plan
3. Mise en cache des données fréquentes
4. Pagination des résultats

### C. Maintenabilité
1. Structure modulaire
2. Documentation complète
3. Tests automatisés
4. Logs détaillés

## 4. Fonctionnalités Principales

### A. Interface Utilisateur
1. Saisie des déclarations
2. Visualisation des cumuls
3. Historique des modifications
4. Export des données

### B. Administration
1. Gestion des configurations
2. Suivi des validations
3. Gestion des utilisateurs
4. Paramétrage système

### C. Reporting
1. Tableaux de bord
2. États récapitulatifs
3. Exports personnalisés
4. Alertes et notifications

## 5. Points d'Attention

### A. Gestion des Changements de Configuration
1. Conservation des anciens taux
2. Application correcte selon les dates
3. Recalcul possible avec historique
4. Traçabilité des modifications

### B. Calculs Hebdomadaires
1. Automatisation des calculs
2. Gestion des cas particuliers
3. Validation des résultats
4. Historisation des données

### C. Cumuls et Soldes
1. Mise à jour en temps réel
2. Vérification de cohérence
3. Gestion des corrections
4. Exports et reporting

## 6. Évolutions Futures

### A. Améliorations Possibles
1. Interface mobile
2. API REST
3. Intégration avec d'autres systèmes
4. Automatisation avancée

### B. Maintenance
1. Mise à jour des dépendances
2. Optimisation des performances
3. Amélioration de la sécurité
4. Support utilisateur

## 7. Services et Classes Principales

### A. DeclarationService
```php
class DeclarationService {
    public function creerDeclaration(array $data): int
    public function validerDeclaration(int $declaration_id, int $validateur_id): bool
    public function refuserDeclaration(int $declaration_id, int $validateur_id, string $motif): bool
    public function getDeclarationsSemaine(int $user_id, int $semaine, int $annee): array
    public function modifierDeclaration(int $declaration_id, array $data): bool
    public function supprimerDeclaration(int $declaration_id): bool
}
```

### B. CalculService
```php
class CalculService {
    public function calculerSemaine(int $user_id, int $semaine, int $annee): bool
    private function calculerTotauxSemaine(array $declarations, array $config): array
    private function historiserCumul(array $cumulExistant): void
    private function mettreAJourCumulUtilisateur(int $user_id): void
    public function recalculerPeriode(int $user_id, string $date_debut, string $date_fin): bool
    public function verifierCoherenceCumuls(int $user_id): array
}
```

### C. ConfigurationService
```php
class ConfigurationService {
    public function creerConfiguration(array $data): int
    public function getConfigurationPourDate(string $date): ?array
    public function verifierChevauchement(string $date_debut, string $date_fin): bool
    public function desactiverConfiguration(int $configuration_id): bool
    public function historiserModification(int $configuration_id, array $old_data, array $new_data): void
}
```

### D. CumulService
```php
class CumulService {
    public function getCumulUtilisateur(int $user_id): array
    public function getCumulsSemaine(int $user_id, int $annee): array
    public function recalculerCumulUtilisateur(int $user_id): bool
    public function exporterCumuls(array $params): string
    public function verifierAnomalies(): array
}
```

### E. ValidationService
```php
class ValidationService {
    public function getDeclarationsAValider(int $validateur_id): array
    public function validerDeclarations(array $declaration_ids, int $validateur_id): bool
    public function notifierValidateur(int $validateur_id, array $declarations): void
    public function getHistoriqueValidations(int $declaration_id): array
}
```

### F. ReportingService
```php
class ReportingService {
    public function genererTableauBord(array $params): array
    public function exporterDonnees(array $params, string $format): string
    public function genererEtatRecapitulatif(array $params): array
    public function genererAlertesUtilisateurs(): array
}
```

## 8. Workflows Automatisés

### A. Tâches Planifiées
1. Calcul automatique des cumuls hebdomadaires
2. Vérification des anomalies
3. Génération des rapports périodiques
4. Envoi des notifications

### B. Triggers Base de Données
1. Mise à jour des cumuls après validation
2. Historisation des modifications
3. Vérification des contraintes métier
4. Calcul des champs dérivés

### C. Événements Système
1. Notification des validateurs
2. Alerte sur dépassement de seuils
3. Synchronisation des données
4. Journal des actions système

## 9. Tests et Qualité

### A. Tests Unitaires
1. Services métier
2. Calculs et règles
3. Validation des données
4. Gestion des erreurs

### B. Tests d'Intégration
1. Workflow complet
2. Interactions entre services
3. Performance du système
4. Cohérence des données

### C. Tests de Non-Régression
1. Calculs historiques
2. Modifications de configuration
3. Migration des données
4. Mises à jour système

## 10. Logique de Calcul et Gestion des Heures

### A. Principes Fondamentaux
1. Toute déclaration est liée à une semaine spécifique
2. Les calculs sont toujours effectués au niveau hebdomadaire
3. Les cumuls sont mis à jour uniquement après validation
4. Chaque modification déclenche une historisation

### B. Classes de Calcul

#### DeclarationCalculator
```php
class DeclarationCalculator {
    public function calculerDureeDeclaration(Declaration $declaration): float {
        // 1. Calcul de la durée brute
        $duree_minutes = $this->calculerDureeMinutes(
            $declaration->heure_debut,
            $declaration->heure_fin
        );
        
        // 2. Soustraction de la pause
        $duree_finale = ($duree_minutes - ($declaration->temps_pause * 60)) / 60;
        
        // 3. Arrondi à 2 décimales
        return round($duree_finale, 2);
    }

    private function calculerDureeMinutes(string $debut, string $fin): int {
        $debut_dt = new DateTime($debut);
        $fin_dt = new DateTime($fin);
        return $fin_dt->diff($debut_dt)->h * 60 + $fin_dt->diff($debut_dt)->i;
    }
}
```

#### CalculHebdomadaire
```php
class CalculHebdomadaire {
    public function calculerSemaine(int $user_id, int $semaine, int $annee): array {
        // 1. Récupération des déclarations validées de la semaine
        $declarations = $this->getDeclarationsValidees($user_id, $semaine, $annee);
        
        // 2. Récupération de la configuration applicable
        $config = $this->getConfigurationApplicable($semaine, $annee);
        
        // 3. Initialisation des compteurs
        $totaux = [
            'total_heures' => 0,
            'heures_normales' => 0,
            'heures_maj_n1' => 0,    // Entre heures contractuelles et seuil N1
            'heures_maj_n2' => 0,    // Au-dessus du seuil N2
            'heures_recup' => 0,
            'montant_maj_n1' => 0,
            'montant_maj_n2' => 0
        ];

        // 4. Cumul des heures de la semaine
        foreach ($declarations as $declaration) {
            if ($declaration->type_temps === 'recuperation') {
                $totaux['heures_recup'] += $declaration->duree_calculee;
                continue;
            }
            $totaux['total_heures'] += $declaration->duree_calculee;
        }

        // 5. Application des seuils et calcul des majorations
        return $this->appliquerSeuils($totaux, $config);
    }

    private function appliquerSeuils(array $totaux, array $config): array {
        // Exemple de configuration :
        // heures_contractuelles = 35.00
        // seuil_majoration_n1 = 39.00 (au-delà des heures contractuelles)
        // seuil_majoration_n2 = 43.00
        // taux_majoration_n1 = 1.25 (25%)
        // taux_majoration_n2 = 1.50 (50%)

        // A. Si total inférieur aux heures contractuelles
        if ($totaux['total_heures'] <= $config['heures_contractuelles']) {
            $totaux['heures_normales'] = $totaux['total_heures'];
            return $totaux;
        }

        // B. Heures normales = heures contractuelles
        $totaux['heures_normales'] = $config['heures_contractuelles'];
        $heures_restantes = $totaux['total_heures'] - $config['heures_contractuelles'];

        // C. Calcul majoration N1 (entre heures contractuelles et seuil N1)
        if ($heures_restantes > 0) {
            $plage_n1 = $config['seuil_majoration_n2'] - $config['heures_contractuelles'];
            $heures_maj_n1 = min($heures_restantes, $plage_n1);
            
            $totaux['heures_maj_n1'] = $heures_maj_n1;
            $totaux['montant_maj_n1'] = $heures_maj_n1 * ($config['taux_majoration_n1'] - 1);
            
            $heures_restantes -= $heures_maj_n1;
        }

        // D. Calcul majoration N2 (au-dessus du seuil N2)
        if ($heures_restantes > 0) {
            $totaux['heures_maj_n2'] = $heures_restantes;
            $totaux['montant_maj_n2'] = $heures_restantes * ($config['taux_majoration_n2'] - 1);
        }

        // E. Calcul des totaux finaux
        $totaux['total_majoration'] = $totaux['montant_maj_n1'] + $totaux['montant_maj_n2'];
        
        return $totaux;
    }

    /**
     * Exemple de résultat pour une semaine de 45h avec configuration standard :
     * - heures_normales = 35h
     * - heures_maj_n1 = 4h (de 35 à 39h) -> majoration 25%
     * - heures_maj_n2 = 6h (au-dessus de 43h) -> majoration 50%
     * - montant_maj_n1 = 4 * 0.25 = 1h
     * - montant_maj_n2 = 6 * 0.50 = 3h
     * Total majorations = 4h
     */
}
```

### C. Gestion des Cumuls et Vérifications

#### GestionCumuls
```php
class GestionCumuls {
    public function mettreAJourCumuls(int $user_id, array $totaux_semaine): void {
        $this->db->beginTransaction();
        try {
            $this->sauvegarderCumulSemaine($user_id, $totaux_semaine);
            $this->recalculerCumulUtilisateur($user_id);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
```

#### ValidationService
```php
class ValidationService {
    public function validerDeclaration(Declaration $declaration): array {
        $erreurs = [];
        // Vérifications des horaires
        // Vérification du temps de pause
        // Vérification des chevauchements
        // Vérification des limites journalières
        return $erreurs;
    }
}
```

### D. Points de Contrôle Critiques

1. **Validation des Données**
   - Formats de dates et heures
   - Chevauchements
   - Limites légales
   - Cohérence des périodes

2. **Sécurisation des Calculs**
   - Utilisation de transactions
   - Gestion des erreurs
   - Historisation systématique
   - Vérification des résultats

3. **Gestion des Configurations**
   - Application des bons taux
   - Périodes de validité
   - Conservation historique
   - Traçabilité des changements

4. **Performance et Fiabilité**
   - Optimisation des requêtes
   - Gestion de la concurrence
   - Sauvegarde des données
   - Logs détaillés

### E. Processus de Vérification

1. **Contrôles Automatiques**
   - Vérification quotidienne des cumuls
   - Détection des anomalies
   - Alertes sur incohérences
   - Rapports d'erreurs

2. **Contrôles Manuels**
   - Interface de vérification
   - Outils de correction
   - Historique des modifications
   - Validation des corrections

# Thème et Interface Utilisateur

## Charte Graphique MSI

### Couleurs Principales
- **Vert MSI Foncé** : `#006B5F`
  - Utilisation : Couleur principale, en-têtes, boutons primaires
  - État normal des éléments interactifs
  
- **Vert MSI Clair** : `#008374`
  - Utilisation : États de survol (hover)
  - Éléments interactifs au survol

### Couleurs Secondaires
- **Bleu Clair** : `#00D1E8`
  - Utilisation : Actions secondaires, boutons d'historique
  - Éléments d'information

### Couleurs Fonctionnelles
- **Bleu HS** : `#0D6EFD`
  - Utilisation : Badges "HS" (Heures Supplémentaires)
  
- **Vert Récup** : `#198754`
  - Utilisation : Badges "Récup" (Récupération)

### Styles Communs
- **Cartes (Cards)**
  - Bordures arrondies : 15px
  - Ombre portée légère
  - En-tête :
    - Fond blanc
    - Texte en vert MSI foncé
    - Bordure inférieure en vert MSI foncé
  
- **Boutons**
  - Bordures arrondies : 10px
  - Animation de survol avec translation Y
  - Transition fluide des couleurs
  
- **Badges et Statuts**
  - Bordures arrondies : 20px
  - Taille de police réduite
  - Couleurs distinctives selon le type

### Composants Spécifiques
- **Barres de Progression**
  - Hauteur : 20px
  - Bordures arrondies : 10px
  - Couleur de fond : `#e9ecef`
  - Couleur de progression : Vert MSI Foncé

- **Tableaux**
  - En-têtes en vert MSI foncé
  - Lignes alternées pour meilleure lisibilité
  - Responsive avec scroll horizontal si nécessaire

### Variables CSS
```css
:root {
    --msi-primary: #006B5F;
    --msi-primary-hover: #008374;
    --msi-secondary: #00D1E8;
    --msi-hs: #0D6EFD;
    --msi-recup: #198754;
    --msi-border: 2px solid var(--msi-primary);
}
``` 
