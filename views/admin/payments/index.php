<?php include_once __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Payments Management</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <div id="dataTable_wrapper" class="dataTables_wrapper dt-bootstrap4">
                    <div class="row">
                        <div class="col-sm-12 col-md-6">
                            <div class="dataTables_length" id="dataTable_length">
                                <label>Show 
                                    <select name="dataTable_length" aria-controls="dataTable" class="custom-select custom-select-sm form-control form-control-sm">
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select> entries
                                </label>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <div id="dataTable_filter" class="dataTables_filter text-right">
                                <form action="" method="GET" class="form-inline d-inline-block">
                                    <label>Search:
                                        <input type="search" name="search" class="form-control form-control-sm" 
                                            placeholder="Ref No, Name..." 
                                            value="<?= htmlspecialchars($search ?? '') ?>">
                                    </label>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- TABLES -->
                    <div class="row">
                        <div class="col-sm-12">
                            <table class="table table-bordered dataTable" id="dataTable" width="100%" cellspacing="0" role="grid" aria-describedby="dataTable_info" style="width: 100%;">
                                <thead>
                                    <tr role="row">
                                        <th>Booking ID</th>
                                        <th>BillPlz ID</th>
                                        <th>Payment Method</th>
                                        <th>Deposit</th>
                                        <th>Balance</th>
                                        <th>Payment Type</th>
                                        <th>Payment Date</th>
                                        <th>Verified</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td class="text-primary">
                                                <strong><?= htmlspecialchars($payment['booking_ref_no']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($payment['billplz_id'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php if (strtolower($payment['payment_method']) === 'qr'): ?>
                                                    <span class="badge badge-light text-dark"><i class="fas fa-qrcode text-primary"></i> QR</span>
                                                <?php elseif (strtolower($payment['payment_method']) === 'fpx'): ?>
                                                    <span class="badge badge-light text-dark"><i class="fas fa-money-bill-wave text-success"></i> FPX</span>
                                                <?php else: ?>
                                                    <span class="text-muted"><?= strtolower($payment['payment_method']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>RM <?= number_format($payment['amount'] ?? 0, 2) ?></td>
                                            <td>RM <?= number_format($payment['balance_after'] ?? 0, 2) ?></td>
                                            <td><?= ucfirst(htmlspecialchars($payment['payment_type'])) ?></td>
                                            <td><?= date('d M Y', strtotime($payment['payment_date'])) ?></td>
                                            <td>
                                                <?php if ($payment['verified'] === 'pending'): ?>
                                                    <span class="badge badge-info animate-pulse">Pending
                                                    </span>
                                                <?php elseif (strtolower($payment['verified']) === 'approved'): ?>
                                                    <span class="badge badge-success text-dark">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger text-dark">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                <a href="<?= APP_URL . '/admin/payments/verify/' . $payment['id'] ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <!-- <a href="<?= APP_URL . '/admin/payments/delete/' . $payment['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">
                                                    <i class="fas fa-trash"></i>
                                                </a> -->
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Pagination -->
                    <div class="row">
                        <div class="col-sm-12 col-md-5">
                            <div class="dataTables_info" id="dataTable_info" role="status" aria-live="polite">
                                Showing <?= $offset + 1 ?> to <?= min($offset + $resultsPerPage, $totalPayments) ?> of <?= $totalPayments ?> entries
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-7">
                            <div class="dataTables_paginate paging_simple_numbers" id="dataTable_paginate">
                                <ul class="pagination">
                                    <li class="page-item <?= $currentPage == 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $currentPage - 1 ?>&search=<?= urlencode($search) ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>

                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?= $currentPage == $totalPages || $totalPages == 0 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $currentPage + 1 ?>&search=<?= urlencode($search) ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/../layouts/admin_footer.php'; ?>