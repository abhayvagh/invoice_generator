<?php

// Require the Dompdf autoloader.
require_once __DIR__ . '/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Function to sanitize input data.
 * Prevents XSS attacks and cleans up user input.
 * @param string $data The input string to sanitize.
 * @return string The sanitized string.
 */
function sanitizeInput($data) {
    return htmlspecialchars(trim($data));
}

/**
 * Function to convert numbers to words for Indian currency format.
 * Handles Crores, Lakhs, Thousands, Hundreds, and Paise.
 * @param float $num The number to convert.
 * @return string The number in words, capitalized, with "ONLY" at the end.
 */
function convertNumberToWords($num) {
    // Ensure 2 decimal places for consistent handling of paise
    $num = number_format($num, 2, ".", "");
    $num_arr = explode(".", $num);
    $wholenum = (int)$num_arr[0];
    $decnum = (int)$num_arr[1];

    $words = [];

    $ones = [
        0 => "", 1 => "ONE", 2 => "TWO", 3 => "THREE", 4 => "FOUR", 5 => "FIVE", 6 => "SIX", 7 => "SEVEN", 8 => "EIGHT", 9 => "NINE", 10 => "TEN",
        11 => "ELEVEN", 12 => "TWELVE", 13 => "THIRTEEN", 14 => "FOURTEEN", 15 => "FIFTEEN", 16 => "SIXTEEN", 17 => "SEVENTEEN", 18 => "EIGHTEEN", 19 => "NINETEEN"
    ];
    $tens = [
        0 => "", 1 => "", 2 => "TWENTY", 3 => "THIRTY", 4 => "FORTY", 5 => "FIFTY", 6 => "SIXTY", 7 => "SEVENTY", 8 => "EIGHTY", 9 => "NINETY"
    ];

    // Helper closure to convert a number segment (e.g., hundreds, tens, ones) to words
    $convertSegment = function($number) use ($ones, $tens) {
        $segmentWords = [];
        if ($number >= 100) {
            $segmentWords[] = $ones[floor($number / 100)] . " HUNDRED";
            $number %= 100;
        }
        if ($number > 0) {
            if ($number < 20) {
                $segmentWords[] = $ones[$number];
            } else {
                $segmentWords[] = $tens[floor($number / 10)];
                if (($number % 10) > 0) {
                    $segmentWords[] = $ones[$number % 10];
                }
            }
        }
        return implode(" ", array_filter($segmentWords)); // Filter out empty strings
    };

    if ($wholenum == 0) {
        $words[] = "ZERO";
    } else {
        if ($wholenum >= 10000000) { // Crores
            $words[] = $convertSegment(floor($wholenum / 10000000)) . " CRORE";
            $wholenum %= 10000000;
        }
        if ($wholenum >= 100000) { // Lakhs
            $words[] = $convertSegment(floor($wholenum / 100000)) . " LAKH";
            $wholenum %= 100000;
        }
        if ($wholenum >= 1000) { // Thousands
            $words[] = $convertSegment(floor($wholenum / 1000)) . " THOUSAND";
            $wholenum %= 1000;
        }
        if ($wholenum > 0) { // Hundreds, Tens, and Ones (remaining part)
            $words[] = $convertSegment($wholenum);
        }
    }

    $rupees_part = trim(implode(" ", array_filter($words)));

    $paise_part = "";
    if ($decnum > 0) {
        $paise_part = $convertSegment($decnum) . " PAISA";
    }

    $final_string = "";
    if (!empty($rupees_part) && $rupees_part !== "ZERO") {
        $final_string = $rupees_part;
    } else if (empty($rupees_part) && $decnum == 0) { // Special case for exactly 0.00
        $final_string = "ZERO";
    }

    if (!empty($paise_part)) {
        if (!empty($final_string) && $final_string !== "ZERO") {
            $final_string .= " AND ";
        }
        $final_string .= $paise_part;
    }

    // Add "ONLY" at the very end
    if (!empty($final_string)) {
        $final_string .= " ONLY";
    } else {
        $final_string = "ZERO ONLY"; // Fallback
    }

    return strtoupper($final_string);
}


// --- Collect Invoice Data from POST ---
$invoice_data = [
    'invoice_no' => sanitizeInput($_POST['invoice_no'] ?? 'N/A'),
    'dated' => sanitizeInput($_POST['dated'] ?? date('d-m-Y')),
    'customer_name' => sanitizeInput($_POST['customer_name'] ?? 'N/A'),
    'customer_address' => sanitizeInput($_POST['customer_address'] ?? 'N/A'),
    'customer_gst' => sanitizeInput($_POST['customer_gst'] ?? ''),
    'customer_pan' => sanitizeInput($_POST['customer_pan'] ?? ''),
    'jobwork_period' => sanitizeInput($_POST['jobwork_period'] ?? ''),
    // Hardcoded company details
    'company_name' => 'BRAHMANI LASER',
    'company_address' => '31-32, SHAKTI NAGAR SOC, 3RD FLOOR, KHODIYAR KRUPA, PEOPLES CHAR RASTA, KATARGAM SURAT-395004',
    'company_contact' => '9825878501',
    'company_gstin' => '24ANBPM4640G1ZJ', // Example GSTIN
    'company_pan' => 'ANBPM4640G',
    'bank_name' => 'BANK OF INDIA',
    'account_no' => '241810110008544',
    'ifsc_code' => 'BKID0002418',
    'items' => []
];

// Initialize calculation variables
$total_discount_amount = 0;
$sub_total_before_discount = 0;
$sub_total_after_discount = 0;

// Process multiple items from the form
if (isset($_POST['process']) && is_array($_POST['process'])) {
    $num_items = count($_POST['process']);
    for ($i = 0; $i < $num_items; $i++) {
        $weight = (float)($_POST['weight'][$i] ?? 0);
        $rate = (float)($_POST['rate'][$i] ?? 0);
        $discount_percent = (float)($_POST['disc'][$i] ?? 0);

        $base_amount = $weight * $rate;
        $sub_total_before_discount += $base_amount;

        $item_discount_amount = ($base_amount * $discount_percent) / 100;
        $total_discount_amount += $item_discount_amount;

        $final_item_amount = $base_amount - $item_discount_amount;
        $sub_total_after_discount += $final_item_amount;

        $invoice_data['items'][] = [
            'process' => sanitizeInput($_POST['process'][$i] ?? ''),
            'weight' => number_format($weight, 2, '.', ''), // Format for display
            'rate' => number_format($rate, 2, '.', ''),   // Format for display
            'disc' => number_format($discount_percent, 2, '.', ''), // Display discount as percentage
            'rate_per' => sanitizeInput($_POST['rate_per'][$i] ?? ''),
            'amount' => number_format($final_item_amount, 2, '.', '') // Calculated final amount
        ];
    }
}

// Calculate final net amount and convert to words
$net_amount = number_format($sub_total_after_discount, 2, '.', '');
$net_amount_words = convertNumberToWords($net_amount);

// --- HTML for the PDF (CSS embedded directly for Dompdf) ---
$html = '
<!DOCTYPE html>
<html>
<head>
    <title>Tax Invoice</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            padding: 0;
            font-size: 8.5px; /* Base font size */
            color: #333; /* Soft black text */
        }
        .invoice-container {
            width: 96%; /* Optimal width */
            margin: 8px auto; /* Small margins */
            border: 0.5px solid #ddd; /* Very light border for the whole document */
            padding: 8px; /* Small internal padding */
            box-sizing: border-box;
            background-color: #fff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }

        /* Header Section */
        .header-table {
            margin-bottom: 10px;
            border-bottom: 0.5px solid #eee; /* Very light separator line */
            padding-bottom: 5px;
        }
        .header-table td {
            padding: 3px;
            vertical-align: top;
        }
        .company-info {
            width: 60%;
        }
        .invoice-info {
            width: 40%;
            text-align: right;
        }
        .company-name {
            font-size: 20px; /* Prominent company name */
            font-weight: bold;
            color: #4a4a4a; /* A bit darker for prominence */
            margin-bottom: 3px;
        }
        .company-address, .company-contact, .company-gstin, .company-pan {
            font-size: 8.5px;
            line-height: 1.3;
            margin: 0;
            color: #666; /* Softer gray for contact details */
        }
        .invoice-info table {
            width: auto;
            margin-left: auto;
            font-size: 9.5px; /* Clear invoice info */
        }
        .invoice-info table td {
            padding: 2px 5px;
            border: none;
        }
        .invoice-info table td:first-child {
            text-align: left;
            font-weight: bold;
            color: #555;
        }
        .invoice-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            color: #4a4a4a;
            padding: 8px 0;
            border-top: 0.5px solid #ddd;
            border-bottom: 0.5px solid #ddd;
            margin-top: 8px;
            margin-bottom: 10px;
            background-color: #fcfcfc; /* Almost white, very subtle hint */
        }

        /* Customer Details Section */
        .details-section {
            border: 0.5px solid #ddd; /* Light border */
            margin-bottom: 10px;
            padding: 8px;
            background-color: #fdfdfd; /* Very subtle background */
        }
        .details-section table {
            border: none;
        }
        .details-section td {
            padding: 2px 0;
            border: none;
            vertical-align: top;
            font-size: 8.5px;
        }
        .details-section td:first-child {
            width: 120px;
            font-weight: bold;
            color: #555;
        }
        .details-section p {
            margin: 0;
            line-height: 1.4;
        }

        /* Item Table Section */
        .item-table {
            border: 0.5px solid #bbb; /* A bit more defined border for the main table */
            margin-bottom: 10px;
        }
        .item-table th, .item-table td {
            border: 0.5px solid #ddd; /* Very light internal grid lines */
            padding: 5px 3px; /* Compact padding */
            text-align: left;
            font-size: 8.5px;
            vertical-align: top;
        }
        .item-table th {
            background-color: #f2f2f2; /* Light header background */
            color: #4a4a4a; /* Soft header text */
            font-size: 9.5px;
            text-align: center;
            font-weight: bold;
        }
        .item-table tbody tr:nth-child(even) {
            background-color: #fafafa; /* Extremely subtle alternating row color */
        }
        .item-table .text-center { text-align: center; }
        .item-table .text-right { text-align: right; }
        .item-table tfoot td {
            font-weight: bold;
            background-color: #f2f2f2; /* Match header for footer summary */
            font-size: 9.5px;
            padding: 6px 3px;
            color: #4a4a4a;
        }

        /* Totals Section */
        .totals-section {
            display: flex;
            width: 149%;
            margin-bottom: 10px;
            align-items: flex-end;
        }
        .amount-words-col {
            flex-grow: 1;
            width: 65%;
            border: 0.5px solid #ddd;
            font-weight: bold;
            font-size: 9.5px;
            padding: 10px;
            margin-right: 10px;
            background-color: #fdfdfd;
        }
        .summary-col {
            width: 35%;
            flex-shrink: 0;
        }
        .summary-table {
            border: 0.5px solid #ddd;
            width: 100%;
        }
        .summary-table td {
            padding: 6px 8px;
            border: none;
            font-size: 9.5px;
        }
        .summary-table tr:first-child td {
            border-bottom: 0.5px solid #eee;
        }
        .summary-table td:first-child { text-align: left; color: #555;}
        .summary-table td:last-child { text-align: right; font-weight: bold; color: #4a4a4a;}
        .summary-table .total-row td {
            background-color: #f2f2f2;
            font-size: 10.5px; /* Total amount slightly larger */
            font-weight: bold;
            border-top: 0.5px solid #bbb; /* Stronger border for total */
            color: #333;
        }

        /* Footer Section */
        .footer-section {
            display: table;
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border-top: 0.5px solid #e0e0e0; /* Solid light line */
            padding-top: 10px;
        }
        .footer-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 3px;
            font-size: 8.5px;
            color: #666;
        }
        .bank-details {
            text-align: left;
            padding-right: 5px;
        }
        .authorised-signatory {
            text-align: right;
            padding-left: 5px;
        }
        .authorised-signatory p:first-of-type {
            font-size: 9.5px;
            font-weight: bold;
            color: #4a4a4a;
            margin-bottom: 25px; /* Space for signature */
        }
        .terms {
            font-size: 7.5px;
            margin-top: 10px;
            text-align: center;
            color: #888; /* Very light for less emphasis */
        }

    </style>
</head>
<body>
    <div class="invoice-container">

        <table class="header-table">
            <tr>
                <td class="company-info">
                    <div class="company-name">' . $invoice_data['company_name'] . '</div>
                    <p class="company-address">' . nl2br($invoice_data['company_address']) . '</p>
                    <p class="company-contact">CONTACT NO. ' . $invoice_data['company_contact'] . '</p>
                    <p class="company-gstin">GSTIN: ' . $invoice_data['company_gstin'] . '</p>
                    <p class="company-pan">PAN NO.: ' . $invoice_data['company_pan'] . '</p>
                </td>
                <td class="invoice-info">
                    <table>
                        <tr><td>INVOICE NO.</td><td><strong>' . $invoice_data['invoice_no'] . '</strong></td></tr>
                        <tr><td>DATE</td><td><strong>' . $invoice_data['dated'] . '</strong></td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="invoice-title">TAX INVOICE</div>

        <div class="details-section">
            <table>
                <tr>
                    <td>Customer Name:</td>
                    <td><p>' . $invoice_data['customer_name'] . '</p></td>
                </tr>
                <tr>
                    <td>Address:</td>
                    <td><p>' . nl2br($invoice_data['customer_address']) . '</p></td>
                </tr>
                <tr>
                    <td>GST No.:</td>
                    <td><p>' . $invoice_data['customer_gst'] . '</p></td>
                </tr>
                <tr>
                    <td>PAN No.:</td>
                    <td><p>' . $invoice_data['customer_pan'] . '</p></td>
                </tr>
                <tr>
                    <td>Payment Mode:</td>
                    <td><p>CREDIT</p></td>
                </tr>
                <tr>
                    <td>Jobwork Period:</td>
                    <td><p>' . $invoice_data['jobwork_period'] . '</p></td>
                </tr>
            </table>
        </div>

        <table class="item-table">
            <thead>
                <tr>
                    <th style="width: 5%;">SR.</th>
                    <th style="width: 30%;">PROCESS</th>
                    <th style="width: 15%;">WGHT.</th>
                    <th style="width: 15%;">RATE</th>
                    <th style="width: 10%;">DISC.(%)</th>
                    <th style="width: 10%;">RATE PER</th>
                    <th style="width: 15%;">AMOUNT</th>
                </tr>
            </thead>
            <tbody>';
                $sr_no = 1;
                foreach ($invoice_data['items'] as $item) {
                    $html .= '
                    <tr>
                        <td class="text-center">' . $sr_no++ . '</td>
                        <td>' . $item['process'] . '</td>
                        <td class="text-right">' . $item['weight'] . '</td>
                        <td class="text-right">' . $item['rate'] . '</td>
                        <td class="text-right">' . $item['disc'] . '</td>
                        <td class="text-center">' . $item['rate_per'] . '</td>
                        <td class="text-right">' . $item['amount'] . '</td>
                    </tr>';
                }
                // Add empty rows to ensure minimum height for template consistency.
                // You can adjust 'target_min_rows' based on your average number of items.
                $target_min_rows = 15; // Aim for at least 15 rows visible for a typical invoice
                $rows_to_add = $target_min_rows - count($invoice_data['items']);
                for ($i = 0; $i < $rows_to_add && $rows_to_add > 0; $i++) {
                    $html .= '<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                }

            $html .= '
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="text-right">TOTAL (AFTER DISCOUNT)</td>
                    <td class="text-right">' . number_format($sub_total_after_discount, 2) . '</td>
                </tr>
            </tfoot>
        </table>

        <div class="totals-section">
            <div class="amount-words-col">
                AMOUNT IN WORDS: <br><strong>' . $net_amount_words . '</strong>
            </div>
            
        </div>

        <table class="footer-section">
            <tr>
                
                <td class="footer-col authorised-signatory">
                    <p>For <strong>' . $invoice_data['company_name'] . '</strong></p>
                    <br><br> <p>Authorised Signatory</p>
                </td>
            </tr>
        </table>
        <p class="terms">This is a Computer Generated Invoice and does not require a physical signature.</p>

    </div>
</body>
</html>';

// Configure Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="invoice_' . str_replace('/', '_', $invoice_data['invoice_no']) . '.pdf"');
echo $dompdf->output();

?>