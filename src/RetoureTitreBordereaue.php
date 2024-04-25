<?php
namespace Groupup\Retouretitrebordereauegenerator;

use setasign\Fpdi\Fpdi;

require_once('libs/barcodegen/class/BCGFontFile.php');
require_once('libs/barcodegen/class/BCGColor.php');
require_once('libs/barcodegen/class/BCGDrawing.php');
require_once('libs/barcodegen/class/BCGi25.barcode.php');

class RetoureTitreBordereaue
{

    private $colis;
    public $bordereau;
    public $mentionCodeBarre;
    public $uploadPath;
    public $productsTemplatesPath;
    public $pdf;

    public function __construct(
        $colis,
        $uploadPath,
        $productsTemplatesPath
    ) {
        if(!is_dir($uploadPath)){
            throw new \InvalidArgumentException('uploads path folder is not set');
        }
        if(!isset($productsTemplatesPath)&& !is_dir($productsTemplatesPath)){
            throw new \InvalidArgumentException('upload path folder is not set');
        }

        if (!$colis->bordereaus) {
            throw new \InvalidArgumentException('Colis is null');
        }
        if (empty($colis->bordereaus)) {
            throw new \InvalidArgumentException('Empty bordereaus array');
        }

        if (!isset($colis->produit)) {
            throw new \InvalidArgumentException('Colis must have a product');
        }
        $this->uploadPath = $uploadPath;
        $this->productsTemplatesPath = $productsTemplatesPath;
        $this->colis = $colis;
    }

    function generateBordereaus($preview = 0)
    {
        try
        {
            if ($preview == 1) {
                $this->bordereau = $this->colis->bordereaus[0];
                $this->generate($preview);
                return;
            }
            $generatedFiles = [];
            foreach ($this->colis->bordereaus as $key => $bordereau) {
                $this->bordereau = $bordereau;
                $generatedFiles[] = $this->generate(0);
            }
            return $generatedFiles;
        } catch (\RuntimeException $e) {

        }

    }

    function generate($preview = 0)
    {
        $this->pdf = new Fpdi('P', 'pt', 'A4');

        $modele = $this->getModele();

        //Files that will be generated
        $pdf_genere = $this->uploadPath.'/bordereau_' . $this->bordereau->uid . '_' . $this->bordereau->code_client . '.pdf';
        $gif_genere = $this->uploadPath.'/bordereau_' . $this->bordereau->uid . '_' . $this->bordereau->code_client . '.jpg';
        $gif_genere2 = $this->uploadPath.'/bordereau_' . $this->bordereau->uid . '_' . $this->bordereau->code_client . '2.jpg';

        $this->pdf->SetCompression(false);
        $this->pdf->SetMargins(0, 0);
        $this->pdf->AddPage();

        $this->pdf->SetAutoPageBreak(false);

        $pagecount = $this->pdf->setSourceFile($modele);
        $tppl = $this->pdf->importPage(1);

        $size = $this->pdf->getTemplateSize($tppl);

        $this->pdf->useTemplate($tppl, null, null, $size['width'], $size['height'], true);
        $this->setNumclientFirst();
        $this->setNumRemiseFirst();
        $this->setAdresse();
        $this->setNumclientSecond();
        $this->setNumRemiseSecond();
        if (intval($this->colis->type_retour) != 1) {
            $this->setMotifRetour();
        }

        $this->setNumRemiseThird();
        $this->setRaisonSociale();
        $this->setAdresseEncartBas();
        $this->setTM();
        $this->setDateLimite($this->colis);

        $this->setQteLocale();
        $this->setMontantTotal();

        if ($preview == 1) {
            $codeBarre = $this->productsTemplatesPath.'/CodeBarExamples/code_barre_exemple.gif';
            $codeBarre2 =  $this->productsTemplatesPath.'/CodeBarExamples/code_barre_exemple2.gif';
            $this->setCodeBarre($codeBarre,$codeBarre2);
            $this->setMentionCodeBarre();
        }
        else {

            $this->generateCodeBarre($gif_genere,$gif_genere2);
            $this->setCodeBarre($gif_genere,$gif_genere2);
            unlink($gif_genere);
            unlink($gif_genere2);
            $this->setMentionCodeBarre();
        }
        $this->pdf->AddPage();
        $tpp2 = $this->pdf->importPage(2);
        $size = $this->pdf->getTemplateSize($tpp2);
        $this->pdf->useTemplate($tpp2, null, null, $size['width'], $size['height'], true);

        // $this->setCheque();
        $this->setDate();
        $this->setQteLocale2();
        $this->setMontantTotal2();

        $this->pdf->Output($pdf_genere, "F");

        if ($preview == 1) {

            $name = 'apercu' . time() . '.pdf';
            $apercu = $uploads.$name;
            // WaterMark::applyAndSpit($pdf_genere,$apercu);
            $content = file_get_contents($apercu);
            unlink($apercu);
            unlink($pdf_genere);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            echo $content;
            exit();
        } 
        
        return ['uid' => $this->bordereau->uid,  'generatedFileName' =>$pdf_genere];
    }

    function generateCodeBarre($file_horizontal, $file_vertical)
    {

        // The arguments are R, G, B for color.
        $color_black = new \BCGColor(0, 0, 0);
        $color_white = new \BCGColor(255, 255, 255);

        $code_societe = $this->colis->produit->code_societe;
        $remise = ($this->bordereau->numero_remise != '') ? $this->bordereau->numero_remise : '123456789';

        $remise = str_pad($remise, 9, '0', STR_PAD_LEFT);

        if ($this->colis->type_retour == 1) {
            $document = $this->colis->produit->num_document_perime;
        } else {
            $document = $this->colis->produit->num_document_avoir;
        }

        $client = trim(str_replace(array("UP-FR-", "UP", "-", "FR"), '', $this->bordereau->code_client));

        $client = str_pad($client, 10, '0', STR_PAD_LEFT);
        $operation = '00000';
        $libre = '0000000000';
        $tabBarCode = array();
        $tabBarCode[] = $code_societe;
        $tabBarCode[] = $remise;
        $tabBarCode[] = $document;
        $tabBarCode[] = $client;
        $tabBarCode[] = $operation;
        $tabBarCode[] = $libre;

        $datas = implode($tabBarCode);
        $cle = intval($remise) % 97;
        $datas .= $cle;

        $this->mentionCodeBarre = '>' . $code_societe . '<' . $remise . '<' . $document . '<' . $client . '<' . $operation . '<' . $libre . '<' . $cle . '<';
        
        try {
            $code = new \BCGi25();
            $code->setScale(1); // Resolution
            $code->setRatio(1.9);
            $code->setThickness(56); // Thickness
            $code->setForegroundColor($color_black); // Color of bars
            $code->setBackgroundColor($color_white); // Color of spaces
            $code->setFont(0); // Font (or 0)
            $code->parse($datas); // Text
        } catch (Exception $exception) {
            $drawException = $exception;
        }

        $drawing = new \BCGDrawing($color_white, $file_horizontal);
        $drawing->setDPI(300);
        if (isset($drawException)) {
            $drawing->drawException($drawException);
        } else {
            $drawing->setBarcode($code);
            $drawing->draw();
        }
        $drawing->finish(\BCGDrawing::IMG_FORMAT_JPEG);

        unset($code);

        try {
            $code = new \BCGi25();
            //$code = new BCGcode128();
            $code->setScale(1); // Resolution
            $code->setRatio(0.6); //==> A Cause du 128
            $code->setThickness(26); // Thickness
            $code->setForegroundColor($color_black); // Color of bars
            $code->setBackgroundColor($color_white); // Color of spaces
            $code->setFont(0); // Font (or 0)

            $code->parse($datas); // Text
        } catch (Exception $exception) {
            $drawException = $exception;
        }

        $drawing2 = new \BCGDrawing($color_white, $file_vertical);
        $drawing2->setDPI(300);
        $drawing2->setRotationAngle(90);
        if (isset($drawException)) {
            $drawing2->drawException($drawException);
        } else {
            $drawing2->setBarcode($code);
            $drawing2->draw();
        }

        $drawing2->finish(\BCGDrawing::IMG_FORMAT_JPEG);

    }

    function getModele()
    {
        if ($this->colis->type_retour == 1 && $this->colis->produit->modele_bordereau_perime != '') {
            return $this->productsTemplatesPath.'/PdfModels/' . $this->colis->produit->modele_bordereau_perime;
        } else {
            return $this->productsTemplatesPath.'/PdfModels/' . $this->colis->produit->modele_bordereau_avoir;
        }

    }

    function getCheque($uid_bordereau)
    {

        $cheques = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            "*",
            "tx_obladyretourtitre_cheque",
            "hidden=0 AND deleted=0 AND bordereau=" . $uid_bordereau
        );

        return $cheques;
    }

    function setNumclientFirst()
    {

        if (strlen($this->bordereau->code_client) > 10) {
            $x = 30;
            $this->pdf->SetFont('Arial', '', 9);
        } else {
            $x = 32;
            $this->pdf->SetFont('Arial', '', 12);
        }
        $this->pdf->SetXY($x, 120);
        $this->pdf->Write(0, $this->bordereau->code_client);

    }

    function setNumRemiseFirst()
    {

        $text = ($this->bordereau->numero_remise != '') ? $this->bordereau->numero_remise : '123456789';

        $this->pdf->SetFont('Arial', '', 12);
        $this->pdf->SetXY(130, 120);
        $this->pdf->Write(0, $text);

    }

    function setAdresse()
    {

        $x = 250;
        $y = 100;

        $this->bordereau->adresse_siege = str_replace(array("\r\n", "\r", "\n"), "", $this->bordereau->adresse_siege);
        $this->bordereau->raison_sociale_siege = str_replace(array("\r\n", "\r", "\n"), "", $this->bordereau->raison_sociale_siege);
        $this->bordereau->complement_siege = str_replace(array("\r\n", "\r", "\n"), "", $this->bordereau->complement_siege);
        $this->bordereau->batiment_siege = str_replace(array("\r\n", "\r", "\n"), "", $this->bordereau->batiment_siege);
        $this->bordereau->code_postal_siege = str_replace(array("\r\n", "\r", "\n"), "", $this->bordereau->code_postal_siege);
        $this->bordereau->ville_siege = str_replace(array("\r\n", "\r", "\n"), "", $this->bordereau->ville_siege);

        //echo "================>JSON=============>\r\n";
        //echo json_encode($this->bordereau);
        //echo "\r\n";

        //on ajuste la taille de la police en fonction de la largeur disponible
        if (strlen($this->bordereau->raison_sociale_siege) < strlen($this->bordereau->adresse_siege)) {
            $this->ajustFontSize($this->bordereau->adresse_siege, 11, 200);
        } else {
            $this->ajustFontSize($this->bordereau->raison_sociale_siege, 11, 200);
        }
        $this->pdf->SetXY($x, $y);
        $this->pdf->Write(0,
            mb_convert_encoding($this->bordereau->raison_sociale_siege, 'iso-8859-2', 'UTF-8')
        );

        $y += 30;

        //$this->pdf->SetFont('Arial', '', 11);
        $this->pdf->SetXY($x, $y);

        $this->pdf->Write(0,
            mb_convert_encoding($this->bordereau->adresse_siege, 'iso-8859-2', 'UTF-8')
        );

        if ($this->bordereau->complement_siege != '') {
            $y += 15;

            //$this->pdf->SetFont('Arial', '', 11);
            $this->pdf->SetXY($x, $y);
            $this->pdf->Write(0, mb_convert_encoding($this->bordereau->complement_siege, 'iso-8859-2', 'UTF-8'));
        }

        if ($this->bordereau->batiment_siege != '') {
            $y += 15;

            //$this->pdf->SetFont('Arial', '', 11);
            $this->pdf->SetXY($x, $y);
            $this->pdf->Write(0, mb_convert_encoding($this->bordereau->batiment_siege, 'iso-8859-2', 'UTF-8'));
        }

        $y += 15;

        //$this->pdf->SetFont('Arial', '', 11);
        $this->pdf->SetXY($x, $y);
        $this->pdf->Write(0, mb_convert_encoding($this->bordereau->code_postal_siege . ' ' . $this->bordereau->ville_siege, 'iso-8859-2', 'UTF-8'));

    }

    function setNumclientSecond()
    {

        if (strlen($this->bordereau->code_client) > 10) {
            $this->pdf->SetFont('Arial', '', 8);
        } else {
            $this->pdf->SetFont('Arial', '', 11);
        }
        $this->pdf->SetXY(118, 405);
        $this->pdf->Write(0, $this->bordereau->code_client);

    }

    function setNumRemiseSecond()
    {

        $text = ($this->bordereau->numero_remise != '') ? $this->bordereau->numero_remise : '123456789';

        $this->pdf->SetFont('Arial', '', 11);
        $this->pdf->SetXY(191, 405);
        $this->pdf->Write(0, $text);

    }

    function setMotifRetour()
    {

        $motif_retour = $this->bordereau->motif_retour;

        $y = 394;

        switch ($motif_retour) {

            case "110":$y += 12;
                break;

            case "120":$y += 24;
                break;

            case "130":$y += 35;
                break;

            case "140":$y += 46;
                break;

            case "150":$y += 58;
                break;

            case "160":$y += 69;
                break;

            case "170":$y += 81;
                break;

            case "180":$y += 92;
                break;

            case "190":$y += 104;
                break;

            case "200":$y += 115;
                break;

            case "210":$y += 126;
                break;

            case "220":$y += 138;
                break;

            case "230":$y += 149;
                break;

            case "240":$y += 161;
                break;
        }

        $this->pdf->SetFont('Arial', 'B', 11);
        $this->pdf->SetXY(524, $y);
        $this->pdf->Write(0, 'X');

    }

    function setNumRemiseThird()
    {
        $text = ($this->bordereau->numero_remise != '') ? $this->bordereau->numero_remise : '123456789';

        $this->pdf->SetFont('Arial', '', 11);
        $this->pdf->SetXY(210, 612);
        $this->pdf->Write(0, $text);

    }

    function setRaisonSociale()
    {

        $this->ajustFontSize($this->bordereau->raison_sociale_siege, 11, 175);

        $this->pdf->SetXY(280, 612);
        $this->pdf->Write(0, mb_convert_encoding($this->bordereau->raison_sociale_siege, 'UTF-8'));

    }

    function setAdresseEncartBas()
    {

        $x = 212;
        $y = 628;

        $this->pdf->SetFont('Arial', '', 11);
        $this->pdf->SetXY($x, $y);
        $this->pdf->Write(0, $this->bordereau->code_postal_siege);

        $x += 68;

        $this->pdf->SetFont('Arial', '', 11);
        $this->pdf->SetXY($x, $y);
        $this->pdf->Write(0, $this->bordereau->ville_siege);

    }

    function setCodeBarre($codeBarre, $codeBarre2)
    {

        $this->pdf->Image($codeBarre, 215, 708);

        $this->pdf->Image($codeBarre2, 511, 569);
    }

    function setMentionCodeBarre()
    {

        if ($this->mentionCodeBarre == '') {

            $code_societe = $this->colis->produit->code_societe;
            $remise = ($this->bordereau->numero_remise != '') ? $this->bordereau->numero_remise : '123456789';

            $len = strlen($remise);
            for ($i = $len; $i < 9; $i++) {
                $remise = '0' . $remise;
            }

            if ($this->colis->type_retour == 1) {
                $document = $this->colis->produit->num_document_perime;
            } else {
                $document = $this->colis->produit->num_document_avoir;
            }

            $client = $this->bordereau->code_client;
            $len = strlen($client);
            for ($i = $len; $i < 10; $i++) {
                $client = '0' . $client;
            }

            $operation = '00000';
            $libre = '0000000000';

            $cle = intval($remise) % 97;

            $text = '>' . $code_societe . '<' . $remise . '<' . $document . '<' . $client . '<' . $operation . '<' . $libre . '<' . $cle . '<';
        } else {
            $text = $this->mentionCodeBarre;
        }

        $this->pdf->AddFont('OCR', '', 'ocr-b_10_pitch_bt.php');
        $this->pdf->SetFont('OCR', '', 8);
        $this->pdf->SetXY(220, 766);
        $this->pdf->Write(0, $text);
        $this->pdf->SetFont('Arial', '', 8);
    }

    function setTM()
    {

        $text = ($this->bordereau->numero_remise != '') ? $this->bordereau->numero_remise : '123456789';

        $this->pdf->SetFont('Arial', '', 15);
        $this->pdf->SetXY(216, 665);
        $this->pdf->Write(0, 'TM' . $text);

    }

    function setDateLimite($colis)
    {

        setlocale(LC_TIME, 'fr_FR');

        $date = '';

        if ($this->colis->produit->uid == 1 && $this->colis->type_retour == 1) {
            if (mktime(0, 0, 0, 4, 0, date('Y', time())) < time()) {
                $date = \DateTime::format('%d %B %Y', mktime(0, 0, 0, 4, 0, date('Y', time()) + 1));
            } else {
                $date = \DateTime::format('%d %B %Y', mktime(0, 0, 0, 4, 0, date('Y', time())));
            }
        } elseif ($this->colis->produit->uid == 3) {
            if (mktime(0, 0, 0, 3, 0, date('Y', time())) < time()) {
                $date = \DateTime::format('%d %B %Y', mktime(0, 0, 0, 3, 0, date('Y', time()) + 1));
            } else {
                $date = \DateTime::format('%d %B %Y', mktime(0, 0, 0, 3, 0, date('Y', time())));
            }
        } elseif ($this->colis->produit->uid == 7 || $this->colis->produit->uid == 9) {
            if (strtotime("last day of january") < time()) {
                $date = \DateTime::format('%d %B %Y', mktime(0, 0, 0, 2, 0, date('Y', time()) + 1));
            } else {
                $date = \DateTime::format('%d %B %Y', strtotime("last day of january"));
            }
        }

        if ($date != '') {
            $this->pdf->SetFont('Arial', 'B', 10);
            $this->pdf->SetXY(50, 743);
            $this->pdf->Write(0, strtoupper($date));
        }

    }

    function setCheque()
    {

        $x = 10;
        $y = 410;

        $count = 0;

        foreach ($this->cheques as $cheque) {

            if ($count < 27) {
                if ($cheque['dernier_numero'] != '') {
                    $this->pdf->SetFont('Arial', '', 10);
                    $txt = 'Du n° ' . $cheque['premier_numero'] . ' au ' . $cheque['dernier_numero'];
                } else {
                    $this->pdf->SetFont('Arial', '', 12);
                    $txt = $cheque['premier_numero'];
                }

                $this->pdf->SetXY($x, $y);
                $this->pdf->Write(0, mb_convert_encoding($txt));

                $count++;
                $y += 18;
                if ($count % 9 == 0) {
                    $y = 410;
                    $x += 180;
                }
            }
        }

    }

    function setDate()
    {

        $y = 718;

        $jour = date('d', time()) . '/';
        $mois = date('m', time()) . '/';
        $annee = date('y', time());

        $this->pdf->SetFont('Arial', '', 15);
        $this->pdf->SetXY(7, $y);
        $this->pdf->Write(0, $jour);

        $this->pdf->SetFont('Arial', '', 15);
        $this->pdf->SetXY(30, $y);
        $this->pdf->Write(0, $mois);

        $this->pdf->SetFont('Arial', '', 15);
        $this->pdf->SetXY(53, $y);
        $this->pdf->Write(0, $annee);

    }

    function setQteLocale()
    {

        $this->pdf->SetFont('Arial', '', 13);
        $this->pdf->SetXY(340, 673);
        $this->pdf->Write(0, $this->bordereau->nb_cheque);

    }

    function setMontantTotal()
    {

        $this->pdf->SetFont('Arial', '', 13);
        $this->pdf->SetXY(400, 673);
        $this->pdf->Write(0, $this->bordereau->montant_total);

    }

    function setQteLocale2()
    {

        $this->pdf->SetFont('Arial', '', 15);
        $this->pdf->SetXY(15, 761);
        $this->pdf->Write(0, $this->bordereau->nb_cheque);

    }

    function setMontantTotal2()
    {

        $this->pdf->SetFont('Arial', '', 15);
        $this->pdf->SetXY(90, 761);
        $this->pdf->Write(0, $this->bordereau->montant_total);

    }

    function ajustFontSize($texte, $sizeStart, $lineWidth)
    {

        $my_string = $texte;
        $font_size = $sizeStart;
        $decrement_step = 0.1;
        $line_width = $lineWidth; // Line width (approx) in mm

        $this->pdf->SetFont('Arial', '', $font_size);
        while ($this->pdf->GetStringWidth($my_string) > $line_width) {
            $this->pdf->SetFontSize($font_size -= $decrement_step);
        }

    }

}
