<?php include_once __DIR__ . '/../layouts/admin_header.php'; ?>

<div class="container-fluid mt-4">
    <?php Flash::display(); ?>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Verify Payment Request</h1>
        <a href="<?= APP_URL . '/admin/payments' ?>" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to List
        </a>
    </div>

    <div class="row">
        <div class="col-xl-5 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-primary">
                    <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-file-invoice-dollar"></i> Customer Receipt</h6>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($payment['receipt_image'])): ?>
                        <?php 
                            // Ensure path logic: APP_URL + /public/ + relative_path_from_db
                            $cleanPath = ltrim($payment['receipt_image'], '/');
                            $fullUrl = APP_URL . '/public/' . $cleanPath; 
                        ?>
                        
                        <a href="<?= $fullUrl ?>" target="_blank">
                            <img src="<?= $fullUrl ?>" 
                                class="img-fluid rounded border shadow-sm" 
                                style="max-height: 500px; width: auto;" 
                                alt="Receipt"
                                onerror="this.onerror=null;this.src='https://placehold.co/400x500?text=Receipt+Not+Found';">
                        </a>
                        
                        <p class="mt-2 text-muted small"><i class="fas fa-search-plus"></i> Click image to enlarge</p>
                        
                        <div class="mt-3">
                            <a href="<?= $fullUrl ?>" download="Receipt_<?= $payment['payment_ref_no'] ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-download"></i> Download Receipt
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="py-5 bg-light border rounded">
                            <i class="fas fa-receipt fa-4x text-gray-300"></i>
                            <p class="mt-3 text-gray-500 italic">No receipt image uploaded.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-7 col-lg-6">
            <div class="card shadow mb-4 border-left-info">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Transaction Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <label class="text-xs font-weight-bold text-uppercase mb-1">Payment Method</label>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span class="badge badge-pill badge-primary">
                                    <i class="fas fa-qrcode"></i> <?= strtoupper($payment['payment_method'] ?? 'N/A') ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-xs font-weight-bold text-uppercase mb-1">Status</label>
                            <div class="h5 mb-0">
                                <?php if ($payment['verified'] === 'pending'): ?>
                                    <span class="badge badge-warning text-dark">Pending Verification</span>
                                <?php elseif ($payment['verified'] === 'approved'): ?>
                                    <span class="badge badge-success">Verified</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Rejected</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <table class="table table-sm table-borderless">
                        <tr><th width="40%">Payment Ref:</th><td><?= $payment['payment_ref_no'] ?></td></tr>
                        <tr><th>Amount Paid:</th><td class="text-success font-weight-bold">RM <?= number_format($payment['amount'], 2) ?></td></tr>
                        <tr><th>Uploaded On:</th><td><?= date('d M Y, h:i A', strtotime($payment['created_at'])) ?></td></tr>
                    </table>

                    <hr>

                    <?php if ($payment['verified'] === 'pending'): ?>
                        <div class="bg-gray-100 p-3 rounded border">
                            <h6 class="font-weight-bold text-dark small">Take Action</h6>
                            <form action="<?= APP_URL ?>/admin/payments/verify/<?= $payment['id'] ?>" method="POST">
                                <div class="form-group">
                                    <textarea name="rejection_reason" class="form-control form-control-sm" rows="2" placeholder="Note/Reason (Optional)"></textarea>
                                </div>
                                <div class="d-flex">
                                    <button type="submit" name="status" value="verified" class="btn btn-success btn-sm flex-fill mr-2" onclick="return confirm('Approve this payment?')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button type="submit" name="status" value="rejected" class="btn btn-danger btn-sm flex-fill" onclick="return confirm('Reject this payment?')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-secondary mb-0">
                            <i class="fas fa-info-circle"></i> Payment was <strong><?= $payment['verified'] ?></strong> 
                            <?php if (!empty($payment['rejection_reason'])): ?>
                                <br><small>Note: <?= htmlspecialchars($payment['rejection_reason']) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow mb-4 border-left-primary">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Related Booking Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 border-right">
                            <label class="text-xs font-weight-bold text-uppercase text-muted mb-0">Customer</label>
                            <p class="font-weight-bold mb-2"><?= htmlspecialchars($payment['full_name']) ?></p>
                            
                            <label class="text-xs font-weight-bold text-uppercase text-muted mb-0">Booking Ref</label>
                            <p class="font-weight-bold text-primary mb-2">#<?= $payment['booking_ref_no'] ?></p>

                            <label class="text-xs font-weight-bold text-uppercase text-muted mb-0">Booking Date</label>
                            <p class="small mb-0"><?= date('d M Y', strtotime($payment['booking_created_at'])) ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-xs font-weight-bold text-uppercase text-muted mb-0">Financial Summary</label>
                            <table class="table table-sm table-borderless mt-1 mb-0">
                                <tr>
                                    <td class="small">Total Bill:</td>
                                    <td class="text-right font-weight-bold">RM <?= number_format($payment['total_amount'], 2) ?></td>
                                </tr>
                                <tr>
                                    <td class="small">Already Paid:</td>
                                    <td class="text-right text-success font-weight-bold">RM <?= number_format($payment['total_paid_to_date'] ?? 0, 2) ?></td>
                                </tr>
                                <tr class="border-top">
                                    <?php 
                                        $alreadyPaid = (float)($payment['total_paid_to_date'] ?? 0);
                                        $thisAmount = (float)$payment['amount'];
                                        $isPending = ($payment['verified'] === 'pending');
                                        $remaining = $payment['total_amount'] - ($isPending ? ($alreadyPaid + $thisAmount) : $alreadyPaid);
                                    ?>
                                    <td class="small font-weight-bold"><?= $isPending ? 'Est. Balance:' : 'Current Balance:' ?></td>
                                    <td class="text-right font-weight-bold <?= $remaining <= 0 ? 'text-success' : 'text-danger' ?>">
                                        RM <?= number_format($remaining, 2) ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="mt-3 text-right">
                         <a href="<?= APP_URL ?>/admin/bookings/view/<?= $payment['booking_id'] ?>" class="btn btn-sm btn-link">View Full Booking <i class="fas fa-chevron-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../layouts/admin_footer.php'; ?>