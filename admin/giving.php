<?php
require __DIR__ . '/partials/init.php';

$categories = fn() => array_column(DB::all('SELECT id, name FROM giving_categories ORDER BY sort_order'), 'name', 'id');
$methods = fn() => array_column(DB::all('SELECT id, name FROM giving_methods ORDER BY sort_order'), 'name', 'id');
$currencies = array_combine($c = array_map('trim', explode(',', setting('giving_currencies', 'USD'))), $c);

$summary = function () {
    $month = DB::one("SELECT COALESCE(SUM(amount),0) total, COUNT(*) n FROM giving_transactions WHERE status = 'confirmed' AND currency = 'USD' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $year = DB::value("SELECT COALESCE(SUM(amount),0) FROM giving_transactions WHERE status = 'confirmed' AND currency = 'USD' AND YEAR(created_at) = YEAR(CURDATE())");
    $pending = DB::value("SELECT COUNT(*) FROM giving_transactions WHERE status = 'pending'");
    $cards = [
        ['This Month (USD)', money($month['total']), $month['n'] . ' confirmed gifts', 'fa-calendar', 'navy'],
        ['This Year (USD)', money($year), 'Confirmed giving', 'fa-chart-line', 'green'],
        ['Awaiting Confirmation', (string) $pending, 'Check mobile money / bank records', 'fa-hourglass-half', 'gold'],
    ];
    $html = '<div class="row g-3 mb-3">';
    foreach ($cards as [$label, $value, $sub, $icon, $tone]) {
        $html .= '<div class="col-md-4"><div class="stat-card tone-' . $tone . '"><div class="stat-icon"><i class="fa-solid ' . $icon . '"></i></div><div><div class="stat-label">' . e($label) . '</div><div class="stat-value">' . e($value) . '</div><div class="stat-sub">' . e($sub) . '</div></div></div></div>';
    }
    return $html . '</div>';
};

(new CrudController([
    'table'    => 'giving_transactions',
    'title'    => 'Giving',
    'singular' => 'Gift',
    'icon'     => 'fa-hand-holding-dollar',
    'nav'      => 'giving',
    'module'   => 'giving',
    'intro'    => 'Giving history. Online submissions arrive as "pending" until confirmed against your mobile money or bank records.',
    'tabs'     => require __DIR__ . '/partials/giving_tabs.php',
    'header_html' => $summary,
    'perms'    => ['view' => 'giving.view', 'create' => 'giving.manage', 'edit' => 'giving.manage', 'delete' => 'giving.manage'],
    'select'   => 'SELECT t.*, c.name AS category_name, m.name AS method_name FROM giving_transactions t
                   LEFT JOIN giving_categories c ON c.id = t.category_id LEFT JOIN giving_methods m ON m.id = t.method_id',
    'search'   => ['t.reference', 't.donor_name', 't.email', 't.payment_reference'],
    'filters'  => [
        'status'      => ['label' => 'Statuses', 'options' => GIVING_STATUSES],
        'category_id' => ['label' => 'Giving Types', 'options' => $categories],
        'method_id'   => ['label' => 'Methods', 'options' => $methods],
    ],
    'order'    => 't.created_at DESC',
    'columns'  => [
        ['key' => 'reference', 'label' => 'Reference', 'sub' => 'payment_reference'],
        ['key' => 'donor_name', 'label' => 'Giver', 'type' => 'custom', 'render' => fn($r) => $r['is_anonymous'] ? '<em class="text-muted">Anonymous</em>' : e($r['donor_name'] ?: '—') . '<div class="cell-sub">' . e($r['phone'] ?: $r['email'] ?: '') . '</div>'],
        ['key' => 'category_name', 'label' => 'Type', 'type' => 'excerpt'],
        ['key' => 'method_name', 'label' => 'Method', 'type' => 'excerpt'],
        ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
        ['key' => 'created_at', 'label' => 'Date', 'type' => 'date'],
        ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ],
    'fields'   => [
        'reference'         => ['type' => 'static', 'label' => 'Reference', 'col' => 6],
        'source'            => ['type' => 'static', 'label' => 'Source', 'col' => 6, 'render' => fn($r) => $r['source'] === 'online' ? 'Submitted online by giver' : 'Recorded by staff'],
        'category_id'       => ['label' => 'Giving Type', 'type' => 'select', 'required' => true, 'options' => $categories, 'col' => 6],
        'method_id'         => ['label' => 'Payment Method', 'type' => 'select', 'required' => true, 'options' => $methods, 'col' => 6],
        'amount'            => ['label' => 'Amount', 'type' => 'number', 'required' => true, 'step' => '0.01', 'col' => 6],
        'currency'          => ['label' => 'Currency', 'type' => 'select', 'required' => true, 'options' => $currencies, 'col' => 6, 'default' => array_key_first($currencies)],
        'donor_name'        => ['label' => 'Giver Name', 'max' => 120, 'col' => 6],
        'phone'             => ['label' => 'Phone', 'type' => 'tel', 'max' => 40, 'col' => 6],
        'email'             => ['label' => 'Email', 'type' => 'email', 'max' => 190, 'col' => 6],
        'payment_reference' => ['label' => 'Transaction ID', 'max' => 100, 'col' => 6],
        'is_anonymous'      => ['label' => 'Anonymous', 'type' => 'checkbox', 'check_label' => 'Anonymous gift', 'col' => 6],
        'status'            => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => GIVING_STATUSES, 'col' => 6, 'default' => 'confirmed'],
        'notes'             => ['label' => 'Notes', 'type' => 'textarea', 'rows' => 2],
    ],
    'before_save' => function (array $data, ?array $existing) {
        if ((float) ($data['amount'] ?? 0) <= 0) {
            throw new ValidationError(['amount' => 'Amount must be greater than zero.']);
        }
        if (!$existing) {
            $data['reference'] = Giving::newReference();
            $data['source'] = 'recorded';
            $data['recorded_by'] = user_id();
        }
        if (($data['status'] ?? '') === 'confirmed' && ($existing['status'] ?? '') !== 'confirmed') {
            $data['confirmed_at'] = date('Y-m-d H:i:s');
        }
        return $data;
    },
]))->handle();
