<?php

namespace App\Http\Controllers;

use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Generation;
use App\Models\Line;
use App\Models\Family;
use App\Models\Breeder;
use App\Models\BreederInventory;
use App\Models\Pen;
use App\Models\AnimalMovement;
use App\Models\Replacement;
use App\Models\ReplacementInventory;
use App\Models\BreederFeeding;
use App\Models\EggProduction;
use App\Models\HatcheryRecord;
use App\Models\EggQuality;
use App\Models\BrooderGrower;
use App\Models\BrooderGrowerInventory;
use App\Models\PhenoMorpho;
use App\Models\PhenoMorphoValue;
use App\Models\MortalitySale;
use App\Models\Farm;

class BreederController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function addBreederPage()
    {
        return view('chicken.breeder.add_breeder');
    }

    public function getBreederList()
    {
        $inventories = BreederInventory::
        leftJoin('breeders', 'breeder_inventories.breeder_id', 'breeders.id')
        ->leftJoin('families', 'breeders.family_id', 'families.id')
        ->leftJoin('lines', 'families.line_id', 'lines.id')
        ->leftJoin('generations', 'lines.generation_id', 'generations.id')
        ->leftJoin('pens', 'pens.id', 'breeder_inventories.pen_id')
        ->where('total', '>', 0)
        ->where('generations.farm_id', Auth::user()->farm_id)
        ->select('breeder_inventories.*', 'breeders.*','families.*',
        'breeder_inventories.id as inventory_id','breeders.id as breeder_id','families.id as family_id','families.number as family_number',
        'lines.number as line_number', 'generations.number as generation_number', 'pens.number as pen_number')
        ->paginate(4);

        return $inventories;
    }

    public function searchBreederTag ($breeder_tag)
    {
        $inventories = BreederInventory::
            leftJoin('breeders', 'breeder_inventories.breeder_id', 'breeders.id')
            ->leftJoin('families', 'breeders.family_id', 'families.id')
            ->leftJoin('lines', 'families.line_id', 'lines.id')
            ->leftJoin('generations', 'lines.generation_id', 'generations.id')
            ->where('total', '>', 0)
            ->where('generations.farm_id', Auth::user()->farm_id)
            ->where('breeder_inventories.breeder_tag', 'like', '%'.$breeder_tag.'%')
            ->select('breeder_inventories.*', 'breeders.*','families.*',
            'breeder_inventories.id as inventory_id','breeders.id as breeder_id','families.id as family_id','families.number as family_number',
            'lines.number as line_number', 'generations.number as generation_number')
            ->paginate(4);

        return $inventories;
    }

    /**
     * Add Breeders to system either from within or outside the system
     *
     * @param Request   $request  POST request from client about the breeder info
     * 
     * @throws none
     * @return JSON     response
     */ 
    public function addBreeder(Request $request)
    {
        $code = Auth::user()->getFarm()->code;
        $timestamp = Carbon::now()->timestamp;
        $random = random_bytes(1);
        $tag = $code.bin2hex($random).$timestamp;
        $farm = Farm::where('id', Auth::user()->farm_id)->first();
        $generation = Generation::where('id', $request->generation)->first();
        $line = Line::where('id', $request->line)->first();
        if($request->within == true){
            $request->validate([
                'male_family'  => 'required',
                'male_inventory'  => 'required',
                'number_male'  => 'required|numeric',
                'female_family'  => 'required',
                'female_inventory'  => 'required',
                'number_female'  => 'required|numeric',
                'pen_id'  => 'required',
                'date_added'  => 'required|date',
            ]);
            $breeder_pen = Pen::where('id', $request->pen_id)->firstOrFail();
            if($breeder_pen->total_capacity < ($breeder_pen->current_capacity + ($request->number_male + $request->number_female))){
                return response()->json( ['error'=>'Breeder pen capacity is too small for total male and female'] );
            }
            $breeder_pen->current_capacity = $request->number_male + $request->number_female;

            $male_inventory = ReplacementInventory::where('id', $request->male_inventory)->firstOrFail();
            $female_inventory = ReplacementInventory::where('id', $request->female_inventory)->firstOrFail();
            if(($male_inventory->number_male < $request->number_male)|| $female_inventory->number_female < $request->number_female){
                return response()->json( ['error'=>'Replacement inventory has insufficient number of animals'] );
            }
            
            // Update Pens
            $male_replacement_pen = Pen::where('id', $male_inventory->pen_id)->firstOrFail();
            $female_replacement_pen = Pen::where('id', $female_inventory->pen_id)->firstOrFail();
            
            // Animal Movements
            $movement_replacement_male = new AnimalMovement;
            $movement_replacement_male->date = $request->date_added;
            $movement_replacement_male->family_id = $request->male_family;
            $movement_replacement_male->tag = $male_inventory->replacement_tag;
            $movement_replacement_male->previous_pen_id = $male_inventory->pen_id;
            $movement_replacement_male->current_pen_id = $request->pen_id;
            $movement_replacement_male->previous_type = 'replacement';
            $movement_replacement_male->current_type = 'breeder';
            $movement_replacement_male->activity = 'transfer';
            $movement_replacement_male->number_male = $request->number_male;
            $movement_replacement_male->number_female = 0;
            $movement_replacement_male->number_total = $request->number_male;
            $movement_replacement_male->remarks = "within system";

            $movement_replacement_female = new AnimalMovement;
            $movement_replacement_female->date = $request->date_added;
            $movement_replacement_female->family_id = $request->female_family;
            $movement_replacement_female->tag = $female_inventory->replacmement_tag;
            $movement_replacement_female->previous_pen_id = $female_inventory->pen_id;
            $movement_replacement_female->current_pen_id = $request->pen_id;
            $movement_replacement_female->previous_type = 'replacement';
            $movement_replacement_female->current_type = 'breeder';
            $movement_replacement_female->activity = 'transfer';
            $movement_replacement_female->number_male = 0;
            $movement_replacement_female->number_female = $request->number_female;
            $movement_replacement_female->number_total = $request->number_female;
            $movement_replacement_female->remarks = "within system";

            // Update Inventories
            $male_inventory->number_male = $male_inventory->number_male - $request->number_male;
            $male_inventory->total = $male_inventory->total - $request->number_male;
            $female_inventory->number_female = $female_inventory->number_female - $request->number_female;
            $female_inventory->total = $female_inventory->total - $request->number_female;

            // Make breeder record if not in database else skip process
            $breeder_record = Breeder::where('family_id', $request->male_family)->where('female_family_id', $request->female_family)->first();
            $family = Family::where('id', $request->male_family)->first();
            if($breeder_record == null){
                $new_breeder = new Breeder;
                $new_breeder->family_id = $request->male_family;
                $new_breeder->female_family_id = $request->female_family;
                $new_breeder->date_added = $request->date_added;
                $new_breeder->save();

                $new_inventory = new BreederInventory;
                $new_inventory->breeder_id = $new_breeder->id;
                $new_inventory->pen_id = $request->pen_id;
                $new_inventory->breeder_tag = $tag;
                $new_inventory->batching_date = $male_inventory->batching_date;
                $new_inventory->number_male = $request->number_male;
                $new_inventory->number_female = $request->number_female;
                $new_inventory->total = $request->number_male + $request->number_female;
                $new_inventory->last_update = $request->date_added;

                $datecode = Carbon::createFromFormat('Y-m-d', $male_inventory->batching_date);
                $new_inventory->breeder_code = $farm->code.$generation->number.$line->number.$family->number.$datecode->year.$datecode->month.$datecode->day;
                if($request->male_wingband != null){
                    $new_inventory->male_wingbands = $this->convertToArray($request->male_wingband);
                }
                if($request->female_wingband != null){
                    $new_inventory->female_wingbands = $this->convertToArray($request->female_wingband);
                }
                
                $new_inventory->save();
            }else{
                $new_inventory = new BreederInventory;
                $new_inventory->breeder_id = $breeder_record->id;
                $new_inventory->pen_id = $request->pen_id;
                $new_inventory->breeder_tag = $tag;
                $new_inventory->batching_date = $male_inventory->batching_date;
                $new_inventory->number_male = $request->number_male;
                $new_inventory->number_female = $request->number_female;
                $new_inventory->total = $request->number_male + $request->number_female;
                $new_inventory->last_update = $request->date_added;
                $datecode = Carbon::createFromFormat('Y-m-d', $male_inventory->batching_date);
                $new_inventory->breeder_code = $farm->code.$generation->number.$line->number.$family->number.$datecode->year.$datecode->month.$datecode->day;
                if($request->male_wingband != null){
                    $new_inventory->male_wingbands = $this->convertToArray($request->male_wingband);
                }
                if($request->female_wingband != null){
                    $new_inventory->female_wingbands = $this->convertToArray($request->female_wingband);
                }
                $new_inventory->save();
            }

            $movement_replacement_male->save();
            $movement_replacement_female->save();
            $male_replacement_pen->current_capacity = $male_replacement_pen->current_capacity - $request->number_male;
            // $male_replacement_pen->total = $male_replacement_pen->total - $request->number_male;
            $male_replacement_pen->save();
            $female_replacement_pen->current_capacity = $female_replacement_pen->current_capacity - $request->number_female;
            // $female_replacement_pen->total = $female_replacement_pen->total - $request->number_female;
            $female_replacement_pen->save();
            $male_inventory->save();
            $female_inventory->save();
            $breeder_pen->save();

            return response()->json(['status' => 'success', 'message' => 'Breeder added']);
        }else{
            $request->validate([
                'family'  => 'required',
                'number_male'  => 'required|numeric',
                'number_female'  => 'required|numeric',
                'pen_id'  => 'required',
                'date_added'  => 'required|date',
            ]);
            $breeder_pen = Pen::where('id', $request->pen_id)->firstOrFail();
            if($breeder_pen->total_capacity < ($breeder_pen->current_capacity + ($request->number_male + $request->number_female))){
                return response()->json( ['error'=>'Breeder pen capacity is too small for total male and female'] );
            }
            $breeder_pen->current_capacity = $request->number_male + $request->number_female;

            $movement = new AnimalMovement;
            $movement->date = $request->date_added;
            $movement->family_id = $request->family;
            $movement->tag = $tag;
            $movement->previous_pen_id = null;
            $movement->current_pen_id = $request->pen_id;
            $movement->previous_type = null;
            $movement->current_type = 'breeder';
            $movement->activity = 'transfer';
            $movement->number_male = $request->number_male;
            $movement->number_female = $request->number_female;
            $movement->number_total = $request->number_male + $request->number_female;
            $movement->remarks = "outside system";

            $new_breeder = new Breeder;
            $new_breeder->family_id = $request->family;
            $new_breeder->female_family_id = null;
            $new_breeder->date_added = $request->date_added;
            $new_breeder->save();

            $family = Family::where('id', $request->family)->first();

            $new_inventory = new BreederInventory;
            $new_inventory->breeder_id = $new_breeder->id;
            $new_inventory->pen_id = $request->pen_id;
            $new_inventory->breeder_tag = $tag;
            $new_inventory->batching_date = $request->estimated_batching_date;
            $new_inventory->number_male = $request->number_male;
            $new_inventory->number_female = $request->number_female;
            $new_inventory->total = $request->number_male + $request->number_female;
            $new_inventory->last_update = $request->estimated_batching_date;
            $datecode = Carbon::createFromFormat('Y-m-d', $request->estimated_batching_date);
            $new_inventory->breeder_code = $farm->code.$generation->number.$line->number.$family->number.$datecode->year.$datecode->month.$datecode->day;
            if($request->male_wingband != null){
                $new_inventory->male_wingbands = $this->convertToArray($request->male_wingband);
            }
            if($request->female_wingband != null){
                $new_inventory->female_wingbands = $this->convertToArray($request->female_wingband);
            }
            $new_inventory->save();

            $movement->save();
            $breeder_pen->save();
            return response()->json(['status' => 'success', 'message' => 'Breeder added']);
        }
    }

    public function fetchFeedingRecords ($breeder_id)
    {
        $records = BreederFeeding::
        leftjoin('breeder_inventories', 'breeder_inventories.id', 'breeder_feedings.breeder_inventory_id')
        ->select('breeder_inventories.*', 'breeder_feedings.*', 'breeder_feedings.id as feeding_id')
        ->where('breeder_feedings.breeder_inventory_id', $breeder_id)->orderBy('date_collected', 'desc')->paginate(10);
        return $records;
    }

    public function addFeedingRecords (Request $request)
    {
        if($request->multiple){
            $start = new Carbon($request->date_start);
            $end = new Carbon($request->date_end);
            $difference = $start->diffInDays($end);
            for($i = 0; $i <= $difference; $i++) {   
                $day = $start->copy()->addDays($i);
                $record = new BreederFeeding;
                $record->breeder_inventory_id = $request->breeder_id;
                $record->date_collected = $day;
                $record->amount_offered = $request->offered;
                $record->amount_refused = $request->refused;
                $record->remarks = $request->remarks;
                $record->save();
            }
        }else{
            $record = new BreederFeeding;
            $record->breeder_inventory_id = $request->breeder_id;
            $record->date_collected = $request->date_collected;
            $record->amount_offered = $request->offered;
            $record->amount_refused = $request->refused;
            $record->remarks = $request->remarks;
            $record->save();
        }
        
        return response()->json(['status' => 'success', 'message' => 'Feeding record added']);
    }

    public function editFeedingRecords(Request $request)
    {
        $request->validate([
            'feeding_record' => 'required',
            'date_collected' => 'required',
            'feed_offered' => 'required',
            'feed_refused' => 'required',
        ]);

        $record = BreederFeeding::where('id', $request->feeding_record)->first();
        $record->date_collected = $request->date_collected;
        $record->amount_offered = $request->feed_offered;
        $record->amount_refused = $request->feed_refused;
        $record->remarks = $request->remarks;
        $record->save();
        return response()->json(['status' => 'success', 'message' => 'Feeding record edited']);
    }

    public function fetchEggProduction ($breeder_id)
    {
        $eggprod = EggProduction::
        leftjoin('breeder_inventories', 'breeder_inventories.id', 'egg_productions.breeder_inventory_id')
        ->where('egg_productions.breeder_inventory_id', $breeder_id)
        ->select('egg_productions.*', 'breeder_inventories.*', 'breeder_inventories.id as inventory_id', 'egg_productions.id as eggprod_id')
        ->orderBy('date_collected', 'desc')->paginate(10);
        return $eggprod;
    }

    public function addEggProduction(Request $request)
    {
        $request->validate([
            'breeder_id' => 'required',
            'date_added' => 'required',
            'total_eggs_intact' => 'required',
            'total_egg_weight' => 'required',
            'total_broken' => 'required',
            'total_rejects' => 'required',
        ]);
        $inventory = BreederInventory::where('id', $request->breeder_id)->first();
        $eggprod = new EggProduction;
        $eggprod->breeder_inventory_id = $request->breeder_id;
        $eggprod->date_collected = $request->date_added;
        $eggprod->total_eggs_intact = $request->total_eggs_intact;
        $eggprod->total_egg_weight = $request->total_egg_weight;
        $eggprod->total_broken = $request->total_broken;
        $eggprod->total_rejects = $request->total_rejects;
        $eggprod->remarks = $request->remarks;
        $eggprod->female_inventory = $inventory->number_female;
        $eggprod->save();
        return response()->json(['status' => 'success', 'message' => 'Egg production added']);
    }

    public function editEggProduction (Request $request) 
    {
        $inventory = BreederInventory::where('id', $request->inventory_id)->firstOrFail();
        $eggprod = EggProduction::where("id", $request->record_id)->firstOrFail();
        $eggprod->date_collected = $request->date_added;
        $eggprod->total_eggs_intact = $request->total_eggs_intact;
        $eggprod->total_egg_weight = $request->total_egg_weight;
        $eggprod->total_broken = $request->total_broken;
        $eggprod->total_rejects = $request->total_rejects;
        $eggprod->remarks = $request->remarks;
        $eggprod->female_inventory = $inventory->number_female;
        $eggprod->save();
        
        return response()->json(['status' => 'success', 'message' => 'Egg production added']);
    }

    public function deleteEggProduction ($record_id)
    {
        $record = EggProduction::where('id', $record_id)->firstOrFail();
        $record->forceDelete();
        return response()->json(['status' => 'success', 'message' => 'Egg Production record deleted']);
    }

    public function getHatcheryParameter($breeder_inventoy)
    {
        $hatchery_records = HatcheryRecord::where('breeder_inventory_id', $breeder_inventoy)->paginate(10);
        return $hatchery_records;
    }

    public function addHatcheryParameter(Request $request) 
    {
        $request->validate([
            'breeder_inventory_id' => 'required',
            'date_eggs_set' => 'required',
            'number_eggs_set' => 'required',
            'include' => 'required'
        ]);
        $inventory = BreederInventory::where('id', $request->breeder_inventory_id)->firstOrFail();
        $hatchery = new HatcheryRecord;
        $hatchery->breeder_inventory_id = $request->breeder_inventory_id;
        $hatchery->date_eggs_set = $request->date_eggs_set;
        $hatchery->number_eggs_set = $request->number_eggs_set;
        if($inventory->batching_date != null){
            $hatchery->week_of_lay = Carbon::parse($inventory->batching_date)->diffInWeeks(Carbon::parse($request->date_eggs_set));
        }else{
            $hatchery->week_of_lay = null;
        }
        $hatchery->number_fertile = $request->number_fertile;
        $hatchery->number_hatched = $request->number_hatched;
        if($request->number_hatched == 0){
            $hatchery->date_hatched = null;
            $hatchery->batching_date = null;
            $hatchery->save();
            return response()->json(['status' => 'success', 'message' => 'Hatchery record added']);
        }
        $hatchery->date_hatched = $request->date_hatched;
        $hatchery->batching_date = Carbon::createFromFormat('Y-m-d', $request->date_hatched)->subWeeks(Auth::user()->getFarm()->batching_week)->toDateString();
        if($request->include){
            if($request->family != null){
                $brooder_pen = Pen::where('id', $request->broodergrower_pen_id)->firstOrFail();
                if($brooder_pen->total_capacity < ($brooder_pen->current_capacity + $request->number_hatched)){
                    return response()->json( ['error'=>'Brooder pen does not have enough space for the chicks'] );
                }
                
                $family = Family::where('id', $request->family)->firstOrFail();
                $line = Line::where('id', $family->line_id)->firstOrFail();
                $generation = Generation::where('id', $line->generation_id)->firstOrFail();

                $code = Auth::user()->getFarm()->code;
                $timestamp = Carbon::now()->timestamp;
                $tag = $code.$generation->number.$line->number.$family->number.$timestamp;

                $brooder_record = BrooderGrower::where('family_id', $request->family)->first();
                if($brooder_record==null){
                    $new_brooder = new BrooderGrower;
                    $new_brooder->family_id = $request->family;
                    $new_brooder->date_added = $request->date_hatched;
                    $new_brooder->save();

                    $new_brooder_inventory = new BrooderGrowerInventory;
                    $new_brooder_inventory->broodergrower_id = $new_brooder->id;
                    $new_brooder_inventory->pen_id = $request->broodergrower_pen_id;
                    $new_brooder_inventory->broodergrower_tag = $tag;
                    $new_brooder_inventory->batching_date = $hatchery->batching_date;
                    $new_brooder_inventory->number_male = null;
                    $new_brooder_inventory->number_female = null;
                    $new_brooder_inventory->total = $hatchery->number_hatched;
                    $new_brooder_inventory->last_update = $hatchery->date_hatched;
                    $new_brooder_inventory->save();
                }else{
                    $new_brooder_inventory = new BrooderGrowerInventory;
                    $new_brooder_inventory->broodergrower_id = $brooder_record->id;
                    $new_brooder_inventory->pen_id = $request->broodergrower_pen_id;
                    $new_brooder_inventory->broodergrower_tag = $tag;
                    $new_brooder_inventory->batching_date = $hatchery->batching_date;
                    $new_brooder_inventory->number_male = null;
                    $new_brooder_inventory->number_female = null;
                    $new_brooder_inventory->total = $hatchery->number_hatched;
                    $new_brooder_inventory->last_update = $hatchery->date_hatched;
                    $new_brooder_inventory->save();
                }
                $brooder_pen->current_capacity = $brooder_pen->current_capacity + $request->number_hatched;

                $brooder_movement = new AnimalMovement;
                $brooder_movement->date = $hatchery->date_hatched;
                $brooder_movement->family_id = $request->family;
                $brooder_movement->tag = $tag;
                $brooder_movement->previous_pen_id = null;
                $brooder_movement->current_pen_id = $brooder_pen->id;
                $brooder_movement->previous_type = 'egg';
                $brooder_movement->current_type = 'broodersgrowers';
                $brooder_movement->activity = 'transfer';
                $brooder_movement->number_male = null;
                $brooder_movement->number_female = null;
                $brooder_movement->number_total = $request->number_hatched;
                $brooder_movement->remarks = 'within system';
                $brooder_movement->save();

                $brooder_pen->save();
                $hatchery->save();
                return response()->json(['status' => 'success', 'message' => 'Hatchery record added']);
            }else{
                return response()->json( ['error'=>'Generation, Line and Family is required'] );
            }
        }else{
            $hatchery->save();
            return response()->json(['status' => 'success', 'message' => 'Hatchery record added']);
        }
    }

    public function editHatcheryRecord (Request $request)
    {
        $record = HatcheryRecord::where('id', $request->selected_record_id)->firstOrFail();
        $inventory = BreederInventory::where('id', $record->breeder_inventory_id)->firstOrFail();
        if($request->date_hatched != null && $request->number_hatched != null){
            $record->date_eggs_set = $request->date_eggs_set;
            $record->number_eggs_set = $request->number_eggs_set;
            $record->number_fertile = $request->number_fertile;
            $record->number_hatched = $request->number_hatched;
            $record->date_hatched = $request->date_hatched;
            $record->batching_date = Carbon::createFromFormat('Y-m-d', $request->date_hatched)->subWeeks(Auth::user()->getFarm()->batching_week)->toDateString();

            if($inventory->batching_date != null){
                $record->week_of_lay = Carbon::parse($inventory->batching_date)->diffInWeeks(Carbon::parse($request->date_eggs_set));
            }else{
                $record->week_of_lay = null;
            }

            if($request->include) {
                $brooder_pen = Pen::where('id', $request->broodergrower_pen_id)->firstOrFail();
                if($brooder_pen->total_capacity < $brooder_pen->current_capacity + $request->number_hatched){
                    return response()->json( ['error'=>'Brooder pen does not have enough space for the chicks'] );
                }
                $brooder_pen->current_capacity = $brooder_pen->current_capacity + $request->number_hatched;
                
                $family = Family::where('id', $request->family)->firstOrFail();
                $line = Line::where('id', $family->line_id)->firstOrFail();
                $generation = Generation::where('id', $line->generation_id)->firstOrFail();

                $code = Auth::user()->getFarm()->code;
                $timestamp = Carbon::now()->timestamp;
                $tag = $code.$generation->number.$line->number.$family->number.$timestamp;

                $brooder_record = BrooderGrower::where('family_id', $request->family)->first();
                if($brooder_record==null){
                    $new_brooder = new BrooderGrower;
                    $new_brooder->family_id = $request->family;
                    $new_brooder->date_added = $request->date_hatched;
                    $new_brooder->save();

                    $new_brooder_inventory = new BrooderGrowerInventory;
                    $new_brooder_inventory->broodergrower_id = $new_brooder->id;
                    $new_brooder_inventory->pen_id = $request->broodergrower_pen_id;
                    $new_brooder_inventory->broodergrower_tag = $tag;
                    $new_brooder_inventory->batching_date = $record->batching_date;
                    $new_brooder_inventory->number_male = null;
                    $new_brooder_inventory->number_female = null;
                    $new_brooder_inventory->total = $record->number_hatched;
                    $new_brooder_inventory->last_update = $record->date_hatched;
                    $new_brooder_inventory->save();
                }else{
                    $new_brooder_inventory = new BrooderGrowerInventory;
                    $new_brooder_inventory->broodergrower_id = $brooder_record->id;
                    $new_brooder_inventory->pen_id = $request->broodergrower_pen_id;
                    $new_brooder_inventory->broodergrower_tag = $tag;
                    $new_brooder_inventory->batching_date = $record->batching_date;
                    $new_brooder_inventory->number_male = null;
                    $new_brooder_inventory->number_female = null;
                    $new_brooder_inventory->total = $record->number_hatched;
                    $new_brooder_inventory->last_update = $record->date_hatched;
                    $new_brooder_inventory->save();
                }

                $brooder_movement = new AnimalMovement;
                $brooder_movement->date = $record->date_hatched;
                $brooder_movement->family_id = $request->family;
                $brooder_movement->tag = $tag;
                $brooder_movement->previous_pen_id = null;
                $brooder_movement->current_pen_id = $brooder_pen->id;
                $brooder_movement->previous_type = 'egg';
                $brooder_movement->current_type = 'broodersgrowers';
                $brooder_movement->activity = 'transfer';
                $brooder_movement->number_male = null;
                $brooder_movement->number_female = null;
                $brooder_movement->number_total = $request->number_hatched;
                $brooder_movement->remarks = 'within system';
                $brooder_movement->save();

                $brooder_pen->save();
                $record->save();
                return response()->json(['status' => 'success', 'message' => 'Hatchery record updated']);
            }else{
                $record->save();
                return response()->json(['status' => 'success', 'message' => 'Hatchery record added']);
            }
        }else{
            $record->date_eggs_set = $request->date_eggs_set;
            $record->number_eggs_set = $request->number_eggs_set;
            if($inventory->batching_date != null){
                $record->week_of_lay = Carbon::parse($inventory->batching_date)->diffInWeeks(Carbon::parse($request->date_eggs_set));
            }else{
                $record->week_of_lay = null;
            }
            $record->number_fertile = $request->number_fertile;
            $record->number_hatched = $request->number_hatched;
            if($request->date_hatched == null){
                $record->date_hatched = null;
            }
            $record->save();
            return response()->json(['status' => 'success', 'message' => 'Hatchery record updated']);
        }
    }

    public function deleteHatcheryRecord (Request $request)
    {
        $request->validate([
            'delete_record' => 'required'
        ]);
        $hatchery_record = HatcheryRecord::find($request->delete_record);
        if($hatchery_record->number_hatched === 0 || $hatchery_record->number_hatched === null){
            $hatchery_record->forceDelete();
            return response()->json(['status' => 'success', 'message' => 'Hatchery record deleted']);
        }
        else{
            $breeder_inventory = BreederInventory::find($hatchery_record->breeder_inventory_id);
            $breeder = Breeder::find($breeder_inventory->breeder_id);
            $breeder_family = Family::find($breeder->family_id);
            $breeder_line = Line::find($breeder_family->line_id);
            $breeder_generation =  Generation::find($breeder_line->generation_id);
            $brooder_generation = Generation::where('numerical_generation', $breeder_generation->numerical_generation + 1)
                                    ->where('farm_id', Auth::user()->getFarm()->id)->first();
            $brooder_line = Line::where('generation_id', $brooder_generation->id)->where('number', $breeder_line->number)->first();
            $brooder_family = Family::where('line_id', $brooder_line->id)->where('number', $breeder_family->number)->first();
            $brooder = BrooderGrower::where('family_id', $brooder_family->id)->first();
            $brooder_inventory = BrooderGrowerInventory::where('broodergrower_id', $brooder->id)
                                    ->where('batching_date', $hatchery_record->batching_date)
                                    ->where('last_update', $hatchery_record->date_hatched)
                                    ->first();
            $animal_movement = AnimalMovement::where('tag', $brooder_inventory->broodergrower_tag)
                                ->where('date', $hatchery_record->date_hatched)
                                ->where('previous_type', 'egg')
                                ->where('remarks', 'within system')
                                ->first();
            $brooder_pen = Pen::where('id', $brooder_inventory->pen_id)->first();
            $brooder_pen->current_capacity = $brooder_pen->current_capacity - $brooder_inventory->total;
            $brooder_pen->save();
            $animal_movement->forceDelete();
            $brooder_inventory->forceDelete();
            $hatchery_record->forceDelete();
            if(BrooderGrowerInventory::where('broodergrower_id', $brooder->id)->count() === 0){
                $brooder->forceDelete();
            }
            return response()->json(['status' => 'success', 'message' => 'Hatchery Record updated']);
        }
    }

    public function hardDeleteHatcheryRecord(Request $request) 
    {
        $request->validate([
            'delete_record' => 'required'
        ]);
        $record = HatcheryRecord::where('id', $request->delete_record)->first();
        $record->forceDelete();
        return response()->json(['status' => 'success', 'message' => 'Hatchery Record Deleted Successfully']);
    }

    public function hatcheryRecordPage()
    {
        return view('chicken.breeder.hatchery_record');
    }

    public function fetchEggQuality($breeder_inventory)
    {
        $qualities = EggQuality::
        leftJoin('breeder_inventories', 'breeder_inventories.id', 'egg_qualities.breeder_inventory_id')
        ->leftJoin('breeders', 'breeders.id', 'breeder_inventories.breeder_id')
        ->where('egg_qualities.breeder_inventory_id', $breeder_inventory)
        ->select('breeder_inventories.*', 'egg_qualities.*', 'egg_qualities.id as qual_id', 'breeder_inventories.id as inv_id')
        ->orderBy('egg_qualities.date_collected', 'desc')->paginate(10);
        return $qualities;
    }

    public function addEggQuality(Request $request)
    {
        $request->validate([
            'breeder_id' => 'required',
        ]);
        $eggqual = new EggQuality;
        $eggqual->breeder_inventory_id = $request->breeder_id;
        $eggqual->date_collected = $request->date_collected;
        $eggqual->egg_quality_at = $request->egg_quality_at;
        $eggqual->weight = $request->egg_weight;
        $eggqual->color = ucfirst($request->egg_color);
        $eggqual->shape = ucfirst($request->egg_shape);
        $eggqual->length = $request->egg_length;
        $eggqual->width = $request->egg_width;
        $eggqual->albumen_height = $request->albumen_height;
        $eggqual->albumen_weight = $request->albumen_weight;
        $eggqual->yolk_weight = $request->yolk_weight;
        $eggqual->yolk_color = $request->yolk_color;
        $eggqual->shell_weight = $request->shell_weight;
        $eggqual->thickness_top = $request->thickness_top;
        $eggqual->thickness_mid = $request->thickness_mid;
        $eggqual->thickness_bot = $request->thickness_bot;
        $eggqual->save();
        return response()->json(['status' => 'success', 'message' => 'Egg quality added']);
    }

    public function editEggQuality (Request $request) 
    {
        $eggqual = EggQuality::where('id', $request->record_id)->firstOrFail();
        $eggqual->date_collected = $request->date_collected;
        $eggqual->egg_quality_at = $request->egg_quality_at;
        $eggqual->weight = $request->egg_weight;
        $eggqual->color = ucfirst($request->egg_color);
        $eggqual->shape = ucfirst($request->egg_shape);
        $eggqual->length = $request->egg_length;
        $eggqual->width = $request->egg_width;
        $eggqual->albumen_height = $request->albumen_height;
        $eggqual->albumen_weight = $request->albumen_weight;
        $eggqual->yolk_weight = $request->yolk_weight;
        $eggqual->yolk_color = $request->yolk_color;
        $eggqual->shell_weight = $request->shell_weight;
        $eggqual->thickness_top = $request->thickness_top;
        $eggqual->thickness_mid = $request->thickness_mid;
        $eggqual->thickness_bot = $request->thickness_bot;
        $eggqual->save();
        return response()->json(['status' => 'success', 'message' => 'Egg quality edited']);
    }

    public function getPhenoMorphoRecord ($inventory_id)
    {
        $inventories = BreederInventory::leftJoin('pheno_morphos', 'pheno_morphos.breeder_inventory_id', 'breeder_inventories.id')
        ->leftJoin('pheno_morpho_values', 'pheno_morpho_values.id', 'pheno_morphos.values_id')
        ->select('breeder_inventories.*', 'pheno_morphos.*', 'pheno_morpho_values.*', 'breeder_inventories.id as inventory_id',
        'pheno_morphos.id as phenomorpho_id', 'pheno_morpho_values.id as values_id')
        ->where('pheno_morphos.breeder_inventory_id', $inventory_id)
        ->orderBy('pheno_morpho_values.date_collected', 'desc')
        ->paginate(10);
        return $inventories;
    }

    public function addPhenoMorphoRecords (Request $request)
    {
        if($request->duck){
            $request->validate([
                'breeder_inventory_id' => 'required',
                'tag' => 'required',
                'date_collected' => 'required',
                'gender' => 'required',
                'plummage_color' => 'required',
                'plummage_pattern' => 'required',
                'neck_feather' => 'required',
                'wing_feather' => 'required',
                'tail_feather' => 'required',
                'bill_color' => 'required',
                'bill_shape' => 'required',
                'bean_color' => 'required',
                'crest' => 'required',
                'eye_color' => 'required',
                'body_carriage' => 'required',
                'shank_color' => 'required',
                'skin_color' => 'required',
                'height' => 'required',
                'weight' => 'required',
                'body_length' => 'required',
                'chest_circumference' => 'required',
                'wing_span' => 'required',
                'shank_length' => 'required',
                'bill_length'  => 'required',
                'neck_length'  => 'required',
                ]);

                $pheno = collect([
                    $request->plummage_color,
                    $request->plummage_pattern,
                    $request->neck_feather,
                    $request->wing_feather,
                    $request->tail_feather,
                    $request->bill_color,
                    $request->bill_shape,
                    $request->bean_color,
                    $request->crest,
                    $request->eye_color,
                    $request->body_carriage,
                    $request->shank_color,
                    $request->skin_color
                ]);
                $morpho = collect([
                    $request->height,
                    $request->weight,
                    $request->body_length,
                    $request->chest_circumference,
                    $request->wing_span,
                    $request->shank_length,
                    $request->bill_length,
                    $request->neck_length
                ]);
        }else{
            $request->validate([
                'breeder_inventory_id' => 'required',
                'tag' => 'required',
                'date_collected' => 'required',
                'gender' => 'required',
                'plummage_color' => 'required',
                'plummage_pattern' => 'required',
                'hackle_color' => 'required',
                'hackle_pattern' => 'required',
                'body_carriage' => 'required',
                'comb_type' => 'required',
                'comb_color' => 'required',
                'earlobe_color' => 'required',
                'iris_color' => 'required',
                'beak_color' => 'required',
                'shank_color' => 'required',
                'skin_color' => 'required',
                'height' => 'required',
                'weight' => 'required',
                'body_length' => 'required',
                'chest_circumference' => 'required',
                'wing_span' => 'required',
                'shank_length' => 'required',
            ]);

            $pheno = collect([
                $request->plummage_color,
                $request->plummage_pattern,
                $request->hackle_color,
                $request->hackle_pattern,
                $request->body_carriage,
                $request->comb_type,
                $request->comb_color,
                $request->earlobe_color,
                $request->iris_color,
                $request->beak_color,
                $request->shank_color,
                $request->skin_color
            ]);
            $morpho = collect([
                $request->height,
                $request->weight,
                $request->body_length,
                $request->chest_circumference,
                $request->wing_span,
                $request->shank_length
            ]);
        }

        $phenomorphovalues = new PhenoMorphoValue;
        $phenomorphovalues->tag = $request->tag;
        $phenomorphovalues->gender = $request->gender;
        $phenomorphovalues->phenotypic = $pheno;
        $phenomorphovalues->morphometric = $morpho;
        $phenomorphovalues->date_collected = $request->date_collected;
        $phenomorphovalues->save();

        $phenomorpho = new PhenoMorpho;
        $phenomorpho->breeder_inventory_id = $request->breeder_inventory_id;
        $phenomorpho->values_id = $phenomorphovalues->id;
        $phenomorpho->save();

        return response()->json(['status' => 'success', 'message' => 'Phenotypic and Morphometric values saved']);
    }

    public function editPhenoRecord (Request $request) 
    {
        if($request->animal_type === 1){
            $pheno = collect([
                $request->plummage_color,
                $request->plummage_pattern,
                $request->hackle_color,
                $request->hackle_pattern,
                $request->body_carriage,
                $request->comb_type,
                $request->comb_color,
                $request->earlobe_color,
                $request->iris_color,
                $request->beak_color,
                $request->shank_color,
                $request->skin_color
            ]);
        }else if($request->animal_type === 2){
            $pheno = collect([
                $request->plummage_color,
                $request->plummage_pattern,
                $request->neck_feather,
                $request->wing_feather,
                $request->tail_feather,
                $request->bill_color,
                $request->bill_shape,
                $request->bean_color,
                $request->crest,
                $request->eye_color,
                $request->body_carriage,
                $request->shank_color,
                $request->skin_color
            ]);
        }
        try {
            $record = PhenoMorphoValue::where('id', $request->record_id)->firstOrFail();
        }catch(Exception $exception){
            return back()->withError($exception->getMessage());
        }
        $record->phenotypic = $pheno;
        $record->save();
        return response()->json(['status' => 'success', 'message' => 'Phenotypic values edited, please refresh page to update tooltip data']);
    }

    public function editMorphoRecord (Request $request) 
    {
        if($request->animal_type === 1){
            $morpho = collect([
                $request->height,
                $request->weight,
                $request->body_length,
                $request->chest_circumference,
                $request->wing_span,
                $request->shank_length
            ]);
        }else if($request->animal_type === 2){
            $morpho = collect([
                $request->height,
                $request->weight,
                $request->body_length,
                $request->chest_circumference,
                $request->wing_span,
                $request->shank_length,
                $request->bill_length,
                $request->neck_length
            ]);
        }
        try {
            $record = PhenoMorphoValue::where('id', $request->record_id)->firstOrFail();
        }catch(Exception $exception){
            return back()->withError($exception->getMessage());
        }
        $record->morphometric = $morpho;
        $record->save();
        return response()->json(['status' => 'success', 'message' => 'Morphometric values edited, please refresh page to update tooltip data']);
    }

    public function getMortalitySale ($inventory_id)
    {
        $record = MortalitySale::where('breeder_inventory_id', $inventory_id)
        ->orderBy('date', 'desc')
        ->paginate(10);
        return $record;
    }

    public function addMortality (Request $request)
    {
        $breeder_inventory = BreederInventory::where('id', $request->breeder_id)->firstOrFail();
        if($request->male > $breeder_inventory->number_male || $request->female > $breeder_inventory->number_female){
            return response()->json( ['error'=>'Input quantity is too large for the inventory'] );
        }

        $breeder_pen = Pen::where('id', $breeder_inventory->pen_id)->firstOrFail();
        $breeder_inventory->number_male = $breeder_inventory->number_male - $request->male;
        $breeder_inventory->number_female = $breeder_inventory->number_female - $request->female;
        $breeder_inventory->total = $breeder_inventory->total - ($request->male + $request->female);

        $breeder_pen->current_capacity = $breeder_pen->current_capacity - ($request->male + $request->female);

        $movement = new AnimalMovement;
        $movement->date = $request->date;
        $movement->family_id = $breeder_inventory->getBreederData()->family_id;
        $movement->tag = $breeder_inventory->tag;
        $movement->previous_pen_id = $breeder_pen->id;
        $movement->current_pen_id = null;
        $movement->previous_type = "breeder";
        $movement->current_type = "breeder";
        $movement->activity = "mortality";
        $movement->number_male = $request->male;
        $movement->number_female = $request->female;
        $movement->number_total = $request->male + $request->female;
        $movement->remarks = $request->remarks;

        $mortality = new MortalitySale;
        $mortality->date = $request->date;
        $mortality->breeder_inventory_id = $request->breeder_id;
        $mortality->type = "breeder";
        $mortality->category = "died";
        $mortality->male = $request->male;
        $mortality->female = $request->female;
        $mortality->total = $request->male + $request->female;
        $mortality->reason = $request->reason;
        $mortality->remarks = $request->remarks;

        $breeder_inventory->save();
        $breeder_pen->save();
        $movement->save();
        $mortality->save();
        return response()->json(['status' => 'success', 'message' => 'Breeder mortality recorded']);
    }

    public function addSale (Request $request)
    {
        $breeder_inventory = BreederInventory::where('id', $request->breeder_id)->firstOrFail();
        if($request->male > $breeder_inventory->number_male || $request->female > $breeder_inventory->number_female){
            return response()->json( ['error'=>'Input quantity is too large for the inventory'] );
        }

        $breeder_pen = Pen::where('id', $breeder_inventory->pen_id)->firstOrFail();
        $breeder_inventory->number_male = $breeder_inventory->number_male - $request->male;
        $breeder_inventory->number_female = $breeder_inventory->number_female - $request->female;
        $breeder_inventory->total = $breeder_inventory->total - ($request->male + $request->female);

        $breeder_pen->current_capacity = $breeder_pen->current_capacity - ($request->male + $request->female);

        $movement = new AnimalMovement;
        $movement->date = $request->date;
        $movement->family_id = $breeder_inventory->getBreederData()->family_id;
        $movement->tag = $breeder_inventory->tag;
        $movement->previous_pen_id = $breeder_pen->id;
        $movement->current_pen_id = null;
        $movement->previous_type = "breeder";
        $movement->current_type = "breeder";
        $movement->activity = "sale";
        $movement->number_male = $request->male;
        $movement->number_female = $request->female;
        $movement->number_total = $request->male + $request->female;
        $movement->remarks = $request->remarks;

        $sales = new MortalitySale;
        $sales->date = $request->date;
        $sales->breeder_inventory_id = $request->breeder_id;
        $sales->type = "breeder";
        $sales->category = "sold";
        $sales->male = $request->male;
        $sales->female = $request->female;
        $sales->total = $request->male + $request->female;
        $sales->price = $request->price;
        $sales->remarks = $request->remarks;

        $breeder_inventory->save();
        $breeder_pen->save();
        $movement->save();
        $sales->save();
        return response()->json(['status' => 'success', 'message' => 'Breeder sales recorded']);
    }

    public function addEggSale (Request $request)
    {
        $record = new MortalitySale;
        $record->breeder_inventory_id = $request->breeder_id;
        $record->type = "egg";
        $record->category = "sold";
        $record->total = $request->eggs;
        $record->price = $request->price;
        $record->remarks = $request->remarks;
        $record->date = $request->date;
        $record->save();
        return response()->json(['status' => 'success', 'message' => 'Egg sales added']);
    }

    public function cullBreeder ($inventory_id)
    {
        $now = Carbon::now();
        $inventory = BreederInventory::where('id', $inventory_id)->firstOrFail();
        $pen = Pen::where('id', $inventory->pen_id)->firstOrFail();
        $pen->current_capacity = 0;

        $movement = new AnimalMovement;
        $movement->date = $now->toDateString();
        $movement->family_id = $inventory->getBreederData()->family_id;
        $movement->tag = $inventory->breeder_tag;
        $movement->previous_pen_id = $pen->id;
        $movement->current_pen_id = null;
        $movement->previous_type = "breeder";
        $movement->current_type = "breeder";
        $movement->activity = "cull";
        $movement->number_male = $inventory->number_male;
        $movement->number_female = $inventory->number_female;
        $movement->number_total = $inventory->number_male + $inventory->number_female;

        $movement->save();
        $pen->save();
        $inventory->delete();
        return response()->json(['status' => 'success', 'message' => 'Culled breeders']);
    }

    public function editFeedingRecord(Request $request)
    {
        $edit = BreederFeeding::where('id', $request->record_id)->firstOrFail();
        if($request->date_collected != null){
            $edit->date_collected = $request->date_collected;
        }
        if($request->amount_offered != null){
            $edit->amount_offered = $request->amount_offered;
        }
        if($request->amount_refused != null) {
            $edit->amount_refused = $request->amount_refused;
        }
        if($request->remarks != null){
            $edit->remarks = $request->remarks;
        }
        $edit->save();
        return response()->json(['status' => 'success', 'message' => 'Feeding record edited']);
    }

    public function deleteFeedingRecord ($record_id)
    {
        $record = BreederFeeding::where('id', $record_id)->firstOrFail();
        $record->forceDelete();
        return response()->json(['status' => 'success', 'message' => 'Feeding record deleted']);
    }

    public function editEggQualityRecord (Request $request)
    {
        $edit = EggQuality::where('id', $request->record_id)->firstOrFail();
        if($request->egg_quality_at) $edit->egg_quality_at = $request->egg_quality_at;
        if($request->weight) $edit->weight = $request->weight;
        if($request->color) $edit->color = $request->color;
        if($request->shape) $edit->shape = $request->shape;
        if($request->length) $edit->length = $request->length;
        if($request->width) $edit->width = $request->width;
        if($request->albumen_height) $edit->albumen_height = $request->albumen_height;
        if($request->albumen_weight) $edit->albumen_weight = $request->albumen_weight;
        if($request->yolk_weight) $edit->yolk_weight = $request->yolk_weight;
        if($request->yolk_color) $edit->yolk_color = $request->yolk_color;
        if($request->shell_weight) $edit->shell_weight = $request->shell_weight;
        if($request->thickness_top) $edit->thickness_top = $request->thickness_top;
        if($request->thickness_mid) $edit->thickness_mid = $request->thickness_mid;
        if($request->thickness_bot) $edit->thickness_bot = $request->thickness_bot;
        $edit->save();
        return response()->json(['status' => 'success', 'message' => 'Egg quality record edited']);
    }



    public function deleteEggQuality($record_id)
    {
        $record = EggQuality::where('id', $record_id)->firstOrFail();
        $record->forceDelete();
        return response()->json(['status' => 'success', 'message' => 'Egg Quality record deleted']);
    }

    public function deletePhenoMorphoRecord ($record_id)
    {
        $record = PhenoMorpho::where('id', $record_id)->firstOrFail();
        $record_value = PhenoMorphoValue::where('id', $record->values_id)->firstOrFail();
        $record->forceDelete();
        $record_value->forceDelete();
        return response()->json(['status' => 'success', 'message' => 'Pheno & Morpho record deleted']);
    }

    public function breederInventoryPage()
    {
        return view('chicken.breeder.breeder_inventory');
    }

    public function addAdditionalBreeder(Request $request)
    {
        $breeder_inventory = BreederInventory::where('id', $request->selected_breeder)->first();
        $breeder_pen = Pen::where('id', $breeder_inventory->pen_id)->first();
        if($breeder_pen->current_capacity == $breeder_pen->total_capacity || $breeder_pen->total_capacity < ($breeder_pen->current_capacity + $request->male + $request->female) ){
            return response()->json(['error' => 'Input number of male & female too large for the pen'], 400);
        }
        if($request->within){   
            $male_replacement_inventory = ReplacementInventory::where('id', $request->replacement_male_inventory)->first();
            $female_replacement_inventory = ReplacementInventory::where('id', $request->replacement_male_inventory)->first();
            $male_replacement_pen = Pen::where('id', $male_replacement_inventory->pen_id)->first();
            $female_replacement_pen = Pen::where('id', $female_replacement_inventory->pen_id)->first();
            
            if($request->male > $male_replacement_inventory->number_male){
                return response()->json(['error' => 'Input number of male too large for the current inventory'], 400);
            }

            if($request->female > $female_replacement_inventory->number_female){
                return response()->json(['error' => 'Input number of female too large for the current inventory'], 400);
            }

            if($request->male!=null){
                $breeder_inventory->number_male = $breeder_inventory->number_male + $request->male;
                $male_replacement_inventory->number_male = $male_replacement_inventory->number_male - $request->male;
            }
            
            if($request->female!=null){
                $breeder_inventory->number_female = $breeder_inventory->number_female + $request->female;
                $female_replacement_inventory->number_female = $female_replacement_inventory->number_female - $request->female;
            }

            if($request->male_wingband!=null){
                if($breeder_inventory->male_wingbands != null){
                    $wingbands = json_decode($breeder_inventory->male_wingbands);
                    $newwingbands = json_decode($this->convertToArray($request->male_wingband));
                    foreach($newwingbands as $value){
                        array_push($wingbands, $value);
                    }
                    unset($value);
                    $breeder_inventory->male_wingbands = json_encode($wingbands);
                }else{
                    $breeder_inventory->male_wingbands = $this->convertToArray($request->male_wingband);
                }

            }

            if($request->female_wingband!=null){
                if($breeder_inventory->female_wingbands != null){
                    $wingbands = json_decode($breeder_inventory->female_wingbands);
                    $newwingbands = json_decode($this->convertToArray($request->female_wingband));
                    foreach($newwingbands as $value){
                        array_push($wingbands, $value);
                    }
                    unset($value);
                    $breeder_inventory->female_wingbands = json_encode($wingbands);
                }else{
                    $breeder_inventory->female_wingbands = $this->convertToArray($request->female_wingband);
                }
            }
            $breeder_pen->current_capacity = $breeder_pen->current_capacity + ($request->male + $request->female);
            $male_replacement_pen->current_capacity = $male_replacement_pen->current_capacity - ($request->male + $request->female);
            $female_replacement_pen ->current_capacity = $female_replacement_pen->current_capacity - ($request->male + $request->female);
            $breeder_inventory->total = $breeder_inventory->number_male + $breeder_inventory->number_female;
            $male_replacement_inventory->total = $male_replacement_inventory->number_male + $male_replacement_inventory->number_female;
            $female_replacement_inventory->total = $female_replacement_inventory->number_male + $female_replacement_inventory->number_female;
            $breeder_pen->save();
            $male_replacement_inventory->save();
            $female_replacement_inventory->save();
            $male_replacement_pen->save();
            $female_replacement_pen->save();
            $breeder_inventory->save();
            return response()->json(['success' => 'Additional breeder added'], 200);
        }else{
            if($request->male!=null){
                $breeder_inventory->number_male = $breeder_inventory->number_male + $request->male;
            }
            
            if($request->female!=null){
                $breeder_inventory->number_female = $breeder_inventory->number_female + $request->female;
            }

            if($request->male_wingband!=null){
                if($breeder_inventory->male_wingbands != null){
                    $wingbands = json_decode($breeder_inventory->male_wingbands);
                    $newwingbands = json_decode($this->convertToArray($request->male_wingband));
                    foreach($newwingbands as $value){
                        array_push($wingbands, $value);
                    }
                    unset($value);
                    $breeder_inventory->male_wingbands = json_encode($wingbands);
                }else{
                    $breeder_inventory->male_wingbands = $this->convertToArray($request->male_wingband);
                }
            }

            if($request->female_wingband!=null){
                if($breeder_inventory->female_wingbands != null){
                    $wingbands = json_decode($breeder_inventory->female_wingbands);
                    $newwingbands = json_decode($this->convertToArray($request->female_wingband));
                    foreach($newwingbands as $value){
                        array_push($wingbands, $value);
                    }
                    unset($value);
                    $breeder_inventory->female_wingbands = json_encode($wingbands);
                }else{
                    $breeder_inventory->female_wingbands = $this->convertToArray($request->female_wingband);
                }
            }
            $breeder_pen->current_capacity = $breeder_pen->current_capacity + ($request->male + $request->female);
            $breeder_inventory->total = $breeder_inventory->number_male + $breeder_inventory->number_female;
            $breeder_pen->save();
            $breeder_inventory->save();
            return response()->json(['success' => 'Additional breeder added'], 200);
        }
    }
    
    /**
     * * HELPER FUNCTIONS FOR THE BREEDER CONTROLLER
     */
    public function fetchGenerations()
    {
        $generations = Generation::where('farm_id', Auth::user()->farm_id)->where('is_active', true)->get();
        return $generations;
    }
    public function fetchLines($generation_id)
    {
        $lines = Line::where('is_active', true)->where('generation_id', $generation_id)->get();
        return $lines;
    }

    public function fetchFamilies ($line_id)
    {
        $families = Family::where('is_active', true)->where('line_id', $line_id)->get();
        return $families;
    }

    public function fetchFemaleFamilies($line_id, $male_family)
    {
        if($line_id == "" && $male_family == ""){
            return null;
        }else{
            $females = Family::where('is_active', true)
                    ->where('line_id', $line_id)
                    ->where('id', '!=', $male_family)
                    ->get();
            return $females;
        }
    }
    public function fetchBreederPens()
    {
        $pens = Pen::where('farm_id', Auth::user()->farm_id)
        ->where('is_active', true)
        ->where('current_capacity', 0)
        ->where('type', 'layer')
        ->get();
        return $pens;
    }

    public function fetchBrooderPens()
    {
        $pens = Pen::where('farm_id', Auth::user()->farm_id)
        ->where('is_active', true)
        ->where('type', 'brooder')
        ->get();
        return $pens;
    }

    public function fetchReplacementInventories ($family_id, $gender)
    {
        $replacement = Replacement::where('family_id', $family_id)->first();
        if($replacement===null){
            return null;
        }
        return $replacement->getUpdatedInventories($gender);
    }

    public function fetchNewGenerations ($inventory) 
    {
        $breeder_inventory = BreederInventory::findOrFail($inventory);
        $breeder = Breeder::findOrFail($breeder_inventory->breeder_id);
        $generation = $breeder->getGeneration();
        $gen_list = Generation::where('farm_id', Auth::user()->farm_id)->where('numerical_generation', '>', $generation->numerical_generation)->get();
        return $gen_list;
    }

    public function getValidInventory ($inventory) 
    {
        $breeder = BreederInventory::where('id', $inventory)->first()->getBreederData();
        $inventories = ReplacementInventory::join('replacements', 'replacement_inventories.replacement_id', 'replacements.id')
                    ->where('replacements.family_id', $breeder->family_id)
                    ->select('replacement_inventories.*', 'replacement_inventories.id as inv_id', 'replacements.id as rep_id')
                    ->get();
        return $inventories;
    } 

    public function getValidAdditionalMaleBreeder ($inventory)
    {
        $inventory = BreederInventory::join('breeders', 'breeders.id', 'breeder_inventories.breeder_id')
                    ->where('breeder_inventories.id', $inventory)
                    ->select('breeder_inventories.batching_date', 'breeders.family_id', 'breeders.female_family_id')
                    ->first();
        $replacements = ReplacementInventory::join('replacements', 'replacements.id', 'replacement_inventories.replacement_id')
                    ->where('replacements.family_id', $inventory->family_id)
                    ->where('replacement_inventories.batching_date', $inventory->batching_date)
                    ->where('replacement_inventories.number_male', '>', 0)
                    ->select('replacement_inventories.*')
                    ->get();
        return $replacements;
    }

    public function getValidAdditionalFemaleBreeder ($inventory) 
    {
        $inventory = BreederInventory::join('breeders', 'breeders.id', 'breeder_inventories.breeder_id')
                    ->where('breeder_inventories.id', $inventory)
                    ->select('breeder_inventories.batching_date', 'breeders.family_id', 'breeders.female_family_id')
                    ->first();
        $replacements = ReplacementInventory::join('replacements', 'replacements.id', 'replacement_inventories.replacement_id')
                    ->where('replacements.family_id', $inventory->female_family_id)
                    ->where('replacement_inventories.batching_date', $inventory->batching_date)
                    ->where('replacement_inventories.number_female', '>', 0)
                    ->select('replacement_inventories.*')
                    ->get();
        return $replacements;
    }
    
    public function convertToArray($string)
    {
        $clean = str_replace(' ', '', $string);
        $array = explode(',', $clean);
        return json_encode($array);
    }
    public function convertToArrayWithoutEncode () 
    {
        $clean = str_replace(' ', '', $string);
        $array = explode(',', $clean);
        return $array;
    }

}
