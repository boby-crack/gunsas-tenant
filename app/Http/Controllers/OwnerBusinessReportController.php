<?php

namespace App\Http\Controllers;

use App\Services\BusinessInsightsCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerBusinessReportController extends Controller
{
    public function print(Request $request, BusinessInsightsCalculator $calculator): View
    {
        $filters = $request->only([
            'outlet_group',
            'outlet_id',
            'product_category',
            'product_type',
            'durian_variety_id',
            'inventory_item_id',
            'date_from',
            'date_until',
        ]);

        return view('reports.owner-business-report', [
            'insights' => $calculator->calculate($filters, includeOperationalReports: true),
        ]);
    }
}
