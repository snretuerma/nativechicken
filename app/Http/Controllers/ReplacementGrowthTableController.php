<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReplacementGrowthTableModel;

class ReplacementGrowthTableController extends Controller
{
    public function addReplacementGrowth(Request $request)
    {
        $replacement_Growth = new ReplacementGrowthTableModel($request->all());
        $replacement_Growth->save();
    }

    public function getReplacementGrowth()
    {
      
          $replacement_Growth = ReplacementGrowthTableModel::paginate(1000);
        
   
        return $replacement_Growth;
    }

    
}

