<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
checkAuth();

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$txn_id = htmlspecialchars($_GET['txn'] ?? 'TXN' . time());
$method = htmlspecialchars($_GET['method'] ?? 'cash');
$amount = floatval($_GET['amount'] ?? 0);

$methodLabels = [
    'cash' => ['name' => 'Cash', 'icon' => '💵', 'color' => '#22c55e'],
    'card' => ['name' => 'Credit/Debit Card', 'icon' => '💳', 'color' => '#3b82f6'],
    'upi'  => ['name' => 'UPI Payment', 'icon' => '📱', 'color' => '#8b5cf6']
];
$mInfo = $methodLabels[$method] ?? $methodLabels['cash'];

// Get order info
$orderInfo = null;
if ($order_id) {
    $stmt = $pdo->prepare("SELECT o.*, rt.table_number FROM orders o JOIN restaurant_tables rt ON o.table_id = rt.id WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $orderInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($orderInfo) {
        $amount = $orderInfo['grand_total'];
    }
    
    // Check if feedback already exists
    $stmtFb = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE order_id = ?");
    $stmtFb->execute([$order_id]);
    $feedbackExists = $stmtFb->fetchColumn() > 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - SmartRestro ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #15110d;
            background-image:
                radial-gradient(ellipse at 30% 30%, rgba(217, 119, 6, 0.12) 0%, transparent 50%),
                radial-gradient(ellipse at 70% 70%, rgba(234, 88, 12, 0.08) 0%, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .success-container {
            text-align: center;
            animation: fadeInUp 0.8s ease;
            padding: 1rem;
            width: 100%;
            max-width: 480px;
        }

        /* Animated Check Circle */
        .check-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #16a34a, #22c55e);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 0 60px rgba(34, 197, 94, 0.4), 0 0 120px rgba(34, 197, 94, 0.15);
            animation: checkPulse 0.6s ease, checkGlow 2s ease-in-out infinite 0.6s;
            position: relative;
        }

        .check-circle::after {
            content: '';
            position: absolute;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 2px solid rgba(34, 197, 94, 0.3);
            animation: ringExpand 1.5s ease-out 0.3s;
            opacity: 0;
        }

        .check-icon {
            color: #fff;
            font-size: 56px;
            font-weight: bold;
            animation: checkDraw 0.5s ease 0.3s both;
        }

        .success-title {
            font-family: 'Outfit', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: #22c55e;
            margin-bottom: 0.5rem;
            animation: fadeInUp 0.8s ease 0.2s both;
        }

        .success-subtitle {
            color: #cdbda6;
            font-size: 1rem;
            margin-bottom: 2rem;
            animation: fadeInUp 0.8s ease 0.3s both;
        }

        /* Amount Card */
        .amount-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            animation: fadeInUp 0.8s ease 0.4s both;
        }

        .amount-label {
            color: #8e7d69;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .amount-value {
            font-family: 'Outfit', sans-serif;
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fbbf24, #d97706);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .method-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 50px;
            padding: 8px 20px;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #fdf6e6;
        }

        /* Details Section */
        .details-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            animation: fadeInUp 0.8s ease 0.5s both;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.9rem;
        }

        .detail-row:last-child { border-bottom: none; }
        .detail-row .label { color: #8e7d69; }
        .detail-row .value { color: #fdf6e6; font-weight: 500; }

        /* Action Buttons */
        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 0.8s ease 0.6s both;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover { transform: translateY(-2px); }

        .btn-primary {
            background: linear-gradient(135deg, #d97706, #b56003);
            color: #fff;
            box-shadow: 0 4px 20px rgba(217, 119, 6, 0.3);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.05);
            color: #cdbda6;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.1);
            color: #fdf6e6;
        }

        /* Confetti particles */
        .confetti {
            position: fixed;
            top: -10px;
            animation: confettiFall linear forwards;
            z-index: -1;
            font-size: 1.5rem;
        }

        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes checkPulse {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
        }

        @keyframes checkGlow {
            0%, 100% { box-shadow: 0 0 60px rgba(34, 197, 94, 0.4); }
            50% { box-shadow: 0 0 80px rgba(34, 197, 94, 0.6), 0 0 120px rgba(34, 197, 94, 0.2); }
        }

        @keyframes checkDraw {
            from { opacity: 0; transform: scale(0.5) rotate(-10deg); }
            to { opacity: 1; transform: scale(1) rotate(0); }
        }

        @keyframes ringExpand {
            0% { transform: scale(0.8); opacity: 1; }
            100% { transform: scale(1.5); opacity: 0; }
        }

        @keyframes confettiFall {
            to { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }

        /* Feedback Card Styles */
        .feedback-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
        }

        .feedback-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.2rem;
            font-weight: 600;
            color: #fdf6e6;
            margin-bottom: 0.5rem;
        }

        /* Interactive Star Rating CSS */
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            gap: 8px;
            margin: 0.5rem 0 1rem;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            font-size: 2.2rem;
            color: rgba(255, 255, 255, 0.15);
            cursor: pointer;
            transition: color 0.15s ease, transform 0.1s ease;
        }

        .star-rating label:active {
            transform: scale(0.9);
        }

        /* Hover & Checked state (fills in stars from right-to-left, which works with reverse-row) */
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #fbbf24; /* Golden yellow */
        }

        .feedback-textarea {
            width: 100%;
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 0.75rem;
            color: #fdf6e6;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            resize: none;
            outline: none;
            transition: border-color 0.2s;
            margin-bottom: 1rem;
        }

        .feedback-textarea:focus {
            border-color: #d97706;
        }

        .feedback-success-msg {
            color: #22c55e;
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 0.5rem;
        }

        @media (max-width: 600px) {
            .amount-value { font-size: 2.2rem; }
            .success-title { font-size: 1.6rem; }
            .actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<div class="success-container">
    <!-- Animated Check -->
    <div class="check-circle">
        <span class="check-icon">✓</span>
    </div>

    <h1 class="success-title">Payment Successful!</h1>
    <p class="success-subtitle">Your payment has been processed successfully</p>

    <!-- Amount -->
    <div class="amount-card">
        <div class="amount-label">Amount Paid</div>
        <div class="amount-value">₹<?php echo number_format($amount, 2); ?></div>
        <div class="method-badge">
            <span><?php echo $mInfo['icon']; ?></span>
            <span><?php echo $mInfo['name']; ?></span>
        </div>
    </div>

    <!-- Transaction Details -->
    <div class="details-card">
        <div class="detail-row">
            <span class="label">Transaction ID</span>
            <span class="value"><?php echo $txn_id; ?></span>
        </div>
        <?php if ($orderInfo): ?>
        <div class="detail-row">
            <span class="label">Order #</span>
            <span class="value"><?php echo $orderInfo['id']; ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Table</span>
            <span class="value">Table <?php echo htmlspecialchars($orderInfo['table_number']); ?></span>
        </div>
        <?php endif; ?>
        <div class="detail-row">
            <span class="label">Date & Time</span>
            <span class="value"><?php echo date('d M Y, h:i A'); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Status</span>
            <span class="value" style="color: #22c55e;">✅ Completed</span>
        </div>
    </div>

    <!-- Customer Feedback & Rating System -->
    <div id="feedbackContainer" class="feedback-card">
        <?php if ($feedbackExists): ?>
            <div class="feedback-success-msg">
                <span>💚</span>
                <span>Thank you! You've already submitted feedback for this order.</span>
            </div>
        <?php else: ?>
            <h3 class="feedback-title">Rate Your Experience</h3>
            <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 0.5rem;">How was your food and service today?</p>
            <form id="feedbackForm">
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                
                <div class="star-rating">
                    <input type="radio" id="star5" name="rating" value="5">
                    <label for="star5" title="5 stars">★</label>
                    <input type="radio" id="star4" name="rating" value="4">
                    <label for="star4" title="4 stars">★</label>
                    <input type="radio" id="star3" name="rating" value="3">
                    <label for="star3" title="3 stars">★</label>
                    <input type="radio" id="star2" name="rating" value="2">
                    <label for="star2" title="2 stars">★</label>
                    <input type="radio" id="star1" name="rating" value="1">
                    <label for="star1" title="1 star">★</label>
                </div>
                
                <textarea class="feedback-textarea" name="comments" rows="3" placeholder="Add any comments or suggestions (optional)..."></textarea>
                
                <button type="submit" class="btn btn-primary btn-block" style="justify-content: center;">Submit Feedback</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="actions">
        <?php if ($order_id): ?>
        <a href="bill.php?order_id=<?php echo $order_id; ?>" class="btn btn-primary">🧾 View Receipt</a>
        <?php endif; ?>
        <a href="../index.php" class="btn btn-secondary">🏠 Back to Dashboard</a>
    </div>
</div>

<script>
// Confetti animation
const emojis = ['🎉', '🎊', '✨', '🌟', '💫', '🎇', '🎆', '🍽️'];
for (let i = 0; i < 30; i++) {
    setTimeout(() => {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.textContent = emojis[Math.floor(Math.random() * emojis.length)];
        confetti.style.left = Math.random() * 100 + 'vw';
        confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
        confetti.style.fontSize = (Math.random() * 1.5 + 0.8) + 'rem';
        document.body.appendChild(confetti);
        setTimeout(() => confetti.remove(), 5000);
    }, Math.random() * 2000);
}

// Feedback Form Submission Handler
const feedbackForm = document.getElementById('feedbackForm');
if (feedbackForm) {
    feedbackForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const ratingSelected = feedbackForm.querySelector('input[name="rating"]:checked');
        if (!ratingSelected) {
            alert('Please select a rating of 1 to 5 stars.');
            return;
        }
        
        const submitBtn = feedbackForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
        
        const formData = new FormData(feedbackForm);
        
        fetch('/api/submit_feedback.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('feedbackContainer').innerHTML = `
                    <div class="feedback-success-msg" style="animation: fadeInUp 0.5s ease;">
                        <span>🎉</span>
                        <span>${data.message}</span>
                    </div>
                `;
            } else {
                alert(data.message || 'Failed to submit feedback.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Feedback';
            }
        })
        .catch(err => {
            console.error('Error submitting feedback:', err);
            alert('Network error. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Feedback';
        });
    });
}
</script>

</body>
</html>
