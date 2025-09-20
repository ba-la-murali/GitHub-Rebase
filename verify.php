<?php
// --- PHP LOGIC ---
$details = null;
$status = 'INVALID DATA Has Been Found';
$statusClass = 'invalid';
$statusNote = 'The provided data could not be read or is missing.';
date_default_timezone_set('Asia/Kolkata');

if (isset($_GET['data']) && !empty($_GET['data'])) {
    $jsonData = urldecode($_GET['data']);
    $details = json_decode($jsonData, true);

    if ($details && isset($details['expireDate'])) {
        try {
            $expireDate = DateTime::createFromFormat('d/m/Y', $details['expireDate']);
            // This ensures the certificate is valid for the ENTIRE last day, until 23:59:59
            $expireDate->setTime(23, 59, 59);
            $currentDate = new DateTime();

            if ($expireDate < $currentDate) {
                $status = 'EXPIRED';
                $statusClass = 'expired';
                $statusNote = 'This Registration Certificate has expired as of ' . $expireDate->format('d-M-Y') . '.';
            } else {
                $status = 'VALID'; // Changed from VERIFIED to VALID
                $statusClass = 'verified';
                $statusNote = 'This is a digitally verified document valid until the date of expiry.';
            }
        } catch (Exception $e) { /* Handle errors */ }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Registration Certificate - SA-GOV</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700;900&family=Lato:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js"></script>
    <style>
        :root {
            --paper-bg: #FFFFFF;
            --primary-text: #2c3e50;
            --secondary-text: #596a7b;
            --accent-color: #0d47a1;
            --verified-color: #28a745;
            --expired-color: #c0392b;
            --invalid-color: #f39c12;
            --border-color: #dee2e6;
            --bg-color: #f1f3f5;
            --font-heading: 'Merriweather', serif;
            --font-body: 'Lato', sans-serif;
        }

        /* --- PRINT STYLES (UPDATED FOR A4) --- */
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body {
                background: #FFF !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 210mm;
                height: 297mm;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .certificate-container {
                box-shadow: none !important;
                margin: 15mm; /* Add some margin inside the A4 page */
                border: 2px solid #000;
                width: 100%;
                height: auto;
                border-radius: 0;
                page-break-inside: avoid; /* Ensure it stays on one page */
            }
            .print-button, .modal-overlay {
                display: none !important; /* Hides the print button and modal */
            }
            .status-stamp {
                opacity: 0.15 !important; /* Makes stamp lighter for printing */
            }
            .developer-footer {
                color: #666; /* Lighter color for print */
            }
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background-color: var(--bg-color);
            font-family: var(--font-body);
            color: var(--primary-text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .certificate-container {
            width: 100%;
            max-width: 900px;
            background: var(--paper-bg);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.5s ease-out;
        }

        .status-indicator { width: 100%; height: 8px; }
        .status-indicator.verified { background-color: var(--verified-color); }
        .status-indicator.expired { background-color: var(--expired-color); }
        .status-indicator.invalid { background-color: var(--invalid-color); }

        .certificate-content { padding: 40px 50px; position: relative; }

        .certificate-header { text-align: center; margin-bottom: 25px; border-bottom: 2px dashed var(--border-color); padding-bottom: 20px; }
        .header-logo { width: 175px; height: auto; margin-bottom: 10px; }
        h1 { font-family: var(--font-heading); font-size: 2rem; font-weight: 900; color: var(--accent-color); text-transform: uppercase; letter-spacing: 1.5px; }
        .subtitle { font-size: 1rem; color: var(--secondary-text); margin-top: 5px; }
        .intro-text { font-size: 1rem; color: var(--secondary-text); text-align: center; margin-bottom: 15px; }
        .owner-name-container { padding: 10px; margin-bottom: 25px; text-align: center; }
        #owner { font-family: var(--font-heading); font-size: 2rem; font-weight: 700; color: var(--primary-text); padding-bottom: 5px; border-bottom: 1px solid var(--border-color); display: inline-block; }

        .info-section { background: #f8f9fa; border-radius: 8px; border: 1px solid var(--border-color); padding: 25px; margin-bottom: 25px; }
        .detail-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; }
        .detail-item:not(:last-child) { border-bottom: 1px solid #e9ecef; }
        .detail-item .label { font-weight: 700; color: var(--secondary-text); font-size: 0.9rem; }
        .detail-item .value { font-weight: 600; color: var(--primary-text); font-size: 1rem; }

        .status-stamp {
            position: absolute;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-15deg);
            font-family: var(--font-heading);
            font-size: 6rem;
            font-weight: 900;
            text-transform: uppercase;
            border: 8px double;
            padding: 5px 30px;
            border-radius: 10px;
            opacity: 0.1;
            pointer-events: none;
        }
        .status-stamp.verified { color: var(--verified-color); border-color: var(--verified-color); }
        .status-stamp.expired { color: var(--expired-color); border-color: var(--expired-color); }
        .status-stamp.invalid { display: none; }

        .certificate-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; margin-top: 20px; border-top: 2px dashed var(--border-color); }
        .date-item { text-align: center; }
        .date-item .label { font-weight: 700; font-size: 0.9rem; color: var(--secondary-text); }
        .date-value { font-weight: 700; font-size: 1.1rem; display: block; margin-top: 5px; }
        #qrcode-container { text-align: center; cursor: pointer; transition: transform 0.2s ease; }
        #qrcode-container:hover { transform: scale(1.05); }
        #qrcode { padding: 5px; background-color: white; border: 1px solid var(--border-color); border-radius: 4px; margin-bottom: 4px; display: inline-block; }
        #qrcode-container .qr-label { font-size: 0.8rem; color: var(--secondary-text); display: block; }
        .status-note { text-align: center; font-size: 13px; color: var(--secondary-text); padding: 20px 0 0 0; }
        
        /* --- NEW DEVELOPER FOOTER --- */
        .developer-footer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
            font-size: 0.8rem;
            color: var(--secondary-text);
        }
        .developer-footer svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }
        .developer-footer a {
            color: inherit;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.2s;
        }
        .developer-footer a:hover {
            color: var(--primary-text);
            text-decoration: underline;
        }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(5px); display: flex; justify-content: center; align-items: center; opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 1000; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-content { background: #fff; padding: 30px; border-radius: 16px; text-align: center; transform: scale(0.95); transition: transform 0.3s ease; }
        .modal-overlay.active .modal-content { transform: scale(1); }
        #qrcode-zoomed { padding: 15px; border-radius: 12px; }
        .copy-button { display: inline-flex; align-items: center; gap: 8px; background: #e9ecef; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px 20px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background-color 0.2s ease; margin-top: 20px; }
        .copy-button:hover { background: #dee2e6; }
        .print-button { position: absolute; top: 20px; right: 20px; background: transparent; border: 1px solid #dee2e6; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
        .print-button:hover { background: #e9ecef; color: var(--primary-text); }
        .print-button svg { width: 20px; height: 20px; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 768px) {
            .certificate-content { padding: 20px; }
            h1 { font-size: 1.8rem; }
            #owner { font-size: 1.5rem; }
            .certificate-footer { flex-direction: column; gap: 25px; }
            .status-stamp { font-size: 4.5rem; }
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <div class="status-indicator <?php echo htmlspecialchars($statusClass); ?>"></div>
        <div class="certificate-content">
            <button class="print-button" onclick="window.print()" title="Print Certificate">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"></path></svg>
            </button>
            <div class="certificate-header">
                <img class="header-logo" src="https://files.fivemerr.com/images/2bab2c45-403c-47fd-bc47-83ac531986f1.png" alt="Seal of Los Santos"> 
                <div>
                    <h1>VEHICLE REGISTRATION CERTIFICATE</h1>
                    <p class="subtitle">Government of DJONTOP</p>
                </div>
            </div>

            <p class="intro-text">This is to certify that the vehicle described below is registered under the name of:</p>
            <div class="owner-name-container">
                 <h2 id="owner"><?php echo htmlspecialchars($details['owner'] ?? 'N/A'); ?></h2>
            </div>
            
            <div class="info-section">
                <div class="status-stamp <?php echo htmlspecialchars($statusClass); ?>">
                    <?php echo htmlspecialchars($status); ?>
                </div>

                <div class="detail-item">
                    <span class="label">Plate Number</span>
                    <span class="value"><?php echo htmlspecialchars($details['plate'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Vehicle Model</span>
                    <span class="value"><?php echo htmlspecialchars($details['vehicle'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label">Vehicle Identification Number (VIN)</span>
                    <span class="value"><?php echo htmlspecialchars($details['vin'] ?? 'N/A'); ?></span>
                </div>
            </div>

            <div class="certificate-footer">
                <div class="date-item">
                    <span class="label">Date of Issue</span>
                    <span class="date-value"><?php echo htmlspecialchars($details['issueDate'] ?? 'N/A'); ?></span>
                </div>
                <div id="qrcode-container">
                    <div id="qrcode"></div>
                    <span class="qr-label">Zoom for more</span>
                </div>
                <div class="date-item">
                    <span class="label">Date of Expiry</span>
                    <span class="date-value"><?php echo htmlspecialchars($details['expireDate'] ?? 'N/A'); ?></span>
                </div>
            </div>
            <div class="status-note">
                <p><strong>Status: <?php echo htmlspecialchars($status); ?>.</strong> <?php echo htmlspecialchars($statusNote); ?></p>
            </div>
            
            <div class="developer-footer">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"></path></svg>
                <span>System Developed By <a href="https://tinyurl.com/djontop" target="_blank" rel="noopener noreferrer">DJONTOP As Always</a></span>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="qr-modal">
        <div class="modal-content">
            <div id="qrcode-zoomed"></div>
            <button class="copy-button" id="copy-link-btn">
                <span>Copy Link</span>
            </button>
        </div>
    </div>

    <script>
        const currentUrl = window.location.href;
        <?php if ($details): ?>
            new QRCode(document.getElementById("qrcode"), { text: currentUrl, width: 90, height: 90 });
            new QRCode(document.getElementById("qrcode-zoomed"), { text: currentUrl, width: 500, height: 200 });
        <?php endif; ?>

        const qrContainer = document.getElementById('qrcode-container');
        const modal = document.getElementById('qr-modal');
        const copyBtn = document.getElementById('copy-link-btn');
        const copyTextSpan = copyBtn.querySelector('span');

        qrContainer.addEventListener('click', () => modal.classList.add('active'));
        modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('active'); });
        copyBtn.addEventListener('click', () => {
            navigator.clipboard.writeText(currentUrl).then(() => {
                copyTextSpan.textContent = 'Copied!';
                setTimeout(() => { copyTextSpan.textContent = 'Copy Link'; }, 2000);
            });
        });
    </script>
</body>
</html>
