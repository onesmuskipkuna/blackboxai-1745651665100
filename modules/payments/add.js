document.getElementById('student_id').addEventListener('change', function() {
    const studentId = this.value;
    const invoiceContainer = document.getElementById('invoiceContainer');
    const invoiceSelect = document.getElementById('invoice_id');
    const feeItemsContainer = document.getElementById('feeItemsContainer');
    const totalAmount = document.getElementById('totalAmount');

    invoiceSelect.innerHTML = '<option value="">Select Invoice</option>';
    feeItemsContainer.innerHTML = '';
    totalAmount.textContent = '0.00';

    if (!studentId) {
        invoiceContainer.classList.add('hidden');
        return;
    }

    fetch(`get_invoices.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                invoiceContainer.classList.remove('hidden');
                data.forEach(invoice => {
                    const option = document.createElement('option');
                    option.value = invoice.id;
                    option.textContent = `${invoice.invoice_number} - Balance: KES ${parseFloat(invoice.balance).toFixed(2)}`;
                    invoiceSelect.appendChild(option);
                });
            } else {
                invoiceContainer.classList.add('hidden');
            }
        })
        .catch(error => console.error('Error loading invoices:', error));
});

document.getElementById('invoice_id').addEventListener('change', function() {
    const invoiceId = this.value;
    const feeItemsContainer = document.getElementById('feeItemsContainer');
    const totalAmount = document.getElementById('totalAmount');

    feeItemsContainer.innerHTML = '';
    totalAmount.textContent = '0.00';

    if (!invoiceId) return;

    fetch(`get_fee_items.php?invoice_id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            data.forEach(item => {
                const div = document.createElement('div');
                div.className = 'grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-4 border-b pb-4';
                div.innerHTML = `
                    <div class="sm:col-span-1 flex items-center">
                        <input type="checkbox" name="fee_items[][selected]" value="1" class="mr-2 checkbox-select" checked onchange="toggleAmountInput(this)">
                        <label class="block text-sm font-medium text-gray-700">${item.fee_item}</label>
                        <input type="hidden" name="fee_items[][invoice_item_id]" value="${item.id}">
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">Balance: KES ${parseFloat(item.balance).toFixed(2)}</label>
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">Amount to Pay</label>
                        <input type="number" name="fee_items[][amount]" required min="0" max="${item.balance}" step="0.01"
                               class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md fee-amount"
                               onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>
                `;
                feeItemsContainer.appendChild(div);
            });
        })
        .catch(error => console.error('Error loading fee items:', error));
});

function toggleAmountInput(checkbox) {
    const amountInput = checkbox.closest('div').nextElementSibling.nextElementSibling.querySelector('input[type="number"]');
    if (checkbox.checked) {
        amountInput.disabled = false;
        amountInput.required = true;
    } else {
        amountInput.disabled = true;
        amountInput.required = false;
        amountInput.value = '';
        calculateTotal();
    }
}

function calculateTotal() {
    const amounts = document.getElementsByClassName('fee-amount');
    let total = 0;

    for (let amount of amounts) {
        total += parseFloat(amount.value) || 0;
    }

    document.getElementById('totalAmount').textContent = total.toFixed(2);
}

// Form validation
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const feeItems = document.getElementsByClassName('fee-amount');
    if (feeItems.length === 0) {
        e.preventDefault();
        alert('Please select an invoice to load fee items');
        return;
    }

    let total = 0;
    for (let item of feeItems) {
        if (!item.value || parseFloat(item.value) < 0 || parseFloat(item.value) > parseFloat(item.max)) {
            e.preventDefault();
            alert('Invalid payment amount. Amount must be between 0 and the remaining balance.');
            return;
        }
        total += parseFloat(item.value);
    }

    if (total <= 0) {
        e.preventDefault();
        alert('Total payment amount must be greater than 0');
        return;
    }

    const paymentMode = document.getElementById('payment_mode').value;
    const reference = document.getElementById('reference_number').value;

    if ((paymentMode === 'mpesa' || paymentMode === 'bank') && !reference) {
        e.preventDefault();
        alert('Reference number is required for M-Pesa and Bank payments');
        return;
    }
});
