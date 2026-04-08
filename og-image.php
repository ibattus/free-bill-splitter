<?php
// Generate OG image for bill link previews
$billId = '';
$params = array_keys($_GET);
if (!empty($params)) $billId = $params[0];

$billData = null;
if (preg_match('/^[a-zA-Z0-9]{7}$/', $billId)) {
    $billFile = __DIR__ . '/bills/' . $billId . '.json';
    if (file_exists($billFile)) {
        $billData = json_decode(file_get_contents($billFile), true);
    }
}

$width = 1200;
$height = 630;
$img = imagecreatetruecolor($width, $height);

// Background
$bg = imagecolorallocate($img, 255, 255, 255);
imagefilledrectangle($img, 0, 0, $width, $height, $bg);

// Top accent bar
$bar = imagecolorallocate($img, 24, 24, 43);
imagefilledrectangle($img, 0, 0, $width, 6, $bar);

if ($billData) {
    $svcRate = floatval($billData['serviceRate'] ?? 0);
    $gstRate = floatval($billData['gstRate'] ?? 0);
    $grandTotal = 0;
    $people = [];

    foreach ($billData['people'] as $p) {
        $sub = 0;
        foreach ($p['amounts'] as $a) $sub += floatval($a) ?: 0;
        $svc = $sub * ($svcRate / 100);
        $gst = ($sub + $svc) * ($gstRate / 100);
        $total = $sub + $svc + $gst;
        $grandTotal += $total;
        $people[] = ['name' => $p['name'], 'total' => number_format($total, 2)];
    }

    // Title
    $textColor = imagecolorallocate($img, 24, 24, 43);
    $grayColor = imagecolorallocate($img, 113, 113, 122);
    $greenColor = imagecolorallocate($img, 16, 185, 129);
    $lightBg = imagecolorallocate($img, 250, 250, 250);

    // "Bill Splitter" label
    imagettftext($img, 28, 0, 80, 100, $grayColor, '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', 'BILL SPLITTER');

    // Grand total
    imagettftext($img, 72, 0, 80, 190, $textColor, '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', number_format($grandTotal, 2));

    // Divider line
    $lineColor = imagecolorallocate($img, 228, 228, 231);
    imageline($img, 80, 220, 1120, 220, $lineColor);

    // People list
    $y = 290;
    foreach ($people as $person) {
        // Row background
        imagefilledrectangle($img, 80, $y - 35, 1120, $y + 15, $lightBg);
        imagettftext($img, 36, 0, 100, $y, $textColor, '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', $person['name']);
        imagettftext($img, 36, 0, 1000, $y, $greenColor, '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', $person['total']);
        $y += 70;
    }

    // Total row
    $totalBg = imagecolorallocate($img, 24, 24, 43);
    imagefilledrectangle($img, 80, $y + 10, 1120, $y + 70, $totalBg);
    $whiteText = imagecolorallocate($img, 255, 255, 255);
    imagettftext($img, 36, 0, 100, $y + 52, $whiteText, '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', 'Total');
    imagettftext($img, 36, 0, 1000, $y + 52, $whiteText, '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', number_format($grandTotal, 2));
} else {
    $textColor = imagecolorallocate($img, 24, 24, 43);
    $grayColor = imagecolorallocate($img, 113, 113, 122);

    imagettftext($img, 48, 0, 80, 280, $textColor, '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', 'Bill Splitter');
    imagettftext($img, 28, 0, 80, 340, $grayColor, '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', 'Split expenses with your group');
}

header('Content-Type: image/png');
imagepng($img);
imagedestroy($img);
