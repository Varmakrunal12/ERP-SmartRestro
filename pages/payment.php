<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
checkAuth();

$pageTitle = 'Payment';
$currentPage = 'orders';
require_once '../includes/header.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    echo '<div class="card text-center text-muted" style="padding:3rem;"><p>Invalid order ID.</p><a href="orders.php" class="btn btn-primary">Back to Orders</a></div>';
    require_once '../includes/footer.php';
    exit;
}

// Fetch order with table info
$stmtOrder = $pdo->prepare("SELECT o.*, rt.table_number FROM orders o JOIN restaurant_tables rt ON o.table_id = rt.id WHERE o.id = ?");
$stmtOrder->execute([$order_id]);
$order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo '<div class="card text-center text-muted" style="padding:3rem;"><p>Order not found.</p><a href="orders.php" class="btn btn-primary">Back to Orders</a></div>';
    require_once '../includes/footer.php';
    exit;
}

// Check if already paid
if ($order['status'] === 'paid') {
    echo '<div class="card text-center" style="padding:3rem;">
            <div class="empty-icon">✅</div>
            <h3>Payment Already Completed</h3>
            <p class="text-muted">This order has already been paid.</p>
            <a href="bill.php?order_id=' . $order_id . '" class="btn btn-primary">View Receipt</a>
            <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
          </div>';
    require_once '../includes/footer.php';
    exit;
}
?>

<div class="page-header flex-between">
    <h1 class="page-title">💳 Payment</h1>
    <a href="bill.php?order_id=<?php echo $order_id; ?>" class="btn btn-secondary">← Back to Bill</a>
</div>

<!-- Order Summary -->
<div class="card" style="margin-bottom: 1.5rem;">
    <h3>Order Summary</h3>
    <div class="flex-between" style="margin-top: 1rem;">
        <span><strong>Order #:</strong> <?php echo $order['id']; ?></span>
        <span><strong>Table #:</strong> <?php echo htmlspecialchars($order['table_number']); ?></span>
    </div>
    <div class="flex-between" style="margin-top: 0.5rem;">
        <span><strong>Grand Total:</strong></span>
        <span style="font-size: 1.5rem; font-weight: 700; color: var(--primary);">
            ₹<?php echo number_format((float)$order['grand_total'], 2); ?>
        </span>
    </div>
</div>

<!-- Payment Options -->
<h3 style="margin-bottom: 1rem;">Select Payment Method</h3>
<div class="payment-options">
    <div class="payment-card" data-method="cash" onclick="selectPayment('cash')">
        <div class="payment-icon">💵</div>
        <div class="payment-name">Cash</div>
    </div>
    <div class="payment-card" data-method="card" onclick="selectPayment('card')">
        <div class="payment-icon">💳</div>
        <div class="payment-name">Card</div>
    </div>
    <div class="payment-card" data-method="upi" onclick="selectPayment('upi')">
        <div class="payment-icon">📱</div>
        <div class="payment-name">UPI / GPay / Paytm</div>
    </div>
</div>

<!-- Payment Details -->
<div class="payment-details" id="paymentDetails" style="display: none; margin-top: 1.5rem;">
    <!-- Cash details -->
    <div id="cashDetails" class="hidden">
        <div class="card">
            <p class="text-muted">No additional details required for cash payment.</p>
        </div>
    </div>

    <!-- Card details -->
    <div id="cardDetails" class="hidden">
        <div class="card">
            <div class="form-group">
                <label class="form-label" for="cardNumber">Card Number</label>
                <input type="text" class="form-input" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="cardExpiry">Expiry Date</label>
                    <input type="text" class="form-input" id="cardExpiry" placeholder="MM/YY" maxlength="5">
                </div>
                <div class="form-group">
                    <label class="form-label" for="cardCvv">CVV</label>
                    <input type="password" class="form-input" id="cardCvv" placeholder="***" maxlength="4">
                </div>
            </div>
        </div>
    </div>

    <!-- UPI details -->
    <div id="upiDetails" class="hidden">
        <div class="card">
            <div class="form-group">
                <label class="form-label" for="upiId">UPI ID</label>
                <input type="text" class="form-input" id="upiId" placeholder="yourname@paytm or yourname@okaxis">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1rem;">
                <div style="text-align:center; cursor:pointer; padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--border-glass); transition: all 0.3s;" onclick="document.getElementById('upiId').value='user@paytm'" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border-glass)'">
                    <div style="font-size: 1.5rem;">💙</div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary);">Paytm</div>
                </div>
                <div style="text-align:center; cursor:pointer; padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--border-glass); transition: all 0.3s;" onclick="document.getElementById('upiId').value='user@okaxis'" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border-glass)'">
                    <div style="font-size: 1.5rem;">🟢</div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary);">GPay</div>
                </div>
                <div style="text-align:center; cursor:pointer; padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--border-glass); transition: all 0.3s;" onclick="document.getElementById('upiId').value='user@ybl'" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border-glass)'">
                    <div style="font-size: 1.5rem;">🟣</div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary);">PhonePe</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div style="margin-top: 1.5rem;">
    <button class="btn btn-primary btn-lg btn-block" id="payNowBtn" disabled>Pay Now - ₹<?php echo number_format((float)$order['grand_total'], 2); ?></button>
</div>

<!-- Processing Overlay -->
<div class="payment-processing" id="paymentProcessing">
    <div class="payment-spinner"></div>
    <p>Processing Payment...</p>
</div>

<script>
let selectedMethod = null;

function selectPayment(method) {
    selectedMethod = method;
    
    document.querySelectorAll('.payment-card').forEach(card => {
        card.classList.remove('selected');
    });
    document.querySelector(`.payment-card[data-method="${method}"]`).classList.add('selected');
    
    document.getElementById('paymentDetails').style.display = 'block';
    
    document.getElementById('cashDetails').classList.add('hidden');
    document.getElementById('cardDetails').classList.add('hidden');
    document.getElementById('upiDetails').classList.add('hidden');
    
    document.getElementById(method + 'Details').classList.remove('hidden');
    
    document.getElementById('payNowBtn').disabled = false;
}

document.getElementById('payNowBtn').addEventListener('click', async function() {
    if (!selectedMethod) {
        showToast('Please select a payment method', 'error');
        return;
    }

    if (selectedMethod === 'card') {
        const cardNum = document.getElementById('cardNumber').value.trim();
        const expiry = document.getElementById('cardExpiry').value.trim();
        const cvv = document.getElementById('cardCvv').value.trim();
        if (!cardNum || !expiry || !cvv) {
            showToast('Please fill in all card details', 'error');
            return;
        }
    }

    if (selectedMethod === 'upi') {
        const upiId = document.getElementById('upiId').value.trim();
        if (!upiId) {
            showToast('Please enter your UPI ID', 'error');
            return;
        }
    }

    this.disabled = true;

    const processing = document.getElementById('paymentProcessing');
    processing.classList.add('active');

    setTimeout(async () => {
        try {
            const result = await postData('../api/process_payment.php', {
                order_id: <?php echo $order_id; ?>,
                payment_method: selectedMethod
            });

            processing.classList.remove('active');

            if (result.success) {
                // Redirect to success page
                window.location.href = 'payment_success.php?order_id=<?php echo $order_id; ?>&txn=' + result.transaction_id + '&method=' + selectedMethod + '&amount=<?php echo $order['grand_total']; ?>';
            } else {
                showToast(result.message || 'Payment failed', 'error');
                this.disabled = false;
            }
        } catch (err) {
            processing.classList.remove('active');
            showToast('Payment failed. Please try again.', 'error');
            this.disabled = false;
        }
    }, 2000);
});
</script>

<?php require_once '../includes/footer.php'; ?>
