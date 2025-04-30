<?php
function getPagination($total_records, $records_per_page = 10, $current_page = 1) {
    $total_pages = ceil($total_records / $records_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $records_per_page;
    
    // Calculate page range (show 5 pages before and after current page)
    $start_page = max(1, $current_page - 5);
    $end_page = min($total_pages, $current_page + 5);
    
    return [
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'offset' => $offset,
        'limit' => $records_per_page,
        'start_page' => $start_page,
        'end_page' => $end_page,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

function renderPagination($pagination, $base_url) {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }

    $html = '<div class="flex items-center justify-end space-x-2">';
    
    // Previous button
    if ($pagination['has_previous']) {
        $prev_url = $base_url . ($pagination['current_page'] - 1);
        $html .= "<a href=\"$prev_url\" class=\"px-3 py-1 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50\">Previous</a>";
    }
    
    // Page numbers
    for ($i = $pagination['start_page']; $i <= $pagination['end_page']; $i++) {
        if ($i == $pagination['current_page']) {
            $html .= '<span class="px-3 py-1 text-sm text-white bg-blue-600 border border-blue-600 rounded-md">' . $i . '</span>';
        } else {
            $html .= "<a href=\"{$base_url}{$i}\" class=\"px-3 py-1 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50\">" . $i . "</a>";
        }
    }
    
    // Next button
    if ($pagination['has_next']) {
        $next_url = $base_url . ($pagination['current_page'] + 1);
        $html .= "<a href=\"$next_url\" class=\"px-3 py-1 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50\">Next</a>";
    }
    
    $html .= '</div>';
    
    return $html;
}
?>
