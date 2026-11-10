<?php

namespace App\Services;

use App\Models\QslCard;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Symfony\Component\Process\Process;

class QslRenderer
{
    public function render(QslCard $card): string
    {
        $card->loadMissing('match.qsoA.callsign', 'match.qsoB.callsign');
        $qso = $card->match->qsoA;
        $url = rtrim(config('qsl.frontend_url'), '/').'/verify/'.$card->public_id;
        $matrix = Encoder::encode($url, ErrorCorrectionLevel::M())->getMatrix();
        $size = $matrix->getWidth();
        $scale = 4;
        $qrSize = ($size + 8) * $scale;
        $rects = '<rect x="'.(1160 - $qrSize).'" y="40" width="'.$qrSize.'" height="'.$qrSize.'" fill="white"/>';
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $rects .= '<rect x="'.(1160 - $qrSize + ($x + 4) * $scale).'" y="'.(40 + ($y + 4) * $scale).'" width="4" height="4" fill="black"/>';
                }
            }
        }
        $lines = ['QSL NEXUS', 'BILATERALLY CONFIRMED', 'Station A: '.$qso->callsign->callsign, 'Station B: '.$card->match->qsoB->callsign->callsign,
            $qso->started_at->utc()->format('Y-m-d H:i').' UTC', $qso->band.' | '.$qso->frequency_khz.' kHz | '.$qso->mode,
            'ID: '.$card->public_id, 'Issued: '.$card->generated_at->utc()->format('Y-m-d H:i').' UTC'];
        $text = '';
        foreach ($lines as $index => $line) {
            $text .= '<text x="55" y="'.(80 + $index * 68).'" font-size="'.($index === 2 ? '30' : '24').'" fill="'.($index < 2 ? '#22d3ee' : '#e2e8f0').'">'.htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</text>';
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="600"><rect width="1200" height="600" fill="#080f20"/><rect x="20" y="20" width="1160" height="560" rx="16" fill="none" stroke="#155e75"/><g font-family="DejaVu Sans">'.$text.'</g>'.$rects.'</svg>';
        // Argumentos separados y SVG generado internamente: ningún comando o ruta viene del usuario.
        $process = new Process([config('qsl.imagemagick'), '-background', 'none', 'svg:-', 'png:-']);
        $process->setInput($svg);
        $process->setTimeout(20);
        $process->mustRun();

        return $process->getOutput();
    }
}
