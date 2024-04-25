<?php
namespace Groupup\Retouretitrebordereauegenerator;

require_once '../vendor/autoload.php';
// ini_set('display_errors', 0);



$colis = json_decode(json_encode(array(
    'uid' => '10',
    'type_retour' => 0,
    'bordereaus' => [
        [
            'uid' => '10',
            'code_client' => 80836,
            'numero_remise' => 70095350,
            'adresse_siege' => '72 AVENUE RAYMOND POINCARÉ',
            'raison_sociale_siege' => 'INEO INFRACOM',
            'complement_siege' => '',
            'batiment_siege' => '',
            'code_postal_siege' => '21066',
            'ville_siege' => 'DIJON CEDEX',
            'motif_retour' => "110",
            'nb_cheque' => 1,
            'montant_total' => 1,
        ],
        [
            'uid' => '12',
            'code_client' => 80736,
            'numero_remise' => 70095350,
            'adresse_siege' => '72 AVENUE RAYMOND POINCARÉ',
            'raison_sociale_siege' => 'INEO INFRACOM',
            'complement_siege' => '',
            'batiment_siege' => '',
            'code_postal_siege' => '21066',
            'ville_siege' => 'DIJON CEDEX',
            'motif_retour' => "110",
            'nb_cheque' => 1,
            'montant_total' => 1,
        ],
    ],
    'produit' => [
        'uid' => 1,
    'modele_bordereau_avoir' => 'CDJ_hors_perimes_01.pdf',
    'modele_bordereau_perime' => 'CDJ_Perimes_02.pdf',
    'num_document_avoir' => '0270',
    'num_document_perime' => '0280',
        'code_societe'=> '01'

    ],
)));

$retoureTitreB = new RetoureTitreBordereaue($colis, 'uploads', 'templates');
var_dump($retoureTitreB->generateBordereaus(0));