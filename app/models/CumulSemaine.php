<?php

class CumulSemaine {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function calculerCumulSemaine($user_id, $annee, $semaine) {
        $log_file = __DIR__ . '/log_cumul.txt';
        file_put_contents($log_file, "== Traitement $user_id / S$semaine / $annee ==\n", FILE_APPEND);
        try {
            // Récupération des déclarations validées
            $stmt = $this->db->prepare("
                SELECT * FROM heures_supplementaires 
                WHERE user_id = :user_id 
                AND statut = 'validé'
                AND WEEK(date_jour, 3) = :semaine 
                AND YEAR(date_jour) = :annee
            ");
            file_put_contents($log_file, "Exécution requête d'insertion SQL...\n", FILE_APPEND);
            $stmt->execute([
                ':user_id' => $user_id,
                ':semaine' => $semaine,
                ':annee' => $annee
            ]);
            $heures = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $nb_declarations = count($heures);
            if ($nb_declarations === 0) {
                file_put_contents($log_file, "Aucune déclaration validée trouvée.\n", FILE_APPEND);
                return;
            }

            // Chargement de la configuration active
            require_once __DIR__ . '/../admin/configuration.php';
            $config = getParametresTemps($this->db);
            

            $heures_contractuelles = (float)$config['heures_contractuelles'];
            $seuil = (float)$config['seuil_majoration_n2'];
            $taux_n1 = (float)$config['taux_majoration_n1'];
            $taux_n2 = (float)$config['taux_majoration_n2'];

            // Calcul brut
            $heures_sup = 0;
            $heures_recup = 0;
            foreach ($heures as $h) {
                if ($h['type_temps'] === 'supp') {
                    $heures_sup += (float)$h['duree_calculee'];
                } elseif ($h['type_temps'] === 'recup') {
                    $heures_recup += (float)$h['duree_calculee'];
                }
            }

            $heures_apres_recup = $heures_sup - $heures_recup;

            $heures_maj_n1 = 0;
            $heures_maj_n2 = 0;

            if ($heures_apres_recup <= 0) {
                $solde_semaine = $heures_apres_recup;
            } else {
                $total_hebdo = $heures_contractuelles + $heures_apres_recup;
                if ($total_hebdo <= $seuil) {
                    $heures_maj_n1 = $heures_apres_recup;
                } else {
                    $n1 = max(0, $seuil - $heures_contractuelles);
                    $n2 = $total_hebdo - $seuil;
                    $heures_maj_n1 = $n1;
                    $heures_maj_n2 = $n2;
                }

                $solde_semaine = ($heures_maj_n1 * $taux_n1) + ($heures_maj_n2 * $taux_n2);
            }

            $total_maj = ($heures_maj_n1 * $taux_n1) + ($heures_maj_n2 * $taux_n2);

            // Insertion ou mise à jour
            $query = "
                INSERT INTO cumuls_semaine (
                    user_id, annee, semaine,
                    heures_sup_brutes, heures_recup_brutes, heures_sup_apres_recup,
                    heures_maj_n1, heures_maj_n2,
                    total_heures_sup, total_heures_recup,
                    solde_semaine, nb_declarations, hash_verification
                ) VALUES (
                    :user_id, :annee, :semaine,
                    :sup_brutes, :recup_brutes, :net,
                    :n1, :n2,
                    :total_sup, :total_recup,
                    :solde, :nb_declarations, :hash
                )
                ON DUPLICATE KEY UPDATE
                    heures_sup_brutes = VALUES(heures_sup_brutes),
                    heures_recup_brutes = VALUES(heures_recup_brutes),
                    heures_sup_apres_recup = VALUES(heures_sup_apres_recup),
                    heures_maj_n1 = VALUES(heures_maj_n1),
                    heures_maj_n2 = VALUES(heures_maj_n2),
                    total_heures_sup = VALUES(total_heures_sup),
                    total_heures_recup = VALUES(total_heures_recup),
                    solde_semaine = VALUES(solde_semaine),
                    nb_declarations = VALUES(nb_declarations),
                    hash_verification = VALUES(hash_verification)
            ";

            $stmt = $this->db->prepare($query);
            file_put_contents($log_file, "Exécution requête d'insertion SQL...\n", FILE_APPEND);
            $stmt->execute([
                ':user_id' => $user_id,
                ':annee' => $annee,
                ':semaine' => $semaine,
                ':sup_brutes' => $heures_sup,
                ':recup_brutes' => $heures_recup,
                ':net' => $heures_apres_recup,
                ':n1' => $heures_maj_n1,
                ':n2' => $heures_maj_n2,
                ':total_sup' => $total_maj,
                ':total_recup' => $heures_recup,
                ':solde' => $solde_semaine,
                ':nb_declarations' => $nb_declarations,
                ':hash' => hash('sha256', $user_id . $annee . $semaine . $total_maj)
            ]);
        } catch (Exception $e) {
            file_put_contents($log_file, "Erreur SQL : " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}
