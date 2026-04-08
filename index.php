<?php
$billId = '';
$billData = null;
$params = array_keys($_GET);
if (!empty($params)) {
    $billId = $params[0];
    if (preg_match('/^[a-zA-Z0-9]{7}$/', $billId)) {
        $billFile = __DIR__ . '/bills/' . $billId . '.json';
        if (file_exists($billFile)) {
            $billData = json_decode(file_get_contents($billFile), true);
        }
    }
}

$title = 'Bill Splitter';
$desc = 'Split expenses with your group';

if ($billData) {
    $svcRate = floatval($billData['serviceRate'] ?? 0);
    $gstRate = floatval($billData['gstRate'] ?? 0);
    $grandTotal = 0;
    $summary = [];
    foreach ($billData['people'] as $p) {
        $sub = 0;
        foreach ($p['amounts'] as $a) $sub += floatval($a) ?: 0;
        $svc = $sub * ($svcRate / 100);
        $gst = ($sub + $svc) * ($gstRate / 100);
        $total = $sub + $svc + $gst;
        $grandTotal += $total;
        $summary[] = $p['name'] . ': ' . number_format($total, 2);
    }
    $summary[] = 'Total: ' . number_format($grandTotal, 2);
    $title = 'Bill Splitter';
    $desc = implode("\n", $summary);
    $descAttr = str_replace("\n", '&#10;', htmlspecialchars($desc));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.svg?v=2" type="image/svg+xml">
    <title><?php echo $title ? htmlspecialchars($title) : 'Bill Splitter'; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($desc); ?>">
<?php if ($title): ?>
    <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>">
<?php endif; ?>
    <meta property="og:description" content="<?php echo $descAttr; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Bill Splitter">
    <meta property="og:image" content="https://bill.ibattus.com/og-image.php<?php echo $billId ? '?' . htmlspecialchars($billId) : ''; ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300..800&family=Oswald:wght@200..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=15">
</head>
<body>
    <div class="app">
        <header class="app-header">
            <div class="header-inner">
                <div>
                    <h1 class="logo">Bill Splitter</h1>
                    <p class="tagline">Split expenses with your group</p>
                </div>
                <div class="header-actions">
                    <button class="icon-btn btn-new" onclick="BillSplitter.clearAll()" title="New Bill" aria-label="New Bill">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                    <button class="icon-btn btn-print" onclick="window.print()" title="Print" aria-label="Print">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    </button>
                </div>
            </div>
        </header>

        <div id="toast" aria-live="polite"></div>

        <div class="settings no-print">
            <div class="setting">
                <label for="serviceRate">Service</label>
                <div class="input-wrap">
                    <input type="number" id="serviceRate" value="10" step="0.5" min="0">
                    <span class="input-suffix">%</span>
                </div>
            </div>
            <div class="setting">
                <label for="gstRate">GST</label>
                <div class="input-wrap">
                    <input type="number" id="gstRate" value="8" step="0.5" min="0">
                    <span class="input-suffix">%</span>
                </div>
            </div>
        </div>

        <div class="table-card">
            <table class="bill-table">
                <thead>
                    <tr id="headerRow">
                        <th class="col-name">Person</th>
                        <th class="col-item" contenteditable="true">Item 1</th>
                        <th class="col-add no-print"><button onclick="BillSplitter.addItem()" aria-label="Add item">+</button></th>
                        <th class="col-total">Total</th>
                        <th class="col-action no-print"></th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
                <tfoot>
                    <tr class="footer-row">
                        <td class="footer-label">Total</td>
                        <td class="footer-breakdown" colspan="2" id="footerBreakdown">
                            <div class="bk"><span>Subtotal</span><span id="totalSubtotal">0.00</span></div>
                            <div class="bk"><span>Service</span><span id="totalService">0.00</span></div>
                            <div class="bk"><span>GST</span><span id="totalGST">0.00</span></div>
                        </td>
                        <td class="footer-grand" id="grandTotal">0.00</td>
                        <td class="no-print"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="bottom-bar no-print">
            <button class="btn btn-add" onclick="BillSplitter.addPerson()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Person
            </button>
            <button class="btn btn-save" id="saveBtn" onclick="BillSplitter.saveBill()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Save &amp; Share
            </button>
        </div>
    </div>

    <div class="modal-backdrop" id="modal">
        <div class="modal">
            <div class="modal-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <h2 class="modal-title">Bill Saved</h2>
            <p class="modal-desc">Share this link so others can view the bill</p>
            <div class="share-link">
                <input type="text" id="shareLink" readonly>
                <button class="btn btn-copy" id="copyBtn" onclick="BillSplitter.copyLink()">Copy</button>
            </div>
            <button class="btn btn-close" onclick="BillSplitter.closeModal()">Done</button>
        </div>
    </div>

    <script src="app.js?v=12"></script>
</body>
</html>
