<?php
// Clé API noCRM.io
$api_key = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

// Fichiers CSV
$csvProjetDetecte = __DIR__ . '/webhook_output_projet_detecte.csv';
$csvAllAppels = __DIR__ . '/webhook_output_all.csv';

// En-têtes CSV
$header = [
    'timestamp', 'ip', 'client_id', 'civilite', 'nom', 'prenom', 'societe', 'siret', 'adresse',
    'code_postal', 'ville', 'activite', 'fonction', 'tel_fixe', 'tel_portable', 'email',
    'etat_appel', 'duree', 'date_appel', 'teleoperateur', 'audio_url', 'commentaire'
];

// Création des fichiers CSV si besoin
foreach ([$csvProjetDetecte, $csvAllAppels] as $file) {
    if (!file_exists($file)) {
        $fp = fopen($file, 'w');
        fputcsv($fp, $header);
        fclose($fp);
    }
}

// Lecture du JSON reçu
$rawData = file_get_contents('php://input');
$data = json_decode($_POST['data'] ?? $rawData, true);
if (!$data) exit("❌ Erreur JSON");

// Informations client
$client = $data['Client'] ?? [];

// Parcours des appels
foreach ($data['HistoriqueAppel'] ?? [] as $appel) {
    $etat = mb_strtolower($appel['Etat'] ?? '');

    $row = [
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'],
        $client['ID'] ?? '',
        $client['Civilite'] ?? '',
        $client['Nom'] ?? '',
        $client['Prenom'] ?? '',
        $client['Societe'] ?? '',
        $client['Siret'] ?? '',
        $client['Adresse'] ?? '',
        $client['CodePostal'] ?? '',
        $client['Ville'] ?? '',
        $client['Activite'] ?? '',
        $client['Fonction'] ?? '',
        $client['TelephoneFixe'] ?? '',
        $client['TelephonePortable'] ?? '',
        $client['Mail'] ?? '',
        $appel['Etat'] ?? '',
        $appel['Duree'] ?? '',
        $appel['DateAppel'] ?? '',
        $appel['Teleoperateur']['Login'] ?? '',
        $appel['AudioUrl'] ?? '',
        $appel['Commentaire'] ?? '',
    ];

    // Sauvegarde CSV global
    $fpAll = fopen($csvAllAppels, 'a');
    fputcsv($fpAll, $row);
    fclose($fpAll);

    // Si projet détecté → enregistrement + création noCRM
    if ($etat === 'projet détecté') {
        $fpProjet = fopen($csvProjetDetecte, 'a');
        fputcsv($fpProjet, $row);
        fclose($fpProjet);

        // Création du lead dans noCRM.io
        $leadData = [
            'title' => $client['Societe'] ?? 'Prospect détecté',
            'description' => sprintf(
                "Nom: %s\nTéléphone: %s\nCommentaire: %s",
                $client['Nom'] ?? '',
                $client['TelephoneFixe'] ?? '',
                $appel['Commentaire'] ?? ''
            ),
            'tags' => ['Projet détecté', 'JobPhoning'],
            'step' => 'Infos OK'
        ];

        $ch = curl_init('https://example.nocrm.io/api/v2/leads');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "X-API-KEY: $api_key",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($leadData),
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        curl_close($ch);
    }
}
