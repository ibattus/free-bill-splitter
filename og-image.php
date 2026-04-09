<?php
$billId = isset($_GET['id']) ? $_GET['id'] : '';

$billData = null;
if (preg_match('/^[a-zA-Z0-9]{7}$/', $billId)) {
    $billFile = __DIR__ . '/bills/' . $billId . '.json';
    if (file_exists($billFile)) {
        $billData = json_decode(file_get_contents($billFile), true);
    }
}

$font = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
$fontBold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

if ($billData) {
    $svcRate = floatval($billData['serviceRate'] ?? 0);
    $gstRate = floatval($billData['gstRate'] ?? 0);
    $items = $billData['items'] ?? [];
    $people = $billData['people'] ?? [];
    $numItems = count($items);
    $numPeople = count($people);

    $colW = $numItems <= 2 ? 160 : ($numItems <= 4 ? 130 : 105);
    $nameW = 200;
    $totalW = 160;
    $rowH = 48;
    $headerH = 56;
    $padding = 60;
    $topBarH = 50;
    $titleH = 90;
    $footerH = 40;

    $tableW = $nameW + ($colW * $numItems) + $totalW;
    $canvasW = $tableW + ($padding * 2);
    $canvasH = $topBarH + $titleH + $headerH + ($rowH * $numPeople) + $footerH * 4 + $padding + 40;

    $img = imagecreatetruecolor($canvasW, $canvasH);

    $white = imagecolorallocate($img, 255, 255, 255);
    $dark = imagecolorallocate($img, 24, 24, 27);
    $gray = imagecolorallocate($img, 113, 113, 122);
    $green = imagecolorallocate($img, 16, 185, 129);
    $lightBg = imagecolorallocate($img, 250, 250, 250);
    $border = imagecolorallocate($img, 228, 228, 231);
    $darkBg = imagecolorallocate($img, 24, 24, 27);

    imagefilledrectangle($img, 0, 0, $canvasW, $canvasH, $white);
    imagefilledrectangle($img, 0, 0, $canvasW, 4, $darkBg);

    imagettftext($img, 24, 0, $padding, $topBarH + 20, $gray, $font, 'BILL SPLITTER');

    $tableX = $padding;
    $y = $topBarH + $titleH;

    $cols = [];
    $x = $tableX;
    $cols[] = ['x' => $x, 'w' => $nameW, 'label' => 'Person', 'align' => 'left'];
    $x += $nameW;
    for ($i = 0; $i < $numItems; $i++) {
        $cols[] = ['x' => $x, 'w' => $colW, 'label' => $items[$i] ?? 'Item ' . ($i + 1), 'align' => 'right'];
        $x += $colW;
    }
    $cols[] = ['x' => $x, 'w' => $totalW, 'label' => 'Total', 'align' => 'right'];

    imagefilledrectangle($img, $tableX, $y, $tableX + $tableW, $y + $headerH, $darkBg);
    $whiteText = imagecolorallocate($img, 255, 255, 255);
    foreach ($cols as $col) {
        $label = strtoupper($col['label']);
        $bbox = imagettfbbox(22, 0, $fontBold, $label);
        $tw = $bbox[2] - $bbox[0];
        if ($col['align'] === 'right') {
            $tx = $col['x'] + $col['w'] - $tw - 16;
        } else {
            $tx = $col['x'] + 16;
        }
        imagettftext($img, 22, 0, $tx, $y + 36, $whiteText, $fontBold, $label);
    }
    $y += $headerH;

    $tSub = 0; $tSvc = 0; $tGst = 0;
    foreach ($people as $idx => $p) {
        $bgColor = ($idx % 2 === 0) ? $white : $lightBg;
        imagefilledrectangle($img, $tableX, $y, $tableX + $tableW, $y + $rowH, $bgColor);

        imageline($img, $tableX, $y + $rowH, $tableX + $tableW, $y + $rowH, $border);

        imagettftext($img, 24, 0, $cols[0]['x'] + 16, $y + 34, $dark, $fontBold, $p['name']);

        $sub = 0;
        for ($i = 0; $i < $numItems; $i++) {
            $val = floatval($p['amounts'][$i] ?? 0) ?: 0;
            $sub += $val;
            $valStr = number_format($val, 2);
            $bbox = imagettfbbox(22, 0, $font, $valStr);
            $tw = $bbox[2] - $bbox[0];
            $tx = $cols[$i + 1]['x'] + $cols[$i + 1]['w'] - $tw - 16;
            imagettftext($img, 22, 0, $tx, $y + 34, $dark, $font, $valStr);
        }

        $svc = $sub * ($svcRate / 100);
        $gst = ($sub + $svc) * ($gstRate / 100);
        $total = $sub + $svc + $gst;
        $tSub += $sub; $tSvc += $svc; $tGst += $gst;

        $totalStr = number_format($total, 2);
        $bbox = imagettfbbox(22, 0, $fontBold, $totalStr);
        $tw = $bbox[2] - $bbox[0];
        $tx = $cols[$numItems + 1]['x'] + $cols[$numItems + 1]['w'] - $tw - 16;
        imagettftext($img, 22, 0, $tx, $y + 34, $green, $fontBold, $totalStr);

        $y += $rowH;
    }

    imageline($img, $tableX, $y, $tableX + $tableW, $y, $border);

    $footerLines = [
        ['label' => 'Subtotal', 'value' => number_format($tSub, 2)],
    ];
    if ($svcRate > 0) {
        $footerLines[] = ['label' => "Service ({$svcRate}%)", 'value' => number_format($tSvc, 2)];
    }
    if ($gstRate > 0) {
        $footerLines[] = ['label' => "GST ({$gstRate}%)", 'value' => number_format($tGst, 2)];
    }

    $fy = $y + 8;
    foreach ($footerLines as $fl) {
        imagettftext($img, 20, 0, $cols[0]['x'] + 16, $fy + 30, $gray, $font, $fl['label']);
        $bbox = imagettfbbox(20, 0, $font, $fl['value']);
        $tw = $bbox[2] - $bbox[0];
        $tx = $cols[$numItems + 1]['x'] + $cols[$numItems + 1]['w'] - $tw - 16;
        imagettftext($img, 20, 0, $tx, $fy + 30, $gray, $font, $fl['value']);
        $fy += $footerH;
    }

    $fy += 4;
    $grandTotal = $tSub + $tSvc + $tGst;
    imagefilledrectangle($img, $tableX, $fy, $tableX + $tableW, $fy + $footerH + 12, $darkBg);
    imagettftext($img, 24, 0, $cols[0]['x'] + 16, $fy + 36, $whiteText, $fontBold, 'Total');
    $gtStr = number_format($grandTotal, 2);
    $bbox = imagettfbbox(24, 0, $fontBold, $gtStr);
    $tw = $bbox[2] - $bbox[0];
    $tx = $cols[$numItems + 1]['x'] + $cols[$numItems + 1]['w'] - $tw - 16;
    imagettftext($img, 24, 0, $tx, $fy + 36, $whiteText, $fontBold, $gtStr);
} else {
    $img = imagecreatetruecolor(1200, 630);
    $white = imagecolorallocate($img, 255, 255, 255);
    $dark = imagecolorallocate($img, 24, 24, 27);
    $gray = imagecolorallocate($img, 113, 113, 122);
    $darkBg = imagecolorallocate($img, 24, 24, 27);

    imagefilledrectangle($img, 0, 0, 1200, 630, $white);
    imagefilledrectangle($img, 0, 0, 1200, 6, $darkBg);

    imagettftext($img, 48, 0, 80, 280, $dark, $fontBold, 'Bill Splitter');
    imagettftext($img, 28, 0, 80, 340, $gray, $font, 'Split expenses with your group');
}

header('Content-Type: image/png');
imagepng($img);
imagedestroy($img);
