<?php
// PHP function to generate the invoice number
function generateInvoiceNumber() {
    $currentDate = date('d-m-Y'); // e.g., 28-05-2025
    $currentMonthAbbr = date('M', strtotime($currentDate)); // e.g., May
    $currentYearTwoDigit = date('y', strtotime($currentDate)); // e.g., 25

    $counterFile = 'invoice_counter.txt';
    $counter = 1;
    $lastInvoiceDate = '';

    // Check if the counter file exists and read its content
    if (file_exists($counterFile)) {
        $data = file_get_contents($counterFile);
        // Ensure the file is not empty before splitting
        if (!empty($data)) {
            list($lastInvoiceDate, $lastCounter) = explode(',', $data);
        }

        // If it's the same date, increment the counter
        if ($lastInvoiceDate === $currentDate) {
            $counter = (int)$lastCounter + 1;
        }
        // If it's a new day, counter remains 1 (default)
    }

    // Save the new counter and date back to the file
    // Ensure the directory is writable by the web server
    file_put_contents($counterFile, $currentDate . ',' . $counter);

    // Format the invoice number: BL/MONTH-YY-XXX (e.g., BL/MAY-25-001)
    // Use str_pad to ensure the sequential number is always 3 digits (e.g., 001, 010, 100)
    return 'BL/' . strtoupper($currentMonthAbbr) . '-' . $currentYearTwoDigit . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
}

// Generate the invoice number when the page loads
$autoInvoiceNo = generateInvoiceNumber();

// Set today's date for the 'Dated' field (format YYYY-MM-DD for input type="date")
$todayDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Invoice Data Entry</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');

        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }
        .container {
            background-color: #ffffff;
            max-width: 950px; /* Increased max-width for more spacious item table */
            margin: 20px auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e0e0e0;
        }
        h1 {
            text-align: center;
            color: #2c3e50;
            font-size: 2.2em;
            margin-bottom: 25px;
            font-weight: 500;
        }
        h2 {
            color: #34495e;
            font-size: 1.5em;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e74c3c; /* Accent color */
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            font-size: 0.95em;
        }
        input[type="text"],
        input[type="date"],
        input[type="number"], /* Added number type */
        textarea {
            width: calc(100% - 20px); /* Adjust width to account for padding */
            padding: 10px;
            margin-bottom: 18px;
            border: 1px solid #cfd8dc;
            border-radius: 6px;
            font-size: 1em;
            color: #333;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box; /* Include padding in element's total width */
        }
        input[type="text"]:focus,
        input[type="date"]:focus,
        input[type="number"]:focus,
        textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.4);
            outline: none;
        }
        textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* --- New styles for the item table --- */
        .items-table-container {
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            overflow-x: auto; /* Enable horizontal scroll on small screens */
            margin-bottom: 15px;
        }

        #items-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #ecf0f1;
        }
        #items-table th, #items-table td {
            padding: 10px;
            border: 1px solid #cfd8dc;
            text-align: left;
            vertical-align: middle;
            font-size: 0.9em;
        }
        #items-table th {
            background-color: #34495e;
            color: white;
            font-weight: 500;
            white-space: nowrap; /* Prevent headers from wrapping */
        }
        #items-table td input {
            width: 95%; /* Adjust input width within table cells */
            margin-bottom: 0;
            padding: 8px;
        }
        #items-table td .remove-item-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            transition: background-color 0.3s ease;
            white-space: nowrap;
        }
        #items-table td .remove-item-btn:hover {
            background-color: #c0392b;
        }
        /* Fixed widths for columns */
        #items-table th:nth-child(1), #items-table td:nth-child(1) { width: 5%; text-align: center;} /* SR */
        #items-table th:nth-child(2), #items-table td:nth-child(2) { width: 30%; } /* Process */
        #items-table th:nth-child(3), #items-table td:nth-child(3) { width: 15%; } /* Weight */
        #items-table th:nth-child(4), #items-table td:nth-child(4) { width: 15%; } /* Rate */
        #items-table th:nth-child(5), #items-table td:nth-child(5) { width: 10%; } /* Disc */
        #items-table th:nth-child(6), #items-table td:nth-child(6) { width: 10%; } /* Rate Per */
        #items-table th:nth-child(7), #items-table td:nth-child(7) { width: 10%; } /* Remove button column */
        /* --- End new styles for the item table --- */


        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: flex-end; /* Align buttons to the right */
        }
        button {
            padding: 12px 25px;
            background-color: #2ecc71; /* Green for generate */
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
        }
        button:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
        }
        button.add-item-btn {
            background-color: #3498db; /* Blue for add item */
            align-self: flex-start; /* Align add item button to the left */
            margin-top: 10px; /* Space above the add item button */
        }
        button.add-item-btn:hover {
            background-color: #2980b9;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            #items-table th, #items-table td {
                padding: 8px; /* Reduce padding on small screens */
            }
            .button-group {
                flex-direction: column; /* Stack buttons vertically */
            }
            button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Invoice Data Entry</h1>

        <form action="generate_invoice.php" method="POST">
            <h2>Invoice Details</h2>
            <label for="invoice_no">Invoice No.:</label>
            <input type="text" id="invoice_no" name="invoice_no" value="<?php echo $autoInvoiceNo; ?>" required readonly>

            <label for="dated">Dated:</label>
            <input type="date" id="dated" name="dated" value="<?php echo $todayDate; ?>" required>

            <h2>Customer Details</h2>
            <label for="customer_name">Customer Name:</label>
            <input type="text" id="customer_name" name="customer_name" placeholder="Enter Customer Name" required>

            <label for="customer_address">Customer Address:</label>
            <textarea id="customer_address" name="customer_address" rows="4" placeholder="Enter Customer Address" required></textarea>

            <label for="customer_gst">Customer GST No.:</label>
            <input type="text" id="customer_gst" name="customer_gst" placeholder="Enter Customer GST Number (Optional)">

            <label for="customer_pan">Customer PAN No.:</label>
            <input type="text" id="customer_pan" name="customer_pan" placeholder="Enter Customer PAN Number (Optional)">

            <label for="jobwork_period">Jobwork Period:</label>
            <input type="text" id="jobwork_period" name="jobwork_period" placeholder="e.g., Nov 2024">

            <h2>Items</h2>
            <div class="items-table-container">
                <table id="items-table">
                    <thead>
                        <tr>
                            <th>SR.</th>
                            <th>PROCESS</th>
                            <th>WEIGHT</th>
                            <th>RATE</th>
                            <th>DISC.(%)</th>
                            <th>RATE PER</th>
                            <th>ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="sr-no">1</td>
                            <td><input type="text" name="process[]" placeholder="Process/Description" required></td>
                            <td><input type="number" name="weight[]" placeholder="Weight" step="0.01" required></td>
                            <td><input type="number" name="rate[]" placeholder="Rate" step="0.01" required></td>
                            <td><input type="number" name="disc[]" placeholder="Disc (%)" value="0" step="0.01"></td>
                            <td><input type="text" name="rate_per[]" placeholder="Rate Per" value="CRT"></td>
                            <td><button type="button" class="remove-item-btn" onclick="removeItemRow(this)">Remove</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button type="button" class="add-item-btn" onclick="addItemRow()">+ Add Item</button>

            <div class="button-group">
                <button type="submit">Generate PDF Invoice</button>
            </div>
        </form>
    </div>

    <script>
        // Function to update serial numbers
        function updateSerialNumbers() {
            const srCells = document.querySelectorAll('#items-table tbody .sr-no');
            srCells.forEach((cell, index) => {
                cell.textContent = index + 1;
            });
        }

        function addItemRow() {
            const tbody = document.querySelector('#items-table tbody');
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td class="sr-no"></td>
                <td><input type="text" name="process[]" placeholder="Process/Description" required></td>
                <td><input type="number" name="weight[]" placeholder="Weight" step="0.01" required></td>
                <td><input type="number" name="rate[]" placeholder="Rate" step="0.01" required></td>
                <td><input type="number" name="disc[]" placeholder="Disc (%)" value="0" step="0.01"></td>
                <td><input type="text" name="rate_per[]" placeholder="Rate Per" value="CRT"></td>
                <td><button type="button" class="remove-item-btn" onclick="removeItemRow(this)">Remove</button></td>
            `;
            tbody.appendChild(newRow);
            updateSerialNumbers(); // Update serial numbers after adding
        }

        function removeItemRow(button) {
            const row = button.closest('tr');
            const tbody = row.closest('tbody');
            if (tbody.querySelectorAll('tr').length > 1) { // Ensure at least one row remains
                row.remove();
                updateSerialNumbers(); // Update serial numbers after removing
            } else {
                alert("You must have at least one item row.");
            }
        }
    </script>
</body>
</html>