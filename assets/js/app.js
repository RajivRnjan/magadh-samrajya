/**
 * App.js - Invoice Calculator & Row Builder
 */

document.addEventListener('DOMContentLoaded', () => {
    const itemsBody = document.getElementById('itemsBody');
    const addRowBtn = document.getElementById('addRowBtn');
    const addRowBtnBottom = document.getElementById('addRowBtnBottom');
    const totalDisplay = document.getElementById('totalDisplay');
    const totalAmountInput = document.getElementById('totalAmountInput');
    const amountInWordsInput = document.getElementById('amountInWordsInput');

    if (addRowBtnBottom && addRowBtn) {
        addRowBtnBottom.addEventListener('click', () => {
            addRowBtn.click();
        });
    }

    // Add row template
    const createRowHTML = () => `
        <tr class="item-row">
            <td>
                <input type="date" name="item_date[]" class="input-cell">
            </td>
            <td>
                <input type="date" name="delivery_date[]" class="input-cell">
            </td>
            <td>
                <input type="text" name="c_note[]" class="input-cell" required placeholder="Consignment Note No.">
            </td>
            <td>
                <input type="text" name="destination[]" class="input-cell" required placeholder="e.g. Ranchi">
            </td>
            <td>
                <input type="number" name="packets[]" class="input-cell text-right num-input packets-input" required min="0" placeholder="0">
            </td>
            <td>
                <input type="number" step="any" name="weight[]" class="input-cell text-right num-input weight-input" required min="0" placeholder="0">
            </td>
            <td>
                <input type="number" step="any" name="amount[]" class="input-cell text-right num-input amount-input" required min="0" placeholder="0">
            </td>
            <td class="text-center">
                <button type="button" class="btn-icon btn-icon-delete delete-row-btn" title="Remove Row">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </td>
        </tr>
    `;

    const fuelAmountInput = document.getElementById('fuelAmountInput');
    const fuelPercentageInput = document.getElementById('fuelPercentageInput');
    const taxableDisplay = document.getElementById('taxableDisplay');

    if (fuelAmountInput) {
        fuelAmountInput.addEventListener('input', () => {
            if (fuelPercentageInput) fuelPercentageInput.value = '';
            calculateTotal();
        });
    }

    if (fuelPercentageInput) {
        fuelPercentageInput.addEventListener('input', () => {
            calculateTotal();
        });
    }

    // Calculate sum of all row amounts, CGST, SGST and Grand Total
    const calculateTotal = () => {
        let subtotal = 0.00;
        const amountInputs = document.querySelectorAll('.amount-input');
        
        amountInputs.forEach(input => {
            const val = parseFloat(input.value);
            if (!isNaN(val)) {
                subtotal += val;
            }
        });

        if (fuelPercentageInput && fuelPercentageInput.value !== '') {
            const pct = parseFloat(fuelPercentageInput.value);
            if (!isNaN(pct)) {
                const calculatedFuel = subtotal * (pct / 100);
                if (fuelAmountInput) {
                    fuelAmountInput.value = calculatedFuel.toFixed(2);
                }
            }
        }

        let fuelAmount = 0.00;
        if (fuelAmountInput) {
            const val = parseFloat(fuelAmountInput.value);
            if (!isNaN(val)) {
                fuelAmount = val;
            }
        }

        const taxable = subtotal + fuelAmount;
        const cgst = taxable * 0.09;
        const sgst = taxable * 0.09;
        const grandTotal = taxable + cgst + sgst;

        // Update display fields
        const subtotalDisplay = document.getElementById('subtotalDisplay');
        const cgstDisplay = document.getElementById('cgstDisplay');
        const sgstDisplay = document.getElementById('sgstDisplay');

        if (subtotalDisplay) subtotalDisplay.textContent = '₹' + subtotal.toFixed(2);
        if (taxableDisplay) taxableDisplay.textContent = '₹' + taxable.toFixed(2);
        if (cgstDisplay) cgstDisplay.textContent = '₹' + cgst.toFixed(2);
        if (sgstDisplay) sgstDisplay.textContent = '₹' + sgst.toFixed(2);

        // Update display and hidden input
        if (totalDisplay) totalDisplay.textContent = '₹' + grandTotal.toFixed(2);
        if (totalAmountInput) totalAmountInput.value = grandTotal.toFixed(2);

        // Update amount in words
        if (amountInWordsInput) {
            if (grandTotal > 0) {
                amountInWordsInput.value = 'Rupees ' + numberToIndianWords(grandTotal);
            } else {
                amountInWordsInput.value = '';
            }
        }
    };

    // Indian Numbering System to Words Converter (Lakh/Crore)
    const numberToIndianWords = (num) => {
        const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 
                      'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        
        // Split rupees and paise
        const parts = num.toFixed(2).split('.');
        let rupees = parseInt(parts[0]);
        let paise = parseInt(parts[1]);
        
        const convertHundreds = (n) => {
            let str = '';
            if (n >= 100) {
                str += ones[Math.floor(n / 100)] + ' Hundred ';
                n %= 100;
            }
            if (n > 0) {
                if (str !== '') str += 'and ';
                if (n < 20) {
                    str += ones[n];
                } else {
                    str += tens[Math.floor(n / 10)] + (n % 10 !== 0 ? '-' + ones[n % 10] : '');
                }
            }
            return str.trim();
        };

        if (rupees === 0) return 'Zero';

        let wordStr = '';
        
        // Crores
        if (rupees >= 10000000) {
            wordStr += convertHundreds(Math.floor(rupees / 10000000)) + ' Crore ';
            rupees %= 10000000;
        }
        
        // Lakhs
        if (rupees >= 100000) {
            wordStr += convertHundreds(Math.floor(rupees / 100000)) + ' Lakh ';
            rupees %= 100000;
        }
        
        // Thousands
        if (rupees >= 1000) {
            wordStr += convertHundreds(Math.floor(rupees / 1000)) + ' Thousand ';
            rupees %= 1000;
        }
        
        // Hundreds/Tens/Ones
        if (rupees > 0) {
            wordStr += convertHundreds(rupees);
        }

        wordStr = wordStr.trim();

        // Add paise if applicable
        if (paise > 0) {
            let paiseWord = '';
            if (paise < 20) {
                paiseWord = ones[paise];
            } else {
                paiseWord = tens[Math.floor(paise / 10)] + (paise % 10 !== 0 ? '-' + ones[paise % 10] : '');
            }
            wordStr += ' and ' + paiseWord + ' Paise';
        }

        return wordStr;
    };

    // Add Row Click Action
    if (addRowBtn && itemsBody) {
        addRowBtn.addEventListener('click', () => {
            itemsBody.insertAdjacentHTML('beforeend', createRowHTML());
            
            // Focus on first input of new row
            const rows = itemsBody.querySelectorAll('.item-row');
            const lastRow = rows[rows.length - 1];
            lastRow.querySelector('input').focus();
        });

        // Event Delegation on table body for dynamic actions
        itemsBody.addEventListener('click', (e) => {
            const deleteBtn = e.target.closest('.delete-row-btn');
            if (deleteBtn) {
                const row = deleteBtn.closest('.item-row');
                const allRows = itemsBody.querySelectorAll('.item-row');
                
                if (allRows.length > 1) {
                    row.remove();
                    calculateTotal();
                } else {
                    alert('An invoice must contain at least one item.');
                }
            }
        });

        // Monitor amount changes to trigger recalculation
        itemsBody.addEventListener('input', (e) => {
            if (e.target.classList.contains('amount-input')) {
                calculateTotal();
            }
        });

        // Auto-calculate on tab out or format check
        itemsBody.addEventListener('blur', (e) => {
            if (e.target.classList.contains('num-input')) {
                const val = parseFloat(e.target.value);
                if (!isNaN(val)) {
                    if (e.target.classList.contains('packets-input')) {
                        e.target.value = Math.round(val);
                    }
                }
            }
        }, true);

        // Support keyboard shortcuts for fast billing entries
        itemsBody.addEventListener('keydown', (e) => {
            // Pressing Enter in the last input of a row adds a new row
            if (e.key === 'Enter' && e.target.classList.contains('amount-input')) {
                e.preventDefault();
                const row = e.target.closest('.item-row');
                const nextRow = row.nextElementSibling;
                
                if (!nextRow) {
                    addRowBtn.click();
                } else {
                    // Focus first input of next row
                    nextRow.querySelector('input').focus();
                }
            }
        });
    }

    // Prevent mouse wheel scrolling from changing values of number inputs
    document.addEventListener('wheel', function(e) {
        if (document.activeElement && document.activeElement.type === 'number') {
            e.preventDefault();
        }
    }, { passive: false });

    // Fetch customer GST automatically
    const customerToInput = document.getElementById('customer_to');
    const customerGstInput = document.getElementById('customer_gst');

    if (customerToInput && customerGstInput) {
        let debounceTimer;
        customerToInput.addEventListener('keyup', function() {
            clearTimeout(debounceTimer);
            const val = this.value.trim();
            if (val === '') {
                return;
            }
            debounceTimer = setTimeout(() => {
                fetch('fetch_customer_gst.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ customer_name: val })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.gst) {
                        if (customerGstInput.value.trim() === '') {
                            customerGstInput.value = data.gst;
                        }
                    }
                })
                .catch(err => console.error('Error fetching GST:', err));
            }, 800);
        });
    }

    // Perform an initial calculation if pre-filled data exists (edit mode)
    calculateTotal();
});
