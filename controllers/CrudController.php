<?php
/**
 * Generic, permission-aware admin resource controller.
 *
 * An admin page describes a resource declaratively (table, fields, columns,
 * permissions, department scoping) and calls ->handle(). This controller then
 * provides: list with search / filters / pagination, modal create & edit via
 * AJAX, delete with confirmation, publish toggle, secure uploads, activity
 * logging — with every action authorised on the server.
 *
 * Definition keys (see admin/sermons.php for a full example):
 *   table, title, singular, icon, nav, module
 *   perms      => [view, create, edit, delete, publish]  (string or array = any-of; false = disabled)
 *   select     => SQL selecting from `table` aliased as t (joins allowed)
 *   search     => columns searched with LIKE
 *   filters    => [column => [label, options]]
 *   order      => ORDER BY clause
 *   columns    => list table columns
 *   fields     => form fields
 *   slug_from  => field used to build a unique slug on create
 *   owner      => column set to the creating user's id
 *   scope      => department column for department-scoped users ('id' for the departments table)
 *   toggle     => [column, on, off]  quick publish/unpublish
 *   before_save(array $data, ?array $existing): array   may throw ValidationError
 *   after_save(int $id, array $data, bool $isNew)
 *   view_url(array $row): ?string   public link
 */
class ValidationError extends RuntimeException
{
    public function __construct(public array $errors, string $message = 'Please correct the highlighted fields.')
    {
        parent::__construct($message);
    }
}

class CrudController
{
    private array $d;

    public function __construct(array $definition)
    {
        $this->d = $definition + [
            'module'    => $definition['table'],
            'singular'  => rtrim($definition['title'], 's'),
            'icon'      => 'fa-folder',
            'nav'       => $definition['table'],
            'select'    => 'SELECT t.* FROM `' . $definition['table'] . '` t',
            'search'    => [],
            'filters'   => [],
            'order'     => 't.id DESC',
            'per_page'  => 15,
            'columns'   => [],
            'fields'    => [],
            'scope'     => null,
            'toggle'    => null,
            'owner'     => null,
            'slug_from' => null,
            'intro'     => '',
            'modal_size' => 'modal-lg',
            'tabs'      => [],
        ];
        $this->d['perms'] += ['view' => false, 'create' => false, 'edit' => false, 'delete' => false, 'publish' => false];
    }

    // -----------------------------------------------------------------
    //  Dispatcher
    // -----------------------------------------------------------------
    public function handle(): void
    {
        $this->authorize('view');
        $action = $_POST['action'] ?? $_GET['action'] ?? 'index';

        try {
            match ($action) {
                'get'    => $this->get(),
                'save'   => $this->save(),
                'delete' => $this->delete(),
                'toggle' => $this->toggle(),
                default  => $this->index(),
            };
        } catch (ValidationError $e) {
            json_response(['ok' => false, 'message' => $e->getMessage(), 'errors' => $e->errors], 422);
        } catch (UploadException $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function can(string $ability): bool
    {
        $perm = $this->d['perms'][$ability] ?? false;
        if ($perm === false) {
            return false;
        }
        return is_array($perm) ? can_any($perm) : can($perm);
    }

    private function authorize(string $ability): void
    {
        $perm = $this->d['perms'][$ability] ?? false;
        if ($perm === false) {
            abort(403, 'This action is not available.');
        }
        require_permission($perm);
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            abort(405, 'Method not allowed.');
        }
    }

    // -----------------------------------------------------------------
    //  Department scoping
    // -----------------------------------------------------------------
    private function scoped(): bool
    {
        return $this->d['scope'] !== null && !has_all_department_access();
    }

    private function scopeSql(array &$params): string
    {
        if (!$this->scoped()) {
            return '';
        }
        $ids = user_department_ids() ?: [0];
        array_push($params, ...$ids);
        return ' AND t.`' . $this->d['scope'] . '` IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    }

    private function findScoped(int $id): array
    {
        $params = [$id];
        $row = DB::one($this->d['select'] . ' WHERE t.id = ?' . $this->scopeSql($params), $params);
        if (!$row) {
            abort(404, 'Record not found or outside your department.');
        }
        return $row;
    }

    // -----------------------------------------------------------------
    //  Actions
    // -----------------------------------------------------------------
    private function get(): void
    {
        $row = $this->findScoped(query_int('id'));
        $previews = [];
        foreach ($this->d['fields'] as $name => $f) {
            if (($f['type'] ?? '') === 'file' && !empty($row[$name])) {
                $previews[$name] = ['url' => media_url($row[$name]), 'kind' => $f['upload']];
            }
            if (($f['type'] ?? '') === 'static') {
                $row[$name] = isset($f['render']) ? ($f['render'])($row) : e($row[$name] ?? '');
            }
        }
        json_response(['ok' => true, 'record' => $row, 'previews' => $previews]);
    }

    private function save(): void
    {
        $this->requirePost();
        $id = (int) ($_POST['id'] ?? 0);
        $existing = null;
        if ($id) {
            $this->authorize('edit');
            $existing = $this->findScoped($id);
        } else {
            $this->authorize('create');
        }

        [$data, $newFiles, $replacedFiles] = $this->collect($existing);

        if (isset($this->d['before_save'])) {
            $data = ($this->d['before_save'])($data, $existing);
        }

        if (!$existing && $this->d['slug_from'] && isset($data[$this->d['slug_from']])) {
            $data['slug'] = unique_slug($this->d['table'], $data[$this->d['slug_from']]);
        }
        if (!$existing && $this->d['owner']) {
            $data[$this->d['owner']] = user_id();
        }

        try {
            if ($existing) {
                if ($data) {
                    DB::update($this->d['table'], $data, 'id = ?', [$id]);
                }
            } else {
                $id = DB::insert($this->d['table'], $data);
            }
        } catch (PDOException $e) {
            foreach ($newFiles as $f) {
                Upload::delete($f);
            }
            if ($e->getCode() === '23000') {
                throw new ValidationError([], 'This record conflicts with an existing one (duplicate value).');
            }
            throw $e;
        }
        foreach ($replacedFiles as $f) {
            Upload::delete($f);
        }

        if (isset($this->d['after_save'])) {
            ($this->d['after_save'])($id, $data, !$existing);
        }

        $label = $data['title'] ?? $data['name'] ?? $existing['title'] ?? $existing['name'] ?? ('#' . $id);
        log_activity($existing ? 'update' : 'create', $this->d['module'], ($existing ? 'Updated ' : 'Created ') . strtolower($this->d['singular']) . ': ' . $label);
        json_response(['ok' => true, 'message' => $this->d['singular'] . ($existing ? ' updated successfully.' : ' created successfully.'), 'id' => $id]);
    }

    private function delete(): void
    {
        $this->requirePost();
        $this->authorize('delete');
        $row = $this->findScoped((int) ($_POST['id'] ?? 0));
        if (isset($this->d['before_delete'])) {
            ($this->d['before_delete'])($row);
        }
        DB::delete($this->d['table'], 'id = ?', [$row['id']]);
        foreach ($this->d['fields'] as $name => $f) {
            if (($f['type'] ?? '') === 'file') {
                Upload::delete($row[$name] ?? null);
            }
        }
        log_activity('delete', $this->d['module'], 'Deleted ' . strtolower($this->d['singular']) . ': ' . ($row['title'] ?? $row['name'] ?? '#' . $row['id']));
        json_response(['ok' => true, 'message' => $this->d['singular'] . ' deleted.']);
    }

    private function toggle(): void
    {
        $this->requirePost();
        if (!$this->d['toggle']) {
            abort(404);
        }
        $this->authorize('publish');
        [$col, $on, $off] = $this->d['toggle'];
        $row = $this->findScoped((int) ($_POST['id'] ?? 0));
        $new = (string) $row[$col] === (string) $on ? $off : $on;
        DB::update($this->d['table'], [$col => $new], 'id = ?', [$row['id']]);
        $state = $new === $on ? 'published' : 'unpublished';
        log_activity($state === 'published' ? 'publish' : 'unpublish', $this->d['module'], ucfirst($state) . ' ' . strtolower($this->d['singular']) . ': ' . ($row['title'] ?? $row['name'] ?? '#' . $row['id']));
        json_response(['ok' => true, 'message' => $this->d['singular'] . ' ' . $state . '.']);
    }

    // -----------------------------------------------------------------
    //  Input collection & validation
    // -----------------------------------------------------------------
    private function collect(?array $existing): array
    {
        $data = [];
        $errors = [];
        $newFiles = [];
        $replaced = [];

        foreach ($this->d['fields'] as $name => $f) {
            $type = $f['type'] ?? 'text';
            if ($type === 'static' || !empty($f['readonly']) || (isset($f['perm']) && !can($f['perm']))) {
                continue;
            }
            if (!empty($f['create_only']) && $existing) {
                continue;
            }
            // Status-like fields are only settable by users holding the publish permission.
            if (!empty($f['publish']) && !$this->can('publish')) {
                if (!$existing) {
                    $data[$name] = $f['unpublished'] ?? 'draft';
                }
                continue;
            }

            if ($type === 'file') {
                if (!empty($_POST['remove_' . $name]) && $existing) {
                    $data[$name] = null;
                    $replaced[] = $existing[$name] ?? null;
                }
                try {
                    $path = Upload::fromField($name, $f['upload'], $f['dir'] ?? $this->d['table'], $this->d['module']);
                } catch (UploadException $e) {
                    $errors[$name] = $e->getMessage();
                    continue;
                }
                if ($path) {
                    $data[$name] = $path;
                    $newFiles[] = $path;
                    if ($existing && !empty($existing[$name])) {
                        $replaced[] = $existing[$name];
                    }
                } elseif (!empty($f['required']) && empty($existing[$name])) {
                    $errors[$name] = $f['label'] . ' is required.';
                }
                continue;
            }

            if ($type === 'checkbox') {
                $data[$name] = !empty($_POST[$name]) ? 1 : 0;
                continue;
            }

            $value = $_POST[$name] ?? '';
            $value = is_string($value) ? trim($value) : '';
            $label = $f['label'] ?? $name;

            if ($value === '') {
                if (!empty($f['required'])) {
                    $errors[$name] = "$label is required.";
                }
                $data[$name] = array_key_exists('empty', $f) ? $f['empty'] : null;
                continue;
            }
            if (isset($f['max']) && mb_strlen($value) > $f['max']) {
                $errors[$name] = "$label must be at most {$f['max']} characters.";
            }

            switch ($type) {
                case 'email':
                    if (!valid_email($value)) $errors[$name] = 'Enter a valid email address.';
                    break;
                case 'url':
                    if (!valid_url($value)) $errors[$name] = 'Enter a valid URL starting with http:// or https://';
                    break;
                case 'date':
                    if (!DateTime::createFromFormat('Y-m-d', $value)) $errors[$name] = 'Enter a valid date.';
                    break;
                case 'time':
                    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) $errors[$name] = 'Enter a valid time.';
                    break;
                case 'number':
                    if (!is_numeric($value)) $errors[$name] = "$label must be a number.";
                    break;
                case 'select':
                    $options = $this->options($f);
                    if (!array_key_exists($value, $options)) $errors[$name] = "Choose a valid $label.";
                    break;
                case 'html':
                    $value = sanitize_html($value);
                    break;
            }
            $data[$name] = $value;
        }

        // Department-scoped users must file content under one of their departments.
        if ($this->scoped() && $this->d['scope'] !== 'id') {
            $dept = (int) ($data[$this->d['scope']] ?? $existing[$this->d['scope']] ?? 0);
            if (!in_array($dept, user_department_ids(), true)) {
                $errors[$this->d['scope']] = 'Choose one of your departments.';
            }
        }

        if ($errors) {
            foreach ($newFiles as $p) {
                Upload::delete($p);
            }
            throw new ValidationError($errors);
        }
        return [$data, $newFiles, array_filter($replaced)];
    }

    private function options(array $f): array
    {
        $o = $f['options'] ?? [];
        return is_callable($o) ? $o() : $o;
    }

    // -----------------------------------------------------------------
    //  Listing
    // -----------------------------------------------------------------
    private function listQuery(): array
    {
        $params = [];
        $where = ' WHERE 1' . $this->scopeSql($params);
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '' && $this->d['search']) {
            $where .= ' AND (' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $this->d['search'])) . ')';
            foreach ($this->d['search'] as $_) {
                $params[] = "%$q%";
            }
        }
        foreach ($this->d['filters'] as $col => $filter) {
            $v = $_GET[str_replace('.', '_', $col)] ?? '';
            if (is_string($v) && $v !== '' && array_key_exists($v, $this->options($filter))) {
                $where .= ' AND ' . (str_contains($col, '.') ? $col : "t.`$col`") . ' = ?';
                $params[] = $v;
            }
        }
        return [$where, $params];
    }

    private function index(): void
    {
        [$where, $params] = $this->listQuery();
        $countSql = 'SELECT COUNT(*) FROM (' . $this->d['select'] . $where . ') x';
        $p = paginate((int) DB::value($countSql, $params), $this->d['per_page']);
        $rows = DB::all($this->d['select'] . $where . ' ORDER BY ' . $this->d['order'] . " LIMIT {$p['per_page']} OFFSET {$p['offset']}", $params);

        if (!empty($_GET['partial'])) {
            $this->renderTable($rows, $p);
            return;
        }

        admin_header($this->d['title'], $this->d['nav']);
        $this->renderToolbar();
        echo '<div id="crud-table">';
        $this->renderTable($rows, $p);
        echo '</div>';
        if ($this->can('create') || $this->can('edit')) {
            $this->renderModal();
        }
        admin_footer();
    }

    private function renderToolbar(): void
    {
        $d = $this->d;
        if ($d['tabs']) {
            echo '<div class="tab-bar mb-3">';
            foreach ($d['tabs'] as $label => [$href, $perm]) {
                if ($perm && !can($perm)) {
                    continue;
                }
                $active = basename(parse_url($href, PHP_URL_PATH)) === current_path();
                echo '<a class="tab-link' . ($active ? ' active' : '') . '" href="' . e(url($href)) . '">' . e($label) . '</a>';
            }
            echo '</div>';
        }
        echo '<div class="page-head"><div><h1 class="page-title"><i class="fa-solid ' . e($d['icon']) . '"></i> ' . e($d['title']) . '</h1>';
        if ($d['intro']) {
            echo '<p class="page-sub">' . e($d['intro']) . '</p>';
        }
        echo '</div>';
        if ($this->can('create')) {
            echo '<button type="button" class="btn btn-gold" data-crud-create><i class="fa-solid fa-plus"></i> Add ' . e($d['singular']) . '</button>';
        }
        echo '</div>';
        if ($this->scoped()) {
            echo '<div class="alert alert-info small py-2"><i class="fa-solid fa-circle-info"></i> You are viewing items for your department(s) only.</div>';
        }
        if (isset($d['header_html'])) {
            echo is_callable($d['header_html']) ? ($d['header_html'])() : $d['header_html'];
        }

        if ($d['search'] || $d['filters']) {
            echo '<form class="toolbar card-lite" method="get" role="search">';
            if ($d['search']) {
                echo '<div class="input-icon"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" class="form-control" placeholder="Search ' . e(strtolower($d['title'])) . '…" value="' . e($_GET['q'] ?? '') . '" aria-label="Search"></div>';
            }
            foreach ($d['filters'] as $col => $filter) {
                $key = str_replace('.', '_', $col);
                echo '<select name="' . e($key) . '" class="form-select" aria-label="' . e($filter['label']) . '" onchange="this.form.submit()"><option value="">All ' . e($filter['label']) . '</option>';
                foreach ($this->options($filter) as $val => $text) {
                    echo '<option value="' . e($val) . '"' . ((string) ($_GET[$key] ?? '') === (string) $val ? ' selected' : '') . '>' . e($text) . '</option>';
                }
                echo '</select>';
            }
            echo '<button class="btn btn-navy">Filter</button>';
            if (!empty($_GET['q']) || array_filter(array_intersect_key($_GET, array_flip(array_map(fn($c) => str_replace('.', '_', $c), array_keys($d['filters'])))))) {
                echo '<a class="btn btn-light" href="' . e(strtok($_SERVER['REQUEST_URI'], '?')) . '">Reset</a>';
            }
            echo '</form>';
        }
    }

    private function renderTable(array $rows, array $p): void
    {
        $d = $this->d;
        if (!$rows) {
            echo empty_state($d['icon'], 'No ' . strtolower($d['title']) . ' found', $this->can('create') ? 'Click "Add ' . $d['singular'] . '" to create the first one.' : 'Nothing to show yet.');
            return;
        }
        echo '<div class="table-card"><div class="table-responsive"><table class="table admin-table align-middle"><thead><tr>';
        foreach ($d['columns'] as $c) {
            echo '<th scope="col">' . e($c['label'] ?? '') . '</th>';
        }
        echo '<th scope="col" class="text-end">Actions</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr data-id="' . (int) $row['id'] . '">';
            foreach ($d['columns'] as $c) {
                echo '<td data-label="' . e($c['label'] ?? '') . '">' . $this->cell($row, $c) . '</td>';
            }
            echo '<td class="text-end text-nowrap">' . $this->actions($row) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="table-foot"><span class="text-muted small">Showing ' . count($rows) . ' of ' . $p['total'] . '</span>' . pagination_links($p) . '</div></div>';
    }

    private function cell(array $row, array $c): string
    {
        $v = $row[$c['key']] ?? null;
        $html = match ($c['type'] ?? 'text') {
            'image'    => '<img src="' . e(media_url($v, $c['fallback'] ?? 'assets/images/placeholders/worship.svg')) . '" alt="" class="thumb" loading="lazy">',
            'date'     => e(format_date($v)),
            'datetime' => e($v ? format_date($v, 'M j, Y g:i A') : ''),
            'time'     => e(format_time($v)),
            'badge'    => status_badge((string) $v),
            'bool'     => $v ? '<span class="badge bg-success-subtle text-success-emphasis">Yes</span>' : '<span class="badge bg-light text-muted">No</span>',
            'money'    => e(money((float) $v, $row['currency'] ?? 'USD')),
            'excerpt'  => e(excerpt($v, $c['length'] ?? 70)),
            'icon'     => '<span class="icon-chip"><i class="fa-solid ' . e($v ?: 'fa-circle') . '"></i></span>',
            'custom'   => ($c['render'])($row),
            default    => '<strong class="cell-title">' . e($v) . '</strong>',
        };
        if (!empty($c['sub']) && !empty($row[$c['sub']])) {
            $html .= '<div class="cell-sub">' . e($row[$c['sub']]) . '</div>';
        }
        return $html;
    }

    private function actions(array $row): string
    {
        $out = '';
        if (isset($this->d['view_url']) && ($u = ($this->d['view_url'])($row))) {
            $out .= '<a class="btn-icon" href="' . e($u) . '" target="_blank" rel="noopener" title="View on website" aria-label="View on website"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>';
        }
        if ($this->d['toggle'] && $this->can('publish')) {
            [$col, $on] = $this->d['toggle'];
            $isOn = (string) $row[$col] === (string) $on;
            $out .= '<button type="button" class="btn-icon" data-crud-toggle="' . (int) $row['id'] . '" title="' . ($isOn ? 'Unpublish' : 'Publish') . '" aria-label="' . ($isOn ? 'Unpublish' : 'Publish') . '"><i class="fa-solid ' . ($isOn ? 'fa-eye-slash' : 'fa-eye') . '"></i></button>';
        }
        if ($this->can('edit')) {
            $out .= '<button type="button" class="btn-icon" data-crud-edit="' . (int) $row['id'] . '" title="Edit" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>';
        }
        if ($this->can('delete')) {
            $label = $row['title'] ?? $row['name'] ?? ('#' . $row['id']);
            $out .= '<button type="button" class="btn-icon danger" data-crud-delete="' . (int) $row['id'] . '" data-label="' . e($label) . '" title="Delete" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>';
        }
        return $out;
    }

    // -----------------------------------------------------------------
    //  Modal form
    // -----------------------------------------------------------------
    private function renderModal(): void
    {
        $d = $this->d;
        echo '<div class="modal fade" id="crudModal" tabindex="-1" aria-labelledby="crudModalTitle" aria-hidden="true" data-singular="' . e($d['singular']) . '">'
            . '<div class="modal-dialog ' . e($d['modal_size']) . ' modal-dialog-scrollable modal-fullscreen-sm-down"><form class="modal-content" id="crudForm" enctype="multipart/form-data" novalidate>'
            . '<div class="modal-header"><h2 class="modal-title h5" id="crudModalTitle">' . e($d['singular']) . '</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>'
            . '<div class="modal-body">' . csrf_field() . '<input type="hidden" name="action" value="save"><input type="hidden" name="id" value=""><div class="row g-3">';
        foreach ($d['fields'] as $name => $f) {
            if (isset($f['perm']) && !can($f['perm'])) {
                continue;
            }
            if (!empty($f['publish']) && !$this->can('publish')) {
                echo '<div class="col-12"><div class="alert alert-light border small mb-0"><i class="fa-solid fa-lock"></i> New items are saved as <strong>draft</strong>. A user with publish permission will review and publish.</div></div>';
                continue;
            }
            echo form_field($name, $f + ['options_resolved' => ($f['type'] ?? '') === 'select' ? $this->options($f) : []]);
        }
        echo '</div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>'
            . '<button type="submit" class="btn btn-gold" data-loading-text="Saving…"><i class="fa-solid fa-check"></i> Save</button></div></form></div></div>';
    }
}
