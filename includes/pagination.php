<?php
/**
 * Shared SQL Pagination Helpers & Bootstrap 5.3 UI Renderer
 */

if (!function_exists('get_pagination_params')) {
    /**
     * Read and sanitize ?page= and ?per_page= query parameters.
     */
    function get_pagination_params($default_per_page = 25, $allowed_per_page = [25, 50, 100]) {
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : (int) $default_per_page;
        if (!in_array($per_page, $allowed_per_page, true)) {
            $per_page = (int) $default_per_page;
        }

        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }

        return [
            'page' => $page,
            'per_page' => $per_page,
        ];
    }
}

if (!function_exists('finalize_pagination')) {
    /**
     * Compute total pages, clamped page number, SQL OFFSET, and display range.
     */
    function finalize_pagination($total_records, $page, $per_page) {
        $total_records = max(0, (int) $total_records);
        $per_page = max(1, (int) $per_page);
        $total_pages = $total_records > 0 ? (int) ceil($total_records / $per_page) : 1;
        $page = min(max(1, (int) $page), $total_pages);
        $offset = ($page - 1) * $per_page;

        $start_record = $total_records > 0 ? ($offset + 1) : 0;
        $end_record = $total_records > 0 ? min($total_records, $offset + $per_page) : 0;

        return [
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'page' => $page,
            'per_page' => $per_page,
            'offset' => $offset,
            'start_record' => $start_record,
            'end_record' => $end_record,
        ];
    }
}

if (!function_exists('build_pagination_url')) {
    /**
     * Build a query URL preserving existing GET parameters while applying overrides.
     */
    function build_pagination_url($overrides = []) {
        $params = $_GET;
        foreach ($overrides as $k => $v) {
            if ($v === null || $v === '') {
                unset($params[$k]);
            } else {
                $params[$k] = $v;
            }
        }
        $qs = http_build_query($params);
        return $qs !== '' ? ('?' . $qs) : '?page=1';
    }
}

if (!function_exists('render_pagination_bar')) {
    /**
     * Render a responsive Bootstrap 5.3 pagination footer with range text and page-size selector.
     */
    function render_pagination_bar($pagination, $allowed_per_page = [25, 50, 100]) {
        $total_records = (int) $pagination['total_records'];
        $total_pages = (int) $pagination['total_pages'];
        $page = (int) $pagination['page'];
        $per_page = (int) $pagination['per_page'];
        $start = (int) $pagination['start_record'];
        $end = (int) $pagination['end_record'];

        // Build limited page window
        $window_start = max(1, $page - 2);
        $window_end = min($total_pages, $page + 2);
        ?>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 border-top">
            <div class="d-flex flex-wrap align-items-center gap-3 small text-muted">
                <span>
                    <?php if ($total_records > 0) { ?>
                        Showing <strong class="text-dark"><?php echo $start; ?>&ndash;<?php echo $end; ?></strong> of <strong class="text-dark"><?php echo $total_records; ?></strong>
                    <?php } else { ?>
                        Showing <strong class="text-dark">0</strong> of <strong class="text-dark">0</strong>
                    <?php } ?>
                </span>

                <form method="GET" class="d-inline-flex align-items-center gap-1 m-0">
                    <?php foreach ($_GET as $gk => $gv) {
                        if ($gk === 'per_page' || $gk === 'page' || is_array($gv)) continue;
                    ?>
                        <input type="hidden" name="<?php echo htmlspecialchars((string)$gk, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars((string)$gv, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php } ?>
                    <input type="hidden" name="page" value="1">
                    <label class="text-muted small mb-0" for="perPageSelect_<?php echo $per_page; ?>">Rows:</label>
                    <select name="per_page" id="perPageSelect_<?php echo $per_page; ?>" class="form-select form-select-sm pos-form-control py-0" style="width: auto;" onchange="this.form.submit()">
                        <?php foreach ($allowed_per_page as $opt) { ?>
                            <option value="<?php echo (int)$opt; ?>" <?php if ($per_page === (int)$opt) echo 'selected'; ?>>
                                <?php echo (int)$opt; ?>
                            </option>
                        <?php } ?>
                    </select>
                </form>
            </div>

            <?php if ($total_pages > 1) { ?>
                <nav aria-label="Pagination">
                    <ul class="pagination pagination-sm mb-0 flex-wrap">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($page <= 1) ? '#' : htmlspecialchars(build_pagination_url(['page' => $page - 1]), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Previous" <?php if ($page <= 1) echo 'tabindex="-1" aria-disabled="true"'; ?>>
                                &laquo; Prev
                            </a>
                        </li>

                        <?php if ($window_start > 1) { ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo htmlspecialchars(build_pagination_url(['page' => 1]), ENT_QUOTES, 'UTF-8'); ?>">1</a>
                            </li>
                            <?php if ($window_start > 2) { ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php } ?>
                        <?php } ?>

                        <?php for ($p = $window_start; $p <= $window_end; $p++) { ?>
                            <li class="page-item <?php echo ($p === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars(build_pagination_url(['page' => $p]), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo $p; ?>
                                </a>
                            </li>
                        <?php } ?>

                        <?php if ($window_end < $total_pages) { ?>
                            <?php if ($window_end < $total_pages - 1) { ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                            <?php } ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo htmlspecialchars(build_pagination_url(['page' => $total_pages]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $total_pages; ?></a>
                            </li>
                        <?php } ?>

                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : htmlspecialchars(build_pagination_url(['page' => $page + 1]), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Next" <?php if ($page >= $total_pages) echo 'tabindex="-1" aria-disabled="true"'; ?>>
                                Next &raquo;
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php } ?>
        </div>
        <?php
    }
}
